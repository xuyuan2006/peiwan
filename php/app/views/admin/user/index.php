<?php
/**
 * 后台用户列表
 * @var array  $rows
 * @var int    $total
 * @var int    $page
 * @var int    $per_page
 * @var string $keyword
 * @var string $role
 * @var string $status
 */
$totalPage = (int) ceil($total / max(1, $per_page));
$roleText  = ['user' => '普通用户', 'companion' => '陪玩', 'admin' => '管理员'];
?>
<form class="filter-bar" method="get" action="<?= e(admin_url('user/index')) ?>">
  <input type="hidden" name="r" value="user/index">
  <label>关键词
    <input type="text" name="keyword" value="<?= e($keyword) ?>" placeholder="账号 / 昵称 / 手机 / ID">
  </label>
  <label>角色
    <select name="role">
      <option value="">全部</option>
      <?php foreach ($roleText as $key => $label): ?>
        <option value="<?= e($key) ?>" <?= $role === $key ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>状态
    <select name="status">
      <option value="">全部</option>
      <option value="1" <?= $status === '1' ? 'selected' : '' ?>>正常</option>
      <option value="0" <?= $status === '0' ? 'selected' : '' ?>>已禁用</option>
    </select>
  </label>
  <div class="actions">
    <button class="btn btn-primary btn-sm" type="submit">查询</button>
    <a class="btn btn-ghost btn-sm" href="<?= e(admin_url('user/index')) ?>">重置</a>
  </div>
</form>

<div class="table-card">
  <div class="summary-row" style="padding-top:12px">
    <span>共 <strong><?= (int) $total ?></strong> 个账号</span>
  </div>
  <table class="table">
    <thead>
      <tr>
        <th>用户号</th><th>用户</th><th>手机号</th><th>角色</th><th>状态</th>
        <th>余额</th><th>冻结</th><th>注册时间</th><th>操作</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $u): ?>
      <tr>
        <td>
          <?php
          // 必须和用户自己看到的号**完全一致**：客服核对时报的就是这个数字。
          // 没执行升级脚本时（no 为 0）用与回填 SQL 相同的公式现算，保证前后一致。
          $__no = (int) ($u['no'] ?? 0);
          if ($__no <= 0) {
              $__no = \App\Auth::fallbackUserNo((int) $u['id']);
          }
          ?>
          <b><?= $__no ?></b>
        </td>
        <td>
          <div style="display:flex;align-items:center;gap:8px">
            <img class="avatar avatar-sm" src="<?= e(avatar_url($u['avatar'], (string) $u['nickname'])) ?>" alt="">
            <div>
              <div><?= e($u['nickname']) ?></div>
              <div class="muted" style="font-size:12px"><?= e($u['username']) ?></div>
            </div>
          </div>
        </td>
        <td><?= e($u['phone'] !== '' ? $u['phone'] : '-') ?></td>
        <td>
          <?= e($roleText[$u['role']] ?? $u['role']) ?>
          <?php if (isset($u['audit_status']) && $u['audit_status'] !== null): ?>
            <div class="muted" style="font-size:12px">入驻：<?= e(audit_status_text((int) $u['audit_status'])) ?></div>
          <?php endif; ?>
        </td>
        <td>
          <?php if ((int) $u['status'] === 1): ?>
            <span class="status status-success">正常</span>
          <?php else: ?>
            <span class="status status-muted">已禁用</span>
          <?php endif; ?>
        </td>
        <td><?= e(money((float) ($u['balance'] ?? 0))) ?></td>
        <td class="muted"><?= e(money((float) ($u['frozen'] ?? 0))) ?></td>
        <td class="muted nowrap"><?= e((string) $u['created_at']) ?></td>
        <td>
          <div class="cell-actions">
            <a class="btn btn-ghost btn-sm" href="<?= e(admin_url('user/detail', ['id' => (int) $u['id']])) ?>">详情</a>
            <?php if ($u['role'] !== 'admin'): ?>
              <form method="post" action="<?= e(admin_url('user/toggleStatus')) ?>" class="inline-form"
                    data-confirm="确定<?= (int) $u['status'] === 1 ? '禁用' : '启用' ?>该账号吗？">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                <button class="btn btn-sm <?= (int) $u['status'] === 1 ? 'btn-danger' : 'btn-ghost' ?>" type="submit">
                  <?= (int) $u['status'] === 1 ? '禁用' : '启用' ?>
                </button>
              </form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?>
      <tr><td colspan="9" class="empty">没有符合条件的用户</td></tr>
    <?php endif; ?>
    </tbody>
  </table>

  <?= pager($page, $total, $per_page) ?>
</div>
