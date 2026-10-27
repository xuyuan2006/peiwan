<?php
/**
 * 工单部门：账号、层级权限、工号生成、登录态。
 *
 * 四层：超级管理员（= 原后台 users.role='admin'）
 *      > 主管（经理）manager > 组长 leader > 客服 agent
 *
 * 权限规则（对应需求）：
 *   - 超管：管理所有（建三种角色、改任何人、分配组长）
 *   - 主管：管理「组长」和「客服」；可以把客服分配到某个组长名下
 *   - 组长：管理自己组里的客服（重置密码、查看），不能建号、不能停用
 *   - 客服：没有任何管理权限
 *
 * 这套账号**不占用 users 表**，登录态放在 $_SESSION['kf_staff_id']，
 * 且只能访问工单模块，进其它后台页面一律「权限不足」。
 */
declare(strict_types=1);

namespace App;

class KfStaff
{
    /** 角色 => 名称 */
    public static function roles(): array
    {
        return [
            'manager' => '经理',
            'leader'  => '组长',
            'agent'   => '客服',
        ];
    }

    public static function roleText(string $role): string
    {
        $map = self::roles();
        return $map[$role] ?? '未知';
    }

    /** 层级值：越大越高 */
    public static function rank(string $role): int
    {
        $map = ['super' => 100, 'manager' => 80, 'leader' => 60, 'agent' => 20];
        return $map[$role] ?? 0;
    }

    // ---------------------------------------------------------------- 依赖检查

    /**
     * 自动补齐工单部门依赖的表/字段（幂等，只补缺的，不动数据）。
     *
     * 这样就不用记着手动执行 sql/kf_desk.sql 了：
     * 第一次打开工单模块时会自己建好；数据库账号没有建表权限时，
     * 会退回到「告诉你缺什么」，不会白屏。
     *
     * @return array<int,string> 本次做了什么
     */
    public static function ensureSchema(): array
    {
        static $done = null;
        if ($done !== null) {
            return $done;
        }
        $done = [];

        // 1) 工单部门账号表
        try {
            if (!Db::tableExists('kf_staff')) {
                Db::pdo()->exec(
                    "CREATE TABLE IF NOT EXISTS `kf_staff` (
                      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                      `staff_no` VARCHAR(20) NOT NULL DEFAULT '',
                      `account` VARCHAR(50) NOT NULL DEFAULT '',
                      `password` VARCHAR(255) NOT NULL DEFAULT '',
                      `name` VARCHAR(50) NOT NULL DEFAULT '',
                      `email` VARCHAR(100) NOT NULL DEFAULT '',
                      `phone` VARCHAR(20) NOT NULL DEFAULT '',
                      `role` VARCHAR(20) NOT NULL DEFAULT 'agent',
                      `leader_id` INT UNSIGNED NOT NULL DEFAULT 0,
                      `status` TINYINT NOT NULL DEFAULT 1,
                      `created_by` INT UNSIGNED NOT NULL DEFAULT 0,
                      `last_login_at` DATETIME DEFAULT NULL,
                      `last_login_ip` VARCHAR(45) NOT NULL DEFAULT '',
                      `created_at` DATETIME NOT NULL,
                      `updated_at` DATETIME NOT NULL,
                      PRIMARY KEY (`id`),
                      UNIQUE KEY `uk_account` (`account`),
                      UNIQUE KEY `uk_no` (`staff_no`),
                      KEY `idx_role` (`role`, `status`),
                      KEY `idx_leader` (`leader_id`, `status`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='工单部门账号'"
                );
                $done[] = '已创建表 kf_staff';
            }
        } catch (\Throwable $e) {
            // 没有建表权限：下面会把它列为缺失并提示
        }

        // 2) tickets.staff_id + 索引
        try {
            if (!self::columnExists('tickets', 'staff_id')) {
                Db::pdo()->exec("ALTER TABLE `tickets` ADD COLUMN `staff_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '处理客服 kf_staff.id，0=公海'");
                $done[] = '已为 tickets 增加字段 staff_id';
            }
        } catch (\Throwable $e) {
            // 忽略
        }
        try {
            if (!self::indexExists('tickets', 'idx_staff')) {
                Db::pdo()->exec('ALTER TABLE `tickets` ADD KEY `idx_staff` (`staff_id`, `status`)');
                $done[] = '已为 tickets 增加索引 idx_staff';
            }
        } catch (\Throwable $e) {
            // 忽略
        }

        // 3) ticket_replies.sender_name
        try {
            if (!self::columnExists('ticket_replies', 'sender_name')) {
                Db::pdo()->exec("ALTER TABLE `ticket_replies` ADD COLUMN `sender_name` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '回复人显示名（工单部门用）'");
                $done[] = '已为 ticket_replies 增加字段 sender_name';
            }
        } catch (\Throwable $e) {
            // 忽略
        }

        // 4) kf_staff.manager_id（组长挂在哪个经理下面）
        try {
            if (!self::columnExists('kf_staff', 'manager_id')) {
                Db::pdo()->exec("ALTER TABLE `kf_staff` ADD COLUMN `manager_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '组长所属经理 kf_staff.id'");
                $done[] = '已为 kf_staff 增加字段 manager_id';
            }
        } catch (\Throwable $e) {
            // 忽略
        }

        // 5) 已读记录表（每个客服对每张单读到哪条回复）
        try {
            if (!Db::tableExists('kf_ticket_reads')) {
                Db::pdo()->exec(
                    "CREATE TABLE IF NOT EXISTS `kf_ticket_reads` (
                      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                      `staff_id` INT UNSIGNED NOT NULL DEFAULT 0,
                      `ticket_id` INT UNSIGNED NOT NULL DEFAULT 0,
                      `last_reply_id` INT UNSIGNED NOT NULL DEFAULT 0,
                      `read_at` DATETIME DEFAULT NULL,
                      PRIMARY KEY (`id`),
                      UNIQUE KEY `uk_staff_ticket` (`staff_id`, `ticket_id`),
                      KEY `idx_staff` (`staff_id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='工单已读记录'"
                );
                $done[] = '已创建表 kf_ticket_reads（未读/已读）';
            }
        } catch (\Throwable $e) {
            // 忽略
        }

        // 6) 附件列（图片 / 视频 / 文档，存 JSON 数组）
        try {
            if (!self::columnExists('tickets', 'attachments')) {
                Db::pdo()->exec("ALTER TABLE `tickets` ADD COLUMN `attachments` TEXT NULL COMMENT '附件（JSON 数组）'");
                $done[] = '已为 tickets 增加字段 attachments';
            }
        } catch (\Throwable $e) {
            // 忽略
        }
        try {
            if (!self::columnExists('ticket_replies', 'attachments')) {
                Db::pdo()->exec("ALTER TABLE `ticket_replies` ADD COLUMN `attachments` TEXT NULL COMMENT '附件（JSON 数组）'");
                $done[] = '已为 ticket_replies 增加字段 attachments';
            }
        } catch (\Throwable $e) {
            // 忽略
        }

        return $done;
    }

    public static function columnExists(string $table, string $column): bool
    {
        try {
            $n = Db::value(
                'SELECT COUNT(*) FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
                [$table, $column]
            );
            return (int) $n > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function indexExists(string $table, string $index): bool
    {
        try {
            $n = Db::value(
                'SELECT COUNT(*) FROM information_schema.statistics
                 WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
                [$table, $index]
            );
            return (int) $n > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * 检查工单部门依赖的表/字段是否就绪（没执行 sql/kf_desk.sql 时用）
     * @return array<int,string> 缺失项
     */
    public static function missingSchema(): array
    {
        $missing = [];

        try {
            if (!Db::tableExists('kf_staff')) {
                $missing[] = '表 kf_staff';
            }
        } catch (\Throwable $e) {
            $missing[] = '表 kf_staff（数据库连不上）';
        }

        foreach (
            [
                ['tickets', 'staff_id', '字段 tickets.staff_id'],
                ['ticket_replies', 'sender_name', '字段 ticket_replies.sender_name'],
                ['kf_staff', 'manager_id', '字段 kf_staff.manager_id'],
            ] as $item
        ) {
            if (!self::columnExists($item[0], $item[1])) {
                $missing[] = $item[2];
            }
        }

        try {
            if (!Db::tableExists('kf_ticket_reads')) {
                $missing[] = '表 kf_ticket_reads';
            }
        } catch (\Throwable $e) {
            $missing[] = '表 kf_ticket_reads';
        }

        return $missing;
    }

    // ---------------------------------------------------------------- 已读 / 未读

    /** 打开工单时标记为已读（记到"最后一条回复"） */
    public static function markRead(int $staffId, int $ticketId): void
    {
        if ($staffId <= 0 || $ticketId <= 0) {
            return;
        }
        try {
            $maxReply = (int) Db::value(
                'SELECT COALESCE(MAX(id), 0) FROM ticket_replies WHERE ticket_id = ?',
                [$ticketId]
            );
            Db::query(
                'INSERT INTO kf_ticket_reads (staff_id, ticket_id, last_reply_id, read_at)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE last_reply_id = VALUES(last_reply_id), read_at = VALUES(read_at)',
                [$staffId, $ticketId, $maxReply, now()]
            );
        } catch (\Throwable $e) {
            // 已读记录写失败不影响主流程
        }
    }

    /** 某人未读工单数（没打开过，或打开后用户又追问了） */
    public static function unreadCount(int $staffId): int
    {
        if ($staffId <= 0) {
            return 0;
        }
        try {
            return (int) Db::value(
                "SELECT COUNT(*) FROM tickets t
                 LEFT JOIN kf_ticket_reads kr ON kr.ticket_id = t.id AND kr.staff_id = ?
                 WHERE t.staff_id = ?
                   AND t.status <> 3
                   AND (kr.id IS NULL OR EXISTS (
                        SELECT 1 FROM ticket_replies r
                        WHERE r.ticket_id = t.id AND r.sender_type = 'user' AND r.id > kr.last_reply_id
                   ))",
                [$staffId, $staffId]
            );
        } catch (\Throwable $e) {
            return 0;
        }
    }

    // ---------------------------------------------------------------- 登录态

    /** @return array<string,mixed>|null */
    public static function current()
    {
        static $row = null;
        static $loaded = false;

        if ($loaded) {
            return $row;
        }
        $loaded = true;

        $id = (int) ($_SESSION['kf_staff_id'] ?? 0);
        if ($id <= 0) {
            return $row = null;
        }

        // kf_staff 表还没建（没执行 sql/kf_desk.sql）时，当作未登录，不要让整站 500
        try {
            $found = Db::one('SELECT * FROM kf_staff WHERE id = ? LIMIT 1', [$id]);
        } catch (\Throwable $e) {
            unset($_SESSION['kf_staff_id']);
            return $row = null;
        }

        if (!$found || (int) $found['status'] !== 1) {
            unset($_SESSION['kf_staff_id']);
            return $row = null;
        }
        return $row = $found;
    }

    public static function check(): bool
    {
        return self::current() !== null;
    }

    /** @return array<string,mixed>|null */
    public static function attempt(string $account, string $password): ?array
    {
        $account = trim($account);
        if ($account === '' || $password === '') {
            return null;
        }
        try {
            $row = Db::one('SELECT * FROM kf_staff WHERE account = ? LIMIT 1', [$account]);
        } catch (\Throwable $e) {
            // 还没执行 sql/kf_desk.sql（表不存在）：当作登录失败，不要 500
            return null;
        }
        if (!$row || !password_verify($password, (string) $row['password'])) {
            return null;
        }
        if ((int) $row['status'] !== 1) {
            return null;
        }
        return $row;
    }

    /** @param array<string,mixed> $row */
    public static function login(array $row): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        // 工单部门账号与后台管理员互斥：登录一方就清掉另一方
        unset($_SESSION['uid']);
        $_SESSION['kf_staff_id'] = (int) $row['id'];

        Db::update('kf_staff', [
            'last_login_at' => now(),
            'last_login_ip' => client_ip(),
            'updated_at'    => now(),
        ], 'id = ?', [(int) $row['id']]);
    }

    public static function logout(): void
    {
        unset($_SESSION['kf_staff_id']);
    }

    // ---------------------------------------------------------------- 操作者

    /**
     * 当前操作者（统一形状）
     * @return array{type:string,id:int,role:string,name:string,leader_id:int}
     */
    public static function actor(): array
    {
        $staff = self::current();
        if ($staff) {
            return [
                'type'      => 'staff',
                'id'        => (int) $staff['id'],
                'role'      => (string) $staff['role'],
                'name'      => (string) $staff['name'],
                'leader_id' => (int) $staff['leader_id'],
            ];
        }
        if (Auth::isAdmin()) {
            $u = Auth::user();
            return [
                'type'      => 'admin',
                'id'        => (int) ($u['id'] ?? 0),
                'role'      => 'super',
                'name'      => (string) ($u['nickname'] ?? '管理员'),
                'leader_id' => 0,
            ];
        }
        return ['type' => 'none', 'id' => 0, 'role' => '', 'name' => '', 'leader_id' => 0];
    }

    public static function isSuper(array $actor): bool
    {
        return $actor['type'] === 'admin' || $actor['role'] === 'super';
    }

    /** 能看公海、能看到全局的层级：经理及以上 */
    public static function canDispatch(array $actor): bool
    {
        return self::rank((string) $actor['role']) >= self::rank('manager');
    }

    /** 能派单/转单的层级：组长及以上（组长派给本组客服） */
    public static function canAssign(array $actor): bool
    {
        return self::rank((string) $actor['role']) >= self::rank('leader');
    }

    /** @return array<int,string> 能创建的角色 */
    public static function creatableRoles(array $actor): array
    {
        if (self::isSuper($actor)) {
            return ['manager', 'leader', 'agent'];
        }
        if ($actor['role'] === 'manager') {
            return ['leader', 'agent'];
        }
        return [];
    }

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM kf_staff WHERE id = ? LIMIT 1', [$id]);
    }

    /** @param array<string,mixed> $target */
    public static function canManage(array $actor, array $target): bool
    {
        if ($actor['type'] === 'none') {
            return false;
        }
        if ((int) $actor['id'] === (int) $target['id'] && $actor['type'] === 'staff') {
            return false; // 不能动自己
        }
        if (self::isSuper($actor)) {
            return true;
        }
        if ($actor['role'] === 'manager') {
            return in_array((string) $target['role'], ['leader', 'agent'], true);
        }
        // 组长：只能管自己组里的客服，且只允许「重置密码」这类操作
        if ($actor['role'] === 'leader') {
            return (string) $target['role'] === 'agent'
                && (int) $target['leader_id'] === (int) $actor['id'];
        }
        return false;
    }

    // ---------------------------------------------------------------- 列表

    /**
     * @param array<string,mixed> $filter role/status/keyword/leader_id
     * @return array{total:int,rows:array<int,array<string,mixed>>,page:int,per:int}
     */
    public static function paginate(array $filter, int $page = 1, int $per = 30): array
    {
        $where  = ['1 = 1'];
        $params = [];

        $role = (string) ($filter['role'] ?? '');
        if ($role !== '' && array_key_exists($role, self::roles())) {
            $where[]  = 'role = ?';
            $params[] = $role;
        }
        $status = (string) ($filter['status'] ?? '');
        if ($status === '0' || $status === '1') {
            $where[]  = 'status = ?';
            $params[] = (int) $status;
        }
        if (!empty($filter['leader_id'])) {
            $where[]  = 'leader_id = ?';
            $params[] = (int) $filter['leader_id'];
        }
        $keyword = trim((string) ($filter['keyword'] ?? ''));
        if ($keyword !== '') {
            $kw = '%' . like_escape($keyword) . '%';
            $where[] = '(staff_no LIKE ? OR account LIKE ? OR name LIKE ? OR phone LIKE ? OR email LIKE ?)';
            array_push($params, $kw, $kw, $kw, $kw, $kw);
        }

        $whereSql = implode(' AND ', $where);
        $total    = (int) Db::value("SELECT COUNT(*) FROM kf_staff WHERE $whereSql", $params);

        $page   = max(1, $page);
        $offset = ($page - 1) * $per;
        $rows   = Db::all(
            "SELECT s.*, l.name AS leader_name, m.name AS manager_name
             FROM kf_staff s
             LEFT JOIN kf_staff l ON l.id = s.leader_id
             LEFT JOIN kf_staff m ON m.id = s.manager_id
             WHERE $whereSql
             ORDER BY s.status DESC, FIELD(s.role, 'manager', 'leader', 'agent'), s.id ASC
             LIMIT $per OFFSET $offset",
            $params
        );

        return ['total' => $total, 'rows' => $rows, 'page' => $page, 'per' => $per];
    }

    /** 组长列表（可只取某个经理名下的） */
    public static function leaders(int $managerId = 0): array
    {
        if ($managerId > 0) {
            return Db::all(
                "SELECT id, name, staff_no, manager_id FROM kf_staff
                 WHERE role = 'leader' AND status = 1 AND manager_id = ?
                 ORDER BY id ASC",
                [$managerId]
            );
        }
        return Db::all("SELECT id, name, staff_no, manager_id FROM kf_staff WHERE role = 'leader' AND status = 1 ORDER BY id ASC");
    }

    /** 经理列表 */
    public static function managers(): array
    {
        return Db::all("SELECT id, name, staff_no FROM kf_staff WHERE role = 'manager' AND status = 1 ORDER BY id ASC");
    }

    /** 客服列表 */
    public static function agents(int $leaderId = 0): array
    {
        if ($leaderId > 0) {
            return Db::all(
                "SELECT id, name, staff_no, leader_id FROM kf_staff
                 WHERE role = 'agent' AND status = 1 AND leader_id = ?
                 ORDER BY id ASC",
                [$leaderId]
            );
        }
        return Db::all("SELECT id, name, staff_no, leader_id FROM kf_staff WHERE role = 'agent' AND status = 1 ORDER BY id ASC");
    }

    /**
     * 某操作者可以派单给谁（带分组，方便下拉里分开显示）：
     *   超管  → 组长（优先）+ 所有客服（按组长分组）
     *   经理  → 自己名下的组长（优先）+ 这些组长名下的客服（按组分组）
     *   组长  → 本组客服
     * @return array<int,array{id:int,staff_no:string,name:string,role:string,group:string,label:string}>
     */
    public static function dispatchTargets(array $actor): array
    {
        $out = [];

        // 组长 id => 姓名，用来给"客服"分组
        $leaderNames = [];
        try {
            foreach (Db::all("SELECT id, name FROM kf_staff WHERE role = 'leader'") as $row) {
                $leaderNames[(int) $row['id']] = (string) $row['name'];
            }
        } catch (\Throwable $e) {
            $leaderNames = [];
        }

        $agentGroup = function (array $agent) use ($leaderNames): string {
            $lid = (int) ($agent['leader_id'] ?? 0);
            $name = $leaderNames[$lid] ?? '';
            return $name !== '' ? ('客服 · ' . $name . '组') : '客服 · 未分组';
        };

        if (self::isSuper($actor)) {
            foreach (self::leaders() as $l) {
                $out[] = self::targetRow($l, 'leader', '组长（优先）');
            }
            foreach (self::agents() as $a) {
                $out[] = self::targetRow($a, 'agent', $agentGroup($a));
            }
            return $out;
        }

        if ($actor['role'] === 'manager') {
            $myLeaders = self::leaders((int) $actor['id']);
            $leaderIds = array_map('intval', array_column($myLeaders, 'id'));
            foreach ($myLeaders as $l) {
                $out[] = self::targetRow($l, 'leader', '组长（优先）');
            }
            foreach (self::agents() as $a) {
                if (in_array((int) $a['leader_id'], $leaderIds, true)) {
                    $out[] = self::targetRow($a, 'agent', $agentGroup($a));
                }
            }
            return $out;
        }

        if ($actor['role'] === 'leader') {
            foreach (self::agents((int) $actor['id']) as $a) {
                $out[] = self::targetRow($a, 'agent', '本组客服');
            }
        }

        return $out;
    }

    /** @param array<string,mixed> $row */
    private static function targetRow(array $row, string $role, string $group): array
    {
        return [
            'id'       => (int) $row['id'],
            'staff_no' => (string) $row['staff_no'],
            'name'     => (string) $row['name'],
            'role'     => $role,
            'group'    => $group,
            'label'    => (string) $row['staff_no'] . ' · ' . (string) $row['name'] . '（' . self::roleText($role) . '）',
        ];
    }

    // ---------------------------------------------------------------- 增删改

    /**
     * 新建账号
     * @param array<string,mixed> $data
     */
    public static function create(array $actor, array $data): int
    {
        $allowed = self::creatableRoles($actor);
        if (!$allowed) {
            throw new \RuntimeException('你没有创建账号的权限');
        }

        $account = trim((string) ($data['account'] ?? ''));
        $name    = trim((string) ($data['name'] ?? ''));
        $email   = trim((string) ($data['email'] ?? ''));
        $phone   = trim((string) ($data['phone'] ?? ''));
        $role    = (string) ($data['role'] ?? 'agent');
        $pass    = (string) ($data['password'] ?? '');
        $leader  = (int) ($data['leader_id'] ?? 0);
        $manager = (int) ($data['manager_id'] ?? 0);

        if (!preg_match('/^[A-Za-z0-9_]{3,20}$/', $account)) {
            throw new \RuntimeException('登录账号需为 3~20 位字母、数字或下划线');
        }
        if (Db::one('SELECT id FROM kf_staff WHERE account = ? LIMIT 1', [$account])) {
            throw new \RuntimeException('这个登录账号已被占用');
        }
        if ($name === '' || mb_strlen($name) > 20) {
            throw new \RuntimeException('请填写姓名（不超过 20 字）');
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('请填写正确的邮箱');
        }
        if ($phone === '' || !is_phone($phone)) {
            throw new \RuntimeException('请填写正确的手机号');
        }
        if (!in_array($role, $allowed, true)) {
            throw new \RuntimeException('你不能创建「' . self::roleText($role) . '」');
        }
        $problem = self::passwordProblem($pass, $account);
        if ($problem !== '') {
            throw new \RuntimeException($problem);
        }
        // 层级归属：组长挂经理，客服挂组长，经理不挂
        if ($role === 'agent') {
            $manager = 0;
            if ($leader > 0) {
                $l = self::find($leader);
                if (!$l || (string) $l['role'] !== 'leader') {
                    throw new \RuntimeException('指定的组长不存在');
                }
            }
        } elseif ($role === 'leader') {
            $leader = 0;
            if ($manager > 0) {
                $m = self::find($manager);
                if (!$m || (string) $m['role'] !== 'manager') {
                    throw new \RuntimeException('指定的经理不存在');
                }
            }
        } else {
            $leader  = 0;
            $manager = 0;
        }

        $id = Db::insert('kf_staff', [
            'staff_no'   => '',
            'account'    => $account,
            'password'   => password_hash($pass, PASSWORD_DEFAULT),
            'name'       => mb_substr($name, 0, 20),
            'email'      => mb_substr($email, 0, 100),
            'phone'      => mb_substr($phone, 0, 20),
            'role'       => $role,
            'leader_id'  => $leader,
            'manager_id' => $manager,
            'status'     => 1,
            'created_by' => (int) $actor['id'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 工号：KF + 4 位序号（按自增 id），保证唯一且好记
        $no = 'KF' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);
        Db::update('kf_staff', ['staff_no' => $no], 'id = ?', [$id]);

        return $id;
    }

    /**
     * 修改账号
     * @param array<string,mixed> $data
     */
    public static function update(array $actor, int $id, array $data): void
    {
        $target = self::find($id);
        if (!$target) {
            throw new \RuntimeException('账号不存在');
        }
        if (!self::canManage($actor, $target)) {
            throw new \RuntimeException('你不能修改该账号');
        }

        $name   = trim((string) ($data['name'] ?? ''));
        $email  = trim((string) ($data['email'] ?? ''));
        $phone  = trim((string) ($data['phone'] ?? ''));
        $role    = (string) ($data['role'] ?? (string) $target['role']);
        $leader  = (int) ($data['leader_id'] ?? (int) $target['leader_id']);
        $manager = (int) ($data['manager_id'] ?? (int) ($target['manager_id'] ?? 0));

        if ($name === '' || mb_strlen($name) > 20) {
            throw new \RuntimeException('请填写姓名（不超过 20 字）');
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('请填写正确的邮箱');
        }
        if ($phone === '' || !is_phone($phone)) {
            throw new \RuntimeException('请填写正确的手机号');
        }
        if ($role !== (string) $target['role'] && !in_array($role, self::creatableRoles($actor), true)) {
            throw new \RuntimeException('你不能把角色改成「' . self::roleText($role) . '」');
        }
        // 层级归属：客服挂组长，组长挂经理
        if ($role === 'agent') {
            $manager = 0;
            if ($leader > 0) {
                $l = self::find($leader);
                if (!$l || (string) $l['role'] !== 'leader') {
                    throw new \RuntimeException('指定的组长不存在');
                }
            }
        } elseif ($role === 'leader') {
            $leader = 0;
            if ($manager > 0) {
                $m = self::find($manager);
                if (!$m || (string) $m['role'] !== 'manager') {
                    throw new \RuntimeException('指定的经理不存在');
                }
            }
        } else {
            $leader  = 0;
            $manager = 0;
        }

        Db::update('kf_staff', [
            'name'       => mb_substr($name, 0, 20),
            'email'      => mb_substr($email, 0, 100),
            'phone'      => mb_substr($phone, 0, 20),
            'role'       => $role,
            'leader_id'  => $leader,
            'manager_id' => $manager,
            'updated_at' => now(),
        ], 'id = ?', [$id]);
    }

    /** 停用 / 启用 */
    public static function toggle(array $actor, int $id): int
    {
        $target = self::find($id);
        if (!$target) {
            throw new \RuntimeException('账号不存在');
        }
        // 封禁属于"重"操作：超管、经理可以；组长可以封自己组里的客服
        $leaderOfTarget = $actor['role'] === 'leader'
            && (string) $target['role'] === 'agent'
            && (int) ($target['leader_id'] ?? 0) === (int) $actor['id'];
        if (!self::isSuper($actor) && $actor['role'] !== 'manager' && !$leaderOfTarget) {
            throw new \RuntimeException('只有经理、组长（限本组）或超级管理员可以封禁账号');
        }
        if (!self::canManage($actor, $target)) {
            throw new \RuntimeException('你不能操作该账号');
        }

        $new = (int) $target['status'] === 1 ? 0 : 1;
        Db::update('kf_staff', ['status' => $new, 'updated_at' => now()], 'id = ?', [$id]);
        return $new;
    }

    /** 重置密码（组长可以重置本组客服的） */
    public static function resetPassword(array $actor, int $id, string $password): void
    {
        $target = self::find($id);
        if (!$target) {
            throw new \RuntimeException('账号不存在');
        }
        if (!self::canManage($actor, $target)) {
            throw new \RuntimeException('你不能重置该账号的密码');
        }
        if ($password === '') {
            $password = self::randomPassword();
        }
        $problem = self::passwordProblem($password, (string) $target['account']);
        if ($problem !== '') {
            throw new \RuntimeException($problem);
        }

        Db::update('kf_staff', [
            'password'   => password_hash($password, PASSWORD_DEFAULT),
            'updated_at' => now(),
        ], 'id = ?', [$id]);

        // 把生成的密码回传给控制器显示
        $GLOBALS['kf_reset_password'] = $password;
    }

    public static function randomPassword(): string
    {
        $letters = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
        $digits  = '23456789';
        $all     = $letters . $digits;

        $out = '';
        for ($i = 0; $i < 8; $i++) {
            $out .= $all[random_int(0, strlen($all) - 1)];
        }
        $out .= $digits[random_int(0, strlen($digits) - 1)];
        $out .= $letters[random_int(0, strlen($letters) - 1)];

        return str_shuffle($out);
    }

    public static function passwordProblem(string $password, string $account = ''): string
    {
        if (strlen($password) < 8) {
            return '密码至少 8 位';
        }
        if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            return '密码必须同时包含字母和数字';
        }
        if ($account !== '' && strtolower($password) === strtolower($account)) {
            return '密码不能和账号相同';
        }
        return '';
    }
}
