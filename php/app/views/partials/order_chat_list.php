<?php
/**
 * 订单聊天记录（首次渲染与轮询共用同一份，保证两边长得一样）
 * @var array $messages
 * @var int   $me
 */
?>
<?php if (!$messages): ?>
  <p class="muted" style="font-size:13px">还没有消息。可以在这里约时间、报区服、同步进度。</p>
<?php else: ?>
  <?php foreach ($messages as $m): ?>
    <?php $__mine = (int) $m['sender_id'] === (int) $me; ?>
    <div class="chat-item<?= $__mine ? ' mine' : '' ?>">
      <img class="chat-avatar" src="<?= e(avatar_url($m['avatar'] ?? '', (string) ($m['nickname'] ?? '用户'))) ?>" alt="">
      <div class="chat-body">
        <div class="chat-meta">
          <?php
          if ($__mine) {
              echo '我';
          } elseif ((string) $m['sender_role'] === 'admin') {
              echo '客服';
          } else {
              echo e((string) ($m['nickname'] ?? '对方'));
          }
          ?>
          · <?= e((string) $m['created_at']) ?>
        </div>
        <div class="chat-bubble"><?= nl2br(e((string) $m['content'])) ?></div>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
