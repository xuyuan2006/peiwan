<?php
/**
 * 底部 Tab 导航（居中、均匀分布、线性图标）
 *
 * 选中判断：用 route_is() 精确/前缀匹配，并且**只让第一个命中的 Tab 高亮**，
 * 避免 home/index 与 home/companions 这种同前缀路由同时变色。
 *
 * 注意：视图在全局命名空间执行，类引用必须写全限定名 \App\X
 * @var string $__route
 */
$__loggedIn = \App\Auth::check();

/** 线性图标（feather 风格，stroke = currentColor） */
$__icons = [
    'home'  => '<path d="M3 10.6 12 3.5l9 7.1"/><path d="M5.5 9.4V20.5h13V9.4"/><path d="M9.8 20.5v-5.2h4.4v5.2"/>',
    'game'  => '<rect x="2" y="7" width="20" height="10.5" rx="4.8"/><path d="M7.2 10.3v4M5.2 12.3h4"/><circle cx="16" cy="11.2" r="1"/><circle cx="18.2" cy="13.4" r="1"/>',
    'bolt'  => '<path d="M13.2 2.5 4.6 13.6h6L9.4 21.5l8.9-11.3h-6l.9-7.7z"/>',
    'order' => '<path d="M7.5 3.5h6.2L18 7.8v12.7H7.5z"/><path d="M7.5 3.5v4.3H18"/><path d="M10 12.5h5M10 16h3.5"/>',
    'user'  => '<circle cx="12" cy="8.2" r="3.9"/><path d="M4.4 20.6c0-3.9 3.4-5.9 7.6-5.9s7.6 2 7.6 5.9"/>',
    'cart'  => '<circle cx="9.2" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M2.5 3.6h2.9l2.4 11.1h11.3l2.4-8H6.5"/>',
];

// [路由, 文案, 图标, 命中模式]；顺序即优先级（先命中者高亮）
// 注意：首页只能精确匹配 home/index —— 一旦写成 home/ 前缀，陪玩页也会点亮首页
$__tabs = [
    ['home/index',      '首页', 'home', ['home/index']],
    ['home/companions', '陪玩', 'game', ['home/companions', 'home/companion']],
    ['home/packages',   '点单', 'cart', ['home/packages']],
];

// 「接单」只对陪玩身份显示；普通用户与管理员都看不到这一栏
if (is_companion()) {
    $__tabs[] = ['companion/hall', '接单', 'bolt', ['companion/hall']];
}

$__tabs[] = ['order/index', '订单', 'order', ['order/'], $__unread];
$__tabs[] = [
    $__loggedIn ? 'user/center' : 'auth/login',
    '我的',
    'user',
    ['user/', 'wallet/', 'ticket/', 'companion/', 'auth/'],
];

$__activeTab = active_index($__route, $__tabs);
?>
<nav class="tabbar">
  <div class="tabbar-inner">
    <?php foreach ($__tabs as $__i => $__tab): ?>
      <a href="<?= e(url($__tab[0])) ?>" class="tab-item<?= $__i === $__activeTab ? ' active' : '' ?>">
        <svg class="tab-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $__icons[$__tab[2]] ?? '' ?></svg>
        <span class="tab-label"><?= e($__tab[1]) ?></span>
        <?php if (!empty($__tab[4])): ?><em class="tab-badge"><?= (int) $__tab[4] ?></em><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div>
</nav>
