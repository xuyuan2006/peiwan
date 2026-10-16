<?php
/**
 * 后台陪玩管理 / 入驻审核
 */
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth;
use App\CompanionService;
use App\Db;
use App\OrderService;

class CompanionController extends BaseController
{
    public function index()
    {
        $audit   = input_str('audit', 'all');
        $keyword = input_str('keyword');

        $where  = ['1 = 1'];
        $params = [];

        if ($audit !== 'all' && in_array($audit, ['0', '1', '2'], true)) {
            $where[]  = 'p.audit_status = ?';
            $params[] = (int) $audit;
        }
        if ($keyword !== '') {
            $kw      = '%' . $keyword . '%';
            $where[] = '(u.nickname LIKE ? OR p.real_name LIKE ? OR p.phone LIKE ? OR u.id = ?)';
            array_push($params, $kw, $kw, $kw, (int) $keyword);
        }

        $whereSql = implode(' AND ', $where);
        $perPage  = 20;
        $page     = $this->page();
        $total    = (int) Db::value("SELECT COUNT(*) FROM companion_profiles p JOIN users u ON u.id = p.user_id WHERE $whereSql", $params);
        $offset   = ($page - 1) * $perPage;
        $rows     = Db::all(
            "SELECT p.*, u.nickname, u.avatar, u.status AS user_status, u.credit_score, u.created_at AS reg_time,
                    (SELECT COUNT(*) FROM companion_skills s WHERE s.companion_id = p.user_id AND s.status = 1) AS skill_count
             FROM companion_profiles p
             JOIN users u ON u.id = p.user_id
             WHERE $whereSql
             ORDER BY p.audit_status ASC, p.id DESC
             LIMIT $perPage OFFSET $offset",
            $params
        );

        $counts = Db::one(
            'SELECT SUM(CASE WHEN audit_status = 0 THEN 1 ELSE 0 END) AS pending,
                    SUM(CASE WHEN audit_status = 1 THEN 1 ELSE 0 END) AS approved,
                    SUM(CASE WHEN audit_status = 2 THEN 1 ELSE 0 END) AS rejected
             FROM companion_profiles'
        );

        $this->view('admin/companion/index', [
            'title'    => '陪玩管理',
            'rows'     => $rows,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'audit'    => $audit,
            'keyword'  => $keyword,
            'counts'   => $counts ?: [],
        ]);
    }

    /** 审核通过 / 拒绝 */
    public function audit()
    {
        require_post();
        verify_csrf();

        $id     = input_int('id', 0);
        $status = input_int('status', 1);
        $remark = input_str('remark');

        $this->run(function () use ($id, $status, $remark) {
            if (!in_array($status, [1, 2], true)) {
                throw new \RuntimeException('审核结果不正确');
            }
            CompanionService::audit($id, $status, $remark);
            Auth::logAdmin('陪玩审核', '用户ID ' . $id . ' 审核结果：' . ($status === 1 ? '通过' : '拒绝') . ' ' . $remark);
            return true;
        }, '审核完成', admin_url('companion/index'));
    }

    /** 把普通用户直接设为陪玩（免审核开通，方便后台代运营） */
    public function promote()
    {
        require_post();
        verify_csrf();

        $id = input_int('id', 0);
        $this->run(function () use ($id) {
            $user = Db::one('SELECT * FROM users WHERE id = ? LIMIT 1', [$id]);
            if (!$user) {
                throw new \RuntimeException('用户不存在');
            }
            if ((int) $user['status'] !== 1) {
                throw new \RuntimeException('该账号已被禁用，请先启用再设为陪玩');
            }
            if ($user['role'] === 'admin') {
                throw new \RuntimeException('管理员账号无需设为陪玩');
            }

            $realName = input_str('real_name');
            if ($realName === '') {
                $realName = (string) $user['nickname'];
            }

            $profile = Db::one('SELECT * FROM companion_profiles WHERE user_id = ? LIMIT 1', [$id]);
            if ($profile) {
                Db::update('companion_profiles', [
                    'real_name'     => mb_substr($realName, 0, 50),
                    'phone'         => $user['phone'] !== '' ? (string) $user['phone'] : (string) $profile['phone'],
                    'audit_status'  => 1,
                    'audit_remark'  => '后台直接开通',
                    'audit_at'      => now(),
                    'online_status' => 1,
                    'updated_at'    => now(),
                ], 'user_id = ?', [$id]);
            } else {
                Db::insert('companion_profiles', [
                    'user_id'       => $id,
                    'real_name'     => mb_substr($realName, 0, 50),
                    'phone'         => (string) $user['phone'],
                    'gender'        => (int) $user['gender'],
                    'intro'         => '由平台后台直接开通',
                    'audit_status'  => 1,
                    'audit_remark'  => '后台直接开通',
                    'audit_at'      => now(),
                    'online_status' => 1,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
            }

            CompanionService::markRole($id);
            Auth::logAdmin('设为陪玩', '用户ID ' . $id . '（' . $user['nickname'] . '）');
            return true;
        }, '已设为陪玩，可到「技能管理」为其上架服务项目', admin_url('user/detail', ['id' => $id]));
    }

    /** 设置推荐 / 取消推荐 */
    public function recommend()
    {
        require_post();
        verify_csrf();

        $id = input_int('id', 0);
        $this->run(function () use ($id) {
            $profile = Db::one('SELECT * FROM companion_profiles WHERE user_id = ? LIMIT 1', [$id]);
            if (!$profile) {
                throw new \RuntimeException('陪玩不存在');
            }
            $to = (int) $profile['is_recommend'] === 1 ? 0 : 1;
            Db::update('companion_profiles', ['is_recommend' => $to, 'updated_at' => now()], 'user_id = ?', [$id]);
            Auth::logAdmin('陪玩推荐', '用户ID ' . $id . ' 推荐状态改为 ' . $to);
            return ['is_recommend' => $to];
        }, '操作成功', admin_url('companion/index'));
    }

    /** 取消陪玩资格 */
    public function demote()
    {
        require_post();
        verify_csrf();

        $id = input_int('id', 0);
        $this->run(function () use ($id) {
            $running = (int) Db::value(
                'SELECT COUNT(*) FROM orders WHERE companion_id = ? AND status IN (?, ?, ?)',
                [$id, OrderService::STATUS_PENDING_ACCEPT, OrderService::STATUS_IN_SERVICE, OrderService::STATUS_PENDING_CONFIRM]
            );
            if ($running > 0) {
                throw new \RuntimeException('该陪玩还有 ' . $running . ' 笔进行中的订单，请先处理（后台强制完成或关闭退款）后再取消资格');
            }

            Db::tx(function () use ($id) {
                Db::update('companion_profiles', [
                    'audit_status' => 2,
                    'audit_remark' => '平台取消陪玩资格',
                    'audit_at'     => now(),
                    'online_status' => 0,
                    'updated_at'   => now(),
                ], 'user_id = ?', [$id]);
                Db::query('UPDATE companion_skills SET status = 0, updated_at = ? WHERE companion_id = ?', [now(), $id]);
                CompanionService::demoteRole($id);
            });
            Auth::logAdmin('取消陪玩资格', '用户ID ' . $id);
            return true;
        }, '已取消陪玩资格', admin_url('companion/index'));
    }
}
