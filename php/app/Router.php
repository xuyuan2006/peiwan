<?php
/**
 * 极简路由：?r=controller/action  →  App\Controllers\{Controller}::{action}
 * 采用 query string 路由，不需要配置 URL 重写，虚拟主机也能直接跑。
 */
declare(strict_types=1);

namespace App;

class Router
{
    public static function dispatch(string $route, string $namespace = 'App\\Controllers\\'): void
    {
        $route = trim($route, '/');
        if ($route === '') {
            $route = 'home/index';
        }
        if (!preg_match('#^[A-Za-z0-9_]+(/[A-Za-z0-9_]+)?$#', $route)) {
            abort(404, '页面不存在');
        }

        $parts = explode('/', $route);
        $ctrl  = $parts[0];
        $act   = $parts[1] ?? 'index';

        $class  = $namespace . self::studly($ctrl) . 'Controller';
        $method = lcfirst(self::studly($act));

        if (!class_exists($class)) {
            abort(404, '页面不存在');
        }
        if (!method_exists($class, $method)) {
            abort(404, '页面不存在');
        }

        $ref = new \ReflectionMethod($class, $method);
        if (!$ref->isPublic() || $ref->isStatic() || $ref->isConstructor()) {
            abort(404, '页面不存在');
        }

        $controller = new $class();
        $controller->$method();
    }

    private static function studly(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $value)));
    }
}
