<?php
/**
 * 钱包接口：余额概览、资金流水、充值（人工凭证 / 在线支付）、提现。
 *
 * 在线支付的账号与回调仍然复用网页端既有入口（pay/notify、pay/alipayNotify），
 * 这里只负责把「支付链接 / 二维码内容」告诉 App，钱到账与否以服务端回调为准。
 */
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Alipay;
use App\Api\ApiException;
use App\Api\Presenter;
use App\Api\RateLimit;
use App\Api\Request;
use App\Db;
use App\Epay;
use App\FinanceService;
use App\Setting;
use App\Wallet;

class WalletController extends BaseController
{
    /** 钱包首页：余额 + 待办数量 + 提现规则 */
    public function index(): void
    {
        $uid = (int) $this->user()['id'];

        $counts = Db::one(
            'SELECT
                (SELECT COUNT(*) FROM recharge_orders WHERE user_id = ? AND status = 0) AS recharge_pending,
                (SELECT COUNT(*) FROM withdraw_orders WHERE user_id = ? AND status = 0) AS withdraw_pending,
                (SELECT COUNT(*) FROM orders WHERE user_id = ? AND status = 1) AS order_pending_pay',
            [$uid, $uid, $uid]
        ) ?: [];

        $this->ok([
            'wallet' => Presenter::wallet(Wallet::get($uid)),
            'counts' => [
                'recharge_pending'  => (int) ($counts['recharge_pending'] ?? 0),
                'withdraw_pending'  => (int) ($counts['withdraw_pending'] ?? 0),
                'order_pending_pay' => (int) ($counts['order_pending_pay'] ?? 0),
            ],
            'withdraw_config' => [
                'min'      => Setting::float('min_withdraw', 50),
                'fee_rate' => Setting::float('withdraw_fee_rate', 1),
            ],
        ]);
    }

    /** 资金流水 */
    public function transactions(): void
    {
        $uid = (int) $this->user()['id'];

        $where  = 'user_id = ?';
        $params = [$uid];

        $type = Request::str('type');
        if ($type !== '') {
            $where   .= ' AND type = ?';
            $params[] = $type;
        }
        $direction = Request::int('direction', 0);
        if ($direction === 1 || $direction === 2) {
            $where   .= ' AND direction = ?';
            $params[] = $direction;
        }

        $perPage = $this->perPage(20, 50);
        $page    = $this->page();
        $offset  = ($page - 1) * $perPage;

        $total = (int) Db::value("SELECT COUNT(*) FROM wallet_transactions WHERE $where", $params);
        $rows  = Db::all(
            "SELECT * FROM wallet_transactions WHERE $where ORDER BY id DESC LIMIT $perPage OFFSET $offset",
            $params
        );

        $this->ok(array_merge(
            Presenter::page(['rows' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage], [Presenter::class, 'transaction']),
            [
                'wallet'     => Presenter::wallet(Wallet::get($uid)),
                'type_options' => self::typeOptions(),
            ]
        ));
    }

    // ---------------------------------------------------------------- 充值

    /**
     * 发起充值。
     *
     * App 端请先用 `upload/image?type=voucher` 上传转账凭证拿到 path，
     * 再把 path 填到 voucher 字段传进来（避免在一个接口里同时处理 multipart 与 JSON）。
     */
    public function recharge(): void
    {
        Request::requirePost();
        $uid = (int) $this->user()['id'];

        $amount = safe_float(Request::float('amount', 0));
        FinanceService::assertRechargeAmount($amount);

        RateLimit::hit('recharge:' . $uid, 10, 600, '提交过于频繁，请稍后再试');

        $channel = (string) Alipay::channel();
        $voucher = $channel === 'manual' ? Request::uploadPath('voucher', ['voucher']) : '';

        // epay/alipay 渠道下凭证字段没有意义，忽略即可
        $rechargeId = FinanceService::createRecharge($uid, $amount, $channel, $voucher, Request::str('remark'));

        $order = Db::one('SELECT * FROM recharge_orders WHERE id = ? LIMIT 1', [$rechargeId]) ?: [];
        $pay   = self::buildPayment($order, Request::str('pay_type'));

        // buildPayment 已经把 pay_url 写进库了，这里同步到本地这一行，
        // 省掉一次重复查询，返回给 App 的字段也才是最新的。
        $order['pay_url'] = $pay['qr_code'] !== '' ? $pay['qr_code'] : $pay['pay_url'];

        $this->ok([
            'recharge' => Presenter::recharge($order),
            'pay_mode' => $pay['mode'],
            'pay_url'  => $pay['pay_url'],
            'qr_code'  => $pay['qr_code'],
        ], $pay['mode'] === 'manual' ? '充值申请已提交，客服核对到账后会为你入账' : '请完成支付，到账后余额自动更新');
    }

    /** 重新获取某张待支付充值单的支付链接 / 二维码（用户中途退出后可继续支付） */
    public function rechargePay(): void
    {
        $uid = (int) $this->user()['id'];
        $id  = Request::int('id', 0);

        if ($id <= 0) {
            throw ApiException::invalid('缺少充值单 ID');
        }

        $order = Db::one('SELECT * FROM recharge_orders WHERE id = ? AND user_id = ? LIMIT 1', [$id, $uid]);
        if (!$order) {
            throw ApiException::notFound('充值单不存在');
        }
        if ((int) $order['status'] === 1) {
            throw ApiException::invalid('该充值单已到账');
        }
        if ((int) $order['status'] !== 0) {
            throw ApiException::invalid('该充值单已关闭，无法继续支付');
        }

        $pay = self::buildPayment($order, Request::str('pay_type'));

        // 与 recharge() 保持一致：buildPayment 可能刚刷新了库里的 pay_url，
        // 这里把结果同步回本地行，否则响应里顶层 payUrl 是新的、recharge.payUrl 是旧的
        // （epay 每次签名都不同，这种不一致会很明显）。
        $order['pay_url'] = $pay['qr_code'] !== '' ? $pay['qr_code'] : $pay['pay_url'];

        $this->ok([
            'recharge' => Presenter::recharge($order),
            'pay_mode' => $pay['mode'],
            'pay_url'  => $pay['pay_url'],
            'qr_code'  => $pay['qr_code'],
        ]);
    }

    /** 取消自己未支付的充值单 */
    public function rechargeCancel(): void
    {
        Request::requirePost();
        $uid = TokenAuth::requireLogin();

        try {
            FinanceService::cancelRecharge(Request::int('id'), $uid);
        } catch (\RuntimeException $e) {
            // 归属不符、已处理等情况都归为「参数/状态不合法」
            throw ApiException::invalid($e->getMessage());
        }

        $this->ok(null, '充值单已取消');
    }

    public function rechargeDetail(): void
    {
        $uid = (int) $this->user()['id'];
        $id  = Request::int('id', 0);

        if ($id <= 0) {
            throw ApiException::invalid('缺少充值单 ID');
        }

        $order = Db::one('SELECT * FROM recharge_orders WHERE id = ? AND user_id = ? LIMIT 1', [$id, $uid]);
        if (!$order) {
            throw ApiException::notFound('充值单不存在');
        }

        $this->ok(['recharge' => Presenter::recharge($order)]);
    }

    /** 充值记录 */
    public function rechargeList(): void
    {
        $uid     = (int) $this->user()['id'];
        $perPage = $this->perPage(20, 50);
        $page    = $this->page();
        $offset  = ($page - 1) * $perPage;

        $total = (int) Db::value('SELECT COUNT(*) FROM recharge_orders WHERE user_id = ?', [$uid]);
        $rows  = Db::all(
            "SELECT * FROM recharge_orders WHERE user_id = ? ORDER BY id DESC LIMIT $perPage OFFSET $offset",
            [$uid]
        );

        $this->ok(Presenter::page(
            ['rows' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage],
            [Presenter::class, 'recharge']
        ));
    }

    // ---------------------------------------------------------------- 提现

    public function withdraw(): void
    {
        Request::requirePost();
        $uid = (int) $this->user()['id'];

        $amount      = safe_float(Request::float('amount', 0));
        $accountType = Request::str('account_type', 'alipay');
        $accountName = Request::str('account_name');
        $accountNo   = Request::str('account_no');

        if ($accountName === '') {
            throw ApiException::invalid('请填写收款人姓名');
        }
        if ($accountNo === '') {
            throw ApiException::invalid('请填写收款账号');
        }
        if (!in_array($accountType, ['alipay', 'wechat', 'bank'], true)) {
            $accountType = 'alipay';
        }

        // 提现是资金出口，限流严一点
        RateLimit::hit('withdraw:' . $uid, 5, 600, '提交过于频繁，请稍后再试');

        $res = FinanceService::applyWithdraw($uid, $amount, $accountType, $accountName, $accountNo);

        $this->ok([
            'withdraw'   => Presenter::withdraw(
                Db::one('SELECT * FROM withdraw_orders WHERE id = ? LIMIT 1', [(int) ($res['withdraw_id'] ?? 0)])
            ),
            'wallet'     => Presenter::wallet(Wallet::get($uid)),
        ], '提现申请已提交，平台审核通过后打款');
    }

    /** 提现记录 */
    public function withdrawList(): void
    {
        $uid     = (int) $this->user()['id'];
        $perPage = $this->perPage(20, 50);
        $page    = $this->page();
        $offset  = ($page - 1) * $perPage;

        $total = (int) Db::value('SELECT COUNT(*) FROM withdraw_orders WHERE user_id = ?', [$uid]);
        $rows  = Db::all(
            "SELECT * FROM withdraw_orders WHERE user_id = ? ORDER BY id DESC LIMIT $perPage OFFSET $offset",
            [$uid]
        );

        $this->ok(array_merge(
            Presenter::page(['rows' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage], [Presenter::class, 'withdraw']),
            [
                'wallet' => Presenter::wallet(Wallet::get($uid)),
                'withdraw_config' => [
                    'min'      => Setting::float('min_withdraw', 50),
                    'fee_rate' => Setting::float('withdraw_fee_rate', 1),
                ],
            ]
        ));
    }

    // ---------------------------------------------------------------- 内部

    /**
     * 生成支付指引。与网页端 wallet/recharge 的分支逻辑保持一致。
     * @return array{mode:string,pay_url:string,qr_code:string,channel:string}
     */
    /**
     * 生成/复用支付凭据。
     *
     * @param string $payType 仅易支付用到：wxpay（微信支付）/ alipay（支付宝）。
     *                        其余渠道忽略此参数。
     */
    private static function buildPayment(array $order, string $payType = ''): array
    {
        $channel = (string) ($order['channel'] ?? 'manual');
        $orderNo = (string) ($order['order_no'] ?? '');
        $amount  = (float) ($order['amount'] ?? 0);
        $id      = (int) ($order['id'] ?? 0);
        $stored  = trim((string) ($order['pay_url'] ?? ''));

        if ($channel === 'epay') {
            // 已经生成过就直接复用。易支付的 URL 里带着用户当时选的支付方式（微信/支付宝），
            // 而且签名每次都不一样 —— 重新生成会把「用户选的是微信」这个信息丢掉。
            if ($stored !== '' && strpos($stored, 'http') === 0) {
                return ['mode' => 'redirect', 'channel' => 'epay', 'pay_url' => $stored, 'qr_code' => ''];
            }

            $url = Epay::payUrl([
                'out_trade_no' => $orderNo,
                'name'         => '账户充值',
                'money'        => money($amount),
                'type'         => $payType === 'wxpay' ? 'wxpay' : 'alipay',
                'notify_url'   => base_url() . '/index.php?r=pay/notify',
                'return_url'   => base_url() . '/index.php?r=pay/back&out_trade_no=' . $orderNo,
            ]);
            Db::update('recharge_orders', ['pay_url' => mb_substr($url, 0, 490)], 'id = ?', [$id]);
            return ['mode' => 'redirect', 'channel' => 'epay', 'pay_url' => $url, 'qr_code' => ''];
        }

        if ($channel === 'alipay') {
            $notify  = base_url() . '/index.php?r=pay/alipayNotify';
            $subject = site_name() . ' 账户充值';

            if (Alipay::mode() === 'precreate') {
                // 当面付：已经预下单过就直接复用二维码，避免重复下单被支付宝拒绝
                if ($stored !== '') {
                    return ['mode' => 'qrcode', 'channel' => 'alipay', 'pay_url' => '', 'qr_code' => $stored];
                }
                $res = Alipay::precreate($orderNo, $amount, $subject, $notify);
                $qr  = (string) ($res['qr_code'] ?? '');
                Db::update('recharge_orders', ['pay_url' => mb_substr($qr, 0, 490)], 'id = ?', [$id]);
                return ['mode' => 'qrcode', 'channel' => 'alipay', 'pay_url' => '', 'qr_code' => $qr];
            }

            $return = base_url() . '/index.php?r=pay/alipayReturn&out_trade_no=' . $orderNo;
            $url    = Alipay::payUrl($orderNo, $amount, $subject, $return, $notify, Alipay::mode() === 'wap');
            Db::update('recharge_orders', ['pay_url' => mb_substr($url, 0, 490)], 'id = ?', [$id]);
            return ['mode' => 'redirect', 'channel' => 'alipay', 'pay_url' => $url, 'qr_code' => ''];
        }

        return ['mode' => 'manual', 'channel' => 'manual', 'pay_url' => '', 'qr_code' => ''];
    }

    /** 流水类型筛选项（供 App 做下拉筛选） */
    private static function typeOptions(): array
    {
        $out = [];
        foreach (wallet_type_map() as $key => $text) {
            $out[] = ['key' => $key, 'name' => $text];
        }
        return $out;
    }
}
