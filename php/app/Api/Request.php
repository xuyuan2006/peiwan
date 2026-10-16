<?php
/**
 * 请求参数读取：把 JSON body / 表单 POST / query string 统一成一套取值方法。
 *
 * 优先级：JSON body > POST > GET（同名时以 body 为准）。
 */
declare(strict_types=1);

namespace App\Api;

class Request
{
    /** @var array<string,mixed>|null */
    private static $json = null;

    /** @var array<string,mixed>|null */
    private static $merged = null;

    private static $rawBody = null;

    /** 解析后的 JSON body（非 JSON 请求返回空数组） */
    public static function json(): array
    {
        if (self::$json !== null) {
            return self::$json;
        }
        self::$json = [];
        $ctype = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
        if (strpos($ctype, 'application/json') === false) {
            return self::$json;
        }
        $raw = self::rawBody();
        if ($raw === '') {
            return self::$json;
        }
        $data = json_decode($raw, true);
        if (is_array($data)) {
            self::$json = $data;
        }
        return self::$json;
    }

    /** 原始请求体（只读一次，multipart 时不适用） */
    public static function rawBody(): string
    {
        if (self::$rawBody === null) {
            $raw = file_get_contents('php://input');
            self::$rawBody = $raw === false ? '' : $raw;
        }
        return self::$rawBody;
    }

    /** @return array<string,mixed> */
    public static function all(): array
    {
        if (self::$merged === null) {
            self::$merged = array_merge($_GET, $_POST, self::json());
        }
        return self::$merged;
    }

    /** @param mixed $default
     *  @return mixed */
    public static function get(string $key, $default = null)
    {
        $all = self::all();
        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::all());
    }

    public static function str(string $key, string $default = ''): string
    {
        $v = self::get($key, $default);
        if (is_array($v)) {
            return $default;
        }
        if (is_bool($v)) {
            return $v ? '1' : '0';
        }
        return trim((string) $v);
    }

    /** 未 trim 的字符串（密码等原样取值） */
    public static function raw(string $key, string $default = ''): string
    {
        $v = self::get($key, $default);
        if (is_array($v) || is_bool($v)) {
            return $default;
        }
        return (string) $v;
    }

    public static function int(string $key, int $default = 0): int
    {
        $v = self::get($key, $default);
        if (is_array($v)) {
            return $default;
        }
        if (is_bool($v)) {
            return $v ? 1 : 0;
        }
        return (int) $v;
    }

    public static function float(string $key, float $default = 0.0): float
    {
        $v = self::get($key, $default);
        if (is_array($v) || is_bool($v)) {
            return $default;
        }
        return (float) $v;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $v = self::get($key, $default ? 1 : 0);
        if (is_bool($v)) {
            return $v;
        }
        if (is_array($v)) {
            return $default;
        }
        return in_array(strtolower((string) $v), ['1', 'true', 'yes', 'on'], true);
    }

    /** @return array<int|string,mixed> */
    public static function arr(string $key): array
    {
        $v = self::get($key, []);
        return is_array($v) ? $v : [];
    }

    /**
     * 取字符串数组，过滤空值与数组嵌套，限制条数与单条长度。
     * @return array<int,string>
     */
    public static function strList(string $key, int $max = 10, int $itemLen = 20): array
    {
        $out = [];
        foreach (self::arr($key) as $item) {
            if (is_array($item) || is_bool($item) || $item === null) {
                continue;
            }
            $s = trim((string) $item);
            if ($s === '') {
                continue;
            }
            $out[] = mb_substr($s, 0, $itemLen);
            if (count($out) >= $max) {
                break;
            }
        }
        return $out;
    }

    public static function method(): string
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    public static function isPost(): bool
    {
        return self::method() === 'POST';
    }

    /**
     * 读取「已上传图片的相对路径」参数。
     *
     * App 的上传是两步：先调 upload/image 拿到 path，再把 path 传给业务接口。
     * 这里做严格校验，防止客户端塞一个任意外链进来（该值最终会被拼成对外 URL）。
     *
     * @param array<int,string> $allowDirs 允许的 uploads 子目录，空数组表示不限
     */
    public static function uploadPath(string $key, array $allowDirs = []): string
    {
        $path = ltrim(trim(self::str($key)), '/');
        if ($path === '') {
            return '';
        }
        if (!preg_match('#^uploads/[A-Za-z0-9_\-/]+\.[A-Za-z0-9]{2,5}$#', $path)) {
            throw ApiException::invalid('图片地址不合法，请重新上传');
        }
        if ($allowDirs) {
            $ok = false;
            foreach ($allowDirs as $dir) {
                $dir = trim((string) $dir, '/');
                if ($dir !== '' && strpos($path, 'uploads/' . $dir . '/') === 0) {
                    $ok = true;
                    break;
                }
            }
            if (!$ok) {
                throw ApiException::invalid('图片地址不合法，请重新上传');
            }
        }
        return $path;
    }

    /** 要求 POST（写操作统一入口，避免被 GET 预取/爬虫误触发） */
    public static function requirePost(): void
    {
        if (!self::isPost()) {
            throw new ApiException('请使用 POST 提交', 405);
        }
    }

    public static function ip(): string
    {
        return client_ip();
    }
}
