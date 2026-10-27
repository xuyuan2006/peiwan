<?php
/**
 * 我的工单
 * @var array $result
 * @var array $categories
 */
$rows      = $result['rows'];
$total     = (int) $result['total'];
$page      = (int) $result['page'];
$perPage   = (int) $result['per_page'];
$totalPage = (int) ceil($total / max(1, $perPage));
?>
<div class="page-head">
  <div class="head-row">
    <div>
      <h1>我的工单</h1>
      <p class="muted">售前咨询 / 售后问题 / 投诉建议，客服会在这里回复你</p>
    </div>
    <div class="head-actions">
      <a class="btn btn-primary" href="#ticket-new">提交新工单</a>
    </div>
  </div>
</div>

<section class="card" id="ticket-new">
  <h2 class="card-title">提交新工单</h2>
  <form class="ticket-inline-form" method="post" action="<?= e(url('ticket/create')) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="form-row">
      <label>问题类型</label>
      <div class="chip-row">
        <?php foreach ($categories as $key => $cat): ?>
          <label class="chip chip-check">
            <input type="radio" name="category" value="<?= e($key) ?>" <?= $key === 'presale' ? 'checked' : '' ?>>
            <span><?= e($cat[0]) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
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
      <p class="hint">图片、视频、文档都行，最多 5 个（图片 ≤5MB，视频 ≤50MB，文档 ≤20MB）</p>
    </div>
    <button class="btn btn-primary" type="submit">提交工单</button>
    <p class="hint" style="margin-top:8px">也可以点右下角「在线咨询」，用快捷卡片直接提交。</p>
  </form>
</section>

<?php if (!$rows): ?>
  <div class="card empty">
    还没有工单。遇到问题可以随时点右下角「在线咨询」，或上面的「提交新工单」。
  </div>
<?php else: ?>
  <div class="order-list">
    <?php foreach ($rows as $t): ?>
      <a class="card order-card" href="<?= e(url('ticket/detail', ['id' => (int) $t['id']])) ?>" style="display:block;color:inherit">
        <div class="order-head">
          <span class="order-no"><?= e($t['ticket_no']) ?></span>
          <span class="status status-<?= e(\App\TicketService::statusClass((int) $t['status'])) ?>"><?= e(\App\TicketService::statusText((int) $t['status'])) ?></span>
          <span class="tag"><?= e(\App\TicketService::categoryText((string) $t['category'])) ?></span>
          <span class="muted order-time"><?= e((string) $t['created_at']) ?></span>
        </div>
        <div class="order-body">
          <div class="order-info">
            <div class="order-title"><?= e($t['title']) ?></div>
            <div class="order-sub muted"><?= e(str_limit((string) $t['content'], 60)) ?></div>
          </div>
          <div class="order-amount">
            <small class="muted"><?= (int) $t['reply_count'] ?> 条对话</small>
          </div>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?= pager($page, $total, $perPage) ?>
