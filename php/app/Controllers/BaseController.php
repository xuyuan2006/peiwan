<?php
/**
 * 前台控制器基类：统一视图、AJAX 返回、异常处理。
 */
declare(strict_types=1);

namespace App\Controllers;

abstract class BaseController
{
    protected function view(string $tpl, array $data = [], string $layout = 'layout'): void
    {
        $data['__user'] = user();
        view($tpl, $data, $layout);
    }

    /**
     * 执行写操作：统一异常处理 —— AJAX 返回 JSON，普通表单则 flash + 跳转。
     *
     * @param callable    $fn
     * @param string      $successMsg
     * @param string|null $redirect 成功后跳转地址，null 表示返回上一页
     * @return mixed
     */
    protected function run(callable $fn, string $successMsg = '操作成功', ?string $redirect = null)
    {
        try {
            $result = $fn();
            if (is_ajax()) {
                json_out(['code' => 0, 'msg' => $successMsg, 'data' => $result]);
            }
            flash('success', $successMsg);
            if ($redirect !== null) {
                redirect($redirect);
            }
            redirect_back();
        } catch (\Throwable $e) {
            if (is_ajax()) {
                json_out(['code' => 1, 'msg' => $e->getMessage()], 400);
            }
            flash('error', $e->getMessage());
            keep_old($_POST);
            redirect_back();
        }
    }

    /** 读取分页页码 */
    protected function page(): int
    {
        return max(1, input_int('page', 1));
    }
}
