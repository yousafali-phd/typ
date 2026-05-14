<?php
header("Content-Type: application/json");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("Referrer-Policy: same-origin");
require_once("mysqli.php");
mysqli_report(MYSQLI_REPORT_OFF);

$minutes = 1;
$q = $mysqli->query("SELECT svalue FROM app_settings WHERE skey='default_test_minutes' LIMIT 1");
if ($q && $q->num_rows > 0) {
    $row = $q->fetch_assoc();
    $v = intval($row['svalue']);
    if ($v > 0 && $v <= 30) {
        $minutes = $v;
    }
}

echo json_encode(["default_test_minutes" => $minutes]);
?>