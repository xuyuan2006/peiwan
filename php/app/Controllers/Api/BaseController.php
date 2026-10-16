<?php
/**
 * API 控制器基类：只提供取用户、分页、统一响应这几个能力。
 *
 * 刻意不继承网页端的 BaseController —— 那个类依赖 Session、CSRF 和视图渲染，
 * 在纯接口场景下只会添乱。
 */
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Api\Request;
use App\Api\Response;
use App\Api\TokenAuth;

abstract class BaseController
{
    /** 要求登录，返回当前用户行 */
    protected function user(): array
    {
        return TokenAuth::requireLogin();
    }

    /** 当前登录用户 ID（未登录为 0） */
    protected function uid(): int
    {
        return TokenAuth::id();
    }

    /** 要求「已通过审核的陪玩」身份 */
    protected function companionUser(): array
    {
        return TokenAuth::requireCompanion();
    }

    /** @param mixed $data */
    protected function ok($data = null, string $msg = 'ok'): void
    {
        Response::ok($data, $msg);
    }

    protected function page(int $default = 1): int
    {
        return max(1, Request::int('page', $default));
    }

    protected function perPage(int $default = 10, int $max = 50): int
    {
        $n = Request::int('per_page', $default);
        if ($n <= 0) {
            $n = $default;
        }
        return max(1, min($max, $n));
    }
}
