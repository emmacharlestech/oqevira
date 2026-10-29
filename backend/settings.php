<?php
/**
 * Site settings (key/value) editable from Admin → Settings.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

const DEFAULT_SETTINGS = [
    'notify_email'      => 'support@oqevira.com',
    'autoreply_enabled' => 'on',
    'autoreply_subject' => "You're on the Oqevira waitlist, {first_name}!",
    'autoreply_body'    => "Hi {first_name},\n\n"
        . "Thank you for joining the Oqevira waitlist. You're officially in line for early access!\n\n"
        . "Oqevira brings flights, hotels, holiday homes, car hire, restaurants, local guides and event tickets together in one app, so you can stop juggling tabs and start enjoying the journey.\n\n"
        . "We'll be in touch before launch with your early access details. In the meantime, follow along for sneak peeks:\n\n"
        . "Instagram: https://www.instagram.com/oqevira/\n"
        . "TikTok: https://www.tiktok.com/@oqeviratravel\n"
        . "YouTube: https://www.youtube.com/@OqeviraTravel\n\n"
        . "Got a question or an idea? Just reply to this email, we read every message.\n\n"
        . "See you soon,\n"
        . "The Oqevira team",
];

function get_settings(): array
{
    static $cache = null;
    if ($cache === null) {
        $rows = db()->query('SELECT key, value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);
        $cache = array_merge(DEFAULT_SETTINGS, array_intersect_key($rows, DEFAULT_SETTINGS));
    }
    return $cache;
}

function save_settings(array $values): void
{
    $stmt = db()->prepare('INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value');
    foreach (array_intersect_key($values, DEFAULT_SETTINGS) as $k => $v) {
        $stmt->execute([$k, (string) $v]);
    }
}

/** The notification addresses, validated (comma or space separated in Settings). */
function notify_addresses(array $s): array
{
    $list = preg_split('/[\s,;]+/', $s['notify_email'], -1, PREG_SPLIT_NO_EMPTY) ?: [];
    return array_values(array_filter($list, fn ($a) => filter_var($a, FILTER_VALIDATE_EMAIL)));
}
