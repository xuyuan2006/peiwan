<?php
/**
 * 陪玩接口：接单大厅、抢单、我的接单、上下线、陪玩中心、技能管理、收益统计。
 *
 * 陪玩身份判定统一走 TokenAuth::requireCompanion()：
 * 「已登录 + 存在 companion_profiles + audit_status = 1」。
 */
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Api\ApiException;
use App\Api\OrderQuery;
use App\Api\Presenter;
use App\Api\Request;
use App\CompanionService;
use App\Db;
use App\OrderService;
use App\Wallet;

class CompanionController extends BaseController
{
    // ---------------------------------------------------------------- 接单大厅

    /** 接单大厅：已付款、等待陪玩抢单的公开订单 */
    public function hall(): void
    {
        $uid = (int) $this->user()['id'];

        $result = OrderService::hallList($this->page(), $this->perPage(12, 30), [
            'game_id' => Request::int('game_id', 0),
            'keyword' => Request::str('keyword'),
        ]);

        $this->ok(array_merge(
            Presenter::page($result, function ($row) use ($uid) {
                return Presenter::order($row, $uid);
            }),
            [
                'games' => array_map([Presenter::class, 'game'], Db::all(
                    'SELECT * FROM games WHERE status = 1 ORDER BY sort DESC, id ASC'
                )),
            ]
        ));
    }

    /** 抢单：公开单归属给自己并直接进入服务中 */
    public function grab(): void
    {
        Request::requirePost();
        $uid = (int) $this->companionUser()['id'];
        $id  = Request::int('id', 0);

        if ($id <= 0) {
            throw ApiException::invalid('缺少订单 ID');
        }

        // 行锁保证同一单不会被两个人抢到，失败会抛出可读原因
        $order = OrderService::grab($id, $uid);

        $this->ok(['order' => Presenter::order($order, $uid)], '抢单成功，请尽快联系用户开始服务');
    }

    // ---------------------------------------------------------------- 我的接单

    /** 我接到的订单（接单方视角） */
    public function orders(): void
    {
        $uid = (int) $this->companionUser()['id'];

        $result = OrderService::paginate([
            'companion_id' => $uid,
            'status'       => OrderQuery::statusFilter(Request::str('status', 'all')),
        ], $this->page(), $this->perPage(10, 50));

        $this->ok(array_merge(
            Presenter::page($result, function ($row) use ($uid) {
                return Presenter::order($row, $uid);
            }),
            ['counts' => OrderQuery::counts('companion_id', $uid)]
        ));
    }

    /** 接单：订单已被指派给我，确认开始服务 */
    public function accept(): void
    {
        Request::requirePost();
        $uid = (int) $this->companionUser()['id'];
        $id  = Request::int('id', 0);

        if ($id <= 0) {
            throw ApiException::invalid('缺少订单 ID');
        }

        $order = OrderService::accept($id, $uid);

        $this->ok(['order' => Presenter::order($order, $uid)], '已接单，服务开始');
    }

    /** 提交完成，等待用户确认（超时后系统自动确认） */
    public function finish(): void
    {
        Request::requirePost();
        $uid = (int) $this->companionUser()['id'];
        $id  = Request::int('id', 0);

        if ($id <= 0) {
            throw ApiException::invalid('缺少订单 ID');
        }

        $order = OrderService::finish($id, $uid);

        $this->ok(['order' => Presenter::order($order, $uid)], '已提交完成，等待用户确认');
    }

    // ---------------------------------------------------------------- 陪玩中心

    /** 陪玩中心：资料 + 收益 + 待办 + 技能 */
    public function center(): void
    {
        $user = $this->user();
        $uid  = (int) $user['id'];

        $profile = Db::one('SELECT * FROM companion_profiles WHERE user_id = ? LIMIT 1', [$uid]);
        $audit   = $profile ? (int) $profile['audit_status'] : -1;

        $serving = (int) Db::value(
            'SELECT COUNT(*) FROM orders WHERE companion_id = ? AND status IN (?, ?)',
            [$uid, OrderService::STATUS_IN_SERVICE, OrderService::STATUS_PENDING_CONFIRM]
        );
        $pending = (int) Db::value(
            'SELECT COUNT(*) FROM orders WHERE companion_id = ? AND status = ?',
            [$uid, OrderService::STATUS_PENDING_ACCEPT]
        );
        $hallTotal = (int) Db::value(
            'SELECT COUNT(*) FROM orders WHERE is_public = 1 AND companion_id = 0 AND status = ?',
            [OrderService::STATUS_PENDING_ACCEPT]
        );

        $this->ok([
            'profile'    => Presenter::myCompanion($profile),
            'audit'      => [
                'applied'      => $profile !== null,
                'audit_status' => $audit,
                'audit_text'   => $profile ? audit_status_text($audit) : '未申请',
                'audit_remark' => (string) ($profile['audit_remark'] ?? ''),
            ],
            'online'     => $profile ? ((int) $profile['online_status'] === 1) : false,
            'wallet'     => Presenter::wallet(Wallet::get($uid)),
            'income'     => self::income($profile ? $audit === 1 : false, $uid),
            'counts'     => [
                'pending_accept' => $pending,
                'serving'        => $serving,
                'hall_total'     => $hallTotal,
            ],
            'skills'     => array_map([Presenter::class, 'skill'], CompanionService::skills($uid, true)),
        ]);
    }

    /** 我的陪玩资料（编辑用，含实名与证件号，仅本人可见） */
    public function profile(): void
    {
        $uid     = (int) $this->user()['id'];
        $profile = Db::one('SELECT * FROM companion_profiles WHERE user_id = ? LIMIT 1', [$uid]);

        $this->ok([
            'applied' => $profile !== null,
            'audit_status' => $profile ? (int) $profile['audit_status'] : -1,
            'audit_text'   => $profile ? audit_status_text((int) $profile['audit_status']) : '未申请',
            'audit_remark' => (string) ($profile['audit_remark'] ?? ''),
            'online'  => $profile ? ((int) $profile['online_status'] === 1) : false,
            'profile' => Presenter::myCompanion($profile),
        ]);
    }

    /** 提交 / 修改入驻申请 */
    public function apply(): void
    {
        Request::requirePost();
        $uid = (int) $this->user()['id'];

        CompanionService::apply($uid, [
            'real_name' => Request::str('real_name'),
            'id_card'   => Request::str('id_card'),
            'phone'     => Request::str('phone'),
            'gender'    => Request::int('gender', 0),
            'city'      => Request::str('city'),
            'intro'     => Request::str('intro'),
            'tags'      => Request::str('tags'),
        ]);

        $profile = Db::one('SELECT * FROM companion_profiles WHERE user_id = ? LIMIT 1', [$uid]);
        $audit   = $profile ? (int) $profile['audit_status'] : 0;

        $this->ok([
            'audit_status' => $audit,
            'audit_text'   => audit_status_text($audit),
            'profile'      => Presenter::myCompanion($profile),
        ], $audit === 1 ? '资料已更新' : '申请已提交，请等待平台审核');
    }

    /** 一键上下线；不传 online 参数时按「当前状态取反」处理（与网页端一致） */
    public function toggleOnline(): void
    {
        Request::requirePost();
        $uid = (int) $this->companionUser()['id'];

        $row     = Db::one('SELECT online_status FROM companion_profiles WHERE user_id = ? LIMIT 1', [$uid]);
        $current = (int) ($row['online_status'] ?? 0) === 1;

        // 显式传了布尔值就按它设置；没传、或传了 null / 空串，则按当前状态取反。
        // （不能只用 has() 判断：客户端发 {"online": null} 时 has() 为 true，
        //   会被 bool() 解析成 false，把「取反」静默变成「强制下线」。）
        $raw    = Request::get('online');
        $online = ($raw === null || $raw === '') ? !$current : Request::bool('online');

        CompanionService::setOnline($uid, $online ? 1 : 0);

        $this->ok(['online' => $online], $online ? '已开始接单' : '已暂停接单');
    }

    // ---------------------------------------------------------------- 技能管理

    /** 我的服务项目；all=1 时连下架的一起返回 */
    public function skills(): void
    {
        $uid = (int) $this->companionUser()['id'];

        $this->ok([
            'list'  => array_map([Presenter::class, 'skill'], CompanionService::skills($uid, !Request::bool('all'))),
            'games' => array_map([Presenter::class, 'game'], Db::all(
                'SELECT * FROM games WHERE status = 1 ORDER BY sort DESC, id ASC'
            )),
            'service_types' => self::serviceTypes(),
        ]);
    }

    /** 新增 / 编辑服务项目（传 id 表示编辑） */
    public function skillSave(): void
    {
        Request::requirePost();
        $uid = (int) $this->companionUser()['id'];

        $data = [
            'id'           => Request::int('id', 0),
            'game_id'      => Request::int('game_id', 0),
            'title'        => Request::str('title'),
            'service_type' => Request::str('service_type', 'hour'),
            'rank_level'   => Request::str('rank_level'),
            'price'        => Request::float('price', 0),
            'description'  => Request::str('description'),
            // 配图留空表示保持原图（与网页端一致）
            'cover'        => Request::uploadPath('cover', ['skill']),
        ];

        $skillId = CompanionService::saveSkill($uid, $data);

        $skill = Db::one(
            'SELECT s.*, g.`name` AS game_name FROM companion_skills s
             LEFT JOIN games g ON g.id = s.game_id WHERE s.id = ? LIMIT 1',
            [$skillId]
        );

        $this->ok(['skill' => Presenter::skill($skill)], $data['id'] > 0 ? '服务项目已更新' : '服务项目已上架');
    }

    public function skillDelete(): void
    {
        Request::requirePost();
        $uid = (int) $this->companionUser()['id'];
        $id  = Request::int('id', 0);

        if ($id <= 0) {
            throw ApiException::invalid('缺少服务项目 ID');
        }

        CompanionService::deleteSkill($uid, $id);

        $this->ok(null, '服务项目已删除');
    }

    /** 上架 / 下架切换 */
    public function skillToggle(): void
    {
        Request::requirePost();
        $uid = (int) $this->companionUser()['id'];
        $id  = Request::int('id', 0);

        if ($id <= 0) {
            throw ApiException::invalid('缺少服务项目 ID');
        }

        $status = CompanionService::toggleSkill($uid, $id);

        $this->ok(['status' => $status], $status === 1 ? '已上架' : '已下架');
    }

    // ---------------------------------------------------------------- 收益

    public function incomeStat(): void
    {
        $uid = (int) $this->companionUser()['id'];

        $this->ok(self::income(true, $uid));
    }

    // ---------------------------------------------------------------- 内部

    /** 陪玩收益统计（未通过审核时返回零值，避免 App 端到处判空） */
    private static function income(bool $approved, int $uid): array
    {
        $zero = [
            'total_orders'     => 0,
            'completed_orders' => 0,
            'serving_orders'   => 0,
            'total_income'     => 0.0,
            'total_income_text'=> '0.00',
            'today_income'     => 0.0,
            'today_income_text'=> '0.00',
            'today_orders'     => 0,
        ];

        if (!$approved) {
            return $zero;
        }

        $row = CompanionService::incomeStat($uid);

        $total = (float) ($row['total_income'] ?? 0);
        $today = (float) ($row['today_income'] ?? 0);

        return [
            'total_orders'      => (int) ($row['total_orders'] ?? 0),
            'completed_orders'  => (int) ($row['completed_orders'] ?? 0),
            'serving_orders'    => (int) ($row['serving_orders'] ?? 0),
            'total_income'      => $total,
            'total_income_text' => number_format($total, 2),
            'today_income'      => $today,
            'today_income_text' => number_format($today, 2),
            'today_orders'      => (int) ($row['today_orders'] ?? 0),
        ];
    }

    /** 服务类型选项（App 表单用，避免前端硬编码中文） */
    private static function serviceTypes(): array
    {
        $out = [];
        foreach (service_type_map() as $key => $text) {
            $out[] = ['key' => $key, 'name' => $text, 'unit' => service_unit_suffix($key)];
        }
        return $out;
    }
}
