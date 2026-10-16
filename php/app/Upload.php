<?php
/**
 * 文件上传（图片）
 */
declare(strict_types=1);

namespace App;

class Upload
{
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

        $subdir = trim($subdir, '/');
        if (!preg_match('#^[A-Za-z0-9_\-/]{1,50}$#', $subdir) || strpos($subdir, '..') !== false) {
            throw new \RuntimeException('上传目录不合法');
        }

        $rel = 'uploads/' . $subdir . '/' . date('Ym');
        $dir = APP_ROOT . '/public/' . $rel;
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('上传目录创建失败，请检查 public/uploads 目录权限');
        }
        $name = date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $allow[$type];
        if (!@move_uploaded_file($tmp, $dir . '/' . $name)) {
            throw new \RuntimeException('保存上传图片失败，请检查目录权限');
        }
        return $rel . '/' . $name;
    }
}
