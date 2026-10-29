<?php
declare(strict_types=1);

require __DIR__ . '/../backend/auth.php';

require_csrf();
logout_admin();
redirect('login');
