<?php
/**
 * 后台操作日志
 */
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Db;

class LogController extends BaseController
{
    public function index()
    {
        $keyword = input_str('keyword');
        $where   = '1 = 1';
        $params  = [];

        if ($keyword !== '') {
            $kw      = '%' . $keyword . '%';
            $where   = '(l.action LIKE ? OR l.detail LIKE ? OR u.nickname LIKE ?)';
            $params  = [$kw, $kw, $kw];
        }

        $perPage = 30;
        $page    = $this->page();
        $total   = (int) Db::value("SELECT COUNT(*) FROM admin_logs l LEFT JOIN users u ON u.id = l.admin_id WHERE $where", $params);
        $offset  = ($page - 1) * $perPage;
        $rows    = Db::all(
            "SELECT l.*, u.nickname
             FROM admin_logs l LEFT JOIN users u ON u.id = l.admin_id
             WHERE $where
             ORDER BY l.id DESC
             LIMIT $perPage OFFSET $offset",
            $params
        );

        $this->view('admin/log/index', [
            'title'    => '操作日志',
            'rows'     => $rows,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'keyword'  => $keyword,
        ]);
    }
}
