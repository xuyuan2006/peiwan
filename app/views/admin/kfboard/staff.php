<?php
/**
 * 员工名下工单（从员工看板点进来）
 * @var array $actor
 * @var array $target
 * @var array $rows
 * @var array $filter
 * @var int $doing
 * @var int $done
 */
$labels = [0 => '待处理', 1 => '处理中', 2 => '已解决', 3 => '已关闭'];
?>
<p><a class="btn btn-ghost btn-sm" href="<?= e(admin_url('kf_board/index')) ?>">← 返回员工看板</a></p>

<div class="summary-row">
  <span><strong><?= e((string) $target['name']) ?></strong>（<?= e((string) $target['staff_no']) ?>）</span>
  <span><?= e(\App\KfStaff::roleText((string) $target['role'])) ?></span>
  <span>手里待办 <strong class="bad"><?= (int) $doing ?></strong></span>
  <span>已完结 <strong><?= (int) $done ?></strong></span>
</div>

<div class="tabs">
  <?php foreach ([['', '全部'], ['0', '待处理'], ['1', '处理中'], ['2', '已解决'], ['3', '已关闭']] as $item): ?>
    <a class="tab <?= (string) ($filter['status'] ?? '') === $item[0] ? 'active' : '' ?>"
       href="<?= e(admin_url('kf_board/staff', ['id' => (int) $target['id'], 'status' => $item[0]])) ?>">
      <?= e($item[1]) ?>
    </a>
  <?php endforeach; ?>
</div>

<div class="table-card">
  <?php if (!$rows): ?>
    <p class="muted" style="padding:16px">这个人名下还没有工单。</p>
  <?php else: ?>
    <table class="table">
      <thead>
        <tr><th>单号</th><th>客户</th><th>类型</th><th>标题</th><th>状态</th><th>更新</th><th>操作</th></tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $t): ?>
        <tr>
          <td class="nowrap"><?= e((string) $t['ticket_no']) ?></td>
          <td><?= e((string) ($t['user_nickname'] ?? ('#' . (int) $t['user_id']))) ?></td>
          <td><?= e(\App\TicketService::categoryText((string) $t['category'])) ?></td>
          <td>
            <?= e(str_limit((string) $t['title'], 28)) ?>
            <div class="muted" style="font-size:12px"><?= e(str_limit((string) $t['content'], 30)) ?></div>
          </td>
          <td>
            <span class="status status-<?= e(\App\TicketService::statusClass((int) $t['status'])) ?>">
              <?= e($labels[(int) $t['status']] ?? \App\TicketService::statusText((int) $t['status'])) ?>
            </span>
          </td>
          <td class="muted nowrap"><?= e((string) $t['updated_at']) ?></td>
          <td>
            <div class="cell-actions">
              <a class="btn btn-primary btn-sm" href="<?= e(admin_url('kf_desk/detail', ['id' => (int) $t['id']])) ?>">查看 / 协同处理</a>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
