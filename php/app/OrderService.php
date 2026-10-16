<?php
/**
 * 订单服务：下单、支付、接单、完成、分账、退款、自动流转。
 *
 * 资金说明：
 *  - 用户支付的金额在订单完成前由平台托管（已从用户余额扣除，尚未结算给陪玩）
 *  - 订单完成时按 platform_fee_rate 抽成，其余结算到陪玩钱包
 *  - 退款时全额退回用户余额
 */
declare(strict_types=1);

namespace App;

class OrderService
{
    public const STATUS_PENDING_PAY     = 1; // 待支付
    public const STATUS_PENDING_ACCEPT  = 2; // 待接单
    public const STATUS_IN_SERVICE      = 3; // 服务中
    public const STATUS_PENDING_CONFIRM = 4; // 待确认
    public const STATUS_COMPLETED       = 5; // 已完成
    public const STATUS_REFUNDING       = 6; // 退款中
    public const STATUS_CLOSED          = 7; // 已关闭
    public const STATUS_REFUNDED        = 8; // 已退款

    // ---------------------------------------------------------------- 下单

    public static function create(int $userId, int $skillId, int $quantity, string $remark = ''): array
    {
        $skill = Db::one(
            'SELECT s.*, g.`name` AS game_name, g.`status` AS game_status
             FROM companion_skills s LEFT JOIN games g ON g.id = s.game_id
             WHERE s.id = ? LIMIT 1',
            [$skillId]
        );
        if (!$skill || (int) $skill['status'] !== 1) {
            throw new \RuntimeException('该服务已下架，请选择其他服务');
        }
        if (isset($skill['game_status']) && $skill['game_status'] !== null && (int) $skill['game_status'] !== 1) {
            throw new \RuntimeException('该游戏已下架，暂时无法下单');
        }

        $companionId = (int) $skill['companion_id'];
        if ($companionId === $userId) {
            throw new \RuntimeException('不能给自己下单');
        }

        $companion = Db::one(
            'SELECT u.id, u.status, u.role, p.audit_status, p.online_status
             FROM users u LEFT JOIN companion_profiles p ON p.user_id = u.id
             WHERE u.id = ? LIMIT 1',
            [$companionId]
        );
        if (!$companion || (int) $companion['status'] !== 1) {
            throw new \RuntimeException('该陪玩账号已被禁用');
        }
        if ((int) $companion['audit_status'] !== 1) {
            throw new \RuntimeException('该陪玩尚未通过审核');
        }
        if ((int) $companion['online_status'] !== 1) {
            throw new \RuntimeException('该陪玩当前暂停接单，请稍后再试');
        }

        $serviceType = (string) $skill['service_type'];
        if ($serviceType === 'custom') {
            $quantity = 1;
        }
        if ($quantity < 1 || $quantity > 99) {
            throw new \RuntimeException('下单数量需在 1 ~ 99 之间');
        }

        $unitPrice = safe_float($skill['price']);
        if ($unitPrice <= 0) {
            throw new \RuntimeException('该服务价格异常，暂时无法下单');
        }
        $amount = safe_float($unitPrice * $quantity);

        $autoCancel = max(5, Setting::int('order_auto_cancel_minutes', 30));

        // 订单与状态日志放在同一事务里，避免出现"有订单没日志"
        $orderId = Db::tx(function () use ($userId, $companionId, $skill, $serviceType, $quantity, $unitPrice, $amount, $remark, $autoCancel) {
            $id = Db::insert('orders', [
                'order_no'     => order_no('PO'),
                'user_id'      => $userId,
                'companion_id' => $companionId,
                'game_id'      => (int) $skill['game_id'],
                'skill_id'     => (int) $skill['id'],
                'game_name'    => (string) ($skill['game_name'] ?? ''),
                'skill_title'  => (string) $skill['title'],
                'service_type' => $serviceType,
                'quantity'     => $quantity,
                'unit_price'   => $unitPrice,
                'amount'       => $amount,
                'status'       => self::STATUS_PENDING_PAY,
                'user_remark'  => mb_substr($remark, 0, 200),
                'expire_at'    => date('Y-m-d H:i:s', time() + $autoCancel * 60),
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
            self::log($id, 0, self::STATUS_PENDING_PAY, $userId, 'user', '创建订单');
            return $id;
        });

        return self::find($orderId);
    }

    // ---------------------------------------------------------------- 后台代客下单

    /**
     * 后台代客下单：管理员指定用户、陪玩、数量与单价（可自定金额，用于补单/调解）。
     * 全程写 order_logs（operator_type=admin）；$payNow=true 时立即用用户余额支付，
     * 余额不足会抛错并整体回滚，不会留下半张订单。
     */
    public static function adminCreate(
        int $adminId,
        int $userId,
        int $companionId,
        int $skillId,
        int $quantity,
        float $unitPrice,
        string $remark = '',
        bool $payNow = false
    ): array {
        if ($userId <= 0 || $companionId <= 0) {
            throw new \RuntimeException('请选择下单用户与陪玩');
        }
        if ($userId === $companionId) {
            throw new \RuntimeException('下单用户不能是陪玩本人');
        }

        $user = Db::one('SELECT id, status, nickname FROM users WHERE id = ? LIMIT 1', [$userId]);
        if (!$user) {
            throw new \RuntimeException('下单用户不存在');
        }
        if ((int) $user['status'] !== 1) {
            throw new \RuntimeException('下单用户已被禁用');
        }

        $companion = Db::one(
            'SELECT u.id, u.status, u.nickname, p.audit_status
             FROM users u LEFT JOIN companion_profiles p ON p.user_id = u.id
             WHERE u.id = ? LIMIT 1',
            [$companionId]
        );
        if (!$companion || (int) $companion['status'] !== 1) {
            throw new \RuntimeException('陪玩账号不存在或已被禁用');
        }
        if ((int) $companion['audit_status'] !== 1) {
            throw new \RuntimeException('该陪玩的入驻资料尚未通过审核');
        }

        $skill = null;
        if ($skillId > 0) {
            $skill = Db::one(
                'SELECT s.*, g.`name` AS game_name
                 FROM companion_skills s LEFT JOIN games g ON g.id = s.game_id
                 WHERE s.id = ? AND s.companion_id = ? LIMIT 1',
                [$skillId, $companionId]
            );
            if (!$skill) {
                throw new \RuntimeException('所选服务项目不属于该陪玩');
            }
            if ($unitPrice <= 0) {
                $unitPrice = (float) $skill['price'];
            }
        }

        if ($quantity < 1 || $quantity > 99) {
            throw new \RuntimeException('数量需在 1 ~ 99 之间');
        }
        $unitPrice = safe_float($unitPrice);
        if ($unitPrice <= 0) {
            throw new \RuntimeException('请填写正确的单价');
        }
        if ($unitPrice > 99999) {
            throw new \RuntimeException('单价过高，请检查');
        }

        $serviceType = $skill ? (string) $skill['service_type'] : 'custom';
        if ($serviceType === 'custom') {
            $quantity = 1; // 一口价按 1 单计
        }
        $amount = safe_float($unitPrice * $quantity);

        $autoCancel = max(5, Setting::int('order_auto_cancel_minutes', 30));

        $orderId = Db::tx(function () use ($adminId, $userId, $companionId, $skill, $serviceType, $quantity, $unitPrice, $amount, $remark, $autoCancel, $payNow) {
            $id = Db::insert('orders', [
                'order_no'     => order_no('PO'),
                'user_id'      => $userId,
                'companion_id' => $companionId,
                'game_id'      => $skill ? (int) $skill['game_id'] : 0,
                'skill_id'     => $skill ? (int) $skill['id'] : 0,
                'game_name'    => $skill ? (string) ($skill['game_name'] ?? '') : '后台代客下单',
                'skill_title'  => $skill ? (string) $skill['title'] : '平台代客下单（自定义金额）',
                'service_type' => $serviceType,
                'quantity'     => $quantity,
                'unit_price'   => $unitPrice,
                'amount'       => $amount,
                'status'       => self::STATUS_PENDING_PAY,
                'user_remark'  => mb_substr($remark, 0, 200),
                'expire_at'    => date('Y-m-d H:i:s', time() + $autoCancel * 60),
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            self::log($id, 0, self::STATUS_PENDING_PAY, $adminId, 'admin', '后台代客下单：' . mb_substr($remark !== '' ? $remark : '无备注', 0, 120));

            if ($payNow) {
                self::pay($id, $userId); // 余额不足会抛错，整个事务回滚
            }

            return $id;
        });

        return self::find($orderId);
    }

    /**
     * 后台发布接单任务：生成一张 companion_id=0 的公开订单放进接单大厅，
     * 由陪玩自己抢单。建议 $payNow=true（先扣付款人余额），未付款的订单不会出现在大厅。
     */
    public static function adminPublishTask(
        int $adminId,
        int $userId,
        string $title,
        int $gameId,
        string $serviceType,
        int $quantity,
        float $unitPrice,
        string $cover = '',
        string $remark = '',
        bool $payNow = true
    ): array {
        if ($userId <= 0) {
            throw new \RuntimeException('请选择付款用户');
        }
        $title = trim($title);
        if ($title === '') {
            throw new \RuntimeException('请填写任务标题，例如：三角洲行动 上分');
        }
        if (!array_key_exists($serviceType, service_type_map())) {
            $serviceType = 'custom';
        }

        $user = Db::one('SELECT id, status FROM users WHERE id = ? LIMIT 1', [$userId]);
        if (!$user) {
            throw new \RuntimeException('付款用户不存在');
        }
        if ((int) $user['status'] !== 1) {
            throw new \RuntimeException('付款用户已被禁用');
        }

        $gameName = '接单大厅';
        if ($gameId > 0) {
            $game = Db::one('SELECT id, name FROM games WHERE id = ? LIMIT 1', [$gameId]);
            if (!$game) {
                throw new \RuntimeException('所选游戏不存在');
            }
            $gameName = (string) $game['name'];
        }

        if ($quantity < 1 || $quantity > 99) {
            throw new \RuntimeException('数量需在 1 ~ 99 之间');
        }
        $unitPrice = safe_float($unitPrice);
        if ($unitPrice <= 0) {
            throw new \RuntimeException('请填写正确的单价');
        }
        if ($unitPrice > 99999) {
            throw new \RuntimeException('单价过高，请检查');
        }
        if ($serviceType === 'custom') {
            $quantity = 1;
        }
        $amount     = safe_float($unitPrice * $quantity);
        $autoCancel = max(5, Setting::int('order_auto_cancel_minutes', 30));

        $orderId = Db::tx(function () use ($adminId, $userId, $title, $gameId, $gameName, $serviceType, $quantity, $unitPrice, $amount, $cover, $remark, $autoCancel, $payNow) {
            $id = Db::insert('orders', [
                'order_no'     => order_no('PO'),
                'user_id'      => $userId,
                'companion_id' => 0, // 0 = 待接单，由大厅里的陪玩抢
                'game_id'      => $gameId,
                'skill_id'     => 0,
                'game_name'    => $gameName,
                'skill_title'  => mb_substr($title, 0, 100),
                'service_type' => $serviceType,
                'quantity'     => $quantity,
                'unit_price'   => $unitPrice,
                'amount'       => $amount,
                'cover'        => $cover,
                'is_public'    => 1,
                'status'       => self::STATUS_PENDING_PAY,
                'user_remark'  => mb_substr($remark, 0, 200),
                'expire_at'    => date('Y-m-d H:i:s', time() + $autoCancel * 60),
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            self::log($id, 0, self::STATUS_PENDING_PAY, $adminId, 'admin', '后台发布接单任务：' . mb_substr($title, 0, 100));

            if ($payNow) {
                self::pay($id, $userId); // 付款后才进大厅
            }

            return $id;
        });

        return self::find($orderId);
    }

    // ---------------------------------------------------------------- 订单聊天

    /** 当前状态是否允许在订单里发言（需已接单，且订单未关闭/未退款） */
    public static function chatOpen(array $order): bool
    {
        return (int) $order['companion_id'] > 0
            && in_array((int) $order['status'], [self::STATUS_IN_SERVICE, self::STATUS_PENDING_CONFIRM, self::STATUS_COMPLETED], true);
    }

    /** 聊天记录（按时间正序） */
    public static function messages(int $orderId, int $limit = 300): array
    {
        try {
            return Db::all(
                'SELECT m.*, u.nickname, u.avatar
                 FROM order_messages m
                 LEFT JOIN users u ON u.id = m.sender_id
                 WHERE m.order_id = ?
                 ORDER BY m.id ASC
                 LIMIT ' . (int) max(1, $limit),
                [$orderId]
            );
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** 发消息：只有下单人 / 接单陪玩 / 管理员可以发 */
    public static function sendMessage(int $orderId, int $senderId, string $content): array
    {
        $content = trim($content);
        if ($content === '') {
            throw new \RuntimeException('请输入消息内容');
        }
        if (mb_strlen($content) > 500) {
            throw new \RuntimeException('单条消息不能超过 500 字');
        }

        $order = self::find($orderId);
        if (!$order) {
            throw new \RuntimeException('订单不存在');
        }

        $isUser = (int) $order['user_id'] === $senderId;
        $isComp = (int) $order['companion_id'] === $senderId;
        if (!$isUser && !$isComp && !is_admin()) {
            throw new \RuntimeException('无权在该订单里发言');
        }

        // 管理员随时可以介入调解；普通双方需订单处于可沟通状态
        if (!$isUser && !$isComp) {
            $role = 'admin';
        } elseif ($isUser) {
            $role = 'user';
        } else {
            $role = 'companion';
        }

        if ($role !== 'admin' && !self::chatOpen($order)) {
            throw new \RuntimeException('该订单当前不能发消息（需陪玩已接单，且订单未关闭/未退款）');
        }

        $id = Db::insert('order_messages', [
            'order_id'    => $orderId,
            'sender_id'   => $senderId,
            'sender_role' => $role,
            'content'     => mb_substr($content, 0, 500),
            'is_read'     => 0,
            'created_at'  => now(),
        ]);

        // 我既然回复了，对方之前的消息就算已读
        self::markRead($orderId, $senderId);

        return ['id' => $id, 'role' => $role];
    }

    /** 把别人发给我的消息标记为已读 */
    public static function markRead(int $orderId, int $viewerId): void
    {
        try {
            Db::query(
                'UPDATE order_messages SET is_read = 1 WHERE order_id = ? AND sender_id <> ? AND is_read = 0',
                [$orderId, $viewerId]
            );
        } catch (\Throwable $e) {
            // 忽略：未读标记失败不影响主流程
        }
    }

    /** 我在所有订单里的未读消息数（底部 Tab / 顶部导航红点） */
    public static function unreadTotal(int $userId): int
    {
        try {
            return (int) Db::value(
                'SELECT COUNT(*) FROM order_messages m
                 JOIN orders o ON o.id = m.order_id
                 WHERE m.is_read = 0 AND m.sender_id <> ? AND (o.user_id = ? OR o.companion_id = ?)',
                [$userId, $userId, $userId]
            );
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /** 某个订单里我未读的条数 */
    public static function unreadOf(int $orderId, int $viewerId): int
    {
        try {
            return (int) Db::value(
                'SELECT COUNT(*) FROM order_messages WHERE order_id = ? AND sender_id <> ? AND is_read = 0',
                [$orderId, $viewerId]
            );
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * 用户购买「点单套餐」（后台预置的一口价单子）
     *
     * 建单后：余额够就一键付款（付款才进入接单大厅，由陪玩抢单），不够就留在待支付。
     * @return array{order:array,paid:bool,need:float}
     */
    public static function buyPackage(int $userId, int $packageId, string $remark = ''): array
    {
        $package = \App\ContentService::findPackage($packageId);
        if (!$package) {
            throw new \RuntimeException('套餐不存在');
        }
        if ((int) $package['status'] !== 1) {
            throw new \RuntimeException('该套餐已下架，换一个试试');
        }

        $user = Db::one('SELECT id, status FROM users WHERE id = ? LIMIT 1', [$userId]);
        if (!$user) {
            throw new \RuntimeException('用户不存在');
        }
        if ((int) $user['status'] !== 1) {
            throw new \RuntimeException('账号已被禁用，请联系客服');
        }

        $price = safe_float((float) $package['price']);
        if ($price <= 0) {
            throw new \RuntimeException('套餐价格异常，请联系客服');
        }

        $gameId   = (int) $package['game_id'];
        $gameName = '点单套餐';
        if ($gameId > 0) {
            $game = Db::one('SELECT id, name FROM games WHERE id = ? LIMIT 1', [$gameId]);
            if ($game) {
                $gameName = (string) $game['name'];
            }
        }

        $title      = (string) $package['title'];
        $cover      = (string) $package['cover'];
        $autoCancel = max(5, Setting::int('order_auto_cancel_minutes', 30));

        $orderId = Db::tx(function () use ($userId, $gameId, $gameName, $title, $price, $cover, $remark, $autoCancel, $packageId) {
            $id = Db::insert('orders', [
                'order_no'     => order_no('PO'),
                'user_id'      => $userId,
                'companion_id' => 0, // 0 = 待接单，进大厅由陪玩抢
                'game_id'      => $gameId,
                'skill_id'     => 0,
                'game_name'    => $gameName,
                'skill_title'  => mb_substr($title, 0, 100),
                'service_type' => 'custom', // 一口价：数量固定 1，金额 = 套餐价
                'quantity'     => 1,
                'unit_price'   => $price,
                'amount'       => $price,
                'cover'        => $cover,
                'is_public'    => 1,
                'status'       => self::STATUS_PENDING_PAY,
                'user_remark'  => mb_substr($remark, 0, 200),
                'expire_at'    => date('Y-m-d H:i:s', time() + $autoCancel * 60),
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            self::log($id, 0, self::STATUS_PENDING_PAY, $userId, 'user', '购买点单套餐 #' . $packageId . '：' . mb_substr($title, 0, 80));
            return $id;
        });

        // 余额够就一键付款（并发导致余额不足时不要抛异常，留成待支付让用户去充值）
        $paid = false;
        $wallet = Wallet::get($userId);
        if ((float) $wallet['balance'] >= $price) {
            try {
                self::pay($orderId, $userId);
                $paid = true;
            } catch (\Throwable $e) {
                $paid = false;
            }
        }

        return ['order' => self::find($orderId), 'paid' => $paid, 'need' => $price];
    }

    /** 接单大厅列表（公开、未接单、已付款的订单） */
    public static function hallList(int $page, int $perPage = 12, array $filter = []): array
    {
        $where  = ['o.is_public = 1', 'o.companion_id = 0', 'o.status = ?'];
        $params = [self::STATUS_PENDING_ACCEPT];

        if (!empty($filter['game_id'])) {
            $where[]  = 'o.game_id = ?';
            $params[] = (int) $filter['game_id'];
        }
        if (!empty($filter['keyword'])) {
            $kw      = '%' . like_escape((string) $filter['keyword']) . '%';
            $where[] = '(o.game_name LIKE ? OR o.skill_title LIKE ? OR o.order_no LIKE ?)';
            array_push($params, $kw, $kw, $kw);
        }

        $whereSql = implode(' AND ', $where);
        $total    = (int) Db::value("SELECT COUNT(*) FROM orders o WHERE $whereSql", $params);

        $page   = max(1, $page);
        $offset = ($page - 1) * $perPage;
        $rows   = Db::all(
            "SELECT o.*, u.nickname AS user_nickname, u.avatar AS user_avatar, u.credit_score AS user_credit
             FROM orders o LEFT JOIN users u ON u.id = o.user_id
             WHERE $whereSql
             ORDER BY o.id DESC
             LIMIT $perPage OFFSET $offset",
            $params
        );

        return ['total' => $total, 'rows' => $rows, 'page' => $page, 'per_page' => $perPage];
    }

    // ---------------------------------------------------------------- 支付

    public static function pay(int $orderId, int $userId): array
    {
        // 超时订单：先在独立事务里关闭并提交，再抛错给用户
        // （不能在事务内 setStatus 之后 throw，否则 Db::tx 回滚会把"已关闭"撤销）
        $current = self::find($orderId);
        if ((int) $current['user_id'] !== $userId) {
            throw new \RuntimeException('无权操作该订单');
        }
        if ((int) $current['status'] === self::STATUS_PENDING_PAY
            && !empty($current['expire_at'])
            && strtotime((string) $current['expire_at']) < time()) {
            Db::tx(function () use ($orderId, $userId) {
                $fresh = self::lock($orderId);
                if ((int) $fresh['status'] === self::STATUS_PENDING_PAY) {
                    self::setStatus($fresh, self::STATUS_CLOSED, $userId, 'system', [
                        'close_time'    => now(),
                        'cancel_reason' => '支付超时，系统自动关闭',
                    ], '支付超时，订单关闭');
                }
            });
            throw new \RuntimeException('订单已超时关闭，请重新下单');
        }

        return Db::tx(function () use ($orderId, $userId) {
            $order = self::lock($orderId);
            if ((int) $order['user_id'] !== $userId) {
                throw new \RuntimeException('无权操作该订单');
            }
            if ((int) $order['status'] !== self::STATUS_PENDING_PAY) {
                throw new \RuntimeException('订单状态已变化，无法支付');
            }
            if (!empty($order['expire_at']) && strtotime((string) $order['expire_at']) < time()) {
                throw new \RuntimeException('订单已超时，请重新下单');
            }

            $amount = (float) $order['amount'];
            Wallet::expense($userId, $amount, 'order_pay', 'order', $orderId, '支付订单 ' . $order['order_no'], [
                'total_expense' => new \App\Expr('total_expense + ' . $amount),
            ]);

            return self::setStatus($order, self::STATUS_PENDING_ACCEPT, $userId, 'user', [
                'pay_time' => now(),
            ], '支付成功，等待陪玩接单');
        });
    }

    // ---------------------------------------------------------------- 接单

    public static function accept(int $orderId, int $companionId): array
    {
        return Db::tx(function () use ($orderId, $companionId) {
            $order = self::lock($orderId);
            if ((int) $order['companion_id'] !== $companionId) {
                throw new \RuntimeException('这不是你的订单');
            }
            if ((int) $order['status'] !== self::STATUS_PENDING_ACCEPT) {
                throw new \RuntimeException('订单当前状态不可接单');
            }
            return self::setStatus($order, self::STATUS_IN_SERVICE, $companionId, 'companion', [
                'accept_time' => now(),
            ], '陪玩已接单，服务开始');
        });
    }

    /**
     * 接单大厅抢单：把「公开待接单」的订单（companion_id=0）归属给当前陪玩并进入服务中。
     * 用行锁 + 状态校验保证同一单不会被两个人抢到。
     */
    public static function grab(int $orderId, int $companionId): array
    {
        return Db::tx(function () use ($orderId, $companionId) {
            $order = self::lock($orderId);

            if ((int) $order['is_public'] !== 1) {
                throw new \RuntimeException('该订单不在接单大厅');
            }
            if ((int) $order['companion_id'] !== 0) {
                throw new \RuntimeException('手慢了，这单已经被接走');
            }
            if ((int) $order['status'] !== self::STATUS_PENDING_ACCEPT) {
                throw new \RuntimeException('该订单当前不可接单（可能已取消或退款）');
            }
            if ((int) $order['user_id'] === $companionId) {
                throw new \RuntimeException('不能接自己发布的订单');
            }

            $companion = Db::one(
                'SELECT u.id, u.status, p.audit_status
                 FROM users u LEFT JOIN companion_profiles p ON p.user_id = u.id
                 WHERE u.id = ? LIMIT 1',
                [$companionId]
            );
            if (!$companion || (int) $companion['status'] !== 1) {
                throw new \RuntimeException('你的账号已被禁用，无法接单');
            }
            if ((int) $companion['audit_status'] !== 1) {
                throw new \RuntimeException('你还不是陪玩，或入驻资料未通过审核');
            }

            return self::setStatus($order, self::STATUS_IN_SERVICE, $companionId, 'companion', [
                'companion_id' => $companionId,
                'accept_time'  => now(),
                'taken_at'     => now(),
            ], '陪玩在接单大厅抢单成功');
        });
    }

    /** 陪玩标记服务完成 */
    public static function finish(int $orderId, int $companionId): array
    {
        return Db::tx(function () use ($orderId, $companionId) {
            $order = self::lock($orderId);
            if ((int) $order['companion_id'] !== $companionId) {
                throw new \RuntimeException('这不是你的订单');
            }
            if ((int) $order['status'] !== self::STATUS_IN_SERVICE) {
                throw new \RuntimeException('订单当前状态不可提交完成');
            }
            return self::setStatus($order, self::STATUS_PENDING_CONFIRM, $companionId, 'companion', [
                'finish_time' => now(),
            ], '陪玩提交完成，等待用户确认');
        });
    }

    // ---------------------------------------------------------------- 确认完成并分账

    public static function confirm(int $orderId, int $operatorId, string $operatorType = 'user'): array
    {
        return Db::tx(function () use ($orderId, $operatorId, $operatorType) {
            $order = self::lock($orderId);
            if ($operatorType === 'user' && (int) $order['user_id'] !== $operatorId) {
                throw new \RuntimeException('无权操作该订单');
            }

            // 后台/系统可以把"服务中"直接推进到"待确认"再结算（陪玩忘记点完成时的兜底）
            if ((int) $order['status'] === self::STATUS_IN_SERVICE) {
                if ($operatorType === 'user') {
                    throw new \RuntimeException('陪玩尚未提交完成，暂时无法确认');
                }
                $order = self::setStatus($order, self::STATUS_PENDING_CONFIRM, $operatorId, $operatorType, [
                    'finish_time' => !empty($order['finish_time']) ? $order['finish_time'] : now(),
                ], '平台代陪玩提交完成');
            }

            if ((int) $order['status'] !== self::STATUS_PENDING_CONFIRM) {
                throw new \RuntimeException('订单当前状态不可确认');
            }
            return self::settle($order, $operatorId, $operatorType);
        });
    }

    /** 后台强制关闭并退款（服务中/待确认等任意已支付状态） */
    public static function adminRefund(int $orderId, int $adminId, string $reason = ''): array
    {
        return Db::tx(function () use ($orderId, $adminId, $reason) {
            $order = self::lock($orderId);
            return self::refund($order, $adminId, 'admin', $reason !== '' ? $reason : '平台关闭订单并退款');
        });
    }

    /** 结算：平台抽成 + 陪玩入账 + 统计更新 */
    public static function settle(array $order, int $operatorId, string $operatorType): array
    {
        if ((int) $order['status'] !== self::STATUS_PENDING_CONFIRM) {
            // 只有"待确认"的订单才能结算，防止已结算订单被重复分账
            throw new \RuntimeException('订单当前状态不可结算');
        }

        $orderId     = (int) $order['id'];
        $amount      = (float) $order['amount'];
        $feeRate     = Setting::float('platform_fee_rate', 20.0);
        $platformFee = safe_float($amount * $feeRate / 100);
        if ($platformFee < 0) {
            $platformFee = 0.0;
        }
        if ($platformFee > $amount) {
            $platformFee = $amount;
        }
        $income = safe_float($amount - $platformFee);

        if ($income > 0) {
            Wallet::income((int) $order['companion_id'], $income, 'order_income', 'order', $orderId, '订单收入 ' . $order['order_no'], [
                'total_income' => new \App\Expr('total_income + ' . $income),
            ]);
        }

        self::updateCompanionStat((int) $order['companion_id'], $amount - $platformFee);

        return self::setStatus($order, self::STATUS_COMPLETED, $operatorId, $operatorType, [
            'confirm_time'   => now(),
            'platform_fee'   => $platformFee,
            'companion_income' => $income,
        ], $operatorType === 'user' ? '用户确认完成' : '系统/后台确认完成');
    }

    // ---------------------------------------------------------------- 取消与退款

    /** 取消订单：未支付直接关闭；已支付未接单退款；服务中需申请退款 */
    public static function cancel(int $orderId, int $operatorId, string $operatorType, string $reason = ''): array
    {
        return Db::tx(function () use ($orderId, $operatorId, $operatorType, $reason) {
            if (!in_array($operatorType, ['user', 'companion', 'admin'], true)) {
                throw new \RuntimeException('操作方不合法');
            }
            $order  = self::lock($orderId);
            $status = (int) $order['status'];

            if ($operatorType === 'user' && (int) $order['user_id'] !== $operatorId) {
                throw new \RuntimeException('无权操作该订单');
            }
            if ($operatorType === 'companion' && (int) $order['companion_id'] !== $operatorId) {
                throw new \RuntimeException('无权操作该订单');
            }

            if ($status === self::STATUS_PENDING_PAY) {
                return self::setStatus($order, self::STATUS_CLOSED, $operatorId, $operatorType, [
                    'close_time'    => now(),
                    'cancel_reason' => mb_substr($reason !== '' ? $reason : '订单取消', 0, 200),
                ], '订单关闭');
            }

            if ($status === self::STATUS_PENDING_ACCEPT) {
                return self::refund($order, $operatorId, $operatorType, $reason !== '' ? $reason : '取消订单退款');
            }

            throw new \RuntimeException('订单已开始服务，请通过“申请退款”提交诉求');
        });
    }

    /** 用户/后台申请退款 */
    public static function applyRefund(int $orderId, int $userId, string $reason): array
    {
        return Db::tx(function () use ($orderId, $userId, $reason) {
            $order = self::lock($orderId);
            if ((int) $order['user_id'] !== $userId) {
                throw new \RuntimeException('无权操作该订单');
            }
            $status = (int) $order['status'];
            if (!in_array($status, [self::STATUS_PENDING_ACCEPT, self::STATUS_IN_SERVICE, self::STATUS_PENDING_CONFIRM], true)) {
                throw new \RuntimeException('当前状态不可申请退款');
            }
            if ($status === self::STATUS_PENDING_ACCEPT) {
                // 还没开始服务，直接全额退款
                return self::refund($order, $userId, 'user', $reason);
            }
            return self::setStatus($order, self::STATUS_REFUNDING, $userId, 'user', [
                'refund_reason'      => mb_substr($reason, 0, 200),
                'refund_from_status' => $status, // 记录申请前的状态，驳回时原样退回
            ], '用户申请退款，等待平台处理');
        });
    }

    /** 后台审核退款 */
    public static function auditRefund(int $orderId, int $adminId, bool $agree, string $remark = ''): array
    {
        return Db::tx(function () use ($orderId, $adminId, $agree, $remark) {
            $order = self::lock($orderId);
            if ((int) $order['status'] !== self::STATUS_REFUNDING) {
                throw new \RuntimeException('该订单不在退款审核中');
            }
            if ($agree) {
                return self::refund($order, $adminId, 'admin', $remark !== '' ? $remark : '平台同意退款');
            }

            // 驳回：回到用户申请退款之前的状态（服务中 3 / 待确认 4），避免跳过陪玩"提交完成"
            $fromStatus = (int) ($order['refund_from_status'] ?? 0);
            if (!in_array($fromStatus, [self::STATUS_IN_SERVICE, self::STATUS_PENDING_CONFIRM], true)) {
                $fromStatus = self::STATUS_PENDING_CONFIRM;
            }

            return self::setStatus($order, $fromStatus, $adminId, 'admin', [
                'refund_remark'      => mb_substr($remark, 0, 200),
                'refund_operator'    => 'admin',
                'refund_from_status' => 0,
            ], '平台驳回退款申请，订单回到原状态继续');
        });
    }

    /** 全额退款到用户余额 */
    public static function refund(array $order, int $operatorId, string $operatorType, string $reason = ''): array
    {
        $status = (int) $order['status'];
        if (!in_array($status, [
            self::STATUS_PENDING_ACCEPT,
            self::STATUS_IN_SERVICE,
            self::STATUS_PENDING_CONFIRM,
            self::STATUS_REFUNDING,
        ], true)) {
            // 已完成/已退款/已关闭的订单不允许再次退款，防止把已结算的订单重复退给用户
            throw new \RuntimeException('当前订单状态不可退款');
        }

        $orderId = (int) $order['id'];
        $amount  = (float) $order['amount'];
        if ($amount > 0) {
            Wallet::income((int) $order['user_id'], $amount, 'order_refund', 'order', $orderId, '订单退款 ' . $order['order_no']);
        }
        return self::setStatus($order, self::STATUS_REFUNDED, $operatorId, $operatorType, [
            'close_time'         => now(),
            'cancel_reason'      => mb_substr($reason, 0, 200),
            'refund_operator'    => $operatorType,
            'refund_from_status' => 0,
        ], '订单已退款');
    }

    // ---------------------------------------------------------------- 自动流转（cron 调用）

    /** 关闭超时未支付订单，返回处理数量 */
    public static function autoCloseExpired(): int
    {
        $rows = Db::all(
            'SELECT * FROM orders WHERE status = ? AND expire_at IS NOT NULL AND expire_at < ? LIMIT 200',
            [self::STATUS_PENDING_PAY, now()]
        );
        $count = 0;
        foreach ($rows as $order) {
            try {
                $done = Db::tx(function () use ($order) {
                    $fresh = self::lock((int) $order['id']);
                    if ((int) $fresh['status'] !== self::STATUS_PENDING_PAY) {
                        return false;
                    }
                    self::setStatus($fresh, self::STATUS_CLOSED, 0, 'system', [
                        'close_time'    => now(),
                        'cancel_reason' => '超时未支付，系统自动关闭',
                    ], '超时未支付自动关闭');
                    return true;
                });
                if ($done) {
                    $count++;
                }
            } catch (\Throwable $e) {
                // 单笔失败不影响其他订单
            }
        }
        return $count;
    }

    /** 自动确认完成（服务完成超过 N 小时） */
    public static function autoConfirmFinished(): int
    {
        $hours = max(1, Setting::int('order_auto_confirm_hours', 24));
        $deadline = date('Y-m-d H:i:s', time() - $hours * 3600);
        $rows = Db::all(
            'SELECT * FROM orders WHERE status = ? AND finish_time IS NOT NULL AND finish_time < ? LIMIT 200',
            [self::STATUS_PENDING_CONFIRM, $deadline]
        );
        $count = 0;
        foreach ($rows as $order) {
            try {
                $done = Db::tx(function () use ($order) {
                    $fresh = self::lock((int) $order['id']);
                    if ((int) $fresh['status'] !== self::STATUS_PENDING_CONFIRM) {
                        return false;
                    }
                    self::settle($fresh, 0, 'system');
                    return true;
                });
                if ($done) {
                    $count++;
                }
            } catch (\Throwable $e) {
                // 忽略单笔异常
            }
        }
        return $count;
    }

    // ---------------------------------------------------------------- 查询/工具

    /** @return array<string,mixed> */
    public static function find(int $orderId): array
    {
        $order = Db::one('SELECT * FROM orders WHERE id = ? LIMIT 1', [$orderId]);
        if (!$order) {
            throw new \RuntimeException('订单不存在');
        }
        return $order;
    }

    /** 带行锁读取 */
    private static function lock(int $orderId): array
    {
        $order = Db::one('SELECT * FROM orders WHERE id = ? FOR UPDATE', [$orderId]);
        if (!$order) {
            throw new \RuntimeException('订单不存在');
        }
        return $order;
    }

    /** 更新订单状态并写日志 */
    public static function setStatus(array $order, int $toStatus, int $operatorId, string $operatorType, array $extra = [], string $remark = ''): array
    {
        $fromStatus = (int) $order['status'];
        $data       = array_merge($extra, [
            'status'     => $toStatus,
            'updated_at' => now(),
        ]);
        Db::update('orders', $data, 'id = ?', [(int) $order['id']]);
        self::log((int) $order['id'], $fromStatus, $toStatus, $operatorId, $operatorType, $remark);

        $order['status'] = $toStatus;
        foreach ($extra as $k => $v) {
            $order[$k] = $v;
        }
        return $order;
    }

    public static function log(int $orderId, int $from, int $to, int $operatorId, string $operatorType, string $remark = ''): void
    {
        Db::insert('order_logs', [
            'order_id'      => $orderId,
            'from_status'   => $from,
            'to_status'     => $to,
            'operator_id'   => $operatorId,
            'operator_type' => $operatorType,
            'remark'        => mb_substr($remark, 0, 250),
            'created_at'    => now(),
        ]);
    }

    /** 更新陪玩统计（完成单数、累计收入） */
    private static function updateCompanionStat(int $companionId, float $income): void
    {
        Db::query(
            'UPDATE companion_profiles
             SET order_count = order_count + 1,
                 total_income = total_income + ?,
                 updated_at = ?
             WHERE user_id = ?',
            [safe_float($income), now(), $companionId]
        );
    }

    /** 订单列表查询（可复用于前台与后台） */
    public static function paginate(array $filter, int $page, int $perPage = 10): array
    {
        $where  = ['1 = 1'];
        $params = [];

        if (!empty($filter['user_id'])) {
            $where[]  = 'o.user_id = ?';
            $params[] = (int) $filter['user_id'];
        }
        if (!empty($filter['companion_id'])) {
            $where[]  = 'o.companion_id = ?';
            $params[] = (int) $filter['companion_id'];
        }
        if (!empty($filter['status'])) {
            if (is_array($filter['status'])) {
                $where[] = 'o.status IN (' . implode(',', array_fill(0, count($filter['status']), '?')) . ')';
                foreach ($filter['status'] as $s) {
                    $params[] = (int) $s;
                }
            } else {
                $where[]  = 'o.status = ?';
                $params[] = (int) $filter['status'];
            }
        }
        if (!empty($filter['keyword'])) {
            $kw      = '%' . like_escape((string) $filter['keyword']) . '%';
            $where[] = '(o.order_no LIKE ? OR o.game_name LIKE ? OR u.nickname LIKE ? OR c.nickname LIKE ?)';
            array_push($params, $kw, $kw, $kw, $kw);
        }
        if (!empty($filter['date_start'])) {
            $where[]  = 'o.created_at >= ?';
            $params[] = $filter['date_start'] . ' 00:00:00';
        }
        if (!empty($filter['date_end'])) {
            $where[]  = 'o.created_at <= ?';
            $params[] = $filter['date_end'] . ' 23:59:59';
        }

        $whereSql = implode(' AND ', $where);
        $total    = (int) Db::value(
            "SELECT COUNT(*) FROM orders o
             LEFT JOIN users u ON u.id = o.user_id
             LEFT JOIN users c ON c.id = o.companion_id
             WHERE $whereSql",
            $params
        );

        $page    = max(1, $page);
        $offset  = ($page - 1) * $perPage;
        $rows    = Db::all(
            "SELECT o.*, u.nickname AS user_nickname, u.avatar AS user_avatar,
                    c.nickname AS companion_nickname, c.avatar AS companion_avatar
             FROM orders o
             LEFT JOIN users u ON u.id = o.user_id
             LEFT JOIN users c ON c.id = o.companion_id
             WHERE $whereSql
             ORDER BY o.id DESC
             LIMIT $perPage OFFSET $offset",
            $params
        );

        return ['total' => $total, 'rows' => $rows, 'page' => $page, 'per_page' => $perPage];
    }
}
