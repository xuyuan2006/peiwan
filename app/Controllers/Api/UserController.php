<?php
/**
 * 用户中心接口：个人中心聚合、资料查看与修改、陪玩入驻申请。
 */
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Api\ApiException;
use App\Api\Presenter;
use App\Api\Request;
use App\Api\TokenAuth;
use App\CompanionService;
use App\Db;
use App\OrderService;
use App\Wallet;

class UserController extends BaseController
{
    /** 「我的」页聚合数据 */
    public function center(): void
    {
        $user = $this->user();
        $uid  = (int) $user['id'];

        // 计数口径与 OrderQuery::counts 保持一致：
        // serving 只算「服务中(3)」，待接单(2) 与待确认(4) 各有独立字段，不重复计数。
        $counts = Db::one(
            'SELECT COUNT(*) AS total,
                    SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS pending_pay,
                    SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS pending_accept,
                    SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS serving,
                    SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS pending_confirm,
                    SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS completed
             FROM orders WHERE user_id = ?',
            [
                OrderService::STATUS_PENDING_PAY,
                OrderService::STATUS_PENDING_ACCEPT,
                OrderService::STATUS_IN_SERVICE,
                OrderService::STATUS_PENDING_CONFIRM,
                OrderService::STATUS_COMPLETED,
                $uid,
            ]
        ) ?: [];

        $profile = Db::one('SELECT * FROM companion_profiles WHERE user_id = ? LIMIT 1', [$uid]);
        $audit   = $profile ? (int) $profile['audit_status'] : -1;

        $compStat = null;
        if ($profile && $audit === 1) {
            $row      = CompanionService::incomeStat($uid);
            $compStat = [
                'total_income' => (float) ($row['total_income'] ?? 0),
                'today_income' => (float) ($row['today_income'] ?? 0),
                'today_orders' => (int) ($row['today_orders'] ?? 0),
                'completed_orders' => (int) ($row['completed_orders'] ?? 0),
            ];
        }

        $this->ok([
            'user'    => Presenter::user($user),
            'wallet'  => Presenter::wallet(Wallet::get($uid)),
            'counts'  => [
                'total'           => (int) ($counts['total'] ?? 0),
                'pending_pay'     => (int) ($counts['pending_pay'] ?? 0),
                'pending_accept'  => (int) ($counts['pending_accept'] ?? 0),
                'serving'         => (int) ($counts['serving'] ?? 0),
                'pending_confirm' => (int) ($counts['pending_confirm'] ?? 0),
                'completed'       => (int) ($counts['completed'] ?? 0),
                'unread_message'  => OrderService::unreadTotal($uid),
            ],
            'companion' => [
                'applied'      => $profile !== null,
                'audit_status' => $audit,
                'audit_text'   => $profile ? audit_status_text($audit) : '未申请',
                'audit_remark' => (string) ($profile['audit_remark'] ?? ''),
                'online'       => $profile ? ((int) $profile['online_status'] === 1) : false,
            ],
            'comp_stat' => $compStat,
            'intro'     => (string) \App\Setting::get('companion_intro', ''),
        ]);
    }

    /** 个人资料（含陪玩资料，便于一个页面编辑） */
    public function profile(): void
    {
        $user    = $this->user();
        $uid     = (int) $user['id'];
        $profile = Db::one('SELECT * FROM companion_profiles WHERE user_id = ? LIMIT 1', [$uid]);

        $this->ok([
            'user'      => Presenter::user($user),
            'companion' => [
                'applied'      => $profile !== null,
                'audit_status' => $profile ? (int) $profile['audit_status'] : -1,
                'audit_text'   => $profile ? audit_status_text((int) $profile['audit_status']) : '未申请',
                'audit_remark' => (string) ($profile['audit_remark'] ?? ''),
                'online'       => $profile ? ((int) $profile['online_status'] === 1) : false,
                // myCompanion 是「本人视角」，含实名与证件号；对外公开接口一律用 Presenter::companion
                'profile'      => Presenter::myCompanion($profile),
            ],
        ]);
    }

    /**
     * 修改个人资料。
     * 改密码必须同时提供 old_password（与网页端一致，防止 token 被盗后直接改密踢走本人）。
     */
    public function saveProfile(): void
    {
        Request::requirePost();
        $user = $this->user();
        $uid  = (int) $user['id'];

        $nickname = Request::str('nickname');
        $phone    = Request::str('phone');
        $email    = strtolower(Request::str('email'));
        $gender   = Request::int('gender', 0);

        if ($nickname === '') {
            throw ApiException::invalid('昵称不能为空');
        }
        if (mb_strlen($nickname) > 30) {
            throw ApiException::invalid('昵称不能超过 30 个字');
        }
        if ($phone !== '') {
            if (!is_phone($phone)) {
                throw ApiException::invalid('手机号格式不正确');
            }
            if (Db::one('SELECT id FROM users WHERE phone = ? AND id <> ? LIMIT 1', [$phone, $uid])) {
                throw ApiException::invalid('该手机号已被其他账号使用');
            }
        }
        if ($email !== '') {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw ApiException::invalid('邮箱格式不正确');
            }
            if (Db::one('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1', [$email, $uid])) {
                throw ApiException::invalid('该邮箱已被其他账号使用');
            }
        }

        $data = [
            'nickname'   => mb_substr($nickname, 0, 30),
            'phone'      => $phone,
            'email'      => $email,
            'gender'     => in_array($gender, [1, 2], true) ? $gender : 0,
            'updated_at' => now(),
        ];

        // 头像：App 先调 upload/image?type=avatar 拿 path，再传进来
        $avatar = Request::uploadPath('avatar', ['avatar']);
        if ($avatar !== '') {
            $data['avatar'] = $avatar;
        }

        $password = Request::raw('password');
        if ($password !== '') {
            if (strlen($password) < 6) {
                throw ApiException::invalid('新密码至少 6 位');
            }
            $old = Request::raw('old_password');
            if ($old === '') {
                throw ApiException::invalid('修改密码需要先填写当前密码');
            }
            $row = Db::one('SELECT password FROM users WHERE id = ? LIMIT 1', [$uid]);
            if (!$row || !password_verify($old, (string) $row['password'])) {
                throw new ApiException('当前密码不正确', 400);
            }
            $data['password'] = password_hash($password, PASSWORD_DEFAULT);
        }

        Db::update('users', $data, 'id = ?', [$uid]);

        // 改过密码就把其它设备踢下线（保留当前这台），
        // 否则被盗号后改了密码也踢不掉对方，下面的提示语就成了假话。
        if (isset($data['password'])) {
            $keep = TokenAuth::tokenId();
            // 防御：tokenId 为 0 时（理论上不可达）不能执行这条 SQL，
            // 否则 `id <> 0` 会把包括当前令牌在内的全部令牌撤销。
            if ($keep > 0) {
                Db::query(
                    'UPDATE api_tokens SET revoked_at = ?, updated_at = ? WHERE user_id = ? AND id <> ? AND revoked_at IS NULL',
                    [now(), now(), $uid, $keep]
                );
            }
        }

        $fresh = Db::one('SELECT * FROM users WHERE id = ? LIMIT 1', [$uid]);

        $this->ok(['user' => Presenter::user($fresh)], isset($data['password']) ? '资料与密码已更新，其它设备需重新登录' : '资料已更新');
    }

    /** 陪玩入驻申请（与 companion/apply 等价，保留网页端的入口习惯） */
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
        ], $audit === 1 ? '资料已更新' : '申请已提交，请等待平台审核');
    }
}
