<?php
/**
 * Transactional email. Drivers:
 *   log  - write messages to storage/logs/mail.log (local development)
 *   smtp - minimal SMTP client with STARTTLS/SSL and AUTH LOGIN
 *   mail - PHP mail()
 * Subjects never contain confidential details.
 */
declare(strict_types=1);

function send_mail(string $to, string $subject, string $body, ?string $type = null): bool
{
    $masked = mask_email($to);
    $log = function (string $status, ?string $err = null) use ($masked, $subject, $type) {
        try {
            q_insert('email_log', ['recipient_masked' => mb_substr($masked, 0, 191), 'subject' => mb_substr($subject, 0, 191),
                'notif_type' => $type, 'delivery_status' => $status, 'error_message' => $err ? mb_substr($err, 0, 255) : null, 'created_at' => now()]);
        } catch (Throwable $e) {
        }
    };
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $log('skipped', 'Invalid address');
        return false;
    }
    if (setting('mail_enabled', '1') !== '1') {
        $log('skipped', 'Email disabled in settings');
        return false;
    }
    $subject = preg_replace('/[\r\n]+/', ' ', $subject);
    $fromEmail = setting('mail_from_email', 'no-reply@example.org');
    $fromName = preg_replace('/[\r\n"]+/', '', setting('mail_from_name', site_name()));
    $body .= "\n\n--\n" . site_name() . "\nThis is an automated message. Please do not reply directly to this email.";
    $driver = setting('mail_driver', 'log');
    try {
        if ($driver === 'smtp') {
            smtp_send($to, $subject, $body, $fromEmail, $fromName);
        } elseif ($driver === 'mail') {
            $headers = 'From: "' . $fromName . '" <' . $fromEmail . ">\r\nContent-Type: text/plain; charset=UTF-8\r\nMIME-Version: 1.0";
            if (!mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers)) {
                throw new RuntimeException('mail() returned false');
            }
        } else {
            $entry = "==== " . now() . "\nTo: {$to}\nSubject: {$subject}\n\n{$body}\n\n";
            file_put_contents(STORAGE_PATH . '/logs/mail.log', $entry, FILE_APPEND | LOCK_EX);
            $log('logged');
            return true;
        }
        $log('sent');
        return true;
    } catch (Throwable $e) {
        $log('failed', $e->getMessage());
        return false;
    }
}

function smtp_send(string $to, string $subject, string $body, string $fromEmail, string $fromName): void
{
    $host = setting('smtp_host');
    $port = setting_int('smtp_port', 587);
    $secure = setting('smtp_secure', 'tls');
    $user = setting('smtp_username');
    $pass = decrypt_value(setting('smtp_password_enc')) ?? '';
    if ($host === '') {
        throw new RuntimeException('SMTP host is not configured');
    }
    $remote = ($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $fp = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) {
        throw new RuntimeException("SMTP connect failed: {$errstr}");
    }
    stream_set_timeout($fp, 15);
    $read = function () use ($fp): string {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };
    $cmd = function (string $c, array $ok) use ($fp, $read): string {
        if ($c !== '') {
            fwrite($fp, $c . "\r\n");
        }
        $r = $read();
        if (!in_array((int) substr($r, 0, 3), $ok, true)) {
            throw new RuntimeException('SMTP error: ' . trim(substr($r, 0, 120)));
        }
        return $r;
    };
    $cmd('', [220]);
    $ehloHost = preg_replace('/[^a-z0-9.\-]/i', '', parse_url((string) cfg('app_url'), PHP_URL_HOST) ?: 'localhost');
    $cmd('EHLO ' . $ehloHost, [250]);
    if ($secure === 'tls') {
        $cmd('STARTTLS', [220]);
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT') ? STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT : 0))) {
            throw new RuntimeException('STARTTLS negotiation failed');
        }
        $cmd('EHLO ' . $ehloHost, [250]);
    }
    if ($user !== '') {
        $cmd('AUTH LOGIN', [334]);
        $cmd(base64_encode($user), [334]);
        $cmd(base64_encode($pass), [235]);
    }
    $cmd('MAIL FROM:<' . $fromEmail . '>', [250]);
    $cmd('RCPT TO:<' . $to . '>', [250, 251]);
    $cmd('DATA', [354]);
    $headers = [
        'Date: ' . date('r'),
        'From: =?UTF-8?B?' . base64_encode($fromName) . '?= <' . $fromEmail . '>',
        'To: <' . $to . '>',
        'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
        'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $ehloHost . '>',
    ];
    $data = implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode($body)) . "\r\n.";
    $cmd($data, [250]);
    $cmd('QUIT', [221]);
    fclose($fp);
}
