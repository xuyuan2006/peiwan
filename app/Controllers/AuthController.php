<?php
/**
 * 注册 / 登录 / 退出 / 验证码 / 找回密码
 *
 * 验证方式由后台按环节配置（off / email 邮箱验证码 / sms 手机短信验证码），
 * 统一走 App\VerifyCode。
 */
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Db;
use App\VerifyCode;

class AuthController extends BaseController
{
    public function login()
    {
        if (Auth::check()) {
            redirect_route('home/index');
        }

        if (is_post()) {
            verify_csrf();
            $account  = input_str('account');
            $password = (string) input('password');

            if ($account === '' || $password === '') {
                flash('error', '请填写账号和密码');
                keep_old(['account' => $account]);
                redirect_route('auth/login');
            }

            if (!Auth::attempt($account, $password)) {
                flash('error', '账号或密码错误，或账号已被禁用');
                keep_old(['account' => $account]);
                redirect_route('auth/login');
            }

            $intended = $_SESSION['_intended'] ?? '';
            unset($_SESSION['_intended']);
            flash('success', '登录成功，欢迎回来');
            if ($intended !== '' && strpos($intended, '://') === false) {
                redirect($intended);
            }
            redirect_route('home/index');
        }

        $this->view('auth/login', ['title' => '登录'], 'layout');
    }

    public function register()
    {
        if (Auth::check()) {
            redirect_route('home/index');
        }

        $channel = VerifyCode::channelFor('register');

        if (is_post()) {
            verify_csrf();
            $nickname = input_str('nickname');
            $phone    = input_str('phone');
            $email    = strtolower(input_str('email'));
            $code     = input_str('email_code');
            $password = (string) input('password');
            $confirm  = (string) input('password_confirm');
            $gender   = input_int('gender', 0);

            keep_old([
                'nickname' => $nickname, 'phone' => $phone,
                'email' => $email, 'gender' => $gender,
            ]);

            try {
                // 账号不再由用户填写，改由服务端分配（Auth::generateUsername）
                if ($nickname === '') {
                    throw new \RuntimeException('请填写昵称');
                }
                if (mb_strlen($nickname) > 20) {
                    throw new \RuntimeException('昵称最多 20 个字');
                }
                if (strlen($password) < 6) {
                    throw new \RuntimeException('密码至少 6 位');
                }
                if ($password !== $confirm) {
                    throw new \RuntimeException('两次输入的密码不一致');
                }

                // 手机号与邮箱都必填
                if ($phone === '') {
                    throw new \RuntimeException('请填写手机号');
                }
                if (!is_phone($phone)) {
                    throw new \RuntimeException('手机号格式不正确');
                }
                if (Db::one('SELECT id FROM users WHERE phone = ? LIMIT 1', [$phone])) {
                    throw new \RuntimeException('该手机号已被注册');
                }

                if ($email === '') {
                    throw new \RuntimeException('请填写邮箱');
                }
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new \RuntimeException('邮箱格式不正确');
                }
                if (Db::one('SELECT id FROM users WHERE email = ? LIMIT 1', [$email])) {
                    throw new \RuntimeException('该邮箱已被注册');
                }

                // 按后台配置的验证方式校验
                if ($channel === 'email') {
                    VerifyCode::verify('register', $email, $code);
                } elseif ($channel === 'sms') {
                    VerifyCode::verify('register', $phone, $code);
                }

                $username = Auth::generateUsername();

                $userId = Db::tx(function () use ($username, $password, $nickname, $phone, $email, $gender) {
                    $id = Db::insert('users', Auth::newUserFields($username, $password, $nickname, $phone, $email, $gender));
                    Db::query(
                        'INSERT IGNORE INTO user_wallets (user_id, balance, frozen, total_recharge, total_income, total_withdraw, total_expense, created_at, updated_at)
                         VALUES (?, 0, 0, 0, 0, 0, 0, ?, ?)',
                        [$id, now(), now()]
                    );
                    return $id;
                });

                Auth::login($userId);
                clear_old();
                flash('success', '注册成功，欢迎加入');
                redirect_route('home/index');
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
                redirect_route('auth/register');
            }
        }

        $this->view('auth/register', [
            'title'          => '注册',
            'verify_channel' => $channel,
            'verify_label'   => VerifyCode::targetLabel($channel),
        ]);
    }

    /** 发送验证码（注册 / 找回密码共用） */
    public function sendCode()
    {
        require_post();
        verify_csrf();

        $scene  = input_str('scene', 'register');
        $target = input_str('target');
        if ($target === '') {
            // 兼容旧字段名
            $target = input_str('email');
        }
        if ($target === '') {
            $target = input_str('phone');
        }

        try {
            VerifyCode::send($scene, $target);
            $msg = '验证码已发送，请查收（' . VerifyCode::ttlMinutes() . ' 分钟内有效）';
            if (is_ajax()) {
                json_out(['code' => 0, 'msg' => $msg]);
            }
            flash('success', $msg);
        } catch (\Throwable $e) {
            if (is_ajax()) {
                json_out(['code' => 1, 'msg' => $e->getMessage()], 400);
            }
            flash('error', $e->getMessage());
        }

        redirect_back($scene === 'reset' ? 'auth/forgot' : 'auth/register');
    }

    /** 找回密码：验证码 + 新密码 */
    public function forgot()
    {
        if (Auth::check()) {
            redirect_route('home/index');
        }

        $channel = VerifyCode::channelFor('reset');

        if (is_post()) {
            verify_csrf();
            $email    = strtolower(input_str('email'));
            $phone    = input_str('phone');
            $code     = input_str('email_code');
            $password = (string) input('password');
            $confirm  = (string) input('password_confirm');

            keep_old(['email' => $email, 'phone' => $phone]);

            try {
                if ($channel === 'off') {
                    throw new \RuntimeException('平台未开启验证码找回密码，请联系客服协助重置');
                }
                if (strlen($password) < 6) {
                    throw new \RuntimeException('新密码至少 6 位');
                }
                if ($password !== $confirm) {
                    throw new \RuntimeException('两次输入的密码不一致');
                }

                if ($channel === 'email') {
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        throw new \RuntimeException('邮箱格式不正确');
                    }
                    $user = Db::one('SELECT id, status FROM users WHERE email = ? LIMIT 1', [$email]);
                    if (!$user) {
                        throw new \RuntimeException('该邮箱没有绑定任何账号');
                    }
                    $target = $email;
                } else {
                    if (!is_phone($phone)) {
                        throw new \RuntimeException('手机号格式不正确');
                    }
                    $user = Db::one('SELECT id, status FROM users WHERE phone = ? LIMIT 1', [$phone]);
                    if (!$user) {
                        throw new \RuntimeException('该手机号没有绑定任何账号');
                    }
                    $target = $phone;
                }

                if ((int) $user['status'] !== 1) {
                    throw new \RuntimeException('该账号已被禁用，请联系客服');
                }

                VerifyCode::verify('reset', $target, $code);

                Db::tx(function () use ($user, $password, $target) {
                    Db::update('users', [
                        'password'   => password_hash($password, PASSWORD_DEFAULT),
                        'updated_at' => now(),
                    ], 'id = ?', [(int) $user['id']]);
                    VerifyCode::invalidate('reset', $target);
                });

                clear_old();
                flash('success', '密码已重置，请用新密码登录');
                redirect_route('auth/login');
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
                redirect_route('auth/forgot');
            }
        }

        $this->view('auth/forgot', [
            'title'          => '找回密码',
            'channel'        => $channel,
            'verify_label'   => VerifyCode::targetLabel($channel),
        ]);
    }

    public function logout()
    {
        Auth::logout();
        flash('success', '已退出登录');
        redirect_route('home/index');
    }
}
