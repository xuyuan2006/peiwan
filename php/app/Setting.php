<?php
/**
 * 平台设置（settings 表，键值对）
 */
declare(strict_types=1);

namespace App;

class Setting
{
    /** @var array<string,string>|null */
    private static $cache = null;

    /** 默认值：即使数据库还没建好也不会报错 */
    private static $defaults = [
        'site_name'                => '陪玩点单平台',
        'site_notice'              => '欢迎来到陪玩点单平台，下单后请耐心等待陪玩接单。',
        'platform_fee_rate'        => '20',
        'min_withdraw'             => '50',
        'withdraw_fee_rate'        => '1',
        'order_auto_cancel_minutes' => '30',
        'order_auto_confirm_hours' => '24',
        'companion_need_audit'     => '1',
        'service_phone'            => '',
        // 邮箱验证码 / SMTP
        'mail_enabled'             => '0',
        'mail_verify_register'     => '1',
        'mail_verify_reset'        => '1',
        'mail_smtp_host'           => '',
        'mail_smtp_port'           => '465',
        'mail_smtp_secure'         => 'ssl',
        'mail_smtp_user'           => '',
        'mail_smtp_pass'           => '',
        'mail_from'                => '',
        'mail_from_name'           => '',
        // 验证方式（注册 / 找回密码）
        'verify_register_channel'  => 'email',
        'verify_reset_channel'     => 'email',
        'verify_code_ttl_minutes'  => '3',
        // 短信（阿里云）
        'sms_driver'               => 'none',
        'sms_access_key_id'        => '',
        'sms_access_key_secret'    => '',
        'sms_sign_name'            => '',
        'sms_template_code'        => '',
        'sms_region'               => 'cn-hangzhou',
        // 支付渠道
        'pay_channel'              => 'manual',
        'epay_api_url'             => '',
        'epay_pid'                 => '',
        'epay_key'                 => '',
        'alipay_app_id'            => '',
        'alipay_private_key'       => '',
        'alipay_public_key'        => '',
        'alipay_gateway'           => 'https://openapi.alipay.com/gateway.do',
        'alipay_mode'              => 'page',
        // 运营展示
        'site_logo'                => '',
        // App 版本更新（客户端会和自己的版本号比较，服务端更大就弹更新窗）
        'app_min_version'          => '1.0.0',
        'app_force_update'         => '0',
        'app_update_url'           => '',
        'app_update_note'          => '',
        'notice_interval'          => '4',
        'banner_interval'          => '5',
        'companion_intro'          => "上架自己的服务项目（带配图与价格），用户可以直接下单买你的时间。\n在「接单大厅」抢平台发布的订单，接单后按约定时间提供服务。\n服务完成后钱进钱包，可随时申请提现。",
        // 陪玩申请（网页端与 App 共用）
        'companion_assess'         => "1. 年满 18 周岁，具备完全民事行为能力\n2. 提供真实有效的身份信息，平台会进行审核\n3. 至少熟练掌握一款主流游戏，段位达到平台要求\n4. 服务期间保持礼貌、准时，不得辱骂或消极游戏\n5. 严禁私下交易、绕开平台收款，一经发现永久封禁\n6. 接单后请按约定时间提供服务，临时有事提前沟通",
        'companion_contact_qr'     => '',
        'companion_contact_text'   => '',
    ];

    public static function warmup(): void
    {
        try {
            self::all();
        } catch (\Throwable $e) {
            self::$cache = self::$defaults;
        }
    }

    /** @return array<string,string> */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $rows  = Db::all('SELECT `k`, `v` FROM settings');
        $data  = self::$defaults;
        foreach ($rows as $r) {
            $data[(string) $r['k']] = (string) $r['v'];
        }
        return self::$cache = $data;
    }

    /** @return mixed */
    public static function get(string $key, $default = null)
    {
        $all = self::all();
        if (array_key_exists($key, $all)) {
            return $all[$key];
        }
        return $default !== null ? $default : (self::$defaults[$key] ?? null);
    }

    public static function set(string $key, $value): void
    {
        Db::query(
            'INSERT INTO settings (`k`, `v`, `updated_at`) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE `v` = VALUES(`v`), `updated_at` = VALUES(`updated_at`)',
            [$key, (string) $value, now()]
        );
        self::$cache = null;
    }

    public static function float(string $key, float $default = 0.0): float
    {
        return (float) self::get($key, $default);
    }

    public static function int(string $key, int $default = 0): int
    {
        return (int) self::get($key, $default);
    }
}
