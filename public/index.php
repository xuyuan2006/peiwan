<?php
/**
 * 前台入口。使用 ?r=控制器/方法 路由，无需 URL 重写。
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

if (!\App\Db::tableExists('users')) {
    // 相对路径跳转：不读 config.php 里可能过期的旧 app.url，避免新站点被拉回旧域名
    redirect('/install.php');
}

\App\Router::dispatch((string) ($_GET['r'] ?? 'home/index'));
