<?php
/**
 * 文件上传：图片（原有）、以及工单用的「图片 / 视频 / 文档」多文件上传
 */
declare(strict_types=1);

namespace App;

class Upload
{
    /** 图片扩展名 */
    private const IMAGE_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    /** 视频扩展名 */
    private const VIDEO_EXT = ['mp4', 'webm', 'mov', 'm4v'];
    /** 文档扩展名 */
    private const FILE_EXT  = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'zip', 'rar', '7z'];

    /** 保存图片，返回相对路径（uploads/xxx/yyy.jpg），无文件时返回 null */
    public static function image(string $field, string $subdir = 'images'): ?string
    {
        if (empty($_FILES[$field]) || !is_array($_FILES[$field])) {
            return null;
        }
        $file = $_FILES[$field];
        $err  = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($err !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('图片上传失败（错误码 ' . $err . '）');
        }
        if ((int) $file['size'] > 5 * 1024 * 1024) {
            throw new \RuntimeException('图片不能超过 5MB');
        }
        $tmp = (string) $file['tmp_name'];
        if (!is_uploaded_file($tmp)) {
            throw new \RuntimeException('非法的上传文件');
        }
        $info = @getimagesize($tmp);
        if ($info === false) {
            throw new \RuntimeException('只能上传图片文件');
        }
        $allow = [
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG  => 'png',
            IMAGETYPE_GIF  => 'gif',
            IMAGETYPE_WEBP => 'webp',
        ];
        $type = (int) $info[2];
        if (!isset($allow[$type])) {
            throw new \RuntimeException('仅支持 jpg / png / gif / webp 格式');
        }

        $dir = self::ensureDir($subdir);
        $name = date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $allow[$type];
        if (!@move_uploaded_file($tmp, $dir . '/' . $name)) {
            throw new \RuntimeException('保存上传图片失败，请检查目录权限');
        }
        return self::rel($subdir, $name);
    }

    /**
     * 多文件上传（工单附件）：图片 / 视频 / 文档混着传都行。
     * 表单写法：<input type="file" name="attachments[]" multiple>
     *
     * @return array<int,string> 相对路径列表，如 uploads/tickets/202610/a.jpg
     */
    public static function files(string $field, string $subdir = 'tickets', int $maxCount = 5): array
    {
        if (empty($_FILES[$field]) || !is_array($_FILES[$field])) {
            return [];
        }
        $f = $_FILES[$field];

        // 单个文件（input 没有 multiple）也兼容
        if (!isset($f['name']) || !is_array($f['name'])) {
            $one = self::storeOne($f, $subdir);
            return $one === null ? [] : [$one];
        }

        $dir   = self::ensureDir($subdir);
        $out   = [];
        $total = count($f['name']);
        for ($i = 0; $i < $total; $i++) {
            if (count($out) >= max(1, $maxCount)) {
                break;
            }
            $err = (int) ($f['error'][$i] ?? UPLOAD_ERR_NO_FILE);
            if ($err === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $file = [
                'name'     => (string) ($f['name'][$i] ?? ''),
                'tmp_name' => (string) ($f['tmp_name'][$i] ?? ''),
                'error'    => $err,
                'size'     => (int) ($f['size'][$i] ?? 0),
            ];
            $saved = self::storeOne($file, $subdir, $dir);
            if ($saved !== null) {
                $out[] = $saved;
            }
        }

        return $out;
    }

    /** 附件是图片吗（用于对话里直接显示缩略图） */
    public static function isImage(string $path): bool
    {
        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::IMAGE_EXT, true);
    }

    public static function isVideo(string $path): bool
    {
        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::VIDEO_EXT, true);
    }

    // ---------------------------------------------------------------- 内部

    /**
     * @param array<string,mixed> $file 单个 $_FILES 项
     */
    private static function storeOne(array $file, string $subdir, ?string $dir = null): ?string
    {
        $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($err !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('附件上传失败（错误码 ' . $err . '）');
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new \RuntimeException('非法的上传文件');
        }

        $ext  = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        $size = (int) ($file['size'] ?? 0);

        if (in_array($ext, self::IMAGE_EXT, true)) {
            if ($size > 5 * 1024 * 1024) {
                throw new \RuntimeException('图片不能超过 5MB');
            }
            if (@getimagesize($tmp) === false) {
                throw new \RuntimeException('这个图片文件看起来已损坏');
            }
        } elseif (in_array($ext, self::VIDEO_EXT, true)) {
            if ($size > 50 * 1024 * 1024) {
                throw new \RuntimeException('视频不能超过 50MB');
            }
        } elseif (in_array($ext, self::FILE_EXT, true)) {
            if ($size > 20 * 1024 * 1024) {
                throw new \RuntimeException('文档不能超过 20MB');
            }
        } else {
            throw new \RuntimeException('不支持的附件类型：' . ($ext !== '' ? $ext : '未知'));
        }

        $dir  = $dir ?? self::ensureDir($subdir);
        $name = date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        if (!@move_uploaded_file($tmp, $dir . '/' . $name)) {
            throw new \RuntimeException('保存附件失败，请检查 public/uploads 目录权限');
        }

        return self::rel($subdir, $name);
    }

    /** 相对路径（存库用） */
    private static function rel(string $subdir, string $name): string
    {
        return 'uploads/' . trim($subdir, '/') . '/' . date('Ym') . '/' . $name;
    }

    /** 绝对目录（按年月分子目录），不存在则创建 */
    private static function ensureDir(string $subdir): string
    {
        $subdir = trim($subdir, '/');
        if (!preg_match('#^[A-Za-z0-9_\-/]{1,50}$#', $subdir) || strpos($subdir, '..') !== false) {
            throw new \RuntimeException('上传目录不合法');
        }
        $dir = APP_ROOT . '/public/uploads/' . $subdir . '/' . date('Ym');
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('上传目录创建失败，请检查 public/uploads 目录权限');
        }
        return $dir;
    }
}
