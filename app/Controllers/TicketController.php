<?php
/**
 * 前台工单：提交（卡片/页面）、我的工单、对话追问、关闭
 */
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Db;
use App\TicketService;

class TicketController extends BaseController
{
    /** 提交工单（支持 AJAX，弹卡片直接提交） */
    public function create()
    {
        require_post();
        verify_csrf();
        Auth::requireLogin();

        $category = input_str('category', 'presale');
        $title    = input_str('title');
        $content  = input_str('content');
        $orderId  = input_int('order_id', 0);

        try {
            $attachments = \App\Upload::files('attachments', 'tickets', 5);
            $ticket = TicketService::create(Auth::id(), $category, $title, $content, $orderId, $attachments);
            $msg    = '工单已提交（' . $ticket['ticket_no'] . '），客服会尽快处理';
            if (is_ajax()) {
                json_out(['code' => 0, 'msg' => $msg, 'data' => ['id' => (int) $ticket['id'], 'url' => url('ticket/detail', ['id' => (int) $ticket['id']])]]);
            }
            flash('success', $msg);
            redirect(url('ticket/detail', ['id' => (int) $ticket['id']]));
        } catch (\Throwable $e) {
            if (is_ajax()) {
                json_out(['code' => 1, 'msg' => $e->getMessage()], 400);
            }
            flash('error', $e->getMessage());
            keep_old($_POST);
            redirect_route('ticket/index');
        }
    }

    /** 我的工单 */
    public function index()
    {
        Auth::requireLogin();

        $result = TicketService::userList(Auth::id(), $this->page(), 10);

        $this->view('ticket/index', [
            'title'      => '我的工单',
            'result'     => $result,
            'categories' => TicketService::categories(),
        ]);
    }

    /** 工单详情（对话） */
    public function detail()
    {
        Auth::requireLogin();

        $id     = input_int('id', 0);
        $ticket = $id > 0 ? TicketService::find($id) : null;
        if (!$ticket) {
            abort(404, '工单不存在');
        }
        if ((int) $ticket['user_id'] !== Auth::id() && !is_admin()) {
            abort(403, '无权查看该工单');
        }

        $this->view('ticket/detail', [
            'title'  => '工单 ' . $ticket['ticket_no'],
            'ticket' => $ticket,
            'thread' => TicketService::thread($id),
        ]);
    }

    /** 用户追问 */
    public function reply()
    {
        require_post();
        verify_csrf();
        Auth::requireLogin();

        $id      = input_int('id', 0);
        $content = input_str('content');

        $this->run(function () use ($id, $content) {
            $ticket = TicketService::find($id);
            if (!$ticket) {
                throw new \RuntimeException('工单不存在');
            }
            if ((int) $ticket['user_id'] !== Auth::id()) {
                throw new \RuntimeException('无权回复该工单');
            }
            $attachments = \App\Upload::files('attachments', 'tickets', 5);
            TicketService::reply($id, 'user', Auth::id(), $content, '', $attachments);
            return true;
        }, '已发送', url('ticket/detail', ['id' => $id]));
    }

    /** 用户关闭自己的工单 */
    public function close()
    {
        require_post();
        verify_csrf();
        Auth::requireLogin();

        $id = input_int('id', 0);
        $this->run(function () use ($id) {
            $ticket = TicketService::find($id);
            if (!$ticket) {
                throw new \RuntimeException('工单不存在');
            }
            if ((int) $ticket['user_id'] !== Auth::id()) {
                throw new \RuntimeException('无权操作该工单');
            }
            TicketService::setStatus($id, 3);
            return true;
        }, '工单已关闭', url('ticket/detail', ['id' => $id]));
    }
}
