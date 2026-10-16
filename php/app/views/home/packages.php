<?php
/**
 * 直接点单（套餐列表，可按游戏筛选）
 * @var array $packages
 * @var array $games
 * @var int   $game_id
 */
?>
<section class="page-head">
  <div class="head-row">
    <div>
      <h1>直接点单</h1>
      <p class="muted">
        选一个套餐直接下单，付款后订单进入接单大厅，由陪玩抢单为你服务
        <?php if ((int) $game_id > 0): ?>
          ｜已筛选：<span class="tag"><?php foreach ($games as $g) { if ((int) $g['id'] === (int) $game_id) { echo e($g['name']); } } ?></span>
          · 共 <?= count($packages) ?> 个套餐
          <a href="<?= e(url('home/packages')) ?>">清除筛选</a>
        <?php endif; ?>
      </p>
    </div>
    <div class="head-actions">
      <a class="btn btn-ghost" href="<?= e(url('home/companions')) ?>">我想挑陪玩</a>
    </div>
  </div>
</section>

<?php if ($games): ?>
  <section class="card">
    <div class="chip-row">
      <a class="chip <?= (int) $game_id <= 0 ? 'active' : '' ?>"
         href="<?= e(query_url(['game_id' => '', 'page' => 1])) ?>">全部游戏</a>
      <?php foreach ($games as $g): ?>
        <a class="chip <?= (int) $game_id === (int) $g['id'] ? 'active' : '' ?>"
           href="<?= e(query_url(['game_id' => (int) $g['id'], 'page' => 1])) ?>"><?= e($g['name']) ?></a>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<?php if (!$packages): ?>
  <div class="card empty">
    <?php if ((int) $game_id > 0): ?>
      这个游戏下还没有套餐，<a href="<?= e(url('home/packages')) ?>">看看全部套餐</a>。
    <?php else: ?>
      还没有上架套餐。管理员可在后台「点单套餐」里添加，例如「王者荣耀 陪玩 5 小时」。
    <?php endif; ?>
  </div>
<?php else: ?>
  <div class="package-grid">
    <?php foreach ($packages as $p): ?>
      <?php include APP_ROOT . '/app/views/partials/package_card.php'; ?>
    <?php endforeach; ?>
  </div>
  <p class="hint" style="text-align:center;margin-top:16px">
    下单会从钱包余额扣款（不够会引导你先充值）；付款后进入接单大厅，长时间没人接可申请退款。
  </p>
<?php endif; ?>
