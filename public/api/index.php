<?php
/**
 * App 接口统一入口。
 *
 * 访问形式：http://你的域名/api/index.php?r=控制器/方法
 * 例：
 *   GET  /api/index.php?r=home/index
 *   POST /api/index.php?r=auth/login          （Body JSON：{"account":"xx","password":"yy"}）
 *   GET  /api/index.php?r=order/index         （Header：Authorization: Bearer <token>）
 *
 * 这样设计是为了和全站保持一致：不依赖 URL 重写，虚拟主机 / 宝塔 / Nginx 都能直接跑。
 */
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/api_bootstrap.php';

try {
    \App\Api\Router::dispatch((string) ($_GET['r'] ?? ''));
} catch (\Throwable $e) {
    \App\Api\Response::handleThrowable($e);
}
