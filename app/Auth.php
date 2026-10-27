<?php
/**
 * 登录态管理：前台用户与后台管理员共用 users 表，用 role 区分。
 */
declare(strict_types=1);

namespace App;

class Auth
{
    /** @var array<string,mixed>|null */
    private static $user = null;

    private static $loaded = false;

    public static function boot(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'httponly' => true,
                'secure'   => $secure,
                'samesite' => 'Lax',
            ]);
            session_name('PEIWAN_SID');
            session_start();
        }
    }

    /** @return array<string,mixed>|null */
    public static function user()
    {
        if (self::$loaded) {
            return self::$user;
        }
        self::$loaded = true;

        $uid = (int) ($_SESSION['uid'] ?? 0);
        if ($uid <= 0) {
            return self::$user = null;
        }
        $row = Db::one('SELECT * FROM users WHERE id = ? LIMIT 1', [$uid]);
        if (!$row || (int) $row['status'] !== 1) {
            unset($_SESSION['uid']);
            return self::$user = null;
        }
        return self::$user = $row;
    }

    public static function id(): int
    {
        $u = self::user();
        return $u ? (int) $u['id'] : 0;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function isAdmin(): bool
    {
        $u = self::user();
        return $u !== null && ($u['role'] ?? '') === 'admin';
    }

    public static function isCompanion(): bool
    {
        $u = self::user();
        return $u !== null && in_array(($u['role'] ?? ''), ['companion'], true);
    }

    /**
     * 生成一个未被占用的账号（前台注册、接口注册共用）。
     *
     * 注册不再让用户自己想账号：昵称 + 手机号 + 邮箱已经足够标识一个人，
     * 多一个「账号」既增加填写负担，又常常因为重名被驳回。
     * 账号在库里仍然保留（唯一键、后台检索、兼容旧数据），只是改由服务端分配。
     *
     * 形如 u260915A3F91C2D4E5B —— u + 6 位日期 + 12 位十六进制，共 19 位。
     * 每天 16^12 ≈ 2.8×10^14 种组合，实际用不完；users.username 是 VARCHAR(50)，
     * 长度也留足了余量。
     */
    public static function generateUsername(): string
    {
        for ($i = 0; $i < 30; $i++) {
            $candidate = 'u' . date('ymd') . strtoupper(bin2hex(random_bytes(6)));
            if (!Db::one('SELECT id FROM users WHERE username = ? LIMIT 1', [$candidate])) {
                return $candidate;
            }
        }
        // 极端兜底：加长随机串，仍在 VARCHAR(50) 之内
        return 'u' . date('ymdHis') . strtoupper(bin2hex(random_bytes(8)));
    }

    /**
     * 生成一个未被占用的 6 位用户号 —— 个人中心里展示给用户的那个「ID」。
     *
     * 为什么不直接把自增主键当 ID：自增 id 会暴露「平台一共有多少用户」，
     * 而且顺手就能推出注册先后。随机 6 位（100000~999999）有 90 万种取值，
     * 配合 users.no 上的唯一索引，对本站量级完全够用。
     */
    public static function generateUserNo(): int
    {
        // 升级脚本还没跑（users.no 列不存在）时返回 0，调用方会跳过这个字段。
        // 绝不能让「少了一列」把注册整条链路打挂 —— 列没加只是没有展示号，注册照常。
        if (!self::hasNoColumn()) {
            return 0;
        }

        for ($i = 0; $i < 50; $i++) {
            $candidate = random_int(100000, 999999);
            // 这个号会同时用作「展示 ID」和「账号」，所以两列都要不能撞：
            // no 撞了会违反唯一索引；username 撞了会和历史用户重名。
            if (!Db::one('SELECT id FROM users WHERE no = ? LIMIT 1', [$candidate])
                && !Db::one('SELECT id FROM users WHERE username = ? LIMIT 1', [(string) $candidate])) {
                return $candidate;
            }
        }

        // 兜底：随机撞了 50 次都没空位（极度罕见），改成顺序找一个空号
        for ($n = 100000; $n <= 999999; $n++) {
            if (!Db::one('SELECT id FROM users WHERE no = ? LIMIT 1', [$n])) {
                return $n;
            }
        }

        return random_int(100000, 999999);
    }

    /**
     * 没有 users.no 列时的兜底展示号：用自增 id 通过一个唯一映射算出来。
     *
     * ⚠️ 这里的乘数与 sql/api_upgrade.sql 里回填老数据用的**完全相同**
     * （76271 与 900000 互质，所以不同 id 必得不同结果）。
     * 这样做的意义是：升级脚本跑之前先按公式算，跑之后从列里读，
     * **用户看到的号前后一致，不会突然变一个数**。
     */
    public static function fallbackUserNo(int $id): int
    {
        if ($id <= 0) {
            return 0;
        }
        return 100000 + (($id * 76271) % 900000);
    }

    /** users.no 列是否存在（升级脚本没跑之前不能引用它）。结果缓存，一次请求只查一次。 */
    private static function hasNoColumn(): bool
    {
        static $has = null;
        if ($has !== null) {
            return $has;
        }
        try {
            $has = Db::one("SHOW COLUMNS FROM `users` LIKE 'no'") !== null;
        } catch (\Throwable $e) {
            $has = false;
        }
        return $has;
    }

    /**
     * 组装注册时写入 users 表的字段（网页端与接口共用）。
     *
     * users.no 是后加的列：升级脚本没跑时 generateUserNo() 会返回 0，
     * 这里就整列跳过 —— 「少一列」绝不该让注册报 SQL 错误。
     */
    public static function newUserFields(
        string $username,
        string $password,
        string $nickname,
        string $phone,
        string $email,
        int $gender
    ): array {
        $fields = [
            'username'   => $username,
            'password'   => password_hash($password, PASSWORD_DEFAULT),
            'nickname'   => mb_substr($nickname, 0, 50),
            'phone'      => $phone,
            'email'      => $email,
            'gender'     => in_array($gender, [1, 2], true) ? $gender : 0,
            'role'       => 'user',
            'status'     => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $no = self::generateUserNo();
        if ($no > 0) {
            $fields['no'] = $no;
            // 账号也用同一个 6 位数：后台/客服看到的和用户自己看到的「ID」是同一个值，
            // 不会出现「后台显示一串 u261015…、前台显示 6 位数」这种对不上的情况。
            $fields['username'] = (string) $no;
        }

        return $fields;
    }

    public static function attempt(string $account, string $password): bool
    {
        // 账号 / 手机号 / 邮箱 三种都能登录。
        // 账号是服务端生成的（形如 u260915A3F91C2D4E5B），用户记不住，
        // 所以实际入口是手机号和邮箱，账号主要用于后台检索。
        $row = Db::one(
            "SELECT * FROM users WHERE username = ? OR (phone <> '' AND phone = ?) OR (email <> '' AND email = ?) LIMIT 1",
            [$account, $account, strtolower($account)]
        );
        if (!$row) {
            return false;
        }
        if ((int) $row['status'] !== 1) {
            return false;
        }
        if (!password_verify($password, (string) $row['password'])) {
            return false;
        }
        self::login((int) $row['id']);
        return true;
    }

    public static function login(int $uid): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['uid'] = $uid;
        self::$user      = null;
        self::$loaded    = false;
        Db::update('users', [
            'last_login_at' => now(),
            'last_login_ip' => client_ip(),
        ], 'id = ?', [$uid]);
    }

    public static function logout(): void
    {
        unset($_SESSION['uid']);
        self::$user   = null;
        self::$loaded = false;
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            $_SESSION['_intended'] = $_SERVER['REQUEST_URI'] ?? '';
            flash('warning', '请先登录后再操作');
            redirect(url('auth/login'));
        }
    }

    public static function requireAdmin(): void
    {
        if (!self::check()) {
            $_SESSION['_intended'] = $_SERVER['REQUEST_URI'] ?? '';
            flash('warning', '请先登录后台');
            redirect(admin_url('auth/login'));
        }
        if (!self::isAdmin()) {
            abort(403, '当前账号没有后台管理权限');
        }
    }

    /** 管理员操作日志 */
    public static function logAdmin(string $action, string $detail = ''): void
    {
        try {
            Db::insert('admin_logs', [
                'admin_id'   => self::id(),
                'action'     => mb_substr($action, 0, 100),
                'detail'     => mb_substr($detail, 0, 1000),
                'ip'         => client_ip(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // 日志失败不影响主流程
        }
    }
}
