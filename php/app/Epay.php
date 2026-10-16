<?php
/**
 * 易支付（彩虹易支付协议）适配器：跳转收银台 + 异步通知签名校验。
 *
 * 参数优先读后台「支付设置」，读不到时回落到 config/config.php 的 pay 节点（兼容旧配置）。
 * 如需接入微信/支付宝官方接口，可在 app/Alipay.php 同级新增实现，业务层无需改动。
 */
declare(strict_types=1);

namespace App;

class Epay
{
    public static function apiUrl(): string
    {
        $url = trim((string) Setting::get('epay_api_url', ''));
        return $url !== '' ? $url : trim((string) config('pay.epay.api_url', ''));
    }

    public static function pid(): string
    {
        $pid = trim((string) Setting::get('epay_pid', ''));
        return $pid !== '' ? $pid : trim((string) config('pay.epay.pid', ''));
    }

    public static function key(): string
    {
        $key = (string) Setting::get('epay_key', '');
        return $key !== '' ? $key : (string) config('pay.epay.key', '');
    }

    /** 参数是否配置完整 */
    public static function configured(): bool
    {
        return self::apiUrl() !== '' && self::pid() !== '' && self::key() !== '';
    }

    /** 当前支付渠道是否为易支付且配置完整 */
    public static function enabled(): bool
    {
        return Alipay::channel() === 'epay' && self::configured();
    }

    /**
     * 生成收银台跳转地址
     * @param array{out_trade_no:string,name:string,money:string,notify_url:string,return_url:string,type?:string} $order
     */
    public static function payUrl(array $order): string
    {
        $params = [
            'pid'          => self::pid(),
            'type'         => (string) ($order['type'] ?? 'alipay'),
            'out_trade_no' => $order['out_trade_no'],
            'notify_url'   => $order['notify_url'],
            'return_url'   => $order['return_url'],
            'name'         => $order['name'],
            'money'        => $order['money'],
        ];
        $params['sign']      = self::sign($params);
        $params['sign_type'] = 'MD5';

        $base = rtrim(self::apiUrl(), '/');
        if (substr($base, -11) === 'submit.php') {
            return $base . '?' . http_build_query($params);
        }
        return $base . '/submit.php?' . http_build_query($params);
    }

    /** 校验异步通知签名 */
    public static function verify(array $params): bool
    {
        if (empty($params['sign'])) {
            return false;
        }
        return hash_equals(self::sign($params), (string) $params['sign']);
    }

    public static function sign(array $params): string
    {
        unset($params['sign'], $params['sign_type']);
        $params = array_filter($params, function ($v) {
            return $v !== '' && $v !== null;
        });
        ksort($params);

        $pairs = [];
        foreach ($params as $k => $v) {
            $pairs[] = $k . '=' . $v;
        }
        return md5(implode('&', $pairs) . self::key());
    }
}
