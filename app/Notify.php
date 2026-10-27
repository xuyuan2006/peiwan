<?php
/**
 * 站内通知（当前只实现邮件）。
 *
 * 设计原则：**通知失败绝不能影响主流程**。
 * 所以每个对外方法内部都自己 try/catch 吞掉异常，
 * 调用方（如工单回复）不需要也不应该处理邮件的错误。
 */
declare(strict_types=1);

namespace App;

class Notify
{
    /**
     * 现在到底能不能发邮件？
     *
     * 不能看已经废弃的 `mail_enabled` 设置项 —— 它早被「注册/找回密码验证方式」取代，
     * 代码里没有任何发送逻辑读它（见 sql/schema.sql 里那条备注）。
     *
     * 这里按两个条件同时成立来判断：
     *   1) SMTP 配好了（Mailer::configured()）
     *   2) 邮箱验证确实在用（VerifyCode::channelFor() 返回 email）
     * 之所以第 2 条也必要：VerifyCode::channelFor() 在 SMTP 没配好时会自动降级成 off，
     * 所以它等于 email 说明邮箱链路是通的；而如果站点把验证方式关掉了，
     * 通常意味着运营方并不想依赖邮件，那就不要给他们发通知邮件。
     */
    public static function mailReady(): bool
    {
        if (!Mailer::configured()) {
            return false;
        }

        return VerifyCode::channelFor('register') === 'email'
            || VerifyCode::channelFor('reset') === 'email';
    }

    /**
     * 工单被**客服**回复后，通知工单主人。
     *
     * 只在 admin 回复时调用：用户自己追问不必给自己发信。
     */
    public static function ticketReplied(int $ticketId, string $replyContent): void
    {
        // 只要 SMTP 配好就发。不再依赖「注册/找回密码的验证方式是不是邮箱」，
        // 否则站点用短信收验证码时，工单回复的邮件就永远发不出去。
        if (!Mailer::configured()) {
            return;
        }

        try {
            $ticket = TicketService::find($ticketId);
            if (!$ticket) {
                return;
            }

            $to = trim((string) ($ticket['email'] ?? ''));
            if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
                // 用户没留邮箱（或格式不对）就跳过，这不是错误
                return;
            }

            $subject = '【' . site_name() . '】您的工单有最新回复';
            $html    = self::ticketTemplate(
                (string) ($ticket['nickname'] ?? ''),
                (string) ($ticket['title'] ?? ''),
                $replyContent,
                abs_url('ticket/detail', ['id' => $ticketId])   // 邮件里必须是完整地址
            );

            Mailer::send($to, $subject, $html);
        } catch (\Throwable $e) {
            // 邮件发送失败不影响工单回复本身；错误会由 PHP 的错误日志记录
        }
    }

    /** 工单回复通知的邮件正文（内联样式，和 VerifyCode::mailTemplate 保持同一风格） */
    private static function ticketTemplate(string $nickname, string $title, string $reply, string $url): string
    {
        $greeting = $nickname !== '' ? e($nickname) . '，您好：' : '您好：';
        $body     = nl2br(e(mb_substr($reply, 0, 500)));

        return '<div style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',\'Microsoft YaHei\',sans-serif;'
            . 'font-size:14px;line-height:1.7;color:#222;max-width:560px">'
            . '<p>' . $greeting . '</p>'
            . '<p>您的工单「' . e($title) . '」客服已回复：</p>'
            . '<div style="background:#f4f5f9;border-left:3px solid #6c5ce7;padding:10px 12px;'
            . 'border-radius:6px;margin:10px 0;color:#333">' . $body . '</div>'
            . '<p><a href="' . e($url) . '" style="color:#6c5ce7">点击查看完整对话 →</a></p>'
            . '<p style="color:#999;font-size:12px">本邮件由系统自动发送，请勿直接回复。</p>'
            . '</div>';
    }
}
