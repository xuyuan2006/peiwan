<?php
/**
 * 后台充值审核
 * @var array  $rows
 * @var int    $total
 * @var int    $page
 * @var int    $per_page
 * @var string $status
 * @var string $keyword
 * @var array  $sum
 */
$totalPage = (int) ceil($total / max(1, $per_page));
$tabs = ['0' => '待处理', '1' => '已到账', '2' => '已关闭', '3' => '已驳回', 'all' => '全部'];
$statusMap = [0 => ['待处理', 'warn'], 1 => ['已到账', 'success'], 2 => ['已关闭', 'muted'], 3 => ['已驳回', 'muted']];
?>
<div class="summary-row">
  <span>待处理 <strong><?= (int) ($sum['pending_count'] ?? 0) ?></strong> 笔，合计 <?= e(yuan((float) ($sum['pending_amount'] ?? 0))) ?></span>
  <span>累计到账 <strong><?= e(yuan((float) ($sum['paid_amount'] ?? 0))) ?></strong></span>
</div>

<div class="tabs">
  <?php foreach ($tabs as $key => $label): ?>
    <a class="tab <?= $status === (string) $key ? 'active' : '' ?>"
       href="<?= e(admin_url('recharge/index', ['status' => $key])) ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</div>

<form class="filter-bar" method="get" action="<?= e(admin_url('recharge/index')) ?>">
  <input type="hidden" name="r" value="recharge/index">
  <input type="hidden" name="status" value="<?= e($status) ?>">
  <label>关键词
    <input type="text" name="keyword" value="<?= e($keyword) ?>" placeholder="单号 / 用户昵称 / 第三方流水号">
  </label>
  <div class="actions">
    <button class="btn btn-primary btn-sm" type="submit">查询</button>
    <a class="btn btn-ghost btn-sm" href="<?= e(admin_url('recharge/index')) ?>">重置</a>
  </div>
</form>

<div class="table-card">
  <div class="summary-row" style="padding-top:12px">
    <span>共 <strong><?= (int) $total ?></strong> 条</span>
  </div>
  <table class="table">
    <thead>
      <tr><th>单号</th><th>用户</th><th>金额</th><th>渠道</th><th>凭证 / 备注</th><th>状态</th><th>提交时间</th><th>操作</th></tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <?php $st = $statusMap[(int) $r['status']] ?? ['未知', 'muted']; ?>
      <tr>
        <td class="nowrap"><?= e($r['order_no']) ?></td>
        <td>
          <?= e($r['nickname']) ?>
          <div class="muted" style="font-size:12px"><?= e($r['phone'] !== '' ? $r['phone'] : '-') ?></div>
        </td>
        <td><strong><?= e(yuan((float) $r['amount'])) ?></strong></td>
        <td><?= e($r['channel'] === 'epay' ? '在线支付' : '人工转账') ?></td>
        <td>
          <?php if ($r['pay_voucher'] !== ''): ?>
            <a href="<?= e(upload_url((string) $r['pay_voucher'])) ?>" target="_blank">查看凭证</a>
          <?php endif; ?>
          <?php if ($r['remark'] !== ''): ?>
            <div class="muted" style="font-size:12px"><?= e(str_limit((string) $r['remark'], 24)) ?></div>
          <?php endif; ?>
        </td>
        <td><span class="status status-<?= e($st[1]) ?>"><?= e($st[0]) ?></span></td>
        <td class="muted nowrap"><?= e((string) $r['created_at']) ?></td>
        <td>
          <?php if ((int) $r['status'] === 0): ?>
            <form method="post" action="<?= e(admin_url('recharge/audit')) ?>" class="inline-form">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
              <input type="text" name="remark" placeholder="备注" style="padding:5px 8px;font-size:12px;min-width:100px">
              <button class="btn btn-primary btn-sm" type="submit" name="result" value="1">确认到账</button>
              <button class="btn btn-danger btn-sm" type="submit" name="result" value="3">驳回</button>
              <button class="btn btn-ghost btn-sm" type="submit" name="result" value="2">关闭</button>
            </form>
          <?php else: ?>
            <span class="muted">
              <?= e($r['audit_remark'] !== '' ? str_limit((string) $r['audit_remark'], 16) : '-') ?>
            </span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?>
      <tr><td colspan="8" class="empty">没有符合条件的充值单</td></tr>
    <?php endif; ?>
    </tbody>
  </table>

  <?= pager($page, $total, $per_page) ?>
</div>
