<?php
/**
 * 邮件发送：内置极简 SMTP 客户端（无 Composer 依赖）。
 *
 * 支持 ssl（隐式 TLS，一般 465）、tls（STARTTLS，一般 587）、none 三种加密方式，
 * 使用 AUTH LOGIN 认证，正文 base64 编码，主题/发件人名称做 UTF-8 编码。
 *
 * 验证码的生成/校验/防刷在 App\VerifyCode 里，本类只负责“把邮件发出去”。
 */
declare(strict_types=1);

namespace App;

class Mailer
{
    public static function fromAddress(): string
    {
        $from = trim((string) Setting::get('mail_from', ''));
        if ($from === '') {
            $from = trim((string) Setting::get('mail_smtp_user', ''));
        }
        return $from;
    }

    /** SMTP 是否配置完整 */
    public static function configured(): bool
    {
        return trim((string) Setting::get('mail_smtp_host', '')) !== '' && self::fromAddress() !== '';
    }

    /** 兼容旧调用：等价于 configured() */
    public static function enabled(): bool
    {
        return self::configured();
    }

    // ---------------------------------------------------------------- 发送

    public static function send(string $to, string $subject, string $html): void
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('收件邮箱格式不正确');
        }

        $host     = trim((string) Setting::get('mail_smtp_host', ''));
        $port     = Setting::int('mail_smtp_port', 465);
        $secure   = strtolower(trim((string) Setting::get('mail_smtp_secure', 'ssl')));
        $user     = trim((string) Setting::get('mail_smtp_user', ''));
        $pass     = (string) Setting::get('mail_smtp_pass', '');
        $from     = self::fromAddress();
        $fromName = trim((string) Setting::get('mail_from_name', ''));
        if ($fromName === '') {
            $fromName = site_name();
        }

        if ($host === '' || $from === '') {
            throw new \RuntimeException('尚未配置 SMTP 服务器与发件人邮箱');
        }
        if (!in_array($secure, ['ssl', 'tls', 'none'], true)) {
            $secure = 'ssl';
        }
        if ($port <= 0) {
            $port = $secure === 'tls' ? 587 : 465;
        }

        $remote  = ($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
        $context = stream_context_create([
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true],
        ]);
        $fp = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $context);
        if (!$fp) {
            throw new \RuntimeException('连接邮件服务器失败：' . ($errstr !== '' ? $errstr : ('错误码 ' . $errno)));
        }
        stream_set_timeout($fp, 15);

        try {
            self::expect($fp, [220]);

            $ehlo = 'localhost';
            if (!empty($_SERVER['HTTP_HOST'])) {
                $clean = preg_replace('/[^A-Za-z0-9\.\-]/', '', (string) $_SERVER['HTTP_HOST']);
                if (is_string($clean) && $clean !== '') {
                    $ehlo = $clean;
                }
            }
            self::command($fp, 'EHLO ' . $ehlo, [250]);

            if ($secure === 'tls') {
                self::command($fp, 'STARTTLS', [220]);
                if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new \RuntimeException('STARTTLS 握手失败，请确认端口与加密方式是否匹配');
                }
                self::command($fp, 'EHLO ' . $ehlo, [250]);
            }

            if ($user !== '') {
                self::command($fp, 'AUTH LOGIN', [334]);
                self::command($fp, base64_encode($user), [334]);
                self::command($fp, base64_encode($pass), [235]);
            }

            self::command($fp, 'MAIL FROM:<' . $from . '>', [250]);
            self::command($fp, 'RCPT TO:<' . $to . '>', [250, 251]);
            self::command($fp, 'DATA', [354]);

            $headers = [
                'From: ' . self::encodeHeader($fromName) . ' <' . $from . '>',
                'To: <' . $to . '>',
                'Subject: ' . self::encodeHeader($subject),
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8',
                'Content-Transfer-Encoding: base64',
                'Date: ' . date('r'),
                'Message-ID: <' . bin2hex(random_bytes(8)) . '@' . $host . '>',
            ];
            $body = chunk_split(base64_encode($html), 76, "\r\n");
            fwrite($fp, implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.\r\n");
            self::expect($fp, [250]);

            @fwrite($fp, "QUIT\r\n");
        } finally {
            @fclose($fp);
        }
    }

    /** 后台「发送测试邮件」 */
    public static function sendTest(string $to): void
    {
        self::send(
            $to,
            site_name() . ' 测试邮件',
            '<div style="font-family:sans-serif;font-size:14px;line-height:1.8">'
            . '<p>如果你收到这封邮件，说明 SMTP 配置正确。</p>'
            . '<p style="color:#8b8a97">发送时间：' . now() . '</p></div>'
        );
    }

    // ---------------------------------------------------------------- 内部工具

    private static function encodeHeader(string $text): string
    {
        return '=?UTF-8?B?' . base64_encode($text) . '?=';
    }

    /** @param resource $fp */
    private static function command($fp, string $command, array $codes): string
    {
        fwrite($fp, $command . "\r\n");
        return self::expect($fp, $codes);
    }

    /**
     * 读取 SMTP 响应并按预期状态码校验（支持 250-xxx 多行响应）
     * @param resource $fp
     */
    private static function expect($fp, array $codes): string
    {
        $response = '';
        while (($line = fgets($fp, 515)) !== false) {
            $response .= $line;
            if (strlen($line) < 4 || $line[3] !== '-') {
                break;
            }
        }
        if ($response === '') {
            throw new \RuntimeException('邮件服务器无响应（可能被防火墙拦截或端口错误）');
        }

        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $codes, true)) {
            throw new \RuntimeException('邮件服务器返回异常：' . trim($response));
        }
        return $response;
    }
}
