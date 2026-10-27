<?php
/**
 * 陪玩卡片（首页网格、瀑布流、无限滚动接口共用）
 * @var array $c 一行陪玩资料（CompanionService::search 的返回行）
 */
$__tags   = array_slice(array_filter(explode(',', (string) ($c['tags'] ?? ''))), 0, 3);
$__online = (int) ($c['online_status'] ?? 0) === 1;
$__price  = (float) ($c['min_price'] ?? 0);
?>
<a class="companion-card" href="<?= e(url('home/companion', ['id' => (int) $c['uid']])) ?>">
  <div class="cc-top">
    <img class="avatar" src="<?= e(avatar_url($c['avatar'] ?? '', (string) $c['nickname'])) ?>" alt="" loading="lazy">
    <div class="cc-info">
      <div class="cc-name">
        <?= e(str_limit((string) $c['nickname'], 12)) ?>
        <?php if ((int) ($c['is_recommend'] ?? 0) === 1): ?><span class="badge badge-primary">推荐</span><?php endif; ?>
      </div>
      <div class="cc-meta">
        <span class="stars"><?= str_repeat('★', max(1, (int) round((float) ($c['score'] ?? 5)))) ?></span>
        <span><?= e(number_format((float) ($c['score'] ?? 5), 1)) ?> 分</span>
        <span class="muted">接单 <?= (int) ($c['order_count'] ?? 0) ?> 次</span>
      </div>
      <div class="cc-online">
        <span class="online-tag <?= $__online ? 'on' : 'off' ?>">
          <i class="dot"></i><?= $__online ? '在线' : '离线' ?>
        </span>
      </div>
    </div>
  </div>

  <p class="cc-intro"><?= e(str_limit((string) ($c['intro'] ?? ''), 46)) ?></p>

  <div class="cc-tags">
    <?php foreach ($__tags as $tag): ?>
      <span class="tag"><?= e(trim($tag)) ?></span>
    <?php endforeach; ?>
    <?php if ((int) ($c['skill_count'] ?? 0) > 0): ?>
      <span class="tag"><?= (int) $c['skill_count'] ?> 项服务</span>
    <?php endif; ?>
  </div>

  <div class="cc-foot">
    <span class="price">
      <?php if ($__price > 0): ?><?= e(yuan($__price)) ?><small> 起</small><?php else: ?><span class="muted" style="font-size:13px">价格面议</span><?php endif; ?>
    </span>
    <span class="btn btn-primary btn-sm"><?= $__online ? '立即下单' : '看看主页' ?></span>
  </div>
</a>
