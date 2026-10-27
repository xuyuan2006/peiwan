<?php
/**
 * 接单大厅（陪玩身份可见）
 * @var array $profile
 * @var array $filter
 * @var array $games
 * @var array $result
 * @var int   $per_page
 * @var array $serving
 */
$rows      = $result['rows'];
$total     = (int) $result['total'];
$page      = (int) $result['page'];
$totalPage = (int) ceil($total / max(1, $per_page));
?>
<div class="page-head">
  <div class="head-row">
    <div>
      <h1>接单大厅</h1>
      <p class="muted">
        当前 <span class="badge badge-success">待接单 <?= $total ?> 单</span>
        · 我的评分 <?= e(number_format((float) $profile['score'], 2)) ?>
        · 完成 <?= (int) $profile['order_count'] ?> 单
      </p>
    </div>
    <div class="head-actions">
      <a class="btn btn-ghost" href="<?= e(url('companion/orders')) ?>">我的接单</a>
      <a class="btn btn-ghost" href="<?= e(url('companion/center')) ?>">陪玩中心</a>
    </div>
  </div>
</div>

<?php if ($serving): ?>
  <section class="card">
    <h2 class="card-title">手上的单（<?= count($serving) ?>）</h2>
    <div class="order-list">
      <?php foreach ($serving as $order): ?>
        <div class="order-card inner">
          <div class="order-head">
            <span class="order-no">订单号 <?= e($order['order_no']) ?></span>
            <span class="status status-<?= e(order_status_class((int) $order['status'])) ?>"><?= e(order_status_text((int) $order['status'])) ?></span>
            <span class="muted order-time"><?= e((string) $order['created_at']) ?></span>
          </div>
          <div class="order-body">
            <img class="avatar" src="<?= e(avatar_url($order['user_avatar'], (string) $order['user_nickname'])) ?>" alt="">
            <div class="order-info">
              <div class="order-title"><?= e($order['game_name']) ?> · <?= e($order['skill_title']) ?></div>
              <div class="order-sub muted">
                用户：<?= e($order['user_nickname']) ?>
                · <?= (int) $order['quantity'] ?> <?= e(service_unit_suffix((string) $order['service_type'])) ?>
              </div>
            </div>
            <div class="order-amount"><strong><?= e(yuan((float) $order['amount'])) ?></strong></div>
          </div>
          <?php
          $role = 'companion';
          include APP_ROOT . '/app/views/partials/order_actions.php';
          ?>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<section class="card">
  <form class="filter-form" method="get" action="<?= e(url('companion/hall')) ?>">
    <input type="hidden" name="r" value="companion/hall">
    <label>游戏
      <select name="game_id">
        <option value="0">全部游戏</option>
        <?php foreach ($games as $g): ?>
          <option value="<?= (int) $g['id'] ?>" <?= (int) $filter['game_id'] === (int) $g['id'] ? 'selected' : '' ?>><?= e($g['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>关键词
      <input type="text" name="keyword" value="<?= e($filter['keyword']) ?>" placeholder="标题 / 订单号">
    </label>
    <button class="btn btn-primary btn-sm" type="submit">筛选</button>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('companion/hall')) ?>">重置</a>
  </form>
</section>

<?php if (!$rows): ?>
  <div class="card empty">
    大厅暂时没有可接的单。<br>
    管理员在后台「订单管理 → 发布接单任务」发布的订单会出现在这里。
  </div>
<?php else: ?>
  <div class="task-list">
    <?php foreach ($rows as $t): ?>
      <div class="card task-card">
        <?php if ($t['cover'] !== ''): ?>
          <img class="task-cover" src="<?= e(upload_url((string) $t['cover'])) ?>" alt="" loading="lazy">
        <?php else: ?>
          <div class="task-cover task-cover-ph"><?= e($t['game_name']) ?></div>
        <?php endif; ?>

        <div class="task-main">
          <div class="task-title"><?= e($t['skill_title']) ?></div>
          <div class="task-meta muted">
            订单号 <?= e($t['order_no']) ?> · <?= e((string) $t['created_at']) ?>
          </div>
          <div class="task-meta muted">
            需求：<?= (int) $t['quantity'] ?> <?= e(service_unit_suffix((string) $t['service_type'])) ?>
            × <?= e(yuan((float) $t['unit_price'])) ?>
            · 发布人：<?= e($t['user_nickname']) ?>
            · 信用 <?= (int) ($t['user_credit'] ?? 100) ?>
          </div>
          <?php if ($t['user_remark'] !== ''): ?>
            <div class="task-remark">备注：<?= e(str_limit((string) $t['user_remark'], 60)) ?></div>
          <?php endif; ?>

          <div class="task-foot">
            <span class="price"><?= e(yuan((float) $t['amount'])) ?></span>
            <span class="muted" style="font-size:12.5px">
              预计收入 <?= e(yuan(safe_float((float) $t['amount'] * (100 - \App\Setting::float('platform_fee_rate', 20)) / 100))) ?>
            </span>
            <form method="post" action="<?= e(url('companion/grab')) ?>" class="inline-form"
                  data-confirm="确认接这一单吗？接单后需要按时提供服务。">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
              <button class="btn btn-primary btn-sm" type="submit">立即接单</button>
            </form>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?= pager($page, $total, $per_page) ?>
