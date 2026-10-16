<?php
/**
 * 钱包：余额、流水、充值、提现
 */
declare(strict_types=1);

namespace App\Controllers;

use App\Alipay;
use App\Auth;
use App\Db;
use App\Epay;
use App\FinanceService;
use App\Setting;
use App\Upload;
use App\Wallet;

class WalletController extends BaseController
{
    public function index()
    {
        Auth::requireLogin();

        $userId = Auth::id();
        $wallet = Wallet::get($userId);
        $txns   = Db::all('SELECT * FROM wallet_transactions WHERE user_id = ? ORDER BY id DESC LIMIT 10', [$userId]);
        $recharges = Db::all('SELECT * FROM recharge_orders WHERE user_id = ? ORDER BY id DESC LIMIT 5', [$userId]);
        $withdraws = Db::all('SELECT * FROM withdraw_orders WHERE user_id = ? ORDER BY id DESC LIMIT 5', [$userId]);

        $this->view('wallet/index', [
            'title'     => '我的钱包',
            'wallet'    => $wallet,
            'txns'      => $txns,
            'recharges' => $recharges,
            'withdraws' => $withdraws,
            'epay_on'   => Epay::enabled(),
        ]);
    }

    /** 资金流水（分页） */
    public function transactions()
    {
        Auth::requireLogin();

        $userId  = Auth::id();
        $perPage = 20;
        $page    = $this->page();
        $total   = (int) Db::value('SELECT COUNT(*) FROM wallet_transactions WHERE user_id = ?', [$userId]);
        $offset  = ($page - 1) * $perPage;
        $rows    = Db::all(
            "SELECT * FROM wallet_transactions WHERE user_id = ? ORDER BY id DESC LIMIT $perPage OFFSET $offset",
            [$userId]
        );

        $this->view('wallet/transactions', [
            'title' => '资金流水',
            'rows'  => $rows,
            'total' => $total,
            'page'  => $page,
            'per_page' => $perPage,
        ]);
    }

    /** 充值 */
    public function recharge()
    {
        Auth::requireLogin();

        if (is_post()) {
            verify_csrf();
            $amount = safe_float(input_float('amount', 0));

            try {
                // 先校验金额，再处理上传，避免金额非法时留下孤儿文件
                FinanceService::assertRechargeAmount($amount);

                $channel = Alipay::channel(); // manual | epay | alipay
                $voucher = $channel === 'manual' ? Upload::image('voucher', 'voucher') : null;

                $rechargeId = FinanceService::createRecharge(
                    Auth::id(),
                    $amount,
                    $channel,
                    (string) $voucher,
                    input_str('remark')
                );

                $row     = Db::one('SELECT order_no FROM recharge_orders WHERE id = ? LIMIT 1', [$rechargeId]);
                $orderNo = (string) ($row['order_no'] ?? '');

                if ($channel === 'epay') {
                    $payUrl = Epay::payUrl([
                        'out_trade_no' => $orderNo,
                        'name'         => '账户充值',
                        'money'        => money($amount),
                        'notify_url'   => base_url() . '/index.php?r=pay/notify',
                        'return_url'   => base_url() . '/index.php?r=pay/back&out_trade_no=' . $orderNo,
                    ]);
                    Db::update('recharge_orders', ['pay_url' => mb_substr($payUrl, 0, 490)], 'id = ?', [$rechargeId]);
                    redirect($payUrl);
                }

                if ($channel === 'alipay') {
                    $notify  = base_url() . '/index.php?r=pay/alipayNotify';
                    $return  = base_url() . '/index.php?r=pay/alipayReturn&out_trade_no=' . $orderNo;
                    $subject = site_name() . ' 账户充值';

                    if (Alipay::mode() === 'precreate') {
                        // 当面付：预下单拿 qr_code，跳到展示页（可点可复制，配了二维码库会自动渲染）
                        $res = Alipay::precreate($orderNo, $amount, $subject, $notify);
                        Db::update('recharge_orders', ['pay_url' => mb_substr((string) $res['qr_code'], 0, 490)], 'id = ?', [$rechargeId]);
                        redirect(url('wallet/alipayQr', ['id' => $rechargeId]));
                    }

                    $payUrl = Alipay::payUrl($orderNo, $amount, $subject, $return, $notify, Alipay::mode() === 'wap');
                    Db::update('recharge_orders', ['pay_url' => mb_substr($payUrl, 0, 490)], 'id = ?', [$rechargeId]);
                    redirect($payUrl);
                }

                flash('success', '充值申请已提交，客服核对到账后会为你入账');
                redirect_route('wallet/index');
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
                redirect_route('wallet/recharge');
            }
        }

        $this->view('wallet/recharge', [
            'title'         => '账户充值',
            'channel'       => Alipay::channel(),
            'alipay_mode'   => Alipay::mode(),
            'service_phone' => (string) Setting::get('service_phone', ''),
        ]);
    }

    /** 支付宝当面付：展示 qr_code 支付链接 */
    public function alipayQr()
    {
        Auth::requireLogin();

        $id    = input_int('id', 0);
        $order = Db::one('SELECT * FROM recharge_orders WHERE id = ? AND user_id = ? LIMIT 1', [$id, Auth::id()]);
        if (!$order) {
            abort(404, '充值单不存在');
        }
        if ((int) $order['status'] === 1) {
            flash('success', '该充值单已到账');
            redirect_route('wallet/index');
        }

        $this->view('wallet/alipay_qr', [
            'title' => '扫码支付',
            'order' => $order,
        ]);
    }

    /** 提现 */
    public function withdraw()
    {
        Auth::requireLogin();

        $userId = Auth::id();
        $wallet = Wallet::get($userId);
        $min    = Setting::float('min_withdraw', 50.0);
        $feeRate = Setting::float('withdraw_fee_rate', 1.0);

        if (is_post()) {
            verify_csrf();
            $amount      = safe_float(input_float('amount', 0));
            $accountType = input_str('account_type', 'alipay');
            $accountName = input_str('account_name');
            $accountNo   = input_str('account_no');

            try {
                FinanceService::applyWithdraw(Auth::id(), $amount, $accountType, $accountName, $accountNo);

                flash('success', '提现申请已提交，平台审核通过后打款');
                redirect_route('wallet/index');
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
                redirect_route('wallet/withdraw');
            }
        }

        $this->view('wallet/withdraw', [
            'title'    => '申请提现',
            'wallet'   => $wallet,
            'min'      => $min,
            'fee_rate' => $feeRate,
        ]);
    }

    /** 充值单详情 */
    public function rechargeDetail()
    {
        Auth::requireLogin();

        $id    = input_int('id', 0);
        $order = Db::one('SELECT * FROM recharge_orders WHERE id = ? AND user_id = ? LIMIT 1', [$id, Auth::id()]);
        if (!$order) {
            abort(404, '充值单不存在');
        }

        $this->view('wallet/recharge_detail', ['title' => '充值单详情', 'order' => $order]);
    }
}
