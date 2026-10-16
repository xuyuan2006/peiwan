<?php
/**
 * 工单对话（前台与后台共用）
 * @var array  $thread
 * @var string $mode user | admin（决定哪一方的消息靠右显示）
 */
$__mode = $mode ?? 'user';
?>
<div class="chat">
  <?php foreach ($thread as $msg): ?>
    <?php $__mine = ($__mode === 'admin') ? ($msg['sender_type'] === 'admin') : ($msg['sender_type'] === 'user'); ?>
    <div class="chat-item<?= $__mine ? ' mine' : '' ?>">
      <img class="chat-avatar" src="<?= e(avatar_url($msg['avatar'] ?? '', (string) ($msg['nickname'] ?? '用户'))) ?>" alt="">
      <div class="chat-body">
        <div class="chat-meta">
          <?= e($msg['sender_type'] === 'admin' ? '客服' : '我') ?>
          · <?= e((string) $msg['created_at']) ?>
        </div>
        <div class="chat-bubble"><?= nl2br(e((string) $msg['content'])) ?></div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
