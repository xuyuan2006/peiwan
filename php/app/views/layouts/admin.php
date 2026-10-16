<?php
/**
 * 后台布局
 * @var string $content
 */
$__me      = user();
$__meName  = $__me !== null ? (string) $__me['nickname'] : '未登录';
$__meRole  = $__me !== null && (string) $__me['role'] === 'admin' ? '管理员' : '用户';
$__flashes = take_flashes();
$__route   = (string) ($_GET['r'] ?? 'dashboard/index');
$__title   = isset($title) && $title !== '' ? $title : '管理后台';

$__pending = ['companion' => 0, 'recharge' => 0, 'withdraw' => 0, 'refund' => 0, 'ticket' => 0];
try {
    $__pending['companion'] = (int) \App\Db::value('SELECT COUNT(*) FROM companion_profiles WHERE audit_status = 0');
    $__pending['recharge']  = (int) \App\Db::value('SELECT COUNT(*) FROM recharge_orders WHERE status = 0');
    $__pending['withdraw']  = (int) \App\Db::value('SELECT COUNT(*) FROM withdraw_orders WHERE status = 0');
    $__pending['refund']    = (int) \App\Db::value('SELECT COUNT(*) FROM orders WHERE status = ?', [\App\OrderService::STATUS_REFUNDING]);
    $__pending['ticket']    = \App\TicketService::pendingCount();
} catch (\Throwable $e) {
    // 忽略统计异常
}

// [路由, 文案, 角标, 命中模式, 分组]；顺序即优先级，第一个命中的菜单项高亮
$__nav = [
    ['dashboard/index', '数据概览', '', ['dashboard/'], '概览'],
    ['order/index', '订单管理', '', ['order/'], '交易'],
    ['ticket/index', '工单管理', $__pending['ticket'] > 0 ? $__pending['ticket'] : '', ['ticket/'], '交易'],
    ['recharge/index', '充值审核', $__pending['recharge'] > 0 ? $__pending['recharge'] : '', ['recharge/'], '交易'],
    ['withdraw/index', '提现审核', $__pending['withdraw'] > 0 ? $__pending['withdraw'] : '', ['withdraw/'], '交易'],
    ['review/index', '评价管理', '', ['review/'], '交易'],
    ['content/index', '公告管理', '', ['content/index', 'content/saveAnnouncement', 'content/deleteAnnouncement'], '运营'],
    ['content/banners', '广告管理', '', ['content/banners', 'content/saveBanner', 'content/deleteBanner'], '运营'],
    ['content/packages', '点单套餐', '', ['content/packages', 'content/savePackage', 'content/deletePackage'], '运营'],
    ['game/index', '游戏管理', '', ['game/'], '运营'],
    ['skill/index', '技能管理', '', ['skill/'], '运营'],
    ['user/index', '用户管理', '', ['user/'], '用户'],
    ['companion/index', '陪玩管理', $__pending['companion'] > 0 ? $__pending['companion'] : '', ['companion/'], '用户'],
    ['payment/index', '支付设置', '', ['payment/'], '系统'],
    ['setting/index', '平台设置', '', ['setting/'], '系统'],
    ['log/index', '操作日志', '', ['log/'], '系统'],
    ['account/index', '账号设置', '', ['account/'], '系统'],
];

$__activeNav = active_index($__route, $__nav);
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($__title) ?> - 管理后台</title>
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="admin-body">
<div class="admin-wrap">
  <aside class="sidebar">
    <div class="sidebar-brand">
      <span class="brand-dot"></span>
      <span><?= e(site_name()) ?></span>
    </div>
    <nav class="sidebar-nav">
      <?php $__lastGroup = ''; ?>
      <?php foreach ($__nav as $__i => $item): ?>
        <?php $__group = (string) ($item[4] ?? ''); ?>
        <?php if ($__group !== '' && $__group !== $__lastGroup): $__lastGroup = $__group; ?>
          <div class="sidebar-group"><?= e($__group) ?></div>
        <?php endif; ?>
        <a href="<?= e(admin_url($item[0])) ?>" class="<?= $__i === $__activeNav ? 'active' : '' ?>">
          <span><?= e($item[1]) ?></span>
          <?php if ($item[2] !== ''): ?><em class="nav-badge"><?= (int) $item[2] ?></em><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar-foot">
      <a href="<?= e(url('home/index')) ?>" target="_blank">访问前台 ↗</a>
    </div>
  </aside>

  <div class="admin-right">
    <header class="admin-topbar">
      <h1><?= e($__title) ?></h1>

      <form class="admin-search" method="get" action="<?= e(admin_url('order/index')) ?>">
        <input type="hidden" name="r" value="order/index">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
          <circle cx="11" cy="11" r="7"/><path d="m20 20-3.6-3.6"/>
        </svg>
        <input type="text" name="keyword" placeholder="搜索订单号 / 用户 / 陪玩昵称…">
      </form>

      <div class="admin-topbar-right">
        <?php if ($__pending['refund'] > 0): ?>
          <a class="pill pill-warn" href="<?= e(admin_url('order/index', ['status' => 6])) ?>">退款待处理 <?= (int) $__pending['refund'] ?></a>
        <?php endif; ?>

        <a class="admin-bell" href="<?= e(admin_url('ticket/index')) ?>" title="待处理工单">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M18 8.5a6 6 0 1 0-12 0c0 5-2 6.5-2 6.5h16s-2-1.5-2-6.5"/><path d="M10.5 19a2 2 0 0 0 3 0"/>
          </svg>
          <?php if ($__pending['ticket'] > 0): ?><em class="bell-dot"><?= (int) $__pending['ticket'] ?></em><?php endif; ?>
        </a>

        <a class="admin-user" href="<?= e(admin_url('account/index')) ?>">
          <span class="admin-avatar"><?= e(mb_substr($__meName, 0, 1)) ?></span>
          <span class="admin-user-info">
            <strong><?= e($__meName) ?></strong>
            <small><?= e($__meRole) ?></small>
          </span>
        </a>

        <a class="btn btn-ghost btn-sm" href="<?= e(admin_url('auth/logout')) ?>">退出</a>
      </div>
    </header>

    <main class="admin-content">
      <?php foreach ($__flashes as $__f): ?>
        <div class="alert alert-flash alert-<?= e($__f['type']) ?>"><?= e($__f['msg']) ?></div>
      <?php endforeach; ?>

      <?= $content ?>
    </main>
  </div>
</div>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
