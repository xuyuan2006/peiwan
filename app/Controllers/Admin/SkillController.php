<?php
/**
 * 后台技能管理：新增/编辑/上下架/删除陪玩的服务项目（可为任意陪玩上架并定价）
 */
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth;
use App\CompanionService;
use App\Db;
use App\Upload;

class SkillController extends BaseController
{
    public function index()
    {
        $keyword = input_str('keyword');
        $gameId  = input_int('game_id', 0);
        $status  = input_str('status', 'all');

        $where  = ['1 = 1'];
        $params = [];

        if ($keyword !== '') {
            $kw      = '%' . like_escape($keyword) . '%';
            $where[] = '(s.title LIKE ? OR u.nickname LIKE ? OR s.rank_level LIKE ? OR s.id = ?)';
            array_push($params, $kw, $kw, $kw, (int) $keyword);
        }
        if ($gameId > 0) {
            $where[]  = 's.game_id = ?';
            $params[] = $gameId;
        }
        if ($status !== 'all' && in_array($status, ['0', '1'], true)) {
            $where[]  = 's.status = ?';
            $params[] = (int) $status;
        }

        $whereSql = implode(' AND ', $where);
        $perPage  = 20;
        $page     = $this->page();
        $total    = (int) Db::value(
            "SELECT COUNT(*) FROM companion_skills s
             LEFT JOIN users u ON u.id = s.companion_id
             WHERE $whereSql",
            $params
        );
        $offset = ($page - 1) * $perPage;
        $rows   = Db::all(
            "SELECT s.*, g.`name` AS game_name, u.nickname, u.status AS user_status,
                    (SELECT COUNT(*) FROM orders o WHERE o.skill_id = s.id) AS order_count
             FROM companion_skills s
             LEFT JOIN games g ON g.id = s.game_id
             LEFT JOIN users u ON u.id = s.companion_id
             WHERE $whereSql
             ORDER BY s.id DESC
             LIMIT $perPage OFFSET $offset",
            $params
        );

        $games = Db::all('SELECT id, name FROM games ORDER BY sort DESC, id ASC');
        $sum   = Db::one(
            'SELECT COUNT(*) AS total,
                    SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) AS online,
                    SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) AS offline
             FROM companion_skills'
        );

        // 已通过审核的陪玩（供「新增技能」选择）
        $companions = Db::all(
            "SELECT u.id, u.nickname
             FROM users u
             JOIN companion_profiles p ON p.user_id = u.id
             WHERE p.audit_status = 1 AND u.status = 1
             ORDER BY u.id DESC
             LIMIT 500"
        );

        $editId = input_int('edit', 0);
        $edit   = $editId > 0 ? Db::one('SELECT * FROM companion_skills WHERE id = ? LIMIT 1', [$editId]) : null;

        $this->view('admin/skill/index', [
            'title'      => '技能管理',
            'rows'       => $rows,
            'total'      => $total,
            'page'       => $page,
            'per_page'   => $perPage,
            'keyword'    => $keyword,
            'game_id'    => $gameId,
            'status'     => $status,
            'games'      => $games,
            'sum'        => $sum ?: [],
            'companions' => $companions,
            'edit'       => $edit,
        ]);
    }

    /** 新增 / 编辑技能（后台可代任意陪玩上架并定价） */
    public function save()
    {
        require_post();
        verify_csrf();

        $this->run(function () {
            $skillId     = input_int('id', 0);
            $companionId = input_int('companion_id', 0);

            // 编辑时不允许换陪玩：以技能原本归属为准
            if ($skillId > 0) {
                $skill = Db::one('SELECT id, companion_id FROM companion_skills WHERE id = ? LIMIT 1', [$skillId]);
                if (!$skill) {
                    throw new \RuntimeException('技能不存在');
                }
                $companionId = (int) $skill['companion_id'];
            }

            if ($companionId <= 0) {
                throw new \RuntimeException('请选择要上架服务的陪玩');
            }
            $user = Db::one('SELECT id, status, role, nickname FROM users WHERE id = ? LIMIT 1', [$companionId]);
            if (!$user) {
                throw new \RuntimeException('陪玩不存在');
            }
            if ((int) $user['status'] !== 1) {
                throw new \RuntimeException('该陪玩账号已被禁用');
            }
            $profile = Db::one('SELECT audit_status FROM companion_profiles WHERE user_id = ? LIMIT 1', [$companionId]);
            if (!$profile) {
                throw new \RuntimeException('该用户还不是陪玩，请先到「用户详情」里设为陪玩');
            }
            if ((int) $profile['audit_status'] !== 1) {
                throw new \RuntimeException('该陪玩的入驻资料尚未通过审核');
            }

            $cover = Upload::image('cover', 'skill');

            CompanionService::saveSkill($companionId, [
                'id'           => $skillId,
                'game_id'      => input_int('game_id', 0),
                'title'        => input_str('title'),
                'service_type' => input_str('service_type', 'hour'),
                'rank_level'   => input_str('rank_level'),
                'price'        => input_float('price', 0),
                'description'  => input_str('description'),
                'cover'        => (string) $cover,
            ]);

            Auth::logAdmin(
                $skillId > 0 ? '编辑技能' : '新增技能',
                '陪玩ID ' . $companionId . '（' . $user['nickname'] . '）：' . input_str('title') . '，价格 ' . money(input_float('price', 0))
            );
            return true;
        }, '技能已保存', admin_url('skill/index'));
    }

    /** 上架 / 下架 */
    public function toggle()
    {
        require_post();
        verify_csrf();

        $id = input_int('id', 0);
        $this->run(function () use ($id) {
            $skill = Db::one('SELECT * FROM companion_skills WHERE id = ? LIMIT 1', [$id]);
            if (!$skill) {
                throw new \RuntimeException('技能不存在');
            }
            $to = (int) $skill['status'] === 1 ? 0 : 1;
            Db::update('companion_skills', ['status' => $to, 'updated_at' => now()], 'id = ?', [$id]);
            Auth::logAdmin('技能状态变更', '技能ID ' . $id . '（' . $skill['title'] . '）改为 ' . ($to === 1 ? '上架' : '下架'));
            return ['status' => $to];
        }, '操作成功', admin_url('skill/index'));
    }

    /** 删除（有订单引用时只允许下架，保留历史可追溯） */
    public function delete()
    {
        require_post();
        verify_csrf();

        $id = input_int('id', 0);
        $this->run(function () use ($id) {
            $skill = Db::one('SELECT * FROM companion_skills WHERE id = ? LIMIT 1', [$id]);
            if (!$skill) {
                throw new \RuntimeException('技能不存在');
            }
            $used = (int) Db::value('SELECT COUNT(*) FROM orders WHERE skill_id = ?', [$id]);
            if ($used > 0) {
                throw new \RuntimeException('该技能已被 ' . $used . ' 笔订单引用，为保留历史记录请改用「下架」');
            }
            Db::query('DELETE FROM companion_skills WHERE id = ?', [$id]);
            Auth::logAdmin('删除技能', '技能ID ' . $id . '（' . $skill['title'] . '）');
            return true;
        }, '技能已删除', admin_url('skill/index'));
    }
}
