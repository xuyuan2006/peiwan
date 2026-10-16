<?php
/**
 * 后台控制器基类：进入即校验管理员身份
 */
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth;
use App\Controllers\BaseController as FrontBase;

abstract class BaseController extends FrontBase
{
    public function __construct()
    {
        Auth::requireAdmin();
    }

    protected function view(string $tpl, array $data = [], string $layout = 'admin'): void
    {
        $data['__user'] = user();
        view($tpl, $data, $layout);
    }
}
