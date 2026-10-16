<?php
/**
 * 支付回调（当前用于易支付异步通知）
 */
declare(strict_types=1);

namespace App\Controllers;

use App\Alipay;
use App\Auth;
use App\Db;
use App\Epay;
use App\FinanceService;

class PayController extends BaseController
{
    /** 异步通知：/index.php?r=pay/notify */
    public function notify()
    {
        $params = array_merge($_GET, $_POST);
        unset($params['r']);

        if (!Epay::enabled()) {
            echo 'channel disabled';
            return;
        }
        if (!Epay::verify($params)) {
            echo 'sign error';
            return;
        }

        $orderNo = (string) ($params['out_trade_no'] ?? '');
        $tradeNo = mb_substr((string) ($params['trade_no'] ?? ''), 0, 64);
        if ($orderNo === '') {
            echo 'order error';
            return;
        }
        // 只处理支付成功的通知（易支付可能推送 WAIT_BUYER_PAY 等中间状态）
        if (!empty($params['trade_status']) && $params['trade_status'] !== 'TRADE_SUCCESS') {
            echo 'wait';
            return;
        }

        try {
            $done = Db::tx(function () use ($orderNo, $tradeNo) {
                $order = Db::one('SELECT * FROM recharge_orders WHERE order_no = ? LIMIT 1', [$orderNo]);
                if (!$order) {
                    throw new \RuntimeException('充值单不存在');
                }
                if ((int) $order['status'] === 1) {
                    return true; // 已到账，幂等返回
                }
                if ((int) $order['status'] !== 0) {
                    // 已被后台关闭/驳回的单据不再入账
                    return false;
                }

                // 入账规则与后台审核收敛到同一处，避免两套逻辑分叉
                FinanceService::auditRecharge((int) $order['id'], 0, 1, '在线支付自动入账');
                if ($tradeNo !== '') {
                    Db::update('recharge_orders', [
                        'trade_no'   => $tradeNo,
                        'updated_at' => now(),
                    ], 'id = ?', [(int) $order['id']]);
                }
                return true;
            });
            echo $done ? 'success' : 'fail';
        } catch (\Throwable $e) {
            echo 'fail';
        }
    }

    /** 同步跳转回站内：/index.php?r=pay/back */
    public function back()
    {
        $orderNo = input_str('out_trade_no');
        $order   = $orderNo !== '' ? Db::one('SELECT * FROM recharge_orders WHERE order_no = ? LIMIT 1', [$orderNo]) : null;
        if ($order && (int) $order['status'] === 1) {
            flash('success', '充值成功，金额已到账');
        } else {
            flash('warning', '支付结果确认中，若长时间未到账请联系客服');
        }
        redirect_route('wallet/index');
    }

    // ---------------------------------------------------------------- 支付宝

    /** 支付宝异步通知（只有验签通过的才入账） */
    public function alipayNotify()
    {
        $params = array_merge($_GET, $_POST);
        unset($params['r']);

        if (Alipay::channel() !== 'alipay') {
            echo 'channel disabled';
            return;
        }
        if (!Alipay::verifyNotify($params)) {
            echo 'sign error';
            return;
        }

        $orderNo = (string) ($params['out_trade_no'] ?? '');
        $status  = (string) ($params['trade_status'] ?? '');
        $tradeNo = mb_substr((string) ($params['trade_no'] ?? ''), 0, 64);

        if ($orderNo === '') {
            echo 'order error';
            return;
        }
        if (!in_array($status, ['TRADE_SUCCESS', 'TRADE_FINISHED'], true)) {
            echo 'wait';
            return;
        }

        try {
            $done = Db::tx(function () use ($orderNo, $tradeNo) {
                $order = Db::one('SELECT * FROM recharge_orders WHERE order_no = ? LIMIT 1', [$orderNo]);
                if (!$order) {
                    throw new \RuntimeException('充值单不存在');
                }
                if ((int) $order['status'] === 1) {
                    return true; // 幂等
                }
                if ((int) $order['status'] !== 0) {
                    return false; // 已关闭/已驳回的不再入账
                }

                FinanceService::auditRecharge((int) $order['id'], 0, 1, '支付宝自动入账');
                if ($tradeNo !== '') {
                    Db::update('recharge_orders', [
                        'trade_no'   => $tradeNo,
                        'updated_at' => now(),
                    ], 'id = ?', [(int) $order['id']]);
                }
                return true;
            });
            echo $done ? 'success' : 'fail';
        } catch (\Throwable $e) {
            echo 'fail';
        }
    }

    /** 支付宝同步跳回（不代表支付成功，会主动查一次单） */
    public function alipayReturn()
    {
        $orderNo = input_str('out_trade_no');
        $order   = $orderNo !== '' ? Db::one('SELECT * FROM recharge_orders WHERE order_no = ? LIMIT 1', [$orderNo]) : null;

        if ($order && (int) $order['status'] === 1) {
            flash('success', '充值成功，金额已到账');
            redirect_route('wallet/index');
        }
        if ($order) {
            try {
                $this->creditByQuery((string) $order['order_no']);
                flash('success', '支付已确认，金额已到账');
                redirect_route('wallet/index');
            } catch (\Throwable $e) {
                flash('warning', '支付结果确认中：' . $e->getMessage());
                redirect(url('wallet/alipayQr', ['id' => (int) $order['id']]));
            }
        }

        flash('warning', '未找到对应充值单，请联系客服');
        redirect_route('wallet/index');
    }

    /** 用户点「我已完成支付」：主动查单并补入账 */
    public function alipayQuery()
    {
        require_post();
        verify_csrf();
        Auth::requireLogin();

        $id = input_int('id', 0);
        try {
            $order = Db::one('SELECT * FROM recharge_orders WHERE id = ? AND user_id = ? LIMIT 1', [$id, Auth::id()]);
            if (!$order) {
                throw new \RuntimeException('充值单不存在');
            }
            if ((int) $order['status'] === 1) {
                flash('success', '该充值单已到账');
            } else {
                $this->creditByQuery((string) $order['order_no']);
                flash('success', '支付已确认，金额已到账');
            }
            redirect_route('wallet/index');
        } catch (\Throwable $e) {
            flash('warning', $e->getMessage());
            redirect(url('wallet/alipayQr', ['id' => $id]));
        }
    }

    /** 查单并入账（同步跳回与手动刷新共用） */
    private function creditByQuery(string $orderNo): void
    {
        if (!Alipay::configured()) {
            throw new \RuntimeException('支付宝尚未配置');
        }

        $resp   = Alipay::query($orderNo);
        $code   = (string) ($resp['code'] ?? '');
        $status = (string) ($resp['trade_status'] ?? '');

        if ($code !== '10000') {
            throw new \RuntimeException('未查询到支付结果（' . (string) ($resp['sub_msg'] ?? $resp['msg'] ?? $code) . '）');
        }
        if (!in_array($status, ['TRADE_SUCCESS', 'TRADE_FINISHED'], true)) {
            throw new \RuntimeException('交易状态：' . $status . '，请完成支付后再刷新');
        }

        Db::tx(function () use ($resp, $orderNo) {
            $order = Db::one('SELECT * FROM recharge_orders WHERE order_no = ? LIMIT 1', [$orderNo]);
            if (!$order) {
                throw new \RuntimeException('充值单不存在');
            }
            if ((int) $order['status'] === 1) {
                return;
            }
            if ((int) $order['status'] !== 0) {
                throw new \RuntimeException('该充值单已处理过，无法再次入账');
            }

            FinanceService::auditRecharge((int) $order['id'], 0, 1, '支付宝查单自动入账');

            $tradeNo = mb_substr((string) ($resp['trade_no'] ?? ''), 0, 64);
            if ($tradeNo !== '') {
                Db::update('recharge_orders', ['trade_no' => $tradeNo, 'updated_at' => now()], 'id = ?', [(int) $order['id']]);
            }
        });
    }
}
