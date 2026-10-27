<?php
/**
 * 数据库访问层（PDO 单例 + 常用快捷方法 + 事务封装）
 */
declare(strict_types=1);

namespace App;

use PDO;
use PDOException;
use PDOStatement;
use Throwable;

class Db
{
    /** @var PDO|null */
    private static $pdo = null;

    /** @var array */
    private static $cfg = [];

    /** @var array 记录表字段缓存，避免重复查询 information_schema */
    private static $columns = [];

    /** @var int 事务嵌套层级：只有最外层提交/回滚才真正生效 */
    private static $txDepth = 0;

    public static function init(array $cfg): void
    {
        self::$cfg = $cfg;
    }

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }
        $c   = self::$cfg;
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            (string) ($c['host'] ?? '127.0.0.1'),
            (int) ($c['port'] ?? 3306),
            (string) ($c['name'] ?? ''),
            (string) ($c['charset'] ?? 'utf8mb4')
        );
        try {
            self::$pdo = new PDO($dsn, (string) ($c['user'] ?? ''), (string) ($c['pass'] ?? ''), [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            throw new \RuntimeException(
                '数据库连接失败：' . $e->getMessage()
                . '（请检查 config/config.php 的数据库配置，或访问 install.php 重新安装）'
            );
        }
        return self::$pdo;
    }

    public static function query(string $sql, array $params = []): PDOStatement
    {
        if ($params && array_keys($params) !== range(0, count($params) - 1)) {
            throw new \RuntimeException('SQL 参数必须是顺序数组（不支持关联数组或命名占位符）');
        }
        $st = self::pdo()->prepare($sql);
        $st->execute(array_values($params));
        return $st;
    }

    /** @return array<int,array<string,mixed>> */
    public static function all(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public static function one(string $sql, array $params = [])
    {
        $row = self::query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** @return mixed */
    public static function value(string $sql, array $params = [])
    {
        $row = self::query($sql, $params)->fetch(PDO::FETCH_NUM);
        return $row === false ? null : $row[0];
    }

    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql  = 'INSERT INTO `' . $table . '` (`' . implode('`,`', $cols) . '`) VALUES ('
              . implode(',', array_fill(0, count($cols), '?')) . ')';
        self::query($sql, array_values($data));
        return (int) self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        if (!$data) {
            return 0;
        }
        $sets   = [];
        $values = [];
        foreach ($data as $col => $val) {
            if ($val instanceof Expr) {
                // 原子表达式，如 total_expense + 10.00
                $sets[] = '`' . $col . '`=' . $val->sql();
                continue;
            }
            $sets[]   = '`' . $col . '`=?';
            $values[] = $val;
        }
        $sql = 'UPDATE `' . $table . '` SET ' . implode(',', $sets) . ' WHERE ' . $where;
        return self::query($sql, array_merge($values, array_values($whereParams)))->rowCount();
    }

    public static function begin(): void
    {
        if (self::$txDepth === 0) {
            self::pdo()->beginTransaction();
        }
        self::$txDepth++;
    }

    public static function commit(): void
    {
        if (self::$txDepth <= 0) {
            return;
        }
        self::$txDepth--;
        if (self::$txDepth === 0 && self::pdo()->inTransaction()) {
            self::pdo()->commit();
        }
    }

    public static function rollback(): void
    {
        if (self::$txDepth <= 0) {
            return;
        }
        // 任意一层失败都整体回滚，避免"部分提交"
        self::$txDepth = 0;
        if (self::pdo()->inTransaction()) {
            self::pdo()->rollBack();
        }
    }

    /**
     * 事务包装：回调内抛异常自动回滚。
     * @return mixed
     */
    public static function tx(callable $fn)
    {
        self::begin();
        try {
            $result = $fn();
            self::commit();
            return $result;
        } catch (Throwable $e) {
            self::rollback();
            throw $e;
        }
    }

    /** 表是否存在（安装检测用） */
    public static function tableExists(string $table): bool
    {
        try {
            $n = self::value(
                'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
                [$table]
            );
            return (int) $n > 0;
        } catch (Throwable $e) {
            return false;
        }
    }
}
