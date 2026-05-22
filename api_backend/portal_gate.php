<?php

function portal_setting_enabled($mysqli, $key, $default = 1) {
    $key = $mysqli->real_escape_string(trim((string)$key));
    if ($key === '') return $default ? 1 : 0;

    $q = $mysqli->query("SELECT svalue FROM app_settings WHERE skey='$key' LIMIT 1");
    if ($q && $q->num_rows > 0) {
        $row = $q->fetch_assoc();
        return intval($row['svalue']) === 1 ? 1 : 0;
    }

    return $default ? 1 : 0;
}

function portal_require_enabled($mysqli, $settingKey, $fallback = 1) {
    if (portal_setting_enabled($mysqli, $settingKey, $fallback)) {
        return true;
    }

    // Log denial for diagnostics (file created if not present)
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $now = date('Y-m-d H:i:s');
    $msg = "[$now] portal_gate: access denied for setting=$settingKey ip=$ip\n";
    @error_log($msg, 3, __DIR__ . '/portal_gate_denied.log');

    http_response_code(200);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Exam Expired</title><style>html,body{height:100%;margin:0}body{display:flex;align-items:center;justify-content:center;font-family:Arial,sans-serif;background:#f4f6fb;color:#1f2937}.box{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:28px 32px;box-shadow:0 10px 30px rgba(0,0,0,.08);text-align:center}.title{font-size:28px;font-weight:700}</style></head><body><div class="box"><div class="title">Exam Expired</div></div></body></html>';
    exit;
}