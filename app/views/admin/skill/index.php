<?php
/**
 * 后台技能管理
 * @var array      $rows
 * @var int        $total
 * @var int        $page
 * @var int        $per_page
 * @var string     $keyword
 * @var int        $game_id
 * @var string     $status
 * @var array      $games
 * @var array      $sum
 * @var array      $companions 已通过审核的陪玩
 * @var array|null $edit       正在编辑的技能
 */
$totalPage = (int) ceil($total / max(1, $per_page));
?>
<div class="admin-detail-grid">
  <section class="table-card">
    <div class="card-head" style="padding-top:12px">
      <h2 class="card-title">服务项目（<?= (int) ($sum['total'] ?? 0) ?>）</h2>
      <span class="muted" style="font-size:13px">
        上架中 <?= (int) ($sum['online'] ?? 0) ?> · 已下架 <?= (int) ($sum['offline'] ?? 0) ?>
      </span>
    </div>

    <form class="filter-bar" method="get" action="<?= e(admin_url('skill/index')) ?>" style="margin-top:6px">
      <input type="hidden" name="r" value="skill/index">
      <label>关键词
        <input type="text" name="keyword" value="<?= e($keyword) ?>" placeholder="技能标题 / 陪玩昵称 / 段位 / ID">
      </label>
      <label>游戏
        <select name="game_id">
          <option value="0">全部游戏</option>
          <?php foreach ($games as $g): ?>
            <option value="<?= (int) $g['id'] ?>" <?= $game_id === (int) $g['id'] ? 'selected' : '' ?>><?= e($g['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>状态
        <select name="status">
          <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>全部</option>
          <option value="1" <?= $status === '1' ? 'selected' : '' ?>>上架中</option>
          <option value="0" <?= $status === '0' ? 'selected' : '' ?>>已下架</option>
        </select>
      </label>
      <div class="actions">
        <button class="btn btn-primary btn-sm" type="submit">查询</button>
        <a class="btn btn-ghost btn-sm" href="<?= e(admin_url('skill/index')) ?>">重置</a>
      </div>
    </form>

    <div style="overflow-x:auto">
      <table class="table">
        <thead>
          <tr>
            <th>ID</th><th>陪玩</th><th>游戏</th><th>技能标题</th><th>类型</th>
            <th>段位</th><th>价格</th><th>订单数</th><th>状态</th><th>操作</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $s): ?>
          <tr>
            <td><?= (int) $s['id'] ?></td>
            <td>
              <a href="<?= e(admin_url('user/detail', ['id' => (int) $s['companion_id']])) ?>"><?= e($s['nickname'] ?? ('#' . (int) $s['companion_id'])) ?></a>
              <?php if (isset($s['user_status']) && (int) $s['user_status'] !== 1): ?>
                <span class="badge">账号已禁用</span>
              <?php endif; ?>
            </td>
            <td><?= e($s['game_name'] ?? '-') ?></td>
            <td>
              <?php if (!empty($s['cover'])): ?>
                <img src="<?= e(upload_url((string) $s['cover'])) ?>" alt="" style="width:42px;height:42px;border-radius:8px;object-fit:cover;vertical-align:middle;margin-right:8px">
              <?php endif; ?>
              <?= e($s['title']) ?>
              <?php if ($s['description'] !== ''): ?>
                <div class="muted" style="font-size:12px"><?= e(str_limit((string) $s['description'], 26)) ?></div>
              <?php endif; ?>
            </td>
            <td><?= e(service_type_text((string) $s['service_type'])) ?></td>
            <td><?= e($s['rank_level'] !== '' ? $s['rank_level'] : '-') ?></td>
            <td><?= e(yuan((float) $s['price'])) ?> / <?= e(service_unit_suffix((string) $s['service_type'])) ?></td>
            <td><?= (int) $s['order_count'] ?></td>
            <td>
              <?= (int) $s['status'] === 1 ? '<span class="status status-success">上架中</span>' : '<span class="status status-muted">已下架</span>' ?>
            </td>
            <td>
              <div class="cell-actions">
                <a class="btn btn-ghost btn-sm" href="<?= e(admin_url('skill/index', ['edit' => (int) $s['id']])) ?>">编辑</a>
                <form method="post" action="<?= e(admin_url('skill/toggle')) ?>" class="inline-form">
                  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                  <button class="btn btn-ghost btn-sm" type="submit"><?= (int) $s['status'] === 1 ? '下架' : '上架' ?></button>
                </form>
                <form method="post" action="<?= e(admin_url('skill/delete')) ?>" class="inline-form"
                      data-confirm="确定删除该技能吗？已被订单引用的技能无法删除，请改用下架。">
                  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                  <button class="btn btn-danger btn-sm" type="submit">删除</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
          <tr><td colspan="10" class="empty">没有符合条件的技能，可在右侧「新增服务项目」上架</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?= pager($page, $total, $per_page) ?>
  </section>

  <aside>
    <section class="card">
      <h2 class="card-title"><?= $edit ? '编辑服务项目 #' . (int) $edit['id'] : '新增服务项目' ?></h2>

      <?php if (!$companions): ?>
        <div class="alert alert-warning">还没有已通过审核的陪玩。请先到「用户管理 → 用户详情」把某个用户设为陪玩。</div>
      <?php endif; ?>

      <form method="post" action="<?= e(admin_url('skill/save')) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">

        <div class="form-row">
          <label>陪玩 <span class="req">*</span></label>
          <?php if ($edit): ?>
            <?php
            $editOwner = '';
            foreach ($companions as $c) {
                if ((int) $c['id'] === (int) $edit['companion_id']) {
                    $editOwner = (string) $c['nickname'];
                }
            }
            ?>
            <input type="text" value="<?= e($editOwner !== '' ? $editOwner . '（#' . (int) $edit['companion_id'] . '）' : '#' . (int) $edit['companion_id']) ?>" disabled>
            <p class="hint">编辑时不能更换归属陪玩；如需给别人上架请新建。</p>
          <?php else: ?>
            <select name="companion_id" required>
              <option value="">请选择陪玩</option>
              <?php foreach ($companions as $c): ?>
                <option value="<?= (int) $c['id'] ?>"><?= e($c['nickname']) ?>（#<?= (int) $c['id'] ?>）</option>
              <?php endforeach; ?>
            </select>
          <?php endif; ?>
        </div>

        <div class="form-row">
          <label>游戏 <span class="req">*</span></label>
          <select name="game_id" required>
            <option value="">请选择游戏</option>
            <?php foreach ($games as $g): ?>
              <option value="<?= (int) $g['id'] ?>" <?= (int) ($edit['game_id'] ?? 0) === (int) $g['id'] ? 'selected' : '' ?>><?= e($g['name']) ?></option>
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
            <?php foreach (service_type_map() as $key => $label): ?>
              <option value="<?= e($key) ?>" <?= ($edit['service_type'] ?? 'hour') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-row">
          <label>段位 / 水平</label>
          <input type="text" name="rank_level" maxlength="50" value="<?= e($edit['rank_level'] ?? '') ?>" placeholder="例如：王者 50 星">
        </div>

        <div class="form-row">
          <label>项目配图</label>
          <input type="file" name="cover" accept="image/*">
          <?php if (!empty($edit['cover'])): ?>
            <p class="hint">
              当前配图：
              <img src="<?= e(upload_url((string) $edit['cover'])) ?>" alt="" style="width:72px;border-radius:8px;vertical-align:middle">
              （不重新选择则保持这张）
            </p>
          <?php else: ?>
            <p class="hint">会显示在用户端的服务项目上，选填；支持 jpg / png / gif / webp，≤5MB。</p>
          <?php endif; ?>
        </div>

        <div class="form-row">
          <label>价格（元） <span class="req">*</span></label>
          <input type="number" name="price" min="0.01" step="0.01" value="<?= e($edit['price'] ?? '') ?>" placeholder="按所选服务类型计价" required>
          <p class="hint">用户下单时按「价格 × 数量」计算订单金额，平台再按抽成比例结算给陪玩。</p>
        </div>

        <div class="form-row">
          <label>服务说明</label>
          <textarea name="description" rows="3" maxlength="500" placeholder="服务内容、注意事项、可约时间段等"><?= e($edit['description'] ?? '') ?></textarea>
        </div>

        <button class="btn btn-primary btn-block" type="submit"><?= $edit ? '保存修改' : '上架服务项目' ?></button>
        <?php if ($edit): ?>
          <a class="btn btn-ghost btn-block" href="<?= e(admin_url('skill/index')) ?>" style="margin-top:8px">取消编辑</a>
        <?php endif; ?>
      </form>
    </section>
  </aside>
</div>
