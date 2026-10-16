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
        $actions = [];

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
}
