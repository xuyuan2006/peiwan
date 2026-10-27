<?php
/**
 * 订单列表的公共查询辅助：状态筛选语义与徽标计数。
 *
 * 抽出来是因为「我的订单」（下单方视角）和「陪玩订单」（接单方视角）
 * 必须用完全一致的状态筛选规则，否则用户会看到两个页面口径不一致。
 */
declare(strict_types=1);

namespace App\Api;

use App\Db;
use App\OrderService;

class OrderQuery
{
    /**
     * 状态筛选，语义与网页端 OrderController::index 完全一致：
     *   all / ''   → 不过滤
     *   refund     → 退款中 + 已退款（6, 8）
     *   closed     → 已关闭（7）
     *   '1'~'8'    → 精确匹配
     *   其它字符串 → 不过滤
     *
     * @return int|array<int,int>|null null 表示不限制状态
     */
    public static function statusFilter(string $status)
    {
        $s = strtolower(trim($status));
        if ($s === '' || $s === 'all') {
            return null;
        }
        if ($s === 'refund') {
            return [OrderService::STATUS_REFUNDING, OrderService::STATUS_REFUNDED];
        }
        if ($s === 'closed') {
            return [OrderService::STATUS_CLOSED];
        }
        if (ctype_digit($s)) {
            $n = (int) $s;
            return $n > 0 ? $n : null;
        }
        return null;
    }

    /**
     * 列表顶部徽标数量。
     *
     * 口径说明（与网页端的 Tab 分组**不同**，这里刻意做得更细）：
     *   serving         = 仅「服务中」(3)
     *   pending_accept  = 「待接单」(2)
     *   pending_confirm = 「待确认」(4)
     * 网页端把 2+3 都归进「服务中」Tab，App 端因为有三格独立展示，
     * 所以拆开统计，避免同一个订单被算进两个数字里。
     *
     * @param string $column user_id（我下的单）或 companion_id（我接的单）
     */
    public static function counts(string $column, int $uid): array
    {
        // 列名只允许白名单值，杜绝 SQL 拼接注入
        $column = $column === 'companion_id' ? 'companion_id' : 'user_id';

        $row = Db::one(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS pending_pay,
                    SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS pending_accept,
                    SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS serving,
                    SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS pending_confirm,
                    SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS done,
                    SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) AS refunding
             FROM orders WHERE `$column` = ?",
            [
                OrderService::STATUS_PENDING_PAY,
                OrderService::STATUS_PENDING_ACCEPT,
                OrderService::STATUS_IN_SERVICE,
                OrderService::STATUS_PENDING_CONFIRM,
                OrderService::STATUS_COMPLETED,
                OrderService::STATUS_REFUNDING,
                OrderService::STATUS_REFUNDED,
                $uid,
            ]
        );

        return [
            'total'           => (int) ($row['total'] ?? 0),
            'pending_pay'     => (int) ($row['pending_pay'] ?? 0),
            'pending_accept'  => (int) ($row['pending_accept'] ?? 0),
            'serving'         => (int) ($row['serving'] ?? 0),
            'pending_confirm' => (int) ($row['pending_confirm'] ?? 0),
            'done'            => (int) ($row['done'] ?? 0),
            'refunding'       => (int) ($row['refunding'] ?? 0),
        ];
    }
}
