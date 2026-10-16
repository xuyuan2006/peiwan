<?php
/**
 * 陪玩瀑布流（底部 Tab「陪玩」页，下拉无限加载）
 * @var array $filter
 * @var array $games
 * @var array $result
 * @var int   $per_page
 */
$rows      = $result['rows'];
$total     = (int) $result['total'];
$page      = (int) $result['page'];
$totalPage = (int) ceil($total / max(1, $per_page));
$moreUrl   = query_url(['page' => ''], 'home/more');

// 当前筛选提示（让用户一眼看出筛选是否生效）
$__gameName = '';
foreach ($games as $__g) {
    if ((int) $__g['id'] === (int) $filter['game_id']) {
        $__gameName = (string) $__g['name'];
    }
}
$__hasFilter = (int) $filter['game_id'] > 0
    || $filter['keyword'] !== ''
    || (int) $filter['gender'] > 0
    || $filter['min_price'] !== ''
    || $filter['max_price'] !== ''
    || !empty($filter['online_only']);
?>
<section class="page-head">
  <div class="head-row">
    <div>
      <h1>找陪玩</h1>
      <p class="muted">
        共 <strong><?= $total ?></strong> 位陪玩 · 往下滑自动加载更多
        <?php if ($__hasFilter): ?>
          ｜已筛选：
          <?php if ($__gameName !== ''): ?><span class="tag"><?= e($__gameName) ?></span><?php endif; ?>
          <?php if ((int) $filter['gender'] > 0): ?><span class="tag"><?= (int) $filter['gender'] === 2 ? '女生' : '男生' ?></span><?php endif; ?>
          <?php if (!empty($filter['online_only'])): ?><span class="tag">只看在线</span><?php endif; ?>
          <?php if ($filter['keyword'] !== ''): ?><span class="tag"><?= e($filter['keyword']) ?></span><?php endif; ?>
          <a href="<?= e(url('home/companions')) ?>">清除筛选</a>
        <?php endif; ?>
      </p>
    </div>
    <div class="head-actions">
      <a class="btn btn-ghost btn-sm" href="<?= e(url('home/index')) ?>">返回首页</a>
    </div>
  </div>
</section>

<section class="card">
  <form class="filter-form" method="get" action="<?= e(url('home/companions')) ?>">
    <input type="hidden" name="r" value="home/companions">
    <label>游戏
      <select name="game_id">
        <option value="0">全部游戏</option>
        <?php foreach ($games as $g): ?>
          <option value="<?= (int) $g['id'] ?>" <?= (int) $filter['game_id'] === (int) $g['id'] ? 'selected' : '' ?>><?= e($g['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
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
    <label>关键词
      <input type="text" name="keyword" value="<?= e($filter['keyword']) ?>" placeholder="昵称 / 标签">
    </label>
    <button class="btn btn-primary btn-sm" type="submit">筛选</button>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('home/companions')) ?>">重置</a>
  </form>
</section>

<?php if (!$rows): ?>
  <div class="card empty">没有符合条件的陪玩，换个条件试试</div>
<?php else: ?>
  <div class="waterfall" id="wf-list">
    <?php foreach ($rows as $c): ?>
      <?php echo \App\View::capture('partials/companion_card', ['c' => $c]); ?>
    <?php endforeach; ?>
  </div>

  <div id="wf-sentinel"
       class="wf-more"
       data-url="<?= e($moreUrl) ?>"
       data-page="<?= $page ?>"
       data-has-more="<?= $page < $totalPage ? 1 : 0 ?>">
    <?= $page < $totalPage ? '上滑加载更多…' : '没有更多了' ?>
  </div>
<?php endif; ?>
