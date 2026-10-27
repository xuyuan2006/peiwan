<?php
/**
 * 接口业务异常：抛出后由 Response::handleThrowable 统一转成 JSON。
 *
 * 注意：业务代码里的普通提示请继续使用 \RuntimeException（原有 Service 层就是这么做的），
 * 这里的 ApiException 只用于需要指定 HTTP 状态的场景（401 未登录 / 403 无权限 / 404 不存在 / 429 太频繁）。
 */
declare(strict_types=1);

namespace App\Api;

class ApiException extends \RuntimeException
{
    /** @var int */
    private $httpCode;

    public function __construct(string $message, int $httpCode = 400)
    {
        parent::__construct($message);
        $this->httpCode = $httpCode;
    }

    public function httpCode(): int
    {
        return $this->httpCode;
    }

    public static function unauthorized(string $message = '登录已失效，请重新登录'): self
    {
        return new self($message, 401);
    }

    public static function forbidden(string $message = '没有操作权限'): self
    {
        return new self($message, 403);
    }

    public static function notFound(string $message = '内容不存在或已下架'): self
    {
        return new self($message, 404);
    }

    public static function invalid(string $message = '参数不正确'): self
    {
        return new self($message, 422);
    }

    public static function tooMany(string $message = '操作过于频繁，请稍后再试'): self
    {
        return new self($message, 429);
    }
}
