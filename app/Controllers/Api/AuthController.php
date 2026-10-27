<?php
/**
 * 鉴权接口：注册 / 登录 / 退出 / 当前用户 / 验证码 / 找回密码 / 改密码。
 *
 * 与网页端 AuthController 的差异：
 *  - 登录成功后签发 Bearer Token 而不是写 Session
 *  - 校验失败抛异常（统一 JSON），不再 flash + redirect
 *  - 登录与注册都加了 IP / 账号维度的限流
 */
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Api\ApiException;
use App\Api\Presenter;
use App\Api\RateLimit;
use App\Api\Request;
use App\Api\TokenAuth;
use App\Auth;
use App\Db;
use App\VerifyCode;
use App\Wallet;

class AuthController extends BaseController
{
    /** 登录失败限流：15 分钟内最多 10 次 */
    private const LOGIN_MAX    = 10;
    private const LOGIN_WINDOW = 900;

    // ---------------------------------------------------------------- 登录

    public function login(): void
    {
        Request::requirePost();
        TokenAuth::assertReady();

        $account  = Request::str('account');
        $password = Request::raw('password');

        if ($account === '' || $password === '') {
            throw ApiException::invalid('请填写账号和密码');
        }

        $ipKey      = 'login:ip:' . Request::ip();
        $accountKey = 'login:acc:' . strtolower($account);
        RateLimit::assert($ipKey, self::LOGIN_MAX, self::LOGIN_WINDOW, '登录尝试过于频繁，请 15 分钟后再试');
        RateLimit::assert($accountKey, self::LOGIN_MAX, self::LOGIN_WINDOW, '该账号尝试次数过多，请 15 分钟后再试');

        // 账号 / 手机号 / 邮箱 三种都能登录。
        // 账号是服务端生成的（形如 u260915A3F91C2D4E5B），用户记不住，
        // 所以实际入口是手机号和邮箱。
        $row = Db::one(
            "SELECT * FROM users WHERE username = ? OR (phone <> '' AND phone = ?) OR (email <> '' AND email = ?) LIMIT 1",
            [$account, $account, strtolower($account)]
        );

        if (!$row || (int) $row['status'] !== 1 || !password_verify($password, (string) $row['password'])) {
            RateLimit::add($ipKey, self::LOGIN_WINDOW);
            RateLimit::add($accountKey, self::LOGIN_WINDOW);
            throw new ApiException('账号或密码错误，或账号已被禁用', 400);
        }

        $uid = (int) $row['id'];

        // 复用原有登录逻辑：会写入 last_login_at / last_login_ip
        Auth::login($uid);

        RateLimit::clear($ipKey);
        RateLimit::clear($accountKey);

        $issued = TokenAuth::issue(
            $uid,
            Request::str('device'),
            Request::str('platform', 'android'),
            Request::str('app_version')
        );

        $this->ok([
            'token'     => $issued['token'],
            'expire_at' => $issued['expire_at'],
            'me'        => Presenter::me(
                Db::one('SELECT * FROM users WHERE id = ? LIMIT 1', [$uid]),
                Wallet::get($uid),
                Db::one('SELECT * FROM companion_profiles WHERE user_id = ? LIMIT 1', [$uid])
            ),
        ], '登录成功');
    }

    // ---------------------------------------------------------------- 注册

    public function register(): void
    {
        Request::requirePost();
        TokenAuth::assertReady();

        // 注册也要限流，防止批量刷号
        RateLimit::hit('register:ip:' . Request::ip(), 5, 3600, '注册过于频繁，请稍后再试');

        $channel  = VerifyCode::channelFor('register');
        $nickname = Request::str('nickname');
        $phone    = Request::str('phone');
        $email    = strtolower(Request::str('email'));
        $code     = Request::str('code', Request::str('email_code'));
        $password = Request::raw('password');
        $confirm  = Request::raw('password_confirm');
        $gender   = Request::int('gender', 0);

        // 账号不再由用户填写：昵称 + 手机号 + 邮箱已足够标识一个人，
        // 多一个「账号」既增加填写负担，又常因重名被拒。
        // 账号在库里保留（唯一键、后台检索、兼容旧数据），只是改由服务端分配。
        if ($nickname === '') {
            throw ApiException::invalid('请填写昵称');
        }
        if (mb_strlen($nickname) > 20) {
            throw ApiException::invalid('昵称最多 20 个字');
        }
        if (strlen($password) < 6) {
            throw ApiException::invalid('密码至少 6 位');
        }
        if ($password !== $confirm) {
            throw ApiException::invalid('两次输入的密码不一致');
        }

        // 手机号与邮箱都必填
        if ($phone === '') {
            throw ApiException::invalid('请填写手机号');
        }
        if (!is_phone($phone)) {
            throw ApiException::invalid('手机号格式不正确');
        }
        if (Db::one('SELECT id FROM users WHERE phone = ? LIMIT 1', [$phone])) {
            throw ApiException::invalid('该手机号已被注册');
        }

        if ($email === '') {
            throw ApiException::invalid('请填写邮箱');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ApiException::invalid('邮箱格式不正确');
        }
        if (Db::one('SELECT id FROM users WHERE email = ? LIMIT 1', [$email])) {
            throw ApiException::invalid('该邮箱已被注册');
        }

        // 按后台配置的验证方式校验（和网页端同一套 VerifyCode）
        if ($channel === 'email') {
            VerifyCode::verify('register', $email, $code);
        } elseif ($channel === 'sms') {
            VerifyCode::verify('register', $phone, $code);
        }

        $username = Auth::generateUsername();

        $userId = Db::tx(function () use ($username, $password, $nickname, $phone, $email, $gender) {
            $id = Db::insert('users', Auth::newUserFields($username, $password, $nickname, $phone, $email, $gender));
            Db::query(
                'INSERT IGNORE INTO user_wallets (user_id, balance, frozen, total_recharge, total_income, total_withdraw, total_expense, created_at, updated_at)
                 VALUES (?, 0, 0, 0, 0, 0, 0, ?, ?)',
                [$id, now(), now()]
            );
            return $id;
        });

        Auth::login($userId);

        $issued = TokenAuth::issue(
            $userId,
            Request::str('device'),
            Request::str('platform', 'android'),
            Request::str('app_version')
        );

        $this->ok([
            'token'     => $issued['token'],
            'expire_at' => $issued['expire_at'],
            'me'        => Presenter::me(
                Db::one('SELECT * FROM users WHERE id = ? LIMIT 1', [$userId]),
                Wallet::get($userId),
                null
            ),
        ], '注册成功，欢迎加入');
    }

    // ---------------------------------------------------------------- 退出 / 当前用户

    public function logout(): void
    {
        TokenAuth::revokeCurrent();
        $this->ok(null, '已退出登录');
    }

    public function me(): void
    {
        $uid = (int) $this->user()['id'];

        $this->ok(Presenter::me(
            Db::one('SELECT * FROM users WHERE id = ? LIMIT 1', [$uid]),
            Wallet::get($uid),
            Db::one('SELECT * FROM companion_profiles WHERE user_id = ? LIMIT 1', [$uid])
        ));
    }

    // ---------------------------------------------------------------- 验证码 / 找回密码

    /** 当前环节用哪种验证方式（App 据此决定表单显示邮箱还是手机号） */
    public function verifyChannel(): void
    {
        $wrap = function (string $scene): array {
            $channel = VerifyCode::channelFor($scene);
            return [
                'channel'     => $channel,
                'label'       => VerifyCode::targetLabel($channel),
                'enabled'     => VerifyCode::isEnabled($scene),
                'ttl_minutes' => VerifyCode::ttlMinutes(),
            ];
        };

        $this->ok(['register' => $wrap('register'), 'reset' => $wrap('reset')]);
    }

    public function sendCode(): void
    {
        Request::requirePost();

        $scene  = Request::str('scene', 'register');
        $target = Request::str('target', Request::str('email', Request::str('phone')));

        if (!in_array($scene, ['register', 'reset'], true)) {
            throw ApiException::invalid('验证码用途不正确');
        }
        if ($target === '') {
            throw ApiException::invalid('请填写接收验证码的邮箱或手机号');
        }

        // 双保险：VerifyCode 内部已有节流，这里再按 IP 兜一层
        RateLimit::hit('code:ip:' . Request::ip(), 10, 600, '获取验证码过于频繁，请稍后再试');

        VerifyCode::send($scene, $target);

        $this->ok([
            'target'      => $target,
            'ttl_minutes' => VerifyCode::ttlMinutes(),
        ], '验证码已发送，' . VerifyCode::ttlMinutes() . ' 分钟内有效');
    }

    public function forgot(): void
    {
        Request::requirePost();

        $channel  = VerifyCode::channelFor('reset');
        $email    = strtolower(Request::str('email'));
        $phone    = Request::str('phone');
        $code     = Request::str('code', Request::str('email_code'));
        $password = Request::raw('password');
        $confirm  = Request::raw('password_confirm');

        if ($channel === 'off') {
            throw ApiException::invalid('平台未开启验证码找回密码，请联系客服协助重置');
        }
        if (strlen($password) < 6) {
            throw ApiException::invalid('新密码至少 6 位');
        }
        if ($password !== $confirm) {
            throw ApiException::invalid('两次输入的密码不一致');
        }

        if ($channel === 'email') {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw ApiException::invalid('邮箱格式不正确');
            }
            $user = Db::one('SELECT id, status FROM users WHERE email = ? LIMIT 1', [$email]);
            if (!$user) {
                throw ApiException::invalid('该邮箱没有绑定任何账号');
            }
            $target = $email;
        } else {
            if (!is_phone($phone)) {
                throw ApiException::invalid('手机号格式不正确');
            }
            $user = Db::one('SELECT id, status FROM users WHERE phone = ? LIMIT 1', [$phone]);
            if (!$user) {
                throw ApiException::invalid('该手机号没有绑定任何账号');
            }
            $target = $phone;
        }

        if ((int) $user['status'] !== 1) {
            throw ApiException::forbidden('该账号已被禁用，请联系客服');
        }

        VerifyCode::verify('reset', $target, $code);

        $uid = (int) $user['id'];
        Db::tx(function () use ($uid, $password, $target) {
            Db::update('users', [
                'password'   => password_hash($password, PASSWORD_DEFAULT),
                'updated_at' => now(),
            ], 'id = ?', [$uid]);
            VerifyCode::invalidate('reset', $target);
        });

        // 密码已重置：把该账号所有设备踢下线，强制重新登录
        TokenAuth::revokeAll($uid);

        $this->ok(null, '密码已重置，请用新密码登录');
    }

    public function changePassword(): void
    {
        Request::requirePost();

        $uid     = (int) $this->user()['id'];
        $old     = Request::raw('old_password');
        $new     = Request::raw('password');
        $confirm = Request::raw('password_confirm');

        if ($old === '') {
            throw ApiException::invalid('请填写当前密码');
        }
        if (strlen($new) < 6) {
            throw ApiException::invalid('新密码至少 6 位');
        }
        if ($new !== $confirm) {
            throw ApiException::invalid('两次输入的新密码不一致');
        }

        $row = Db::one('SELECT password FROM users WHERE id = ? LIMIT 1', [$uid]);
        if (!$row || !password_verify($old, (string) $row['password'])) {
            throw new ApiException('当前密码不正确', 400);
        }

        Db::update('users', [
            'password'   => password_hash($new, PASSWORD_DEFAULT),
            'updated_at' => now(),
        ], 'id = ?', [$uid]);

        // 踢掉其它设备，但保留当前这台（tokenId 为 0 时不能执行，
        // 否则 `id <> 0` 会把当前令牌也一并撤销）
        $keep = TokenAuth::tokenId();
        if ($keep > 0) {
            Db::query(
                'UPDATE api_tokens SET revoked_at = ?, updated_at = ? WHERE user_id = ? AND id <> ? AND revoked_at IS NULL',
                [now(), now(), $uid, $keep]
            );
        }

        $this->ok(null, '密码已修改，其它设备需重新登录');
    }
}
