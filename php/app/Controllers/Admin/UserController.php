<?php
/**
 * 后台用户管理
 */
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth;
use App\Db;
use App\OrderService;
use App\Wallet;

class UserController extends BaseController
{
    public function index()
    {
        $keyword = input_str('keyword');
        $role    = input_str('role');
        $status  = input_str('status');

        $where  = ['1 = 1'];
        $params = [];

        if ($keyword !== '') {
            $kw      = '%' . $keyword . '%';
            $where[] = '(username LIKE ? OR nickname LIKE ? OR phone LIKE ? OR id = ?)';
            array_push($params, $kw, $kw, $kw, (int) $keyword);
        }
        if ($role !== '' && in_array($role, ['user', 'companion', 'admin'], true)) {
            $where[]  = 'role = ?';
            $params[] = $role;
        }
        if ($status !== '' && in_array($status, ['0', '1'], true)) {
            $where[]  = 'status = ?';
            $params[] = (int) $status;
        }

        $whereSql = implode(' AND ', $where);
        $perPage  = 20;
        $page     = $this->page();
        $total    = (int) Db::value("SELECT COUNT(*) FROM users WHERE $whereSql", $params);
        $offset   = ($page - 1) * $perPage;
        $rows     = Db::all(
            "SELECT u.*, w.balance, w.frozen, p.audit_status
             FROM users u
             LEFT JOIN user_wallets w ON w.user_id = u.id
             LEFT JOIN companion_profiles p ON p.user_id = u.id
             WHERE $whereSql
             ORDER BY u.id DESC
             LIMIT $perPage OFFSET $offset",
            $params
        );

        $this->view('admin/user/index', [
            'title'   => '用户管理',
            'rows'    => $rows,
            'total'   => $total,
            'page'    => $page,
            'per_page' => $perPage,
            'keyword' => $keyword,
            'role'    => $role,
            'status'  => $status,
        ]);
    }

    /** 详情 */
    public function detail()
    {
        $id   = input_int('id', 0);
        $user = Db::one(
            // 陪玩资料（真实姓名 / 身份证 / 城市 / 标签 / 介绍）一并取出：
            // 后台需要在「基本信息」和下方「陪玩资料」区块里都能看到。
            // 注意 users 表里没有这几列，所以不会和 u.* 冲突。
            'SELECT u.*,
                    p.audit_status, p.online_status, p.score, p.order_count,
                    p.real_name, p.id_card, p.city, p.tags, p.intro
             FROM users u LEFT JOIN companion_profiles p ON p.user_id = u.id
             WHERE u.id = ? LIMIT 1',
            [$id]
        );
        if (!$user) {
            abort(404, '用户不存在');
        }

        $wallet = Wallet::get($id);
        $txns   = Db::all('SELECT * FROM wallet_transactions WHERE user_id = ? ORDER BY id DESC LIMIT 20', [$id]);
        $completedStatus = OrderService::STATUS_COMPLETED;
        $orderStat = Db::one(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN status = $completedStatus THEN 1 ELSE 0 END) AS completed,
                    COALESCE(SUM(CASE WHEN status = $completedStatus THEN amount ELSE 0 END), 0) AS amount
             FROM orders WHERE user_id = ? OR companion_id = ?",
            [$id, $id]
        );

        $this->view('admin/user/detail', [
            'title'     => '用户详情',
            'user'      => $user,
            'wallet'    => $wallet,
            'txns'      => $txns,
            'orderStat' => $orderStat ?: [],
        ]);
    }

    /** 启用/禁用 */
    public function toggleStatus()
    {
        require_post();
        verify_csrf();

        $id = input_int('id', 0);
        $this->run(function () use ($id) {
            $user = Db::one('SELECT * FROM users WHERE id = ? LIMIT 1', [$id]);
            if (!$user) {
                throw new \RuntimeException('用户不存在');
            }
            if ((int) $user['id'] === Auth::id()) {
                throw new \RuntimeException('不能禁用自己');
            }
            if ($user['role'] === 'admin') {
                throw new \RuntimeException('不能禁用管理员账号');
            }
            $to = (int) $user['status'] === 1 ? 0 : 1;
            Db::update('users', ['status' => $to, 'updated_at' => now()], 'id = ?', [$id]);
            Auth::logAdmin('用户状态变更', '用户ID ' . $id . ' 状态改为 ' . ($to === 1 ? '正常' : '禁用'));
            return ['status' => $to];
        }, '操作成功', admin_url('user/index'));
    }

    /** 余额调整（充值/扣减） */
    /** 设为管理员 */
    public function promoteAdmin()
    {
        require_post();
        verify_csrf();

        $id = input_int('id', 0);
        $this->run(function () use ($id) {
            $user = Db::one('SELECT id, username, role FROM users WHERE id = ? LIMIT 1', [$id]);
            if (!$user) {
                throw new \RuntimeException('用户不存在');
            }
            if ($user['role'] === 'admin') {
                throw new \RuntimeException('该账号已经是管理员');
            }

            Db::update('users', ['role' => 'admin', 'updated_at' => now()], 'id = ?', [$id]);
            Auth::logAdmin('设为管理员', '用户ID ' . $id . '（' . $user['username'] . '）');
            return true;
        }, '已设为管理员，可用「重置密码」给他设一个新密码', admin_url('user/detail', ['id' => $id]));
    }

    /** 取消管理员（不能取消自己，也不能把最后一个管理员取消掉） */
    public function demoteAdmin()
    {
        require_post();
        verify_csrf();

        $id = input_int('id', 0);
        $this->run(function () use ($id) {
            if ($id === Auth::id()) {
                throw new \RuntimeException('不能取消自己的管理员身份');
            }

            $user = Db::one('SELECT id, username, role FROM users WHERE id = ? LIMIT 1', [$id]);
            if (!$user) {
                throw new \RuntimeException('用户不存在');
            }
            if ($user['role'] !== 'admin') {
                throw new \RuntimeException('该账号不是管理员');
            }

            $admins = (int) Db::value("SELECT COUNT(*) FROM users WHERE role = 'admin'");
            if ($admins <= 1) {
                throw new \RuntimeException('系统至少要保留一个管理员');
            }

            // 若他同时是已通过审核的陪玩，降级后回到陪玩身份，避免丢掉接单入口
            $profile = Db::one('SELECT audit_status FROM companion_profiles WHERE user_id = ? LIMIT 1', [$id]);
            $newRole = ($profile && (int) $profile['audit_status'] === 1) ? 'companion' : 'user';

            Db::update('users', ['role' => $newRole, 'updated_at' => now()], 'id = ?', [$id]);
            Auth::logAdmin('取消管理员', '用户ID ' . $id . '（' . $user['username'] . '）→ ' . $newRole);
            return true;
        }, '已取消管理员身份', admin_url('user/detail', ['id' => $id]));
    }

    /** 重置登录密码（管理员操作，无需旧密码） */
    public function resetPassword()
    {
        require_post();
        verify_csrf();

        $id      = input_int('id', 0);
        $newPass = (string) input('new_password');

        $this->run(function () use ($id, $newPass) {
            $user = Db::one('SELECT id, username, role FROM users WHERE id = ? LIMIT 1', [$id]);
            if (!$user) {
                throw new \RuntimeException('用户不存在');
            }
            if (strlen($newPass) < 6) {
                throw new \RuntimeException('新密码至少 6 位');
            }

            Db::update('users', [
                'password'   => password_hash($newPass, PASSWORD_DEFAULT),
                'updated_at' => now(),
            ], 'id = ?', [$id]);

            Auth::logAdmin('重置用户密码', '用户ID ' . $id . '（' . $user['username'] . '，角色 ' . $user['role'] . '）');
            return true;
        }, '密码已重置，请把新密码告知该用户', admin_url('user/detail', ['id' => $id]));
    }

    public function adjustBalance()
    {
        require_post();
        verify_csrf();

        $id     = input_int('id', 0);
        $amount = safe_float(input_float('amount', 0));
        $remark = input_str('remark', '后台调整');
        $type   = input_str('type', 'add');

        $this->run(function () use ($id, $amount, $remark, $type) {
            \App\FinanceService::adjustBalance($id, $amount, $type, Auth::id(), $remark);
            Auth::logAdmin('余额调整', '用户ID ' . $id . ' ' . ($type === 'add' ? '加款' : '扣款') . ' ' . money($amount) . ' 元：' . $remark);
            return true;
        }, '余额已调整', admin_url('user/detail', ['id' => $id]));
    }
}
