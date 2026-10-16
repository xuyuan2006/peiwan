<?php
/**
 * 后台游戏分类管理
 */
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth;
use App\Db;

class GameController extends BaseController
{
    public function index()
    {
        $rows = Db::all(
            'SELECT g.*,
                    (SELECT COUNT(*) FROM companion_skills s WHERE s.game_id = g.id AND s.status = 1) AS skill_count
             FROM games g ORDER BY g.sort DESC, g.id ASC'
        );

        $editId = input_int('edit', 0);
        $edit   = $editId > 0 ? Db::one('SELECT * FROM games WHERE id = ? LIMIT 1', [$editId]) : null;

        $this->view('admin/game/index', [
            'title' => '游戏管理',
            'rows'  => $rows,
            'edit'  => $edit,
        ]);
    }

    public function save()
    {
        require_post();
        verify_csrf();

        $this->run(function () {
            $id    = input_int('id', 0);
            $name  = input_str('name');
            $sort  = input_int('sort', 0);
            $hot   = input_int('is_hot', 0) === 1 ? 1 : 0;
            $icon  = input_str('icon');

            if ($name === '') {
                throw new \RuntimeException('请填写游戏名称');
            }
            if (Db::one('SELECT id FROM games WHERE name = ? AND id <> ? LIMIT 1', [$name, $id])) {
                throw new \RuntimeException('该游戏已存在');
            }

            $data = [
                'name'       => mb_substr($name, 0, 50),
                'icon'       => mb_substr($icon, 0, 250),
                'sort'       => $sort,
                'is_hot'     => $hot,
                'updated_at' => now(),
            ];

            if ($id > 0) {
                Db::update('games', $data, 'id = ?', [$id]);
                Auth::logAdmin('编辑游戏', 'ID ' . $id . ' ' . $name);
            } else {
                $data['status']     = 1;
                $data['created_at'] = now();
                $id = Db::insert('games', $data);
                Auth::logAdmin('新增游戏', $name);
            }
            return ['id' => $id];
        }, '保存成功', admin_url('game/index'));
    }

    /** 启用/停用 */
    public function toggle()
    {
        require_post();
        verify_csrf();

        $id = input_int('id', 0);
        $this->run(function () use ($id) {
            $game = Db::one('SELECT * FROM games WHERE id = ? LIMIT 1', [$id]);
            if (!$game) {
                throw new \RuntimeException('游戏不存在');
            }
            $to = (int) $game['status'] === 1 ? 0 : 1;
            Db::update('games', ['status' => $to, 'updated_at' => now()], 'id = ?', [$id]);
            Auth::logAdmin('游戏状态变更', 'ID ' . $id . ' 状态改为 ' . $to);
            return ['status' => $to];
        }, '操作成功', admin_url('game/index'));
    }
}
