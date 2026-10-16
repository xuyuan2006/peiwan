<?php
/**
 * 后台公告管理（前台顶部走马灯滚动）
 * @var array      $result
 * @var array|null $edit
 */
$rows      = $result['rows'];
$total     = (int) $result['total'];
$page      = (int) $result['page'];
$perPage   = (int) $result['per_page'];
$totalPage = (int) ceil($total / max(1, $perPage));
$isEdit    = !empty($edit);
?>
<div class="page-head">
  <div class="head-row">
    <div>
      <h1>公告管理</h1>
      <p class="muted">启用的公告会在前台顶部<b>走马灯滚动</b>展示（最多取 20 条，按排序值从大到小）</p>
    </div>
    <div class="head-actions">
      <a class="btn btn-ghost" href="<?= e(admin_url('content/banners')) ?>">去广告管理</a>
      <a class="btn btn-ghost" href="<?= e(url('home/index')) ?>" target="_blank">看前台效果</a>
    </div>
  </div>
</div>

<div class="admin-detail-grid">
  <section class="card">
    <h2 class="card-title"><?= $isEdit ? '编辑公告 #' . (int) $edit['id'] : '新增公告' ?></h2>
    <form method="post" action="<?= e(admin_url('content/saveAnnouncement')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= $isEdit ? (int) $edit['id'] : 0 ?>">

      <div class="form-row">
        <label>公告内容 <span class="req">*</span></label>
        <textarea name="content" rows="2" maxlength="200" placeholder="例如：新用户首单立减 5 元，活动截至本月底" required><?= e($edit['content'] ?? '') ?></textarea>
        <p class="hint">建议控制在一行内（≤200 字），太长前台会省略号截断。</p>
      </div>

      <div class="form-row">
        <label>跳转链接（选填）</label>
        <input type="text" name="link_url" maxlength="255" value="<?= e($edit['link_url'] ?? '') ?>" placeholder="https://... 填了点击公告就会跳转">
      </div>

      <div class="form-row">
        <label>排序</label>
        <input type="number" name="sort" value="<?= (int) ($edit['sort'] ?? 0) ?>">
        <p class="hint">数字越大越靠前。</p>
      </div>

      <div class="form-row">
        <label class="check">
          <input type="checkbox" name="status" value="1" <?= !$isEdit || (int) $edit['status'] === 1 ? 'checked' : '' ?>> 启用
        </label>
      </div>

      <div class="btn-group">
        <button class="btn btn-primary btn-block" type="submit"><?= $isEdit ? '保存修改' : '新增公告' ?></button>
        <?php if ($isEdit): ?>
          <a class="btn btn-ghost btn-block" href="<?= e(admin_url('content/index')) ?>">取消编辑</a>
        <?php endif; ?>
      </div>
    </form>
  </section>

  <aside>
    <section class="card">
      <h2 class="card-title">公告列表</h2>
      <p class="muted">共 <?= (int) $total ?> 条</p>
    </section>
  </aside>
</div>

<div class="table-card">
  <table class="table">
    <thead>
      <tr><th>ID</th><th>内容</th><th>链接</th><th>排序</th><th>状态</th><th>更新时间</th><th>操作</th></tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $a): ?>
      <tr>
        <td><?= (int) $a['id'] ?></td>
        <td><?= e((string) $a['content']) ?></td>
        <td class="muted" style="font-size:12px;word-break:break-all"><?= e((string) $a['link_url']) ?></td>
        <td><?= (int) $a['sort'] ?></td>
        <td>
          <span class="status status-<?= (int) $a['status'] === 1 ? 'success' : 'muted' ?>">
            <?= (int) $a['status'] === 1 ? '启用中' : '已停用' ?>
          </span>
        </td>
        <td class="muted nowrap"><?= e((string) $a['updated_at']) ?></td>
        <td>
          <div class="cell-actions">
            <a class="btn btn-ghost btn-sm" href="<?= e(admin_url('content/index', ['edit' => (int) $a['id']])) ?>">编辑</a>
            <form method="post" action="<?= e(admin_url('content/toggle')) ?>" class="inline-form">
              <?= csrf_field() ?>
              <input type="hidden" name="table" value="announcements">
              <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
              <button class="btn btn-ghost btn-sm" type="submit"><?= (int) $a['status'] === 1 ? '停用' : '启用' ?></button>
            </form>
            <form method="post" action="<?= e(admin_url('content/deleteAnnouncement')) ?>" class="inline-form" data-confirm="确定删除这条公告吗？">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
              <button class="btn btn-danger btn-sm" type="submit">删除</button>
            </form>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?>
      <tr><td colspan="7" class="empty">还没有公告，左边新增一条试试</td></tr>
    <?php endif; ?>
    </tbody>
  </table>

  <?= pager($page, $total, $perPage) ?>
</div>
