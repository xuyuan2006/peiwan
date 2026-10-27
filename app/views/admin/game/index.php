<?php
/**
 * 后台游戏管理
 * @var array      $rows
 * @var array|null $edit
 */
?>
<div class="admin-detail-grid">
  <section class="table-card">
    <div class="card-head" style="padding-top:12px">
      <h2 class="card-title">游戏列表（<?= count($rows) ?>）</h2>
    </div>
    <table class="table">
      <thead>
        <tr><th>ID</th><th>名称</th><th>排序</th><th>热门</th><th>状态</th><th>在售技能</th><th>操作</th></tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $g): ?>
        <tr>
          <td><?= (int) $g['id'] ?></td>
          <td>
            <?= e($g['name']) ?>
            <?php if ($g['icon'] !== ''): ?>
              <div class="muted" style="font-size:12px"><?= e(str_limit((string) $g['icon'], 30)) ?></div>
            <?php endif; ?>
          </td>
          <td><?= (int) $g['sort'] ?></td>
          <td><?= (int) $g['is_hot'] === 1 ? '<span class="badge badge-primary">热门</span>' : '-' ?></td>
          <td><?= (int) $g['status'] === 1 ? '<span class="status status-success">启用</span>' : '<span class="status status-muted">停用</span>' ?></td>
          <td><?= (int) $g['skill_count'] ?></td>
          <td>
            <div class="cell-actions">
              <a class="btn btn-ghost btn-sm" href="<?= e(admin_url('game/index', ['edit' => (int) $g['id']])) ?>">编辑</a>
              <form method="post" action="<?= e(admin_url('game/toggle')) ?>" class="inline-form">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
                <button class="btn btn-ghost btn-sm" type="submit"><?= (int) $g['status'] === 1 ? '停用' : '启用' ?></button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?>
        <tr><td colspan="7" class="empty">还没有游戏，请先在右侧添加</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </section>

  <aside>
    <section class="card">
      <h2 class="card-title"><?= $edit ? '编辑游戏' : '新增游戏' ?></h2>
      <form method="post" action="<?= e(admin_url('game/save')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
        <div class="form-row">
          <label>游戏名称 <span class="req">*</span></label>
          <input type="text" name="name" maxlength="50" value="<?= e($edit['name'] ?? '') ?>" placeholder="例如：王者荣耀" required>
        </div>
        <div class="form-row">
          <label>图标地址</label>
          <input type="text" name="icon" maxlength="250" value="<?= e($edit['icon'] ?? '') ?>" placeholder="可填图片 URL，选填">
        </div>
        <div class="form-row">
          <label>排序值（越大越靠前）</label>
          <input type="number" name="sort" value="<?= (int) ($edit['sort'] ?? 0) ?>">
        </div>
        <div class="form-row">
          <label class="check"><input type="checkbox" name="is_hot" value="1" <?= (int) ($edit['is_hot'] ?? 0) === 1 ? 'checked' : '' ?>> 标记为热门</label>
        </div>
        <button class="btn btn-primary btn-block" type="submit">保存</button>
        <?php if ($edit): ?>
          <a class="btn btn-ghost btn-block" href="<?= e(admin_url('game/index')) ?>" style="margin-top:8px">取消编辑</a>
        <?php endif; ?>
      </form>
      <p class="hint">停用游戏后，用户端筛选不再显示，但已有技能与订单不受影响。</p>
    </section>
  </aside>
</div>
