<?php
/**
 * 陪玩中心
 * @var array $profile
 * @var array $stat
 * @var array $todo
 * @var array $wallet
 */
?>
<div class="page-head">
  <div class="head-row">
    <div>
      <h1>陪玩中心</h1>
      <p class="muted">
        当前状态：
        <?php if ((int) $profile['online_status'] === 1): ?>
          <span class="badge badge-success">接单中</span>
        <?php else: ?>
          <span class="badge badge-muted">休息中</span>
        <?php endif; ?>
        · 评分 <?= e(number_format((float) $profile['score'], 2)) ?> · 好评率 <?= e(number_format((float) $profile['good_rate'], 1)) ?>%
      </p>
    </div>
    <div class="head-actions">
      <form method="post" action="<?= e(url('companion/online')) ?>" class="inline-form">
        <?= csrf_field() ?>
        <button class="btn <?= (int) $profile['online_status'] === 1 ? 'btn-ghost' : 'btn-primary' ?>" type="submit">
          <?= (int) $profile['online_status'] === 1 ? '暂停接单' : '开启接单' ?>
        </button>
      </form>
      <a class="btn btn-primary" href="<?= e(url('companion/skills')) ?>">技能管理</a>
      <a class="btn btn-ghost" href="<?= e(url('companion/orders')) ?>">我的接单</a>
    </div>
  </div>
</div>

<section class="wallet-cards">
  <div class="card wallet-card primary">
    <span class="wc-label">钱包余额（元）</span>
    <strong class="wc-value"><?= e(money((float) $wallet['balance'])) ?></strong>
    <div class="wc-actions">
      <a class="btn btn-light btn-sm" href="<?= e(url('wallet/withdraw')) ?>">提现</a>
      <a class="btn btn-light btn-sm" href="<?= e(url('wallet/index')) ?>">钱包明细</a>
    </div>
  </div>
  <div class="card wallet-card">
    <span class="wc-label">累计收入（元）</span>
    <strong class="wc-value"><?= e(money((float) $profile['total_income'])) ?></strong>
    <span class="muted">今日 <?= e(money((float) $stat['today_income'])) ?> 元</span>
  </div>
  <div class="card wallet-card">
    <span class="wc-label">完成订单</span>
    <strong class="wc-value"><?= (int) $stat['completed_orders'] ?></strong>
    <span class="muted">进行中 <?= (int) $stat['serving_orders'] ?> 单</span>
  </div>
  <div class="card wallet-card">
    <span class="wc-label">冻结金额（元）</span>
    <strong class="wc-value"><?= e(money((float) $wallet['frozen'])) ?></strong>
    <span class="muted">提现审核中</span>
  </div>
</section>

<section class="card">
  <h2 class="card-title">待处理订单</h2>
  <?php if (!$todo): ?>
    <div class="empty">暂时没有待处理的订单，保持在线更容易接到单哦</div>
  <?php else: ?>
    <div class="order-list">
      <?php foreach ($todo as $order): ?>
        <div class="order-card inner">
          <div class="order-head">
            <span class="order-no">订单号 <?= e($order['order_no']) ?></span>
            <span class="status status-<?= e(order_status_class((int) $order['status'])) ?>"><?= e(order_status_text((int) $order['status'])) ?></span>
            <span class="muted order-time"><?= e((string) $order['created_at']) ?></span>
          </div>
          <div class="order-body">
            <img class="avatar" src="<?= e(avatar_url($order['user_avatar'], (string) $order['user_nickname'])) ?>" alt="">
            <div class="order-info">
              <div class="order-title"><?= e($order['game_name']) ?> · <?= e($order['skill_title']) ?></div>
              <div class="order-sub muted">
                用户：<?= e($order['user_nickname']) ?>
                · <?= (int) $order['quantity'] ?> <?= e(service_unit_suffix((string) $order['service_type'])) ?>
                <?php if ($order['user_remark'] !== ''): ?> · 备注：<?= e(str_limit((string) $order['user_remark'], 30)) ?><?php endif; ?>
              </div>
            </div>
            <div class="order-amount">
              <strong><?= e(yuan((float) $order['amount'])) ?></strong>
              <small class="muted">预计收入 <?= e(yuan(safe_float((float) $order['amount'] * (100 - \App\Setting::float('platform_fee_rate', 20)) / 100))) ?></small>
            </div>
          </div>
          <?php
          $role = 'companion';
          include APP_ROOT . '/app/views/partials/order_actions.php';
          ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
