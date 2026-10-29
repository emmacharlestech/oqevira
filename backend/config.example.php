<?php
/**
 * Copy to config.php and fill in. config.php is never committed.
 */
return [
    'debug'      => false,
    // Random secret used to hash visitor IPs for rate limiting. Any long random string.
    'app_secret' => 'replace-with-64-random-hex-characters',
    // Database and private files. Must be OUTSIDE public_html.
    'data_dir'   => dirname(__DIR__, 2) . '/oqevira_data',
    'site_url'   => 'https://oqevira.com',
    // The first admin account, created the first time the database is set up.
    // password_hash: output of password_hash('your password', PASSWORD_DEFAULT)
    'admin'      => [
        'email'         => 'support@oqevira.com',
        'name'          => 'Oqevira Team',
        'password_hash' => '',
    ],
];
