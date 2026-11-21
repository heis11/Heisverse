<?php
/**
 * HEISVERSE — Secure Logout Handler
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';

Auth::logout();
header('Location: login.php');
exit;
