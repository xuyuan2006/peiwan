<?php
/**
 * 钱包服务：余额、冻结、流水。
 * 所有变动必须在事务中调用，内部使用 SELECT ... FOR UPDATE 保证并发安全。
 */
declare(strict_types=1);

namespace App;

class Wallet
{
    /** 确保钱包存在并加行锁，返回钱包行 */
    private static function locked(int $userId): array
    {
        $row = Db::one('SELECT * FROM user_wallets WHERE user_id = ? FOR UPDATE', [$userId]);
        if ($row) {
            return $row;
        }
        Db::query(
            'INSERT IGNORE INTO user_wallets (user_id, balance, frozen, total_recharge, total_income, total_withdraw, total_expense, created_at, updated_at)
             VALUES (?, 0, 0, 0, 0, 0, 0, ?, ?)',
            [$userId, now(), now()]
        );
        $row = Db::one('SELECT * FROM user_wallets WHERE user_id = ? FOR UPDATE', [$userId]);
        if (!$row) {
            throw new \RuntimeException('钱包初始化失败');
        }
        return $row;
    }

    /** @return array<string,mixed> */
    public static function get(int $userId): array
    {
        $row = Db::one('SELECT * FROM user_wallets WHERE user_id = ? LIMIT 1', [$userId]);
        if ($row) {
            return $row;
        }
        Db::query(
            'INSERT IGNORE INTO user_wallets (user_id, balance, frozen, total_recharge, total_income, total_withdraw, total_expense, created_at, updated_at)
             VALUES (?, 0, 0, 0, 0, 0, 0, ?, ?)',
            [$userId, now(), now()]
        );
        $row = Db::one('SELECT * FROM user_wallets WHERE user_id = ? LIMIT 1', [$userId]);
        return $row ?: [
            'balance' => '0.00', 'frozen' => '0.00', 'total_recharge' => '0.00',
            'total_income' => '0.00', 'total_withdraw' => '0.00', 'total_expense' => '0.00',
        ];
    }

    public static function balance(int $userId): float
    {
        $w = self::get($userId);
        return (float) $w['balance'];
    }

    // ---------------------------------------------------------------- 余额变动

    /** 收入 */
    public static function income(int $userId, float $amount, string $type, string $bizType = '', int $bizId = 0, string $remark = '', array $extra = []): void
    {
        $amount = safe_float($amount);
        self::assertAmount($amount);
        $w      = self::locked($userId);
        $before = (float) $w['balance'];
        $after  = safe_float($before + $amount);
        $data   = array_merge([
            'balance'    => $after,
            'updated_at' => now(),
        ], $extra);
        Db::update('user_wallets', $data, 'user_id = ?', [$userId]);
        self::log($userId, $type, 1, $amount, $before, $after, (float) $w['frozen'], (float) $w['frozen'], $bizType, $bizId, $remark);
    }

    /** 支出 */
    public static function expense(int $userId, float $amount, string $type, string $bizType = '', int $bizId = 0, string $remark = '', array $extra = []): void
    {
        $amount = safe_float($amount);
        self::assertAmount($amount);
        $w      = self::locked($userId);
        $before = (float) $w['balance'];
        if ($before + 0.00001 < $amount) {
            throw new \RuntimeException('账户余额不足，请先充值');
        }
        $after = safe_float($before - $amount);
        if ($after < 0) {
            $after = 0.0;
        }
        $data = array_merge([
            'balance'    => $after,
            'updated_at' => now(),
        ], $extra);
        Db::update('user_wallets', $data, 'user_id = ?', [$userId]);
        self::log($userId, $type, 2, $amount, $before, $after, (float) $w['frozen'], (float) $w['frozen'], $bizType, $bizId, $remark);
    }

    // ---------------------------------------------------------------- 冻结/解冻

    /** 冻结：可用余额 → 冻结金额 */
    public static function freeze(int $userId, float $amount, string $type, string $bizType = '', int $bizId = 0, string $remark = ''): void
    {
        $amount = safe_float($amount);
        self::assertAmount($amount);
        $w = self::locked($userId);
        $balanceBefore = (float) $w['balance'];
        $frozenBefore  = (float) $w['frozen'];
        if ($balanceBefore + 0.00001 < $amount) {
            throw new \RuntimeException('可用余额不足，无法提交申请');
        }
        $balanceAfter = safe_float($balanceBefore - $amount);
        $frozenAfter  = safe_float($frozenBefore + $amount);
        Db::update('user_wallets', [
            'balance'    => $balanceAfter,
            'frozen'     => $frozenAfter,
            'updated_at' => now(),
        ], 'user_id = ?', [$userId]);
        self::log($userId, $type, 2, $amount, $balanceBefore, $balanceAfter, $frozenBefore, $frozenAfter, $bizType, $bizId, $remark);
    }

    /** 解冻：冻结金额 → 可用余额（提现被驳回时使用） */
    public static function unfreeze(int $userId, float $amount, string $type, string $bizType = '', int $bizId = 0, string $remark = ''): void
    {
        $amount = safe_float($amount);
        self::assertAmount($amount);
        $w = self::locked($userId);
        $balanceBefore = (float) $w['balance'];
        $frozenBefore  = (float) $w['frozen'];
        if ($frozenBefore + 0.00001 < $amount) {
            // 不能静默按 0 处理，否则差额会凭空加到余额（造币）
            throw new \RuntimeException('冻结金额不足，无法解冻（当前冻结 ' . money($frozenBefore) . ' 元）');
        }
        $balanceAfter = safe_float($balanceBefore + $amount);
        $frozenAfter  = safe_float($frozenBefore - $amount);
        Db::update('user_wallets', [
            'balance'    => $balanceAfter,
            'frozen'     => $frozenAfter,
            'updated_at' => now(),
        ], 'user_id = ?', [$userId]);
        self::log($userId, $type, 1, $amount, $balanceBefore, $balanceAfter, $frozenBefore, $frozenAfter, $bizType, $bizId, $remark);
    }

    /** 冻结扣减：提现打款成功后，从冻结金额里扣走（余额已扣过，不再变化） */
    public static function deductFrozen(int $userId, float $amount, string $type, string $bizType = '', int $bizId = 0, string $remark = ''): void
    {
        $amount = safe_float($amount);
        self::assertAmount($amount);
        $w = self::locked($userId);
        $balance = (float) $w['balance'];
        $frozenBefore = (float) $w['frozen'];
        if ($frozenBefore + 0.00001 < $amount) {
            throw new \RuntimeException('冻结金额不足，无法扣减（当前冻结 ' . money($frozenBefore) . ' 元）');
        }
        $frozenAfter  = safe_float($frozenBefore - $amount);
        Db::update('user_wallets', [
            'frozen'     => $frozenAfter,
            'updated_at' => now(),
        ], 'user_id = ?', [$userId]);
        self::log($userId, $type, 2, $amount, $balance, $balance, $frozenBefore, $frozenAfter, $bizType, $bizId, $remark);
    }

    // ---------------------------------------------------------------- 流水

    public static function log(
        int $userId,
        string $type,
        int $direction,
        float $amount,
        float $balanceBefore,
        float $balanceAfter,
        float $frozenBefore,
        float $frozenAfter,
        string $bizType,
        int $bizId,
        string $remark
    ): void {
        Db::insert('wallet_transactions', [
            'user_id'        => $userId,
            'type'           => $type,
            'direction'      => $direction,
            'amount'         => safe_float($amount),
            'balance_before' => safe_float($balanceBefore),
            'balance_after'  => safe_float($balanceAfter),
            'frozen_before'  => safe_float($frozenBefore),
            'frozen_after'   => safe_float($frozenAfter),
            'biz_type'       => $bizType,
            'biz_id'         => $bizId,
            'remark'         => mb_substr($remark, 0, 250),
            'created_at'     => now(),
        ]);
    }

    private static function assertAmount(float $amount): void
    {
        if ($amount <= 0) {
            throw new \RuntimeException('金额必须大于 0');
        }
        if ($amount > 1000000) {
            throw new \RuntimeException('单笔金额超出限制');
        }
    }
}
