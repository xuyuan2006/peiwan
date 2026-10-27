<?php
/**
 * 极简视图渲染：视图文件 + 布局文件
 */
declare(strict_types=1);

namespace App;

class View
{
    public static function render(string $tpl, array $data = [], string $layout = 'layout'): void
    {
        $content = self::capture($tpl, $data);

        if ($layout === '') {
            echo $content;
            return;
        }

        $layoutFile = APP_ROOT . '/app/views/layouts/' . $layout . '.php';
        if (!is_file($layoutFile)) {
            echo $content;
            return;
        }

        // 让布局文件也能拿到控制器传入的变量（如 title），$content 已定义不会被覆盖
        extract($data, EXTR_SKIP);
        include $layoutFile;
    }

    public static function partial(string $tpl, array $data = []): void
    {
        echo self::capture($tpl, $data);
    }

    public static function capture(string $tpl, array $data = []): string
    {
        $file = self::resolve($tpl);
        if ($file === null) {
            http_response_code(500);
            return '视图文件不存在：' . htmlspecialchars($tpl, ENT_QUOTES, 'UTF-8');
        }
        extract($data, EXTR_SKIP);
        ob_start();
        include $file;
        return (string) ob_get_clean();
    }

    private static function resolve(string $tpl): ?string
    {
        $tpl = str_replace(['..', '\\'], ['', '/'], $tpl);
        $tpl = trim($tpl, '/');
        $file = APP_ROOT . '/app/views/' . $tpl . '.php';
        return is_file($file) ? $file : null;
    }
}
