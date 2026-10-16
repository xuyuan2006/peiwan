<?php
/**
 * 首页广告轮播位（多张自动轮播、可点圆点、手机可左右滑）
 * @var array $banners
 */
$__interval = max(2, min(60, (int) \App\Setting::int('banner_interval', 5)));
?>
<?php if (!empty($banners)): ?>
  <div class="banner-carousel" id="banner-carousel" data-interval="<?= (int) $__interval ?>">
    <div class="banner-track" id="banner-track">
      <?php foreach ($banners as $b): ?>
        <?php $__url = (string) $b['link_url']; ?>
        <?php if ($__url !== ''): ?>
          <a class="banner-slide" href="<?= e($__url) ?>" target="_blank" rel="noopener">
            <img src="<?= e(upload_url((string) $b['image'])) ?>" alt="<?= e((string) $b['title']) ?>">
          </a>
        <?php else: ?>
          <div class="banner-slide">
            <img src="<?= e(upload_url((string) $b['image'])) ?>" alt="<?= e((string) $b['title']) ?>">
          </div>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
    <?php if (count($banners) > 1): ?>
      <div class="banner-dots" id="banner-dots"></div>
    <?php endif; ?>
  </div>
<?php endif; ?>
