<?php
/**
 * 短信服务：目前内置「阿里云短信」驱动（Dysmsapi 2017-05-25，RPC 风格 + HMAC-SHA1 签名），
 * 密钥全部由后台「平台设置 → 验证方式 / 短信」填写，无需 Composer 依赖。
 *
 * 想接其他服务商（腾讯云、容联云等）时，在 sendCode() 里加一个 driver 分支即可。
 */
declare(strict_types=1);

namespace App;

class Sms
{
    public static function driver(): string
    {
        return (string) Setting::get('sms_driver', 'none');
    }

    /** 短信是否配置完整 */
    public static function configured(): bool
    {
        if (self::driver() !== 'aliyun') {
            return false;
        }
        return trim((string) Setting::get('sms_access_key_id', '')) !== ''
            && trim((string) Setting::get('sms_access_key_secret', '')) !== ''
            && trim((string) Setting::get('sms_sign_name', '')) !== ''
            && trim((string) Setting::get('sms_template_code', '')) !== '';
    }

    public static function sendCode(string $phone, string $code, string $scene = 'register'): void
    {
        if (!is_phone($phone)) {
            throw new \RuntimeException('手机号格式不正确');
        }
        if (!self::configured()) {
            throw new \RuntimeException('尚未配置短信服务（后台 → 平台设置 → 验证方式 / 短信）');
        }

        if (self::driver() === 'aliyun') {
            self::sendByAliyun($phone, $code);
            return;
        }

        throw new \RuntimeException('暂不支持该短信服务商：' . self::driver());
    }

    /** 后台「发送测试短信」 */
    public static function sendTest(string $phone): void
    {
        self::sendCode($phone, str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT), 'test');
    }

    // ---------------------------------------------------------------- 阿里云

    private static function sendByAliyun(string $phone, string $code): void
    {
        $accessKeyId     = trim((string) Setting::get('sms_access_key_id', ''));
        $accessKeySecret = trim((string) Setting::get('sms_access_key_secret', ''));
        $signName        = trim((string) Setting::get('sms_sign_name', ''));
        $templateCode    = trim((string) Setting::get('sms_template_code', ''));
        $region          = trim((string) Setting::get('sms_region', 'cn-hangzhou'));
        if ($region === '') {
            $region = 'cn-hangzhou';
        }

        $params = [
            'AccessKeyId'      => $accessKeyId,
            'Action'           => 'SendSms',
            'Format'           => 'JSON',
            'PhoneNumbers'     => $phone,
            'RegionId'         => $region,
            'SignName'         => $signName,
            'SignatureMethod'  => 'HMAC-SHA1',
            'SignatureNonce'   => bin2hex(random_bytes(8)),
            'SignatureVersion' => '1.0',
            'TemplateCode'     => $templateCode,
            'TemplateParam'    => (string) json_encode(['code' => $code], JSON_UNESCAPED_UNICODE),
            'Timestamp'        => gmdate('Y-m-d\TH:i:s\Z'),
            'Version'          => '2017-05-25',
        ];

        $params['Signature'] = self::sign($params, $accessKeySecret);
        $body   = self::httpGet('https://dysmsapi.aliyuncs.com/?' . http_build_query($params));
        $result = json_decode($body, true);

        if (!is_array($result)) {
            throw new \RuntimeException('短信接口返回异常：' . mb_substr($body, 0, 200));
        }
        $respCode = (string) ($result['Code'] ?? '');
        if ($respCode !== 'OK') {
            throw new \RuntimeException('短信发送失败：' . (string) ($result['Message'] ?? $respCode));
        }
    }

    /** 阿里云 RPC 签名：HMAC-SHA1(secret + '&')，结果 base64 */
    private static function sign(array $params, string $secret): string
    {
        ksort($params);
        $pairs = [];
        foreach ($params as $k => $v) {
            $pairs[] = self::percentEncode((string) $k) . '=' . self::percentEncode((string) $v);
        }
        $canonical = 'GET&' . self::percentEncode('/') . '&' . self::percentEncode(implode('&', $pairs));
        return base64_encode(hash_hmac('sha1', $canonical, $secret . '&', true));
    }

    private static function percentEncode(string $value): string
    {
        return str_replace(['+', '*', '%7E'], ['%20', '%2A', '~'], urlencode($value));
    }

    /** 简单 GET：优先 curl，退回 stream（两者都没有时给出明确提示） */
    private static function httpGet(string $url): string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
            ]);
            $body = curl_exec($ch);
            $err  = curl_error($ch);
            curl_close($ch);

            if ($body === false) {
                throw new \RuntimeException('短信接口请求失败：' . $err);
            }
            return (string) $body;
        }

        $context = stream_context_create([
            'http' => ['timeout' => 15],
            'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);
        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            throw new \RuntimeException('短信接口请求失败（服务器既没有 curl 扩展，也禁用了 allow_url_fopen）');
        }
        return (string) $body;
    }
}
