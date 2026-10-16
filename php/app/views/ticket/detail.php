<?php
/**
 * 工单详情（用户侧）
 * @var array $ticket
 * @var array $thread
 */
$status = (int) $ticket['status'];
?>
<div class="page-head">
  <div class="head-row">
    <div>
      <h1><?= e($ticket['title']) ?></h1>
      <p class="muted">
        <?= e($ticket['ticket_no']) ?> ·
        <?= e(\App\TicketService::categoryText((string) $ticket['category'])) ?> ·
        <span class="status status-<?= e(\App\TicketService::statusClass($status)) ?>"><?= e(\App\TicketService::statusText($status)) ?></span>
        · 提交于 <?= e((string) $ticket['created_at']) ?>
      </p>
    </div>
    <div class="head-actions">
      <a class="btn btn-ghost" href="<?= e(url('ticket/index')) ?>">返回列表</a>
      <?php if ($status !== 3): ?>
        <form method="post" action="<?= e(url('ticket/close')) ?>" class="inline-form" data-confirm="关闭后不能再回复，确定吗？">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int) $ticket['id'] ?>">
          <button class="btn btn-ghost" type="submit">关闭工单</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="detail-grid">
  <div class="detail-main">
    <section class="card">
      <h2 class="card-title">对话记录</h2>
      <?php
      $mode = 'user';
      include APP_ROOT . '/app/views/partials/ticket_thread.php';
      ?>
    </section>

    <?php if ($status !== 3): ?>
      <section class="card">
        <h2 class="card-title">继续追问</h2>
        <form method="post" action="<?= e(url('ticket/reply')) ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int) $ticket['id'] ?>">
          <div class="form-row">
            <textarea name="content" rows="3" maxlength="1000" placeholder="补充说明或追问，客服会继续回复" required></textarea>
          </div>
          <button class="btn btn-primary" type="submit">发送</button>
        </form>
      </section>
    <?php else: ?>
      <div class="card empty">工单已关闭。如果问题还在，请 <a href="<?= e(url('ticket/index')) ?>">提交新工单</a>。</div>
    <?php endif; ?>
  </div>

  <aside class="detail-side">
    <section class="card">
      <h2 class="card-title">工单信息</h2>
      <ul class="ticket-meta-list">
        <li><span>单号</span><strong><?= e($ticket['ticket_no']) ?></strong></li>
        <li><span>类型</span><strong><?= e(\App\TicketService::categoryText((string) $ticket['category'])) ?></strong></li>
        <li><span>状态</span><strong><?= e(\App\TicketService::statusText($status)) ?></strong></li>
        <li><span>提交时间</span><strong><?= e((string) $ticket['created_at']) ?></strong></li>
        <li><span>最后更新</span><strong><?= e((string) $ticket['updated_at']) ?></strong></li>
        <li><span>处理客服</span><strong><?= e($ticket['admin_nickname'] !== null && $ticket['admin_nickname'] !== '' ? (string) $ticket['admin_nickname'] : '待分配') ?></strong></li>
        <?php if ((int) $ticket['order_id'] > 0): ?>
          <li><span>关联订单</span><strong><a href="<?= e(url('order/detail', ['id' => (int) $ticket['order_id']])) ?>">#<?= (int) $ticket['order_id'] ?></a></strong></li>
        <?php endif; ?>
      </ul>
    </section>
  </aside>
</div>
