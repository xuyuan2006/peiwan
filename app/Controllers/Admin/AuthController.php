<?php
/**
 * 后台登录 / 退出
 *
 * 同一个登录页接受两种账号：
 *   - 后台管理员（users.role='admin'）→ 进数据概览
 *   - 工单部门账号（kf_staff）        → 只能进「工单处理」
 */
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth;
use App\KfStaff;
use App\Controllers\BaseController as FrontBase;

class AuthController extends FrontBase
{
    public function login()
    {
        if (Auth::isAdmin()) {
            redirect(admin_url('dashboard/index'));
        }
        if (KfStaff::check()) {
            redirect(admin_url('kf_desk/index'));
        }

        if (is_post()) {
            verify_csrf();
            $account  = input_str('account');
            $password = (string) input('password');

            if ($account === '' || $password === '') {
                flash('error', '请填写账号和密码');
                redirect(admin_url('auth/login'));
            }

            // 1) 先按后台管理员登录
            if (Auth::attempt($account, $password)) {
                if (!Auth::isAdmin()) {
                    Auth::logout();
                    flash('error', '该账号不是管理员');
                    redirect(admin_url('auth/login'));
                }
                flash('success', '登录成功');
                redirect(admin_url('dashboard/index'));
            }

            // 2) 再按工单部门账号登录
            $staff = KfStaff::attempt($account, $password);
            if ($staff) {
                KfStaff::login($staff);
                flash('success', '登录成功');
                redirect(admin_url('kf_desk/index'));
            }

            flash('error', '账号或密码错误');
            redirect(admin_url('auth/login'));
        }

        $this->view('admin/login', ['title' => '后台登录'], '');
    }

    public function logout()
    {
        Auth::logout();
        KfStaff::logout();
        flash('success', '已退出后台');
        redirect(admin_url('auth/login'));
    }
}
