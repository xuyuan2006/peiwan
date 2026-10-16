<?php
/**
 * 后台登录 / 退出
 */
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth;
use App\Controllers\BaseController as FrontBase;

class AuthController extends FrontBase
{
    public function login()
    {
        if (Auth::isAdmin()) {
            redirect(admin_url('dashboard/index'));
        }

        if (is_post()) {
            verify_csrf();
            $account  = input_str('account');
            $password = (string) input('password');

            if ($account === '' || $password === '') {
                flash('error', '请填写账号和密码');
                redirect(admin_url('auth/login'));
            }
            if (!Auth::attempt($account, $password)) {
                flash('error', '账号或密码错误');
                redirect(admin_url('auth/login'));
            }
            if (!Auth::isAdmin()) {
                Auth::logout();
                flash('error', '该账号不是管理员');
                redirect(admin_url('auth/login'));
            }

            flash('success', '登录成功');
            redirect(admin_url('dashboard/index'));
        }

        $this->view('admin/login', ['title' => '后台登录'], '');
    }

    public function logout()
    {
        Auth::logout();
        flash('success', '已退出后台');
        redirect(admin_url('auth/login'));
    }
}
