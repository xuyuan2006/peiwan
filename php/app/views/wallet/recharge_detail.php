<?php
/**
 * 充值单详情
 * @var array $order
 */
$map = [0 => ['待处理', 'warn'], 1 => ['已到账', 'success'], 2 => ['已关闭', 'muted'], 3 => ['已驳回', 'muted']];
$st  = $map[(int) $order['status']] ?? ['未知', 'muted'];
?>
<div class="page-head">
  <h1>充值单详情</h1>
  <p class="muted">单号 <?= e($order['order_no']) ?></p>
</div>

<section class="card">
  <table class="table table-plain">
    <tr><th>充值金额</th><td><?= e(yuan((float) $order['amount'])) ?></td><th>状态</th>
      <td><span class="status status-<?= e($st[1]) ?>"><?= e($st[0]) ?></span></td></tr>
    <tr><th>支付渠道</th><td><?= e($order['channel'] === 'epay' ? '在线支付' : '人工转账') ?></td>
      <th>提交时间</th><td><?= e((string) $order['created_at']) ?></td></tr>
    <tr><th>到账时间</th><td><?= e((string) ($order['paid_at'] ?: '-')) ?></td>
      <th>审核备注</th><td><?= e($order['audit_remark'] !== '' ? $order['audit_remark'] : '-') ?></td></tr>
    <?php if ($order['remark'] !== ''): ?>
      <tr><th>用户备注</th><td colspan="3"><?= e($order['remark']) ?></td></tr>
    <?php endif; ?>
  </table>

  <?php if ($order['pay_voucher'] !== ''): ?>
    <div class="form-row">
      <label>转账凭证</label>
      <img class="voucher-img" src="<?= e(upload_url((string) $order['pay_voucher'])) ?>" alt="转账凭证">
    </div>
  <?php endif; ?>

  <a class="btn btn-ghost" href="<?= e(url('wallet/index')) ?>">返回钱包</a>
</section>
