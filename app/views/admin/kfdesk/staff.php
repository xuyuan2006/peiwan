<?php
/**
 * 工单部门 · 账号列表
 * @var array $actor
 * @var array $filter
 * @var array $result
 * @var array $roles
 * @var array $leaders
 * @var bool  $canCreate
 */
$rows  = $result['rows'];
$total = (int) $result['total'];
$page  = (int) $result['page'];
$per   = (int) $result['per'];
$isSuper    = \App\KfStaff::isSuper($actor);
$canToggle  = $isSuper || $actor['role'] === 'manager';
$leaderOnly = !empty($leaderOnly);
$canEditAny = (bool) \App\KfStaff::creatableRoles($actor);
?>
<div class="summary-row">
  <span>工单部门账号 <strong><?= $total ?></strong></span>
  <span class="muted">超管管理全部；主管管理组长和客服；组长只能重置本组客服密码</span>
  <?php if ($canCreate): ?>
    <a class="btn btn-primary btn-sm" href="<?= e(admin_url('kf_staff/form')) ?>" style="margin-left:auto">＋ 新建账号</a>
  <?php endif; ?>
</div>

<form class="filter-bar" method="get" action="<?= e(admin_url('kf_staff/index')) ?>">
  <input type="hidden" name="r" value="kf_staff/index">
  <label>角色
    <select name="role" <?= $leaderOnly ? 'disabled' : '' ?>>
      <option value="">全部角色</option>
      <?php foreach ($roles as $k => $v): ?>
        <option value="<?= e($k) ?>" <?= (string) $filter['role'] === $k ? 'selected' : '' ?>><?= e($v) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>状态
    <select name="status">
      <option value="">全部</option>
      <option value="1" <?= (string) $filter['status'] === '1' ? 'selected' : '' ?>>正常</option>
      <option value="0" <?= (string) $filter['status'] === '0' ? 'selected' : '' ?>>已封禁</option>
    </select>
  </label>
  <label>关键词
    <input type="text" name="keyword" value="<?= e((string) $filter['keyword']) ?>" placeholder="工号 / 账号 / 姓名 / 手机">
  </label>
  <div class="actions">
    <button class="btn btn-primary btn-sm" type="submit">查询</button>
    <a class="btn btn-ghost btn-sm" href="<?= e(admin_url('kf_staff/index')) ?>">重置</a>
  </div>
</form>

<div class="table-card">
  <?php if (!$rows): ?>
    <p class="muted" style="padding:16px">还没有工单部门账号，点右上角「新建账号」。</p>
  <?php else: ?>
    <table class="table">
      <thead>
        <tr><th>工号</th><th>账号</th><th>姓名</th><th>角色</th><th>所属</th><th>手机</th><th>邮箱</th><th>状态</th><th>最近登录</th><th>操作</th></tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $u): ?>
        <?php $can = \App\KfStaff::canManage($actor, $u); ?>
        <tr>
          <td class="nowrap"><strong><?= e((string) $u['staff_no']) ?></strong></td>
          <td><?= e((string) $u['account']) ?></td>
          <td><?= e((string) $u['name']) ?></td>
          <td><?= e(\App\KfStaff::roleText((string) $u['role'])) ?></td>
          <td>
            <?php
            // 组长挂在经理下面，客服挂在组长下面
            $ownerPrefix = '';
            $ownerName   = '';
            if ((string) $u['role'] === 'leader') {
                $ownerPrefix = '经理：';
                $ownerName   = (string) ($u['manager_name'] ?? '');
            } elseif ((string) $u['role'] === 'agent') {
                $ownerPrefix = '组长：';
                $ownerName   = (string) ($u['leader_name'] ?? '');
            }
            ?>
            <?php if ($ownerName !== ''): ?>
              <span class="muted" style="font-size:12px"><?= e($ownerPrefix) ?></span><?= e($ownerName) ?>
            <?php else: ?>
              <span class="muted">—</span>
            <?php endif; ?>
          </td>
          <td class="nowrap"><?= e((string) $u['phone']) ?></td>
          <td class="nowrap"><?= e((string) $u['email']) ?></td>
          <td>
            <span class="status status-<?= (int) $u['status'] === 1 ? 'success' : 'muted' ?>">
              <?= (int) $u['status'] === 1 ? '正常' : '已停用' ?>
            </span>
          </td>
          <td class="muted nowrap"><?= e((string) ($u['last_login_at'] ?? '—')) ?></td>
          <td>
            <div class="cell-actions">
              <?php
              $can      = \App\KfStaff::canManage($actor, $u);
              $canEdit  = $can && $canEditAny;
              $canBan   = $canToggle
                  || ((string) $actor['role'] === 'leader'
                      && (string) $u['role'] === 'agent'
                      && (int) $u['leader_id'] === (int) $actor['id']);
              ?>
              <?php if ($can): ?>
                <?php if ($canEdit): ?>
                  <a class="btn btn-ghost btn-sm" href="<?= e(admin_url('kf_staff/form', ['id' => (int) $u['id']])) ?>">编辑</a>
                <?php endif; ?>

                <form method="post" action="<?= e(admin_url('kf_staff/reset')) ?>" style="display:inline-flex;gap:4px">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                  <input type="text" name="password" placeholder="新密码(可空)" style="width:110px;padding:4px 6px;font-size:12px">
                  <button class="btn btn-ghost btn-sm" type="submit">重置密码</button>
                </form>

                <?php if ($canBan): ?>
                  <form method="post" action="<?= e(admin_url('kf_staff/toggle')) ?>" style="display:inline"
                        onsubmit="return confirm('确定<?= (int) $u['status'] === 1 ? '封禁' : '解封' ?>这个账号？')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                    <button class="btn btn-ghost btn-sm" type="submit"><?= (int) $u['status'] === 1 ? '封禁' : '解封' ?></button>
                  </form>
                <?php endif; ?>
              <?php else: ?>
                <span class="muted" style="font-size:12px">—</span>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>

    <?= pager($page, $total, $per) ?>
  <?php endif; ?>
</div>
