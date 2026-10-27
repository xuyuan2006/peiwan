<?php
/**
 * 后台广告管理（首页轮播图）
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
      <h1>广告管理</h1>
      <p class="muted">启用的广告会出现在<b>首页顶部轮播位</b>；建议图片尺寸 1200×360，多张自动轮播</p>
    </div>
    <div class="head-actions">
      <a class="btn btn-ghost" href="<?= e(admin_url('content/index')) ?>">去公告管理</a>
      <a class="btn btn-ghost" href="<?= e(url('home/index')) ?>" target="_blank">看前台效果</a>
    </div>
  </div>
</div>

<div class="admin-detail-grid">
  <section class="card">
    <h2 class="card-title"><?= $isEdit ? '编辑广告 #' . (int) $edit['id'] : '新增广告' ?></h2>
    <form method="post" action="<?= e(admin_url('content/saveBanner')) ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= $isEdit ? (int) $edit['id'] : 0 ?>">

      <div class="form-row">
        <label>广告标题</label>
        <input type="text" name="title" maxlength="100" value="<?= e($edit['title'] ?? '') ?>" placeholder="仅后台备注用，前台不显示">
      </div>

      <div class="form-row">
        <label>广告图片 <?= $isEdit ? '' : '<span class="req">*</span>' ?></label>
        <input type="file" name="image" accept="image/*">
        <?php if ($isEdit && (string) $edit['image'] !== ''): ?>
          <p class="hint">
            当前图片：<img src="<?= e(upload_url((string) $edit['image'])) ?>" alt="" style="width:150px;border-radius:8px;vertical-align:middle">
            （不重新上传则保持不变）
          </p>
        <?php else: ?>
          <p class="hint">支持 jpg / png / gif / webp，≤5MB，建议 1200×360。</p>
        <?php endif; ?>
      </div>

      <div class="form-row">
        <label>跳转链接（<b>选填</b>，不填就是纯展示图）</label>
        <input type="text" name="link_url" maxlength="255" value="<?= e($edit['link_url'] ?? '') ?>"
               placeholder="可留空；也可以只填域名，例如 www.baidu.com（会自动补 https://）">
        <p class="hint">只支持 http / https 地址，新窗口打开。</p>
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
        <button class="btn btn-primary btn-block" type="submit"><?= $isEdit ? '保存修改' : '新增广告' ?></button>
        <?php if ($isEdit): ?>
          <a class="btn btn-ghost btn-block" href="<?= e(admin_url('content/banners')) ?>">取消编辑</a>
        <?php endif; ?>
      </div>
    </form>
  </section>

  <aside>
    <section class="card">
      <h2 class="card-title">预览效果</h2>
      <?php if ($isEdit && (string) $edit['image'] !== ''): ?>
        <img src="<?= e(upload_url((string) $edit['image'])) ?>" alt="" style="width:100%;border-radius:12px">
      <?php else: ?>
        <p class="muted">选择图片上传后，前台首页顶部会以轮播形式展示（手机上高度 170px，桌面 260px，自动裁切填充）。</p>
      <?php endif; ?>
      <p class="hint" style="margin-top:10px">共 <?= (int) $total ?> 条广告，前台最多轮播 10 条。</p>
    </section>
  </aside>
</div>

<div class="table-card">
  <table class="table">
    <thead>
      <tr><th>ID</th><th>图片</th><th>标题</th><th>链接</th><th>排序</th><th>状态</th><th>操作</th></tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $b): ?>
      <tr>
        <td><?= (int) $b['id'] ?></td>
        <td>
          <img src="<?= e(upload_url((string) $b['image'])) ?>" alt="" style="width:120px;height:36px;object-fit:cover;border-radius:6px">
        </td>
        <td><?= e((string) $b['title']) ?></td>
        <td class="muted" style="font-size:12px;word-break:break-all"><?= e((string) $b['link_url']) ?></td>
        <td><?= (int) $b['sort'] ?></td>
        <td>
          <span class="status status-<?= (int) $b['status'] === 1 ? 'success' : 'muted' ?>">
            <?= (int) $b['status'] === 1 ? '启用中' : '已停用' ?>
          </span>
        </td>
        <td>
          <div class="cell-actions">
            <a class="btn btn-ghost btn-sm" href="<?= e(admin_url('content/banners', ['edit' => (int) $b['id']])) ?>">编辑</a>
            <form method="post" action="<?= e(admin_url('content/toggle')) ?>" class="inline-form">
              <?= csrf_field() ?>
              <input type="hidden" name="table" value="banners">
              <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
              <button class="btn btn-ghost btn-sm" type="submit"><?= (int) $b['status'] === 1 ? '停用' : '启用' ?></button>
            </form>
            <form method="post" action="<?= e(admin_url('content/deleteBanner')) ?>" class="inline-form" data-confirm="确定删除这条广告吗？图片文件也会一起删除。">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
              <button class="btn btn-danger btn-sm" type="submit">删除</button>
            </form>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?>
      <tr><td colspan="7" class="empty">还没有广告，左边上传一张试试</td></tr>
    <?php endif; ?>
    </tbody>
  </table>

  <?= pager($page, $total, $perPage) ?>
</div>
