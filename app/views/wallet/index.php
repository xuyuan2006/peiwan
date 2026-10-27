<?php
/**
 * 钱包首页
 * @var array $wallet
 * @var array $txns
 * @var array $recharges
 * @var array $withdraws
 * @var bool  $epay_on
 */
?>
<div class="page-head">
  <h1>我的钱包</h1>
  <p class="muted">余额可用于下单支付，陪玩收入可申请提现</p>
</div>

<section class="wallet-cards">
  <div class="card wallet-card primary">
    <span class="wc-label">可用余额（元）</span>
    <strong class="wc-value"><?= e(money((float) $wallet['balance'])) ?></strong>
    <div class="wc-actions">
      <a class="btn btn-light btn-sm" href="<?= e(url('wallet/recharge')) ?>">充值</a>
      <a class="btn btn-light btn-sm" href="<?= e(url('wallet/withdraw')) ?>">提现</a>
    </div>
  </div>
  <div class="card wallet-card">
    <span class="wc-label">冻结金额（提现审核中）</span>
    <strong class="wc-value"><?= e(money((float) $wallet['frozen'])) ?></strong>
    <span class="muted">审核通过后打款，驳回则自动解冻</span>
  </div>
  <div class="card wallet-card">
    <span class="wc-label">累计充值</span>
    <strong class="wc-value"><?= e(money((float) $wallet['total_recharge'])) ?></strong>
    <span class="muted">累计收入 <?= e(money((float) $wallet['total_income'])) ?> 元</span>
  </div>
  <div class="card wallet-card">
    <span class="wc-label">累计提现</span>
    <strong class="wc-value"><?= e(money((float) $wallet['total_withdraw'])) ?></strong>
    <span class="muted">累计消费 <?= e(money((float) $wallet['total_expense'])) ?> 元</span>
  </div>
</section>

<section class="card">
  <div class="card-head">
    <h2 class="card-title">最近资金流水</h2>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('wallet/transactions')) ?>">查看全部</a>
  </div>
  <?php if (!$txns): ?>
    <div class="empty">还没有资金记录</div>
  <?php else: ?>
    <table class="table">
      <thead><tr><th>时间</th><th>类型</th><th>金额</th><th>余额</th><th>备注</th></tr></thead>
      <tbody>
      <?php foreach ($txns as $t): ?>
        <tr>
          <td class="muted"><?= e((string) $t['created_at']) ?></td>
          <td><?= e(wallet_type_text((string) $t['type'])) ?></td>
          <td class="<?= (int) $t['direction'] === 1 ? 'text-success' : 'text-danger' ?>">
            <?= (int) $t['direction'] === 1 ? '+' : '-' ?><?= e(money((float) $t['amount'])) ?>
          </td>
          <td><?= e(money((float) $t['balance_after'])) ?></td>
          <td class="muted"><?= e(str_limit((string) $t['remark'], 30)) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>

<div class="detail-grid">
  <section class="card">
    <div class="card-head">
      <h2 class="card-title">充值记录</h2>
      <a class="btn btn-ghost btn-sm" href="<?= e(url('wallet/recharge')) ?>">去充值</a>
    </div>
    <?php if (!$recharges): ?>
      <div class="empty">暂无充值记录</div>
    <?php else: ?>
      <table class="table">
        <thead><tr><th>单号</th><th>金额</th><th>状态</th><th>时间</th></tr></thead>
        <tbody>
        <?php foreach ($recharges as $r): ?>
          <?php $map = [0 => ['待处理', 'warn'], 1 => ['已到账', 'success'], 2 => ['已关闭', 'muted'], 3 => ['已驳回', 'muted']]; ?>
          <tr>
            <td><a href="<?= e(url('wallet/rechargeDetail', ['id' => (int) $r['id']])) ?>"><?= e($r['order_no']) ?></a></td>
            <td><?= e(money((float) $r['amount'])) ?></td>
            <td><span class="status status-<?= e($map[(int) $r['status']][1] ?? 'muted') ?>"><?= e($map[(int) $r['status']][0] ?? '未知') ?></span></td>
            <td class="muted"><?= e((string) $r['created_at']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>

  <section class="card">
    <div class="card-head">
      <h2 class="card-title">提现记录</h2>
      <a class="btn btn-ghost btn-sm" href="<?= e(url('wallet/withdraw')) ?>">去提现</a>
    </div>
    <?php if (!$withdraws): ?>
      <div class="empty">暂无提现记录</div>
    <?php else: ?>
      <table class="table">
        <thead><tr><th>单号</th><th>金额</th><th>状态</th><th>时间</th></tr></thead>
        <tbody>
        <?php foreach ($withdraws as $w): ?>
          <?php $wmap = [0 => ['审核中', 'warn'], 1 => ['已打款', 'success'], 2 => ['已驳回', 'muted']]; ?>
          <tr>
            <td><?= e($w['order_no']) ?></td>
            <td><?= e(money((float) $w['amount'])) ?></td>
            <td><span class="status status-<?= e($wmap[(int) $w['status']][1] ?? 'muted') ?>"><?= e($wmap[(int) $w['status']][0] ?? '未知') ?></span></td>
            <td class="muted"><?= e((string) $w['created_at']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>
</div>
