<?php
/**
 * 应用引导文件：加载配置、注册自动加载、初始化数据库与登录态。
 */
declare(strict_types=1);

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}
define('APP_START', microtime(true));

// ---------- 配置 ----------
$configFile = APP_ROOT . '/config/config.php';
if (!is_file($configFile)) {
    $configFile = APP_ROOT . '/config/config.sample.php';
}
if (!is_file($configFile)) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    exit('缺少配置文件 config/config.php，请先访问 install.php 完成安装。');
}
$GLOBALS['config'] = require $configFile;

date_default_timezone_set((string) ($GLOBALS['config']['app']['timezone'] ?? 'Asia/Shanghai'));

$GLOBALS['APP_DEBUG'] = !empty($GLOBALS['config']['app']['debug']);
ini_set('display_errors', $GLOBALS['APP_DEBUG'] ? '1' : '0');
error_reporting($GLOBALS['APP_DEBUG'] ? E_ALL : (E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING));

// ---------- 自动加载 ----------
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $rel  = substr($class, strlen($prefix));
    $file = APP_ROOT . '/app/' . str_replace('\\', '/', $rel) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require APP_ROOT . '/app/helpers.php';

// 按当前请求推导站点根并覆盖 config.app.url（换域名 / 整站拷贝部署后，旧配置不再污染线上）
\resolve_site_url();

// ---------- 全局异常/错误兜底 ----------
set_exception_handler(function (\Throwable $e) {
    if (!headers_sent()) {
        http_response_code(500);
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    $debug = !empty($GLOBALS['APP_DEBUG']);
    $msg   = $debug ? $e->getMessage() . "\n\n" . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString()
                    : '服务异常，请稍后再试。';
    $install = htmlspecialchars(base_url() . '/install.php', ENT_QUOTES, 'UTF-8');
    echo '<!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><title>出错了</title></head>'
       . '<body style="font-family:-apple-system,Segoe UI,Microsoft YaHei,sans-serif;padding:40px;max-width:860px;margin:0 auto;color:#333">'
       . '<h2 style="color:#c0392b">出错了</h2>'
       . '<pre style="white-space:pre-wrap;background:#f7f7f9;border:1px solid #eee;padding:14px;border-radius:8px;font-size:13px;line-height:1.6">'
       . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</pre>'
       . '<p style="color:#888;font-size:14px">首次部署请先访问 <a href="' . $install . '">install.php</a> 完成安装。</p>'
       . '</body></html>';
});

// ---------- 初始化 ----------
\App\Db::init($GLOBALS['config']['db'] ?? []);
\App\Auth::boot();
\App\Setting::warmup();
