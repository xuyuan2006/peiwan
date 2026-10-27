<?php
/**
 * 临时诊断页：确认部署路径、域名识别、静态资源地址是否正确。
 * 排查完请删除本文件。
 */
header('Content-Type: text/plain; charset=utf-8');

$root    = dirname(__DIR__);
$cfgFile = $root . '/config/config.php';
$cfg     = is_file($cfgFile) ? (array) require $cfgFile : [];

echo "== 运行环境 ==\n";
echo 'APP_ROOT          : ' . $root . "\n";
echo 'PHP 版本          : ' . PHP_VERSION . "\n";
echo 'HTTP_HOST         : ' . (string) ($_SERVER['HTTP_HOST'] ?? '') . "\n";
echo 'SCRIPT_NAME       : ' . (string) ($_SERVER['SCRIPT_NAME'] ?? '') . "\n";
echo 'HTTPS             : ' . (string) ($_SERVER['HTTPS'] ?? '') . "\n";
echo 'X-Forwarded-Host  : ' . (string) ($_SERVER['HTTP_X_FORWARDED_HOST'] ?? '') . "\n";
echo 'X-Forwarded-Proto : ' . (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') . "\n";

echo "\n== 配置文件 ==\n";
echo 'config/app.url        : ' . var_export((string) ($cfg['app']['url'] ?? ''), true) . "\n";
echo 'config/admin_path     : ' . var_export((string) ($cfg['security']['admin_path'] ?? 'admin'), true) . "\n";
echo 'config/db.name        : ' . (string) ($cfg['db']['name'] ?? '') . "\n";

echo "\n== 文件检查 ==\n";
echo 'public/index.php          : ' . (is_file($root . '/public/index.php') ? '存在' : '缺失') . "\n";
echo 'public/assets/css/style.css: ' . (is_file($root . '/public/assets/css/style.css') ? '存在' : '缺失') . "\n";
echo 'public/assets/js/app.js   : ' . (is_file($root . '/public/assets/js/app.js') ? '存在' : '缺失') . "\n";
echo 'app/helpers.php 修改时间  : ' . (is_file($root . '/app/helpers.php') ? date('Y-m-d H:i:s', (int) filemtime($root . '/app/helpers.php')) : '缺失') . "\n";

echo "\n== 地址计算（关键）==\n";
$helpersSrc = is_file($root . '/app/helpers.php') ? (string) file_get_contents($root . '/app/helpers.php') : '';
echo 'helpers 版本       : ' . (strpos($helpersSrc, 'function site_root_path') !== false
        ? '新（含 site_root_path，已修复域名问题）'
        : '旧（没有 site_root_path，仍会用配置里的域名）') . "\n";
echo 'config/config.php  : ' . (is_file($cfgFile) ? '存在' : '缺失 → 站点会认为没安装，跳 install.php') . "\n";

try {
    require $root . '/app/bootstrap.php';

    echo 'site_root_path()   : ' . site_root_path() . "\n";
    echo 'base_url()         : ' . base_url() . "\n";
    echo "asset('css/style.css'): " . asset('css/style.css') . "\n";
    echo "url('home/index')  : " . url('home/index') . "\n";
    echo 'admin_url          : ' . admin_url('dashboard/index') . "\n";
} catch (\Throwable $e) {
    echo '加载 bootstrap 失败 : ' . $e->getMessage() . "\n";
}

echo "\n== 怎么看 ==\n";
echo "1) asset() 如果是 /assets/... 开头（不带 http://），说明新代码已生效；\n";
echo "   如果还是 http://旧IP/assets/...，说明 app/helpers.php 没传到这个目录。\n";
echo "2) base_url() 里的域名:端口 应该和浏览器地址栏一致（含 :1010）。\n";
echo "3) 文件检查里 style.css 缺失 = 静态资源没传，页面一定没样式。\n";
echo "4) APP_ROOT 必须是你这个站点的根目录（宝塔里显示的那个路径）。\n";
