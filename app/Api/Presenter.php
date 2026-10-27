<?php
/**
 * 数据呈现层：把数据库行转换成 App 直接可用的结构。
 *
 * 两条铁律：
 *  1. 敏感字段绝不外泄（password / id_card / real_name / 各种密钥），一律经过这里洗一遍。
 *  2. 相对路径统一转成绝对 URL，枚举值统一附带 *_text 文案，避免 App 端硬编码中文。
 */
declare(strict_types=1);

namespace App\Api;

use App\OrderService;

class Presenter
{
    // ---------------------------------------------------------------- 通用

    /** 相对上传路径 → 绝对 URL（已经是 http(s) 的原样返回） */
    public static function url($path): string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        return upload_url($path);
    }

    /** 头像：为空返回空串，由 App 自己画「首字母占位图」（API 不返回 SVG data URI） */
    public static function avatar($path): string
    {
        return self::url($path);
    }

    /**
     * 分页结果统一包装
     * @param callable $fn 单行转换函数
     */
    public static function page(array $result, callable $fn): array
    {
        $list = [];
        foreach ((array) ($result['rows'] ?? []) as $row) {
            $list[] = $fn($row);
        }
        $total   = (int) ($result['total'] ?? count($list));
        $perPage = max(1, (int) ($result['per_page'] ?? 10));
        $page    = max(1, (int) ($result['page'] ?? 1));

        return [
            'list'       => $list,
            'total'      => $total,
            'page'       => $page,
            'per_page'   => $perPage,
            'total_page' => (int) ceil($total / $perPage),
            'has_more'   => $page * $perPage < $total,
        ];
    }

    // ---------------------------------------------------------------- 用户

    public static function user(?array $row): ?array
    {
        if (!$row) {
            return null;
        }
        $role = (string) ($row['role'] ?? 'user');
        $uid = (int) ($row['id'] ?? 0);
        $no  = (int) ($row['no'] ?? 0);
        if ($no <= 0) {
            // 升级脚本还没跑：按和回填 SQL 相同的公式现算，
            // 保证前端**永远有一个号可显示**，且和迁移后读到的值一致。
            $no = \App\Auth::fallbackUserNo($uid);
        }

        return [
            'id'           => $uid,
            // 对外展示的随机 6 位用户号：个人中心用它当「ID」，不用自增主键
            'no'           => $no,
            'username'     => (string) ($row['username'] ?? ''),
            'nickname'     => (string) ($row['nickname'] ?? ''),
            'avatar_url'   => self::avatar($row['avatar'] ?? ''),
            'phone'        => (string) ($row['phone'] ?? ''),
            'email'        => (string) ($row['email'] ?? ''),
            'gender'       => (int) ($row['gender'] ?? 0),
            'role'         => $role,
            'credit_score' => (int) ($row['credit_score'] ?? 100),
            'is_admin'     => $role === 'admin',
            'is_companion' => $role === 'companion',
            'created_at'   => (string) ($row['created_at'] ?? ''),
        ];
    }

    /** 登录/注册后返回的「我」：用户资料 + 钱包摘要 + 陪玩身份状态 */
    public static function me(?array $userRow, array $walletRow, ?array $companionProfile): array
    {
        if (!$userRow) {
            throw ApiException::unauthorized('账号不存在或已被删除，请重新登录');
        }

        $uid    = (int) $userRow['id'];
        $audit  = $companionProfile ? (int) $companionProfile['audit_status'] : -1;

        return [
            'user'   => self::user($userRow),
            'wallet' => self::wallet($walletRow),
            'companion' => [
                'applied'      => $companionProfile !== null,
                'audit_status' => $audit,
                'audit_text'   => $companionProfile ? audit_status_text($audit) : '未申请',
                'audit_remark' => (string) ($companionProfile['audit_remark'] ?? ''),
                'online'       => $companionProfile ? ((int) $companionProfile['online_status'] === 1) : false,
                // 这里刻意不返回陪玩资料本体：它是高频接口，实名/身份证属于敏感信息，
                // 需要编辑时 App 单独调 companion/profile（那个走 myCompanion，限本人）。
            ],
            'unread_message' => OrderService::unreadTotal($uid),
        ];
    }

    /**
     * 陪玩资料「本人视角」：包含实名与证件号，**只能用于当前登录用户自己的接口**。
     * 对外公开的陪玩主页请用 [companion]，那个会剥掉这些字段。
     */
    public static function myCompanion(?array $profile): ?array
    {
        if (!$profile) {
            return null;
        }

        // 调用方经常只传 companion_profiles 的单表行（没有 JOIN users），
        // 那样 nickname / avatar 会恒为空串，所以这里按 user_id 补一次。
        if (!array_key_exists('nickname', $profile)) {
            $ownerId = (int) ($profile['user_id'] ?? 0);
            if ($ownerId > 0) {
                $owner = \App\Db::one(
                    'SELECT id, nickname, avatar, gender, credit_score FROM users WHERE id = ? LIMIT 1',
                    [$ownerId]
                );
                if ($owner) {
                    $profile = array_merge($owner, $profile);
                }
            }
        }

        $tags = (string) ($profile['tags'] ?? '');

        return [
            'user_id'      => (int) ($profile['user_id'] ?? 0),
            'nickname'     => (string) ($profile['nickname'] ?? ''),
            'avatar_url'   => self::avatar($profile['avatar'] ?? ''),
            'real_name'    => (string) ($profile['real_name'] ?? ''),
            'id_card'      => (string) ($profile['id_card'] ?? ''),
            'phone'        => (string) ($profile['phone'] ?? ''),
            'gender'       => (int) ($profile['gender'] ?? 0),
            'city'         => (string) ($profile['city'] ?? ''),
            'intro'        => (string) ($profile['intro'] ?? ''),
            'tags'         => $tags === '' ? [] : array_values(array_filter(array_map('trim', explode(',', $tags)), function ($t) {
                return $t !== '';
            })),
            'cover_url'    => self::url($profile['cover'] ?? ''),
            'voice_url'    => self::url($profile['voice_url'] ?? ''),
            'score'        => (float) ($profile['score'] ?? 5),
            'good_rate'    => (float) ($profile['good_rate'] ?? 100),
            'order_count'  => (int) ($profile['order_count'] ?? 0),
            'review_count' => (int) ($profile['review_count'] ?? 0),
            'total_income' => (float) ($profile['total_income'] ?? 0),
            'audit_status' => (int) ($profile['audit_status'] ?? 0),
            'audit_text'   => audit_status_text((int) ($profile['audit_status'] ?? 0)),
            'audit_remark' => (string) ($profile['audit_remark'] ?? ''),
            'online'       => (int) ($profile['online_status'] ?? 0) === 1,
            'is_recommend' => (int) ($profile['is_recommend'] ?? 0) === 1,
        ];
    }

    // ---------------------------------------------------------------- 陪玩

    public static function companion(?array $row): ?array
    {
        if (!$row) {
            return null;
        }
        $tags     = (string) ($row['tags'] ?? '');
        $online   = (int) ($row['online_status'] ?? 0) === 1;
        $hasPrice = array_key_exists('min_price', $row) && $row['min_price'] !== null;
        $minPrice = $hasPrice ? (float) $row['min_price'] : 0.0;

        return [
            'user_id'        => (int) ($row['user_id'] ?? ($row['uid'] ?? 0)),
            'nickname'       => (string) ($row['nickname'] ?? ''),
            'avatar_url'     => self::avatar($row['avatar'] ?? ''),
            'gender'         => (int) ($row['gender'] ?? 0),
            'city'           => (string) ($row['city'] ?? ''),
            'intro'          => (string) ($row['intro'] ?? ''),
            'cover_url'      => self::url($row['cover'] ?? ''),
            'voice_url'      => self::url($row['voice_url'] ?? ''),
            'tags'           => $tags === '' ? [] : array_values(array_filter(array_map('trim', explode(',', $tags)), function ($t) {
                return $t !== '';
            })),
            'score'          => (float) ($row['score'] ?? 5),
            'good_rate'      => (float) ($row['good_rate'] ?? 100),
            'order_count'    => (int) ($row['order_count'] ?? 0),
            'review_count'   => (int) ($row['review_count'] ?? 0),
            'credit_score'   => (int) ($row['credit_score'] ?? 100),
            'online'         => $online,
            'online_text'    => $online ? '接单中' : '休息中',
            'is_recommend'   => (int) ($row['is_recommend'] ?? 0) === 1,
            'audit_status'   => (int) ($row['audit_status'] ?? 0),
            'min_price'      => $hasPrice ? $minPrice : null,
            'min_price_text' => $hasPrice ? ('¥' . number_format($minPrice, 2)) : '面议',
            'skill_count'    => (int) ($row['skill_count'] ?? 0),
        ];
    }

    public static function skill(?array $row): ?array
    {
        if (!$row) {
            return null;
        }
        $type  = (string) ($row['service_type'] ?? 'hour');
        $price = (float) ($row['price'] ?? 0);

        return [
            'id'                => (int) ($row['id'] ?? 0),
            'companion_id'      => (int) ($row['companion_id'] ?? 0),
            'game_id'           => (int) ($row['game_id'] ?? 0),
            'game_name'         => (string) ($row['game_name'] ?? ''),
            'title'             => (string) ($row['title'] ?? ''),
            'service_type'      => $type,
            'service_type_text' => service_type_text($type),
            'unit'              => service_unit_suffix($type),
            'rank_level'        => (string) ($row['rank_level'] ?? ''),
            'price'             => $price,
            'price_text'        => '¥' . number_format($price, 2),
            'cover_url'         => self::url($row['cover'] ?? ''),
            'description'       => (string) ($row['description'] ?? ''),
            'status'            => (int) ($row['status'] ?? 1),
            'sort'              => (int) ($row['sort'] ?? 0),
        ];
    }

    // ---------------------------------------------------------------- 订单

    /**
     * @param int $viewerId 当前登录用户，用于算出「我能不能干这件事」
     */
    public static function order(?array $row, int $viewerId = 0): ?array
    {
        if (!$row) {
            return null;
        }

        // 列表 SQL（paginate / hallList）已经 join 出昵称头像；
        // OrderService::find() 只返回 orders 单表行，这里按需补一次，避免列表出现 N+1 查询。
        if (!array_key_exists('user_nickname', $row) && !array_key_exists('companion_nickname', $row)) {
            $row = self::attachParties($row);
        }

        $status  = (int) ($row['status'] ?? 0);
        $userId  = (int) ($row['user_id'] ?? 0);
        $compId  = (int) ($row['companion_id'] ?? 0);
        $isBuyer = $viewerId > 0 && $viewerId === $userId;
        $isComp  = $viewerId > 0 && $compId > 0 && $viewerId === $compId;

        // 是否已到可聊天阶段（复用订单服务里的判断，保证和网页端一致）
        $canChat = false;
        try {
            $canChat = OrderService::chatOpen($row);
        } catch (\Throwable $e) {
            $canChat = false;
        }

        $amount = (float) ($row['amount'] ?? 0);

        return [
            'id'               => (int) ($row['id'] ?? 0),
            'order_no'         => (string) ($row['order_no'] ?? ''),
            'user_id'          => $userId,
            'companion_id'     => $compId,
            'game_id'          => (int) ($row['game_id'] ?? 0),
            'game_name'        => (string) ($row['game_name'] ?? ''),
            'skill_id'         => (int) ($row['skill_id'] ?? 0),
            'skill_title'      => (string) ($row['skill_title'] ?? ''),
            'service_type'     => (string) ($row['service_type'] ?? ''),
            'quantity'         => (int) ($row['quantity'] ?? 1),
            'unit_price'       => (float) ($row['unit_price'] ?? 0),
            'unit_price_text'  => '¥' . number_format((float) ($row['unit_price'] ?? 0), 2),
            'amount'           => $amount,
            'amount_text'      => '¥' . number_format($amount, 2),
            'platform_fee'     => (float) ($row['platform_fee'] ?? 0),
            'companion_income' => (float) ($row['companion_income'] ?? 0),
            'status'           => $status,
            'status_text'      => order_status_text($status),
            'status_class'     => order_status_class($status),
            'user_remark'      => (string) ($row['user_remark'] ?? ''),
            'cancel_reason'    => (string) ($row['cancel_reason'] ?? ''),
            'refund_reason'    => (string) ($row['refund_reason'] ?? ''),
            'refund_remark'    => (string) ($row['refund_remark'] ?? ''),
            'cover_url'        => self::url($row['cover'] ?? ''),
            'is_public'        => (int) ($row['is_public'] ?? 0) === 1,
            'is_reviewed'      => (int) ($row['is_reviewed'] ?? 0) === 1,
            'expire_at'        => (string) ($row['expire_at'] ?? ''),
            'pay_time'         => (string) ($row['pay_time'] ?? ''),
            'finish_time'      => (string) ($row['finish_time'] ?? ''),
            'created_at'       => (string) ($row['created_at'] ?? ''),
            'updated_at'       => (string) ($row['updated_at'] ?? ''),

            // 对方信息（列表 SQL 里带了 join 才有）
            'user_nickname'    => (string) ($row['user_nickname'] ?? ''),
            'user_avatar_url'  => self::avatar($row['user_avatar'] ?? ''),
            'companion_nickname'  => (string) ($row['companion_nickname'] ?? ''),
            'companion_avatar_url'=> self::avatar($row['companion_avatar'] ?? ''),

            // 我在本单里的角色
            'my_role'   => $isBuyer ? 'buyer' : ($isComp ? 'companion' : 'guest'),
            'is_buyer'  => $isBuyer,
            'is_companion' => $isComp,
            'can_chat'  => $canChat,

            // 操作能力（App 据此显示/隐藏按钮，服务端仍会再校验一次）
            // 规则严格对齐网页端：状态 3（服务中）起不能再「取消」，只能走「申请退款」
            'can_pay'     => $isBuyer && $status === OrderService::STATUS_PENDING_PAY,
            'can_cancel'  => $isBuyer && in_array($status, [
                OrderService::STATUS_PENDING_PAY,
                OrderService::STATUS_PENDING_ACCEPT,
            ], true),
            'can_refund'  => $isBuyer && in_array($status, [
                OrderService::STATUS_PENDING_ACCEPT,
                OrderService::STATUS_IN_SERVICE,
                OrderService::STATUS_PENDING_CONFIRM,
            ], true),
            'can_confirm' => $isBuyer && $status === OrderService::STATUS_PENDING_CONFIRM,
            'can_review'  => $isBuyer && $status === OrderService::STATUS_COMPLETED && (int) ($row['is_reviewed'] ?? 0) !== 1,
            'can_grab'    => $status === OrderService::STATUS_PENDING_ACCEPT
                             && (int) ($row['is_public'] ?? 0) === 1
                             && $compId === 0
                             && $viewerId > 0
                             && $viewerId !== $userId,
            'can_accept'  => $isComp && $status === OrderService::STATUS_PENDING_ACCEPT,
            'can_finish'  => $isComp && $status === OrderService::STATUS_IN_SERVICE,
        ];
    }

    /**
     * 给订单行补上买卖双方的昵称与头像。
     * OrderService::find() 只查 orders 单表，展示订单详情时需要这两组信息。
     */
    public static function attachParties(array $order): array
    {
        $ids = [];
        foreach ([(int) ($order['user_id'] ?? 0), (int) ($order['companion_id'] ?? 0)] as $id) {
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        if (!$ids) {
            $order['user_nickname']        = '';
            $order['user_avatar']          = '';
            $order['companion_nickname']   = '';
            $order['companion_avatar']     = '';
            return $order;
        }

        $ids  = array_values($ids);
        $ph   = implode(',', array_fill(0, count($ids), '?'));
        $rows = \App\Db::all("SELECT id, nickname, avatar FROM users WHERE id IN ($ph)", $ids);

        $map = [];
        foreach ($rows as $r) {
            $map[(int) $r['id']] = $r;
        }

        $u = $map[(int) ($order['user_id'] ?? 0)] ?? null;
        $c = $map[(int) ($order['companion_id'] ?? 0)] ?? null;

        $order['user_nickname']      = (string) ($u['nickname'] ?? '');
        $order['user_avatar']        = (string) ($u['avatar'] ?? '');
        $order['companion_nickname'] = (string) ($c['nickname'] ?? '');
        $order['companion_avatar']   = (string) ($c['avatar'] ?? '');

        return $order;
    }

    public static function orderLog(?array $row): ?array
    {
        if (!$row) {
            return null;
        }
        $to = (int) ($row['to_status'] ?? 0);
        return [
            'id'          => (int) ($row['id'] ?? 0),
            'from_status' => (int) ($row['from_status'] ?? 0),
            'to_status'   => $to,
            'to_text'     => order_status_text($to),
            'operator_type' => (string) ($row['operator_type'] ?? 'system'),
            'remark'      => (string) ($row['remark'] ?? ''),
            'created_at'  => (string) ($row['created_at'] ?? ''),
        ];
    }

    public static function message(?array $row, int $viewerId = 0): ?array
    {
        if (!$row) {
            return null;
        }
        $senderId = (int) ($row['sender_id'] ?? 0);
        return [
            'id'          => (int) ($row['id'] ?? 0),
            'order_id'    => (int) ($row['order_id'] ?? 0),
            'sender_id'   => $senderId,
            'sender_role' => (string) ($row['sender_role'] ?? 'user'),
            'content'     => (string) ($row['content'] ?? ''),
            'is_mine'     => $viewerId > 0 && $senderId === $viewerId,
            'created_at'  => (string) ($row['created_at'] ?? ''),
        ];
    }

    public static function review(?array $row): ?array
    {
        if (!$row) {
            return null;
        }
        $anonymous = (int) ($row['is_anonymous'] ?? 0) === 1;
        return [
            'id'           => (int) ($row['id'] ?? 0),
            'order_id'     => (int) ($row['order_id'] ?? 0),
            'user_id'      => (int) ($row['user_id'] ?? 0),
            'companion_id' => (int) ($row['companion_id'] ?? 0),
            'score'        => (int) ($row['score'] ?? 5),
            'content'      => (string) ($row['content'] ?? ''),
            'tags'         => self::splitTags((string) ($row['tags'] ?? '')),
            'is_anonymous' => $anonymous,
            'nickname'     => $anonymous ? '匿名用户' : (string) ($row['nickname'] ?? ''),
            'avatar_url'   => $anonymous ? '' : self::avatar($row['avatar'] ?? ''),
            'reply'        => (string) ($row['reply'] ?? ''),
            'reply_at'     => (string) ($row['reply_at'] ?? ''),
            'created_at'   => (string) ($row['created_at'] ?? ''),
        ];
    }

    /** @return array<int,string> */
    private static function splitTags(string $tags): array
    {
        if (trim($tags) === '') {
            return [];
        }
        return array_values(array_filter(array_map('trim', explode(',', $tags)), function ($t) {
            return $t !== '';
        }));
    }

    // ---------------------------------------------------------------- 钱包

    public static function wallet(?array $row): array
    {
        $row = $row ?: [];
        $g = function (string $k) use ($row): float {
            return (float) ($row[$k] ?? 0);
        };
        return [
            'balance'        => $g('balance'),
            'balance_text'   => number_format($g('balance'), 2),
            'frozen'         => $g('frozen'),
            'frozen_text'    => number_format($g('frozen'), 2),
            'total_recharge' => $g('total_recharge'),
            'total_income'   => $g('total_income'),
            'total_withdraw' => $g('total_withdraw'),
            'total_expense'  => $g('total_expense'),
        ];
    }

    public static function transaction(?array $row): ?array
    {
        if (!$row) {
            return null;
        }
        $type      = (string) ($row['type'] ?? '');
        $direction = (int) ($row['direction'] ?? 1);
        $amount    = (float) ($row['amount'] ?? 0);

        return [
            'id'             => (int) ($row['id'] ?? 0),
            'type'           => $type,
            'type_text'      => wallet_type_text($type),
            'direction'      => $direction,
            'direction_text' => $direction === 1 ? '收入' : '支出',
            'amount'         => $amount,
            'amount_text'    => ($direction === 1 ? '+' : '-') . number_format($amount, 2),
            'balance_after'  => (float) ($row['balance_after'] ?? 0),
            'frozen_after'   => (float) ($row['frozen_after'] ?? 0),
            'remark'         => (string) ($row['remark'] ?? ''),
            'created_at'     => (string) ($row['created_at'] ?? ''),
        ];
    }

    public static function recharge(?array $row): ?array
    {
        if (!$row) {
            return null;
        }
        $status = (int) ($row['status'] ?? 0);
        $map    = [0 => '待处理', 1 => '已到账', 2 => '已关闭', 3 => '已驳回'];
        $amount = (float) ($row['amount'] ?? 0);

        return [
            'id'           => (int) ($row['id'] ?? 0),
            'order_no'     => (string) ($row['order_no'] ?? ''),
            'amount'       => $amount,
            'amount_text'  => '¥' . number_format($amount, 2),
            'channel'      => (string) ($row['channel'] ?? 'manual'),
            'status'       => $status,
            'status_text'  => $map[$status] ?? '未知',
            'pay_voucher_url' => self::url($row['pay_voucher'] ?? ''),
            'pay_url'      => (string) ($row['pay_url'] ?? ''),
            'trade_no'     => (string) ($row['trade_no'] ?? ''),
            'audit_remark' => (string) ($row['audit_remark'] ?? ''),
            'remark'       => (string) ($row['remark'] ?? ''),
            'created_at'   => (string) ($row['created_at'] ?? ''),
            'paid_at'      => (string) ($row['paid_at'] ?? ''),
        ];
    }

    public static function withdraw(?array $row): ?array
    {
        if (!$row) {
            return null;
        }
        $status = (int) ($row['status'] ?? 0);
        $map    = [0 => '待审核', 1 => '已打款', 2 => '已驳回'];

        return [
            'id'            => (int) ($row['id'] ?? 0),
            'order_no'      => (string) ($row['order_no'] ?? ''),
            'amount'        => (float) ($row['amount'] ?? 0),
            'amount_text'   => '¥' . number_format((float) ($row['amount'] ?? 0), 2),
            'fee'           => (float) ($row['fee'] ?? 0),
            'real_amount'   => (float) ($row['real_amount'] ?? 0),
            'account_type'  => (string) ($row['account_type'] ?? 'alipay'),
            'account_name'  => (string) ($row['account_name'] ?? ''),
            'account_no'    => (string) ($row['account_no'] ?? ''),
            'status'        => $status,
            'status_text'   => $map[$status] ?? '未知',
            'audit_remark'  => (string) ($row['audit_remark'] ?? ''),
            'created_at'    => (string) ($row['created_at'] ?? ''),
        ];
    }

    // ---------------------------------------------------------------- 内容

    public static function banner(?array $row): ?array
    {
        if (!$row) {
            return null;
        }
        return [
            'id'       => (int) ($row['id'] ?? 0),
            'title'    => (string) ($row['title'] ?? ''),
            'image_url'=> self::url($row['image'] ?? ''),
            'link_url' => (string) ($row['link_url'] ?? ''),
            'sort'     => (int) ($row['sort'] ?? 0),
        ];
    }

    public static function announcement(?array $row): ?array
    {
        if (!$row) {
            return null;
        }
        return [
            'id'       => (int) ($row['id'] ?? 0),
            'content'  => (string) ($row['content'] ?? ''),
            'link_url' => (string) ($row['link_url'] ?? ''),
            'sort'     => (int) ($row['sort'] ?? 0),
        ];
    }

    public static function game(?array $row): ?array
    {
        if (!$row) {
            return null;
        }
        return [
            'id'       => (int) ($row['id'] ?? 0),
            'name'     => (string) ($row['name'] ?? ''),
            'icon_url' => self::url($row['icon'] ?? ''),
            'is_hot'   => (int) ($row['is_hot'] ?? 0) === 1,
            'sort'     => (int) ($row['sort'] ?? 0),
        ];
    }

    public static function package(?array $row): ?array
    {
        if (!$row) {
            return null;
        }
        $price = (float) ($row['price'] ?? 0);
        return [
            'id'          => (int) ($row['id'] ?? 0),
            'title'       => (string) ($row['title'] ?? ''),
            'game_id'     => (int) ($row['game_id'] ?? 0),
            'price'       => $price,
            'price_text'  => '¥' . number_format($price, 2),
            'cover_url'   => self::url($row['cover'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
            'sort'        => (int) ($row['sort'] ?? 0),
        ];
    }

    // ---------------------------------------------------------------- 工单

    public static function ticket(?array $row): ?array
    {
        if (!$row) {
            return null;
        }
        $status   = (int) ($row['status'] ?? 0);
        $category = (string) ($row['category'] ?? 'presale');

        return [
            'id'            => (int) ($row['id'] ?? 0),
            'ticket_no'     => (string) ($row['ticket_no'] ?? ''),
            'category'      => $category,
            'category_text' => \App\TicketService::categoryText($category),
            'order_id'      => (int) ($row['order_id'] ?? 0),
            'title'         => (string) ($row['title'] ?? ''),
            'content'       => (string) ($row['content'] ?? ''),
            'status'        => $status,
            'status_text'   => \App\TicketService::statusText($status),
            'reply_count'   => (int) ($row['reply_count'] ?? 0),
            'created_at'    => (string) ($row['created_at'] ?? ''),
            'updated_at'    => (string) ($row['updated_at'] ?? ''),
        ];
    }

    public static function ticketReply(?array $row): ?array
    {
        if (!$row) {
            return null;
        }
        $senderType = (string) ($row['sender_type'] ?? 'user');
        return [
            'id'          => (int) ($row['id'] ?? 0),
            'ticket_id'   => (int) ($row['ticket_id'] ?? 0),
            'sender_type' => $senderType,
            'sender_name' => $senderType === 'admin' ? '客服' : '我',
            'content'     => (string) ($row['content'] ?? ''),
            'created_at'  => (string) ($row['created_at'] ?? ''),
        ];
    }
}
