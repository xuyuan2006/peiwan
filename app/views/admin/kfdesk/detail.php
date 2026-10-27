<?php
/**
 * 工单部门 · 工单详情（回复 / 派单 / 改状态）
 * @var array $actor
 * @var array $ticket
 * @var array $thread
 * @var array $targets
 * @var bool  $canReply
 * @var bool  $canAssign
 */
$id = (int) $ticket['id'];
$st = (int) $ticket['status'];

// 转接对象分组：组长（优先）在前，客服按组排在后面
$grouped = [];
foreach (($targets ?? []) as $tg) {
    $grouped[(string) ($tg['group'] ?? '')][] = $tg;
}
?>
<p><a class="btn btn-ghost btn-sm" href="<?= e(admin_url('kf_desk/index')) ?>">← 返回工单列表</a></p>

<div class="summary-row">
  <span>单号 <strong><?= e((string) $ticket['ticket_no']) ?></strong></span>
  <span>类型 <?= e(\App\TicketService::categoryText((string) $ticket['category'])) ?></span>
  <span>客户 <strong><?= e((string) ($ticket['nickname'] ?? ('#' . (int) $ticket['user_id']))) ?></strong></span>
  <span>处理人 <strong><?= e((string) ($ticket['staff_name'] ?? '未分配')) ?></strong></span>
  <span class="status status-<?= e(\App\TicketService::statusClass($st)) ?>"><?= e(\App\TicketService::statusText($st)) ?></span>
</div>

<div class="grid-2">
  <div class="card">
    <h2 class="card-title"><?= e((string) $ticket['title']) ?></h2>
    <p class="muted">提交于 <?= e((string) $ticket['created_at']) ?></p>

    <?php $mode = 'admin'; include APP_ROOT . '/app/views/partials/ticket_thread.php'; ?>

    <?php if ($canReply): ?>
      <form method="post" action="<?= e(admin_url('kf_desk/reply')) ?>" enctype="multipart/form-data" style="margin-top:14px">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <textarea name="content" rows="5" placeholder="回复客户…" required></textarea>
        <div class="form-row" style="margin-top:8px">
          <input type="file" name="attachments[]" multiple
                 accept="image/*,video/mp4,video/webm,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.rar,.7z">
          <p class="hint">可附图片 / 视频 / 文档，最多 5 个（图片 ≤5MB，视频 ≤50MB，文档 ≤20MB）</p>
        </div>
        <div class="actions" style="margin-top:10px">
          <button class="btn btn-primary" type="submit">发送回复</button>
        </div>
      </form>
    <?php else: ?>
      <p class="muted">这张工单不在你手上，只能查看。</p>
    <?php endif; ?>
  </div>

  <aside>
    <?php if ($canAssign): ?>
      <div class="card">
        <h2 class="card-title">派单 / 转单</h2>
        <?php if (!$targets): ?>
          <p class="muted">没有可派单的客服：主管还没分配客服，或客服账号被停用/不在你组里。</p>
        <?php else: ?>
          <form method="post" action="<?= e(admin_url('kf_desk/assign')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <label>交给
              <select name="staff_id" required>
                <option value="">选择转接对象…</option>
                <?php foreach ($grouped as $gname => $items): ?>
                  <optgroup label="<?= e($gname) ?>">
                    <?php foreach ($items as $s): ?>
                      <option value="<?= (int) $s['id'] ?>" <?= (int) $ticket['staff_id'] === (int) $s['id'] ? 'selected' : '' ?>>
                        <?= e((string) $s['label']) ?>
                      </option>
                    <?php endforeach; ?>
                  </optgroup>
                <?php endforeach; ?>
              </select>
            </label>
            <button class="btn btn-primary btn-sm" type="submit">确定</button>
          </form>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="card">
      <h2 class="card-title">状态</h2>
      <div class="actions" style="display:flex;gap:8px;flex-wrap:wrap">
        <?php foreach ([0 => '待处理', 1 => '处理中', 2 => '已解决', 3 => '已关闭'] as $k => $v): ?>
          <form method="post" action="<?= e(admin_url('kf_desk/status')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="status" value="<?= $k ?>">
            <button class="btn btn-sm <?= $st === $k ? 'btn-primary' : 'btn-ghost' ?>" type="submit" <?= $st === $k ? 'disabled' : '' ?>>
              <?= e($v) ?>
            </button>
          </form>
        <?php endforeach; ?>
      </div>
    </div>
  </aside>
</div>
