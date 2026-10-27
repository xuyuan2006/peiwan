<?php
/**
 * 后台订单详情
 * @var array      $order
 * @var array      $logs
 * @var array|null $review
 */
$status = (int) $order['status'];
$operatorText = ['user' => '用户', 'companion' => '陪玩', 'admin' => '平台', 'system' => '系统'];
?>
<div class="admin-detail-grid">
  <div>
    <section class="card">
      <div class="order-detail-head">
        <span class="status status-<?= e(order_status_class($status)) ?>"><?= e(order_status_text($status)) ?></span>
        <span class="order-detail-amount"><?= e(yuan((float) $order['amount'])) ?></span>
        <span class="muted">订单号 <?= e($order['order_no']) ?></span>
      </div>

      <table class="table table-plain">
        <tr><th>下单用户</th>
          <td><a href="<?= e(admin_url('user/detail', ['id' => (int) $order['user_id']])) ?>"><?= e($order['user_nickname']) ?></a>
            <span class="muted"><?= e($order['user_phone'] !== '' ? $order['user_phone'] : '') ?></span></td>
          <th>陪玩</th>
          <td><a href="<?= e(admin_url('user/detail', ['id' => (int) $order['companion_id']])) ?>"><?= e($order['companion_nickname']) ?></a>
            <span class="muted"><?= e($order['companion_phone'] !== '' ? $order['companion_phone'] : '') ?></span></td>
        </tr>
        <tr><th>游戏</th><td><?= e($order['game_name']) ?></td>
          <th>服务</th><td><?= e($order['skill_title']) ?></td></tr>
        <tr><th>单价 × 数量</th>
          <td><?= e(yuan((float) $order['unit_price'])) ?> × <?= (int) $order['quantity'] ?> <?= e(service_unit_suffix((string) $order['service_type'])) ?></td>
          <th>服务类型</th><td><?= e(service_type_text((string) $order['service_type'])) ?></td></tr>
        <tr><th>平台抽成</th><td><?= e(yuan((float) $order['platform_fee'])) ?></td>
          <th>陪玩实收</th><td><?= e(yuan((float) $order['companion_income'])) ?></td></tr>
        <?php if ($order['user_remark'] !== ''): ?>
          <tr><th>订单备注</th><td colspan="3"><?= e($order['user_remark']) ?></td></tr>
        <?php endif; ?>
        <?php if ($order['refund_reason'] !== ''): ?>
          <tr><th>退款原因</th><td colspan="3"><?= e($order['refund_reason']) ?></td></tr>
        <?php endif; ?>
        <?php if ($order['refund_remark'] !== ''): ?>
          <tr><th>处理备注</th><td colspan="3"><?= e($order['refund_remark']) ?></td></tr>
        <?php endif; ?>
        <?php if ($order['cancel_reason'] !== ''): ?>
          <tr><th>关闭说明</th><td colspan="3"><?= e($order['cancel_reason']) ?></td></tr>
        <?php endif; ?>
      </table>
    </section>

    <section class="card">
      <h2 class="card-title">平台操作</h2>
      <div class="cell-actions" style="gap:14px;flex-wrap:wrap">
        <?php if ($status === 6): ?>
          <form method="post" action="<?= e(admin_url('order/refundAudit')) ?>" class="inline-form">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $order['id'] ?>">
            <input type="text" name="remark" placeholder="处理备注" style="padding:6px 10px;min-width:180px">
            <button class="btn btn-primary btn-sm" type="submit" name="agree" value="1">同意退款</button>
            <button class="btn btn-danger btn-sm" type="submit" name="agree" value="0">驳回退款</button>
          </form>
        <?php endif; ?>

        <?php if (in_array($status, [3, 4], true)): ?>
          <form method="post" action="<?= e(admin_url('order/complete')) ?>" class="inline-form"
                data-confirm="强制完成后将按抽成规则结算给陪玩，确定吗？">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $order['id'] ?>">
            <button class="btn btn-primary btn-sm" type="submit">强制完成并结算</button>
          </form>
        <?php endif; ?>

        <?php if (in_array($status, [1, 2, 3, 4], true)): ?>
          <form method="post" action="<?= e(admin_url('order/close')) ?>" class="inline-form"
                data-confirm="关闭订单会全额退款给用户，确定吗？">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $order['id'] ?>">
            <input type="text" name="reason" placeholder="关闭原因" value="平台关闭订单" style="padding:6px 10px;min-width:180px">
            <button class="btn btn-danger btn-sm" type="submit">关闭并退款</button>
          </form>
        <?php endif; ?>

        <?php if (!in_array($status, [1, 2, 3, 4, 6], true)): ?>
          <span class="muted">当前状态无需平台介入</span>
        <?php endif; ?>
      </div>
    </section>

    <section class="card">
      <div class="card-head">
        <h2 class="card-title">订单交流记录（<?= count($messages) ?> 条）</h2>
        <?php if ($order['companion_id'] > 0): ?>
          <span class="muted" style="font-size:12.5px">用户 ↔ 陪玩</span>
        <?php endif; ?>
      </div>

      <?php if (!$messages): ?>
        <p class="muted" style="font-size:13px">这单双方还没有在站内聊过（接单后才会开启站内聊天）。</p>
      <?php else: ?>
        <div class="chat" style="max-height:420px;overflow-y:auto;padding-right:4px">
          <?php
          $me = (int) \App\Auth::id();
          include APP_ROOT . '/app/views/partials/order_chat_list.php';
          ?>
        </div>
      <?php endif; ?>

      <form method="post" action="<?= e(admin_url('order/replyMessage')) ?>" style="margin-top:14px">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $order['id'] ?>">
        <div class="chat-composer">
          <textarea name="content" rows="1" maxlength="500"
                    placeholder="以「客服」身份介入，例如：请勿在站内留联系方式 / 请勿引导站外交易"></textarea>
          <button class="btn btn-primary" type="submit">发送</button>
        </div>
      </form>
      <p class="hint">
        记录只读展示，用于审查违规（引导站外交易、辱骂、作弊代练、留联系方式等）。
        你发出的内容会以「客服」身份同时出现在用户与陪玩的订单页，并记入后台操作日志。
      </p>
    </section>

    <?php if ($review): ?>
      <section class="card">
        <h2 class="card-title">订单评价</h2>
        <div class="review-item">
          <div class="review-head">
            <span class="stars"><?= str_repeat('★', (int) $review['score']) ?></span>
            <span class="muted"><?= e((string) $review['created_at']) ?></span>
            <span class="status status-<?= (int) $review['status'] === 1 ? 'success' : 'muted' ?>"><?= (int) $review['status'] === 1 ? '显示中' : '已隐藏' ?></span>
          </div>
          <p class="review-content"><?= nl2br(e((string) $review['content'])) ?></p>
          <?php if ($review['reply'] !== ''): ?>
            <div class="review-reply"><strong>陪玩回复：</strong><?= nl2br(e((string) $review['reply'])) ?></div>
          <?php endif; ?>
        </div>
      </section>
    <?php endif; ?>
  </div>

  <aside>
    <section class="card">
      <h2 class="card-title">时间记录</h2>
      <ul class="stat-list">
        <li><span>下单</span><strong><?= e((string) $order['created_at']) ?></strong></li>
        <li><span>支付</span><strong><?= e((string) ($order['pay_time'] ?: '-')) ?></strong></li>
        <li><span>接单</span><strong><?= e((string) ($order['accept_time'] ?: '-')) ?></strong></li>
        <li><span>完成</span><strong><?= e((string) ($order['finish_time'] ?: '-')) ?></strong></li>
        <li><span>确认</span><strong><?= e((string) ($order['confirm_time'] ?: '-')) ?></strong></li>
        <li><span>支付超时</span><strong><?= e((string) ($order['expire_at'] ?: '-')) ?></strong></li>
      </ul>
    </section>

    <section class="card">
      <h2 class="card-title">状态流转</h2>
      <ul class="timeline">
        <?php foreach ($logs as $log): ?>
          <li>
            <div class="tl-title"><?= e(order_status_text((int) $log['to_status'])) ?></div>
            <div class="tl-meta muted"><?= e($operatorText[$log['operator_type']] ?? $log['operator_type']) ?> · <?= e((string) $log['created_at']) ?></div>
            <?php if ($log['remark'] !== ''): ?><div class="tl-remark"><?= e($log['remark']) ?></div><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
  </aside>
</div>
