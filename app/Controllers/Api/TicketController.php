<?php
/**
 * 工单接口：售前咨询 / 售后问题 / 投诉建议。
 *
 * ⚠️ 安全要点：TicketService::find() / reply() / setStatus() 本身**不校验归属**，
 * 归属校验是由网页端控制器补的。所以这里的每个入口都必须自己校验，
 * 否则任何登录用户都能读改别人的工单。
 */
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Api\ApiException;
use App\Api\Presenter;
use App\Api\RateLimit;
use App\Api\Request;
use App\Api\TokenAuth;
use App\TicketService;

class TicketController extends BaseController
{
    public function categories(): void
    {
        $out = [];
        // TicketService::categories() 是 ['key' => [标题, 说明]]，值本身是数组，
        // 直接 (string) 转换会得到 "Array"，必须取下标 0。
        foreach (TicketService::categories() as $key => $meta) {
            $out[] = [
                'key'  => (string) $key,
                'name' => (string) ($meta[0] ?? $key),
                'hint' => (string) ($meta[1] ?? ''),
            ];
        }

        $this->ok([
            'list' => $out,
            'status_options' => [
                ['key' => 0, 'name' => '待处理'],
                ['key' => 1, 'name' => '处理中'],
                ['key' => 2, 'name' => '已解决'],
                ['key' => 3, 'name' => '已关闭'],
            ],
        ]);
    }

    /** 我的工单 */
    public function index(): void
    {
        $uid = (int) $this->user()['id'];

        $result = TicketService::userList($uid, $this->page(), $this->perPage(10, 50));

        $this->ok(array_merge(
            Presenter::page($result, [Presenter::class, 'ticket']),
            ['open_count' => TicketService::userOpenCount($uid)]
        ));
    }

    public function create(): void
    {
        Request::requirePost();
        $uid = (int) $this->user()['id'];

        RateLimit::hit('ticket:' . $uid, 5, 600, '提交工单过于频繁，请稍后再试');

        $ticket = TicketService::create(
            $uid,
            Request::str('category', 'presale'),
            Request::str('title'),
            Request::str('content'),
            Request::int('order_id', 0)
        );

        $this->ok([
            'ticket' => Presenter::ticket($ticket),
        ], '工单已提交（' . (string) $ticket['ticket_no'] . '），客服会尽快处理');
    }

    public function detail(): void
    {
        $uid = (int) $this->user()['id'];
        $id  = Request::int('id', 0);

        if ($id <= 0) {
            throw ApiException::invalid('缺少工单 ID');
        }

        $ticket = self::findOwned($id, $uid);

        $this->ok([
            'ticket' => Presenter::ticket($ticket),
            'thread' => array_map([Presenter::class, 'ticketReply'], TicketService::thread($id)),
        ]);
    }

    /** 用户追问 */
    public function reply(): void
    {
        Request::requirePost();
        $uid = (int) $this->user()['id'];
        $id  = Request::int('id', 0);

        if ($id <= 0) {
            throw ApiException::invalid('缺少工单 ID');
        }

        self::findOwned($id, $uid);

        RateLimit::hit('ticket:reply:' . $uid, 30, 600, '发送过于频繁，请稍后再试');

        TicketService::reply($id, 'user', $uid, Request::str('content'));

        $this->ok([
            'thread' => array_map([Presenter::class, 'ticketReply'], TicketService::thread($id)),
        ], '已发送');
    }

    /** 关闭自己的工单 */
    public function close(): void
    {
        Request::requirePost();
        $uid = (int) $this->user()['id'];
        $id  = Request::int('id', 0);

        if ($id <= 0) {
            throw ApiException::invalid('缺少工单 ID');
        }

        self::findOwned($id, $uid);

        TicketService::setStatus($id, 3);

        $this->ok(['ticket' => Presenter::ticket(TicketService::find($id))], '工单已关闭');
    }

    // ---------------------------------------------------------------- 内部

    /** 取工单并校验归属（管理员可越权查看） */
    private static function findOwned(int $id, int $uid): array
    {
        $ticket = TicketService::find($id);
        if (!$ticket) {
            throw ApiException::notFound('工单不存在');
        }
        if ((int) $ticket['user_id'] !== $uid && !TokenAuth::isAdmin()) {
            throw ApiException::forbidden('无权查看该工单');
        }
        return $ticket;
    }
}
