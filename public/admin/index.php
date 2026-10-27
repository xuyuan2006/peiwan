<?php
/**
 * 后台入口。使用 ?r=控制器/方法 路由。
 *
 * 安全说明：安装向导可以把后台入口改成自定义目录名（config/config.php 的 security.admin_path）。
 * 如果这里（默认的 admin 目录）已经不该被使用，就直接返回 404，
 * 这样即使以后更新时又把 admin/ 目录上传回来，也不会成为一个绕过自定义入口的通道。
 */
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

// 当前实际的后台目录名
$currentDir = basename(__DIR__);
if ($currentDir !== admin_path()) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "404 Not Found\n\n"
        . "（站长提示：本站后台入口已经自定义。请用 config/config.php 里 security.admin_path 指定的目录访问后台；\n"
        . "如果要恢复使用当前目录，请把 security.admin_path 的值改成 \"{$currentDir}\"，两处必须一致。）";
    exit;
}

if (!\App\Db::tableExists('users')) {
    redirect(base_url() . '/install.php');
}

\App\Router::dispatch((string) ($_GET['r'] ?? 'dashboard/index'), 'App\\Controllers\\Admin\\');
