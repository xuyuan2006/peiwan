<?php
/**
 * 订单：下单、我的订单、订单详情、支付、取消、退款、确认
 */
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Db;
use App\OrderService;
use App\Setting;

class OrderController extends BaseController
{
    /** 下单 */
    public function create()
    {
        require_post();
        verify_csrf();
        Auth::requireLogin();

        $skillId  = input_int('skill_id');
        $quantity = input_int('quantity', 1);
        $remark   = input_str('remark');

        try {
            $order = OrderService::create(Auth::id(), $skillId, $quantity, $remark);
            $min   = Setting::int('order_auto_cancel_minutes', 30);
            flash('success', '下单成功，请在 ' . $min . ' 分钟内完成支付');
            redirect(url('order/detail', ['id' => (int) $order['id']]));
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
            redirect_back('home/index');
        }
    }

    /** 我的订单（我下的单） */
    /** 购买点单套餐：一口价，付款后进入接单大厅 */
    public function buyPackage()
    {
        require_post();
        verify_csrf();
        Auth::requireLogin();

        $packageId = input_int('package_id', 0);
        $remark    = input_str('remark');

        try {
            $result = OrderService::buyPackage(Auth::id(), $packageId, $remark);
            $order  = $result['order'];

            if ($result['paid']) {
                flash('success', '下单成功，订单已进入接单大厅，等陪玩接单');
                redirect(url('order/detail', ['id' => (int) $order['id']]));
            }

            flash('warning', '订单已创建，但余额不足（需 ' . money((float) $result['need']) . ' 元），请先充值再支付');
            redirect_route('wallet/recharge');
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
            redirect_route('home/packages');
        }
    }

    public function index()
    {
        Auth::requireLogin();

        $status = input_str('status', 'all');
        $filter = ['user_id' => Auth::id()];
        if ($status === 'refund') {
            $filter['status'] = [OrderService::STATUS_REFUNDING, OrderService::STATUS_REFUNDED];
        } elseif ($status === 'closed') {
            $filter['status'] = [OrderService::STATUS_CLOSED];
        } elseif ($status !== 'all' && ctype_digit($status)) {
            $filter['status'] = (int) $status;
        }

        $result = OrderService::paginate($filter, $this->page(), 10);

        $counts = Db::one(
            'SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS pending_pay,
                SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) AS serving,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS done
             FROM orders WHERE user_id = ?',
            [
                OrderService::STATUS_PENDING_PAY,
                OrderService::STATUS_PENDING_ACCEPT,
                OrderService::STATUS_IN_SERVICE,
                OrderService::STATUS_COMPLETED,
                Auth::id(),
            ]
        );

        $this->view('order/index', [
            'title'  => '我的订单',
            'result' => $result,
            'status' => $status,
            'counts' => $counts ?: [],
            'unread' => $this->unreadMap($result['rows'], Auth::id()),
        ]);
    }

    /**
     * 给订单列表补充「未读消息数」（单独一条聚合查询，避免改动原有 SQL 的占位符顺序）
     * @param array $rows
     * @return array<int,int>
     */
    private function unreadMap(array $rows, int $viewerId): array
    {
        $ids = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        if (!$ids) {
            return [];
        }

        try {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $list = Db::all(
                "SELECT order_id, COUNT(*) AS c FROM order_messages
                 WHERE is_read = 0 AND sender_id <> ? AND order_id IN ($placeholders)
                 GROUP BY order_id",
                array_merge([$viewerId], $ids)
            );

            $map = [];
            foreach ($list as $row) {
                $map[(int) $row['order_id']] = (int) $row['c'];
            }
            return $map;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** 订单详情 */
    public function detail()
    {
        Auth::requireLogin();

        $id    = input_int('id', 0);
        $order = $id > 0 ? Db::one(
            'SELECT o.*, u.nickname AS user_nickname, u.avatar AS user_avatar, u.phone AS user_phone,
                    c.nickname AS companion_nickname, c.avatar AS companion_avatar,
                    p.score AS companion_score, p.order_count AS companion_orders
             FROM orders o
             LEFT JOIN users u ON u.id = o.user_id
             LEFT JOIN users c ON c.id = o.companion_id
             LEFT JOIN companion_profiles p ON p.user_id = o.companion_id
             WHERE o.id = ? LIMIT 1',
            [$id]
        ) : null;

        if (!$order) {
            abort(404, '订单不存在');
        }

        $me     = Auth::id();
        $isUser = (int) $order['user_id'] === $me;
        $isComp = (int) $order['companion_id'] === $me;
        if (!$isUser && !$isComp && !is_admin()) {
            abort(403, '无权查看该订单');
        }

        $logs   = Db::all('SELECT * FROM order_logs WHERE order_id = ? ORDER BY id ASC', [$id]);
        $review = Db::one('SELECT * FROM order_reviews WHERE order_id = ? LIMIT 1', [$id]);

        $messages = OrderService::messages($id);
        OrderService::markRead($id, $me); // 打开即视为已读，清掉未读红点

        $this->view('order/detail', [
            'title'     => '订单详情',
            'order'     => $order,
            'logs'      => $logs,
            'review'    => $review,
            'is_user'   => $isUser,
            'is_comp'   => $isComp,
            'messages'  => $messages,
            'chat_open' => OrderService::chatOpen($order),
        ]);
    }

    /** 订单聊天：发送消息（AJAX 返回最新对话 HTML） */
    public function sendMessage()
    {
        require_post();
        verify_csrf();
        Auth::requireLogin();

        $id      = input_int('id', 0);
        $me      = Auth::id();
        $content = input_str('content');

        try {
            OrderService::sendMessage($id, $me, $content);
        } catch (\Throwable $e) {
            if (is_ajax()) {
                json_out(['code' => 1, 'msg' => $e->getMessage()], 400);
            }
            flash('error', $e->getMessage());
            redirect(url('order/detail', ['id' => $id]));
        }

        if (is_ajax()) {
            json_out(['code' => 0, 'msg' => '已发送', 'data' => $this->chatData($id, $me)]);
        }
        redirect(url('order/detail', ['id' => $id]));
    }

    /** 订单聊天：轮询新消息 */
    public function pollMessages()
    {
        Auth::requireLogin();

        $id = input_int('id', 0);
        $me = Auth::id();

        $order = $id > 0 ? Db::one('SELECT id, user_id, companion_id FROM orders WHERE id = ? LIMIT 1', [$id]) : null;
        if (!$order) {
            json_out(['code' => 1, 'msg' => '订单不存在'], 404);
        }
        if ((int) $order['user_id'] !== $me && (int) $order['companion_id'] !== $me && !is_admin()) {
            json_out(['code' => 1, 'msg' => '无权查看该订单'], 403);
        }

        json_out(['code' => 0, 'data' => $this->chatData($id, $me)]);
    }

    /** 聊天数据（发送与轮询共用） */
    private function chatData(int $orderId, int $viewerId): array
    {
        $messages = OrderService::messages($orderId);
        OrderService::markRead($orderId, $viewerId);

        return [
            'html'   => \App\View::capture('partials/order_chat_list', ['messages' => $messages, 'me' => $viewerId]),
            'count'  => count($messages),
            'unread' => OrderService::unreadTotal($viewerId),
        ];
    }

    /** 支付订单（余额支付） */
    public function pay()
    {
        require_post();
        verify_csrf();
        Auth::requireLogin();

        $id = input_int('id', 0);
        $this->run(function () use ($id) {
            OrderService::pay($id, Auth::id());
            return ['order_id' => $id];
        }, '支付成功，等待陪玩接单');
    }

    /** 取消订单 */
    public function cancel()
    {
        require_post();
        verify_csrf();
        Auth::requireLogin();

        $id     = input_int('id', 0);
        $reason = input_str('reason', '用户取消订单');

        $this->run(function () use ($id, $reason) {
            OrderService::cancel($id, Auth::id(), 'user', $reason);
            return ['order_id' => $id];
        }, '订单已取消');
    }

    /** 申请退款 */
    public function refund()
    {
        require_post();
        verify_csrf();
        Auth::requireLogin();

        $id     = input_int('id', 0);
        $reason = input_str('reason');

        $this->run(function () use ($id, $reason) {
            if ($reason === '') {
                throw new \RuntimeException('请填写退款原因');
            }
            OrderService::applyRefund($id, Auth::id(), $reason);
            return ['order_id' => $id];
        }, '退款申请已提交，平台会尽快处理');
    }

    /** 用户确认完成 */
    public function confirm()
    {
        require_post();
        verify_csrf();
        Auth::requireLogin();

        $id = input_int('id', 0);
        $this->run(function () use ($id) {
            OrderService::confirm($id, Auth::id(), 'user');
            return ['order_id' => $id];
        }, '已确认完成，感谢使用');
    }
}
