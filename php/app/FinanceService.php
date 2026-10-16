<?php
/**
 * 资金业务服务：充值审核、提现申请与审核、后台人工调账。
 *
 * 为什么单独抽出来：这些逻辑既要被后台控制器调用，也要能被自动化自检脚本调用，
 * 放在控制器里无法测试。所有资金变动都在事务内完成。
 */
declare(strict_types=1);

namespace App;

class FinanceService
{
    // ---------------------------------------------------------------- 充值审核

    /**
     * 审核充值单
     * @param int    $result 1=确认到账 2=关闭 3=驳回
     * @return array{recharge_id:int,result:int,amount:float,user_id:int}
     */
    public static function auditRecharge(int $rechargeId, int $adminId, int $result, string $remark = ''): array
    {
        if (!in_array($result, [1, 2, 3], true)) {
            throw new \RuntimeException('处理结果不正确');
        }

        return Db::tx(function () use ($rechargeId, $adminId, $result, $remark) {
            $order = Db::one('SELECT * FROM recharge_orders WHERE id = ? FOR UPDATE', [$rechargeId]);
            if (!$order) {
                throw new \RuntimeException('充值单不存在');
            }
            // 只有"待处理"的单据可以被处理：已到账不能再入账，已驳回/已关闭也不能改判入账
            if ((int) $order['status'] !== 0) {
                throw new \RuntimeException('该充值单已处理过（当前状态：' . (int) $order['status'] . '），不能重复操作');
            }

            $amount = safe_float((float) $order['amount']);
            if ($result === 1) {
                Wallet::income((int) $order['user_id'], $amount, 'recharge', 'recharge', $rechargeId, '确认充值到账 ' . $order['order_no'], [
                    'total_recharge' => new Expr('total_recharge + ' . $amount),
                ]);
            }

            Db::update('recharge_orders', [
                'status'       => $result,
                'audit_by'     => $adminId,
                'audit_at'     => now(),
                'audit_remark' => mb_substr($remark, 0, 200),
                'paid_at'      => $result === 1 ? now() : $order['paid_at'],
                'updated_at'   => now(),
            ], 'id = ?', [$rechargeId]);

            return [
                'recharge_id' => $rechargeId,
                'result'      => $result,
                'amount'      => $amount,
                'user_id'     => (int) $order['user_id'],
            ];
        });
    }

    /** 校验充值金额（控制器在上传凭证前先调用，避免留下孤儿文件） */
    public static function assertRechargeAmount(float $amount): void
    {
        if ($amount < 1) {
            throw new \RuntimeException('充值金额不能小于 1 元');
        }
        if ($amount > 50000) {
            throw new \RuntimeException('单次充值不能超过 50000 元');
        }
    }

    /** 创建充值单（人工转账渠道 / 在线支付渠道） */
    public static function createRecharge(int $userId, float $amount, string $channel, string $voucher = '', string $remark = ''): int
    {
        self::assertRechargeAmount($amount);

        if (!in_array($channel, ['manual', 'epay', 'alipay'], true)) {
            throw new \RuntimeException('充值渠道不合法');
        }
        if (!Db::one('SELECT id FROM users WHERE id = ? LIMIT 1', [$userId])) {
            throw new \RuntimeException('用户不存在');
        }

        return Db::insert('recharge_orders', [
            'order_no'    => order_no('RC'),
            'user_id'     => $userId,
            'amount'      => safe_float($amount),
            'channel'     => $channel,
            'status'      => 0,
            'pay_voucher' => $voucher,
            'remark'      => mb_substr($remark, 0, 200),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    /** 关闭长时间未支付的在线充值单（避免列表里堆着一堆未付款记录） */
    public static function autoCloseExpiredRecharge(int $hours = 24): int
    {
        $hours = max(1, $hours);
        $rows  = Db::all(
            "SELECT id FROM recharge_orders WHERE status = 0 AND channel = 'epay' AND created_at < ? LIMIT 200",
            [date('Y-m-d H:i:s', time() - $hours * 3600)]
        );
        $count = 0;
        foreach ($rows as $row) {
            $n = Db::update('recharge_orders', [
                'status'       => 2,
                'audit_remark' => '超时未支付，系统自动关闭',
                'updated_at'   => now(),
            ], 'id = ? AND status = 0', [(int) $row['id']]);
            if ($n > 0) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * 用户主动取消自己**未支付**的充值单。
     *
     * 状态沿用系统超时关闭时用的 2（已关闭）—— 后台列表和 App 都按这一个口径识别，
     * 不另造状态码。WHERE 里再带一次 status = 0 是为了防并发：
     * 万一用户点取消的同时支付回调到了，这里不会把已到账的单子改成「已关闭」。
     */
    public static function cancelRecharge(int $id, int $userId): void
    {
        $order = Db::one(
            'SELECT * FROM recharge_orders WHERE id = ? AND user_id = ? LIMIT 1',
            [$id, $userId]
        );
        if (!$order) {
            throw new \RuntimeException('充值单不存在');
        }
        if ((int) $order['status'] !== 0) {
            throw new \RuntimeException('该充值单已处理，不能取消');
        }

        $n = Db::update('recharge_orders', [
            'status'       => 2,
            'audit_remark' => '用户主动取消',
            'updated_at'   => now(),
        ], 'id = ? AND status = 0', [$id]);

        if ($n === 0) {
            throw new \RuntimeException('该充值单已处理，不能取消');
        }
    }

    // ---------------------------------------------------------------- 提现

    /**
     * 申请提现：校验金额 → 生成提现单 → 冻结金额
     * @return array{withdraw_id:int,amount:float,fee:float,real_amount:float}
     */
    public static function applyWithdraw(int $userId, float $amount, string $accountType, string $accountName, string $accountNo): array
    {
        $amount = safe_float($amount);
        $min    = Setting::float('min_withdraw', 50.0);
        $feeRate = Setting::float('withdraw_fee_rate', 1.0);

        if (!in_array($accountType, ['alipay', 'wechat', 'bank'], true)) {
            throw new \RuntimeException('请选择收款方式');
        }
        if ($accountName === '' || $accountNo === '') {
            throw new \RuntimeException('请填写收款人姓名和收款账号');
        }
        if ($amount < $min) {
            throw new \RuntimeException('最低提现金额为 ' . money($min) . ' 元');
        }

        $fee  = safe_float($amount * $feeRate / 100);
        $real = safe_float($amount - $fee);
        if ($real <= 0) {
            throw new \RuntimeException('提现金额过小，扣除手续费后无金额可打款');
        }

        return Db::tx(function () use ($userId, $amount, $fee, $real, $accountType, $accountName, $accountNo) {
            $balance = Wallet::balance($userId);
            if ($amount > $balance + 0.00001) {
                throw new \RuntimeException('可用余额不足，当前可用 ' . money($balance) . ' 元');
            }

            $id = Db::insert('withdraw_orders', [
                'order_no'     => order_no('WD'),
                'user_id'      => $userId,
                'amount'       => $amount,
                'fee'          => $fee,
                'real_amount'  => $real,
                'account_type' => $accountType,
                'account_name' => mb_substr($accountName, 0, 50),
                'account_no'   => mb_substr($accountNo, 0, 100),
                'status'       => 0,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            Wallet::freeze($userId, $amount, 'withdraw_apply', 'withdraw', $id, '提现申请冻结 ' . money($amount) . ' 元');

            return ['withdraw_id' => $id, 'amount' => $amount, 'fee' => $fee, 'real_amount' => $real];
        });
    }

    /**
     * 审核提现单
     * @param int $result 1=已打款（扣冻结） 2=驳回（解冻回余额）
     * @return array{withdraw_id:int,result:int,amount:float,user_id:int}
     */
    public static function auditWithdraw(int $withdrawId, int $adminId, int $result, string $remark = ''): array
    {
        if (!in_array($result, [1, 2], true)) {
            throw new \RuntimeException('处理结果不正确');
        }

        return Db::tx(function () use ($withdrawId, $adminId, $result, $remark) {
            $order = Db::one('SELECT * FROM withdraw_orders WHERE id = ? FOR UPDATE', [$withdrawId]);
            if (!$order) {
                throw new \RuntimeException('提现单不存在');
            }
            if ((int) $order['status'] !== 0) {
                throw new \RuntimeException('该提现单已处理过');
            }

            $amount = safe_float((float) $order['amount']);
            $userId = (int) $order['user_id'];

            if ($result === 1) {
                // 打款成功：冻结金额扣走（余额在申请时已扣），累计提现增加
                Wallet::deductFrozen($userId, $amount, 'withdraw_paid', 'withdraw', $withdrawId, '提现打款 ' . $order['order_no']);
                Db::query(
                    'UPDATE user_wallets SET total_withdraw = total_withdraw + ?, updated_at = ? WHERE user_id = ?',
                    [$amount, now(), $userId]
                );
            } else {
                // 驳回：冻结金额解冻回可用余额
                Wallet::unfreeze($userId, $amount, 'withdraw_reject', 'withdraw', $withdrawId, '提现驳回解冻 ' . $order['order_no']);
            }

            Db::update('withdraw_orders', [
                'status'       => $result,
                'audit_by'     => $adminId,
                'audit_at'     => now(),
                'audit_remark' => mb_substr($remark, 0, 200),
                'updated_at'   => now(),
            ], 'id = ?', [$withdrawId]);

            return ['withdraw_id' => $withdrawId, 'result' => $result, 'amount' => $amount, 'user_id' => $userId];
        });
    }

    // ---------------------------------------------------------------- 人工调账

    /**
     * 后台人工加款/扣款
     * @param string $type add=加款 sub=扣款
     */
    public static function adjustBalance(int $userId, float $amount, string $type, int $adminId, string $remark = ''): void
    {
        $amount = safe_float($amount);
        if ($amount <= 0) {
            throw new \RuntimeException('调整金额必须大于 0');
        }
        if (!in_array($type, ['add', 'sub'], true)) {
            throw new \RuntimeException('调整类型不正确');
        }
        if (!Db::one('SELECT id FROM users WHERE id = ? LIMIT 1', [$userId])) {
            throw new \RuntimeException('用户不存在');
        }

        Db::tx(function () use ($userId, $amount, $type, $adminId, $remark) {
            if ($type === 'add') {
                Wallet::income($userId, $amount, 'admin_adjust', 'admin', $adminId, '后台加款：' . $remark);
            } else {
                Wallet::expense($userId, $amount, 'admin_adjust', 'admin', $adminId, '后台扣款：' . $remark);
            }
        });
    }
}
