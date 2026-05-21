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

    http_response_code(404);
    exit;
}