<?php
/**
 * 技能管理
 * @var array      $skills
 * @var array      $games
 * @var array|null $edit
 */
$types = service_type_map();
?>
<div class="page-head">
  <div class="head-row">
    <div>
      <h1>技能管理</h1>
      <p class="muted">上架服务项目后，用户即可下单预约你</p>
    </div>
    <div class="head-actions">
      <a class="btn btn-ghost" href="<?= e(url('companion/center')) ?>">返回陪玩中心</a>
    </div>
  </div>
</div>

<div class="detail-grid">
  <section class="card">
    <h2 class="card-title"><?= $edit ? '编辑技能' : '新增技能' ?></h2>
    <form method="post" action="<?= e(url('companion/skillSave')) ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">

      <div class="form-row">
        <label>游戏 <span class="req">*</span></label>
        <select name="game_id" required>
          <option value="">请选择游戏</option>
          <?php foreach ($games as $g): ?>
            <option value="<?= (int) $g['id'] ?>" <?= (int) ($edit['game_id'] ?? 0) === (int) $g['id'] ? 'selected' : '' ?>>
              <?= e($g['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-row">
        <label>服务标题 <span class="req">*</span></label>
        <input type="text" name="title" maxlength="100" value="<?= e($edit['title'] ?? '') ?>" placeholder="例如：王者荣耀 星耀上王者 陪练" required>
      </div>
      <div class="form-row">
        <label>服务类型 <span class="req">*</span></label>
        <select name="service_type" required>
          <?php foreach ($types as $key => $label): ?>
            <option value="<?= e($key) ?>" <?= ($edit['service_type'] ?? 'hour') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-row">
        <label>段位 / 水平</label>
        <input type="text" name="rank_level" maxlength="50" value="<?= e($edit['rank_level'] ?? '') ?>" placeholder="例如：王者 50 星 / 百星打野">
      </div>

      <div class="form-row">
        <label>服务配图</label>
        <input type="file" name="cover" accept="image/*">
        <?php if (!empty($edit['cover'])): ?>
          <p class="hint">
            当前配图：
            <img src="<?= e(upload_url((string) $edit['cover'])) ?>" alt="" style="width:72px;border-radius:8px;vertical-align:middle">
            （不重新选择则保持这张）
          </p>
        <?php else: ?>
          <p class="hint">选填，会显示在你的服务项目上，更容易被点单。</p>
        <?php endif; ?>
      </div>
      <div class="form-row">
        <label>价格（元） <span class="req">*</span></label>
        <input type="number" name="price" min="0.01" step="0.01" value="<?= e($edit['price'] ?? '') ?>" placeholder="按所选服务类型计价" required>
      </div>
      <div class="form-row">
        <label>服务说明</label>
        <textarea name="description" rows="3" maxlength="500" placeholder="服务内容、注意事项、可约时间段等"><?= e($edit['description'] ?? '') ?></textarea>
      </div>

      <button class="btn btn-primary" type="submit">保存</button>
      <?php if ($edit): ?>
        <a class="btn btn-ghost" href="<?= e(url('companion/skills')) ?>">取消编辑</a>
      <?php endif; ?>
    </form>
  </section>

  <aside class="detail-side">
    <section class="card">
      <h2 class="card-title">上架建议</h2>
      <ol class="tips">
        <li>价格建议参考同游戏同段位市场价，过高会影响转化。</li>
        <li>标题写清楚游戏 + 段位 + 服务内容，更容易被搜索到。</li>
        <li>可以同时上架多个服务，覆盖不同游戏与价位。</li>
        <li>下架的服务不会出现在用户端，但历史订单不受影响。</li>
      </ol>
    </section>
  </aside>
</div>

<section class="card">
  <h2 class="card-title">我的服务（<?= count($skills) ?>）</h2>
  <?php if (!$skills): ?>
    <div class="empty">还没有上架服务</div>
  <?php else: ?>
    <table class="table">
      <thead>
        <tr><th>游戏</th><th>标题</th><th>类型</th><th>段位</th><th>价格</th><th>状态</th><th>操作</th></tr>
      </thead>
      <tbody>
      <?php foreach ($skills as $s): ?>
        <tr>
          <td><?= e($s['game_name'] ?? '-') ?></td>
          <td>
            <?php if (!empty($s['cover'])): ?>
              <img src="<?= e(upload_url((string) $s['cover'])) ?>" alt="" style="width:42px;height:42px;border-radius:8px;object-fit:cover;vertical-align:middle;margin-right:8px">
            <?php endif; ?>
            <?= e($s['title']) ?>
          </td>
          <td><?= e(service_type_text((string) $s['service_type'])) ?></td>
          <td><?= e($s['rank_level'] !== '' ? $s['rank_level'] : '-') ?></td>
          <td><?= e(yuan((float) $s['price'])) ?> / <?= e(service_unit_suffix((string) $s['service_type'])) ?></td>
          <td>
            <?php if ((int) $s['status'] === 1): ?>
              <span class="status status-success">已上架</span>
            <?php else: ?>
              <span class="status status-muted">已下架</span>
            <?php endif; ?>
          </td>
          <td class="row-actions">
            <a class="btn btn-ghost btn-sm" href="<?= e(url('companion/skills', ['edit' => (int) $s['id']])) ?>">编辑</a>
            <form method="post" action="<?= e(url('companion/skillToggle')) ?>" class="inline-form">
              <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
              <button class="btn btn-ghost btn-sm" type="submit"><?= (int) $s['status'] === 1 ? '下架' : '上架' ?></button>
            </form>
            <form method="post" action="<?= e(url('companion/skillDelete')) ?>" class="inline-form" data-confirm="确定删除该技能吗？">
              <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
              <button class="btn btn-danger btn-sm" type="submit">删除</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>
