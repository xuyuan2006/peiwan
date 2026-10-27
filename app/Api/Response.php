<?php
/**
 * 统一 JSON 响应。
 *
 * 约定（App 端只需判断 code）：
 *   code = 0    成功，业务数据在 data 里
 *   code > 0    失败，code 与 HTTP 状态码语义一致（401 未登录 / 403 无权限 / 404 不存在 / 422 参数错 / 429 太频繁 / 500 服务端异常）
 */
declare(strict_types=1);

namespace App\Api;

class Response
{
    /** 是否已经输出过响应，避免 shutdown 兜底重复输出 */
    private static $sent = false;

    /** @param mixed $data */
    public static function ok($data = null, string $msg = 'ok'): void
    {
        self::send(['code' => 0, 'msg' => $msg, 'data' => $data], 200);
    }

    /** @param mixed $data */
    public static function fail(string $msg, int $httpCode = 400, $data = null): void
    {
        if ($httpCode < 400 || $httpCode > 599) {
            $httpCode = 400;
        }
        self::send(['code' => $httpCode, 'msg' => $msg, 'data' => $data], $httpCode);
    }

    public static function send(array $payload, int $httpCode = 200): void
    {
        self::$sent = true;

        // 丢掉之前可能已经产生的内容（PHP 警告、BOM 等），保证输出是纯 JSON
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            http_response_code($httpCode);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('X-Content-Type-Options: nosniff');
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * 全局异常兜底。
     *
     * 分级策略（避免把数据库报错原文泄给客户端）：
     *   ApiException / RuntimeException → 视为业务提示，直接用异常文案
     *   PDOException / Error / 其他     → 统一「服务异常」，只有开启 debug 才附带细节
     */
    public static function handleThrowable(\Throwable $e): void
    {
        if ($e instanceof ApiException) {
            self::fail($e->getMessage(), $e->httpCode());
        }

        $debug   = !empty($GLOBALS['APP_DEBUG']);
        // 显式加括号：instanceof 的优先级高于 !，不写清楚容易被误读成 (!$e) instanceof ...
        $isBiz   = $e instanceof \RuntimeException && !($e instanceof \PDOException);

        if ($isBiz) {
            $msg = $e->getMessage() !== '' ? $e->getMessage() : '操作失败，请稍后再试';
            self::fail($msg, 400);
        }

        $msg = '服务异常，请稍后再试';
        if ($debug) {
            $msg .= ' [' . get_class($e) . ': ' . $e->getMessage()
                  . ' @ ' . basename($e->getFile()) . ':' . $e->getLine() . ']';
        }
        self::fail($msg, 500);
    }

    /** register_shutdown_function 兜底：致命错误（E_ERROR/E_PARSE）也要返回 JSON */
    public static function fatal(string $message, string $file = '', int $line = 0): void
    {
        if (self::$sent) {
            return;
        }
        $msg = '服务异常，请稍后再试';
        if (!empty($GLOBALS['APP_DEBUG'])) {
            $msg .= ' [Fatal: ' . $message . ' @ ' . basename($file) . ':' . $line . ']';
        }
        self::fail($msg, 500);
    }
}
