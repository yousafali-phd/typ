<?php
session_start();
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("Referrer-Policy: same-origin");
require_once("../mysqli.php");
mysqli_report(MYSQLI_REPORT_OFF);
require_once(__DIR__ . '/../portal_gate.php');

$action = $_GET['action'] ?? '';

if (empty($_SESSION['incharge_id']) || empty($_SESSION['incharge_center_id'])) {
    header("Content-Type: application/json");
    http_response_code(401);
    echo json_encode(["status" => "unauthorized"]);
    exit;
}

$center_id = intval($_SESSION['incharge_center_id']);

if ($action !== 'logout' && !portal_setting_enabled($mysqli, 'incharge_portal_enabled', 1)) {
    header("Content-Type: application/json");
    http_response_code(403);
    session_unset();
    session_destroy();
    echo json_encode(["status" => "portal_disabled", "message" => "Center Incharge portal is disabled by admin."]);
    exit;
}

if ($action !== 'export_results') {
    header("Content-Type: application/json");
}

switch ($action) {
    case 'me':
        $id = intval($_SESSION['incharge_id']);
        $q = $mysqli->query("SELECT ci.id, ci.username, ci.full_name, ci.center_id, c.name as center_name FROM center_incharges ci LEFT JOIN centers c ON c.id=ci.center_id WHERE ci.id='$id' LIMIT 1");
        $row = $q ? $q->fetch_assoc() : null;
        echo json_encode(["status" => "ok", "user" => $row]);
        break;

    case 'dashboard':
                $candidates = $mysqli->query("SELECT u.*, c.name as center_name, tc.name as type_name,
                                                            EXISTS(
                                                                SELECT 1
                                                                FROM live_candidates lc
                                                                WHERE lc.user_id=u.id
                                                                    AND lc.finished_at IS NULL
                                                                    AND lc.started_at > DATE_SUB(NOW(), INTERVAL 4 HOUR)
                                                            ) as has_live_attempt
                                                            FROM users u
                                                            LEFT JOIN centers c ON c.id=u.center_id
                                                            LEFT JOIN type_codes tc ON tc.code=u.type
                                                            WHERE u.center_id='$center_id'
                                                            ORDER BY u.roll_no");
        $cand = [];
        while ($r = $candidates->fetch_assoc()) $cand[] = $r;

                $results = $mysqli->query("SELECT r.*, u.name, u.cnic, u.wpm_required,
                                                                    CASE
                                                                        WHEN COALESCE(u.wpm_required, 0) > 0 AND r.wpm >= u.wpm_required THEN 'Passed'
                                                                        WHEN COALESCE(u.wpm_required, 0) > 0 THEN 'Failed'
                                                                        ELSE '—'
                                                                    END as status
                                                                    FROM results r
                                                                    LEFT JOIN users u ON u.id=r.uid
                                                                    WHERE (r.center_id='$center_id' OR (r.center_id IS NULL AND u.center_id='$center_id'))
                                                                    ORDER BY r.date DESC LIMIT 200");
        $resRows = [];
        while ($r = $results->fetch_assoc()) $resRows[] = $r;

        $sessions = $mysqli->query("SELECT ls.*, c.name as center_name FROM lab_sessions ls LEFT JOIN centers c ON c.id=ls.center_id WHERE ls.center_id='$center_id' ORDER BY ls.created_at DESC LIMIT 50");
        $sess = [];
        while ($r = $sessions->fetch_assoc()) $sess[] = $r;

        $active_session = null;
        $active_q = $mysqli->query("SELECT id, center_id, session_password, status, created_at, expires_at FROM lab_sessions WHERE center_id='$center_id' AND status='active' ORDER BY id DESC LIMIT 1");
        if (!$active_q) {
            $active_q = $mysqli->query("SELECT id, center_id, session_password, status, created_at, NULL as expires_at FROM lab_sessions WHERE center_id='$center_id' AND status='active' ORDER BY id DESC LIMIT 1");
        }
        if ($active_q && $active_q->num_rows > 0) {
            $active_session = $active_q->fetch_assoc();
        }

        $stats = $mysqli->query("SELECT COUNT(*) as total, SUM(tested) as tested, SUM(logged_once) as logged,
                    SUM(CASE WHEN tested='1' AND r.wpm >= COALESCE(wpm_required,0) AND COALESCE(wpm_required,0) > 0 THEN 1 ELSE 0 END) as passed,
                    SUM(CASE WHEN tested='1' AND r.wpm < COALESCE(wpm_required,0) AND COALESCE(wpm_required,0) > 0 THEN 1 ELSE 0 END) as failed
                    FROM users u
                    LEFT JOIN results r ON r.uid=u.id
                    WHERE u.center_id='$center_id'")->fetch_assoc();

        $centers = [];
        $centers_q = $mysqli->query("SELECT id, name FROM centers ORDER BY name");
        if ($centers_q) {
            while ($r = $centers_q->fetch_assoc()) $centers[] = $r;
        }

        echo json_encode([
            "status" => "ok",
            "center_id" => $center_id,
            "stats" => $stats,
            "candidates" => $cand,
            "results" => $resRows,
            "sessions" => $sess,
            "active_session" => $active_session,
            "centers" => $centers
        ]);
        break;

    case 'create_session':
        $password = str_pad(rand(100000, 999999), 6, "0", STR_PAD_LEFT);
        $mysqli->query("UPDATE lab_sessions SET status='expired' WHERE center_id='$center_id' AND status='active'");
        $ok = $mysqli->query("INSERT INTO lab_sessions (center_id, session_password, status, created_at) VALUES ('$center_id', '$password', 'active', NOW())");
        if (!$ok) {
            echo json_encode(["status" => "fail", "message" => "Could not create session password"]);
            break;
        }
        echo json_encode(["status" => "ok", "password" => $password]);
        break;

    case 'expire_session':
        $raw = json_decode(file_get_contents("php://input"));
        $session_id = intval($raw->id ?? 0);
        if ($session_id <= 0) {
            echo json_encode(["status" => "fail", "message" => "Invalid session id"]);
            break;
        }
        $ok = $mysqli->query("UPDATE lab_sessions SET status='expired' WHERE id='$session_id' AND center_id='$center_id'");
        if (!$ok || $mysqli->affected_rows < 1) {
            echo json_encode(["status" => "fail", "message" => "Session not found for your center"]);
            break;
        }
        echo json_encode(["status" => "ok"]);
        break;

    case 'allow_retest':
        $raw = json_decode(file_get_contents("php://input"));
        $user_id = intval($raw->user_id ?? 0);
        $reason = trim($raw->reason ?? '');
        if ($user_id <= 0 || strlen($reason) < 5) {
            echo json_encode(["status" => "fail", "message" => "Candidate and valid reason are required"]);
            break;
        }

        $table_q = $mysqli->query("SHOW TABLES LIKE 'retest_logs'");
        if (!$table_q || $table_q->num_rows === 0) {
            echo json_encode(["status" => "fail", "message" => "Retest log table is missing. Run update_database.sql first."]);
            break;
        }

        $cand_q = $mysqli->query("SELECT id FROM users WHERE id='$user_id' AND center_id='$center_id' AND type > 0 LIMIT 1");
        if (!$cand_q || $cand_q->num_rows === 0) {
            echo json_encode(["status" => "fail", "message" => "Candidate not found for your center"]);
            break;
        }

        $reason_esc = $mysqli->real_escape_string($reason);
        $incharge_id = intval($_SESSION['incharge_id']);

        $mysqli->query("UPDATE users SET allow_retest='1', tested='0', logged_once='0' WHERE id='$user_id' AND center_id='$center_id'");
        $mysqli->query("INSERT INTO retest_logs (user_id, center_id, incharge_id, reason, allowed_at) VALUES ('$user_id', '$center_id', '$incharge_id', '$reason_esc', NOW())");

        echo json_encode(["status" => "ok"]);
        break;

    case 'unlock_candidate':
        $raw = json_decode(file_get_contents("php://input"));
        $user_id = intval($raw->user_id ?? 0);
        if ($user_id <= 0) {
            echo json_encode(["status" => "fail", "message" => "Invalid candidate id"]);
            break;
        }

        $cand_q = $mysqli->query("SELECT id, tested, logged_once FROM users WHERE id='$user_id' AND center_id='$center_id' AND type > 0 LIMIT 1");
        if (!$cand_q || $cand_q->num_rows === 0) {
            echo json_encode(["status" => "fail", "message" => "Candidate not found for your center"]);
            break;
        }

        $cand = $cand_q->fetch_assoc();
        if ($cand['tested'] == '1') {
            echo json_encode(["status" => "fail", "message" => "Candidate already completed test. Use retest workflow instead."]);
            break;
        }

        if ($cand['logged_once'] != '1') {
            echo json_encode(["status" => "fail", "message" => "Candidate is not locked."]);
            break;
        }

        $mysqli->query("UPDATE users SET logged_once='0' WHERE id='$user_id' AND center_id='$center_id'");
        $mysqli->query("UPDATE live_candidates SET finished_at=NOW() WHERE user_id='$user_id' AND center_id='$center_id' AND finished_at IS NULL");

        echo json_encode(["status" => "ok"]);
        break;

    case 'change_candidate_center':
        echo json_encode(["status" => "fail", "message" => "Center change is admin-only"]);
        break;

    case 'get_center_change_logs':
        $res = $mysqli->query("SELECT l.*, u.name, u.roll_no, u.cnic, co.name as old_center_name, cn.name as new_center_name
                              FROM candidate_center_change_logs l
                              LEFT JOIN users u ON u.id=l.user_id
                              LEFT JOIN centers co ON co.id=l.old_center_id
                              LEFT JOIN centers cn ON cn.id=l.new_center_id
                              WHERE l.old_center_id='$center_id' OR l.new_center_id='$center_id'
                              ORDER BY l.changed_at DESC LIMIT 200");
        $rows = [];
        if ($res) {
            while ($r = $res->fetch_assoc()) $rows[] = $r;
        }
        echo json_encode($rows);
        break;

    case 'export_results':
        $res = $mysqli->query("SELECT r.id, r.roll_no, u.name, u.cnic, r.correct_words, r.incorrect_words, r.wpm, r.accuracy, r.time, r.date,
                              CASE
                                  WHEN COALESCE(u.wpm_required, 0) > 0 AND r.wpm >= u.wpm_required THEN 'Passed'
                                  WHEN COALESCE(u.wpm_required, 0) > 0 THEN 'Failed'
                                  ELSE '—'
                              END as status
                              FROM results r
                              LEFT JOIN users u ON u.id=r.uid
                              WHERE r.center_id='$center_id'
                              ORDER BY r.date DESC");

        header("Content-Type: text/csv; charset=utf-8");
        header("Content-Disposition: attachment; filename=center_" . $center_id . "_results_" . date('Ymd_His') . ".csv");

        $out = fopen("php://output", "w");
        fputcsv($out, ["Result ID", "Roll No", "Candidate Name", "CNIC", "Status", "Correct Words", "Incorrect Words", "WPM", "Accuracy", "Time", "Date"]);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                fputcsv($out, [
                    $row['id'],
                    $row['roll_no'],
                    $row['name'],
                    $row['cnic'],
                    $row['status'],
                    $row['correct_words'],
                    $row['incorrect_words'],
                    $row['wpm'],
                    $row['accuracy'],
                    $row['time'],
                    $row['date']
                ]);
            }
        }
        fclose($out);
        exit;

    case 'logout':
        session_unset();
        session_destroy();
        echo json_encode(["status" => "ok"]);
        break;

    default:
        echo json_encode(["status" => "fail", "message" => "Unknown action"]);
}
?>