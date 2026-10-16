<?php
/**
 * 全局辅助函数（视图与控制器通用）
 */
declare(strict_types=1);

use App\Auth;
use App\Csrf;
use App\Setting;
use App\View;

// ------------------------------------------------------------------ 配置/输出

/** @return mixed */
function config(?string $key = null, $default = null)
{
    $cfg = $GLOBALS['config'] ?? [];
    if ($key === null) {
        return $cfg;
    }
    foreach (explode('.', $key) as $seg) {
        if (!is_array($cfg) || !array_key_exists($seg, $cfg)) {
            return $default;
        }
        $cfg = $cfg[$seg];
    }
    return $cfg;
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function site_name(): string
{
    return (string) Setting::get('site_name', config('app.name', '陪玩点单平台'));
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function client_ip(): string
{
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    if (strpos($ip, ',') !== false) {
        $ip = trim(explode(',', $ip)[0]);
    }
    return substr((string) $ip, 0, 45);
}

// ------------------------------------------------------------------ URL

function base_url(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $configured = trim((string) config('app.url', ''));
    if ($configured !== '') {
        return $base = rtrim($configured, '/');
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $dir    = rtrim(str_replace('\\', '/', dirname($script)), '/');

    // 后台入口目录（默认 admin，可能被安装向导改成别的名字）必须剥掉，
    // 否则后台页面会把资源地址算成 /后台目录名/assets/... 导致样式与链接全 404。
    $adminSeg = '/' . admin_path();
    if ($adminSeg !== '/' && substr($dir, -strlen($adminSeg)) === $adminSeg) {
        $dir = substr($dir, 0, -strlen($adminSeg));
    }

    if ($dir === '/' || $dir === '.') {
        $dir = '';
    }
    return $base = $scheme . '://' . $host . $dir;
}

function url(string $route = 'home/index', array $params = []): string
{
    $params = array_merge(['r' => $route], $params);
    return base_url() . '/index.php?' . http_build_query($params);
}

/**
 * 后台入口目录名（安装时可自定义，写在 config/config.php 的 security.admin_path）
 * 默认 admin；非法值一律回落到 admin，避免拼出奇怪的 URL。
 */
function admin_path(): string
{
    $path = trim((string) config('security.admin_path', 'admin'), '/');
    if ($path === '' || !preg_match('/^[A-Za-z0-9_\-]{1,30}$/', $path)) {
        $path = 'admin';
    }
    return $path;
}

function admin_url(string $route = 'dashboard/index', array $params = []): string
{
    $params = array_merge(['r' => $route], $params);
    return base_url() . '/' . admin_path() . '/index.php?' . http_build_query($params);
}

/**
 * 判断当前路由是否命中导航项
 *
 * 模式以 / 结尾表示「前缀匹配」，否则要求完全相等。
 * 这样 home/index 不会再被 home/companions 之类的兄弟路由误命中。
 */
function route_is(string $route, array $patterns): bool
{
    foreach ($patterns as $pattern) {
        if ($pattern === '') {
            continue;
        }
        if (substr($pattern, -1) === '/') {
            if (strpos($route, $pattern) === 0) {
                return true;
            }
        } elseif ($route === $pattern) {
            return true;
        }
    }
    return false;
}

/**
 * 从导航项里挑出第一个命中的下标（-1 表示都没命中）
 *
 * 导航项格式：[路由, 文案, (图标), [匹配模式...]] —— 取「最后一个元素」当匹配模式，
 * 所以三元组（顶部导航）与四元组（底部 Tab 带图标）都能用。
 */
function active_index(string $route, array $items): int
{
    foreach ($items as $index => $item) {
        if (!is_array($item) || !$item) {
            continue;
        }
        $patterns = end($item);
        if (is_array($patterns) && route_is($route, $patterns)) {
            return (int) $index;
        }
    }
    return -1;
}

/**
 * 静态资源地址：自动带上文件修改时间作为版本号，
 * 避免改了 CSS/JS 之后浏览器还在用旧缓存（这正是"样式没生效"的常见原因）。
 */
function asset(string $path): string
{
    $rel  = ltrim($path, '/');
    $file = APP_ROOT . '/public/assets/' . $rel;
    $ver  = is_file($file) ? (string) filemtime($file) : '1';
    return base_url() . '/assets/' . $rel . '?v=' . $ver;
}

function upload_url(?string $path): string
{
    $path = trim((string) $path);
    if ($path === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return base_url() . '/' . ltrim($path, '/');
}

/** 保留当前查询参数并覆盖部分参数，生成链接（分页/筛选常用）。会自动识别前台/后台入口 */
function query_url(array $overrides = [], ?string $route = null): string
{
    $q     = $_GET;
    $r     = $route ?? (string) ($q['r'] ?? 'home/index');
    unset($q['r'], $q['_token']);
    $q     = array_merge($q, $overrides);
    $q     = array_filter($q, function ($v) {
        return $v !== '' && $v !== null;
    });

    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if (strpos($script, '/' . admin_path() . '/') !== false) {
        return admin_url($r, $q); // 后台页面必须走后台入口
    }
    return url($r, $q);
}

function redirect(string $to): void
{
    header('Location: ' . $to);
    exit;
}

function redirect_route(string $route, array $params = []): void
{
    redirect(url($route, $params));
}

function redirect_back(string $fallback = 'home/index'): void
{
    $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    if ($ref !== '') {
        // 只接受同源跳转，避免开放重定向
        $host   = parse_url($ref, PHP_URL_HOST);
        $scheme = parse_url($ref, PHP_URL_SCHEME);
        $me     = (string) ($_SERVER['HTTP_HOST'] ?? '');
        if (is_string($host) && $host !== '' && is_string($scheme)
            && in_array(strtolower($scheme), ['http', 'https'], true)
            && strcasecmp($host, $me) === 0) {
            redirect($ref);
        }
    }
    redirect_route($fallback);
}

// ------------------------------------------------------------------ 视图/请求

function view(string $tpl, array $data = [], string $layout = 'layout'): void
{
    View::render($tpl, $data, $layout);
}

function abort(int $code, string $message = ''): void
{
    http_response_code($code);
    if (is_ajax()) {
        json_out(['code' => $code, 'msg' => $message !== '' ? $message : '请求失败'], $code);
    }
    $script      = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    // 后台入口目录名可能被自定义，不能写死 /admin/
    $isAdminArea = strpos($script, '/' . admin_path() . '/') !== false;

    // 后台区域内未登录时，直接引导到后台登录页，避免渲染无权限页面
    if ($isAdminArea && !Auth::check()) {
        flash('warning', '请先登录后台');
        redirect(admin_url('auth/login'));
    }

    View::render('error', ['code' => $code, 'msg' => $message], $isAdminArea ? 'admin' : 'layout');
    exit;
}

function is_ajax(): bool
{
    return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
}

function json_out($data, int $httpCode = 200): void
{
    if (!headers_sent()) {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function is_post(): bool
{
    return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST';
}

function require_post(): void
{
    if (!is_post()) {
        abort(405, '请通过表单提交');
    }
}

/** @return mixed */
function input(string $key, $default = '')
{
    if (array_key_exists($key, $_POST)) {
        return $_POST[$key];
    }
    if (array_key_exists($key, $_GET)) {
        return $_GET[$key];
    }
    return $default;
}

function input_str(string $key, string $default = ''): string
{
    $v = input($key, $default);
    if (is_array($v)) {
        return $default;
    }
    return trim((string) $v);
}

function input_int(string $key, int $default = 0): int
{
    $v = input($key, $default);
    return is_array($v) ? $default : (int) $v;
}

function input_float(string $key, float $default = 0.0): float
{
    $v = input($key, $default);
    return is_array($v) ? $default : (float) $v;
}

function input_arr(string $key): array
{
    $v = input($key, []);
    return is_array($v) ? $v : [];
}

// ------------------------------------------------------------------ 会话消息 / CSRF

function csrf_token(): string
{
    return Csrf::token();
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    Csrf::check();
}

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'msg' => $message];
}

function take_flashes(): array
{
    $items = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return is_array($items) ? $items : [];
}

function keep_old(array $data): void
{
    $_SESSION['_old'] = $data;
}

/** @return mixed */
function old(string $key, $default = '')
{
    return $_SESSION['_old'][$key] ?? $default;
}

function clear_old(): void
{
    unset($_SESSION['_old']);
}

// ------------------------------------------------------------------ 用户/权限

/** @return array<string,mixed>|null */
function user()
{
    return Auth::user();
}

function is_logged_in(): bool
{
    return Auth::check();
}

function is_admin(): bool
{
    return Auth::isAdmin();
}

function is_companion(): bool
{
    return Auth::isCompanion();
}

function avatar_url(?string $path, string $nickname = ''): string
{
    $path = trim((string) $path);
    if ($path !== '') {
        return upload_url($path);
    }
    $seed = $nickname !== '' ? $nickname : 'guest';
    return 'data:image/svg+xml;charset=utf-8,' . rawurlencode(
        '<svg xmlns="http://www.w3.org/2000/svg" width="80" height="80"><rect width="80" height="80" fill="#6c5ce7"/>'
        . '<text x="40" y="52" font-size="34" font-family="sans-serif" fill="#fff" text-anchor="middle">'
        . mb_substr($seed, 0, 1) . '</text></svg>'
    );
}

// ------------------------------------------------------------------ 金额/状态

/** 转义 LIKE 通配符：避免用户输入 % 或 _ 扩大匹配范围 */
function like_escape(string $value): string
{
    return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
}

/** 数字格式化（保留两位小数，返回字符串） */
function money(float $amount): string
{
    return number_format($amount, 2, '.', '');
}

function yuan($amount): string
{
    return '¥' . number_format((float) $amount, 2, '.', ',');
}

/** @return array<int,array{0:string,1:string}> */
function order_status_map(): array
{
    return [
        1 => ['待支付', 'warn'],
        2 => ['待接单', 'info'],
        3 => ['服务中', 'primary'],
        4 => ['待确认', 'info'],
        5 => ['已完成', 'success'],
        6 => ['退款中', 'warn'],
        7 => ['已关闭', 'muted'],
        8 => ['已退款', 'muted'],
    ];
}

function order_status_text(int $status): string
{
    $map = order_status_map();
    return $map[$status][0] ?? '未知';
}

function order_status_class(int $status): string
{
    $map = order_status_map();
    return $map[$status][1] ?? 'muted';
}

/** @return array<string,string> */
function wallet_type_map(): array
{
    return [
        'recharge'        => '充值入账',
        'order_pay'       => '订单支付',
        'order_refund'    => '订单退款',
        'order_income'    => '订单收入',
        'platform_fee'    => '平台抽成',
        'withdraw_apply'  => '提现申请(冻结)',
        'withdraw_paid'   => '提现成功',
        'withdraw_reject' => '提现驳回(解冻)',
        'admin_adjust'    => '后台调整',
    ];
}

function wallet_type_text(string $type): string
{
    $map = wallet_type_map();
    return $map[$type] ?? $type;
}

/** @return array<string,string> */
function audit_status_map(): array
{
    return [
        0 => '待审核',
        1 => '已通过',
        2 => '已拒绝',
    ];
}

function audit_status_text(int $status): string
{
    $map = audit_status_map();
    return $map[$status] ?? '未知';
}

/** 分页 HTML */
function pager(int $page, int $total, int $perPage): string
{
    $totalPage = (int) ceil($total / max(1, $perPage));
    if ($totalPage <= 1) {
        return '';
    }
    ob_start();
    include APP_ROOT . '/app/views/partials/pager.php';
    return (string) ob_get_clean();
}

/** 服务类型 */
/** @return array<string,string> */
function service_type_map(): array
{
    return [
        'hour'   => '按小时',
        'round'  => '按局',
        'day'    => '按天',
        'custom' => '一口价',
    ];
}

function service_type_text(string $type): string
{
    $map = service_type_map();
    return $map[$type] ?? $type;
}

/** 服务单位后缀 */
function service_unit_suffix(string $type): string
{
    $map = ['hour' => '小时', 'round' => '局', 'day' => '天', 'custom' => '单'];
    return $map[$type] ?? '单';
}

/** 校验手机号（宽松） */
function is_phone(string $phone): bool
{
    return (bool) preg_match('/^1[3-9]\d{9}$/', $phone);
}

function str_limit(?string $text, int $length = 40): string
{
    $text = (string) $text;
    return mb_strlen($text) > $length ? mb_substr($text, 0, $length) . '…' : $text;
}

function order_no(string $prefix = 'PO'): string
{
    return $prefix . date('YmdHis') . str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function safe_float($value): float
{
    return round((float) $value, 2);
}
