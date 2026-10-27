<?php
/**
 * 陪玩详情页
 * @var array $profile
 * @var array $skills
 * @var array $reviews
 * @var array $stats
 */
$me        = user();
$isSelf    = $me && (int) $me['id'] === (int) $profile['user_id'];
$canOrder  = (int) $profile['online_status'] === 1 && (int) $profile['audit_status'] === 1;
?>
<div class="detail-grid">
  <div class="detail-main">
    <section class="card profile-head">
      <img class="avatar avatar-lg" src="<?= e(avatar_url($profile['avatar'], (string) $profile['nickname'])) ?>" alt="">
      <div class="ph-info">
        <h1>
          <?= e($profile['nickname']) ?>
          <?php if ((int) $profile['online_status'] === 1): ?>
            <span class="badge badge-success">接单中</span>
          <?php else: ?>
            <span class="badge badge-muted">休息中</span>
          <?php endif; ?>
        </h1>
        <p class="ph-meta">
          <span class="stars"><?= str_repeat('★', (int) round((float) $profile['score'])) ?></span>
          <?= e(number_format((float) $profile['score'], 2)) ?> 分 ·
          好评率 <?= e(number_format((float) $profile['good_rate'], 1)) ?>% ·
          完成 <?= (int) $profile['order_count'] ?> 单
          <?php if ($profile['city'] !== ''): ?> · <?= e($profile['city']) ?><?php endif; ?>
        </p>
        <div class="cc-tags">
          <?php foreach (array_filter(explode(',', (string) $profile['tags'])) as $tag): ?>
            <span class="tag"><?= e(trim($tag)) ?></span>
          <?php endforeach; ?>
        </div>
        <p class="ph-intro"><?= nl2br(e((string) $profile['intro'])) ?></p>
      </div>
    </section>

    <section class="card">
      <h2 class="card-title">服务项目</h2>
      <?php if (!$skills): ?>
        <div class="empty">该陪玩还没有上架服务</div>
      <?php else: ?>
        <div class="skill-list">
          <?php foreach ($skills as $s): ?>
            <div class="skill-item">
              <?php if (!empty($s['cover'])): ?>
                <img class="skill-cover" src="<?= e(upload_url((string) $s['cover'])) ?>" alt="" loading="lazy">
              <?php endif; ?>
              <div class="skill-main">
                <div class="skill-title">
                  <?= e($s['title']) ?>
                  <span class="tag"><?= e($s['game_name'] ?? '') ?></span>
                  <?php if ($s['rank_level'] !== ''): ?><span class="tag"><?= e($s['rank_level']) ?></span><?php endif; ?>
                </div>
                <div class="skill-desc"><?= e($s['description'] !== '' ? $s['description'] : '暂无描述') ?></div>
                <div class="skill-price">
                  <strong><?= e(yuan((float) $s['price'])) ?></strong>
                  <span class="muted">/ <?= e(service_unit_suffix((string) $s['service_type'])) ?></span>
                  <span class="tag"><?= e(service_type_text((string) $s['service_type'])) ?></span>
                </div>
              </div>

              <?php if ($isSelf): ?>
                <div class="skill-action"><span class="muted">这是你自己的服务</span></div>
              <?php elseif (!$canOrder): ?>
                <div class="skill-action"><span class="muted">该陪玩当前休息中</span></div>
              <?php elseif (!$me): ?>
                <div class="skill-action">
                  <a class="btn btn-primary" href="<?= e(url('auth/login')) ?>">登录后下单</a>
                </div>
              <?php else: ?>
                <form class="skill-action order-form" method="post" action="<?= e(url('order/create')) ?>">
                  <?= csrf_field() ?>
                  <input type="hidden" name="skill_id" value="<?= (int) $s['id'] ?>">
                  <input type="hidden" name="quantity" value="1">
                  <div class="qty">
                    <button type="button" class="qty-btn" data-step="-1">-</button>
                    <span class="qty-value" data-price="<?= e(money((float) $s['price'])) ?>" data-unit="<?= e(service_unit_suffix((string) $s['service_type'])) ?>">
                      1 <?= e(service_unit_suffix((string) $s['service_type'])) ?>
                    </span>
                    <button type="button" class="qty-btn" data-step="1">+</button>
                  </div>
                  <div class="order-total">合计 <strong class="total-price"><?= e(yuan((float) $s['price'])) ?></strong></div>
                  <input class="remark-input" type="text" name="remark" placeholder="备注：游戏区服 / 时间要求（选填）" maxlength="200">
                  <button class="btn btn-primary btn-block" type="submit">立即下单</button>
                </form>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="card">
      <h2 class="card-title">用户评价 <span class="muted">(<?= (int) $profile['review_count'] ?>)</span></h2>
      <?php if (!$reviews): ?>
        <div class="empty">还没有评价，快来成为第一个吧</div>
      <?php else: ?>
        <ul class="review-list">
          <?php foreach ($reviews as $r): ?>
            <li class="review-item">
              <div class="review-head">
                <img class="avatar avatar-sm" src="<?= e(avatar_url($r['avatar'], (string) $r['nickname'])) ?>" alt="">
                <span class="review-name"><?= e((int) $r['is_anonymous'] === 1 ? '匿名用户' : str_limit((string) $r['nickname'], 12)) ?></span>
                <span class="stars"><?= str_repeat('★', (int) $r['score']) ?></span>
                <span class="muted"><?= e(substr((string) $r['created_at'], 0, 16)) ?></span>
              </div>
              <p class="review-content"><?= nl2br(e((string) $r['content'])) ?></p>
              <?php if ($r['reply'] !== ''): ?>
                <div class="review-reply"><strong>陪玩回复：</strong><?= nl2br(e((string) $r['reply'])) ?></div>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  </div>

  <aside class="detail-side">
    <section class="card">
      <h2 class="card-title">下单须知</h2>
      <ol class="tips">
        <li>下单后请在超时时间内完成支付，超时订单会自动关闭。</li>
        <li>支付金额由平台担保，服务完成并确认后才会结算给陪玩。</li>
        <li>如果陪玩未提供服务，可在订单里申请退款。</li>
        <li>请勿在站外私下交易，出现纠纷平台无法保障。</li>
      </ol>
    </section>
    <section class="card">
      <h2 class="card-title">统计</h2>
      <ul class="stat-list">
        <li><span>累计接单</span><strong><?= (int) $stats['total'] ?></strong></li>
        <li><span>已完成</span><strong><?= (int) $stats['completed'] ?></strong></li>
        <li><span>好评率</span><strong><?= e(number_format((float) $profile['good_rate'], 1)) ?>%</strong></li>
        <li><span>入驻时间</span><strong><?= e(substr((string) $profile['created_at'], 0, 10)) ?></strong></li>
      </ul>
    </section>
  </aside>
</div>
