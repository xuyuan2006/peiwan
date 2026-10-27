<?php
/**
 * 后台数据概览
 * @var array $stat
 * @var array $pending
 * @var array $recentOrders
 * @var array $statusMap
 */
?>
<div class="stat-grid">
  <div class="stat-card has-icon">
    <div class="sc-icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="8.5"/><path d="M12 7.5v9M14.8 10.2h-3.9a1.9 1.9 0 0 0 0 3.8h2.4a1.9 1.9 0 0 1 0 3.8H9.4"/>
      </svg>
    </div>
    <div class="sc-label">累计成交额（已完成订单）</div>
    <div class="sc-value"><?= e(yuan($stat['amount_total'])) ?></div>
    <div class="sc-sub">今日 <?= e(yuan($stat['amount_today'])) ?></div>
  </div>

  <div class="stat-card has-icon">
    <div class="sc-icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path d="M4 19.5V11M10 19.5V4.5M16 19.5v-7M3 21h18"/>
      </svg>
    </div>
    <div class="sc-label">平台收入（订单抽成 + 提现手续费）</div>
    <div class="sc-value"><?= e(yuan($stat['fee_total'] + $stat['withdraw_fee_total'])) ?></div>
    <div class="sc-sub">
      抽成 <?= e(yuan($stat['fee_total'])) ?>（<?= e(money(\App\Setting::float('platform_fee_rate', 20))) ?>%）
      + 提现手续费 <?= e(yuan($stat['withdraw_fee_total'])) ?>
    </div>
  </div>

  <div class="stat-card has-icon">
    <div class="sc-icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path d="M7.5 3.5h6.2L18 7.8v12.7H7.5z"/><path d="M7.5 3.5v4.3H18"/><path d="M10.2 12.6h5M10.2 16h3.4"/>
      </svg>
    </div>
    <div class="sc-label">订单总数</div>
    <div class="sc-value"><?= (int) $stat['order_total'] ?></div>
    <div class="sc-sub">今日新增 <?= (int) $stat['order_today'] ?></div>
  </div>

  <div class="stat-card has-icon">
    <div class="sc-icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="9" cy="8" r="3.4"/><path d="M2.8 20c0-3.3 2.8-5 6.2-5s6.2 1.7 6.2 5"/>
        <path d="M16 4.6a3.4 3.4 0 0 1 0 6.6"/><path d="M18.6 20c0-2.4-.9-3.9-2.5-4.6"/>
      </svg>
    </div>
    <div class="sc-label">用户 / 陪玩</div>
    <div class="sc-value"><?= (int) $stat['user_total'] ?> / <?= (int) $stat['companion_total'] ?></div>
    <div class="sc-sub">注册用户 / 陪玩</div>
  </div>
</div>

<div class="pending-grid">
  <a class="pending-card <?= $pending['companion'] > 0 ? 'warn' : 'ok' ?>" href="<?= e(admin_url('companion/index', ['audit' => 0])) ?>">
    <span>陪玩入驻待审核</span><strong><?= (int) $pending['companion'] ?></strong>
  </a>
  <a class="pending-card <?= $pending['recharge'] > 0 ? 'warn' : 'ok' ?>" href="<?= e(admin_url('recharge/index', ['status' => 0])) ?>">
    <span>充值待确认</span><strong><?= (int) $pending['recharge'] ?></strong>
  </a>
  <a class="pending-card <?= $pending['withdraw'] > 0 ? 'warn' : 'ok' ?>" href="<?= e(admin_url('withdraw/index', ['status' => 0])) ?>">
    <span>提现待审核</span><strong><?= (int) $pending['withdraw'] ?></strong>
  </a>
  <a class="pending-card <?= $pending['refund'] > 0 ? 'danger' : 'ok' ?>" href="<?= e(admin_url('order/index', ['status' => 6])) ?>">
    <span>退款待处理</span><strong><?= (int) $pending['refund'] ?></strong>
  </a>
</div>

<div class="admin-detail-grid">
  <section class="table-card">
    <div class="card-head" style="padding-top:12px">
      <h2 class="card-title">最新订单</h2>
      <a class="btn btn-ghost btn-sm" href="<?= e(admin_url('order/index')) ?>">全部订单</a>
    </div>
    <?php if (!$recentOrders): ?>
      <div class="empty">还没有订单</div>
    <?php else: ?>
      <table class="table">
        <thead><tr><th>订单号</th><th>用户</th><th>陪玩</th><th>金额</th><th>状态</th><th>时间</th></tr></thead>
        <tbody>
        <?php foreach ($recentOrders as $o): ?>
          <tr>
            <td><a href="<?= e(admin_url('order/detail', ['id' => (int) $o['id']])) ?>"><?= e($o['order_no']) ?></a></td>
            <td><?= e($o['user_nickname']) ?></td>
            <td><?= e($o['companion_nickname']) ?></td>
            <td><?= e(yuan((float) $o['amount'])) ?></td>
            <td><span class="status status-<?= e(order_status_class((int) $o['status'])) ?>"><?= e(order_status_text((int) $o['status'])) ?></span></td>
            <td class="muted nowrap"><?= e((string) $o['created_at']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>

  <aside>
    <section class="card">
      <h2 class="card-title">订单状态分布</h2>
      <ul class="stat-list">
        <?php foreach (order_status_map() as $status => $meta): ?>
          <li>
            <span><?= e($meta[0]) ?></span>
            <strong><?= (int) ($statusMap[$status] ?? 0) ?></strong>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>

    <section class="card">
      <h2 class="card-title">今日资金</h2>
      <ul class="stat-list">
        <li><span>充值到账</span><strong><?= e(yuan($stat['recharge_today'])) ?></strong></li>
        <li><span>提现打款</span><strong><?= e(yuan($stat['withdraw_today'])) ?></strong></li>
      </ul>
      <form method="post" action="<?= e(admin_url('order/autoTask')) ?>" style="margin-top:12px">
        <?= csrf_field() ?>
        <button class="btn btn-ghost btn-block btn-sm" type="submit">立即执行自动任务</button>
      </form>
      <p class="hint">自动任务：关闭超时未支付订单 + 自动确认已完成的订单。建议配置定时任务，见 README。</p>
    </section>
  </aside>
</div>
