<?php
/**
 * 后台控制器基类：进入即校验身份
 *
 * 两类登录态：
 *   1. 后台管理员（users.role='admin'）—— 可进所有后台页面
 *   2. 工单部门账号（kf_staff 表）—— **只能**进 $allowKfStaff() 返回 true 的控制器
 *      其它页面一律「权限不足」，避免客服乱点其它模块。
 */
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth;
use App\KfStaff;
use App\Controllers\BaseController as FrontBase;

abstract class BaseController extends FrontBase
{
    public function __construct()
    {
        if (KfStaff::check()) {
            if (!$this->allowKfStaff()) {
                abort(403, '权限不足：工单部门账号只能访问工单模块');
            }
            return;
        }

        Auth::requireAdmin();
    }

    /** 子类覆写为 true，表示允许工单部门账号进入 */
    protected function allowKfStaff(): bool
    {
        return false;
    }

    protected function view(string $tpl, array $data = [], string $layout = 'admin'): void
    {
        $data['__user'] = user();
        view($tpl, $data, $layout);
    }
}
