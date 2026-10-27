<?php
/**
 * 顶部公告栏（走马灯滚动）
 *
 * 变量名必须与 layout.php 里传的一致：$__announcements / $__notice
 * @var array  $__announcements 启用中的公告
 * @var string $__notice        站点公告设置（没有公告时的兜底文案）
 */
$__list  = (isset($__announcements) && is_array($__announcements)) ? $__announcements : [];
$__items = [];
foreach ($__list as $a) {
    if (trim((string) $a['content']) === '') {
        continue;
    }
    $__items[] = ['text' => (string) $a['content'], 'url' => (string) $a['link_url']];
}
if (!$__items && $__notice !== '') {
    $__items[] = ['text' => $__notice, 'url' => ''];
}
$__interval = max(2, min(60, (int) \App\Setting::int('notice_interval', 4)));
?>
<?php if ($__items): ?>
  <div class="notice">
    <div class="container notice-inner">
      <span class="notice-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
          <path d="M4 9.5v5h3l5 4V5.5l-5 4H4z"/><path d="M16.5 9a4 4 0 0 1 0 6"/>
        </svg>
      </span>
      <div class="notice-viewport" id="notice-viewport" data-interval="<?= (int) $__interval ?>">
        <div class="notice-track" id="notice-track">
          <?php foreach ($__items as $__it): ?>
            <?php if ($__it['url'] !== ''): ?>
              <a class="notice-item" href="<?= e($__it['url']) ?>" target="_blank" rel="noopener"><?= e($__it['text']) ?></a>
            <?php else: ?>
              <div class="notice-item"><?= e($__it['text']) ?></div>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      </div>
      <?php if (count($__items) > 1): ?>
        <span class="notice-count"><?= count($__items) ?> 条</span>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>
