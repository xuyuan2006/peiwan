<?php
/**
 * 后台操作日志
 * @var array  $rows
 * @var int    $total
 * @var int    $page
 * @var int    $per_page
 * @var string $keyword
 */
$totalPage = (int) ceil($total / max(1, $per_page));
?>
<form class="filter-bar" method="get" action="<?= e(admin_url('log/index')) ?>">
  <input type="hidden" name="r" value="log/index">
  <label>关键词
    <input type="text" name="keyword" value="<?= e($keyword) ?>" placeholder="操作类型 / 详情 / 管理员昵称">
  </label>
  <div class="actions">
    <button class="btn btn-primary btn-sm" type="submit">查询</button>
    <a class="btn btn-ghost btn-sm" href="<?= e(admin_url('log/index')) ?>">重置</a>
  </div>
</form>

<div class="table-card">
  <div class="summary-row" style="padding-top:12px">
    <span>共 <strong><?= (int) $total ?></strong> 条操作记录</span>
  </div>
  <table class="table">
    <thead>
      <tr><th>ID</th><th>时间</th><th>管理员</th><th>操作</th><th>详情</th><th>IP</th></tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $l): ?>
      <tr>
        <td><?= (int) $l['id'] ?></td>
        <td class="muted nowrap"><?= e((string) $l['created_at']) ?></td>
        <td><?= e($l['nickname'] ?? ('#' . (int) $l['admin_id'])) ?></td>
        <td><?= e($l['action']) ?></td>
        <td class="muted"><?= e(str_limit((string) $l['detail'], 60)) ?></td>
        <td class="muted"><?= e($l['ip']) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?>
      <tr><td colspan="6" class="empty">暂无操作日志</td></tr>
    <?php endif; ?>
    </tbody>
  </table>

  <?= pager($page, $total, $per_page) ?>
</div>
