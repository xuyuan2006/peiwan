<?php
/**
 * 工单服务：售前咨询 / 售后问题 / 投诉建议
 *
 * 一张工单 = 一条用户诉求 + 一串对话（ticket_replies），客服在后台回复并把状态推进。
 */
declare(strict_types=1);

namespace App;

class TicketService
{
    /** 类型 => [标题, 说明] */
    public static function categories(): array
    {
        return [
            'presale'   => ['售前咨询', '下单之前想问问：价格、流程、能不能做'],
            'aftersale' => ['售后问题', '订单、服务、退款、提现等遇到的问题'],
            'complaint' => ['投诉建议', '投诉陪玩或用户，或对平台提建议'],
        ];
    }

    public static function categoryText(string $category): string
    {
        $cats = self::categories();
        return $cats[$category][0] ?? '其他';
    }

    public static function statusText(int $status): string
    {
        $map = [0 => '待处理', 1 => '处理中', 2 => '已解决', 3 => '已关闭'];
        return $map[$status] ?? '未知';
    }

    public static function statusClass(int $status): string
    {
        $map = [0 => 'warn', 1 => 'info', 2 => 'success', 3 => 'muted'];
        return $map[$status] ?? 'muted';
    }

    // ---------------------------------------------------------------- 用户侧

    /** 提交工单 */
    public static function create(int $userId, string $category, string $title, string $content, int $orderId = 0, array $attachments = []): array
    {
        if (!array_key_exists($category, self::categories())) {
            throw new \RuntimeException('请选择工单类型');
        }

        $title   = trim($title);
        $content = trim($content);

        if ($title === '') {
            throw new \RuntimeException('请填写标题');
        }
        if (mb_strlen($content) < 5) {
            throw new \RuntimeException('请把问题描述清楚一些（至少 5 个字）');
        }
        if (mb_strlen($content) > 1000) {
            throw new \RuntimeException('问题描述不能超过 1000 字');
        }

        // 关联订单必须是自己的（买到的或接到的）
        if ($orderId > 0) {
            $own = Db::one(
                'SELECT id FROM orders WHERE id = ? AND (user_id = ? OR companion_id = ?) LIMIT 1',
                [$orderId, $userId, $userId]
            );
            if (!$own) {
                $orderId = 0;
            }
        }

        if (self::userOpenCount($userId) >= 5) {
            throw new \RuntimeException('你还有 5 个未完成的工单，等客服处理完再提交新的吧');
        }

        $ticketId = Db::tx(function () use ($userId, $category, $title, $content, $orderId, $attachments) {
            $attach = self::attachColumn('tickets', $attachments);

            $id = Db::insert('tickets', array_merge([
                'ticket_no'  => order_no('TK'),
                'user_id'    => $userId,
                'category'   => $category,
                'order_id'   => $orderId,
                'title'      => mb_substr($title, 0, 100),
                'content'    => mb_substr($content, 0, 1000),
                'status'     => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ], $attach));

            Db::insert('ticket_replies', array_merge([
                'ticket_id'   => $id,
                'sender_type' => 'user',
                'sender_id'   => $userId,
                'content'     => mb_substr($content, 0, 1000),
                'created_at'  => now(),
            ], self::attachColumn('ticket_replies', $attachments)));

            return $id;
        });

        return self::find($ticketId);
    }

    /** 追加一条对话（用户追问 / 客服回复） */
    public static function reply(int $ticketId, string $senderType, int $senderId, string $content, string $senderName = '', array $attachments = []): void
    {
        // 'kf' = 工单部门账号（kf_staff 表）的回复
        if (!in_array($senderType, ['user', 'admin', 'kf'], true)) {
            throw new \RuntimeException('回复方不合法');
        }
        $content = trim($content);
        if ($content === '') {
            throw new \RuntimeException('请填写回复内容');
        }
        if (mb_strlen($content) > 1000) {
            throw new \RuntimeException('回复不能超过 1000 字');
        }

        $ticket = self::find($ticketId);
        if (!$ticket) {
            throw new \RuntimeException('工单不存在');
        }
        if ((int) $ticket['status'] === 3) {
            throw new \RuntimeException('工单已关闭，无法继续回复');
        }

        $replyData = [
            'ticket_id'   => $ticketId,
            'sender_type' => $senderType,
            'sender_id'   => $senderId,
            'content'     => mb_substr($content, 0, 1000),
            'created_at'  => now(),
        ];
        // sender_name 是工单部门加的列（sql/kf_desk.sql）；老库没有就跳过，不影响回复
        if ($senderName !== '' && self::hasSenderName()) {
            $replyData['sender_name'] = mb_substr($senderName, 0, 50);
        }
        $replyData = array_merge($replyData, self::attachColumn('ticket_replies', $attachments));

        Db::tx(function () use ($ticketId, $senderType, $senderId, $replyData, $ticket) {
            Db::insert('ticket_replies', $replyData);

            $data = ['updated_at' => now()];
            if ($senderType === 'admin' || $senderType === 'kf') {
                $data['replied_at'] = now();
                if ($senderType === 'admin') {
                    $data['admin_id'] = $senderId;
                }
                if ((int) $ticket['status'] === 0) {
                    $data['status'] = 1; // 待处理 → 处理中
                }
            } elseif ((int) $ticket['status'] === 2) {
                $data['status'] = 1; // 用户再次追问，已解决 → 处理中
            }

            Db::update('tickets', $data, 'id = ?', [$ticketId]);
        });

        // 客服回复后给工单主人发邮件通知（站点没启用邮箱时 Notify 内部会直接跳过）。
        // 刻意放在事务之外：发信要走网络、比较慢，不该占着数据库事务；
        // 而且 Notify 内部自己吞异常，发信失败绝不影响「回复成功」这个结果。
        if ($senderType === 'admin' || $senderType === 'kf') {
            Notify::ticketReplied($ticketId, $content);
        }
    }

    /** ticket_replies 是否有 sender_name 列（结果缓存，一个请求只查一次） */
    private static function hasSenderName(): bool
    {
        static $has = null;
        if ($has !== null) {
            return $has;
        }
        try {
            $has = Db::one("SHOW COLUMNS FROM `ticket_replies` LIKE 'sender_name'") !== null;
        } catch (\Throwable $e) {
            $has = false;
        }
        return $has;
    }

    /**
     * 附件字段：把路径数组转成 JSON 写进 attachments 列。
     * 列不存在（老库没执行补丁）时返回空数组，不写这一列。
     *
     * @param array<int,string> $attachments
     * @return array<string,string>
     */
    private static function attachColumn(string $table, array $attachments): array
    {
        if (!$attachments) {
            return [];
        }
        static $has = [];
        if (!array_key_exists($table, $has)) {
            try {
                $has[$table] = Db::one('SHOW COLUMNS FROM `' . $table . "` LIKE 'attachments'") !== null;
            } catch (\Throwable $e) {
                $has[$table] = false;
            }
        }
        if (!$has[$table]) {
            return [];
        }

        $clean = [];
        foreach ($attachments as $path) {
            $path = trim((string) $path);
            if ($path !== '' && strpos($path, 'uploads/') === 0) {
                $clean[] = $path;
            }
        }
        if (!$clean) {
            return [];
        }

        return ['attachments' => (string) json_encode($clean, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)];
    }

    public static function setStatus(int $ticketId, int $status, int $adminId = 0): void
    {
        if (!in_array($status, [0, 1, 2, 3], true)) {
            throw new \RuntimeException('工单状态不合法');
        }
        $ticket = self::find($ticketId);
        if (!$ticket) {
            throw new \RuntimeException('工单不存在');
        }

        $data = ['status' => $status, 'updated_at' => now()];
        if ($adminId > 0) {
            $data['admin_id'] = $adminId;
        }
        if ($status === 3) {
            $data['closed_at'] = now();
        }

        Db::update('tickets', $data, 'id = ?', [$ticketId]);
    }

    // ---------------------------------------------------------------- 查询

    /** @return array<string,mixed>|null */
    public static function find(int $ticketId): ?array
    {
        return Db::one(
            'SELECT t.*, u.nickname, u.avatar, u.phone, u.email, a.nickname AS admin_nickname
             FROM tickets t
             LEFT JOIN users u ON u.id = t.user_id
             LEFT JOIN users a ON a.id = t.admin_id
             WHERE t.id = ? LIMIT 1',
            [$ticketId]
        );
    }

    /**
     * 对话内容（附发言人昵称）
     *
     * 客服端（kf）是一个独立系统，它的回复通过 kf/reply 接口写进来，
     * sender_type = 'kf'、sender_id = 0，客服名字放在 sender_name 列里。
     * 所以这里只需要 JOIN users（用户/后台管理员），不再依赖客服端的表。
     */
    public static function thread(int $ticketId): array
    {
        // 工单部门账号的回复（sender_type='kf'）要显示姓名，所以多 JOIN 一次 kf_staff；
        // 老库没建 kf_staff 时自动回退到不 JOIN 的版本，不影响原有对话显示。
        try {
            return Db::all(
                "SELECT r.*,
                        u.nickname AS nickname, u.avatar AS avatar,
                        s.name AS staff_name
                 FROM ticket_replies r
                 LEFT JOIN users u ON u.id = r.sender_id AND r.sender_type IN ('user', 'admin')
                 LEFT JOIN kf_staff s ON s.id = r.sender_id AND r.sender_type = 'kf'
                 WHERE r.ticket_id = ?
                 ORDER BY r.id ASC",
                [$ticketId]
            );
        } catch (\Throwable $e) {
            return Db::all(
                'SELECT r.*, u.nickname AS nickname, u.avatar AS avatar
                 FROM ticket_replies r
                 LEFT JOIN users u ON u.id = r.sender_id AND r.sender_type IN (\'user\', \'admin\')
                 WHERE r.ticket_id = ?
                 ORDER BY r.id ASC',
                [$ticketId]
            );
        }
    }

    /** 用户自己的工单列表 */
    public static function userList(int $userId, int $page = 1, int $perPage = 10): array
    {
        $total  = (int) Db::value('SELECT COUNT(*) FROM tickets WHERE user_id = ?', [$userId]);
        $page   = max(1, $page);
        $offset = ($page - 1) * $perPage;
        $rows   = Db::all(
            "SELECT t.*, (SELECT COUNT(*) FROM ticket_replies r WHERE r.ticket_id = t.id) AS reply_count
             FROM tickets t
             WHERE t.user_id = ?
             ORDER BY t.id DESC
             LIMIT $perPage OFFSET $offset",
            [$userId]
        );
        return ['total' => $total, 'rows' => $rows, 'page' => $page, 'per_page' => $perPage];
    }

    /** 后台工单列表 */
    public static function adminList(array $filter, int $page = 1, int $perPage = 20): array
    {
        $where  = ['1 = 1'];
        $params = [];

        if (isset($filter['status']) && $filter['status'] !== '' && $filter['status'] !== 'all') {
            $where[]  = 't.status = ?';
            $params[] = (int) $filter['status'];
        }
        if (!empty($filter['category']) && array_key_exists($filter['category'], self::categories())) {
            $where[]  = 't.category = ?';
            $params[] = $filter['category'];
        }
        if (!empty($filter['keyword'])) {
            $kw      = '%' . like_escape((string) $filter['keyword']) . '%';
            $where[] = '(t.ticket_no LIKE ? OR t.title LIKE ? OR u.nickname LIKE ?)';
            array_push($params, $kw, $kw, $kw);
        }

        $whereSql = implode(' AND ', $where);
        $total    = (int) Db::value(
            "SELECT COUNT(*) FROM tickets t LEFT JOIN users u ON u.id = t.user_id WHERE $whereSql",
            $params
        );

        $page   = max(1, $page);
        $offset = ($page - 1) * $perPage;
        $rows   = Db::all(
            "SELECT t.*, u.nickname, u.phone,
                    (SELECT COUNT(*) FROM ticket_replies r WHERE r.ticket_id = t.id) AS reply_count
             FROM tickets t
             LEFT JOIN users u ON u.id = t.user_id
             WHERE $whereSql
             ORDER BY t.status ASC, t.id DESC
             LIMIT $perPage OFFSET $offset",
            $params
        );

        return ['total' => $total, 'rows' => $rows, 'page' => $page, 'per_page' => $perPage];
    }

    /** 用户未完成工单数（0 待处理 / 1 处理中） */
    public static function userOpenCount(int $userId): int
    {
        return (int) Db::value('SELECT COUNT(*) FROM tickets WHERE user_id = ? AND status IN (0, 1)', [$userId]);
    }

    /** 后台待处理数量（用于侧边栏红点） */
    public static function pendingCount(): int
    {
        try {
            return (int) Db::value('SELECT COUNT(*) FROM tickets WHERE status = 0');
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * 客服工作台（kf）的在线咨询入口。
     *
     * 客服端是独立目录、可能部署在别的域名，所以优先读 config/config.php 里的：
     *
     *   'security' => [
     *       'admin_path' => 'manage8x',
     *       'kf_url'     => 'http://kf.example.com',  // 客服端站点根地址
     *       'kf_secret'  => '一串随机字符',            // 可选：跨域传登录态的签名密钥
     *   ],
     *
     * 关于 kf_secret：客服端在别的域名时，本站的 Session Cookie（PEIWAN_SID）
     * 不会被带过去，用户侧在线咨询就会认不出人。配上这个密钥之后，
     * 链接里会带一个 5 分钟有效的一次性签名令牌（uid|过期时间|HMAC），
     * 客服端校验通过即视为已登录 —— 不跨域时也照常可用。
     *
     * 没配 kf_url 时退回「public 下找 kf 目录」；都没有就返回空串，
     * 网页端不会显示这个入口（不会出现死链）。
     */
    public static function kfChatUrl(): string
    {
        $full = trim((string) config('security.kf_url', ''));
        if ($full !== '') {
            $url = rtrim($full, '/') . '/index.php?r=portal/index';
        } else {
            $url = '';
            $candidates = [
                trim((string) config('security.kf_path', 'kf'), '/'),
                'kf',
            ];
            foreach (array_unique($candidates) as $dir) {
                if ($dir === '' || $dir === '.' || $dir === '..' || !preg_match('/^[A-Za-z0-9_\-]{1,30}$/', $dir)) {
                    continue;
                }
                if (is_file(APP_ROOT . '/public/' . $dir . '/index.php')) {
                    $url = base_url() . '/' . $dir . '/index.php?r=portal/index';
                    break;
                }
            }
        }

        if ($url === '') {
            return '';
        }

        // 带上一次性登录令牌（未登录就不带，客服端会引导用户先登录）
        // 令牌格式：uid . base64url(昵称) . 过期时间 . HMAC
        $secret = trim((string) config('security.kf_secret', ''));
        $me     = user();
        if ($secret !== '' && $me) {
            $uid  = (int) $me['id'];
            $nick = (string) ($me['nickname'] ?? '');
            $exp  = time() + 300;
            $sig  = hash_hmac('sha256', $uid . '|' . $nick . '|' . $exp, $secret);
            $b64  = rtrim(strtr(base64_encode($nick), '+/', '-_'), '=');
            $url .= '&_kft=' . rawurlencode($uid . '.' . $b64 . '.' . $exp . '.' . $sig);
        }

        return $url;
    }
}
