<?php
/**
 * 后台账号设置：管理员修改自己的登录账号与密码
 */
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth;
use App\Db;

class AccountController extends BaseController
{
    public function index()
    {
        $me = Db::one('SELECT * FROM users WHERE id = ? LIMIT 1', [Auth::id()]);
        if (!$me) {
            abort(404, '账号不存在');
        }

        $this->view('admin/account/index', [
            'title' => '账号设置',
            'me'    => $me,
        ]);
    }

    public function save()
    {
        require_post();
        verify_csrf();

        $this->run(function () {
            $userId = Auth::id();
            $me     = Db::one('SELECT * FROM users WHERE id = ? LIMIT 1', [$userId]);
            if (!$me) {
                throw new \RuntimeException('账号不存在');
            }

            $username = input_str('username');
            $nickname = input_str('nickname');
            $email    = strtolower(input_str('email'));
            $phone    = input_str('phone');
            $oldPass  = (string) input('old_password');
            $newPass  = (string) input('new_password');
            $confirm  = (string) input('new_password_confirm');

            // 改登录账号或改密码，都必须先验证当前密码
            $needVerify = $username !== (string) $me['username'] || $newPass !== '';
            if ($needVerify) {
                if ($oldPass === '') {
                    throw new \RuntimeException('修改登录账号或密码前，请先填写当前密码');
                }
                if (!password_verify($oldPass, (string) $me['password'])) {
                    throw new \RuntimeException('当前密码不正确');
                }
            }

            if (!preg_match('/^[A-Za-z0-9_]{4,20}$/', $username)) {
                throw new \RuntimeException('登录账号需为 4~20 位字母、数字或下划线');
            }

            $data = ['updated_at' => now()];

            if ($username !== (string) $me['username']) {
                if (Db::one('SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1', [$username, $userId])) {
                    throw new \RuntimeException('该登录账号已被其他账号占用');
                }
                $data['username'] = $username;
            }

            if ($nickname === '') {
                $nickname = $username;
            }
            $data['nickname'] = mb_substr($nickname, 0, 50);

            if ($email !== '') {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new \RuntimeException('邮箱格式不正确');
                }
                if (Db::one('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1', [$email, $userId])) {
                    throw new \RuntimeException('该邮箱已被其他账号绑定');
                }
            }
            $data['email'] = $email;

            if ($phone !== '') {
                if (!is_phone($phone)) {
                    throw new \RuntimeException('手机号格式不正确');
                }
                if (Db::one('SELECT id FROM users WHERE phone = ? AND id <> ? LIMIT 1', [$phone, $userId])) {
                    throw new \RuntimeException('该手机号已被其他账号绑定');
                }
            }
            $data['phone'] = $phone;

            if ($newPass !== '') {
                if (strlen($newPass) < 6) {
                    throw new \RuntimeException('新密码至少 6 位');
                }
                if ($newPass !== $confirm) {
                    throw new \RuntimeException('两次输入的新密码不一致');
                }
                $data['password'] = password_hash($newPass, PASSWORD_DEFAULT);
            }

            Db::update('users', $data, 'id = ?', [$userId]);

            Auth::logAdmin('修改账号设置', '账号 ' . $username . ($newPass !== '' ? '（含修改密码）' : ''));

            return true;
        }, '账号设置已保存', admin_url('account/index'));
    }
}
