<?php
/** @var int $code */
/** @var string $msg */
?>
<div class="card empty-card">
  <div class="empty-code"><?= (int) $code ?></div>
  <p><?= e($msg !== '' ? $msg : '页面不存在或已被移除') ?></p>
  <a class="btn btn-primary" href="<?= e(url('home/index')) ?>">返回首页</a>
</div>
