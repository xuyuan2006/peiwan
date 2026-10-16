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
    public static function create(int $userId, string $category, string $title, string $content, int $orderId = 0): array
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

        $ticketId = Db::tx(function () use ($userId, $category, $title, $content, $orderId) {
            $id = Db::insert('tickets', [
                'ticket_no'  => order_no('TK'),
                'user_id'    => $userId,
                'category'   => $category,
                'order_id'   => $orderId,
                'title'      => mb_substr($title, 0, 100),
                'content'    => mb_substr($content, 0, 1000),
                'status'     => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            Db::insert('ticket_replies', [
                'ticket_id'   => $id,
                'sender_type' => 'user',
                'sender_id'   => $userId,
                'content'     => mb_substr($content, 0, 1000),
                'created_at'  => now(),
            ]);
            return $id;
        });

        return self::find($ticketId);
    }

    /** 追加一条对话（用户追问 / 客服回复） */
    public static function reply(int $ticketId, string $senderType, int $senderId, string $content): void
    {
        if (!in_array($senderType, ['user', 'admin'], true)) {
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

        Db::tx(function () use ($ticketId, $senderType, $senderId, $content, $ticket) {
            Db::insert('ticket_replies', [
                'ticket_id'   => $ticketId,
                'sender_type' => $senderType,
                'sender_id'   => $senderId,
                'content'     => mb_substr($content, 0, 1000),
                'created_at'  => now(),
            ]);

            $data = ['updated_at' => now()];
            if ($senderType === 'admin') {
                $data['admin_id']   = $senderId;
                $data['replied_at'] = now();
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
        if ($senderType === 'admin') {
            Notify::ticketReplied($ticketId, $content);
        }
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

    /** 对话内容（附发言人昵称） */
    public static function thread(int $ticketId): array
    {
        return Db::all(
            'SELECT r.*, u.nickname, u.avatar
             FROM ticket_replies r
             LEFT JOIN users u ON u.id = r.sender_id
             WHERE r.ticket_id = ?
             ORDER BY r.id ASC',
            [$ticketId]
        );
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
}
