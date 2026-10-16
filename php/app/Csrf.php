<?php
/**
 * CSRF 令牌
 */
declare(strict_types=1);

namespace App;

class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(16));
        }
        return (string) $_SESSION['_csrf'];
    }

    public static function check(): void
    {
        // 只接受 POST 提交的令牌：令牌出现在 URL 里会通过访问日志/Referer 外泄
        $token = (string) ($_POST['_token'] ?? '');
        if ($token === '' || !hash_equals(self::token(), $token)) {
            http_response_code(419);
            exit('表单已过期（CSRF 校验失败），请返回上一页刷新后重试。');
        }
    }
}
