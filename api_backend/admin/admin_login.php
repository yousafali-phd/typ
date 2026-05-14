<?php
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();
header("Content-Type: application/json");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("Referrer-Policy: same-origin");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");
require_once("../../api_backend/mysqli.php");

$data = json_decode(file_get_contents("php://input"));
if (!is_object($data)) {
    echo json_encode(["status" => "fail"]);
    exit;
}

$username = $mysqli->real_escape_string(trim($data->username ?? ''));
$password_raw = trim($data->password ?? '');

$q = $mysqli->query("SELECT * FROM admins WHERE username='$username' AND is_active=1 LIMIT 1");
if (!$q || $q->num_rows == 0) {
    echo json_encode(["status" => "fail"]);
    exit;
}
$admin = $q->fetch_assoc();

$ok = false;
if (!empty($admin['password'])) {
    // Support modern password_hash and legacy MD5 hashes.
    if (strpos($admin['password'], '$2y$') === 0 || strpos($admin['password'], '$argon2') === 0) {
        $ok = password_verify($password_raw, $admin['password']);
    } else {
        $ok = (md5($password_raw) === $admin['password']);
    }
}

if (!$ok) {
    echo json_encode(["status" => "fail"]);
    exit;
}

session_regenerate_id(true);
$_SESSION['admin_id'] = intval($admin['id']);
$_SESSION['admin_username'] = $admin['username'];
$_SESSION['admin_full_name'] = $admin['full_name'] ?? $admin['username'];

unset($admin['password']);
session_write_close();
echo json_encode(["status" => "ok", "admin" => $admin]);
