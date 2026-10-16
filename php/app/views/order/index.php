<?php
/**
 * 我的订单（用户视角）
 * @var array  $result
 * @var string $status
 * @var array  $counts
 */
$rows      = $result['rows'];
$total     = (int) $result['total'];
$page      = (int) $result['page'];
$perPage   = (int) $result['per_page'];
$totalPage = (int) ceil($total / max(1, $perPage));

$tabs = [
    'all' => '全部',
    '1'   => '待支付',
    '2'   => '待接单',
    '3'   => '服务中',
    '4'   => '待确认',
    '5'   => '已完成',
    'refund' => '退款',
    'closed'  => '已关闭',
];
?>
<div class="page-head">
  <h1>我的订单</h1>
  <p class="muted">
    待支付 <?= (int) ($counts['pending_pay'] ?? 0) ?> ·
    进行中 <?= (int) ($counts['serving'] ?? 0) ?> ·
    已完成 <?= (int) ($counts['done'] ?? 0) ?>
  </p>
</div>

<div class="tabs">
  <?php foreach ($tabs as $key => $label): ?>
    <a class="tab <?= $status === (string) $key ? 'active' : '' ?>"
       href="<?= e(url('order/index', ['status' => $key])) ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</div>

<?php if (!$rows): ?>
  <div class="card empty">还没有订单，去 <a href="<?= e(url('home/index')) ?>">找一个陪玩</a> 吧</div>
<?php else: ?>
  <div class="order-list">
    <?php foreach ($rows as $order): ?>
      <div class="card order-card">
        <div class="order-head">
          <span class="order-no">订单号 <?= e($order['order_no']) ?><?php if (!empty($unread[(int) $order['id']])): ?><em class="nav-badge"><?= (int) $unread[(int) $order['id']] ?></em><?php endif; ?></span>
          <span class="status status-<?= e(order_status_class((int) $order['status'])) ?>"><?= e(order_status_text((int) $order['status'])) ?></span>
          <span class="muted order-time"><?= e((string) $order['created_at']) ?></span>
        </div>

        <div class="order-body">
          <img class="avatar" src="<?= e(avatar_url($order['companion_avatar'], (string) $order['companion_nickname'])) ?>" alt="">
          <div class="order-info">
            <div class="order-title">
              <?= e($order['game_name']) ?> · <?= e($order['skill_title']) ?>
            </div>
            <div class="order-sub muted">
              陪玩：<?= e($order['companion_nickname']) ?>
              · <?= (int) $order['quantity'] ?> <?= e(service_unit_suffix((string) $order['service_type'])) ?>
              × <?= e(yuan((float) $order['unit_price'])) ?>
            </div>
            <?php if ($order['user_remark'] !== ''): ?>
              <div class="order-sub muted">备注：<?= e(str_limit((string) $order['user_remark'], 40)) ?></div>
            <?php endif; ?>
          </div>
          <div class="order-amount">
            <strong><?= e(yuan((float) $order['amount'])) ?></strong>
            <?php if ((int) $order['status'] === 5): ?>
              <small class="muted">陪玩实收 <?= e(yuan((float) $order['companion_income'])) ?></small>
            <?php endif; ?>
          </div>
        </div>

        <?php
        $role = 'user';
        include APP_ROOT . '/app/views/partials/order_actions.php';
        ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?= pager($page, $total, $perPage) ?>
