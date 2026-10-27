<?php
/**
 * 后台支付设置：人工转账 / 易支付 / 支付宝（网站支付 · 手机支付 · 当面付）
 */
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Alipay;
use App\Auth;
use App\Epay;
use App\Setting;

class PaymentController extends BaseController
{
    /** 键 => [标题, 类型(text/password/textarea/select), 说明, 选项] */
    private function fields(): array
    {
        return [
            'pay_channel'        => ['支付渠道', 'select', '选择后立即生效；参数没配完整时会自动退回「人工转账」', [
                'manual' => '人工转账（用户在充值页提交，客服核对后入账）',
                'epay'   => '易支付（跳转第三方收银台，支持微信/支付宝）',
                'alipay' => '支付宝官方（网站支付 / 手机支付 / 当面付）',
            ]],
            'epay_api_url'       => ['易支付网关地址', 'text', '例如 https://pay.example.com（不带 submit.php）', []],
            'epay_pid'           => ['易支付商户ID', 'text', '', []],
            'epay_key'           => ['易支付商户密钥', 'password', '留空表示不修改', []],
            'alipay_app_id'      => ['支付宝 APPID', 'text', '开放平台网页/移动应用或当面付应用的 APPID', []],
            'alipay_private_key' => ['应用私钥', 'password', 'PKCS8 一行 base64 或完整 PEM；留空表示不修改', []],
            'alipay_public_key'  => ['支付宝公钥', 'password', '用于异步通知验签；留空表示不修改', []],
            'alipay_gateway'     => ['支付宝网关', 'text', '沙箱环境填 https://openapi.alipaydev.com/gateway.do', []],
            'alipay_mode'        => ['支付宝收款方式', 'select', '', [
                'page'      => '电脑网站支付（跳转支付宝收银台，自带二维码）',
                'wap'       => '手机网站支付（手机浏览器唤起支付宝 App）',
                'precreate' => '当面付预下单（返回 qr_code 直链，页面展示可点可复制）',
            ]],
        ];
    }

    public function index()
    {
        $this->view('admin/payment/index', [
            'title'        => '支付设置',
            'fields'       => $this->fields(),
            'current'      => Setting::all(),
            'active'       => Alipay::channel(),
            'epay_ok'      => Epay::configured(),
            'alipay_ok'    => Alipay::configured(),
            'epay_notify'  => base_url() . '/index.php?r=pay/notify',
            'epay_return'  => base_url() . '/index.php?r=pay/back',
            'ali_notify'   => base_url() . '/index.php?r=pay/alipayNotify',
            'ali_return'   => base_url() . '/index.php?r=pay/alipayReturn',
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

            foreach ($fields as $key => $meta) {
                $type = $meta[1];
                if ($type === 'select') {
                    $options = $meta[3];
                    $value   = input_str($key);
                    if (!array_key_exists($value, $options)) {
                        throw new \RuntimeException($meta[0] . ' 取值不合法');
                    }
                    $values[$key] = $value;
                } elseif ($type === 'password') {
                    // 留空表示不修改，避免把已配置的密钥清空
                    $value = trim((string) input($key));
                    $values[$key] = $value === '' ? (string) ($current[$key] ?? '') : $value;
                } else {
                    $values[$key] = mb_substr(input_str($key), 0, 490);
                }
            }

            // 按所选渠道校验参数是否齐全
            if ($values['pay_channel'] === 'epay') {
                if ($values['epay_api_url'] === '' || $values['epay_pid'] === '' || $values['epay_key'] === '') {
                    throw new \RuntimeException('选择易支付时，网关地址、商户ID、商户密钥都必须填写');
                }
            }
            if ($values['pay_channel'] === 'alipay') {
                if ($values['alipay_app_id'] === '' || $values['alipay_private_key'] === '' || $values['alipay_public_key'] === '') {
                    throw new \RuntimeException('选择支付宝时，APPID、应用私钥、支付宝公钥都必须填写');
                }
            }

            foreach ($values as $key => $value) {
                Setting::set($key, $value);
            }

            Auth::logAdmin('修改支付设置', '渠道 ' . $values['pay_channel']);
            return $values;
        }, '支付设置已保存', admin_url('payment/index'));
    }

    /** 测试支付宝密钥是否可用（不发起真实交易） */
    public function testKeys()
    {
        require_post();
        verify_csrf();

        $this->run(function () {
            $result = Alipay::signingCheck();
            Auth::logAdmin('测试支付宝密钥', $result);
            return ['message' => $result];
        }, '密钥检测通过', admin_url('payment/index'));
    }
}
