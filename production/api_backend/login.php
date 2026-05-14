<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("Referrer-Policy: same-origin");
require_once("mysqli.php");

$data = json_decode(file_get_contents("php://input"));
if (!$data || empty($data->cnic) || empty($data->rollno) || empty($data->otp)) {
    echo json_encode(["status" => "fail", "message" => "Invalid request"]);
    exit;
}

$cnic    = $mysqli->real_escape_string(trim($data->cnic));
$rollno  = $mysqli->real_escape_string(trim($data->rollno));
$otp     = $mysqli->real_escape_string(trim($data->otp));

// Validate lab session password
$session_query = $mysqli->query("SELECT * FROM lab_sessions WHERE session_password='$otp' AND status='active' AND DATE(created_at)=CURDATE()");
if ($session_query->num_rows == 0) {
    echo json_encode(["status" => "invalid_password"]);
    exit;
}

$session = $session_query->fetch_assoc();
$center_id = $session['center_id'];

// Validate candidate
$check_user = $mysqli->query("SELECT u.*, c.name as mapped_center_name, c.code as mapped_center_code FROM users u LEFT JOIN centers c ON c.id=u.center_id WHERE u.cnic='$cnic' AND u.roll_no='$rollno' AND u.center_id='$center_id' AND u.type > 0");
if ($check_user->num_rows == 0) {
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
$last_login_exists = false;
$column_check = $mysqli->query("SHOW COLUMNS FROM users LIKE 'last_login'");
if ($column_check && $column_check->num_rows > 0) {
    $last_login_exists = true;
}

if ($last_login_exists) {
    $mysqli->query("UPDATE users SET logged_once='1', last_login=NOW() WHERE id='$user_id'");
} else {
    $mysqli->query("UPDATE users SET logged_once='1' WHERE id='$user_id'");
}

// Track live candidate
$mysqli->query("INSERT INTO live_candidates (user_id, session_id, center_id, started_at) 
                VALUES ('$user_id', '{$session['id']}', '$center_id', NOW())
                ON DUPLICATE KEY UPDATE started_at=NOW()");

echo json_encode(["status" => "ok", "user" => $row]);
?>
