<?php
/**
 * 陪玩中心：接单、技能管理、收益概览
 */
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\CompanionService;
use App\Db;
use App\OrderService;
use App\Upload;

class CompanionController extends BaseController
{
    /** 当前登录陪玩的资料（含权限校验） */
    private function requireCompanion(): array
    {
        Auth::requireLogin();
        $profile = Db::one('SELECT * FROM companion_profiles WHERE user_id = ? LIMIT 1', [Auth::id()]);
        if (!$profile) {
            flash('warning', '请先提交陪玩入驻申请');
            redirect_route('user/apply');
        }
        if ((int) $profile['audit_status'] !== 1) {
            flash('warning', '你的入驻申请正在审核中，通过后即可接单');
            redirect_route('user/apply');
        }
        return $profile;
    }

    /** 陪玩中心首页（还不是陪玩时展示说明页，不直接踢回申请页） */
    public function center()
    {
        Auth::requireLogin();
        $userId = Auth::id();

        $profile = Db::one('SELECT * FROM companion_profiles WHERE user_id = ? LIMIT 1', [$userId]);
        if (!$profile || (int) $profile['audit_status'] !== 1) {
            $this->view('companion/center_guest', [
                'title'   => '陪玩中心',
                'profile' => $profile,
                'intro'   => trim((string) \App\Setting::get('companion_intro', '')),
            ]);
            return;
        }

        $stat = CompanionService::incomeStat($userId);
        $todo = Db::all(
            'SELECT o.*, u.nickname AS user_nickname, u.avatar AS user_avatar
             FROM orders o LEFT JOIN users u ON u.id = o.user_id
             WHERE o.companion_id = ? AND o.status IN (?, ?)
             ORDER BY o.id ASC LIMIT 10',
            [$userId, OrderService::STATUS_PENDING_ACCEPT, OrderService::STATUS_IN_SERVICE]
        );
        $wallet = \App\Wallet::get($userId);

        $this->view('companion/center', [
            'title'   => '陪玩中心',
            'profile' => $profile,
            'stat'    => $stat,
            'todo'    => $todo,
            'wallet'  => $wallet,
        ]);
    }

    /** 接单大厅：公开的待接单任务（仅陪玩身份可见） */
    /** 陪玩切换自己的接单状态（在线 / 离线） */
    public function toggleOnline()
    {
        require_post();
        verify_csrf();
        Auth::requireLogin();

        $online = input_int('online', -1);
        $this->run(function () use ($online) {
            $profile = \App\Db::one('SELECT online_status FROM companion_profiles WHERE user_id = ? LIMIT 1', [Auth::id()]);
            if ($online !== 0 && $online !== 1) {
                // 没传具体值就翻转当前状态
                $online = ($profile && (int) $profile['online_status'] === 1) ? 0 : 1;
            }
            CompanionService::setOnline(Auth::id(), $online);
            return true;
        }, '接单状态已更新', url('user/center'));
    }

    public function hall()
    {
        $profile = $this->requireCompanion();

        $filter = [
            'game_id' => input_int('game_id', 0),
            'keyword' => input_str('keyword'),
        ];
        $perPage = 12;
        $result  = OrderService::hallList($this->page(), $perPage, $filter);
        $games   = Db::all('SELECT * FROM games WHERE status = 1 ORDER BY sort DESC, id ASC');

        // 我正在进行中的单，方便一眼看到手上的活
        $serving = Db::all(
            'SELECT o.*, u.nickname AS user_nickname
             FROM orders o LEFT JOIN users u ON u.id = o.user_id
             WHERE o.companion_id = ? AND o.status IN (?, ?)
             ORDER BY o.id DESC LIMIT 5',
            [Auth::id(), OrderService::STATUS_IN_SERVICE, OrderService::STATUS_PENDING_CONFIRM]
        );

        $this->view('companion/hall', [
            'title'    => '接单大厅',
            'profile'  => $profile,
            'filter'   => $filter,
            'games'    => $games,
            'result'   => $result,
            'per_page' => $perPage,
            'serving'  => $serving,
        ]);
    }

    /** 抢单 */
    public function grab()
    {
        require_post();
        verify_csrf();
        $this->requireCompanion();

        $id = input_int('id', 0);
        $this->run(function () use ($id) {
            OrderService::grab($id, Auth::id());
            return ['order_id' => $id];
        }, '接单成功，请尽快开始服务并提交完成');
    }

    /** 我的接单 */
    public function orders()
    {
        $this->requireCompanion();

        $status = input_str('status', 'all');
        $filter = ['companion_id' => Auth::id()];
        if ($status === 'refund') {
            $filter['status'] = [OrderService::STATUS_REFUNDING, OrderService::STATUS_REFUNDED];
        } elseif ($status === 'closed') {
            $filter['status'] = [OrderService::STATUS_CLOSED];
        } elseif ($status !== 'all' && ctype_digit($status)) {
            $filter['status'] = (int) $status;
        }

        $result = OrderService::paginate($filter, $this->page(), 10);

        $this->view('companion/orders', [
            'title'  => '我的接单',
            'result' => $result,
            'status' => $status,
        ]);
    }

    /** 接单 */
    public function accept()
    {
        require_post();
        verify_csrf();
        $this->requireCompanion();

        $id = input_int('id', 0);
        $this->run(function () use ($id) {
            OrderService::accept($id, Auth::id());
            return ['order_id' => $id];
        }, '接单成功，请及时提供服务');
    }

    /** 提交完成 */
    public function finish()
    {
        require_post();
        verify_csrf();
        $this->requireCompanion();

        $id = input_int('id', 0);
        $this->run(function () use ($id) {
            OrderService::finish($id, Auth::id());
            return ['order_id' => $id];
        }, '已提交完成，等待用户确认');
    }

    /** 切换接单状态 */
    public function online()
    {
        require_post();
        verify_csrf();
        $profile = $this->requireCompanion();

        $to = (int) $profile['online_status'] === 1 ? 0 : 1;
        Db::update('companion_profiles', ['online_status' => $to, 'updated_at' => now()], 'user_id = ?', [Auth::id()]);

        if (is_ajax()) {
            json_out(['code' => 0, 'msg' => $to === 1 ? '已开启接单' : '已暂停接单', 'data' => ['online_status' => $to]]);
        }
        flash('success', $to === 1 ? '已开启接单' : '已暂停接单');
        redirect_back('companion/center');
    }

    /** 技能管理 */
    public function skills()
    {
        $profile = $this->requireCompanion();

        $skills = CompanionService::skills(Auth::id());
        $games  = Db::all('SELECT * FROM games WHERE status = 1 ORDER BY sort DESC, id ASC');
        $editId = input_int('edit', 0);
        $edit   = null;
        if ($editId > 0) {
            $edit = Db::one('SELECT * FROM companion_skills WHERE id = ? AND companion_id = ? LIMIT 1', [$editId, Auth::id()]);
        }

        $this->view('companion/skills', [
            'title'   => '技能管理',
            'profile' => $profile,
            'skills'  => $skills,
            'games'   => $games,
            'edit'    => $edit,
        ]);
    }

    public function skillSave()
    {
        require_post();
        verify_csrf();
        $this->requireCompanion();

        $this->run(function () {
            $cover = Upload::image('cover', 'skill');
            CompanionService::saveSkill(Auth::id(), [
                'id'           => input_int('id', 0),
                'game_id'      => input_int('game_id', 0),
                'title'        => input_str('title'),
                'service_type' => input_str('service_type', 'hour'),
                'rank_level'   => input_str('rank_level'),
                'price'        => input_float('price', 0),
                'description'  => input_str('description'),
                'cover'        => (string) $cover,
            ]);
            return true;
        }, '保存成功', url('companion/skills'));
    }

    public function skillDelete()
    {
        require_post();
        verify_csrf();
        $this->requireCompanion();

        $id = input_int('id', 0);
        $this->run(function () use ($id) {
            CompanionService::deleteSkill(Auth::id(), $id);
            return true;
        }, '已删除', url('companion/skills'));
    }

    public function skillToggle()
    {
        require_post();
        verify_csrf();
        $this->requireCompanion();

        $id = input_int('id', 0);
        $this->run(function () use ($id) {
            $status = CompanionService::toggleSkill(Auth::id(), $id);
            return ['status' => $status];
        }, '已更新', url('companion/skills'));
    }
}
