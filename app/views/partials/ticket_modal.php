<?php
/**
 * 浮动「在线咨询」按钮 + 工单提交卡片（点开是卡片，不跳页）
 * 视图在全局命名空间执行，类引用必须写全限定名 \App\X
 * @var string $__route
 */
$__cats    = \App\TicketService::categories();
$__logged  = \App\Auth::check();
$__default = 'presale';
$__kfEntry = \App\TicketService::kfChatUrl();
?>
<a class="ticket-fab" id="ticket-fab" href="<?= e(url('ticket/index')) ?>" aria-label="提交工单">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <path d="M21 11.5a8.4 8.4 0 0 1-8.5 8.4 9 9 0 0 1-3.7-.8L3.5 20.5l1.5-5a8.2 8.2 0 0 1-.9-3.8A8.4 8.4 0 0 1 12.6 3 8.4 8.4 0 0 1 21 11.5z"/>
  </svg>
  在线咨询
</a>

<div class="ticket-mask" id="ticket-mask" hidden>
  <div class="ticket-card" role="dialog" aria-modal="true" aria-labelledby="ticket-card-title">
    <div class="ticket-card-head">
      <h3 id="ticket-card-title">提交工单</h3>
      <button type="button" class="ticket-close" id="ticket-close" aria-label="关闭">×</button>
    </div>

    <?php if (!$__logged): ?>
      <p class="muted">登录后就能提交工单，客服会在这里回复你。</p>
      <a class="btn btn-primary btn-block" href="<?= e(url('auth/login')) ?>">去登录</a>
    <?php else: ?>
      <form id="ticket-form" method="post" action="<?= e(url('ticket/create')) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="order_id" id="ticket-order-id" value="">

        <div class="form-row">
          <label>问题类型</label>
          <div class="chip-row">
            <?php foreach ($__cats as $key => $cat): ?>
              <label class="chip chip-check">
                <input type="radio" name="category" value="<?= e($key) ?>"
                       data-hint="<?= e($cat[1]) ?>" <?= $key === $__default ? 'checked' : '' ?>>
                <span><?= e($cat[0]) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
          <p class="hint" id="ticket-cat-hint"><?= e($__cats[$__default][1]) ?></p>
        </div>

        <div class="form-row">
          <label>标题</label>
          <input type="text" name="title" maxlength="100" placeholder="一句话说明问题，例如：订单一直没人接单" required>
        </div>

        <div class="form-row">
          <label>详细描述</label>
          <textarea name="content" rows="4" maxlength="1000" placeholder="把情况说清楚，客服处理更快（有订单号也可以写上）" required></textarea>
        </div>

        <div class="form-row">
          <label>附件（可选）</label>
          <input type="file" name="attachments[]" multiple
                 accept="image/*,video/mp4,video/webm,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.rar,.7z">
          <p class="hint">图片 / 视频 / 文档，最多 5 个</p>
        </div>

        <button class="btn btn-primary btn-block" type="submit">提交工单</button>
        <p class="hint" style="text-align:center;margin-top:8px">
          也可以到 <a href="<?= e(url('ticket/index')) ?>">我的工单</a> 查看历史与回复
        </p>
      </form>
      <?php if ($__kfEntry !== ''): ?>
        <a class="btn btn-ghost btn-block" style="margin-top:10px"
           href="<?= e($__kfEntry) ?>" target="_blank">
          在线客服
        </a>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
