<?php
/**
 * App 接口引导文件。
 *
 * 与 bootstrap.php 的区别：
 *  1. 不启动 PHP Session —— 会话文件锁会把同一客户端的并发请求串行化，
 *     移动端一次进首页会并发好几个请求，用 Session 会互相排队。
 *     改为从 Authorization: Bearer <token> 解析登录态。
 *  2. 任何错误（含致命错误）都以 JSON 返回，绝不吐 HTML，否则 App 解析必然失败。
 *  3. 不把 PHP 警告混进响应体。
 */
declare(strict_types=1);

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}
define('APP_START', microtime(true));
define('API_MODE', true);

/**
 * 引导阶段的 JSON 错误输出（此时自动加载还没注册，只能手写）
 */
function api_boot_error(int $httpCode, string $msg): void
{
    if (!headers_sent()) {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['code' => $httpCode, 'msg' => $msg, 'data' => null], JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------------------------------------------------------------- CORS / 预检
// App 走原生 HTTP 不需要 CORS，这段只为方便用浏览器或 Postman 直接调接口调试。
// 刻意不下发 Access-Control-Allow-Credentials：接口只用 Authorization 头鉴权，
// 不依赖 Cookie，不需要放开凭据，避免被任意站点带着用户凭据调用。
$origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
if ($origin !== '') {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
}
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Token, X-Requested-With');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ---------------------------------------------------------------- 配置
$configFile = APP_ROOT . '/config/config.php';
if (!is_file($configFile)) {
    $configFile = APP_ROOT . '/config/config.sample.php';
}
if (!is_file($configFile)) {
    api_boot_error(500, '缺少配置文件 config/config.php，请先完成站点安装');
}
$GLOBALS['config'] = require $configFile;

date_default_timezone_set((string) ($GLOBALS['config']['app']['timezone'] ?? 'Asia/Shanghai'));
$GLOBALS['APP_DEBUG'] = !empty($GLOBALS['config']['app']['debug']);

// 接口响应必须是纯 JSON：错误只写日志，不输出到响应体。
// 注意 error_reporting 要保留 E_WARNING —— display_errors=0 已经保证它们不会混进 JSON，
// 如果连记录也关掉，线上出问题时就没有任何线索了（例如 Array to string conversion）。
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_STRICT);

// ★ 关键：让 base_url() 返回「站点根」而不是「/api 子目录」。
//   否则 upload_url() 会拼出 http://站点/api/uploads/xxx.png，图片全部 404。
if (trim((string) ($GLOBALS['config']['app']['url'] ?? '')) === '') {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

    // 反向代理（CDN / Nginx 前置）场景下以转发头为准
    $fwdProto = (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '');
    if ($fwdProto !== '') {
        $proto = strtolower(trim(explode(',', $fwdProto)[0]));
        if (in_array($proto, ['http', 'https'], true)) {
            $scheme = $proto;
        }
    }

    // 优先用客户端实际请求的 Host：这样生成的图片地址、支付回调地址一定能被访问到。
    // 但它可被 Host 头伪造，所以要过一遍形态校验；不合法再退回 SERVER_NAME。
    //
    // 注意：SERVER_NAME 不能作为首选 —— 共享主机/宝塔未设 server_name 时它常常是
    // "localhost" / "0.0.0.0" / 容器主机名，直接拿来拼会生成访问不了的地址。
    $host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));

    $hostUsable = $host !== ''
        && $host !== 'localhost'
        && $host !== '0.0.0.0'
        // 域名 / IPv4 / 带端口，形如 example.com、103.47.80.215、example.com:8080
        && preg_match(
            '#^[a-z0-9](?:[a-z0-9\-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9\-]{0,61}[a-z0-9])?)*(?::\d{1,5})?$#',
            $host
        ) === 1;

    if (!$hostUsable) {
        $fallback = strtolower(trim((string) ($_SERVER['SERVER_NAME'] ?? '')));
        if ($fallback !== '' && $fallback !== '_' && $fallback !== 'localhost' && $fallback !== '0.0.0.0') {
            $host = $fallback;
            $port = (int) ($_SERVER['SERVER_PORT'] ?? 0);
            if ($port > 0 && !in_array($port, [80, 443], true)) {
                $host .= ':' . $port;
            }
        } else {
            $host = '';
        }
    }

    if ($host !== '') {
        $GLOBALS['config']['app']['url'] = $scheme . '://' . $host;
    }
}

// ---------------------------------------------------------------- 自动加载
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

// ---------------------------------------------------------------- 错误兜底
set_exception_handler(function (\Throwable $e) {
    \App\Api\Response::handleThrowable($e);
});

// 致命错误（语法错误、内存溢出等）也要返回 JSON，否则 App 收到的是空白页
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array((int) $err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        \App\Api\Response::fatal((string) $err['message'], (string) $err['file'], (int) $err['line']);
    }
});

// ---------------------------------------------------------------- 初始化
try {
    $dbCfg = $GLOBALS['config']['db'] ?? [];
    if (!is_array($dbCfg) || empty($dbCfg)) {
        api_boot_error(500, 'config/config.php 缺少数据库配置');
    }
    \App\Db::init($dbCfg);

    // 先真正连一次库：否则数据库配置写错时，tableExists 会吞掉异常，
    // 导致误报成「站点未安装」，排查方向完全被带偏。
    \App\Db::pdo();

    if (!\App\Db::tableExists('users')) {
        api_boot_error(503, '站点尚未安装完成，请先访问 /install.php');
    }

    \App\Setting::warmup();
} catch (\Throwable $e) {
    \App\Api\Response::handleThrowable($e);
}

// 解析令牌（无令牌时静默通过，由各接口自行决定是否要求登录）
try {
    \App\Api\TokenAuth::boot();
} catch (\Throwable $e) {
    // 鉴权基础设施异常不应阻断公开接口（如首页、陪玩列表）
    if (!empty($GLOBALS['APP_DEBUG'])) {
        error_log('[api] token boot failed: ' . $e->getMessage());
    }
}
