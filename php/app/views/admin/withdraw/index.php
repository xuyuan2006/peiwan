<?php
/**
 * 后台提现审核
 * @var array  $rows
 * @var int    $total
 * @var int    $page
 * @var int    $per_page
 * @var string $status
 * @var string $keyword
 * @var array  $sum
 */
$totalPage = (int) ceil($total / max(1, $per_page));
$tabs = ['0' => '待审核', '1' => '已打款', '2' => '已驳回', 'all' => '全部'];
$statusMap = [0 => ['待审核', 'warn'], 1 => ['已打款', 'success'], 2 => ['已驳回', 'muted']];
$accountText = ['alipay' => '支付宝', 'wechat' => '微信', 'bank' => '银行卡'];
?>
<div class="summary-row">
  <span>待审核 <strong><?= (int) ($sum['pending_count'] ?? 0) ?></strong> 笔，合计 <?= e(yuan((float) ($sum['pending_amount'] ?? 0))) ?></span>
  <span>累计打款 <strong><?= e(yuan((float) ($sum['paid_amount'] ?? 0))) ?></strong></span>
</div>

<div class="tabs">
  <?php foreach ($tabs as $key => $label): ?>
    <a class="tab <?= $status === (string) $key ? 'active' : '' ?>"
       href="<?= e(admin_url('withdraw/index', ['status' => $key])) ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</div>

<form class="filter-bar" method="get" action="<?= e(admin_url('withdraw/index')) ?>">
  <input type="hidden" name="r" value="withdraw/index">
  <input type="hidden" name="status" value="<?= e($status) ?>">
  <label>关键词
    <input type="text" name="keyword" value="<?= e($keyword) ?>" placeholder="单号 / 用户 / 收款人 / 收款账号">
  </label>
  <div class="actions">
    <button class="btn btn-primary btn-sm" type="submit">查询</button>
    <a class="btn btn-ghost btn-sm" href="<?= e(admin_url('withdraw/index')) ?>">重置</a>
  </div>
</form>

<div class="table-card">
  <div class="summary-row" style="padding-top:12px;align-items:center">
    <span>共 <strong><?= (int) $total ?></strong> 条</span>
    <a class="btn btn-ghost btn-sm" href="<?= e(admin_url('withdraw/export', array_filter([
        'status'  => $status,
        'keyword' => $keyword,
    ]))) ?>">导出 CSV（打款清单，最多 5000 条）</a>
  </div>
  <table class="table">
    <thead>
      <tr>
        <th>单号</th><th>用户</th><th>金额</th><th>手续费</th><th>实际到账</th>
        <th>收款信息</th><th>状态</th><th>提交时间</th><th>操作</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $w): ?>
      <?php $st = $statusMap[(int) $w['status']] ?? ['未知', 'muted']; ?>
      <tr>
        <td class="nowrap"><?= e($w['order_no']) ?></td>
        <td>
          <?= e($w['nickname']) ?>
          <div class="muted" style="font-size:12px"><?= e($w['phone'] !== '' ? $w['phone'] : '-') ?></div>
        </td>
        <td><strong><?= e(yuan((float) $w['amount'])) ?></strong></td>
        <td class="muted"><?= e(money((float) $w['fee'])) ?></td>
        <td><?= e(yuan((float) $w['real_amount'])) ?></td>
        <td>
          <?= e($accountText[$w['account_type']] ?? $w['account_type']) ?>
          <div class="muted" style="font-size:12px"><?= e($w['account_name']) ?> · <?= e($w['account_no']) ?></div>
          <div class="cell-actions" style="margin-top:4px">
            <button type="button" class="btn btn-ghost btn-sm" onclick="navigator.clipboard&&navigator.clipboard.writeText('<?= e($w['account_no']) ?>')">复制账号</button>
          </div>
        </td>
        <td><span class="status status-<?= e($st[1]) ?>"><?= e($st[0]) ?></span>
          <?php if ($w['audit_remark'] !== ''): ?>
            <div class="muted" style="font-size:12px"><?= e(str_limit((string) $w['audit_remark'], 16)) ?></div>
          <?php endif; ?>
        </td>
        <td class="muted nowrap"><?= e((string) $w['created_at']) ?></td>
        <td>
          <?php if ((int) $w['status'] === 0): ?>
            <form method="post" action="<?= e(admin_url('withdraw/audit')) ?>" class="inline-form">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $w['id'] ?>">
              <input type="text" name="remark" placeholder="备注/打款流水号" style="padding:5px 8px;font-size:12px;min-width:120px">
              <button class="btn btn-primary btn-sm" type="submit" name="result" value="1"
                      data-confirm="确认已线下打款给用户？确认后将扣减冻结金额。">已打款</button>
              <button class="btn btn-danger btn-sm" type="submit" name="result" value="2"
                      data-confirm="驳回后金额会解冻回用户可用余额，确定吗？">驳回</button>
            </form>
          <?php else: ?>
            <span class="muted"><?= e((string) ($w['audit_at'] ?: '-')) ?></span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?>
      <tr><td colspan="9" class="empty">没有符合条件的提现单</td></tr>
    <?php endif; ?>
    </tbody>
  </table>

  <?= pager($page, $total, $per_page) ?>
</div>
