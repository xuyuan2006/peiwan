<?php
/**
 * 点单套餐卡片（首页与「直接点单」页共用）
 * @var array $p
 */
?>
<div class="package-card">
  <?php if (!empty($p['cover'])): ?>
    <img class="package-cover" src="<?= e(upload_url((string) $p['cover'])) ?>" alt="" loading="lazy">
  <?php else: ?>
    <div class="package-cover package-cover-ph"><?= e((string) ($p['game_name'] ?? '点单套餐')) ?></div>
  <?php endif; ?>

  <div class="package-body">
    <div class="package-title"><?= e((string) $p['title']) ?></div>
    <?php if (!empty($p['game_name'])): ?>
      <div class="muted" style="font-size:12.5px"><?= e((string) $p['game_name']) ?></div>
    <?php endif; ?>
    <?php if (trim((string) $p['description']) !== ''): ?>
      <p class="package-desc"><?= e(str_limit((string) $p['description'], 60)) ?></p>
    <?php endif; ?>

    <div class="package-foot">
      <span class="price"><?= e(yuan((float) $p['price'])) ?></span>
      <form method="post" action="<?= e(url('order/buyPackage')) ?>"
            data-confirm="确认购买「<?= e((string) $p['title']) ?>」？将从钱包余额扣款。">
        <?= csrf_field() ?>
        <input type="hidden" name="package_id" value="<?= (int) $p['id'] ?>">
        <button class="btn btn-primary btn-sm" type="submit">立即下单</button>
      </form>
    </div>
  </div>
</div>
