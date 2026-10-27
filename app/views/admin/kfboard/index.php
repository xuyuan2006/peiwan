<?php
/**
 * 工单部门 · 员工看板
 * @var array $actor
 * @var array $rows
 * @var int $doing
 * @var int $done
 * @var int $pool
 */
$isManager = \App\KfStaff::isSuper($actor) || (string) $actor['role'] === 'manager';
?>
<div class="summary-row">
  <span>团队人数 <strong><?= count($rows) ?></strong></span>
  <span>手里待办 <strong class="bad"><?= (int) $doing ?></strong></span>
  <span>已完结 <strong><?= (int) $done ?></strong></span>
  <?php if ($isManager): ?>
    <span>公海待分配 <strong class="bad"><?= (int) $pool ?></strong></span>
  <?php endif; ?>
  <span class="muted">
    当前身份：<?= e((string) $actor['name']) ?> ·
    <?= e($actor['type'] === 'admin' ? '超级管理员' : \App\KfStaff::roleText((string) $actor['role'])) ?>
  </span>
</div>

<div class="table-card">
  <?php if (!$rows): ?>
    <p class="muted" style="padding:16px">
      范围内还没有账号。<?= $isManager ? '先去「工单账号」建组长和客服，并把组长挂到你名下。' : '' ?>
    </p>
  <?php else: ?>
    <table class="table">
      <thead>
        <tr>
          <th>工号</th>
          <th>姓名</th>
          <th>角色</th>
          <th>归属</th>
          <th>手里待办</th>
          <th>已完结</th>
          <th>合计</th>
          <th>账号状态</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td class="nowrap"><strong><?= e((string) $r['staff_no']) ?></strong></td>
          <td>
            <a href="<?= e(admin_url('kf_board/staff', ['id' => (int) $r['id']])) ?>"><strong><?= e((string) $r['name']) ?></strong></a>
          </td>
          <td><?= e(\App\KfStaff::roleText((string) $r['role'])) ?></td>
          <td>
            <?php
            $owner = '';
            if ((string) $r['role'] === 'leader') {
                $owner = (string) ($r['manager_name'] ?? '');
                $owner = $owner !== '' ? '经理：' . $owner : '';
            } elseif ((string) $r['role'] === 'agent') {
                $owner = (string) ($r['leader_name'] ?? '');
                $owner = $owner !== '' ? '组长：' . $owner : '';
            }
            ?>
            <?= $owner !== '' ? e($owner) : '<span class="muted">—</span>' ?>
          </td>
          <td>
            <?php if ((int) $r['doing'] > 0): ?>
              <strong class="bad"><?= (int) $r['doing'] ?></strong>
            <?php else: ?>
              <span class="muted">0</span>
            <?php endif; ?>
          </td>
          <td><?= (int) $r['done'] ?></td>
          <td class="muted"><?= (int) $r['total'] ?></td>
          <td>
            <span class="status status-<?= (int) $r['status'] === 1 ? 'success' : 'muted' ?>">
              <?= (int) $r['status'] === 1 ? '正常' : '已停用' ?>
            </span>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>

    <p class="hint" style="padding:12px 16px 16px">
      「手里待办」= 待处理 + 处理中；「已完结」= 已解决 + 已关闭。想让某人手里的单转走，去「工单处理」勾选后批量转接。
    </p>
  <?php endif; ?>
</div>
