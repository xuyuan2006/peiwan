<?php
/**
 * App 令牌鉴权。
 *
 * 设计要点：
 *  - App 端不使用 PHP Session/Cookie（会话文件锁会把同一客户端的并发请求串行化），
 *    改为 Authorization: Bearer <token>。
 *  - 库里只存 SHA-256 摘要，拖库也拿不到可用令牌。
 *  - 鉴权成功后把 uid 写进 $_SESSION 数组（不启动会话，不发 Cookie），
 *    这样既有代码里的 Auth::id() / is_companion() / 全部 Service 层都能零改动复用。
 */
declare(strict_types=1);

namespace App\Api;

use App\Db;

class TokenAuth
{
    /** 令牌有效期（天） */
    public const TTL_DAYS = 30;

    /** 剩余不足这么多天时自动续期 */
    public const RENEW_DAYS = 15;

    /** 同一账号最多保留多少个有效令牌 */
    public const MAX_TOKENS = 10;

    /** @var array<string,mixed>|null */
    private static $user = null;

    private static $loaded = false;

    private static $tokenId = 0;

    public static function boot(): void
    {
        if (self::$loaded) {
            return;
        }
        self::$loaded = true;

        $token = self::readToken();
        if ($token === '') {
            return;
        }

        $hash = self::hash($token);
        $row  = Db::one('SELECT * FROM api_tokens WHERE token_hash = ? AND revoked_at IS NULL LIMIT 1', [$hash]);
        if (!$row) {
            return;
        }

        // 过期令牌直接作废，不给续期机会
        if (!empty($row['expire_at']) && strtotime((string) $row['expire_at']) < time()) {
            self::revokeByHash($hash);
            return;
        }

        $user = Db::one('SELECT * FROM users WHERE id = ? LIMIT 1', [(int) $row['user_id']]);
        if (!$user || (int) $user['status'] !== 1) {
            self::revokeByHash($hash);
            return;
        }

        self::$user      = $user;
        self::$tokenId   = (int) $row['id'];

        // 把登录态注入既有代码读取的位置（$_SESSION 未启动会话时就是一个普通数组）
        $_SESSION['uid'] = (int) $user['id'];

        // 记录活跃时间 + 滑动续期，失败不影响业务
        try {
            $data   = ['last_used_at' => now(), 'last_ip' => client_ip(), 'updated_at' => now()];
            $expire = (string) ($row['expire_at'] ?? '');
            // expire_at 为空表示「永不过期」（见 api_upgrade.sql 的字段注释），不要去给它补期限
            if ($expire !== '' && strtotime($expire) < time() + self::RENEW_DAYS * 86400) {
                $data['expire_at'] = date('Y-m-d H:i:s', time() + self::TTL_DAYS * 86400);
            }
            Db::update('api_tokens', $data, 'id = ?', [self::$tokenId]);
        } catch (\Throwable $e) {
            // 忽略
        }
    }

    /** 从各种可能的位置取出 Bearer 令牌 */
    private static function readToken(): string
    {
        $header = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');

        // Apache + CGI/FastCGI 常常拿不到 Authorization，走这两个兜底
        if ($header === '') {
            $header = (string) ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        }
        if ($header === '' && function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            if (is_array($headers)) {
                foreach ($headers as $k => $v) {
                    if (strcasecmp((string) $k, 'Authorization') === 0) {
                        $header = (string) $v;
                        break;
                    }
                }
            }
        }

        if (stripos($header, 'bearer ') === 0) {
            $token = trim(substr($header, 7));
            if ($token !== '') {
                return $token;
            }
        }

        // 备用头：某些环境上边几个都拿不到
        foreach (['HTTP_X_TOKEN', 'HTTP_X_APP_TOKEN'] as $key) {
            $v = trim((string) ($_SERVER[$key] ?? ''));
            if ($v !== '') {
                return $v;
            }
        }
        return '';
    }

    private static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /** api_tokens 表是否已建（升级漏执行 SQL 时给出可读提示，而不是抛数据库异常） */
    public static function ready(): bool
    {
        static $ok = null;
        if ($ok === null) {
            try {
                $ok = Db::tableExists('api_tokens');
            } catch (\Throwable $e) {
                $ok = false;
            }
        }
        return $ok;
    }

    /** 未建表时调用：抛出带修复指引的异常 */
    public static function assertReady(): void
    {
        if (!self::ready()) {
            throw new ApiException(
                '服务端尚未升级接口表结构，请在数据库中执行 sql/api_upgrade.sql 后再试',
                503
            );
        }
    }

    /**
     * 签发令牌（登录成功后调用）
     * @return array{token:string,token_id:int,expire_at:string}
     */
    public static function issue(int $userId, string $device = '', string $platform = 'android', string $appVersion = ''): array
    {
        $token  = bin2hex(random_bytes(32));
        $expire = date('Y-m-d H:i:s', time() + self::TTL_DAYS * 86400);

        $id = Db::insert('api_tokens', [
            'user_id'      => $userId,
            'token_hash'   => self::hash($token),
            'platform'     => mb_substr($platform !== '' ? $platform : 'android', 0, 20),
            'device'       => mb_substr($device, 0, 100),
            'app_version'  => mb_substr($appVersion, 0, 20),
            'last_ip'      => client_ip(),
            'last_used_at' => now(),
            'expire_at'    => $expire,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        self::prune($userId);

        return ['token' => $token, 'token_id' => $id, 'expire_at' => $expire];
    }

    /** 令牌过多时撤销最旧的，避免无限堆积 */
    private static function prune(int $userId, int $keep = self::MAX_TOKENS): void
    {
        try {
            $rows = Db::all(
                'SELECT id FROM api_tokens WHERE user_id = ? AND revoked_at IS NULL ORDER BY id DESC',
                [$userId]
            );
            if (count($rows) <= $keep) {
                return;
            }
            $ids = [];
            foreach (array_slice($rows, $keep) as $r) {
                $ids[] = (int) $r['id'];
            }
            if (!$ids) {
                return;
            }
            $ph = implode(',', array_fill(0, count($ids), '?'));
            Db::query(
                'UPDATE api_tokens SET revoked_at = ?, updated_at = ? WHERE id IN (' . $ph . ')',
                array_merge([now(), now()], $ids)
            );
        } catch (\Throwable $e) {
            // 忽略
        }
    }

    private static function revokeByHash(string $hash): void
    {
        try {
            Db::query(
                'UPDATE api_tokens SET revoked_at = ?, updated_at = ? WHERE token_hash = ? AND revoked_at IS NULL',
                [now(), now(), $hash]
            );
        } catch (\Throwable $e) {
            // 忽略
        }
    }

    public static function id(): int
    {
        return self::$user ? (int) self::$user['id'] : 0;
    }

    public static function tokenId(): int
    {
        return self::$tokenId;
    }

    public static function isAdmin(): bool
    {
        return self::$user !== null && (string) (self::$user['role'] ?? '') === 'admin';
    }

    /** 要求登录，返回当前用户行；未登录抛 401 */
    public static function requireLogin(): array
    {
        // 表缺失时要给出「请执行 sql/api_upgrade.sql」的 503，
        // 而不是让 boot() 里被吞掉的数据库异常伪装成「登录已失效」的 401。
        self::assertReady();

        if (self::$user === null) {
            throw ApiException::unauthorized();
        }
        return self::$user;
    }

    /** 要求「已通过审核的陪玩」身份 */
    public static function requireCompanion(): array
    {
        $user = self::requireLogin();
        $uid  = (int) $user['id'];
        $row  = Db::one('SELECT audit_status FROM companion_profiles WHERE user_id = ? LIMIT 1', [$uid]);
        if (!$row || (int) $row['audit_status'] !== 1) {
            throw ApiException::forbidden('你还不是已通过审核的陪玩，请先提交入驻申请');
        }
        return $user;
    }

    /** 退出当前设备：只撤销这一个令牌 */
    public static function revokeCurrent(): void
    {
        if (self::$tokenId <= 0) {
            return;
        }
        try {
            Db::update('api_tokens', [
                'revoked_at' => now(),
                'updated_at' => now(),
            ], 'id = ?', [self::$tokenId]);
        } catch (\Throwable $e) {
            // 忽略
        }
    }

    /** 退出全部设备（改密码后建议调用） */
    public static function revokeAll(int $userId): void
    {
        try {
            Db::query(
                'UPDATE api_tokens SET revoked_at = ?, updated_at = ? WHERE user_id = ? AND revoked_at IS NULL',
                [now(), now(), $userId]
            );
        } catch (\Throwable $e) {
            // 忽略
        }
    }
}
