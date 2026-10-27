<?php
/**
 * 后台订单列表
 * @var array $result
 * @var array $filter
 * @var array $counts
 */
$rows      = $result['rows'];
$total     = (int) $result['total'];
$page      = (int) $result['page'];
$perPage   = (int) $result['per_page'];
$totalPage = (int) ceil($total / max(1, $perPage));
?>
<div class="summary-row">
  <span>订单总数 <strong><?= (int) ($counts['total'] ?? 0) ?></strong></span>
  <span>待支付 <strong><?= (int) ($counts['pending_pay'] ?? 0) ?></strong></span>
  <span>进行中 <strong><?= (int) ($counts['serving'] ?? 0) ?></strong></span>
  <span>退款中 <strong><?= (int) ($counts['refunding'] ?? 0) ?></strong></span>
  <span>已完成 <strong><?= (int) ($counts['completed'] ?? 0) ?></strong></span>
  <a class="btn btn-primary btn-sm" href="<?= e(admin_url('order/create')) ?>" style="margin-left:auto">代客下单</a>
  <a class="btn btn-ghost btn-sm" href="<?= e(admin_url('order/publish')) ?>">发布接单任务</a>
</div>

<form class="filter-bar" method="get" action="<?= e(admin_url('order/index')) ?>">
  <input type="hidden" name="r" value="order/index">
  <label>订单状态
    <select name="status">
      <option value="">全部状态</option>
      <?php foreach (order_status_map() as $s => $meta): ?>
        <option value="<?= (int) $s ?>" <?= (string) $filter['status'] === (string) $s ? 'selected' : '' ?>><?= e($meta[0]) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>订单号 / 用户 / 陪玩
    <input type="text" name="keyword" value="<?= e($filter['keyword']) ?>" placeholder="支持模糊搜索">
  </label>
  <label>开始日期
    <input type="date" name="date_start" value="<?= e($filter['date_start']) ?>">
  </label>
  <label>结束日期
    <input type="date" name="date_end" value="<?= e($filter['date_end']) ?>">
  </label>
  <div class="actions">
    <button class="btn btn-primary btn-sm" type="submit">查询</button>
    <a class="btn btn-ghost btn-sm" href="<?= e(admin_url('order/index')) ?>">重置</a>
  </div>
</form>

<div class="table-card">
  <div class="summary-row" style="padding-top:12px;align-items:center">
    <span>当前条件共 <strong><?= $total ?></strong> 笔</span>
    <a class="btn btn-ghost btn-sm" href="<?= e(admin_url('order/export', array_filter([
        'status'     => $filter['status'],
        'keyword'    => $filter['keyword'],
        'date_start' => $filter['date_start'],
        'date_end'   => $filter['date_end'],
    ]))) ?>">导出 CSV（按当前筛选，最多 5000 条）</a>
  </div>
  <table class="table">
    <thead>
      <tr>
        <th>订单号</th><th>用户</th><th>陪玩</th><th>服务内容</th>
        <th>金额</th><th>抽成 / 实收</th><th>状态</th><th>下单时间</th><th>操作</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $o): ?>
      <tr>
        <td class="nowrap"><?= e($o['order_no']) ?></td>
        <td><?= e($o['user_nickname']) ?></td>
        <td><?= e($o['companion_nickname']) ?></td>
        <td>
          <?= e($o['game_name']) ?>
          <div class="muted" style="font-size:12px"><?= e(str_limit((string) $o['skill_title'], 18)) ?></div>
        </td>
        <td><?= e(yuan((float) $o['amount'])) ?></td>
        <td class="muted">
          <?= e(money((float) $o['platform_fee'])) ?> / <?= e(money((float) $o['companion_income'])) ?>
        </td>
        <td><span class="status status-<?= e(order_status_class((int) $o['status'])) ?>"><?= e(order_status_text((int) $o['status'])) ?></span></td>
        <td class="muted nowrap"><?= e((string) $o['created_at']) ?></td>
        <td>
          <div class="cell-actions">
            <a class="btn btn-ghost btn-sm" href="<?= e(admin_url('order/detail', ['id' => (int) $o['id']])) ?>">详情</a>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?>
      <tr><td colspan="9" class="empty">没有符合条件的订单</td></tr>
    <?php endif; ?>
    </tbody>
  </table>

  <?= pager($page, $total, $perPage) ?>
</div>
