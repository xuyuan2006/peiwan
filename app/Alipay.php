<?php
/**
 * 支付宝（公钥模式，RSA2 签名）—— 支持三种收款方式：
 *   page      电脑网站支付 alipay.trade.page.pay（跳转支付宝收银台，自带扫码）
 *   wap       手机网站支付 alipay.trade.wap.pay （手机浏览器唤起支付宝）
 *   precreate 当面付预下单 alipay.trade.precreate（拿到 qr_code 直链，可自行渲染二维码）
 *
 * 密钥全部在后台「支付设置」里填写，无需 Composer、无需官方 SDK。
 * 说明：异步通知会用「支付宝公钥」做 RSA2 验签（资金入账只认验签通过的通知）；
 *      预下单响应未做验签（随后仍可用 alipay.trade.query 二次确认）。
 */
declare(strict_types=1);

namespace App;

class Alipay
{
    // ---------------------------------------------------------------- 配置

    public static function gateway(): string
    {
        $gateway = trim((string) Setting::get('alipay_gateway', ''));
        return $gateway !== '' ? $gateway : 'https://openapi.alipay.com/gateway.do';
    }

    /** page / wap / precreate */
    public static function mode(): string
    {
        $mode = (string) Setting::get('alipay_mode', 'page');
        return in_array($mode, ['page', 'wap', 'precreate'], true) ? $mode : 'page';
    }

    public static function appId(): string
    {
        return trim((string) Setting::get('alipay_app_id', ''));
    }

    public static function configured(): bool
    {
        return self::appId() !== ''
            && trim((string) Setting::get('alipay_private_key', '')) !== ''
            && trim((string) Setting::get('alipay_public_key', '')) !== '';
    }

    /** 当前实际使用的支付渠道：manual / epay / alipay */
    public static function channel(): string
    {
        $channel = (string) Setting::get('pay_channel', '');
        if (!in_array($channel, ['manual', 'epay', 'alipay'], true)) {
            $channel = (string) config('pay.channel', 'manual'); // 兼容旧配置
        }
        if ($channel === 'alipay' && !self::configured()) {
            return 'manual';
        }
        if ($channel === 'epay' && !Epay::configured()) {
            return 'manual';
        }
        return in_array($channel, ['manual', 'epay', 'alipay'], true) ? $channel : 'manual';
    }

    // ---------------------------------------------------------------- 下单

    /**
     * 电脑/手机网站支付：返回可直接跳转的支付宝收银台地址
     */
    public static function payUrl(string $outTradeNo, float $amount, string $subject, string $returnUrl, string $notifyUrl, bool $wap = false): string
    {
        self::assertConfigured();

        $biz = [
            'out_trade_no' => $outTradeNo,
            'total_amount' => money($amount),
            'subject'      => mb_substr($subject, 0, 100),
            'product_code' => $wap ? 'QUICK_WAP_WAY' : 'FAST_INSTANT_TRADE_PAY',
        ];

        $params = self::buildParams($wap ? 'alipay.trade.wap.pay' : 'alipay.trade.page.pay', $biz, $notifyUrl, $returnUrl);

        return self::gateway() . '?' . http_build_query($params);
    }

    /**
     * 当面付预下单：返回 qr_code（一个 https://qr.alipay.com/xxx 链接）
     * @return array{qr_code:string,out_trade_no:string}
     */
    public static function precreate(string $outTradeNo, float $amount, string $subject, string $notifyUrl): array
    {
        self::assertConfigured();

        $biz = [
            'out_trade_no' => $outTradeNo,
            'total_amount' => money($amount),
            'subject'      => mb_substr($subject, 0, 100),
        ];

        $params   = self::buildParams('alipay.trade.precreate', $biz, $notifyUrl);
        $body     = self::httpPost(self::gateway(), $params);
        $result   = json_decode($body, true);

        if (!is_array($result)) {
            throw new \RuntimeException('支付宝返回异常：' . mb_substr($body, 0, 200));
        }
        $node = $result['alipay_trade_precreate_response'] ?? null;
        if (!is_array($node)) {
            throw new \RuntimeException('支付宝返回缺少 precreate 节点：' . mb_substr($body, 0, 200));
        }
        if ((string) ($node['code'] ?? '') !== '10000') {
            throw new \RuntimeException('支付宝下单失败：' . (string) ($node['sub_msg'] ?? $node['msg'] ?? '未知错误'));
        }

        return [
            'qr_code'      => (string) ($node['qr_code'] ?? ''),
            'out_trade_no' => (string) ($node['out_trade_no'] ?? $outTradeNo),
        ];
    }

    /** 交易查询（用于用户点「我已完成支付」时二次确认） */
    public static function query(string $outTradeNo): array
    {
        self::assertConfigured();

        $params = self::buildParams('alipay.trade.query', ['out_trade_no' => $outTradeNo]);
        $body   = self::httpPost(self::gateway(), $params);
        $result = json_decode($body, true);

        if (!is_array($result)) {
            throw new \RuntimeException('支付宝返回异常：' . mb_substr($body, 0, 200));
        }
        return (array) ($result['alipay_trade_query_response'] ?? []);
    }

    // ---------------------------------------------------------------- 验签

    /** 异步通知 RSA2 验签 */
    public static function verifyNotify(array $params): bool
    {
        if (empty($params['sign'])) {
            return false;
        }
        $sign = (string) $params['sign'];

        unset($params['sign'], $params['sign_type']);
        ksort($params);

        $pairs = [];
        foreach ($params as $k => $v) {
            if ($v === '' || $v === null || is_array($v)) {
                continue;
            }
            $pairs[] = $k . '=' . $v;
        }

        $verify = @openssl_verify(implode('&', $pairs), base64_decode($sign), self::publicKeyPem(), OPENSSL_ALGO_SHA256);
        return $verify === 1;
    }

    /** 后台「测试密钥」：应用私钥能否签名 + 支付宝公钥能否加载 + 密钥位数是否够 RSA2 */
    public static function signingCheck(): string
    {
        self::assertConfigured();

        $sample = 'app_id=' . self::appId() . '&method=alipay.trade.query';

        // 先看私钥本身：位数决定了能不能用 RSA2
        $keyRes = openssl_pkey_get_private(self::privateKeyPem());
        if ($keyRes === false) {
            throw new \RuntimeException('应用私钥无法解析，请检查是否粘贴完整（PKCS8 一行 base64 或完整 PEM）');
        }
        $bits = 0;
        $details = openssl_pkey_get_details($keyRes);
        if (is_array($details)) {
            $bits = (int) ($details['bits'] ?? 0);
        }

        if (!@openssl_sign($sample, $signature, self::privateKeyPem(), OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('应用私钥无法用于签名，请检查是否粘贴完整（PKCS8 一行 base64 或完整 PEM）');
        }

        if (openssl_pkey_get_public(self::publicKeyPem()) === false) {
            throw new \RuntimeException('支付宝公钥无法解析，请检查是否粘贴完整');
        }

        $signLen = strlen(base64_encode((string) $signature));
        $msg = '应用私钥可正常签名（' . ($bits > 0 ? $bits . ' 位，' : '') . '签名长度 ' . $signLen . ' 字符），支付宝公钥可正常加载';

        // 2048 位密钥的 RSA2 签名 base64 长 344 字符；1024 位只有 172。
        // 本站签名类型固定 RSA2，用 1024 位密钥支付宝一定回 invalid-signature。
        if ($bits > 0 && $bits < 2048) {
            $msg .= '。⚠️ 这是 ' . $bits . ' 位密钥，而本系统的签名类型固定为 RSA2（要求 2048 位），'
                  . '支付宝会返回 invalid-signature。请到支付宝开放平台用「支付宝密钥工具」重新生成 RSA2(2048) 密钥，'
                  . '上传应用公钥后，把新的应用私钥填回这里。';
        }

        // 从私钥反推出「应用公钥」，让你能和开放平台上上传的那段比对：
        // 两者一致 = 私钥和应用是一对（invalid-signature 就不是配对问题）；
        // 不一致 = 你填的是别的应用的私钥，或者平台上传的是旧公钥。
        if (is_array($details) && !empty($details['key'])) {
            $pubOneLine = preg_replace('/-----[^-]+-----|\s+/', '', (string) $details['key']);
            if (is_string($pubOneLine) && $pubOneLine !== '') {
                $msg .= '。从私钥算出的应用公钥（前 44 位）：' . substr($pubOneLine, 0, 44) . '…'
                      . '总长 ' . strlen($pubOneLine) . ' 字符 —— 请与支付宝开放平台「开发设置 → 接口加签方式」里'
                      . '上传的「应用公钥」逐字符比对：一致说明配对正常，不一致说明私钥拿错了（或平台上是旧的公钥）。';
            }
        }

        return $msg;
    }

    // ---------------------------------------------------------------- 内部

    private static function assertConfigured(): void
    {
        if (self::appId() === '') {
            throw new \RuntimeException('未配置支付宝 APPID（后台 → 支付设置）');
        }
        if (trim((string) Setting::get('alipay_private_key', '')) === '') {
            throw new \RuntimeException('未配置支付宝应用私钥（后台 → 支付设置）');
        }
    }

    private static function buildParams(string $method, array $bizContent, string $notifyUrl = '', string $returnUrl = ''): array
    {
        $params = [
            'app_id'      => self::appId(),
            'method'      => $method,
            'format'      => 'JSON',
            'charset'     => 'utf-8',
            'sign_type'   => 'RSA2',
            'timestamp'   => date('Y-m-d H:i:s'),
            'version'     => '1.0',
            'biz_content' => (string) json_encode($bizContent, JSON_UNESCAPED_UNICODE),
        ];
        if ($notifyUrl !== '') {
            $params['notify_url'] = $notifyUrl;
        }
        if ($returnUrl !== '') {
            $params['return_url'] = $returnUrl;
        }
        $params['sign'] = self::sign($params);
        return $params;
    }

    private static function sign(array $params): string
    {
        ksort($params);
        $pairs = [];
        foreach ($params as $k => $v) {
            if ($k === 'sign' || $v === '' || $v === null) {
                continue;
            }
            $pairs[] = $k . '=' . $v;
        }

        $signature = '';
        if (!@openssl_sign(implode('&', $pairs), $signature, self::privateKeyPem(), OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('支付宝签名失败，请检查应用私钥格式');
        }
        return base64_encode($signature);
    }

    private static function privateKeyPem(): string
    {
        $key = trim((string) Setting::get('alipay_private_key', ''));
        if ($key === '') {
            throw new \RuntimeException('未配置支付宝应用私钥');
        }
        if (strpos($key, '-----BEGIN') !== false) {
            return $key;
        }
        return "-----BEGIN PRIVATE KEY-----\n" . chunk_split(preg_replace('/\s+/', '', $key), 64, "\n") . "-----END PRIVATE KEY-----\n";
    }

    private static function publicKeyPem(): string
    {
        $key = trim((string) Setting::get('alipay_public_key', ''));
        if ($key === '') {
            throw new \RuntimeException('未配置支付宝公钥');
        }
        if (strpos($key, '-----BEGIN') !== false) {
            return $key;
        }
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(preg_replace('/\s+/', '', $key), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    private static function httpPost(string $url, array $params): string
    {
        $body = http_build_query($params);

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $body,
                CURLOPT_TIMEOUT        => 20,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded;charset=utf-8'],
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
            ]);
            $response = curl_exec($ch);
            $err      = curl_error($ch);
            curl_close($ch);
            if ($response === false) {
                throw new \RuntimeException('请求支付宝失败：' . $err);
            }
            return (string) $response;
        }

        $context = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/x-www-form-urlencoded;charset=utf-8\r\n",
                'content' => $body,
                'timeout' => 20,
            ],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);
        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            throw new \RuntimeException('请求支付宝失败（服务器既没有 curl 扩展，也禁用了 allow_url_fopen）');
        }
        return (string) $response;
    }
}
