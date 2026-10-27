<?php
/**
 * 工单对话（前台与后台共用）
 * @var array  $thread
 * @var string $mode user | admin（决定哪一方的消息靠右显示）
 *
 * 发送方类型：
 *   user  用户
 *   admin 网页端后台的管理员
 *   kf    客服工作台（/kf/）里的客服 —— 对用户同样显示为「客服」
 */
$__mode = $mode ?? 'user';
?>
<div class="chat">
  <?php foreach ($thread as $msg): ?>
    <?php
    $__isStaff = in_array((string) $msg['sender_type'], ['admin', 'kf'], true);
    $__mine    = ($__mode === 'admin') ? $__isStaff : ((string) $msg['sender_type'] === 'user');

    // 客服显示名：工单部门账号取 kf_staff.name，后台管理员取 sender_name，都没有就笼统叫「客服」
    $__staffName = trim((string) ($msg['staff_name'] ?? ''));
    if ($__staffName === '') {
        $__staffName = trim((string) ($msg['sender_name'] ?? ''));
    }
    $__who = $__isStaff ? ($__staffName !== '' ? $__staffName : '客服') : '我';
    ?>
    <div class="chat-item<?= $__mine ? ' mine' : '' ?>">
      <img class="chat-avatar" src="<?= e(avatar_url($msg['avatar'] ?? '', (string) ($msg['nickname'] ?? '用户'))) ?>" alt="">
      <div class="chat-body">
        <div class="chat-meta">
          <?= e($__who) ?>
          · <?= e((string) $msg['created_at']) ?>
        </div>
        <div class="chat-bubble"><?= nl2br(e((string) $msg['content'])) ?></div>
        <?php
        // 附件：图片直接显示，视频给播放器，其它给下载链接
        $__files = json_decode((string) ($msg['attachments'] ?? ''), true);
        if (is_array($__files) && $__files):
        ?>
          <div class="chat-files" style="margin-top:6px;display:flex;flex-wrap:wrap;gap:8px">
            <?php foreach ($__files as $__f): ?>
              <?php
              $__f = (string) $__f;
              if ($__f === '') {
                  continue;
              }
              ?>
              <?php if (\App\Upload::isImage($__f)): ?>
                <a href="<?= e(upload_url($__f)) ?>" target="_blank" rel="noopener">
                  <img src="<?= e(upload_url($__f)) ?>" alt="附件图片"
                       style="max-width:160px;max-height:160px;border-radius:10px;display:block">
                </a>
              <?php elseif (\App\Upload::isVideo($__f)): ?>
                <video src="<?= e(upload_url($__f)) ?>" controls preload="metadata"
                       style="max-width:280px;border-radius:10px"></video>
              <?php else: ?>
                <a href="<?= e(upload_url($__f)) ?>" target="_blank" rel="noopener" style="font-size:13px">
                  📎 <?= e(basename($__f)) ?>
                </a>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>
