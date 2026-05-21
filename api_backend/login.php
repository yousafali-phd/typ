<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("Referrer-Policy: same-origin");
require_once("mysqli.php");
require_once(__DIR__ . '/portal_gate.php');

if (!portal_setting_enabled($mysqli, 'candidate_portal_enabled', 1)) {
    echo json_encode([
        "status" => "portal_disabled",
        "message" => "Candidate portal is disabled by admin."
    ]);
    exit;
}

$data = json_decode(file_get_contents("php://input"));
if (!$data || empty($data->cnic) || empty($data->rollno) || empty($data->otp)) {
    echo json_encode(["status" => "fail", "message" => "Invalid request"]);
    exit;
}

$cnic    = $mysqli->real_escape_string(trim($data->cnic));
$rollno  = $mysqli->real_escape_string(trim($data->rollno));
$otp     = $mysqli->real_escape_string(trim($data->otp));

// Validate lab session password
$session_query = $mysqli->query("SELECT id, center_id FROM lab_sessions WHERE session_password='$otp' AND status='active' AND created_at >= CURDATE() AND created_at < (CURDATE() + INTERVAL 1 DAY) ORDER BY id DESC LIMIT 1");
if (!$session_query) {
    echo json_encode(["status" => "fail", "message" => "Server is busy. Please try again in a moment."]);
    exit;
}
if ($session_query->num_rows === 0) {
    echo json_encode(["status" => "invalid_password"]);
    exit;
}

$session = $session_query->fetch_assoc();
$center_id = intval($session['center_id']);
$session_id = intval($session['id']);

// Validate candidate
$check_user = $mysqli->query("SELECT u.id, u.name, u.post, u.cnic, u.roll_no, u.center_id, u.center_code, u.center_name, u.image_url, u.type, u.tested, u.logged_once, u.allow_retest, u.wpm_required, c.name as mapped_center_name, c.code as mapped_center_code FROM users u LEFT JOIN centers c ON c.id=u.center_id WHERE u.cnic='$cnic' AND u.roll_no='$rollno' AND u.center_id='$center_id' AND u.type > 0 LIMIT 1");
if (!$check_user) {
    echo json_encode(["status" => "fail", "message" => "Server is busy. Please try again in a moment."]);
    exit;
}
if ($check_user->num_rows === 0) {
    echo json_encode(["status" => "notfound"]);
    exit;
}

$row     = $check_user->fetch_assoc();
$row['center_name'] = trim((string)($row['center_name'] ?? '')) !== '' ? $row['center_name'] : ($row['mapped_center_name'] ?? '');
$row['center_code'] = trim((string)($row['center_code'] ?? '')) !== '' ? $row['center_code'] : ($row['mapped_center_code'] ?? '');
unset($row['mapped_center_name'], $row['mapped_center_code']);
$user_id = $row['id'];

if ($row['tested'] == '1' && $row['allow_retest'] != '1') {
    echo json_encode(["status" => "already_tested"]);
    exit;
}

if ($row['logged_once'] == '1' && $row['tested'] == '0') {
    echo json_encode(["status" => "already_logged"]);
    exit;
}

// Mark as logged in
if (!$mysqli->query("UPDATE users SET logged_once='1', last_login=NOW() WHERE id='$user_id'")) {
    if (intval($mysqli->errno) === 1054) {
        // Backward compatibility for older databases that still miss last_login.
        $mysqli->query("UPDATE users SET logged_once='1' WHERE id='$user_id'");
    }
}

// Track live candidate
$mysqli->query("INSERT INTO live_candidates (user_id, session_id, center_id, started_at) 
                VALUES ('$user_id', '$session_id', '$center_id', NOW())
                ON DUPLICATE KEY UPDATE started_at=NOW()");

echo json_encode(["status" => "ok", "user" => $row]);
?>
