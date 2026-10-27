<?php
/**
 * 自动任务：关闭超时未支付订单 + 自动确认已完成订单
 *
 * 用法一（推荐，Linux 宝塔计划任务）：
 *   php /www/wwwroot/你的目录/cron/auto.php
 *
 * 用法二（不支持 CLI 的虚拟主机，URL 定时访问）：
 *   在 config/config.php 的 app 节点加 'cron_key' => '你的随机密钥'
 *   然后定时请求 https://你的域名/cron/auto.php?key=你的随机密钥
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Db;
use App\FinanceService;
use App\OrderService;
use App\VerifyCode;

$isCli = (PHP_SAPI === 'cli');

if (!$isCli) {
    $key    = (string) ($_GET['key'] ?? '');
    $expect = (string) config('app.cron_key', '');
    if ($expect === '' || !hash_equals($expect, $key)) {
        http_response_code(403);
        exit('forbidden');
    }
    header('Content-Type: text/plain; charset=utf-8');
}

try {
    if (!Db::tableExists('orders')) {
        exit("未安装，请先访问 install.php\n");
    }

    $closed    = OrderService::autoCloseExpired();
    $confirmed = OrderService::autoConfirmFinished();
    $recharge  = FinanceService::autoCloseExpiredRecharge(24);
    $codes     = VerifyCode::cleanupOld(2);

    $line = sprintf(
        "[%s] 关闭超时未支付订单 %d 笔，自动确认完成订单 %d 笔，关闭未支付充值单 %d 笔，清理过期验证码 %d 条\n",
        now(),
        $closed,
        $confirmed,
        $recharge,
        $codes
    );
    echo $line;

    // 记录到操作日志，便于后台查看执行情况
    try {
        Db::insert('admin_logs', [
            'admin_id'   => 0,
            'action'     => '定时任务',
            'detail'     => sprintf(
                '关闭超时订单 %d 笔，自动确认 %d 笔，关闭未支付充值单 %d 笔，清理过期验证码 %d 条',
                $closed,
                $confirmed,
                $recharge,
                $codes
            ),
            'ip'         => $isCli ? 'cron' : client_ip(),
            'created_at' => now(),
        ]);
    } catch (\Throwable $e) {
        // 日志失败忽略
    }
} catch (\Throwable $e) {
    echo '执行失败：' . $e->getMessage() . "\n";
    if (!$isCli) {
        http_response_code(500);
    }
}
