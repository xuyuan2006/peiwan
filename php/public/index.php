<?php
/**
 * 前台入口。使用 ?r=控制器/方法 路由，无需 URL 重写。
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

if (!\App\Db::tableExists('users')) {
    redirect(base_url() . '/install.php');
}

\App\Router::dispatch((string) ($_GET['r'] ?? 'home/index'));
