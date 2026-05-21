<?php
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');

require_once(__DIR__ . '/../api_backend/mysqli.php');
require_once(__DIR__ . '/../api_backend/portal_gate.php');

portal_require_enabled($mysqli, 'incharge_portal_enabled', 1);

header('Content-Type: text/html; charset=UTF-8');
readfile(__DIR__ . '/index.html');
