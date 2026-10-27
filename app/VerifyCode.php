<?php
/**
 * 验证码服务：统一管理「邮箱验证码」与「手机短信验证码」。
 *
 * 后台可为每个环节选择验证方式（off / email / sms），本类负责：
 *   生成 → 存库（verify_codes）→ 通过对应渠道发送 → 校验 → 作废
 * 防刷：同目标同场景 60 秒 1 次、每天 5 次、同 IP 每天 20 次；单码最多试 5 次。
 */
declare(strict_types=1);

namespace App;

class VerifyCode
{
    private const RESEND_SECONDS = 60;
    private const DAILY_LIMIT    = 5;
    private const IP_DAILY_LIMIT = 20;
    private const MAX_ATTEMPTS   = 5;

    /** 场景 => [标题, 设置键] */
    public static function scenes(): array
    {
        return [
            'register' => ['注册', 'verify_register_channel'],
            'reset'    => ['找回密码', 'verify_reset_channel'],
        ];
    }

    /** 当前场景使用的验证方式：off / email / sms（渠道没配好时按 off 处理） */
    public static function channelFor(string $scene): string
    {
        $scenes = self::scenes();
        if (!isset($scenes[$scene])) {
            return 'off';
        }

        $channel = (string) Setting::get($scenes[$scene][1], 'off');
        if (!in_array($channel, ['off', 'email', 'sms'], true)) {
            $channel = 'off';
        }
        if ($channel === 'email' && !Mailer::configured()) {
            return 'off';
        }
        if ($channel === 'sms' && !Sms::configured()) {
            return 'off';
        }
        return $channel;
    }

    public static function isEnabled(string $scene): bool
    {
        return self::channelFor($scene) !== 'off';
    }

    /** 验证码有效期（分钟）：后台可配，邮件文案里的时长与它保持一致 */
    public static function ttlMinutes(): int
    {
        $n = Setting::int('verify_code_ttl_minutes', 3);
        return max(1, min(60, $n));
    }

    public static function targetLabel(string $channel): string
    {
        return $channel === 'sms' ? '手机号' : '邮箱';
    }

    /** 发送验证码 */
    public static function send(string $scene, string $target): void
    {
        $channel = self::channelFor($scene);
        if ($channel === 'off') {
            throw new \RuntimeException('平台未开启该环节的验证码验证');
        }

        $target = trim($target);
        if ($channel === 'email') {
            $target = strtolower($target);
            if (!filter_var($target, FILTER_VALIDATE_EMAIL)) {
                throw new \RuntimeException('邮箱格式不正确');
            }
        } elseif (!is_phone($target)) {
            throw new \RuntimeException('手机号格式不正确');
        }

        self::assertCanSend($target, $scene);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Db::insert('verify_codes', [
            'target'     => $target,
            'channel'    => $channel,
            'scene'      => $scene,
            'code'       => $code,
            'status'     => 0,
            'attempts'   => 0,
            'ip'         => client_ip(),
            'expire_at'  => date('Y-m-d H:i:s', time() + self::ttlMinutes() * 60),
            'created_at' => now(),
        ]);

        if ($channel === 'email') {
            // 注册与找回密码用两套不同口吻的邮件文案
            $subject = $scene === 'reset'
                ? '【' . site_name() . '】密码重置密钥'
                : '【' . site_name() . '】注册验证码';
            Mailer::send($target, $subject, self::mailTemplate($code, $scene));
        } else {
            Sms::sendCode($target, $code, $scene);
        }
    }

    /** 防刷：同目标同场景 60 秒 1 次、每日 5 次；同 IP 每日 20 次 */
    public static function assertCanSend(string $target, string $scene): void
    {
        $last = Db::one(
            'SELECT created_at FROM verify_codes WHERE target = ? AND scene = ? ORDER BY id DESC LIMIT 1',
            [$target, $scene]
        );
        if ($last) {
            $elapsed = time() - (int) strtotime((string) $last['created_at']);
            if ($elapsed < self::RESEND_SECONDS) {
                throw new \RuntimeException('发送太频繁，请 ' . max(1, self::RESEND_SECONDS - $elapsed) . ' 秒后再试');
            }
        }

        $today = (int) Db::value(
            'SELECT COUNT(*) FROM verify_codes WHERE target = ? AND scene = ? AND created_at >= ?',
            [$target, $scene, date('Y-m-d 00:00:00')]
        );
        if ($today >= self::DAILY_LIMIT) {
            throw new \RuntimeException('该账号今日验证码次数已达上限，请明天再试或联系客服');
        }

        $ip = client_ip();
        if ($ip !== '') {
            $ipCount = (int) Db::value(
                'SELECT COUNT(*) FROM verify_codes WHERE ip = ? AND created_at >= ?',
                [$ip, date('Y-m-d 00:00:00')]
            );
            if ($ipCount >= self::IP_DAILY_LIMIT) {
                throw new \RuntimeException('当前网络今日发送次数过多，请明天再试');
            }
        }
    }

    /** 校验验证码，成功后立即作废 */
    public static function verify(string $scene, string $target, string $code): void
    {
        $target = trim($target);
        if (strpos($target, '@') !== false) {
            $target = strtolower($target);
        }
        $code = trim($code);

        if ($code === '') {
            throw new \RuntimeException('请填写验证码');
        }

        $row = Db::one(
            'SELECT * FROM verify_codes WHERE target = ? AND scene = ? AND status = 0 ORDER BY id DESC LIMIT 1',
            [$target, $scene]
        );
        if (!$row) {
            throw new \RuntimeException('请先获取验证码');
        }
        if (!empty($row['expire_at']) && strtotime((string) $row['expire_at']) < time()) {
            throw new \RuntimeException('验证码已过期，请重新获取');
        }
        if ((int) $row['attempts'] >= self::MAX_ATTEMPTS) {
            throw new \RuntimeException('该验证码尝试次数过多，请重新获取');
        }
        if (!hash_equals((string) $row['code'], $code)) {
            Db::query('UPDATE verify_codes SET attempts = attempts + 1 WHERE id = ?', [(int) $row['id']]);
            throw new \RuntimeException('验证码不正确');
        }

        Db::update('verify_codes', ['status' => 1, 'used_at' => now()], 'id = ?', [(int) $row['id']]);
    }

    /** 作废某目标在某场景下所有未使用的验证码（改密码成功后调用） */
    public static function invalidate(string $scene, string $target): void
    {
        Db::update(
            'verify_codes',
            ['status' => 1, 'used_at' => now()],
            'target = ? AND scene = ? AND status = 0',
            [trim($target), $scene]
        );
    }

    /** 清理过期验证码（定时任务调用） */
    public static function cleanupOld(int $days = 2): int
    {
        $n = Db::update(
            'verify_codes',
            ['status' => 1, 'used_at' => now()],
            'status = 0 AND expire_at IS NOT NULL AND expire_at < ?',
            [date('Y-m-d H:i:s', time() - max(1, $days) * 86400)]
        );
        Db::query('DELETE FROM verify_codes WHERE created_at < ?', [date('Y-m-d H:i:s', time() - 30 * 86400)]);
        return $n;
    }

    private static function mailTemplate(string $code, string $scene): string
    {
        $site = htmlspecialchars(site_name(), ENT_QUOTES, 'UTF-8');
        $ttl  = self::ttlMinutes();

        if ($scene === 'reset') {
            $title = '听说您遇到一些麻烦，没关系的';
            $lead  = '这是您的密钥，我刚刚拿到的，趁热';
            $foot  = '超过 ' . $ttl . ' 分钟就凉了，请尽快使用；不是本人操作请忽略本邮件。';
        } else {
            $title = '听说您想加入我们';
            $lead  = '这是我们之间的秘密';
            $foot  = '打死不要告诉任何人 · 请在 ' . $ttl . ' 分钟内使用。';
        }

        return '<div style="font-family:-apple-system,Segoe UI,Microsoft YaHei,sans-serif;background:#f4f5f9;padding:24px 12px">'
            . '<div style="max-width:520px;margin:0 auto;background:#fff;border:1px solid #eceaf3;border-radius:16px;padding:30px 22px;text-align:center">'
            . '<div style="font-size:13px;color:#8b8a97;margin-bottom:18px">' . $site . '</div>'
            . '<h1 style="margin:0 0 10px;font-size:22px;line-height:1.5;color:#23222b;font-weight:700">' . $title . '</h1>'
            . '<p style="margin:0 0 22px;font-size:15px;color:#55535f">' . $lead . '</p>'
            . '<div style="font-size:36px;font-weight:800;letter-spacing:8px;color:#6c5ce7;background:#f4f3fa;border-radius:12px;padding:18px 0;margin:0 0 22px">' . $code . '</div>'
            . '<p style="margin:0;font-size:13.5px;color:#8b8a97;line-height:1.7">' . $foot . '</p>'
            . '</div></div>';
    }
}
