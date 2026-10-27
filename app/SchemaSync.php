<?php
/**
 * 轻量结构同步：以 sql/schema.sql 为唯一事实来源，对比线上库，
 * 自动补齐缺失的「字段」和「索引」（幂等，可重复执行）。
 *
 * 用法：
 *   App\SchemaSync::diff($pdo, $schemaFile)  // 只查看缺什么
 *   App\SchemaSync::sync($pdo, $schemaFile)  // 补齐并返回执行记录
 *
 * 不依赖 bootstrap（只用到 PDO），所以 install.php / check.php 这类独立入口也能直接用。
 */
declare(strict_types=1);

namespace App;

class SchemaSync
{
    /** 解析 schema.sql，返回 [表名 => ['columns' => [列名 => 定义], 'keys' => [索引名 => 定义]]] */
    private static function parse(string $schemaFile): array
    {
        $sql = ltrim((string) file_get_contents($schemaFile), "\xEF\xBB\xBF");
        $tables = [];

        if (!preg_match_all('/CREATE TABLE IF NOT EXISTS `([A-Za-z0-9_]+)` \((.*?)\n\) ENGINE[^;]*;/is', $sql, $matches, PREG_SET_ORDER)) {
            return $tables;
        }

        foreach ($matches as $m) {
            $table = $m[1];
            $body  = $m[2];
            $createSql = trim($m[0]);
            $cols  = [];
            $keys  = [];

            foreach (preg_split('/\r?\n/', $body) as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                // 字段定义：以 `列名` 开头
                if (preg_match('/^`([A-Za-z0-9_]+)`\s+(.+?),?$/', $line, $c)) {
                    $cols[$c[1]] = rtrim($c[2], ',');
                    continue;
                }
                // 索引定义：KEY / UNIQUE KEY
                if (preg_match('/^(UNIQUE\s+)?KEY\s+`([A-Za-z0-9_]+)`\s*\(([^)]+)\)/i', $line, $k)) {
                    $keys[$k[2]] = [
                        'unique' => trim((string) $k[1]) !== '',
                        'cols'   => trim($k[3]),
                    ];
                }
            }

            $tables[$table] = ['columns' => $cols, 'keys' => $keys, 'create_sql' => $createSql];
        }

        return $tables;
    }

    /**
     * 对比出缺失的字段与索引
     * @return array{missing_tables:array<int,array{table:string,sql:string}>,columns:array<int,array{table:string,column:string,def:string}>,keys:array<int,array{table:string,key:string,def:string,unique:bool}>}
     */
    public static function diff(\PDO $pdo, string $schemaFile): array
    {
        $schema  = self::parse($schemaFile);
        $missing = ['missing_tables' => [], 'columns' => [], 'keys' => []];

        foreach ($schema as $table => $def) {
            $exists = $pdo->query("SHOW TABLES LIKE '" . str_replace("'", "", $table) . "'")->fetchAll();
            if (!$exists) {
                $missing['missing_tables'][] = ['table' => $table, 'sql' => (string) ($def['create_sql'] ?? '')];
                continue;
            }

            $have = [];
            foreach ($pdo->query('SHOW COLUMNS FROM `' . $table . '`') as $row) {
                $have[] = (string) $row['Field'];
            }
            foreach ($def['columns'] as $column => $definition) {
                if (!in_array($column, $have, true)) {
                    $missing['columns'][] = ['table' => $table, 'column' => $column, 'def' => $definition];
                }
            }

            $haveKeys = [];
            foreach ($pdo->query('SHOW INDEX FROM `' . $table . '`') as $row) {
                $haveKeys[] = (string) $row['Key_name'];
            }
            foreach ($def['keys'] as $keyName => $keyDef) {
                if (!in_array($keyName, $haveKeys, true)) {
                    $missing['keys'][] = [
                        'table'  => $table,
                        'key'    => $keyName,
                        'def'    => $keyDef['cols'],
                        'unique' => (bool) $keyDef['unique'],
                    ];
                }
            }
        }

        return $missing;
    }

    /**
     * 补齐缺失字段与索引
     * @return array<int,string> 执行记录
     */
    public static function sync(\PDO $pdo, string $schemaFile): array
    {
        $diff    = self::diff($pdo, $schemaFile);
        $actions = self::fixColumnTypes($pdo);

        foreach ($diff['missing_tables'] as $item) {
            if (!empty($item['sql'])) {
                // 缺整张表时直接按 schema.sql 建表（升级安装新表用）
                $pdo->exec($item['sql']);
                $actions[] = '已创建表 ' . $item['table'];
            } else {
                $actions[] = '表 ' . $item['table'] . ' 不存在且无法从 schema.sql 解析建表语句，请手动创建';
            }
        }
        foreach ($diff['columns'] as $item) {
            $sql = 'ALTER TABLE `' . $item['table'] . '` ADD COLUMN `' . $item['column'] . '` ' . $item['def'];
            $pdo->exec($sql);
            $actions[] = '已补齐字段 ' . $item['table'] . '.' . $item['column'];
        }
        foreach ($diff['keys'] as $item) {
            $sql = 'ALTER TABLE `' . $item['table'] . '` ADD ' . ($item['unique'] ? 'UNIQUE ' : '')
                 . 'KEY `' . $item['key'] . '` (' . $item['def'] . ')';
            $pdo->exec($sql);
            $actions[] = '已补齐索引 ' . $item['table'] . '.' . $item['key'];
        }

        return $actions;
    }

    /**
     * 必要的「类型放宽」修正（幂等）。
     *
     * 背景：settings.v 早期是 VARCHAR(500)，而支付宝私钥/公钥 PEM 有 1600+ 字符，
     * 保存「支付设置」时会直接报
     *   SQLSTATE[22001]: 1406 Data too long for column 'v'
     * （易支付密钥短，所以一直没人发现）。
     *
     * sql/api_upgrade.sql 里早就有这条 ALTER，但**全新安装**走的是 sql/schema.sql，
     * 那里的定义没跟上，于是新站的 `v` 还是 VARCHAR(500)。
     * 这里在同步结构时顺手修正一次，以后升级就不用再记着手动执行。
     *
     * @return array<int,string>
     */
    private static function fixColumnTypes(\PDO $pdo): array
    {
        $actions = [];

        // 1) settings.v 必须是 TEXT：
        //    支付宝私钥/公钥 PEM 有 1600+ 字符，VARCHAR(500) 存不下，
        //    后台保存「支付设置」会报 1406 Data too long for column 'v'。
        try {
            $row  = $pdo->query("SHOW COLUMNS FROM `settings` LIKE 'v'")->fetch(\PDO::FETCH_ASSOC);
            $type = is_array($row) ? strtolower((string) ($row['Type'] ?? '')) : '';
            if ($type !== '' && strpos($type, 'text') === false) {
                $pdo->exec('ALTER TABLE `settings` MODIFY COLUMN `v` TEXT NOT NULL');
                $actions[] = '已把 settings.v 从 ' . $type . ' 放宽为 TEXT（支付宝私钥需要，避免 1406 报错）';
            }
        } catch (\Throwable $e) {
            // 没权限改表结构时忽略
        }

        // 2) recharge_orders.pay_url 要够长：
        //    支付宝电脑/手机网站支付的跳转地址（带 344 字符签名）通常超过 500 字符，
        //    原来存进 VARCHAR(500) 会被截断，导致「重新获取支付链接」拿到的是坏地址。
        try {
            $row  = $pdo->query("SHOW COLUMNS FROM `recharge_orders` LIKE 'pay_url'")->fetch(\PDO::FETCH_ASSOC);
            $type = is_array($row) ? strtolower((string) ($row['Type'] ?? '')) : '';
            if (preg_match('/varchar\((\d+)\)/', $type, $m) && (int) $m[1] < 1000) {
                $pdo->exec("ALTER TABLE `recharge_orders` MODIFY COLUMN `pay_url` VARCHAR(1000) NOT NULL DEFAULT ''");
                $actions[] = '已把 recharge_orders.pay_url 从 ' . $type . ' 放宽到 VARCHAR(1000)（支付宝跳转地址很长）';
            }
        } catch (\Throwable $e) {
            // 忽略
        }

        return $actions;
    }
}
