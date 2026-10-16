<?php
/**
 * 订单详情
 * @var array       $order
 * @var array       $logs
 * @var array|null  $review
 * @var bool        $is_user
 * @var bool        $is_comp
 */
$status = (int) $order['status'];
$operatorText = ['user' => '用户', 'companion' => '陪玩', 'admin' => '平台', 'system' => '系统'];
?>
<div class="page-head">
  <div class="head-row">
    <div>
      <h1>订单详情</h1>
      <p class="muted">订单号 <?= e($order['order_no']) ?> · 下单时间 <?= e((string) $order['created_at']) ?></p>
    </div>
    <div class="head-actions">
      <button type="button" class="btn btn-ghost" data-ticket-open data-order-id="<?= (int) $order['id'] ?>">遇到问题？提交工单</button>
    </div>
  </div>
</div>

<div class="detail-grid">
  <div class="detail-main">
    <section class="card">
      <div class="order-detail-head">
        <span class="status status-<?= e(order_status_class($status)) ?>"><?= e(order_status_text($status)) ?></span>
        <span class="order-detail-amount"><?= e(yuan((float) $order['amount'])) ?></span>
      </div>

      <table class="table table-plain">
        <tr><th>游戏</th><td><?= e($order['game_name']) ?></td><th>服务</th><td><?= e($order['skill_title']) ?></td></tr>
        <tr>
          <th>单价 × 数量</th>
          <td><?= e(yuan((float) $order['unit_price'])) ?> × <?= (int) $order['quantity'] ?> <?= e(service_unit_suffix((string) $order['service_type'])) ?></td>
          <th>服务类型</th><td><?= e(service_type_text((string) $order['service_type'])) ?></td>
        </tr>
        <tr>
          <th>下单用户</th>
          <td><?= e($order['user_nickname']) ?></td>
          <th>陪玩</th>
          <td><a href="<?= e(url('home/companion', ['id' => (int) $order['companion_id']])) ?>"><?= e($order['companion_nickname']) ?></a>
            <span class="muted">（<?= e(number_format((float) ($order['companion_score'] ?? 5), 1)) ?> 分 / <?= (int) ($order['companion_orders'] ?? 0) ?> 单）</span>
          </td>
        </tr>
        <?php if ((int) $order['status'] === 5): ?>
          <tr>
            <th>平台抽成</th><td><?= e(yuan((float) $order['platform_fee'])) ?></td>
            <th>陪玩实收</th><td><?= e(yuan((float) $order['companion_income'])) ?></td>
          </tr>
        <?php endif; ?>
        <?php if ($order['user_remark'] !== ''): ?>
          <tr><th>订单备注</th><td colspan="3"><?= e($order['user_remark']) ?></td></tr>
        <?php endif; ?>
        <?php if ($order['refund_reason'] !== ''): ?>
          <tr><th>退款原因</th><td colspan="3"><?= e($order['refund_reason']) ?></td></tr>
        <?php endif; ?>
        <?php if ($order['cancel_reason'] !== ''): ?>
          <tr><th>关闭/退款说明</th><td colspan="3"><?= e($order['cancel_reason']) ?></td></tr>
        <?php endif; ?>
      </table>

      <?php if ($is_user || $is_comp): ?>
        <?php
        $role = $is_comp && !$is_user ? 'companion' : 'user';
        include APP_ROOT . '/app/views/partials/order_actions.php';
        ?>
      <?php else: ?>
        <div class="order-actions">
          <span class="muted">管理员查看模式（前台不提供操作）</span>
          <a class="btn btn-ghost btn-sm" href="<?= e(admin_url('order/detail', ['id' => (int) $order['id']])) ?>">到后台处理该订单</a>
        </div>
      <?php endif; ?>

      <?php if ($is_user && in_array($status, [2, 3, 4], true)): ?>
        <form class="refund-form" method="post" action="<?= e(url('order/refund')) ?>" data-confirm="确定提交退款申请吗？平台会介入处理。">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int) $order['id'] ?>">
          <input type="text" name="reason" maxlength="200" placeholder="请填写退款原因，例如：陪玩未上线 / 服务未开始" required>
          <button class="btn btn-danger btn-sm" type="submit">申请退款</button>
        </form>
      <?php endif; ?>

      <?php if ($status === 6 && $order['refund_reason'] !== ''): ?>
        <p class="hint">退款申请已提交：<?= e($order['refund_reason']) ?>，平台会尽快处理。</p>
      <?php endif; ?>
    </section>

    <section class="card">
      <div class="card-head">
        <h2 class="card-title">订单沟通</h2>
        <?php if ((int) $order['companion_id'] > 0): ?>
          <span class="muted" style="font-size:12.5px">接单陪玩：<?= e((string) ($order['companion_nickname'] ?? '')) ?></span>
        <?php endif; ?>
      </div>
      <?php
      $me = \App\Auth::id();
      include APP_ROOT . '/app/views/partials/order_chat.php';
      ?>
    </section>

    <?php if ($is_user && $status === 5 && !$review): ?>
      <section class="card" id="review">
        <h2 class="card-title">评价这次服务</h2>
        <form method="post" action="<?= e(url('review/create')) ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
          <div class="form-row">
            <label>评分</label>
            <div class="star-picker" data-input="score">
              <?php for ($i = 1; $i <= 5; $i++): ?>
                <span class="star" data-value="<?= $i ?>">★</span>
              <?php endfor; ?>
              <input type="hidden" name="score" value="5">
            </div>
          </div>
          <div class="form-row">
            <label>标签</label>
            <div class="chip-row">
              <?php foreach (['技术好', '声音甜', '有耐心', '准时', '幽默', '带飞'] as $tag): ?>
                <label class="chip chip-check"><input type="checkbox" name="tags[]" value="<?= e($tag) ?>"><span><?= e($tag) ?></span></label>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="form-row">
            <label>评价内容</label>
            <textarea name="content" rows="3" maxlength="500" placeholder="说说这次服务体验（选填）"></textarea>
          </div>
          <label class="check"><input type="checkbox" name="is_anonymous" value="1"> 匿名评价</label>
          <button class="btn btn-primary" type="submit">提交评价</button>
        </form>
      </section>
    <?php endif; ?>

    <?php if ($review): ?>
      <section class="card">
        <h2 class="card-title">我的评价</h2>
        <div class="review-item">
          <div class="review-head">
            <span class="stars"><?= str_repeat('★', (int) $review['score']) ?></span>
            <span class="muted"><?= e((string) $review['created_at']) ?></span>
          </div>
          <p class="review-content"><?= nl2br(e((string) $review['content'])) ?></p>
          <?php if ($review['reply'] !== ''): ?>
            <div class="review-reply"><strong>陪玩回复：</strong><?= nl2br(e((string) $review['reply'])) ?></div>
          <?php elseif ($is_comp): ?>
            <form method="post" action="<?= e(url('review/reply')) ?>" class="reply-form">
              <?= csrf_field() ?>
              <input type="hidden" name="review_id" value="<?= (int) $review['id'] ?>">
              <input type="text" name="reply" placeholder="回复用户评价" maxlength="500">
              <button class="btn btn-primary btn-sm" type="submit">回复</button>
            </form>
          <?php endif; ?>
        </div>
      </section>
    <?php endif; ?>
  </div>

  <aside class="detail-side">
    <section class="card">
      <h2 class="card-title">订单进度</h2>
      <ul class="timeline">
        <?php foreach ($logs as $log): ?>
          <li>
            <div class="tl-title"><?= e(order_status_text((int) $log['to_status'])) ?></div>
            <div class="tl-meta muted">
              <?= e($operatorText[$log['operator_type']] ?? $log['operator_type']) ?>
              · <?= e((string) $log['created_at']) ?>
            </div>
            <?php if ($log['remark'] !== ''): ?><div class="tl-remark"><?= e($log['remark']) ?></div><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>

    <section class="card">
      <h2 class="card-title">时间记录</h2>
      <ul class="stat-list">
        <li><span>下单</span><strong><?= e((string) $order['created_at']) ?></strong></li>
        <li><span>支付</span><strong><?= e((string) ($order['pay_time'] ?: '-')) ?></strong></li>
        <li><span>接单</span><strong><?= e((string) ($order['accept_time'] ?: '-')) ?></strong></li>
        <li><span>完成</span><strong><?= e((string) ($order['finish_time'] ?: '-')) ?></strong></li>
        <li><span>确认</span><strong><?= e((string) ($order['confirm_time'] ?: '-')) ?></strong></li>
      </ul>
    </section>
  </aside>
</div>
