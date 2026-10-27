<?php
/**
 * 用户中心：资料修改、密码修改、陪玩入驻申请
 */
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\CompanionService;
use App\Db;
use App\OrderService;
use App\Upload;
use App\Wallet;

class UserController extends BaseController
{
    /** 我的（个人中心，底部 Tab） */
    public function center()
    {
        Auth::requireLogin();
        $userId = Auth::id();

        $wallet = Wallet::get($userId);

        $counts = Db::one(
            'SELECT COUNT(*) AS total,
                    SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS pending_pay,
                    SUM(CASE WHEN status IN (?, ?, ?) THEN 1 ELSE 0 END) AS serving,
                    SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS completed
             FROM orders WHERE user_id = ?',
            [
                OrderService::STATUS_PENDING_PAY,
                OrderService::STATUS_PENDING_ACCEPT,
                OrderService::STATUS_IN_SERVICE,
                OrderService::STATUS_PENDING_CONFIRM,
                OrderService::STATUS_COMPLETED,
                $userId,
            ]
        );

        $profile = Db::one('SELECT * FROM companion_profiles WHERE user_id = ? LIMIT 1', [$userId]);

        $compStat = null;
        $serving  = 0;
        if (is_companion()) {
            $compStat = CompanionService::incomeStat($userId);
            $serving  = (int) Db::value(
                'SELECT COUNT(*) FROM orders WHERE companion_id = ? AND status IN (?, ?)',
                [$userId, OrderService::STATUS_IN_SERVICE, OrderService::STATUS_PENDING_CONFIRM]
            );
        }

        $this->view('user/center', [
            'title'     => '我的',
            'wallet'    => $wallet,
            'counts'    => $counts ?: [],
            'profile'   => $profile,
            'comp_stat' => $compStat,
            'serving'   => $serving,
        ]);
    }

    /** 个人资料 */
    public function profile()
    {
        Auth::requireLogin();
        $userId = Auth::id();

        if (is_post()) {
            verify_csrf();
            $nickname = input_str('nickname');
            $phone    = input_str('phone');
            $email    = strtolower(input_str('email'));
            $gender   = input_int('gender', 0);
            $password = (string) input('password');

            try {
                if ($nickname === '') {
                    throw new \RuntimeException('昵称不能为空');
                }
                if (mb_strlen($nickname) > 30) {
                    throw new \RuntimeException('昵称不能超过 30 个字');
                }
                if ($phone !== '' && !is_phone($phone)) {
                    throw new \RuntimeException('手机号格式不正确');
                }
                if ($phone !== '') {
                    $exists = Db::one('SELECT id FROM users WHERE phone = ? AND id <> ? LIMIT 1', [$phone, $userId]);
                    if ($exists) {
                        throw new \RuntimeException('该手机号已被其他账号使用');
                    }
                }
                if ($email !== '') {
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        throw new \RuntimeException('邮箱格式不正确');
                    }
                    if (Db::one('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1', [$email, $userId])) {
                        throw new \RuntimeException('该邮箱已被其他账号使用');
                    }
                }

                $data = [
                    'nickname'   => mb_substr($nickname, 0, 30),
                    'phone'      => $phone,
                    'email'      => $email,
                    'gender'     => in_array($gender, [1, 2], true) ? $gender : 0,
                    'updated_at' => now(),
                ];

                $avatar = Upload::image('avatar', 'avatar');
                if ($avatar !== null) {
                    $data['avatar'] = $avatar;
                }
                if ($password !== '') {
                    if (strlen($password) < 6) {
                        throw new \RuntimeException('新密码至少 6 位');
                    }
                    // 改密码必须先验证当前密码，避免会话被盗后直接改密踢走本人
                    $oldPassword = (string) input('old_password');
                    if ($oldPassword === '') {
                        throw new \RuntimeException('修改密码需要先填写当前密码');
                    }
                    $row = Db::one('SELECT password FROM users WHERE id = ? LIMIT 1', [$userId]);
                    if (!$row || !password_verify($oldPassword, (string) $row['password'])) {
                        throw new \RuntimeException('当前密码不正确');
                    }
                    $data['password'] = password_hash($password, PASSWORD_DEFAULT);
                }

                Db::update('users', $data, 'id = ?', [$userId]);
                flash('success', '资料已更新');
                redirect_route('user/profile');
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
                redirect_route('user/profile');
            }
        }

        $profile = Db::one('SELECT * FROM companion_profiles WHERE user_id = ? LIMIT 1', [$userId]);

        $this->view('user/profile', [
            'title'   => '个人中心',
            'profile' => $profile,
        ]);
    }

    /** 陪玩入驻申请 */
    public function apply()
    {
        Auth::requireLogin();
        $userId  = Auth::id();
        $profile = Db::one('SELECT * FROM companion_profiles WHERE user_id = ? LIMIT 1', [$userId]);

        if (is_post()) {
            verify_csrf();
            try {
                CompanionService::apply($userId, [
                    'real_name' => input_str('real_name'),
                    'id_card'   => input_str('id_card'),
                    'phone'     => input_str('phone'),
                    'gender'    => input_int('gender', 0),
                    'city'      => input_str('city'),
                    'intro'     => input_str('intro'),
                    'tags'      => input_str('tags'),
                ]);
                if ($profile && (int) $profile['audit_status'] === 1) {
                    flash('success', '资料已更新');
                } else {
                    flash('success', '申请已提交，请等待平台审核');
                }
                redirect_route('user/apply');
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
                redirect_route('user/apply');
            }
        }

        $this->view('user/apply', [
            'title'   => '成为陪玩',
            'profile' => $profile,
            // 考核标准（后台可填）：申请页会展示，并且提交按钮要停留 4 秒才解禁
            'assess'  => (string) \App\Setting::get('companion_assess', ''),
            // 提交申请后展示的联系方式（后台可填），二维码存的是相对路径
            'contact_qr'   => (string) \App\Setting::get('companion_contact_qr', ''),
            'contact_text' => (string) \App\Setting::get('companion_contact_text', ''),
        ]);
    }
}
