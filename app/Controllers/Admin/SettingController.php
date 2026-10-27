<?php
/**
 * 后台平台设置（含邮箱验证码 / SMTP 配置）
 */
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth;
use App\Mailer;
use App\Setting;

class SettingController extends BaseController
{
    /** 可配置项：键 => [标题, 类型, 分组, 选项/说明] */
    private function fields(): array
    {
        return [
            // 基础
            'site_name'                 => ['站点名称', 'text', 'basic', ''],
            'site_notice'               => ['首页公告', 'textarea', 'basic', ''],
            'service_phone'             => ['客服联系方式', 'text', 'basic', ''],
            // 交易
            'platform_fee_rate'         => ['平台抽成比例(%)', 'number', 'trade', ''],
            'min_withdraw'              => ['最低提现金额(元)', 'number', 'trade', ''],
            'withdraw_fee_rate'         => ['提现手续费(%)', 'number', 'trade', ''],
            // 订单
            'order_auto_cancel_minutes' => ['未支付自动关闭(分钟)', 'number', 'order', ''],
            'order_auto_confirm_hours'  => ['服务完成自动确认(小时)', 'number', 'order', ''],
            'companion_need_audit'      => ['陪玩入驻需要审核', 'bool', 'order', '关闭后，用户提交入驻申请即自动通过，无需人工审核。'],
            // 邮箱验证码
            'mail_enabled'              => ['开启邮箱验证码', 'bool', 'mail', '开启后按下面的开关决定哪些环节需要邮箱验证；必须配置好 SMTP 才会真正生效。'],
            'mail_verify_register'      => ['注册需要邮箱验证', 'bool', 'mail', ''],
            'mail_verify_reset'         => ['找回密码需要邮箱验证', 'bool', 'mail', ''],
            'mail_smtp_host'            => ['SMTP 服务器', 'text', 'mail', '例如 smtp.qq.com、smtp.163.com'],
            'mail_smtp_port'            => ['SMTP 端口', 'number', 'mail', 'SSL 一般 465，STARTTLS 一般 587'],
            'mail_smtp_secure'          => ['SMTP 加密方式', 'select', 'mail', ['ssl' => 'SSL（465）', 'tls' => 'STARTTLS（587）', 'none' => '不加密（25）']],
            'mail_smtp_user'            => ['SMTP 账号', 'text', 'mail', '一般是你的邮箱地址'],
            'mail_smtp_pass'            => ['SMTP 密码 / 授权码', 'password', 'mail', '留空表示不修改；QQ/163 邮箱需要填「授权码」而不是登录密码'],
            'mail_from'                 => ['发件人邮箱', 'text', 'mail', '留空则使用 SMTP 账号'],
            'mail_from_name'            => ['发件人名称', 'text', 'mail', '留空则使用站点名称'],
            // 验证方式（注册 / 找回密码各选一个渠道）
            'verify_register_channel'   => ['注册验证方式', 'select', 'verify', ['off' => '关闭（不验证）', 'email' => '邮箱验证码', 'sms' => '手机短信验证码']],
            'verify_reset_channel'      => ['找回密码验证方式', 'select', 'verify', ['off' => '关闭（提示联系客服）', 'email' => '邮箱验证码', 'sms' => '手机短信验证码']],
            'verify_code_ttl_minutes'   => ['验证码有效期(分钟)', 'number', 'verify', '建议 3~10 分钟；邮件文案里写的时长会自动跟这里保持一致'],
            // 短信（阿里云）
            'sms_driver'                => ['短信服务商', 'select', 'sms', ['none' => '未配置', 'aliyun' => '阿里云短信']],
            'sms_access_key_id'         => ['AccessKeyId', 'text', 'sms', '阿里云控制台创建的 AccessKey ID'],
            'sms_access_key_secret'     => ['AccessKeySecret', 'password', 'sms', '留空表示不修改'],
            'sms_sign_name'             => ['短信签名', 'text', 'sms', '需为阿里云已审核通过的签名，例如：陪玩平台'],
            'sms_template_code'         => ['短信模板 CODE', 'text', 'sms', '模板内容需包含 ${code}，例如 SMS_123456789'],
            'sms_region'                => ['短信接入区域', 'text', 'sms', '一般填 cn-hangzhou'],
            // 运营展示
            'notice_interval'           => ['公告走马灯间隔(秒)', 'number', 'display', '前台顶部公告栏每隔几秒翻下一条，2~60'],
            'banner_interval'           => ['广告轮播间隔(秒)', 'number', 'display', '首页广告图每隔几秒切换，2~60'],
            'site_logo'                 => ['网站 Logo', 'image', 'display', '上传后前台顶部与 App 登录页都会用它；留空则用系统默认图标'],
            'app_min_version'           => ['App 最新版本号', 'text', 'display', '例如 1.1。App 会拿自己的版本和它比，服务端更大就弹更新窗；填 1.0 则不弹'],
            'app_force_update'          => ['强制更新', 'bool', 'display', '勾选后更新窗不能关闭，用户必须去更新才能继续用'],
            'app_update_url'            => ['App 下载地址', 'text', 'display', '点「立即更新」会打开这个链接（留空则该按钮不显示）'],
            'app_update_note'           => ['App 更新说明', 'textarea', 'display', '弹窗里显示的更新内容，用户可一键复制'],
            'companion_intro'           => ['成为陪玩的说明', 'textarea', 'display', '显示在「我的」页面底部；每行一条，留空则整块不显示'],
            // 陪玩申请（网页端与 App 共用）
            'companion_assess'          => ['陪玩考核标准', 'textarea', 'display', '申请陪玩时必须阅读满 4 秒才能提交；留空则不拦截'],
            'companion_contact_qr'      => ['客服二维码图片', 'text', 'display', '提交申请后展示。填图片地址，如 /uploads/qr.png；留空则不显示二维码'],
            'companion_contact_text'    => ['客服联系方式（文字）', 'text', 'display', '提交申请后展示，例如微信号。与二维码至少填一个'],
        ];
    }

    private function groupTitles(): array
    {
        return [
            'basic'  => '基础设置',
            'trade'  => '交易与结算',
            'order'  => '订单与审核',
            'verify' => '验证方式',
            'mail'   => '邮箱 / SMTP',
            'sms'    => '短信（阿里云）',
            'display' => '运营展示',
        ];
    }

    public function index()
    {
        $this->view('admin/setting/index', [
            'title'            => '平台设置',
            'fields'           => $this->fields(),
            'groups'           => $this->groupTitles(),
            'current'          => Setting::all(),
            'mail_ok'          => Mailer::configured(),
            'sms_ok'           => \App\Sms::configured(),
            'channel_register' => \App\VerifyCode::channelFor('register'),
            'channel_reset'    => \App\VerifyCode::channelFor('reset'),
        ]);
    }

    public function save()
    {
        require_post();
        verify_csrf();

        $this->run(function () {
            $fields  = $this->fields();
            $current = Setting::all();
            $values  = [];

            // 图片类设置（如站点 Logo）：先处理文件上传。
            // Upload::image() 没收到文件时返回 null，所以「没选新图」和「选了新图」能区分开。
            $images = [];
            foreach ($fields as $key => $meta) {
                if ($meta[1] !== 'image') {
                    continue;
                }
                $uploaded = \App\Upload::image($key . '_file', 'logo');
                if ($uploaded !== null) {
                    $images[$key] = $uploaded;                       // 传了新图
                } elseif (input_int($key . '_clear', 0) === 1) {
                    $images[$key] = '';                              // 勾了删除，回默认
                }
                // 两者都没有：保持原值，下面用 $current 兜住
            }

            foreach ($fields as $key => $meta) {
                $type = $meta[1];

                if ($type === 'image') {
                    // 表单里没有同名的文本输入，值只能来自上面的文件上传结果
                    $values[$key] = array_key_exists($key, $images)
                        ? $images[$key]
                        : (string) ($current[$key] ?? '');
                    continue;
                }

                if ($type === 'bool') {
                    $values[$key] = input_int($key, 0) === 1 ? '1' : '0';
                } elseif ($type === 'number') {
                    $values[$key] = (string) safe_float(input_float($key, 0));
                } elseif ($type === 'select') {
                    $options = is_array($meta[3]) ? $meta[3] : [];
                    $value   = input_str($key);
                    if (!array_key_exists($value, $options)) {
                        throw new \RuntimeException($meta[0] . ' 取值不合法');
                    }
                    $values[$key] = $value;
                } elseif ($type === 'password') {
                    // 留空表示不修改，避免把已配置的授权码清空
                    $value = (string) input($key);
                    $values[$key] = $value === '' ? (string) ($current[$key] ?? '') : mb_substr($value, 0, 200);
                } elseif ($type === 'textarea') {
                    // 多行内容（如「陪玩考核标准」）单独放宽上限，
                    // 配合 sql/api_upgrade.sql 把 settings.v 从 VARCHAR(500) 改成 TEXT
                    $values[$key] = mb_substr(input_str($key), 0, 20000);
                } else {
                    $values[$key] = mb_substr(input_str($key), 0, 490);
                }
            }

            // 关键数值校验（全部通过后再落库）
            if ((float) $values['platform_fee_rate'] < 0 || (float) $values['platform_fee_rate'] > 100) {
                throw new \RuntimeException('平台抽成比例需在 0 ~ 100 之间');
            }
            if ((float) $values['withdraw_fee_rate'] < 0 || (float) $values['withdraw_fee_rate'] > 100) {
                throw new \RuntimeException('提现手续费比例需在 0 ~ 100 之间');
            }
            if ((float) $values['min_withdraw'] < 0) {
                throw new \RuntimeException('最低提现金额不能为负');
            }
            if ((int) $values['order_auto_cancel_minutes'] < 1) {
                throw new \RuntimeException('未支付自动关闭时间至少 1 分钟');
            }
            if ((int) $values['order_auto_confirm_hours'] < 1) {
                throw new \RuntimeException('自动确认时间至少 1 小时');
            }
            if ((int) $values['mail_smtp_port'] < 1 || (int) $values['mail_smtp_port'] > 65535) {
                throw new \RuntimeException('SMTP 端口需在 1 ~ 65535 之间');
            }
            if ($values['mail_from'] !== '' && !filter_var($values['mail_from'], FILTER_VALIDATE_EMAIL)) {
                throw new \RuntimeException('发件人邮箱格式不正确');
            }
            if ($values['mail_smtp_user'] !== '' && strpos($values['mail_smtp_user'], '@') !== false
                && !filter_var($values['mail_smtp_user'], FILTER_VALIDATE_EMAIL)) {
                throw new \RuntimeException('SMTP 账号看起来不是邮箱地址，请检查');
            }
            $effectiveFrom = $values['mail_from'] !== '' ? $values['mail_from'] : $values['mail_smtp_user'];
            $effectiveMail = $values['mail_smtp_host'] !== '' && $effectiveFrom !== '';
            if ($values['mail_enabled'] === '1' && !$effectiveMail) {
                throw new \RuntimeException('开启邮箱验证码前，请先填写 SMTP 服务器与发件人邮箱（或 SMTP 账号）');
            }

            // 选了某个验证渠道，就必须先把这个渠道配置好，否则用户会收不到码
            $smsSecretKept = $values['sms_access_key_secret'] !== ''
                || (string) ($current['sms_access_key_secret'] ?? '') !== '';
            $effectiveSms  = $values['sms_driver'] === 'aliyun'
                && $values['sms_access_key_id'] !== ''
                && $smsSecretKept
                && $values['sms_sign_name'] !== ''
                && $values['sms_template_code'] !== '';

            foreach (['verify_register_channel' => '注册', 'verify_reset_channel' => '找回密码'] as $key => $label) {
                if ($values[$key] === 'email' && !$effectiveMail) {
                    throw new \RuntimeException($label . '验证方式选了「邮箱验证码」，请先把 SMTP 配置完整');
                }
                if ($values[$key] === 'sms' && !$effectiveSms) {
                    throw new \RuntimeException($label . '验证方式选了「手机短信验证码」，请先把短信服务配置完整');
                }
            }

            // 运营展示：轮播 / 走马灯间隔
            foreach (['notice_interval' => '公告走马灯间隔', 'banner_interval' => '广告轮播间隔'] as $key => $label) {
                $seconds = (int) $values[$key];
                if ($seconds < 2 || $seconds > 60) {
                    throw new \RuntimeException($label . '需在 2 ~ 60 秒之间');
                }
                $values[$key] = (string) $seconds;
            }

            foreach ($values as $key => $value) {
                Setting::set($key, $value);
            }

            Auth::logAdmin('修改平台设置', (string) json_encode($values, JSON_UNESCAPED_UNICODE));
            return $values;
        }, '设置已保存', admin_url('setting/index'));
    }

    /** 发送测试邮件，验证 SMTP 配置是否正确 */
    public function testMail()
    {
        require_post();
        verify_csrf();

        $to = input_str('to');
        $this->run(function () use ($to) {
            if ($to === '') {
                throw new \RuntimeException('请填写接收测试邮件的邮箱');
            }
            Mailer::sendTest($to);
            Auth::logAdmin('发送测试邮件', '收件人 ' . $to);
            return true;
        }, '测试邮件已发送，请到收件箱（含垃圾箱）查看', admin_url('setting/index'));
    }

    /** 发送测试短信，验证短信配置是否正确 */
    public function testSms()
    {
        require_post();
        verify_csrf();

        $phone = input_str('phone');
        $this->run(function () use ($phone) {
            if ($phone === '') {
                throw new \RuntimeException('请填写接收测试短信的手机号');
            }
            \App\Sms::sendTest($phone);
            Auth::logAdmin('发送测试短信', '手机号 ' . $phone);
            return true;
        }, '测试短信已发送，请查看手机', admin_url('setting/index'));
    }
}
