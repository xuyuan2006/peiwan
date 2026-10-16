<?php
/**
 * 后台充值审核
 */
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth;
use App\Db;

class RechargeController extends BaseController
{
    public function index()
    {
        $status  = input_str('status', '0');
        $keyword = input_str('keyword');

        $where  = ['1 = 1'];
        $params = [];

        if ($status !== 'all' && in_array($status, ['0', '1', '2', '3'], true)) {
            $where[]  = 'r.status = ?';
            $params[] = (int) $status;
        }
        if ($keyword !== '') {
            $kw      = '%' . $keyword . '%';
            $where[] = '(r.order_no LIKE ? OR u.nickname LIKE ? OR r.trade_no LIKE ?)';
            array_push($params, $kw, $kw, $kw);
        }

        $whereSql = implode(' AND ', $where);
        $perPage  = 20;
        $page     = $this->page();
        $total    = (int) Db::value("SELECT COUNT(*) FROM recharge_orders r LEFT JOIN users u ON u.id = r.user_id WHERE $whereSql", $params);
        $offset   = ($page - 1) * $perPage;
        $rows     = Db::all(
            "SELECT r.*, u.nickname, u.phone
             FROM recharge_orders r LEFT JOIN users u ON u.id = r.user_id
             WHERE $whereSql
             ORDER BY r.status ASC, r.id DESC
             LIMIT $perPage OFFSET $offset",
            $params
        );

        $sum = Db::one(
            'SELECT COALESCE(SUM(CASE WHEN status = 0 THEN amount ELSE 0 END), 0) AS pending_amount,
                    COUNT(CASE WHEN status = 0 THEN 1 END) AS pending_count,
                    COALESCE(SUM(CASE WHEN status = 1 THEN amount ELSE 0 END), 0) AS paid_amount
             FROM recharge_orders'
        );

        $this->view('admin/recharge/index', [
            'title'    => '充值审核',
            'rows'     => $rows,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'status'   => $status,
            'keyword'  => $keyword,
            'sum'      => $sum ?: [],
        ]);
    }

    /** 审核：1 到账，3 驳回，2 关闭 */
    public function audit()
    {
        require_post();
        verify_csrf();

        $id     = input_int('id', 0);
        $result = input_int('result', 1);
        $remark = input_str('remark');

        $this->run(function () use ($id, $result, $remark) {
            \App\FinanceService::auditRecharge($id, Auth::id(), $result, $remark);

            $map = [1 => '确认到账', 2 => '关闭', 3 => '驳回'];
            Auth::logAdmin('充值审核', '充值单ID ' . $id . ' ' . ($map[$result] ?? '') . ' ' . $remark);
            return true;
        }, '处理完成', admin_url('recharge/index', ['status' => '0']));
    }
}
