<?php
/**
 * 后台评价管理
 * @var array  $rows
 * @var int    $total
 * @var int    $page
 * @var int    $per_page
 * @var string $status
 * @var int    $compid
 */
$totalPage = (int) ceil($total / max(1, $per_page));
$tabs = ['all' => '全部', '1' => '显示中', '0' => '已隐藏'];
?>
<div class="tabs">
  <?php foreach ($tabs as $key => $label): ?>
    <a class="tab <?= (string) $status === (string) $key ? 'active' : '' ?>"
       href="<?= e(admin_url('review/index', ['status' => $key])) ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</div>

<div class="table-card">
  <div class="summary-row" style="padding-top:12px">
    <span>共 <strong><?= (int) $total ?></strong> 条评价</span>
    <span class="muted">隐藏评价会同步重算该陪玩的评分与好评率</span>
  </div>
  <table class="table">
    <thead>
      <tr><th>ID</th><th>订单号</th><th>用户</th><th>陪玩</th><th>评分</th><th>内容</th><th>时间</th><th>操作</th></tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= (int) $r['id'] ?></td>
        <td class="nowrap">
          <a href="<?= e(admin_url('order/detail', ['id' => (int) $r['order_id']])) ?>"><?= e($r['order_no'] ?? ('#' . (int) $r['order_id'])) ?></a>
        </td>
        <td><?= e($r['user_nickname']) ?></td>
        <td><?= e($r['companion_nickname']) ?></td>
        <td class="nowrap"><span class="stars"><?= str_repeat('★', (int) $r['score']) ?></span></td>
        <td>
          <?= e(str_limit((string) $r['content'], 40)) ?>
          <?php if ($r['reply'] !== ''): ?>
            <div class="muted" style="font-size:12px">回复：<?= e(str_limit((string) $r['reply'], 24)) ?></div>
          <?php endif; ?>
        </td>
        <td class="muted nowrap"><?= e((string) $r['created_at']) ?></td>
        <td>
          <div class="cell-actions">
            <span class="status status-<?= (int) $r['status'] === 1 ? 'success' : 'muted' ?>"><?= (int) $r['status'] === 1 ? '显示中' : '已隐藏' ?></span>
            <form method="post" action="<?= e(admin_url('review/toggle')) ?>" class="inline-form">
              <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
              <button class="btn btn-ghost btn-sm" type="submit"><?= (int) $r['status'] === 1 ? '隐藏' : '显示' ?></button>
            </form>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?>
      <tr><td colspan="8" class="empty">暂无评价</td></tr>
    <?php endif; ?>
    </tbody>
  </table>

  <?= pager($page, $total, $per_page) ?>
</div>
