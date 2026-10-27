<?php
/**
 * 订单操作按钮（列表与详情共用）
 * @var array  $order
 * @var string $role  user | companion
 */
$__id     = (int) $order['id'];
$__status = (int) $order['status'];
$__detail = url('order/detail', ['id' => $__id]);
?>
<div class="order-actions">
<?php if ($role === 'user'): ?>
  <?php if ($__status === 1): ?>
    <form method="post" action="<?= e(url('order/pay')) ?>" class="inline-form">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= $__id ?>">
      <button class="btn btn-primary btn-sm" type="submit">立即支付</button>
    </form>
    <form method="post" action="<?= e(url('order/cancel')) ?>" class="inline-form" data-confirm="确定取消该订单吗？">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= $__id ?>">
      <input type="hidden" name="reason" value="用户主动取消">
      <button class="btn btn-ghost btn-sm" type="submit">取消订单</button>
    </form>
  <?php elseif ($__status === 2): ?>
    <a class="btn btn-ghost btn-sm" href="<?= e($__detail) ?>">申请退款</a>
    <form method="post" action="<?= e(url('order/cancel')) ?>" class="inline-form" data-confirm="取消后金额将退回余额，确定吗？">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= $__id ?>">
      <input type="hidden" name="reason" value="陪玩未接单，用户取消">
      <button class="btn btn-ghost btn-sm" type="submit">取消并退款</button>
    </form>
  <?php elseif ($__status === 3): ?>
    <a class="btn btn-ghost btn-sm" href="<?= e($__detail) ?>">申请退款</a>
  <?php elseif ($__status === 4): ?>
    <form method="post" action="<?= e(url('order/confirm')) ?>" class="inline-form" data-confirm="确认服务已完成？确认后金额将结算给陪玩。">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= $__id ?>">
      <button class="btn btn-primary btn-sm" type="submit">确认完成</button>
    </form>
    <a class="btn btn-ghost btn-sm" href="<?= e($__detail) ?>">申请退款</a>
  <?php elseif ($__status === 5 && (int) $order['is_reviewed'] === 0): ?>
    <a class="btn btn-primary btn-sm" href="<?= e($__detail) ?>#review">去评价</a>
  <?php endif; ?>
  <a class="btn btn-ghost btn-sm" href="<?= e($__detail) ?>">订单详情</a>
<?php else: ?>
  <?php if ($__status === 2): ?>
    <form method="post" action="<?= e(url('companion/accept')) ?>" class="inline-form">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= $__id ?>">
      <button class="btn btn-primary btn-sm" type="submit">立即接单</button>
    </form>
  <?php elseif ($__status === 3): ?>
    <form method="post" action="<?= e(url('companion/finish')) ?>" class="inline-form" data-confirm="确认已提供服务并提交完成？">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= $__id ?>">
      <button class="btn btn-primary btn-sm" type="submit">提交完成</button>
    </form>
  <?php endif; ?>
  <a class="btn btn-ghost btn-sm" href="<?= e($__detail) ?>">订单详情</a>
<?php endif; ?>
</div>
