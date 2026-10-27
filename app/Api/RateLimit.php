<?php
/**
 * 接口限流（固定窗口计数）。
 *
 * 用途：防登录爆破、防验证码轰炸、防下单刷单。
 * 依赖 api_throttle 表；表缺失或数据库异常时**自动放行**，绝不因为限流把主流程搞挂。
 */
declare(strict_types=1);

namespace App\Api;

use App\Db;

class RateLimit
{
    /**
     * 先检查再计数（适合「每次调用都要限速」的场景，如发验证码）
     */
    public static function hit(string $key, int $max, int $windowSeconds, string $message = ''): void
    {
        $key = self::key($key);
        $row = self::row($key);
        $now = time();

        if ($row === null) {
            return; // 表不可用，放行
        }

        if ($row['window_start'] <= 0 || ($now - $row['window_start']) >= $windowSeconds) {
            self::write($key, 1, $now);
            return;
        }

        $hits = $row['hits'] + 1;
        self::write($key, $hits, $row['window_start']);

        if ($hits > $max) {
            $left = max(1, $windowSeconds - ($now - $row['window_start']));
            throw ApiException::tooMany(
                $message !== '' ? $message : ('操作过于频繁，请 ' . $left . ' 秒后再试')
            );
        }
    }

    /**
     * 只检查是否已超限，不计数（适合「成功不计数、失败才计数」的登录场景）
     */
    public static function assert(string $key, int $max, int $windowSeconds, string $message = ''): void
    {
        $key = self::key($key);
        $row = self::row($key);
        if ($row === null) {
            return;
        }
        $now = time();
        if ($row['window_start'] <= 0 || ($now - $row['window_start']) >= $windowSeconds) {
            return;
        }
        if ($row['hits'] >= $max) {
            $left = max(1, $windowSeconds - ($now - $row['window_start']));
            throw ApiException::tooMany(
                $message !== '' ? $message : ('尝试次数过多，请 ' . $left . ' 秒后再试')
            );
        }
    }

    /** 记一次失败 */
    public static function add(string $key, int $windowSeconds): void
    {
        $key = self::key($key);
        $row = self::row($key);
        $now = time();
        if ($row === null) {
            return;
        }
        if ($row['window_start'] <= 0 || ($now - $row['window_start']) >= $windowSeconds) {
            self::write($key, 1, $now);
            return;
        }
        self::write($key, $row['hits'] + 1, $row['window_start']);
    }

    /** 成功后清零（登录成功时调用） */
    public static function clear(string $key): void
    {
        try {
            Db::query('DELETE FROM api_throttle WHERE k = ?', [self::key($key)]);
        } catch (\Throwable $e) {
            // 忽略
        }
    }

    private static function key(string $key): string
    {
        return mb_substr($key, 0, 160);
    }

    /**
     * @return array{hits:int,window_start:int}|null  null 表示限流不可用（放行）
     */
    private static function row(string $key): ?array
    {
        try {
            $row = Db::one('SELECT hits, window_start FROM api_throttle WHERE k = ? LIMIT 1', [$key]);
        } catch (\Throwable $e) {
            return null;
        }
        if (!$row) {
            return ['hits' => 0, 'window_start' => 0];
        }
        return ['hits' => (int) $row['hits'], 'window_start' => (int) $row['window_start']];
    }

    private static function write(string $key, int $hits, int $windowStart): void
    {
        try {
            Db::query(
                'INSERT INTO api_throttle (k, hits, window_start, updated_at) VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE hits = VALUES(hits), window_start = VALUES(window_start), updated_at = VALUES(updated_at)',
                [$key, $hits, $windowStart, now()]
            );
        } catch (\Throwable $e) {
            // 忽略
        }
    }
}
