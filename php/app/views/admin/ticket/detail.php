<?php
/**
 * 后台工单详情
 * @var array $ticket
 * @var array $thread
 * @var array $categories
 */
$status = (int) $ticket['status'];
?>
<div class="admin-detail-grid">
  <div>
    <section class="card">
      <div class="order-detail-head">
        <span class="status status-<?= e(\App\TicketService::statusClass($status)) ?>"><?= e(\App\TicketService::statusText($status)) ?></span>
        <span class="tag"><?= e(\App\TicketService::categoryText((string) $ticket['category'])) ?></span>
        <strong><?= e($ticket['title']) ?></strong>
        <span class="muted"><?= e($ticket['ticket_no']) ?></span>
      </div>
      <p class="muted">提交时间 <?= e((string) $ticket['created_at']) ?> · 最后更新 <?= e((string) $ticket['updated_at']) ?></p>
    </section>

    <section class="card">
      <h2 class="card-title">对话记录</h2>
      <?php
      $mode = 'admin';
      include APP_ROOT . '/app/views/partials/ticket_thread.php';
      ?>
    </section>

    <section class="card">
      <h2 class="card-title">回复用户</h2>
      <?php if ($status === 3): ?>
        <div class="alert alert-warning">工单已关闭，可先把状态改回「处理中」再回复。</div>
      <?php endif; ?>
      <form method="post" action="<?= e(admin_url('ticket/reply')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $ticket['id'] ?>">
        <div class="form-row">
          <textarea name="content" rows="4" maxlength="1000" placeholder="回复内容会直接展示给用户" required></textarea>
        </div>
        <button class="btn btn-primary" type="submit">发送回复</button>
      </form>
    </section>
  </div>

  <aside>
    <section class="card">
      <h2 class="card-title">工单信息</h2>
      <ul class="ticket-meta-list">
        <li><span>单号</span><strong><?= e($ticket['ticket_no']) ?></strong></li>
        <li><span>用户</span><strong><?= e($ticket['nickname'] ?? ('#' . (int) $ticket['user_id'])) ?></strong></li>
        <li><span>手机号</span><strong><?= e((string) $ticket['phone']) ?></strong></li>
        <li><span>邮箱</span><strong><?= e((string) $ticket['email']) ?></strong></li>
        <li><span>类型</span><strong><?= e(\App\TicketService::categoryText((string) $ticket['category'])) ?></strong></li>
        <li><span>处理客服</span><strong><?= e((string) ($ticket['admin_nickname'] ?? '') !== '' ? (string) $ticket['admin_nickname'] : '未分配') ?></strong></li>
        <?php if ((int) $ticket['order_id'] > 0): ?>
          <li><span>关联订单</span><strong><a href="<?= e(admin_url('order/detail', ['id' => (int) $ticket['order_id']])) ?>">#<?= (int) $ticket['order_id'] ?></a></strong></li>
        <?php endif; ?>
      </ul>
      <p style="margin-top:12px">
        <a class="btn btn-ghost btn-block" href="<?= e(admin_url('user/detail', ['id' => (int) $ticket['user_id']])) ?>">查看该用户</a>
      </p>
    </section>

    <section class="card">
      <h2 class="card-title">变更状态</h2>
      <div class="btn-group">
        <?php foreach ([1 => '标记处理中', 2 => '标记已解决', 3 => '关闭工单', 0 => '退回待处理'] as $s => $label): ?>
          <?php if ($s === $status) { continue; } ?>
          <form method="post" action="<?= e(admin_url('ticket/status')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $ticket['id'] ?>">
            <input type="hidden" name="status" value="<?= (int) $s ?>">
            <button class="btn <?= $s === 2 ? 'btn-primary' : 'btn-ghost' ?> btn-block" type="submit"><?= e($label) ?></button>
          </form>
        <?php endforeach; ?>
      </div>
    </section>
  </aside>
</div>
