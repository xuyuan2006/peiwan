<?php
/**
 * 首页：公告 + 游戏分类 + 推荐陪玩（完整列表见「陪玩」Tab 的瀑布流页）
 * @var array $filter
 * @var array $games
 * @var array $result
 * @var array $stats
 */
$rows  = $result['rows'];
$total = (int) $result['total'];
?>

<?php include APP_ROOT . '/app/views/partials/banner_carousel.php'; ?>

<section class="card">
  <div class="filters">
    <div class="chip-row">
      <a class="chip <?= empty($filter['game_id']) ? 'active' : '' ?>" href="<?= e(query_url(['game_id' => '', 'page' => 1])) ?>">全部游戏</a>
      <?php foreach ($games as $g): ?>
        <a class="chip <?= (int) $filter['game_id'] === (int) $g['id'] ? 'active' : '' ?>"
           href="<?= e(query_url(['game_id' => (int) $g['id'], 'page' => 1])) ?>"><?= e($g['name']) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php
// 当前筛选的游戏名（用于在标题上提示筛选已生效）
$__gameName = '';
foreach ($games as $__g) {
    if ((int) $__g['id'] === (int) $filter['game_id']) {
        $__gameName = (string) $__g['name'];
    }
}
?>
<?php if (!empty($packages)): ?>
  <section class="section-head">
    <h2>
      直接点单
      <span class="muted">
        <?= $__gameName !== '' ? '已筛选 ' . e($__gameName) . '，共 ' . count($packages) . ' 个套餐' : '选套餐一键下单' ?>
      </span>
      <?php if ($__gameName !== ''): ?>
        <a class="muted" style="font-size:12px;margin-left:6px" href="<?= e(url('home/index')) ?>">清除筛选</a>
      <?php endif; ?>
    </h2>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('home/packages')) ?>">全部套餐 →</a>
  </section>
  <div class="package-grid">
    <?php foreach (array_slice($packages, 0, 6) as $p): ?>
      <?php include APP_ROOT . '/app/views/partials/package_card.php'; ?>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<section class="section-head">
  <h2>
    推荐陪玩 <span class="muted">共 <?= $total ?> 位</span>
    <?php if ($__gameName !== ''): ?>
      <span class="tag"><?= e($__gameName) ?></span>
      <a class="muted" style="font-size:12px;margin-left:6px" href="<?= e(url('home/index')) ?>">清除筛选</a>
    <?php endif; ?>
  </h2>
  <a class="btn btn-ghost btn-sm" href="<?= e(url('home/companions')) ?>">全部陪玩 →</a>
</section>

<?php if (!$rows): ?>
  <div class="card empty">还没有陪玩入驻，先去「成为陪玩」申请一个吧～</div>
<?php else: ?>
  <div class="grid">
    <?php foreach ($rows as $c): ?>
      <?php echo \App\View::capture('partials/companion_card', ['c' => $c]); ?>
    <?php endforeach; ?>
  </div>

  <div style="text-align:center;margin:18px 0 6px">
    <a class="btn btn-primary" href="<?= e(url('home/companions')) ?>">查看更多陪玩</a>
  </div>
<?php endif; ?>
