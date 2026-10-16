<?php
/**
 * 资金流水
 * @var array $rows
 * @var int   $total
 * @var int   $page
 * @var int   $per_page
 */
$totalPage = (int) ceil($total / max(1, $per_page));
?>
<div class="page-head">
  <h1>资金流水</h1>
  <p class="muted">共 <?= (int) $total ?> 条记录</p>
</div>

<section class="card">
  <?php if (!$rows): ?>
    <div class="empty">还没有资金记录</div>
  <?php else: ?>
    <table class="table">
      <thead>
        <tr><th>时间</th><th>类型</th><th>收支</th><th>金额</th><th>余额变动</th><th>备注</th></tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $t): ?>
        <tr>
          <td class="muted"><?= e((string) $t['created_at']) ?></td>
          <td><?= e(wallet_type_text((string) $t['type'])) ?></td>
          <td><?= (int) $t['direction'] === 1 ? '<span class="text-success">收入</span>' : '<span class="text-danger">支出</span>' ?></td>
          <td><?= e(money((float) $t['amount'])) ?></td>
          <td class="muted"><?= e(money((float) $t['balance_before'])) ?> → <?= e(money((float) $t['balance_after'])) ?></td>
          <td class="muted"><?= e(str_limit((string) $t['remark'], 40)) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>

<?= pager($page, $total, $per_page) ?>
