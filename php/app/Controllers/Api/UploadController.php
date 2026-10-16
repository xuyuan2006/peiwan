<?php
/**
 * 图片上传接口。
 *
 * 用法：POST multipart/form-data 到 ?r=upload/image&type=avatar
 *   文件字段名：优先用 file，也兼容 avatar / cover / image / voucher。
 *
 * 返回 path（相对路径，存库用）与 url（绝对地址，展示用）。
 * 业务接口（充值凭证、技能配图、头像）接收的都是 path。
 */
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Api\ApiException;
use App\Api\RateLimit;
use App\Api\Request;
use App\Upload;

class UploadController extends BaseController
{
    /** 上传类型 → [允许的文件字段, 存储子目录] */
    private const TYPES = [
        'avatar'  => [['file', 'avatar'], 'avatar'],
        'voucher' => [['file', 'voucher'], 'voucher'],
        'skill'   => [['file', 'cover'], 'skill'],
        'image'   => [['file', 'image'], 'images'],
    ];

    public function image(): void
    {
        Request::requirePost();

        $uid = (int) $this->user()['id'];

        // 上传最容易被刷，限制严一点
        RateLimit::hit('upload:' . $uid, 30, 600, '上传过于频繁，请稍后再试');

        $type = strtolower(Request::str('type', 'image'));
        if (!isset(self::TYPES[$type])) {
            throw ApiException::invalid('不支持的图片用途');
        }
        [$candidates, $subdir] = self::TYPES[$type];

        self::assertPostSizeOk();

        $field = self::pickField($candidates);
        if ($field === '') {
            throw ApiException::invalid('请选择要上传的图片');
        }

        // Upload::image 内部会校验：大小 ≤5MB、真实图片类型、getimagesize 通过
        $path = Upload::image($field, $subdir);
        if ($path === null) {
            throw ApiException::invalid('没有收到图片文件');
        }

        $this->ok([
            'path' => $path,
            'url'  => upload_url($path),
            'type' => $type,
        ], '上传成功');
    }

    /**
     * 挑出真正带文件的字段。
     * 之所以不直接写死 'file'，是因为 App 端可能沿用网页端的字段名（avatar/cover/voucher）。
     * @param array<int,string> $candidates
     */
    private static function pickField(array $candidates): string
    {
        $fallback = '';
        foreach ($candidates as $name) {
            if (empty($_FILES[$name]) || !is_array($_FILES[$name])) {
                continue;
            }
            $err = (int) ($_FILES[$name]['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($err === UPLOAD_ERR_OK) {
                return $name;
            }
            // 记下第一个「存在但出错」的字段，交给 Upload::image 抛出可读原因
            if ($fallback === '' && $err !== UPLOAD_ERR_NO_FILE) {
                $fallback = $name;
            }
        }
        return $fallback;
    }

    /**
     * 请求体超过 post_max_size 时，PHP 会把 $_POST / $_FILES 全部清空，
     * 此时直接报「请选择图片」会让用户完全摸不着头脑，所以单独识别一下。
     */
    private static function assertPostSizeOk(): void
    {
        if (!empty($_POST) || !empty($_FILES)) {
            return;
        }
        $length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($length <= 0) {
            return;
        }
        $limit = self::iniBytes('post_max_size');
        if ($limit > 0 && $length > $limit) {
            throw ApiException::invalid(
                '图片过大，服务器单次请求上限为 ' . max(1, (int) floor($limit / 1048576)) . 'MB'
            );
        }
    }

    private static function iniBytes(string $key): int
    {
        $val = (string) ini_get($key);
        if ($val === '') {
            return 0;
        }
        $unit = strtolower(substr($val, -1));
        $num  = (int) $val;
        switch ($unit) {
            case 'g':
                return $num * 1073741824;
            case 'm':
                return $num * 1048576;
            case 'k':
                return $num * 1024;
            default:
                return $num;
        }
    }
}
