<?php
/**
 * 后台评价管理
 */
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth;
use App\CompanionService;
use App\Db;

class ReviewController extends BaseController
{
    public function index()
    {
        $status  = input_str('status', 'all');
        $compid  = input_int('companion_id', 0);

        $where  = ['1 = 1'];
        $params = [];
        if ($status !== 'all' && in_array($status, ['0', '1'], true)) {
            $where[]  = 'r.status = ?';
            $params[] = (int) $status;
        }
        if ($compid > 0) {
            $where[]  = 'r.companion_id = ?';
            $params[] = $compid;
        }

        $whereSql = implode(' AND ', $where);
        $perPage  = 20;
        $page     = $this->page();
        $total    = (int) Db::value("SELECT COUNT(*) FROM order_reviews r WHERE $whereSql", $params);
        $offset   = ($page - 1) * $perPage;
        $rows     = Db::all(
            "SELECT r.*, u.nickname AS user_nickname, c.nickname AS companion_nickname, o.order_no
             FROM order_reviews r
             LEFT JOIN users u ON u.id = r.user_id
             LEFT JOIN users c ON c.id = r.companion_id
             LEFT JOIN orders o ON o.id = r.order_id
             WHERE $whereSql
             ORDER BY r.id DESC
             LIMIT $perPage OFFSET $offset",
            $params
        );

        $this->view('admin/review/index', [
            'title'    => '评价管理',
            'rows'     => $rows,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'status'   => $status,
            'compid'   => $compid,
        ]);
    }

    /** 显示/隐藏评价 */
    public function toggle()
    {
        require_post();
        verify_csrf();

        $id = input_int('id', 0);
        $this->run(function () use ($id) {
            $review = Db::one('SELECT * FROM order_reviews WHERE id = ? LIMIT 1', [$id]);
            if (!$review) {
                throw new \RuntimeException('评价不存在');
            }
            $to = (int) $review['status'] === 1 ? 0 : 1;
            Db::update('order_reviews', ['status' => $to], 'id = ?', [$id]);
            CompanionService::recalcScore((int) $review['companion_id']);
            Auth::logAdmin('评价状态变更', '评价ID ' . $id . ' 状态改为 ' . $to);
            return ['status' => $to];
        }, '操作成功', admin_url('review/index'));
    }
}
