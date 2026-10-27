<?php
/**
 * 后台数据概览
 */
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Db;
use App\OrderService;

class DashboardController extends BaseController
{
    public function index()
    {
        $today = date('Y-m-d');

        $stat = [
            'user_total'    => (int) Db::value("SELECT COUNT(*) FROM users WHERE role = 'user'"),
            'companion_total' => (int) Db::value("SELECT COUNT(*) FROM users WHERE role = 'companion'"),
            'order_total'   => (int) Db::value('SELECT COUNT(*) FROM orders'),
            'order_today'   => (int) Db::value('SELECT COUNT(*) FROM orders WHERE created_at >= ?', [$today . ' 00:00:00']),
            'amount_total'  => (float) Db::value('SELECT COALESCE(SUM(amount), 0) FROM orders WHERE status = ?', [OrderService::STATUS_COMPLETED]),
            'amount_today'  => (float) Db::value(
                'SELECT COALESCE(SUM(amount), 0) FROM orders WHERE status = ? AND confirm_time >= ?',
                [OrderService::STATUS_COMPLETED, $today . ' 00:00:00']
            ),
            'fee_total'     => (float) Db::value('SELECT COALESCE(SUM(platform_fee), 0) FROM orders WHERE status = ?', [OrderService::STATUS_COMPLETED]),
            'withdraw_fee_total' => (float) Db::value(
                'SELECT COALESCE(SUM(fee), 0) FROM withdraw_orders WHERE status = 1'
            ),
            'recharge_today' => (float) Db::value(
                'SELECT COALESCE(SUM(amount), 0) FROM recharge_orders WHERE status = 1 AND paid_at >= ?',
                [$today . ' 00:00:00']
            ),
            'withdraw_today' => (float) Db::value(
                'SELECT COALESCE(SUM(amount), 0) FROM withdraw_orders WHERE status = 1 AND audit_at >= ?',
                [$today . ' 00:00:00']
            ),
        ];

        $pending = [
            'companion' => (int) Db::value('SELECT COUNT(*) FROM companion_profiles WHERE audit_status = 0'),
            'recharge'  => (int) Db::value('SELECT COUNT(*) FROM recharge_orders WHERE status = 0'),
            'withdraw'  => (int) Db::value('SELECT COUNT(*) FROM withdraw_orders WHERE status = 0'),
            'refund'    => (int) Db::value('SELECT COUNT(*) FROM orders WHERE status = ?', [OrderService::STATUS_REFUNDING]),
        ];

        $recentOrders = Db::all(
            'SELECT o.*, u.nickname AS user_nickname, c.nickname AS companion_nickname
             FROM orders o
             LEFT JOIN users u ON u.id = o.user_id
             LEFT JOIN users c ON c.id = o.companion_id
             ORDER BY o.id DESC LIMIT 10'
        );

        $statusStat = Db::all('SELECT status, COUNT(*) AS cnt FROM orders GROUP BY status');
        $statusMap = [];
        foreach ($statusStat as $row) {
            $statusMap[(int) $row['status']] = (int) $row['cnt'];
        }

        $this->view('admin/dashboard/index', [
            'title'        => '数据概览',
            'stat'         => $stat,
            'pending'      => $pending,
            'recentOrders' => $recentOrders,
            'statusMap'    => $statusMap,
        ]);
    }
}
