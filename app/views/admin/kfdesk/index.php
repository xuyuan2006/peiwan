<?php
/**
 * 工单部门 · 工单列表
 * @var array $actor
 * @var string $scope
 * @var array $scopes
 * @var array $filter
 * @var array $result
 * @var array $counts
 */
$rows    = $result['rows'];
$total   = (int) $result['total'];
$page    = (int) $result['page'];
$per     = (int) $result['per'];
$labels  = ['pool' => '公海（未分配）', 'all' => '全部', 'mine' => '我的工单', 'group' => '本组工单'];
$cats    = \App\TicketService::categories();

// 转接对象按「组长（优先）/ 客服 · 某组」分组，避免组长和客服混在一起点错
$grouped = [];
foreach (($targets ?? []) as $tg) {
    $grouped[(string) ($tg['group'] ?? '')][] = $tg;
}
?>
<?php
$__isMgmt   = \App\KfStaff::isSuper($actor) || (string) $actor['role'] === 'manager';
$__isLeader = (string) $actor['role'] === 'leader';
?>
<div class="summary-row">
  <span>我的工单 <strong><?= (int) $counts['mine'] ?></strong></span>
  <?php if ($__isLeader): ?>
    <span>本组工单 <strong><?= (int) ($counts['group'] ?? 0) ?></strong></span>
  <?php endif; ?>
  <?php if ($__isMgmt): ?>
    <span>公海待分配 <strong class="bad"><?= (int) $counts['pool'] ?></strong></span>
    <span>进行中 <strong><?= (int) $counts['all'] ?></strong></span>
  <?php endif; ?>
  <span class="muted">当前身份：<?= e((string) $actor['name']) ?> · <?= e($actor['type'] === 'admin' ? '超级管理员' : \App\KfStaff::roleText((string) $actor['role'])) ?></span>
</div>

<div class="tabs">
  <?php foreach ($scopes as $s): ?>
    <a class="tab <?= $scope === $s ? 'active' : '' ?>"
       href="<?= e(admin_url('kf_desk/index', ['scope' => $s])) ?>"><?= e($labels[$s] ?? $s) ?></a>
  <?php endforeach; ?>
</div>

<form class="filter-bar" method="get" action="<?= e(admin_url('kf_desk/index')) ?>">
  <input type="hidden" name="r" value="kf_desk/index">
  <input type="hidden" name="scope" value="<?= e($scope) ?>">
  <label>状态
    <select name="status">
      <option value="">全部状态</option>
      <?php foreach ([0 => '待处理', 1 => '处理中', 2 => '已解决', 3 => '已关闭'] as $k => $v): ?>
        <option value="<?= $k ?>" <?= (string) $filter['status'] === (string) $k ? 'selected' : '' ?>><?= e($v) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>类型
    <select name="category">
      <option value="">全部类型</option>
      <?php foreach ($cats as $k => $c): ?>
        <option value="<?= e($k) ?>" <?= $filter['category'] === $k ? 'selected' : '' ?>><?= e($c[0]) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>关键词
    <input type="text" name="keyword" value="<?= e((string) $filter['keyword']) ?>" placeholder="单号 / 标题 / 客户昵称">
  </label>
  <label style="display:flex;gap:6px;align-items:center">
    <input type="checkbox" name="unread" value="1" <?= (string) ($filter['unread'] ?? '') === '1' ? 'checked' : '' ?>>
    只看未读
  </label>
  <div class="actions">
    <button class="btn btn-primary btn-sm" type="submit">查询</button>
    <a class="btn btn-ghost btn-sm" href="<?= e(admin_url('kf_desk/index', ['scope' => $scope])) ?>">重置</a>
  </div>
</form>

<?php if ($canAssign && $targets): ?>
  <?php /* 批量转接：勾选本页任意几张，一次性转给同一个人（经理→组长，组长→本组客服） */ ?>
  <form id="kf-batch" method="post" action="<?= e(admin_url('kf_desk/batchAssign')) ?>"
        style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin:14px 0 0;padding:12px 14px;background:#fff;border:1px solid #eceaf3;border-radius:12px">
    <?= csrf_field() ?>
    <input type="hidden" name="scope" value="<?= e($scope) ?>">
    <label style="display:flex;gap:6px;align-items:center;margin:0;font-size:13px">
      <input type="checkbox" id="ck-all"> 全选本页
    </label>
    <span class="muted" style="font-size:13px">已选 <strong id="ck-count">0</strong> 张</span>
    <select name="staff_id" required style="max-width:260px">
      <option value="">转接给…</option>
      <?php foreach ($grouped as $gname => $items): ?>
        <optgroup label="<?= e($gname) ?>">
          <?php foreach ($items as $tg): ?>
            <option value="<?= (int) $tg['id'] ?>"><?= e((string) $tg['label']) ?></option>
          <?php endforeach; ?>
        </optgroup>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-primary btn-sm" type="submit"
            onclick="return confirm('确定把勾选的工单一起转接过去？')">批量转接</button>
  </form>
<?php endif; ?>

<div class="table-card">
  <div class="summary-row" style="padding-top:12px">
    <span>共 <strong><?= $total ?></strong> 张</span>
  </div>

  <?php if (!$rows): ?>
    <p class="muted" style="padding:16px">没有工单。<?= $scope === 'pool' ? '公海是空的。' : '' ?></p>
  <?php else: ?>
    <table class="table">
      <thead>
        <tr>
          <th style="width:36px"></th>
          <th>单号</th><th>客户</th><th>类型</th><th>标题</th><th>处理人</th><th>状态</th><th>对话</th><th>更新</th><th>分配 / 转接</th><th>操作</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $t): ?>
        <tr>
          <td>
            <?php if ($canAssign): ?>
              <input type="checkbox" class="ck-row" name="ids[]" value="<?= (int) $t['id'] ?>" form="kf-batch">
            <?php endif; ?>
          </td>
          <td class="nowrap">
            <?php if (!empty($t['is_unread'])): ?>
              <span class="pill pill-warn" style="font-size:11px;padding:1px 7px;margin-right:6px">未读</span>
            <?php endif; ?>
            <?= e((string) $t['ticket_no']) ?>
          </td>
          <td>
            <?= e((string) ($t['user_nickname'] ?? ('#' . (int) $t['user_id']))) ?>
            <?php if (!empty($t['phone'])): ?>
              <div class="muted" style="font-size:12px"><?= e((string) $t['phone']) ?></div>
            <?php endif; ?>
          </td>
          <td><?= e(\App\TicketService::categoryText((string) $t['category'])) ?></td>
          <td>
            <?= e(str_limit((string) $t['title'], 28)) ?>
            <div class="muted" style="font-size:12px"><?= e(str_limit((string) $t['content'], 30)) ?></div>
          </td>
          <td>
            <?php if ((int) $t['staff_id'] > 0): ?>
              <?= e((string) ($t['staff_name'] ?? '')) ?>
            <?php else: ?>
              <span class="muted">未分配</span>
            <?php endif; ?>
          </td>
          <td>
            <span class="status status-<?= e(\App\TicketService::statusClass((int) $t['status'])) ?>">
              <?= e(\App\TicketService::statusText((int) $t['status'])) ?>
            </span>
          </td>
          <td><?= (int) $t['reply_count'] ?></td>
          <td class="muted nowrap"><?= e((string) $t['updated_at']) ?></td>
          <td>
            <?php if ($canAssign && $targets): ?>
              <form method="post" action="<?= e(admin_url('kf_desk/assign')) ?>" style="display:flex;gap:4px;align-items:center">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                <input type="hidden" name="back" value="list">
                <input type="hidden" name="scope" value="<?= e($scope) ?>">
                <select name="staff_id" style="padding:3px 6px;font-size:12px;max-width:180px">
                  <option value="">选择…</option>
                  <?php foreach ($grouped as $gname => $items): ?>
                    <optgroup label="<?= e($gname) ?>">
                      <?php foreach ($items as $tg): ?>
                        <option value="<?= (int) $tg['id'] ?>" <?= (int) $t['staff_id'] === (int) $tg['id'] ? 'selected' : '' ?>>
                          <?= e((string) $tg['label']) ?>
                        </option>
                      <?php endforeach; ?>
                    </optgroup>
                  <?php endforeach; ?>
                </select>
                <button class="btn btn-primary btn-sm" type="submit"><?= (int) $t['staff_id'] > 0 ? '转接' : '分配' ?></button>
              </form>
            <?php else: ?>
              <span class="muted">—</span>
            <?php endif; ?>
          </td>
          <td>
            <div class="cell-actions">
              <a class="btn btn-primary btn-sm" href="<?= e(admin_url('kf_desk/detail', ['id' => (int) $t['id']])) ?>">
                <?= (int) $t['status'] === 0 ? '去处理' : '查看' ?>
              </a>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>

    <?= pager($page, $total, $per) ?>
  <?php endif; ?>
</div>

<?php if ($canAssign && $targets): ?>
<script>
(function () {
  var all   = document.getElementById('ck-all');
  var count = document.getElementById('ck-count');
  var rows  = function () { return Array.prototype.slice.call(document.querySelectorAll('.ck-row')); };

  function refresh() {
    var boxes = rows();
    var picked = boxes.filter(function (b) { return b.checked; }).length;
    if (count) count.textContent = picked;
    if (all) {
      all.checked = boxes.length > 0 && picked === boxes.length;
      all.indeterminate = picked > 0 && picked < boxes.length;
    }
  }

  if (all) {
    all.addEventListener('change', function () {
      rows().forEach(function (b) { b.checked = all.checked; });
      refresh();
    });
  }
  rows().forEach(function (b) { b.addEventListener('change', refresh); });

  var batch = document.getElementById('kf-batch');
  if (batch) {
    batch.addEventListener('submit', function (e) {
      var picked = rows().filter(function (b) { return b.checked; }).length;
      if (picked === 0) {
        e.preventDefault();
        window.alert('请先勾选要转接的工单');
      }
    });
  }

  refresh();
})();
</script>
<?php endif; ?>
