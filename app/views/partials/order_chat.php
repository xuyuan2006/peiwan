<?php
/**
 * 订单聊天卡片（对话记录 + 输入框）
 *
 * 下单方与接单陪玩用的是同一个页面：谁的 id 等于 $me，谁的消息就靠右。
 * @var array $order
 * @var array $messages
 * @var int   $me
 * @var bool  $chat_open
 */
$__compName = (string) ($order['companion_nickname'] ?? '');
$__userName = (string) ($order['user_nickname'] ?? '');
$__isUser   = (int) $order['user_id'] === (int) $me;
$__who      = $__isUser
    ? ($__compName !== '' ? $__compName : '陪玩')
    : ($__userName !== '' ? $__userName : '下单用户');
?>
<div class="chat chat-box" id="chat-list">
  <?php include APP_ROOT . '/app/views/partials/order_chat_list.php'; ?>
</div>

<?php if (!empty($chat_open)): ?>
  <form id="chat-form" method="post" action="<?= e(url('order/sendMessage')) ?>"
        data-order-id="<?= (int) $order['id'] ?>"
        data-count="<?= count($messages) ?>"
        data-poll="<?= e(url('order/pollMessages')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $order['id'] ?>">
    <div class="chat-composer">
      <textarea name="content" rows="1" maxlength="500" placeholder="和 <?= e($__who) ?> 说点什么…（Enter 发送 / Shift+Enter 换行）"></textarea>
      <button class="btn btn-primary" type="submit">发送</button>
    </div>
    <p class="hint">
      对方回复后这里会自动刷新，订单列表也会出现未读提示。
      请勿在站外私下交易，出现问题平台无法介入。
    </p>
  </form>
<?php else: ?>
  <p class="hint">
    <?php if ((int) $order['companion_id'] <= 0): ?>
      陪玩接单后就能在这里和他聊天了（约时间、报区服、同步进度）。
    <?php else: ?>
      该订单当前状态不能在站内发言（已关闭或退款中）。有问题可以
      <a href="#" data-ticket-open data-order-id="<?= (int) $order['id'] ?>">提交工单</a> 让客服介入。
    <?php endif; ?>
  </p>
<?php endif; ?>
