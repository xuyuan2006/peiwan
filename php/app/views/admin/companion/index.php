<?php
/**
 * 后台陪玩管理
 * @var array  $rows
 * @var int    $total
 * @var int    $page
 * @var int    $per_page
 * @var string $audit
 * @var string $keyword
 * @var array  $counts
 */
$totalPage = (int) ceil($total / max(1, $per_page));
$tabs = [
    'all' => '全部',
    '0'   => '待审核',
    '1'   => '已通过',
    '2'   => '已拒绝',
];
?>
<div class="tabs">
  <?php foreach ($tabs as $key => $label): ?>
    <a class="tab <?= (string) $audit === (string) $key ? 'active' : '' ?>"
       href="<?= e(admin_url('companion/index', ['audit' => $key, 'keyword' => $keyword])) ?>">
      <?= e($label) ?>
      <?php if ($key === '0' && (int) ($counts['pending'] ?? 0) > 0): ?>(<?= (int) $counts['pending'] ?>)<?php endif; ?>
    </a>
  <?php endforeach; ?>
</div>

<form class="filter-bar" method="get" action="<?= e(admin_url('companion/index')) ?>">
  <input type="hidden" name="r" value="companion/index">
  <input type="hidden" name="audit" value="<?= e($audit) ?>">
  <label>关键词
    <input type="text" name="keyword" value="<?= e($keyword) ?>" placeholder="昵称 / 真实姓名 / 手机 / ID">
  </label>
  <div class="actions">
    <button class="btn btn-primary btn-sm" type="submit">查询</button>
    <a class="btn btn-ghost btn-sm" href="<?= e(admin_url('companion/index')) ?>">重置</a>
  </div>
</form>

<div class="table-card">
  <div class="summary-row" style="padding-top:12px">
    <span>共 <strong><?= (int) $total ?></strong> 位陪玩</span>
    <span>待审核 <strong><?= (int) ($counts['pending'] ?? 0) ?></strong></span>
    <span>已通过 <strong><?= (int) ($counts['approved'] ?? 0) ?></strong></span>
    <span>已拒绝 <strong><?= (int) ($counts['rejected'] ?? 0) ?></strong></span>
  </div>

  <table class="table">
    <thead>
      <tr>
        <th>ID</th><th>陪玩</th><th>真实姓名 / 手机</th><th>城市</th>
        <th>技能</th><th>评分 / 完成</th><th>接单状态</th><th>审核</th><th>操作</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $p): ?>
      <tr>
        <td><?= (int) $p['user_id'] ?></td>
        <td>
          <div style="display:flex;align-items:center;gap:8px">
            <img class="avatar avatar-sm" src="<?= e(avatar_url($p['avatar'], (string) $p['nickname'])) ?>" alt="">
            <div>
              <div>
                <?= e($p['nickname']) ?>
                <?php if ((int) $p['is_recommend'] === 1): ?><span class="badge badge-primary">推荐</span><?php endif; ?>
                <?php if ((int) $p['user_status'] !== 1): ?><span class="badge">已禁用</span><?php endif; ?>
              </div>
              <div class="muted" style="font-size:12px">
                <a href="<?= e(url('home/companion', ['id' => (int) $p['user_id']])) ?>" target="_blank">查看主页 ↗</a>
              </div>
            </div>
          </div>
        </td>
        <td>
          <?= e($p['real_name'] !== '' ? $p['real_name'] : '-') ?>
          <div class="muted" style="font-size:12px"><?= e($p['phone'] !== '' ? $p['phone'] : '-') ?></div>
        </td>
        <td><?= e($p['city'] !== '' ? $p['city'] : '-') ?></td>
        <td><?= (int) $p['skill_count'] ?></td>
        <td><?= e(number_format((float) $p['score'], 2)) ?> / <?= (int) $p['order_count'] ?></td>
        <td>
          <?= (int) $p['online_status'] === 1 ? '<span class="status status-success">接单中</span>' : '<span class="status status-muted">休息中</span>' ?>
        </td>
        <td>
          <?php $cls = [0 => 'warn', 1 => 'success', 2 => 'muted'][(int) $p['audit_status']] ?? 'muted'; ?>
          <span class="status status-<?= e($cls) ?>"><?= e(audit_status_text((int) $p['audit_status'])) ?></span>
          <?php if ($p['audit_remark'] !== ''): ?>
            <div class="muted" style="font-size:12px"><?= e(str_limit((string) $p['audit_remark'], 20)) ?></div>
          <?php endif; ?>
        </td>
        <td>
          <div class="cell-actions">
            <form method="post" action="<?= e(admin_url('companion/audit')) ?>" class="inline-form">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $p['user_id'] ?>">
              <input type="text" name="remark" placeholder="审核备注(选填)" style="padding:5px 8px;font-size:12px;min-width:110px">
              <button class="btn btn-primary btn-sm" type="submit" name="status" value="1">通过</button>
              <button class="btn btn-danger btn-sm" type="submit" name="status" value="2">拒绝</button>
            </form>
            <form method="post" action="<?= e(admin_url('companion/recommend')) ?>" class="inline-form">
              <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['user_id'] ?>">
              <button class="btn btn-ghost btn-sm" type="submit"><?= (int) $p['is_recommend'] === 1 ? '取消推荐' : '设为推荐' ?></button>
            </form>
            <?php if ((int) $p['audit_status'] === 1): ?>
              <form method="post" action="<?= e(admin_url('companion/demote')) ?>" class="inline-form"
                    data-confirm="取消该陪玩资格会下架其全部技能，确定吗？">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['user_id'] ?>">
                <button class="btn btn-danger btn-sm" type="submit">取消资格</button>
              </form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?>
      <tr><td colspan="9" class="empty">没有符合条件的陪玩</td></tr>
    <?php endif; ?>
    </tbody>
  </table>

  <?= pager($page, $total, $per_page) ?>
</div>
