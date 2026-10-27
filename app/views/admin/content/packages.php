<?php
/**
 * 后台点单套餐管理（一口价单子：普通用户直接下单，订单进接单大厅）
 * @var array      $result
 * @var array|null $edit
 * @var array      $games
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
      <h1>点单套餐</h1>
      <p class="muted">
        这里配置的套餐会出现在前台「直接点单」，<b>普通用户点一下就能下单</b>；
        付款后订单自动进入<b>接单大厅</b>，由陪玩抢单。
      </p>
    </div>
    <div class="head-actions">
      <a class="btn btn-ghost" href="<?= e(admin_url('order/index')) ?>">查看订单</a>
      <a class="btn btn-ghost" href="<?= e(url('home/packages')) ?>" target="_blank">看前台效果</a>
    </div>
  </div>
</div>

<div class="admin-detail-grid">
  <section class="card">
    <h2 class="card-title"><?= $isEdit ? '编辑套餐 #' . (int) $edit['id'] : '新增套餐' ?></h2>
    <form method="post" action="<?= e(admin_url('content/savePackage')) ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= $isEdit ? (int) $edit['id'] : 0 ?>">

      <div class="form-row">
        <label>套餐标题 <span class="req">*</span></label>
        <input type="text" name="title" maxlength="100" value="<?= e($edit['title'] ?? '') ?>"
               placeholder="例如：王者荣耀 陪玩 5 小时 / 三角洲行动 保 100 万" required>
        <p class="hint">标题会作为订单名显示给用户和陪玩，所以把「时长 / 保底」写清楚。</p>
      </div>

      <div class="form-row">
        <label>所属游戏</label>
        <select name="game_id">
          <option value="0">不指定游戏</option>
          <?php foreach ($games as $g): ?>
            <option value="<?= (int) $g['id'] ?>" <?= (int) ($edit['game_id'] ?? 0) === (int) $g['id'] ? 'selected' : '' ?>><?= e($g['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-row">
        <label>一口价（元） <span class="req">*</span></label>
        <input type="number" name="price" min="0.01" step="0.01" value="<?= e((string) ($edit['price'] ?? '')) ?>" placeholder="例如 150" required>
        <p class="hint">套餐是「一口价」，用户下单时直接按这个金额从余额扣款。</p>
      </div>

      <div class="form-row">
        <label>套餐配图</label>
        <input type="file" name="cover" accept="image/*">
        <?php if ($isEdit && (string) $edit['cover'] !== ''): ?>
          <p class="hint">
            当前配图：<img src="<?= e(upload_url((string) $edit['cover'])) ?>" alt="" style="width:140px;border-radius:8px;vertical-align:middle">
            （不重新上传则保持不变）
          </p>
        <?php else: ?>
          <p class="hint">选填，建议 640×360；支持 jpg / png / gif / webp，≤5MB。</p>
        <?php endif; ?>
      </div>

      <div class="form-row">
        <label>套餐说明</label>
        <textarea name="description" rows="3" maxlength="500" placeholder="例如：含 5 小时陪玩时间，可连麦；超时按 30 元/小时结算。"><?= e($edit['description'] ?? '') ?></textarea>
      </div>

      <div class="form-row">
        <label>排序</label>
        <input type="number" name="sort" value="<?= (int) ($edit['sort'] ?? 0) ?>">
        <p class="hint">数字越大越靠前。</p>
      </div>

      <div class="form-row">
        <label class="check">
          <input type="checkbox" name="status" value="1" <?= !$isEdit || (int) $edit['status'] === 1 ? 'checked' : '' ?>> 上架（前台可见）
        </label>
      </div>

      <div class="btn-group">
        <button class="btn btn-primary btn-block" type="submit"><?= $isEdit ? '保存修改' : '新增套餐' ?></button>
        <?php if ($isEdit): ?>
          <a class="btn btn-ghost btn-block" href="<?= e(admin_url('content/packages')) ?>">取消编辑</a>
        <?php endif; ?>
      </div>
    </form>
  </section>

  <aside>
    <section class="card">
      <h2 class="card-title">下单流程</h2>
      <ol class="tips">
        <li>用户在前台「直接点单」选套餐 → 一键下单（余额不足会先引导充值）。</li>
        <li>付款后订单状态变「待接单」，并进入<b>接单大厅</b>。</li>
        <li>陪玩在「接单」页抢单 → 服务 → 用户确认 → 平台抽成后结算给陪玩。</li>
        <li>长时间没人接单，可在订单详情里关闭并退款。</li>
      </ol>
      <p class="hint" style="margin-top:10px">共 <?= (int) $total ?> 个套餐，前台最多展示 50 个。</p>
    </section>
  </aside>
</div>

<div class="table-card">
  <table class="table">
    <thead>
      <tr><th>ID</th><th>配图</th><th>套餐标题</th><th>游戏</th><th>价格</th><th>排序</th><th>状态</th><th>操作</th></tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $p): ?>
      <tr>
        <td><?= (int) $p['id'] ?></td>
        <td>
          <?php if ((string) $p['cover'] !== ''): ?>
            <img src="<?= e(upload_url((string) $p['cover'])) ?>" alt="" style="width:76px;height:44px;object-fit:cover;border-radius:6px">
          <?php else: ?>
            <span class="muted" style="font-size:12px">无图</span>
          <?php endif; ?>
        </td>
        <td>
          <?= e((string) $p['title']) ?>
          <?php if (trim((string) $p['description']) !== ''): ?>
            <div class="muted" style="font-size:12px"><?= e(str_limit((string) $p['description'], 30)) ?></div>
          <?php endif; ?>
        </td>
        <td><?= e((string) ($p['game_name'] ?? '-')) ?></td>
        <td><strong><?= e(yuan((float) $p['price'])) ?></strong></td>
        <td><?= (int) $p['sort'] ?></td>
        <td>
          <span class="status status-<?= (int) $p['status'] === 1 ? 'success' : 'muted' ?>">
            <?= (int) $p['status'] === 1 ? '上架中' : '已下架' ?>
          </span>
        </td>
        <td>
          <div class="cell-actions">
            <a class="btn btn-ghost btn-sm" href="<?= e(admin_url('content/packages', ['edit' => (int) $p['id']])) ?>">编辑</a>
            <form method="post" action="<?= e(admin_url('content/toggle')) ?>" class="inline-form">
              <?= csrf_field() ?>
              <input type="hidden" name="table" value="order_packages">
              <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
              <button class="btn btn-ghost btn-sm" type="submit"><?= (int) $p['status'] === 1 ? '下架' : '上架' ?></button>
            </form>
            <form method="post" action="<?= e(admin_url('content/deletePackage')) ?>" class="inline-form" data-confirm="确定删除该套餐吗？图片文件也会一起删除。">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
              <button class="btn btn-danger btn-sm" type="submit">删除</button>
            </form>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?>
      <tr><td colspan="8" class="empty">还没有套餐，左边加一个试试（例如「王者荣耀 陪玩 5 小时」150 元）</td></tr>
    <?php endif; ?>
    </tbody>
  </table>

  <?= pager($page, $total, $perPage) ?>
</div>
