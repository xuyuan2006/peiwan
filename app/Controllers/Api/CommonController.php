<?php
/**
 * 公共接口：站点配置、App 启动信息。
 *
 * 安全约定：这里只输出「本来就展示在前台页面」的配置，
 * 绝不返回 SMTP 密码、支付密钥、短信 AK 等 setting 敏感项。
 */
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Api\TokenAuth;
use App\Setting;
use App\VerifyCode;

class CommonController extends BaseController
{
    /** 站点公开配置（App 可在启动时拉一次并缓存） */
    public function config(): void
    {
        // 后台填的是相对路径（如 /uploads/qr.png），App 需要绝对地址才能加载图片
        $contactQr = (string) Setting::get('companion_contact_qr', '');
        $logo      = (string) Setting::get('site_logo', '');

        $this->ok([
            'site_name'          => site_name(),
            // 站点 Logo：留空表示用客户端自带的默认图标
            'site_logo'          => $logo !== '' ? upload_url($logo) : '',
            'site_notice'        => (string) Setting::get('site_notice', ''),
            'service_phone'      => (string) Setting::get('service_phone', ''),
            'notice_interval'    => Setting::int('notice_interval', 4),
            'banner_interval'    => Setting::int('banner_interval', 5),
            'companion_intro'    => (string) Setting::get('companion_intro', ''),
            'companion_need_audit' => Setting::int('companion_need_audit', 1) === 1,
            // 陪玩申请相关（均在后台「平台设置」里填写）
            //  - assess：考核标准，申请页必须停留看完才能提交
            //  - contact_qr / contact_text：提交申请后展示的联系方式
            'companion_assess'       => (string) Setting::get('companion_assess', ''),
            'companion_contact_qr'   => $contactQr !== '' ? upload_url($contactQr) : '',
            'companion_contact_text' => (string) Setting::get('companion_contact_text', ''),
            'min_withdraw'       => Setting::float('min_withdraw', 50),
            'withdraw_fee_rate'  => Setting::float('withdraw_fee_rate', 1),
            'platform_fee_rate'  => Setting::float('platform_fee_rate', 20),
            'pay_channel'        => (string) Setting::get('pay_channel', 'manual'),
            'recharge_channels'  => self::rechargeChannels(),
            'order_auto_cancel_minutes' => Setting::int('order_auto_cancel_minutes', 30),
            'order_auto_confirm_hours'  => Setting::int('order_auto_confirm_hours', 24),
            'verify'             => [
                'register' => self::verifyInfo('register'),
                'reset'    => self::verifyInfo('reset'),
            ],
        ]);
    }

    /** App 冷启动握手：判断服务端可用性、是否需要强制升级 */
    public function init(): void
    {
        $this->ok([
            'api_version'     => 'v1',
            'server_time'     => now(),
            'timestamp'       => time(),
            'site_name'       => site_name(),
            'installed'       => true,
            'api_ready'       => TokenAuth::ready(),
            'min_app_version' => (string) Setting::get('app_min_version', '1.0.0'),
            'force_update'    => Setting::int('app_force_update', 0) === 1,
            'update_url'      => (string) Setting::get('app_update_url', ''),
            'update_note'     => (string) Setting::get('app_update_note', ''),
        ]);
    }

    /** @return array<string,mixed> */
    private static function verifyInfo(string $scene): array
    {
        $channel = VerifyCode::channelFor($scene);
        return [
            'channel'     => $channel,
            'label'       => VerifyCode::targetLabel($channel),
            'enabled'     => VerifyCode::isEnabled($scene),
            'ttl_minutes' => VerifyCode::ttlMinutes(),
        ];
    }

    /**
     * 当前可用的充值渠道。
     * 人工转账始终可用；在线渠道只在后台配好了密钥时才告诉 App，避免点了报错。
     */
    private static function rechargeChannels(): array
    {
        $channels = [[
            'key'   => 'manual',
            'name'  => '人工转账',
            'hint'  => '转账后上传凭证，客服确认到账',
            'online'=> false,
        ]];

        $payChannel = (string) Setting::get('pay_channel', 'manual');

        if ($payChannel === 'epay' && \App\Epay::configured()) {
            $channels[] = [
                'key'    => 'epay',
                'name'   => '在线支付（易支付）',
                'hint'   => '跳转第三方收银台',
                'online' => true,
            ];
        }

        if ($payChannel === 'alipay' && \App\Alipay::configured()) {
            $mode = \App\Alipay::mode();
            $channels[] = [
                'key'    => 'alipay',
                'name'   => '支付宝',
                'hint'   => $mode === 'precreate' ? '扫码支付' : '跳转支付宝付款',
                'online' => true,
            ];
        }

        return $channels;
    }
}
