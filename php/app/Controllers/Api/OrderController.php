<?php
/**
 * 订单接口：下单 / 套餐下单 / 支付 / 取消 / 退款 / 确认 / 列表 / 详情 / 聊天。
 *
 * 状态机（OrderService 常量）：
 *   1待支付 2待接单 3服务中 4待确认 5已完成 6退款中 7已关闭 8已退款
 *
 * 两个容易踩的坑（已按服务层真实语义处理）：
 *   - 取消「待接单(2)」的订单，结果是 8已退款，不是 7已关闭。
 *   - 支付一张已过 expire_at 的待支付订单，服务层会先把订单落库成 7 再抛错，
 *     所以 App 收到「订单已超时关闭」后必须刷新订单，不能只看本地状态。
 */
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Api\ApiException;
use App\Api\OrderQuery;
use App\Api\Presenter;
use App\Api\RateLimit;
use App\Api\Request;
use App\Api\TokenAuth;
use App\Db;
use App\OrderService;
use App\Wallet;

class OrderController extends BaseController
{
    // ---------------------------------------------------------------- 下单

    public function create(): void
    {
        Request::requirePost();
        $uid = (int) $this->user()['id'];

        $skillId  = Request::int('skill_id', 0);
        $quantity = Request::int('quantity', 1);
        $remark   = Request::str('remark');

        if ($skillId <= 0) {
            throw ApiException::invalid('请选择要购买的服务项目');
        }

        RateLimit::hit('order:create:' . $uid, 20, 60, '下单过于频繁，请稍后再试');

        $order = OrderService::create($uid, $skillId, $quantity, $remark);

        $this->ok([
            'order'   => Presenter::order($order, $uid),
            'balance' => Wallet::balance($uid),
        ], '下单成功，请尽快完成支付');
    }

    /** 点单套餐一口价：余额够会自动付款并进接单大厅 */
    public function buyPackage(): void
    {
        Request::requirePost();
        $uid = (int) $this->user()['id'];

        $packageId = Request::int('package_id', 0);
        if ($packageId <= 0) {
            throw ApiException::invalid('请选择点单套餐');
        }

        RateLimit::hit('order:create:' . $uid, 20, 60, '下单过于频繁，请稍后再试');

        $res  = OrderService::buyPackage($uid, $packageId, Request::str('remark'));
        $paid = (bool) $res['paid'];
        $need = (float) $res['need'];

        $this->ok([
            'order'      => Presenter::order($res['order'], $uid),
            'paid'       => $paid,
            'need'       => $need,
            'need_text'  => '¥' . number_format($need, 2),
            'balance'    => Wallet::balance($uid),
            'need_recharge' => !$paid,
        ], $paid ? '下单并支付成功，正在为你匹配陪玩' : '下单成功，余额不足请先充值');
    }

    // ---------------------------------------------------------------- 列表 / 详情

    /** 我的订单（下单方视角） */
    public function index(): void
    {
        $uid = (int) $this->user()['id'];

        $result = OrderService::paginate([
            'user_id' => $uid,
            'status'  => OrderQuery::statusFilter(Request::str('status', 'all')),
        ], $this->page(), $this->perPage(10, 50));

        $this->ok(array_merge(
            Presenter::page($result, function ($row) use ($uid) {
                return Presenter::order($row, $uid);
            }),
            ['counts' => OrderQuery::counts('user_id', $uid)]
        ));
    }

    public function detail(): void
    {
        $uid = (int) $this->user()['id'];
        $id  = Request::int('id', 0);

        if ($id <= 0) {
            throw ApiException::invalid('缺少订单 ID');
        }

        $order = OrderService::find($id);
        if (!self::canView($order, $uid)) {
            throw ApiException::forbidden('无权查看该订单');
        }

        $this->ok([
            'order'    => Presenter::order($order, $uid),
            'logs'     => array_map([Presenter::class, 'orderLog'], Db::all(
                'SELECT * FROM order_logs WHERE order_id = ? ORDER BY id ASC',
                [$id]
            )),
            'unread'   => OrderService::unreadOf($id, $uid),
            'chat_open'=> OrderService::chatOpen($order),
        ]);
    }

    /** 订单状态流转时间线 */
    public function logs(): void
    {
        $uid = (int) $this->user()['id'];
        $id  = Request::int('id', 0);

        if ($id <= 0) {
            throw ApiException::invalid('缺少订单 ID');
        }
        if (!self::canView(OrderService::find($id), $uid)) {
            throw ApiException::forbidden('无权查看该订单');
        }

        $this->ok(array_map([Presenter::class, 'orderLog'], Db::all(
            'SELECT * FROM order_logs WHERE order_id = ? ORDER BY id ASC',
            [$id]
        )));
    }

    // ---------------------------------------------------------------- 状态流转

    public function pay(): void
    {
        Request::requirePost();
        $uid = (int) $this->user()['id'];
        $id  = Request::int('id', 0);

        if ($id <= 0) {
            throw ApiException::invalid('缺少订单 ID');
        }

        // 服务层内部已校验「本人 + 状态为待支付 + 未超时」，异常会带中文原因返回
        $order = OrderService::pay($id, $uid);

        $this->ok([
            'order'   => Presenter::order($order, $uid),
            'balance' => Wallet::balance($uid),
        ], '支付成功，等待陪玩接单');
    }

    public function cancel(): void
    {
        Request::requirePost();
        $uid = (int) $this->user()['id'];
        $id  = Request::int('id', 0);

        if ($id <= 0) {
            throw ApiException::invalid('缺少订单 ID');
        }

        $order = OrderService::cancel($id, $uid, 'user', Request::str('reason', '用户取消订单'));

        $this->ok([
            'order'   => Presenter::order($order, $uid),
            'balance' => Wallet::balance($uid),
        ], (int) $order['status'] === OrderService::STATUS_REFUNDED ? '订单已取消，款项已退回余额' : '订单已关闭');
    }

    public function refund(): void
    {
        Request::requirePost();
        $uid = (int) $this->user()['id'];
        $id  = Request::int('id', 0);

        if ($id <= 0) {
            throw ApiException::invalid('缺少订单 ID');
        }

        $reason = Request::str('reason');
        if ($reason === '') {
            throw ApiException::invalid('请填写退款原因');
        }

        $order = OrderService::applyRefund($id, $uid, $reason);

        $refunded = (int) $order['status'] === OrderService::STATUS_REFUNDED;

        $this->ok([
            'order'   => Presenter::order($order, $uid),
            'balance' => Wallet::balance($uid),
        ], $refunded ? '已全额退款到余额' : '退款申请已提交，等待平台处理');
    }

    public function confirm(): void
    {
        Request::requirePost();
        $uid = (int) $this->user()['id'];
        $id  = Request::int('id', 0);

        if ($id <= 0) {
            throw ApiException::invalid('缺少订单 ID');
        }

        $order = OrderService::confirm($id, $uid, 'user');

        $this->ok([
            'order' => Presenter::order($order, $uid),
        ], '已确认完成，款项已结算给陪玩');
    }

    // ---------------------------------------------------------------- 订单内聊天

    /** 拉取消息；传 since_id 可只取增量（用于轮询） */
    public function messages(): void
    {
        $uid = (int) $this->user()['id'];
        $id  = Request::int('id', 0);

        if ($id <= 0) {
            throw ApiException::invalid('缺少订单 ID');
        }

        $order = OrderService::find($id);
        if (!self::canView($order, $uid)) {
            throw ApiException::forbidden('无权查看该订单');
        }

        $sinceId = Request::int('since_id', 0);

        if ($sinceId > 0) {
            $rows = Db::all(
                'SELECT m.*, u.nickname, u.avatar
                 FROM order_messages m LEFT JOIN users u ON u.id = m.sender_id
                 WHERE m.order_id = ? AND m.id > ?
                 ORDER BY m.id ASC LIMIT 200',
                [$id, $sinceId]
            );
        } else {
            $rows = OrderService::messages($id, 300);
        }

        // 拉取即已读：把对方发的消息标记为已读。
        // 管理员只是「查看」，不该把买卖双方的未读红点清掉，所以跳过。
        if (!$this->isAdminViewer($order, $uid)) {
            OrderService::markRead($id, $uid);
        }

        $this->ok([
            'list'     => array_map(function ($row) use ($uid) {
                return Presenter::message($row, $uid);
            }, $rows),
            'chat_open'=> OrderService::chatOpen($order),
            'unread'   => OrderService::unreadOf($id, $uid),
        ]);
    }

    public function sendMessage(): void
    {
        Request::requirePost();
        $uid = (int) $this->user()['id'];
        $id  = Request::int('id', 0);

        if ($id <= 0) {
            throw ApiException::invalid('缺少订单 ID');
        }

        $content = Request::str('content');
        if ($content === '') {
            throw ApiException::invalid('请输入消息内容');
        }

        // 限流：防刷屏
        RateLimit::hit('order:chat:' . $uid, 30, 60, '发送过于频繁，请稍后再试');

        // 服务层会校验：订单存在、是买卖双方或管理员、且处于可聊天状态
        $sent = OrderService::sendMessage($id, $uid, $content);

        $this->ok([
            'message_id' => (int) $sent['id'],
            'role'       => (string) $sent['role'],
        ], '已发送');
    }

    // ---------------------------------------------------------------- 内部辅助

    /** 管理员旁观（既不是买家也不是陪玩）时不该影响订单的未读状态 */
    private function isAdminViewer(array $order, int $uid): bool
    {
        if (!TokenAuth::isAdmin()) {
            return false;
        }
        return $uid !== (int) $order['user_id'] && $uid !== (int) $order['companion_id'];
    }

    /** 订单详情/聊天可见性：买卖双方或管理员 */
    private static function canView(array $order, int $uid): bool
    {
        if ($uid <= 0) {
            return false;
        }
        if ($uid === (int) $order['user_id']) {
            return true;
        }
        $compId = (int) $order['companion_id'];
        if ($compId > 0 && $uid === $compId) {
            return true;
        }
        return TokenAuth::isAdmin();
    }
}
