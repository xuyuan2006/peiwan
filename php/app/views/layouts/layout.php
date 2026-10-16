<?php
/**
 * 前台布局
 * @var string $content
 */
$__me      = user();
$__flashes = take_flashes();
$__title   = isset($title) && $title !== '' ? $title : site_name();
$__route   = (string) ($_GET['r'] ?? 'home/index');
$__notice  = (string) \App\Setting::get('site_notice', '');
// 订单聊天未读（底部 Tab / 顶部导航红点）
$__unread  = $__me ? \App\OrderService::unreadTotal((int) $__me['id']) : 0;
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($__title) ?></title>
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
</head>
<body>

<header class="topbar">
  <div class="topbar-inner">
    <?php
    // 站点 Logo：后台「平台设置 → 运营展示」里上传；没传就回落到默认的品牌圆点
    $__logo = (string) \App\Setting::get('site_logo', '');
    ?>
    <a class="brand" href="<?= e(url('home/index')) ?>">
      <?php if ($__logo !== ''): ?>
        <img src="<?= e(upload_url($__logo)) ?>" alt="<?= e(site_name()) ?>"
             style="height:26px;width:auto;max-width:120px;object-fit:contain;border-radius:6px;display:block">
      <?php else: ?>
        <span class="brand-dot"></span>
      <?php endif; ?>
      <?= e(site_name()) ?>
    </a>

    <?php
    // 顶部导航与底部 Tab 用同一套命中规则：精确匹配优先，第一个命中者高亮
    $__navItems = [
        ['home/index', '首页', ['home/index']],
        ['home/companions', '找陪玩', ['home/companions', 'home/companion']],
        ['home/packages', '直接点单', ['home/packages']],
    ];
    if ($__me && $__me['role'] === 'companion') {
        $__navItems[] = ['companion/hall', '接单大厅', ['companion/hall']];
        $__navItems[] = ['companion/center', '陪玩中心', ['companion/center', 'companion/skills', 'companion/orders']];
    }
    $__navItems[] = ['order/index', '我的订单', ['order/']];
    $__navItems[] = ['wallet/index', '钱包', ['wallet/']];

    $__activeNav = active_index($__route, $__navItems);
    ?>
    <nav class="nav">
      <?php foreach ($__navItems as $__n => $__item): ?>
        <a href="<?= e(url($__item[0])) ?>" class="<?= $__n === $__activeNav ? 'active' : '' ?>"><?= e($__item[1]) ?><?php if ($__item[0] === 'order/index' && $__unread > 0): ?><em class="nav-badge"><?= (int) $__unread ?></em><?php endif; ?></a>
      <?php endforeach; ?>
    </nav>

    <div class="user-area">
      <?php if ($__me): ?>
        <a class="user-chip" href="<?= e(url('user/profile')) ?>">
          <img src="<?= e(avatar_url($__me['avatar'], (string) $__me['nickname'])) ?>" alt="">
          <span><?= e(str_limit((string) $__me['nickname'], 10)) ?></span>
        </a>
        <?php if (\App\Auth::isAdmin()): ?>
          <a class="btn btn-ghost btn-sm" href="<?= e(admin_url('dashboard/index')) ?>" target="_blank">后台</a>
        <?php endif; ?>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('auth/logout')) ?>">退出</a>
      <?php else: ?>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('auth/login')) ?>">登录</a>
        <a class="btn btn-primary btn-sm" href="<?= e(url('auth/register')) ?>">注册</a>
      <?php endif; ?>
    </div>
  </div>
</header>

<?php
// 顶部公告栏：启用中的公告走马灯滚动，没有公告时回落到「站点公告」设置
$__announcements = \App\ContentService::announcements();
include APP_ROOT . '/app/views/partials/notice_bar.php';
?>

<main class="container main">
  <?php foreach ($__flashes as $__f): ?>
    <div class="alert alert-flash alert-<?= e($__f['type']) ?>"><?= e($__f['msg']) ?></div>
  <?php endforeach; ?>

  <?= $content ?>
</main>

<?php include APP_ROOT . '/app/views/partials/tabbar.php'; ?>
<?php include APP_ROOT . '/app/views/partials/ticket_modal.php'; ?>

<footer class="footer">
  <div class="container">
    <p><?= e(site_name()) ?> · 陪玩点单系统</p>
    <p class="muted">理性消费，禁止未成年人下单陪玩服务</p>
  </div>
</footer>

<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
