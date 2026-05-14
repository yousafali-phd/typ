<?php
header("Content-Type: application/json");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("Referrer-Policy: same-origin");
require_once("mysqli.php");

$data   = json_decode(file_get_contents("php://input"));
if (!$data || empty($data->result)) {
    echo json_encode(["status" => "fail", "message" => "Invalid result payload"]);
    exit;
}
$result = $data->result;
$date   = date('Y-m-d H:i:s');

$r_uid              = $mysqli->real_escape_string($result->uid);
$r_roll_no          = $mysqli->real_escape_string($result->roll_no);
$r_center_id        = $mysqli->real_escape_string($result->center_id);
$r_characters_typed = $mysqli->real_escape_string($result->total_words_typed);
$r_correct_words    = sizeof($result->correct_words);
$r_incorrect_words  = sizeof($result->incorrect_words);
$r_wpm              = $mysqli->real_escape_string($result->wpm);
$r_accuracy         = $mysqli->real_escape_string($result->accuracy);
$r_time             = $mysqli->real_escape_string($result->time) . " min";
$r_finish_reason    = $mysqli->real_escape_string($result->finish_reason ?? 'completed');
$is_completed       = in_array($r_finish_reason, ['completed', 'timeout'], true);

if ($is_completed) {
    $mysqli->query("INSERT INTO results 
        (uid, roll_no, center_id, characters_typed, correct_words, incorrect_words, wpm, accuracy, time, date)
        VALUES ('$r_uid','$r_roll_no','$r_center_id','$r_characters_typed','$r_correct_words',
                '$r_incorrect_words','$r_wpm','$r_accuracy','$r_time','$date')");
}

if ($is_completed) {
    $mysqli->query("UPDATE users SET tested='1', logged_once='0', allow_retest='0' WHERE id='$r_uid'");
} else {
    // Keep candidate locked as already logged until admin/incharge performs reset after inquiry.
    $mysqli->query("UPDATE users SET tested='0', logged_once='1' WHERE id='$r_uid'");
}

// Remove from live candidates
$mysqli->query("UPDATE live_candidates SET finished_at=NOW() WHERE user_id='$r_uid' AND finished_at IS NULL");

echo json_encode(["status" => "done", "completion" => $is_completed ? 'completed' : 'abandoned']);
?>
