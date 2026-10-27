<?php
/**
 * 后台工单列表
 * @var array $result
 * @var array $filter
 * @var array $counts
 * @var array $categories
 */
$rows      = $result['rows'];
$total     = (int) $result['total'];
$page      = (int) $result['page'];
$perPage   = (int) $result['per_page'];
$totalPage = (int) ceil($total / max(1, $perPage));

$tabs = ['0' => '待处理', '1' => '处理中', '2' => '已解决', '3' => '已关闭', 'all' => '全部'];
?>
<div class="summary-row">
  <span>工单总数 <strong><?= (int) ($counts['all'] ?? 0) ?></strong></span>
  <span>待处理 <strong class="bad"><?= (int) ($counts['pending'] ?? 0) ?></strong></span>
</div>

<div class="tabs">
  <?php foreach ($tabs as $key => $label): ?>
    <a class="tab <?= (string) $filter['status'] === (string) $key ? 'active' : '' ?>"
       href="<?= e(admin_url('ticket/index', ['status' => $key, 'category' => $filter['category'], 'keyword' => $filter['keyword']])) ?>">
      <?= e($label) ?>
    </a>
  <?php endforeach; ?>
</div>

<form class="filter-bar" method="get" action="<?= e(admin_url('ticket/index')) ?>">
  <input type="hidden" name="r" value="ticket/index">
  <input type="hidden" name="status" value="<?= e($filter['status']) ?>">
  <label>类型
    <select name="category">
      <option value="">全部类型</option>
      <?php foreach ($categories as $key => $cat): ?>
        <option value="<?= e($key) ?>" <?= $filter['category'] === $key ? 'selected' : '' ?>><?= e($cat[0]) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>关键词
    <input type="text" name="keyword" value="<?= e($filter['keyword']) ?>" placeholder="单号 / 标题 / 用户昵称">
  </label>
  <div class="actions">
    <button class="btn btn-primary btn-sm" type="submit">查询</button>
    <a class="btn btn-ghost btn-sm" href="<?= e(admin_url('ticket/index')) ?>">重置</a>
  </div>
</form>

<div class="table-card">
  <div class="summary-row" style="padding-top:12px">
    <span>当前条件共 <strong><?= (int) $total ?></strong> 条</span>
  </div>
  <table class="table">
    <thead>
      <tr><th>单号</th><th>用户</th><th>类型</th><th>标题</th><th>状态</th><th>对话</th><th>更新时间</th><th>操作</th></tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $t): ?>
      <tr>
        <td class="nowrap"><?= e($t['ticket_no']) ?></td>
        <td>
          <?= e($t['nickname'] ?? ('#' . (int) $t['user_id'])) ?>
          <?php if (!empty($t['phone'])): ?>
            <div class="muted" style="font-size:12px"><?= e((string) $t['phone']) ?></div>
          <?php endif; ?>
        </td>
        <td><?= e(\App\TicketService::categoryText((string) $t['category'])) ?></td>
        <td>
          <?= e(str_limit((string) $t['title'], 30)) ?>
          <div class="muted" style="font-size:12px"><?= e(str_limit((string) $t['content'], 34)) ?></div>
        </td>
        <td>
          <span class="status status-<?= e(\App\TicketService::statusClass((int) $t['status'])) ?>">
            <?= e(\App\TicketService::statusText((int) $t['status'])) ?>
          </span>
        </td>
        <td><?= (int) $t['reply_count'] ?></td>
        <td class="muted nowrap"><?= e((string) $t['updated_at']) ?></td>
        <td>
          <div class="cell-actions">
            <a class="btn btn-primary btn-sm" href="<?= e(admin_url('ticket/detail', ['id' => (int) $t['id']])) ?>">
              <?= (int) $t['status'] === 0 ? '去处理' : '查看' ?>
            </a>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?>
      <tr><td colspan="8" class="empty">没有符合条件的工单</td></tr>
    <?php endif; ?>
    </tbody>
  </table>

  <?= pager($page, $total, $perPage) ?>
</div>
