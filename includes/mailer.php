<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

/**
 * Send HTML email via SMTP (Gmail-compatible STARTTLS).
 */
function send_mail(string $to, string $subject, string $htmlBody, ?string $textBody = null): bool
{
    if (MAIL_PASSWORD === '') {
        error_log('CAMS mail: MAIL_PASSWORD is empty');
        return false;
    }

    $textBody = $textBody ?? trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody)));
    $boundary = 'b_' . bin2hex(random_bytes(8));
    $fromName = MAIL_FROM_NAME;
    $from = MAIL_FROM;

    $headers = [
        'Date: ' . date('r'),
        'From: ' . sprintf('"%s" <%s>', addslashes($fromName), $from),
        'Reply-To: ' . $from,
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        'X-Mailer: CAMS',
    ];

    $body = '--' . $boundary . "\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n\r\n"
        . $textBody . "\r\n"
        . '--' . $boundary . "\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n\r\n"
        . $htmlBody . "\r\n"
        . '--' . $boundary . "--\r\n";

    $message = 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n"
        . implode("\r\n", $headers) . "\r\n\r\n"
        . $body;

    try {
        return cams_smtp_send($to, $message);
    } catch (Throwable $e) {
        error_log('CAMS mail error: ' . $e->getMessage());
        return false;
    }
}

function cams_smtp_send(string $to, string $data): bool
{
    $host = MAIL_HOST;
    $port = MAIL_PORT;
    $errno = 0;
    $errstr = '';
    $remote = ($port === 465)
        ? 'ssl://' . $host . ':' . $port
        : 'tcp://' . $host . ':' . $port;

    $fp = @stream_socket_client($remote, $errno, $errstr, 30, STREAM_CLIENT_CONNECT);
    if (!$fp) {
        throw new RuntimeException("SMTP connect failed: {$errstr}");
    }
    stream_set_timeout($fp, 30);

    $read = static function () use ($fp): string {
        $resp = '';
        while (($line = fgets($fp, 515)) !== false) {
            $resp .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $resp;
    };
    $expect = static function (string $resp, string $code) use ($read): void {
        if (!str_starts_with($resp, $code)) {
            throw new RuntimeException('SMTP unexpected: ' . trim($resp));
        }
    };
    $cmd = static function (string $line) use ($fp, $read, $expect): string {
        fwrite($fp, $line . "\r\n");
        $resp = $read();
        return $resp;
    };

    $expect($read(), '220');
    $expect($cmd('EHLO cams.local'), '250');

    if (MAIL_ENCRYPTION === 'tls' && $port !== 465) {
        $expect($cmd('STARTTLS'), '220');
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new RuntimeException('STARTTLS failed');
        }
        $expect($cmd('EHLO cams.local'), '250');
    }

    $expect($cmd('AUTH LOGIN'), '334');
    $expect($cmd(base64_encode(MAIL_USERNAME)), '334');
    $expect($cmd(base64_encode(MAIL_PASSWORD)), '235');
    $expect($cmd('MAIL FROM:<' . MAIL_FROM . '>'), '250');
    $expect($cmd('RCPT TO:<' . $to . '>'), '250');
    $expect($cmd('DATA'), '354');
    fwrite($fp, $data . "\r\n.\r\n");
    $expect($read(), '250');
    $cmd('QUIT');
    fclose($fp);
    return true;
}

function cams_e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function email_layout(string $title, string $preheader, string $innerHtml): string
{
    $app = cams_e(APP_NAME);
    $year = date('Y');
    $url = cams_e(APP_URL);
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$title}</title>
</head>
<body style="margin:0;padding:0;background:#f4f7f8;font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#142228;">
  <div style="display:none;max-height:0;overflow:hidden;opacity:0;">{$preheader}</div>
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f7f8;padding:28px 12px;">
    <tr><td align="center">
      <table role="presentation" width="100%" style="max-width:560px;background:#ffffff;border:1px solid #d9e3e7;border-radius:14px;overflow:hidden;">
        <tr>
          <td style="background:linear-gradient(135deg,#1e8f82,#177a6f);padding:22px 28px;color:#fff;">
            <div style="font-size:13px;letter-spacing:0.12em;text-transform:uppercase;opacity:0.9;">{$app}</div>
            <div style="font-size:22px;font-weight:700;margin-top:6px;">{$title}</div>
          </td>
        </tr>
        <tr>
          <td style="padding:28px;">
            {$innerHtml}
          </td>
        </tr>
        <tr>
          <td style="padding:16px 28px 24px;border-top:1px solid #e8eef0;font-size:12px;color:#5c727c;line-height:1.5;">
            You’re receiving this because of activity on your {$app} account.<br>
            <a href="{$url}" style="color:#1e8f82;text-decoration:none;">{$url}</a><br>
            &copy; {$year} {$app}. Cybersecurity awareness for safer online habits.
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
}

function email_otp_html(string $name, string $otp, string $purposeLabel): string
{
    $safeName = cams_e($name !== '' ? $name : 'there');
    $safeOtp = cams_e($otp);
    $mins = (string) OTP_EXPIRY_MINUTES;
    $inner = <<<HTML
<p style="margin:0 0 14px;font-size:16px;line-height:1.55;">Hi {$safeName},</p>
<p style="margin:0 0 18px;font-size:15px;line-height:1.55;color:#3d535c;">
  Use this one-time code to {$purposeLabel}. Do not share it with anyone — CAMS staff will never ask for it.
</p>
<div style="text-align:center;margin:24px 0;">
  <div style="display:inline-block;letter-spacing:0.35em;font-size:32px;font-weight:700;color:#142228;background:#f4f7f8;border:1px dashed #1e8f82;border-radius:12px;padding:14px 22px;">
    {$safeOtp}
  </div>
</div>
<p style="margin:0;font-size:13px;color:#5c727c;">This code expires in {$mins} minutes. If you didn’t request it, you can ignore this email.</p>
HTML;
    return email_layout('Your verification code', "Your CAMS code is {$otp}", $inner);
}

function email_reset_html(string $name, string $resetUrl): string
{
    $safeName = cams_e($name !== '' ? $name : 'there');
    $safeUrl = cams_e($resetUrl);
    $inner = <<<HTML
<p style="margin:0 0 14px;font-size:16px;line-height:1.55;">Hi {$safeName},</p>
<p style="margin:0 0 18px;font-size:15px;line-height:1.55;color:#3d535c;">
  We received a request to reset your CAMS password. Click the button below to choose a new password.
</p>
<p style="text-align:center;margin:28px 0;">
  <a href="{$safeUrl}" style="display:inline-block;background:#1e8f82;color:#ffffff;text-decoration:none;font-weight:600;padding:12px 22px;border-radius:10px;">Reset password</a>
</p>
<p style="margin:0 0 10px;font-size:13px;color:#5c727c;">Or copy this link:</p>
<p style="margin:0;font-size:12px;word-break:break-all;color:#1e8f82;">{$safeUrl}</p>
<p style="margin:18px 0 0;font-size:13px;color:#5c727c;">If you didn’t ask for a reset, ignore this email — your password stays the same.</p>
HTML;
    return email_layout('Reset your password', 'Reset your CAMS password', $inner);
}

function email_welcome_html(string $name): string
{
    $safeName = cams_e($name !== '' ? $name : 'there');
    $url = cams_e(APP_URL . '/auth/login.php');
    $inner = <<<HTML
<p style="margin:0 0 14px;font-size:16px;line-height:1.55;">Hi {$safeName},</p>
<p style="margin:0 0 18px;font-size:15px;line-height:1.55;color:#3d535c;">
  Your CAMS account is verified. You can now take cybersecurity awareness courses, quizzes, and track your progress.
</p>
<p style="text-align:center;margin:28px 0;">
  <a href="{$url}" style="display:inline-block;background:#1e8f82;color:#ffffff;text-decoration:none;font-weight:600;padding:12px 22px;border-radius:10px;">Open CAMS</a>
</p>
HTML;
    return email_layout('Welcome to CAMS', 'Your CAMS account is ready', $inner);
}

function email_invite_html(string $name, string $roleLabel, string $inviteUrl, string $inviterName, int $hours): string
{
    $safeName = cams_e($name !== '' ? $name : 'there');
    $safeRole = cams_e($roleLabel);
    $safeUrl = cams_e($inviteUrl);
    $safeInviter = cams_e($inviterName);
    $safeHours = (string) $hours;
    $inner = <<<HTML
<p style="margin:0 0 14px;font-size:16px;line-height:1.55;">Hi {$safeName},</p>
<p style="margin:0 0 18px;font-size:15px;line-height:1.55;color:#3d535c;">
  {$safeInviter} invited you to join CAMS as a <strong>{$safeRole}</strong>. Use the button below to accept and finish signup.
</p>
<p style="text-align:center;margin:28px 0;">
  <a href="{$safeUrl}" style="display:inline-block;background:#1e8f82;color:#ffffff;text-decoration:none;font-weight:600;padding:12px 22px;border-radius:10px;">Accept invitation</a>
</p>
<p style="margin:0 0 10px;font-size:13px;color:#5c727c;">This link expires in {$safeHours} hours. Or copy:</p>
<p style="margin:0;font-size:12px;word-break:break-all;color:#1e8f82;">{$safeUrl}</p>
HTML;
    return email_layout('You’re invited to CAMS', "CAMS invitation as {$roleLabel}", $inner);
}
