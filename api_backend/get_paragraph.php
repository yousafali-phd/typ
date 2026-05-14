<?php
header("Content-Type: application/json");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("Referrer-Policy: same-origin");
require_once("mysqli.php");

// Returns active paragraph for test (random by candidate type when available)
$type = intval($_GET['type'] ?? 0);
$uid = intval($_GET['uid'] ?? 0);
if ($type < 0) $type = 0;

function first_active_type_code($mysqli) {
    $q = $mysqli->query("SELECT code FROM type_codes WHERE is_active='1' ORDER BY code LIMIT 1");
    if ($q && ($r = $q->fetch_assoc())) {
        return intval($r['code']);
    }
    return 0;
}

if ($type <= 0) {
    $type = first_active_type_code($mysqli);
}

// Prefer the candidate's stored type from database when uid is provided.
// This avoids stale browser state after reset/unlock/retest workflows.
if ($uid > 0) {
    $u = $mysqli->query("SELECT type FROM users WHERE id='$uid' LIMIT 1");
    if ($u && $u->num_rows > 0) {
        $ur = $u->fetch_assoc();
        $db_type = intval($ur['type'] ?? 0);
        if ($db_type > 0) {
            $type = $db_type;
        }
    }
}

$type_code_exists = false;
$col_q = $mysqli->query("SHOW COLUMNS FROM paragraphs LIKE 'type_code'");
if ($col_q && $col_q->num_rows > 0) {
    $type_code_exists = true;
}

if ($type_code_exists) {
    $res = $mysqli->query("SELECT * FROM paragraphs WHERE is_active=1 AND type_code='$type' ORDER BY id DESC LIMIT 1");
    if (!$res || $res->num_rows == 0) {
        $fallback_type = first_active_type_code($mysqli);
        if ($fallback_type > 0) {
            $res = $mysqli->query("SELECT * FROM paragraphs WHERE is_active=1 AND type_code='$fallback_type' ORDER BY id DESC LIMIT 1");
        }
        if (!$res || $res->num_rows == 0) {
            $res = $mysqli->query("SELECT * FROM paragraphs WHERE is_active=1 ORDER BY id DESC LIMIT 1");
        }
    }
} else {
    $res = $mysqli->query("SELECT * FROM paragraphs WHERE is_active=1 ORDER BY id DESC LIMIT 1");
}

if (!$res || $res->num_rows == 0) {
    // Fallback default paragraph
    echo json_encode(["content" => "SUBJECT: Disposal of Enquiry cases based on anonymous or Pseudonymous complaints. I am directed to invite attention to this Department circular letter of even number dated 22/7/2011, and state that the following instructions may kindly be brought to the notice of all concerned, noted for strict compliance and may be followed during disposal of anonymous communications. Anonymous communications must invariably be filed on their receipt. No action of any kind is to be taken on them and no notice of any kind is to be taken on their contents."]);
} else {
    $row = $res->fetch_assoc();
    echo json_encode(["content" => $row['content'], "title" => $row['title']]);
}
?>
