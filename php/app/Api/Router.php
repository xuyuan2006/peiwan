<?php
/**
 * API 路由：?r=controller/action → App\Controllers\Api\{Controller}Controller::{action}
 *
 * 与前台 Router 的区别：
 *  - 命名空间固定在 App\Controllers\Api\ 下，避免误调到网页控制器（它们会 redirect/渲染 HTML）。
 *  - 方法名必须显式登记在 $publicActions 中，否则 404 —— 防止新增方法被意外暴露成接口。
 */
declare(strict_types=1);

namespace App\Api;

class Router
{
    /**
     * 允许被 App 调用的方法白名单：'控制器小写' => [方法名小写, ...]
     * @var array<string,array<int,string>>
     */
    private static $publicActions = [
        'common'    => ['config', 'init'],
        'auth'      => ['login', 'register', 'logout', 'me', 'sendcode', 'forgot', 'verifychannel', 'changepassword'],
        'home'      => ['index', 'games', 'packages', 'companions', 'companion', 'search'],
        'order'     => ['create', 'buypackage', 'index', 'detail', 'pay', 'cancel', 'refund', 'confirm', 'messages', 'sendmessage', 'logs'],
        'wallet'    => ['index', 'transactions', 'recharge', 'rechargepay', 'rechargecancel', 'rechargedetail', 'withdraw', 'rechargelist', 'withdrawlist'],
        'companion' => ['hall', 'grab', 'orders', 'accept', 'finish', 'toggleonline', 'center', 'apply', 'skills', 'skillsave', 'skilldelete', 'skilltoggle', 'incomestat', 'profile'],
        'user'      => ['center', 'profile', 'saveprofile', 'apply'],
        'ticket'    => ['index', 'create', 'detail', 'reply', 'close', 'categories'],
        'review'    => ['create', 'reply', 'mine'],
        'upload'    => ['image'],
    ];

    public static function dispatch(string $route): void
    {
        $route = trim($route, '/');
        if ($route === '') {
            throw ApiException::notFound('缺少接口名（请传 r=控制器/方法）');
        }
        if (!preg_match('#^[A-Za-z0-9_]+(/[A-Za-z0-9_]+)?$#', $route)) {
            throw ApiException::notFound('接口地址不合法');
        }

        $parts = explode('/', $route);
        $ctrl  = strtolower($parts[0]);
        $act   = strtolower($parts[1] ?? 'index');

        $allowed = self::$publicActions[$ctrl] ?? [];
        if (!in_array($act, $allowed, true)) {
            throw ApiException::notFound('接口不存在');
        }

        $class  = 'App\\Controllers\\Api\\' . self::studly($ctrl) . 'Controller';
        $method = lcfirst(self::studly($act));

        if (!class_exists($class) || !method_exists($class, $method)) {
            throw ApiException::notFound('接口尚未实现');
        }

        $ref = new \ReflectionMethod($class, $method);
        if (!$ref->isPublic() || $ref->isStatic() || $ref->isConstructor()) {
            throw ApiException::notFound('接口不存在');
        }

        $controller = new $class();
        $controller->$method();
    }

    private static function studly(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $value)));
    }
}
