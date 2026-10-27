<?php
/**
 * 工单部门 · 账号管理
 *
 * 权限：
 *   - 超级管理员（原后台管理员）：建主管/组长/客服，改任何人，指定组长
 *   - 主管：建组长/客服，改组长和客服，指定客服归属哪个组长
 *   - 组长 / 客服：看不到本页面（403）
 */
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\KfStaff;

class KfStaffController extends BaseController
{
    /** 经理可以管组长和客服；组长可以管本组客服（封禁/重置密码，不能建号） */
    protected function allowKfStaff(): bool
    {
        return in_array(KfStaff::actor()['role'], ['manager', 'leader'], true);
    }

    public function index()
    {
        $actor    = $this->actor();
        $isLeader = $actor['type'] === 'staff' && (string) $actor['role'] === 'leader';

        $filter = [
            'role'      => input_str('role'),
            'status'    => input_str('status'),
            'leader_id' => input_int('leader_id', 0),
            'keyword'   => input_str('keyword'),
        ];

        // 组长只能看自己组里的客服，不能通过改 URL 越权
        if ($isLeader) {
            $filter['role']      = 'agent';
            $filter['leader_id'] = (int) $actor['id'];
        }

        $this->view('admin/kfdesk/staff', [
            'title'     => '工单部门 · 账号',
            'actor'     => $actor,
            'filter'    => $filter,
            'result'    => KfStaff::paginate($filter, max(1, input_int('page', 1)), 30),
            'roles'     => KfStaff::roles(),
            'leaders'   => KfStaff::leaders(),
            'canCreate' => (bool) KfStaff::creatableRoles($actor),
            'leaderOnly' => $isLeader,
        ]);
    }

    public function form()
    {
        $actor = $this->actor();
        if (!KfStaff::creatableRoles($actor)) {
            abort(403, '权限不足：你不能创建账号');
        }

        $id      = input_int('id', 0);
        $editing = $id > 0 ? KfStaff::find($id) : null;
        if ($id > 0 && !$editing) {
            abort(404, '账号不存在');
        }
        if ($editing && !KfStaff::canManage($actor, $editing)) {
            abort(403, '权限不足：你不能编辑该账号');
        }

        $roles = KfStaff::creatableRoles($actor);
        if ($editing) {
            $roles[] = (string) $editing['role'];
            $roles   = array_values(array_unique($roles));
        }

        $this->view('admin/kfdesk/staff_form', [
            'title'   => $editing ? '编辑工单账号' : '新建工单账号',
            'actor'   => $actor,
            'editing' => $editing,
            'roles'   => $roles,
            'roleMap' => KfStaff::roles(),
            'leaders' => KfStaff::leaders(),
            'managers' => KfStaff::managers(),
        ]);
    }

    public function save()
    {
        require_post();
        verify_csrf();
        $actor = $this->actor();
        $id    = input_int('id', 0);

        $data = [
            'account'   => input_str('account'),
            'name'      => input_str('name'),
            'email'     => input_str('email'),
            'phone'     => input_str('phone'),
            'role'      => input_str('role', 'agent'),
            'leader_id' => input_int('leader_id', 0),
            'manager_id' => input_int('manager_id', 0),
            'password'  => (string) input('password'),
        ];

        try {
            if ($id > 0) {
                KfStaff::update($actor, $id, $data);
                flash('success', '账号已更新');
            } else {
                KfStaff::create($actor, $data);
                flash('success', '账号已创建，请把账号和密码告诉本人');
            }
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
            redirect(admin_url('kf_staff/form', ['id' => $id]));
        }
        redirect(admin_url('kf_staff/index'));
    }

    public function toggle()
    {
        require_post();
        verify_csrf();
        $actor = $this->actor();

        try {
            $new = KfStaff::toggle($actor, input_int('id', 0));
            flash('success', $new === 1 ? '账号已启用' : '账号已停用');
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect(admin_url('kf_staff/index'));
    }

    public function reset()
    {
        require_post();
        verify_csrf();
        $actor = $this->actor();

        try {
            $GLOBALS['kf_reset_password'] = '';
            KfStaff::resetPassword($actor, input_int('id', 0), input_str('password'));
            $pass = (string) ($GLOBALS['kf_reset_password'] ?? '');
            flash('success', '密码已重置为：' . $pass . '（请立刻告诉本人）');
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect(admin_url('kf_staff/index'));
    }

    /** @return array{type:string,id:int,role:string,name:string,leader_id:int} */
    private function actor(): array
    {
        KfStaff::ensureSchema();
        $missing = KfStaff::missingSchema();
        if ($missing) {
            abort(503, '工单部门还没初始化：缺少 ' . implode('、', $missing)
                . '。请先在数据库里执行 sql/kf_desk.sql 的全部内容，或给数据库账号建表权限后刷新本页。');
        }

        $actor = KfStaff::actor();
        if ($actor['type'] === 'none') {
            abort(403, '请先登录');
        }
        return $actor;
    }
}
