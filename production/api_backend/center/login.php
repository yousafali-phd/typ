<?php
session_start();
header("Content-Type: application/json");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("Referrer-Policy: same-origin");
require_once("../mysqli.php");
mysqli_report(MYSQLI_REPORT_OFF);
require_once(__DIR__ . '/../portal_gate.php');

if (!portal_setting_enabled($mysqli, 'incharge_portal_enabled', 1)) {
    echo json_encode(["status" => "fail", "message" => "Center Incharge portal is disabled by admin."]);
    exit;
}

$data = json_decode(file_get_contents("php://input"));
$username = $mysqli->real_escape_string(trim($data->username ?? ''));
$password_raw = trim($data->password ?? '');

if (!$username || !$password_raw) {
    echo json_encode(["status" => "fail", "message" => "Username and password are required"]);
    exit;
}

$q = $mysqli->query("SELECT ci.*, c.name as center_name FROM center_incharges ci LEFT JOIN centers c ON c.id=ci.center_id WHERE ci.username='$username' AND ci.is_active=1 LIMIT 1");
if (!$q) {
    echo json_encode(["status" => "fail", "message" => "Center incharge setup is not ready. Run update_database.sql first."]);
    exit;
}
if ($q->num_rows === 0) {
    echo json_encode(["status" => "fail", "message" => "Invalid credentials"]);
    exit;
}

$row = $q->fetch_assoc();
$ok = false;
if (!empty($row['password'])) {
    if (strpos($row['password'], '$2y$') === 0 || strpos($row['password'], '$argon2') === 0) {
        $ok = password_verify($password_raw, $row['password']);
    } else {
        $ok = (md5($password_raw) === $row['password']);
    }
}

if (!$ok) {
    echo json_encode(["status" => "fail", "message" => "Invalid credentials"]);
    exit;
}

if (empty($row['center_id'])) {
    echo json_encode(["status" => "fail", "message" => "No center assigned. Contact admin."]);
    exit;
}

$_SESSION['incharge_id'] = $row['id'];
$_SESSION['incharge_center_id'] = intval($row['center_id']);

unset($row['password']);
echo json_encode(["status" => "ok", "incharge" => $row]);