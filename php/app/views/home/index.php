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
  <form class="search-row" method="get" action="<?= e(url('home/index')) ?>">
    <input type="hidden" name="r" value="home/index">
    <?php if (!empty($filter['game_id'])): ?><input type="hidden" name="game_id" value="<?= (int) $filter['game_id'] ?>"><?php endif; ?>
    <?php if (!empty($filter['gender'])): ?><input type="hidden" name="gender" value="<?= (int) $filter['gender'] ?>"><?php endif; ?>
    <?php if ($filter['sort'] !== 'default'): ?><input type="hidden" name="sort" value="<?= e($filter['sort']) ?>"><?php endif; ?>
    <?php if (!empty($filter['online_only'])): ?><input type="hidden" name="online_only" value="1"><?php endif; ?>
    <?php if ($filter['min_price'] !== ''): ?><input type="hidden" name="min_price" value="<?= e($filter['min_price']) ?>"><?php endif; ?>
    <?php if ($filter['max_price'] !== ''): ?><input type="hidden" name="max_price" value="<?= e($filter['max_price']) ?>"><?php endif; ?>
    <input type="text" name="keyword" value="<?= e($filter['keyword']) ?>" placeholder="搜索陪玩昵称 / 游戏 / 标签，例如：声音甜 王者">
    <button class="btn btn-primary" type="submit">搜索</button>
  </form>

  <div class="filters">
    <div class="chip-row">
      <a class="chip <?= empty($filter['game_id']) ? 'active' : '' ?>" href="<?= e(query_url(['game_id' => '', 'page' => 1])) ?>">全部游戏</a>
      <?php foreach ($games as $g): ?>
        <a class="chip <?= (int) $filter['game_id'] === (int) $g['id'] ? 'active' : '' ?>"
           href="<?= e(query_url(['game_id' => (int) $g['id'], 'page' => 1])) ?>"><?= e($g['name']) ?></a>
      <?php endforeach; ?>
    </div>

    <form class="filter-form" method="get" action="<?= e(url('home/index')) ?>">
      <input type="hidden" name="r" value="home/index">
      <?php if (!empty($filter['game_id'])): ?><input type="hidden" name="game_id" value="<?= (int) $filter['game_id'] ?>"><?php endif; ?>
      <?php if ($filter['keyword'] !== ''): ?><input type="hidden" name="keyword" value="<?= e($filter['keyword']) ?>"><?php endif; ?>

      <label>性别
        <select name="gender">
          <option value="0">不限</option>
          <option value="2" <?= (int) $filter['gender'] === 2 ? 'selected' : '' ?>>女生</option>
          <option value="1" <?= (int) $filter['gender'] === 1 ? 'selected' : '' ?>>男生</option>
        </select>
      </label>
      <label>价格
        <input type="number" name="min_price" value="<?= e($filter['min_price']) ?>" placeholder="最低" min="0" step="1">
        <span class="dash">-</span>
        <input type="number" name="max_price" value="<?= e($filter['max_price']) ?>" placeholder="最高" min="0" step="1">
      </label>
      <label>排序
        <select name="sort">
          <option value="default" <?= $filter['sort'] === 'default' ? 'selected' : '' ?>>综合推荐</option>
          <option value="hot" <?= $filter['sort'] === 'hot' ? 'selected' : '' ?>>接单最多</option>
          <option value="score" <?= $filter['sort'] === 'score' ? 'selected' : '' ?>>评分最高</option>
          <option value="price_asc" <?= $filter['sort'] === 'price_asc' ? 'selected' : '' ?>>价格从低到高</option>
          <option value="price_desc" <?= $filter['sort'] === 'price_desc' ? 'selected' : '' ?>>价格从高到低</option>
          <option value="new" <?= $filter['sort'] === 'new' ? 'selected' : '' ?>>最新入驻</option>
        </select>
      </label>
      <label class="check">
        <input type="checkbox" name="online_only" value="1" <?= !empty($filter['online_only']) ? 'checked' : '' ?>>只看接单中
      </label>
      <button class="btn btn-primary btn-sm" type="submit">筛选</button>
      <a class="btn btn-ghost btn-sm" href="<?= e(url('home/index')) ?>">重置</a>
    </form>
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
