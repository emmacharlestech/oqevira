<?php
/**
 * Outgoing email: the support-inbox notification and the branded auto-reply.
 */
declare(strict_types=1);

require_once __DIR__ . '/settings.php';

const MAIL_FROM      = 'support@oqevira.com';
const MAIL_FROM_NAME = 'Oqevira';

function mime_header(string $text): string
{
    return preg_match('/[^\x20-\x7E]/', $text) ? '=?UTF-8?B?' . base64_encode($text) . '?=' : $text;
}

/** Send a plain-text email, with an optional HTML version. Returns mail()'s result. */
function send_mail(string $to, string $subject, string $text, ?string $html = null, string $replyTo = '', string $fromName = MAIL_FROM_NAME): bool
{
    $headers = [
        'From: ' . mime_header($fromName) . ' <' . MAIL_FROM . '>',
        'MIME-Version: 1.0',
        'X-Mailer: Oqevira',
    ];
    if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $headers[] = 'Reply-To: ' . $replyTo;
    }
    if ($html === null) {
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: base64';
        $body = chunk_split(base64_encode($text));
    } else {
        $b = 'oq_' . bin2hex(random_bytes(12));
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $b . '"';
        $body = "--$b\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($text))
            . "--$b\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($html))
            . "--$b--\r\n";
    }
    $ok = @mail($to, mime_header($subject), $body, implode("\r\n", $headers), '-f' . MAIL_FROM);
    if (!$ok) {
        error_log("Oqevira mail: mail() failed sending '$subject' to $to");
    }
    return $ok;
}

/** Fill {first_name} / {name} in an auto-reply template. */
function fill_template(string $template, array $signup): string
{
    return strtr($template, ['{first_name}' => first_name($signup['name']), '{name}' => $signup['name']]);
}

/** Plain text → simple HTML: paragraphs, line breaks and clickable links. */
function text_to_html(string $text): string
{
    $html = '';
    foreach (preg_split("/\n{2,}/", trim($text)) as $para) {
        $p = nl2br(e($para), false);
        $p = preg_replace('#(https?://[^\s<]+)#', '<a href="$1" style="color:#C9372C;font-weight:700;text-decoration:none">$1</a>', $p);
        $html .= '<p style="margin:0 0 16px">' . $p . '</p>';
    }
    return $html;
}

/** Branded HTML wrapper used by the auto-reply. */
function branded_html(string $title, string $bodyHtml): string
{
    global $CONFIG;
    $site = rtrim($CONFIG['site_url'] ?? 'https://oqevira.com', '/');
    return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>' . e($title) . '</title></head>'
        . '<body style="margin:0;padding:0;background:#F8F7F3">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F8F7F3"><tr><td align="center" style="padding:28px 14px">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:20px;overflow:hidden">'
        . '<tr><td style="background:#14213D;padding:28px 32px">'
        . '<img src="' . e($site) . '/assets/brand/oqevira_logo_white.png" alt="oqevira" width="150" style="display:block;width:150px;height:auto;border:0">'
        . '<p style="margin:18px 0 0;font:800 12px/1.4 Arial,Helvetica,sans-serif;letter-spacing:2px;text-transform:uppercase;color:#FF5A4E">One app. Seven things.</p>'
        . '</td></tr>'
        . '<tr><td style="padding:32px;font:16px/1.6 Arial,Helvetica,sans-serif;color:#14213D">' . $bodyHtml . '</td></tr>'
        . '<tr><td style="padding:0 32px 32px"><a href="' . e($site) . '" style="display:inline-block;padding:14px 26px;border-radius:999px;background:#FF5A4E;color:#14213D;font:700 15px Arial,Helvetica,sans-serif;text-decoration:none">Visit oqevira.com</a></td></tr>'
        . '<tr><td style="padding:20px 32px;background:#F8F7F3;font:12px/1.6 Arial,Helvetica,sans-serif;color:#646B7A">'
        . 'You\'re receiving this because you joined the Oqevira waitlist at oqevira.com. To be removed, reply to this email or write to <a href="mailto:support@oqevira.com" style="color:#646B7A">support@oqevira.com</a>.'
        . '</td></tr></table></td></tr></table></body></html>';
}

/** Send the "you're on the list" email to someone who just signed up. */
function send_autoreply(array $signup, ?string $to = null): bool
{
    $s = get_settings();
    $subject = fill_template($s['autoreply_subject'], $signup);
    $text = fill_template($s['autoreply_body'], $signup);
    $html = branded_html($subject, text_to_html($text));
    return send_mail($to ?? $signup['email'], $subject, $text . "\n\n—\nhttps://oqevira.com", $html, MAIL_FROM);
}

/** Tell the support inbox about a new sign-up. Reply-To is the person, so replying reaches them. */
function send_signup_notification(array $r): bool
{
    $lines = [
        'New Oqevira waitlist sign-up',
        '',
        'Name:             ' . $r['name'],
        'Email:            ' . $r['email'],
        'Phone:            ' . $r['phone'],
        'Based in:         ' . $r['location'],
        'Using Oqevira as: ' . $r['role'],
    ];
    if ($r['role'] === 'Tour guide') {
        $lines[] = 'Guides in:        ' . $r['guide_area'];
        $lines[] = 'Languages:        ' . ($r['guide_languages'] ?: '—');
    }
    if ($r['role'] === 'Travel business') {
        $lines[] = 'Business:         ' . $r['business_name'];
        $lines[] = 'Business type:    ' . $r['business_type'];
    }
    array_push($lines,
        'Excited about:    ' . ($r['interests'] ?: '—'),
        'Travels:          ' . ($r['frequency'] ?: '—'),
        'Heard via:        ' . ($r['heard'] ?: '—'),
        '',
        'Message:',
        $r['message'] !== '' ? $r['message'] : '—',
        '',
        '---',
        'Submitted: ' . format_date($r['created_at']),
        'From page: ' . ($r['source'] ?: 'unknown'),
        'See everyone on the waitlist: https://oqevira.com/admin/signups',
        'Reply to this email to answer ' . first_name($r['name']) . ' directly.'
    );
    $ok = false;
    foreach (notify_addresses(get_settings()) as $to) {
        $ok = send_mail($to, "New waitlist sign-up: {$r['name']} ({$r['role']})", implode("\n", $lines), null, $r['email'], 'Oqevira Waitlist') || $ok;
    }
    return $ok;
}
