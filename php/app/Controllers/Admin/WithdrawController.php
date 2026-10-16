<?php
/**
 * 后台提现审核
 */
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth;
use App\Db;

class WithdrawController extends BaseController
{
    /** 列表筛选条件（列表与导出共用） */
    private function currentFilter(): array
    {
        $status  = input_str('status', '0');
        $keyword = input_str('keyword');

        $where  = ['1 = 1'];
        $params = [];

        if ($status !== 'all' && in_array($status, ['0', '1', '2'], true)) {
            $where[]  = 'w.status = ?';
            $params[] = (int) $status;
        }
        if ($keyword !== '') {
            $kw      = '%' . like_escape($keyword) . '%';
            $where[] = '(w.order_no LIKE ? OR u.nickname LIKE ? OR w.account_name LIKE ? OR w.account_no LIKE ?)';
            array_push($params, $kw, $kw, $kw, $kw);
        }

        return [implode(' AND ', $where), $params, $status, $keyword];
    }

    public function index()
    {
        list($whereSql, $params, $status, $keyword) = $this->currentFilter();

        $perPage  = 20;
        $page     = $this->page();
        $total    = (int) Db::value("SELECT COUNT(*) FROM withdraw_orders w LEFT JOIN users u ON u.id = w.user_id WHERE $whereSql", $params);
        $offset   = ($page - 1) * $perPage;
        $rows     = Db::all(
            "SELECT w.*, u.nickname, u.phone
             FROM withdraw_orders w LEFT JOIN users u ON u.id = w.user_id
             WHERE $whereSql
             ORDER BY w.status ASC, w.id DESC
             LIMIT $perPage OFFSET $offset",
            $params
        );

        $sum = Db::one(
            'SELECT COALESCE(SUM(CASE WHEN status = 0 THEN amount ELSE 0 END), 0) AS pending_amount,
                    COUNT(CASE WHEN status = 0 THEN 1 END) AS pending_count,
                    COALESCE(SUM(CASE WHEN status = 1 THEN amount ELSE 0 END), 0) AS paid_amount
             FROM withdraw_orders'
        );

        $this->view('admin/withdraw/index', [
            'title'    => '提现审核',
            'rows'     => $rows,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'status'   => $status,
            'keyword'  => $keyword,
            'sum'      => $sum ?: [],
        ]);
    }

    /** 导出提现单（CSV，供财务批量打款与对账） */
    public function export()
    {
        list($whereSql, $params, $status, $keyword) = $this->currentFilter();

        $rows = Db::all(
            "SELECT w.*, u.nickname, u.phone
             FROM withdraw_orders w LEFT JOIN users u ON u.id = w.user_id
             WHERE $whereSql
             ORDER BY w.status ASC, w.id DESC
             LIMIT 5000",
            $params
        );

        $accountText = ['alipay' => '支付宝', 'wechat' => '微信', 'bank' => '银行卡'];
        $statusText  = [0 => '待审核', 1 => '已打款', 2 => '已驳回'];

        $filename = 'withdraws_' . date('Ymd_His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo "\xEF\xBB\xBF";

        $out = fopen('php://output', 'w');
        fputcsv($out, [
            '提现单号', '用户', '手机号', '申请金额', '手续费', '实际到账',
            '收款方式', '收款人', '收款账号', '状态', '提交时间', '审核时间', '审核备注',
        ]);
        foreach ($rows as $w) {
            fputcsv($out, [
                $w['order_no'],
                $w['nickname'],
                (string) $w['phone'],
                money((float) $w['amount']),
                money((float) $w['fee']),
                money((float) $w['real_amount']),
                $accountText[$w['account_type']] ?? $w['account_type'],
                $w['account_name'],
                $w['account_no'],
                $statusText[(int) $w['status']] ?? (string) $w['status'],
                (string) $w['created_at'],
                (string) ($w['audit_at'] ?: ''),
                (string) $w['audit_remark'],
            ]);
        }
        fclose($out);

        Auth::logAdmin('导出提现单', '状态 ' . $status . '，共 ' . count($rows) . ' 条');
        exit;
    }

    /** 审核：1 已打款，2 驳回 */
    public function audit()
    {
        require_post();
        verify_csrf();

        $id     = input_int('id', 0);
        $result = input_int('result', 1);
        $remark = input_str('remark');

        $this->run(function () use ($id, $result, $remark) {
            \App\FinanceService::auditWithdraw($id, Auth::id(), $result, $remark);

            Auth::logAdmin('提现审核', '提现单ID ' . $id . ' ' . ($result === 1 ? '已打款' : '驳回') . ' ' . $remark);
            return true;
        }, '处理完成', admin_url('withdraw/index', ['status' => '0']));
    }
}
