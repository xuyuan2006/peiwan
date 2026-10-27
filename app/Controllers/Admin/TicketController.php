<?php
/**
 * 后台工单管理：售前 / 售后 / 投诉
 */
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth;
use App\TicketService;

class TicketController extends BaseController
{
    public function index()
    {
        $filter = [
            'status'   => input_str('status', '0'),
            'category' => input_str('category'),
            'keyword'  => input_str('keyword'),
        ];

        $result = TicketService::adminList($filter, $this->page(), 20);

        $counts = [
            'pending' => TicketService::pendingCount(),
            'all'     => (int) \App\Db::value('SELECT COUNT(*) FROM tickets'),
        ];

        $this->view('admin/ticket/index', [
            'title'      => '工单管理',
            'result'     => $result,
            'filter'     => $filter,
            'counts'     => $counts,
            'categories' => TicketService::categories(),
        ]);
    }

    public function detail()
    {
        $id     = input_int('id', 0);
        $ticket = $id > 0 ? TicketService::find($id) : null;
        if (!$ticket) {
            abort(404, '工单不存在');
        }

        $this->view('admin/ticket/detail', [
            'title'      => '工单 ' . $ticket['ticket_no'],
            'ticket'     => $ticket,
            'thread'     => TicketService::thread($id),
            'categories' => TicketService::categories(),
        ]);
    }

    /** 客服回复 */
    public function reply()
    {
        require_post();
        verify_csrf();

        $id      = input_int('id', 0);
        $content = input_str('content');

        $this->run(function () use ($id, $content) {
            $attachments = \App\Upload::files('attachments', 'tickets', 5);
            TicketService::reply($id, 'admin', Auth::id(), $content, '', $attachments);
            Auth::logAdmin('回复工单', '工单ID ' . $id . '：' . mb_substr($content, 0, 60)
                . ($attachments ? '（附件 ' . count($attachments) . ' 个）' : ''));
            return true;
        }, '回复已发送', admin_url('ticket/detail', ['id' => $id]));
    }

    /** 变更状态：0待处理 1处理中 2已解决 3已关闭 */
    public function status()
    {
        require_post();
        verify_csrf();

        $id     = input_int('id', 0);
        $status = input_int('status', 1);

        $this->run(function () use ($id, $status) {
            TicketService::setStatus($id, $status, Auth::id());
            Auth::logAdmin('工单状态变更', '工单ID ' . $id . ' → ' . TicketService::statusText($status));
            return true;
        }, '状态已更新', admin_url('ticket/detail', ['id' => $id]));
    }
}
