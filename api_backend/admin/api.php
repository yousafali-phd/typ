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
if (($action ?? '') !== 'export_results') {
    header("Content-Type: application/json");
}
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("Referrer-Policy: same-origin");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");
require_once("../../api_backend/mysqli.php");
mysqli_report(MYSQLI_REPORT_OFF);

$action = $_GET['action'] ?? '';
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

$stateChangingActions = [
    'logout',
    'sync_production',
    'update_settings',
    'add_center',
    'add_type_code',
    'delete_type_code',
    'add_incharge',
    'assign_incharge_center',
    'toggle_incharge',
    'add_candidate',
    'import_candidates',
    'reset_candidate',
    'allow_retest',
    'delete_candidate',
    'change_candidate_type',
    'change_candidate_center',
    'add_paragraph',
    'delete_paragraph',
    'create_session',
    'expire_session',
];

if (in_array($action, $stateChangingActions, true) && $method !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "fail", "message" => "Method not allowed"]);
    exit;
}

// Session Validation at the beginning
if ($action !== 'logout' && empty($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(["status" => "unauthorized"]);
    exit;
}

if ($action === 'me') {
    $admin_id = intval($_SESSION['admin_id'] ?? 0);
    $q = $mysqli->query("SELECT id, username, full_name, is_active FROM admins WHERE id='$admin_id' AND is_active='1' LIMIT 1");
    $admin = $q && $q->num_rows > 0 ? $q->fetch_assoc() : null;
    if (!$admin) {
        session_unset();
        session_destroy();
        http_response_code(401);
        echo json_encode(["status" => "unauthorized"]);
        exit;
    }
    echo json_encode(["status" => "ok", "admin" => $admin]);
    exit;
}

function req_body() {
    $raw = file_get_contents("php://input");
    if ($raw === false || trim($raw) === '') {
        return null;
    }
    $decoded = json_decode($raw);
    return is_object($decoded) ? $decoded : null;
}

function type_code_exists($code, $activeOnly = true) {
    global $mysqli;
    $code = intval($code);
    if ($code <= 0) return false;
    $where = $activeOnly ? "AND is_active='1'" : "";
    $q = $mysqli->query("SELECT id FROM type_codes WHERE code='$code' $where LIMIT 1");
    return $q && $q->num_rows > 0;
}

function default_type_code() {
    global $mysqli;
    $q = $mysqli->query("SELECT code FROM type_codes WHERE is_active='1' ORDER BY code LIMIT 1");
    if ($q && ($r = $q->fetch_assoc())) {
        return intval($r['code']);
    }
    return 1;
}

function type_name_by_code($code) {
    global $mysqli;
    $code = intval($code);
    if ($code <= 0) return '';
    $q = $mysqli->query("SELECT name FROM type_codes WHERE code='$code' ORDER BY is_active DESC, id ASC LIMIT 1");
    if ($q && ($r = $q->fetch_assoc())) {
        return trim((string)$r['name']);
    }
    return '';
}

function users_column_exists($column) {
    global $mysqli;
    static $columns = null;

    if ($columns === null) {
        $columns = [];
        $q = $mysqli->query("SHOW COLUMNS FROM users");
        if ($q) {
            while ($r = $q->fetch_assoc()) {
                $columns[strtolower($r['Field'])] = true;
            }
        }
    }

    return isset($columns[strtolower((string)$column)]);
}

function row_value($row, $key, $default = '') {
    if (is_array($row) && array_key_exists($key, $row)) {
        return $row[$key];
    }
    if (is_object($row) && isset($row->$key)) {
        return $row->$key;
    }
    return $default;
}

function find_center_by_id($id) {
    global $mysqli;
    $id = intval($id);
    if ($id <= 0) return null;
    $q = $mysqli->query("SELECT id, name, code FROM centers WHERE id='$id' LIMIT 1");
    return ($q && $q->num_rows > 0) ? $q->fetch_assoc() : null;
}

function find_center_by_code($code) {
    global $mysqli;
    $code = trim((string)$code);
    if ($code === '') return null;
    $esc = $mysqli->real_escape_string($code);
    $q = $mysqli->query("SELECT id, name, code FROM centers WHERE code='$esc' OR UPPER(code)=UPPER('$esc') ORDER BY id LIMIT 1");
    return ($q && $q->num_rows > 0) ? $q->fetch_assoc() : null;
}

function find_center_by_name($name) {
    global $mysqli;
    $name = trim((string)$name);
    if ($name === '') return null;

    $esc = $mysqli->real_escape_string($name);
    $q = $mysqli->query("SELECT id, name, code FROM centers WHERE name='$esc' OR LOWER(name)=LOWER('$esc') ORDER BY id LIMIT 1");
    if ($q && $q->num_rows > 0) {
        return $q->fetch_assoc();
    }

    $compact = preg_replace('/\s+/', '', strtolower($name));
    $compactEsc = $mysqli->real_escape_string($compact);
    $q2 = $mysqli->query("SELECT id, name, code FROM centers WHERE REPLACE(LOWER(name), ' ', '')='$compactEsc' ORDER BY id LIMIT 1");
    return ($q2 && $q2->num_rows > 0) ? $q2->fetch_assoc() : null;
}

function resolve_center_assignment($row, $defaultCenterId = 0) {
    $center = null;

    $centerId = intval(row_value($row, 'center_id', 0));
    if ($centerId <= 0) {
        $centerId = intval($defaultCenterId);
    }
    if ($centerId > 0) {
        $center = find_center_by_id($centerId);
    }

    if (!$center) {
        $centerCode = trim((string)row_value($row, 'center_code', row_value($row, 'code', '')));
        if ($centerCode === '') {
            $centerCode = trim((string)row_value($row, 'center', ''));
        }
        if ($centerCode !== '') {
            $center = find_center_by_code($centerCode);
        }
    }

    if (!$center) {
        $centerName = trim((string)row_value($row, 'center_name', row_value($row, 'center_full_name', '')));
        if ($centerName === '') {
            $centerName = trim((string)row_value($row, 'center', ''));
        }
        if ($centerName !== '') {
            $center = find_center_by_name($centerName);
        }
    }

    return $center;
}

function typing_root() {
    return realpath(__DIR__ . '/../../');
}

function production_root() {
    $root = typing_root();
    return $root ? $root . DIRECTORY_SEPARATOR . 'production' : null;
}

function normalize_path($path) {
    return str_replace('\\', '/', $path);
}

function ensure_directory($path) {
    if (!is_dir($path)) {
        @mkdir($path, 0777, true);
    }
    return is_dir($path);
}

function delete_directory_contents($path) {
    if (!is_dir($path)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $target = $item->getPathname();
        if ($item->isDir()) {
            @rmdir($target);
        } else {
            @unlink($target);
        }
    }
}

function should_skip_sync($relativePath) {
    $relativePath = normalize_path($relativePath);
    $base = basename($relativePath);
    if ($relativePath === '' || $relativePath === '.' || $relativePath === '..') {
        return true;
    }
    if (preg_match('#(^|/)(production|\.git|\.vscode|node_modules|memories)(/|$)#i', $relativePath)) {
        return true;
    }
    if (preg_match('#\.(sql|md|csv|xlsx|log)$#i', $base)) {
        return true;
    }
    if (preg_match('#^(test_|verify_).+\.php$#i', $base)) {
        return true;
    }
    if (in_array($base, ['test_login.php', 'verify_setup.php', 'Documentationfix', 'FIX_SUMMARY.md', 'CANDIDATES_PAGE_FIX.md', 'import users.csv'], true)) {
        return true;
    }
    return false;
}

function sync_tree($source, $destination, &$stats) {
    if (!file_exists($source)) {
        $stats['warnings'][] = 'Missing source: ' . normalize_path($source);
        return;
    }

    if (is_file($source)) {
        $relative = basename($source);
        if (should_skip_sync($relative)) {
            $stats['skipped'][] = normalize_path($source);
            return;
        }
        ensure_directory(dirname($destination));
        if (@copy($source, $destination)) {
            $stats['copied'][] = normalize_path($destination);
        } else {
            $stats['errors'][] = 'Failed to copy ' . normalize_path($source);
        }
        return;
    }

    ensure_directory($destination);
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $sourcePath = $item->getPathname();
        $relativePath = substr($sourcePath, strlen($source) + 1);
        if ($relativePath === false) {
            $relativePath = '';
        }
        if (should_skip_sync($relativePath)) {
            continue;
        }
        $targetPath = $destination . DIRECTORY_SEPARATOR . $relativePath;
        if ($item->isDir()) {
            ensure_directory($targetPath);
            continue;
        }
        ensure_directory(dirname($targetPath));
        if (@copy($sourcePath, $targetPath)) {
            $stats['copied'][] = normalize_path($targetPath);
        } else {
            $stats['errors'][] = 'Failed to copy ' . normalize_path($sourcePath);
        }
    }
}

function production_htaccess_template() {
    return <<<TXT
Options -Indexes

<IfModule mod_headers.c>
  Header always set X-Content-Type-Options "nosniff"
  Header always set X-Frame-Options "SAMEORIGIN"
  Header always set Referrer-Policy "same-origin"
  Header always set X-XSS-Protection "1; mode=block"
  Header always set Permissions-Policy "camera=(), microphone=(), geolocation=()"
</IfModule>

<FilesMatch "^\.">
  Require all denied
</FilesMatch>

<FilesMatch "\.(sql|md|csv|xlsx|log)$">
  Require all denied
</FilesMatch>
TXT;
}

function security_audit_report() {
    global $mysqli;

    $checks = [];
    $add = function($name, $status, $message, $meta = null) use (&$checks) {
        $checks[] = [
            'name' => $name,
            'status' => $status,
            'message' => $message,
            'meta' => $meta,
        ];
    };

    $root = typing_root();
    $production = production_root();

    if (!$root || !$production) {
        $add('Project paths', 'fail', 'Unable to resolve project root');
    } else {
        $add('Project paths', 'pass', 'Project and production paths resolved');
    }

    $htaccessPath = $production ? $production . DIRECTORY_SEPARATOR . '.htaccess' : null;
    $htaccessRequired = ['Options -Indexes', 'X-Content-Type-Options', 'X-Frame-Options', 'Referrer-Policy', 'FilesMatch "\\.(sql|md|csv|xlsx|log)$"'];
    if ($htaccessPath && file_exists($htaccessPath)) {
        $htaccess = file_get_contents($htaccessPath);
        $missing = [];
        foreach ($htaccessRequired as $needle) {
            if (strpos($htaccess, $needle) === false) {
                $missing[] = $needle;
            }
        }
        if ($missing) {
            $add('Production .htaccess', 'fail', 'Missing security directives', $missing);
        } else {
            $add('Production .htaccess', 'pass', 'Hardening directives are present');
        }
    } else {
        $add('Production .htaccess', 'fail', 'production/.htaccess is missing');
    }

    $publicApiFiles = [
        'api_backend/login.php',
        'api_backend/get_paragraph.php',
        'api_backend/save_result.php',
        'api_backend/admin/admin_login.php',
        'api_backend/admin/api.php',
        'api_backend/center/login.php',
        'api_backend/center/api.php',
    ];
    foreach ($publicApiFiles as $relativeFile) {
        $path = $root . DIRECTORY_SEPARATOR . $relativeFile;
        if (!file_exists($path)) {
            $add($relativeFile, 'fail', 'File is missing');
            continue;
        }
        $content = file_get_contents($path);
        $needs = ['X-Content-Type-Options', 'X-Frame-Options', 'Referrer-Policy'];
        $missing = [];
        foreach ($needs as $needle) {
            if (strpos($content, $needle) === false) {
                $missing[] = $needle;
            }
        }
        if ($missing) {
            $add($relativeFile, 'fail', 'Missing security headers', $missing);
        } else {
            $add($relativeFile, 'pass', 'Security headers present');
        }
    }

    $adminApi = $root . DIRECTORY_SEPARATOR . 'api_backend/admin/api.php';
    $adminLogin = $root . DIRECTORY_SEPARATOR . 'api_backend/admin/admin_login.php';
    if (file_exists($adminApi) && strpos(file_get_contents($adminApi), '$_SESSION[') !== false && strpos(file_get_contents($adminApi), 'session_start()') !== false) {
        $add('Admin API session gate', 'pass', 'Admin API uses session-based authorization');
    } else {
        $add('Admin API session gate', 'fail', 'Admin API is not session-protected');
    }
    if (file_exists($adminLogin) && strpos(file_get_contents($adminLogin), 'session_regenerate_id(true)') !== false) {
        $add('Admin login session regen', 'pass', 'Admin login renews the session ID');
    } else {
        $add('Admin login session regen', 'fail', 'Admin login does not regenerate the session ID');
    }

    $bundleChecks = [];
    if ($production && is_dir($production)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($production, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                continue;
            }
            $path = normalize_path($item->getPathname());
            $base = strtolower($item->getFilename());
            if (preg_match('#\.(sql|md|csv|xlsx|log)$#i', $base) || preg_match('#/(test_|verify_).+\.php$#i', $path) || in_array($base, ['test_login.php', 'verify_setup.php', 'documentationfix', 'fix_summary.md', 'candidates_page_fix.md'], true)) {
                $bundleChecks[] = $path;
            }
        }
        if ($bundleChecks) {
            $add('Production bundle contents', 'fail', 'Disallowed files found in production', array_slice($bundleChecks, 0, 20));
        } else {
            $add('Production bundle contents', 'pass', 'No docs, CSV, SQL, or test files in production');
        }
    }

    $requiredTables = ['admins', 'centers', 'users', 'paragraphs', 'lab_sessions', 'results', 'live_candidates', 'app_settings', 'center_incharges', 'retest_logs', 'candidate_center_change_logs', 'type_codes'];
    $missingTables = [];
    foreach ($requiredTables as $table) {
        $q = $mysqli->query("SHOW TABLES LIKE '$table'");
        if (!$q || $q->num_rows === 0) {
            $missingTables[] = $table;
        }
    }
    if ($missingTables) {
        $add('Database tables', 'fail', 'Missing required tables', $missingTables);
    } else {
        $add('Database tables', 'pass', 'Required tables are present');
    }

    $requiredColumns = [
        'users' => ['center_id', 'center_code', 'center_name', 'image_url', 'post', 'type', 'tested', 'logged_once', 'allow_retest', 'wpm_required', 'last_login'],
        'results' => ['center_id', 'uid', 'wpm', 'accuracy', 'date'],
        'paragraphs' => ['type_code', 'type_name'],
        'center_incharges' => ['username', 'password', 'full_name', 'center_id', 'is_active'],
        'lab_sessions' => ['center_id', 'session_password', 'status', 'created_at'],
    ];
    $missingColumns = [];
    foreach ($requiredColumns as $table => $columns) {
        foreach ($columns as $column) {
            $q = $mysqli->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
            if (!$q || $q->num_rows === 0) {
                $missingColumns[] = $table . '.' . $column;
            }
        }
    }
    if ($missingColumns) {
        $add('Database columns', 'fail', 'Missing required columns', $missingColumns);
    } else {
        $add('Database columns', 'pass', 'Required columns are present');
    }

    $mysqliFile = $root . DIRECTORY_SEPARATOR . 'api_backend/mysqli.php';
    if (file_exists($mysqliFile) && strpos(file_get_contents($mysqliFile), "getenv('DB_HOST')") !== false) {
        $add('DB bootstrap', 'pass', 'Database bootstrap supports environment credentials');
    } else {
        $add('DB bootstrap', 'warn', 'Database bootstrap still uses hardcoded credentials');
    }

    $pass = 0;
    $warn = 0;
    $fail = 0;
    foreach ($checks as $check) {
        if ($check['status'] === 'pass') {
            $pass++;
        } elseif ($check['status'] === 'warn') {
            $warn++;
        } else {
            $fail++;
        }
    }

    return [
        'ready' => $fail === 0,
        'summary' => [
            'passed' => $pass,
            'warnings' => $warn,
            'failed' => $fail,
            'total' => count($checks),
        ],
        'checks' => $checks,
    ];
}

switch ($action) {

    case 'logout':
        session_unset();
        session_destroy();
        echo json_encode(["status" => "ok"]);
        break;

    case 'audit_security':
        echo json_encode(array_merge(["status" => "ok"], security_audit_report()));
        break;

    case 'sync_production':
        $audit = security_audit_report();
        if (!$audit['ready']) {
            echo json_encode([
                "status" => "fail",
                "message" => "Security audit failed. Fix the failing checks before syncing.",
                "audit" => $audit,
            ]);
            break;
        }

        $root = typing_root();
        $production = production_root();
        if (!$root || !$production) {
            echo json_encode(["status" => "fail", "message" => "Could not resolve project paths"]);
            break;
        }

        ensure_directory($production);
        delete_directory_contents($production);
        ensure_directory($production);

        $manifest = [
            ['source' => 'index.html', 'dest' => 'index.html'],
            ['source' => 'logo.png', 'dest' => 'logo.png'],
            ['source' => 'admin', 'dest' => 'admin'],
            ['source' => 'center', 'dest' => 'center'],
            ['source' => 'api_backend', 'dest' => 'api_backend'],
            ['source' => 'css', 'dest' => 'css'],
            ['source' => 'fonts', 'dest' => 'fonts'],
            ['source' => 'img', 'dest' => 'img'],
            ['source' => 'js', 'dest' => 'js'],
        ];

        $stats = [
            'copied' => [],
            'skipped' => [],
            'warnings' => [],
            'errors' => [],
        ];

        foreach ($manifest as $entry) {
            $source = $root . DIRECTORY_SEPARATOR . $entry['source'];
            $destination = $production . DIRECTORY_SEPARATOR . $entry['dest'];
            sync_tree($source, $destination, $stats);
        }

        $htaccessPath = $production . DIRECTORY_SEPARATOR . '.htaccess';
        if (@file_put_contents($htaccessPath, production_htaccess_template()) === false) {
            $stats['errors'][] = 'Failed to write production/.htaccess';
        }

        $auditAfter = security_audit_report();
        echo json_encode([
            "status" => count($stats['errors']) === 0 ? "ok" : "fail",
            "copied" => count($stats['copied']),
            "skipped" => count($stats['skipped']),
            "warnings" => $stats['warnings'],
            "errors" => $stats['errors'],
            "audit" => $auditAfter,
            "synced_at" => date('c'),
        ]);
        break;

    // -- SETTINGS ----------------------------------------------
    case 'get_settings':
        $minutes = 1;
        $q = $mysqli->query("SELECT svalue FROM app_settings WHERE skey='default_test_minutes' LIMIT 1");
        if ($q && $q->num_rows > 0) {
            $row = $q->fetch_assoc();
            $m = intval($row['svalue']);
            if ($m > 0 && $m <= 30) $minutes = $m;
        }
        echo json_encode(["default_test_minutes" => $minutes]);
        break;

    case 'update_settings':
        $d = req_body();
        $minutes = intval($d->default_test_minutes ?? 1);
        if ($minutes < 1 || $minutes > 30) {
            echo json_encode(["status" => "fail", "message" => "Duration must be 1-30 minutes"]);
            break;
        }
        $mysqli->query("INSERT INTO app_settings (skey, svalue) VALUES ('default_test_minutes', '$minutes') ON DUPLICATE KEY UPDATE svalue=VALUES(svalue)");
        echo json_encode(["status" => "ok"]);
        break;

    // -- CENTERS ----------------------------------------------
    case 'get_centers':
        $res = $mysqli->query("SELECT * FROM centers ORDER BY name");
        $centers = [];
        while ($r = $res->fetch_assoc()) $centers[] = $r;
        echo json_encode($centers);
        break;

    case 'add_center':
        $d    = req_body();
        $name = $mysqli->real_escape_string(trim($d->name));
        $code = $mysqli->real_escape_string(trim($d->code));
        $mysqli->query("INSERT INTO centers (name, code) VALUES ('$name','$code')");
        echo json_encode(["status" => "ok", "id" => $mysqli->insert_id]);
        break;

    // -- TYPE CODES -------------------------------------------
    case 'get_type_codes':
        $res = $mysqli->query("SELECT * FROM type_codes ORDER BY code");
        $rows = [];
        if ($res) {
            while ($r = $res->fetch_assoc()) $rows[] = $r;
        }
        echo json_encode($rows);
        break;

    case 'add_type_code':
        $d = req_body();
        $code = intval($d->code ?? 0);
        $name = $mysqli->real_escape_string(trim($d->name ?? ''));
        $description = $mysqli->real_escape_string(trim($d->description ?? ''));
        $is_active = intval($d->is_active ?? 1) ? 1 : 0;

        if ($code <= 0 || $name === '') {
            echo json_encode(["status" => "fail", "message" => "Type code and name are required"]);
            break;
        }

        $exists = $mysqli->query("SELECT id FROM type_codes WHERE code='$code' LIMIT 1");
        if ($exists && $exists->num_rows > 0) {
            echo json_encode(["status" => "fail", "message" => "Type code already exists"]);
            break;
        }

        $mysqli->query("INSERT INTO type_codes (code, name, description, is_active) VALUES ('$code', '$name', '$description', '$is_active')");
        echo json_encode(["status" => "ok", "id" => $mysqli->insert_id]);
        break;

    case 'delete_type_code':
        $d = req_body();
        $id = intval($d->id ?? 0);
        if ($id <= 0) {
            echo json_encode(["status" => "fail", "message" => "Invalid type code"]);
            break;
        }
        $mysqli->query("DELETE FROM type_codes WHERE id='$id'");
        echo json_encode(["status" => "ok"]);
        break;

    // -- CENTER INCHARGES -------------------------------------
    case 'get_incharges':
        $res = $mysqli->query("SELECT ci.id, ci.username, ci.full_name, ci.center_id, ci.is_active, ci.created_at, c.name as center_name FROM center_incharges ci LEFT JOIN centers c ON c.id=ci.center_id ORDER BY ci.id DESC");
        $rows = [];
        if ($res) {
            while ($r = $res->fetch_assoc()) $rows[] = $r;
        }
        echo json_encode($rows);
        break;

    case 'add_incharge':
        $d = req_body();
        $username = $mysqli->real_escape_string(trim($d->username ?? ''));
        $full_name = $mysqli->real_escape_string(trim($d->full_name ?? ''));
        $password_raw = trim($d->password ?? '');
        $center_id = intval($d->center_id ?? 0);

        if (!$username || !$full_name || strlen($password_raw) < 6) {
            echo json_encode(["status" => "fail", "message" => "Username, full name and 6+ char password are required"]);
            break;
        }

        $hash = $mysqli->real_escape_string(password_hash($password_raw, PASSWORD_DEFAULT));
        $cid = $center_id > 0 ? "'$center_id'" : "NULL";
        $ok = $mysqli->query("INSERT INTO center_incharges (username, password, full_name, center_id, is_active) VALUES ('$username', '$hash', '$full_name', $cid, 1)");

        if (!$ok) {
            echo json_encode(["status" => "fail", "message" => "Could not create incharge. Username may already exist."]);
            break;
        }
        echo json_encode(["status" => "ok", "id" => $mysqli->insert_id]);
        break;

    case 'assign_incharge_center':
        $d = req_body();
        $id = intval($d->id ?? 0);
        $center_id = intval($d->center_id ?? 0);
        if ($id <= 0 || $center_id <= 0) {
            echo json_encode(["status" => "fail", "message" => "Invalid incharge or center"]);
            break;
        }
        $mysqli->query("UPDATE center_incharges SET center_id='$center_id' WHERE id='$id'");
        echo json_encode(["status" => "ok"]);
        break;

    case 'toggle_incharge':
        $d = req_body();
        $id = intval($d->id ?? 0);
        $is_active = intval($d->is_active ?? 0) ? 1 : 0;
        if ($id <= 0) {
            echo json_encode(["status" => "fail", "message" => "Invalid incharge"]);
            break;
        }
        $mysqli->query("UPDATE center_incharges SET is_active='$is_active' WHERE id='$id'");
        echo json_encode(["status" => "ok"]);
        break;

    // -- CANDIDATES -------------------------------------------
    case 'get_candidates':
        $center = intval($_GET['center_id'] ?? 0);
        $where  = $center ? "WHERE u.center_id='$center'" : "";
                $res    = $mysqli->query("SELECT u.*, c.name as center_name, c.code as center_code, tc.name as type_name,
                                                    EXISTS(
                                                        SELECT 1
                                                        FROM live_candidates lc
                                                        WHERE lc.user_id=u.id
                                                            AND lc.finished_at IS NULL
                                                            AND lc.started_at > DATE_SUB(NOW(), INTERVAL 4 HOUR)
                                                    ) as has_live_attempt
                                                    FROM users u
                                                    LEFT JOIN centers c ON u.center_id=c.id
                                                    LEFT JOIN type_codes tc ON tc.code=u.type
                                                    $where
                                                    ORDER BY u.roll_no");
        $rows   = [];
        while ($r = $res->fetch_assoc()) $rows[] = $r;
        echo json_encode($rows);
        break;

    case 'add_candidate':
        $d         = req_body();
        $name      = $mysqli->real_escape_string(trim($d->name));
        $cnic      = $mysqli->real_escape_string(trim($d->cnic));
        $roll_no   = $mysqli->real_escape_string(trim($d->roll_no));
        $post      = $mysqli->real_escape_string(trim($d->post ?? ''));
        $wpm_required = intval($d->wpm_required ?? 0);
        $image_url = $mysqli->real_escape_string(trim($d->image_url ?? ''));
        $type      = intval($d->type ?? 0);
        if ($type <= 0) $type = default_type_code();
        if (!type_code_exists($type, true)) $type = default_type_code();

        $center = resolve_center_assignment($d);
        if (!$center) {
            echo json_encode(["status" => "fail", "message" => "Center not found. Use center code or full center name from centers table."]);
            break;
        }

        $center_id = intval($center['id']);
        $center_code = $mysqli->real_escape_string(trim((string)($center['code'] ?? '')));
        $center_name = $mysqli->real_escape_string(trim((string)($center['name'] ?? '')));

        $columns = ['name', 'cnic', 'roll_no', 'center_id', 'type', 'tested', 'logged_once', 'allow_retest'];
        $values = ["'$name'", "'$cnic'", "'$roll_no'", "'$center_id'", "'$type'", "'0'", "'0'", "'0'"];
        if (users_column_exists('post')) {
            $columns[] = 'post';
            $values[] = "'$post'";
        }
        if (users_column_exists('wpm_required')) {
            $columns[] = 'wpm_required';
            $values[] = "'" . intval($wpm_required) . "'";
        }
        if (users_column_exists('center_code')) {
            $columns[] = 'center_code';
            $values[] = "'$center_code'";
        }
        if (users_column_exists('center_name')) {
            $columns[] = 'center_name';
            $values[] = "'$center_name'";
        }
        if (users_column_exists('image_url')) {
            $columns[] = 'image_url';
            $values[] = "'$image_url'";
        }

        $mysqli->query("INSERT INTO users (" . implode(',', $columns) . ") VALUES (" . implode(',', $values) . ")");
        echo json_encode(["status" => "ok", "id" => $mysqli->insert_id]);
        break;

    case 'import_candidates':
        $d    = req_body();
        $rows = $d->rows;
        $default_center_id = intval($d->default_center_id ?? 0);
        $ok   = 0;
        $skip = 0;
        $invalid_center = 0;

        $has_post = users_column_exists('post');
        $has_center_code = users_column_exists('center_code');
        $has_center_name = users_column_exists('center_name');
        $has_image_url = users_column_exists('image_url');

        foreach ($rows as $row) {
            $name      = $mysqli->real_escape_string(trim($row->name));
            $cnic      = $mysqli->real_escape_string(trim($row->cnic));
            $roll_no   = $mysqli->real_escape_string(trim($row->roll_no));
            $post      = $mysqli->real_escape_string(trim($row->post ?? ''));
            $wpm_required = intval($row->wpm_required ?? 0);
            $image_url = $mysqli->real_escape_string(trim($row->image_url ?? ''));
            $type      = intval($row->type ?? 0);
            if ($type <= 0) $type = default_type_code();
            if (!type_code_exists($type, true)) $type = default_type_code();

            $center = resolve_center_assignment($row, $default_center_id);
            if (!$center) {
                $skip++;
                $invalid_center++;
                continue;
            }

            $center_id = intval($center['id']);
            $center_code = $mysqli->real_escape_string(trim((string)($center['code'] ?? '')));
            $center_name = $mysqli->real_escape_string(trim((string)($center['name'] ?? '')));

            $columns = ['name', 'cnic', 'roll_no', 'center_id', 'type', 'tested', 'logged_once', 'allow_retest'];
            $values = ["'$name'", "'$cnic'", "'$roll_no'", "'$center_id'", "'$type'", "'0'", "'0'", "'0'"];

            if ($has_post) {
                $columns[] = 'post';
                $values[] = "'$post'";
            }
            if (users_column_exists('wpm_required')) {
                $columns[] = 'wpm_required';
                $values[] = "'" . intval($wpm_required) . "'";
            }
            if ($has_center_code) {
                $columns[] = 'center_code';
                $values[] = "'$center_code'";
            }
            if ($has_center_name) {
                $columns[] = 'center_name';
                $values[] = "'$center_name'";
            }
            if ($has_image_url) {
                $columns[] = 'image_url';
                $values[] = "'$image_url'";
            }

            $ins = $mysqli->query("INSERT IGNORE INTO users (" . implode(',', $columns) . ") VALUES (" . implode(',', $values) . ")");
            if ($ins && $mysqli->affected_rows > 0) $ok++;
            else $skip++;
        }
        echo json_encode(["status" => "ok", "inserted" => $ok, "skipped" => $skip, "invalid_center" => $invalid_center]);
        break;

    case 'reset_candidate':
        $d  = req_body();
        $id = intval($d->id ?? 0);
        if ($id <= 0) {
            echo json_encode(["status" => "fail", "message" => "Invalid candidate id"]);
            break;
        }
        $mysqli->query("UPDATE users SET tested='0', logged_once='0', allow_retest='0' WHERE id='$id'");
        echo json_encode(["status" => "ok"]);
        break;

    case 'allow_retest':
        $d  = req_body();
        $id = intval($d->id ?? 0);
        if ($id <= 0) {
            echo json_encode(["status" => "fail", "message" => "Invalid candidate id"]);
            break;
        }
        $mysqli->query("UPDATE users SET allow_retest='1' WHERE id='$id'");
        echo json_encode(["status" => "ok"]);
        break;

    case 'delete_candidate':
        $d  = req_body();
        $id = intval($d->id ?? 0);
        if ($id <= 0) {
            echo json_encode(["status" => "fail", "message" => "Invalid candidate id"]);
            break;
        }
        $mysqli->query("DELETE FROM users WHERE id='$id'");
        echo json_encode(["status" => "ok"]);
        break;

    case 'change_candidate_type':
        $d = req_body();
        $user_id = intval($d->user_id ?? 0);
        $new_type = intval($d->new_type ?? 0);

        if ($user_id <= 0 || $new_type <= 0) {
            echo json_encode(["status" => "fail", "message" => "Candidate and type are required"]);
            break;
        }

        $cand_q = $mysqli->query("SELECT id FROM users WHERE id='$user_id' AND type > 0 LIMIT 1");
        if (!$cand_q || $cand_q->num_rows === 0) {
            echo json_encode(["status" => "fail", "message" => "Candidate not found"]);
            break;
        }

        $type_q = $mysqli->query("SELECT id FROM type_codes WHERE code='$new_type' AND is_active='1' LIMIT 1");
        if (!$type_q || $type_q->num_rows === 0) {
            echo json_encode(["status" => "fail", "message" => "Type code not found or inactive"]);
            break;
        }

        $mysqli->query("UPDATE users SET type='$new_type' WHERE id='$user_id'");
        echo json_encode(["status" => "ok"]);
        break;

    case 'change_candidate_center':
        $d = req_body();
        $user_id = intval($d->user_id ?? 0);
        $new_center_id = intval($d->new_center_id ?? 0);
        $reason = trim($d->reason ?? '');

        if ($user_id <= 0 || $new_center_id <= 0 || strlen($reason) < 5) {
            echo json_encode(["status" => "fail", "message" => "Candidate, target center and reason are required"]);
            break;
        }

        $table_q = $mysqli->query("SHOW TABLES LIKE 'candidate_center_change_logs'");
        if (!$table_q || $table_q->num_rows === 0) {
            echo json_encode(["status" => "fail", "message" => "Center change log table is missing. Run update_database.sql first."]);
            break;
        }

        $cand_q = $mysqli->query("SELECT id, center_id, type FROM users WHERE id='$user_id' LIMIT 1");
        if (!$cand_q || $cand_q->num_rows === 0) {
            echo json_encode(["status" => "fail", "message" => "Candidate not found"]);
            break;
        }

        $cand = $cand_q->fetch_assoc();
        if (intval($cand['type']) <= 0) {
            echo json_encode(["status" => "fail", "message" => "Only candidate records can be moved"]);
            break;
        }

        $old_center_id = intval($cand['center_id']);
        if ($old_center_id === $new_center_id) {
            echo json_encode(["status" => "fail", "message" => "Candidate already belongs to selected center"]);
            break;
        }

        $center_q = $mysqli->query("SELECT id, name, code FROM centers WHERE id='$new_center_id' LIMIT 1");
        if (!$center_q || $center_q->num_rows === 0) {
            echo json_encode(["status" => "fail", "message" => "Target center does not exist"]);
            break;
        }

        $reason_esc = $mysqli->real_escape_string($reason);
        $new_center = $center_q->fetch_assoc();
        $set_parts = ["center_id='$new_center_id'"];
        if (users_column_exists('center_name')) {
            $new_center_name = $mysqli->real_escape_string(trim((string)($new_center['name'] ?? '')));
            $set_parts[] = "center_name='$new_center_name'";
        }
        if (users_column_exists('center_code')) {
            $new_center_code = $mysqli->real_escape_string(trim((string)($new_center['code'] ?? '')));
            $set_parts[] = "center_code='$new_center_code'";
        }

        $set_sql = implode(', ', $set_parts);
        $mysqli->query("UPDATE users SET $set_sql WHERE id='$user_id'");
        $mysqli->query("INSERT INTO candidate_center_change_logs (user_id, old_center_id, new_center_id, changed_by_role, changed_by_id, reason, changed_at) VALUES ('$user_id', '$old_center_id', '$new_center_id', 'admin', '0', '$reason_esc', NOW())");

        echo json_encode(["status" => "ok"]);
        break;

    case 'get_center_change_logs':
        $center = intval($_GET['center_id'] ?? 0);
        $where = $center > 0 ? "WHERE l.old_center_id='$center' OR l.new_center_id='$center'" : "";
        $res = $mysqli->query("SELECT l.*, u.name, u.roll_no, u.cnic, co.name as old_center_name, cn.name as new_center_name
                              FROM candidate_center_change_logs l
                              LEFT JOIN users u ON u.id=l.user_id
                              LEFT JOIN centers co ON co.id=l.old_center_id
                              LEFT JOIN centers cn ON cn.id=l.new_center_id
                              $where
                              ORDER BY l.changed_at DESC LIMIT 200");
        $rows = [];
        if ($res) {
            while ($r = $res->fetch_assoc()) $rows[] = $r;
        }
        echo json_encode($rows);
        break;

    // -- PARAGRAPHS -------------------------------------------
    case 'get_paragraphs':
        $res  = $mysqli->query("SELECT * FROM paragraphs ORDER BY id DESC");
        $rows = [];
        while ($r = $res->fetch_assoc()) $rows[] = $r;
        echo json_encode($rows);
        break;

    case 'add_paragraph':
        $d       = req_body();
        $title   = $mysqli->real_escape_string(trim($d->title));
        $content = $mysqli->real_escape_string(trim($d->content));
        $type_code = intval($d->type_code ?? 0);
        if ($type_code <= 0) $type_code = default_type_code();
        if (!type_code_exists($type_code, false)) $type_code = default_type_code();
        $resolved_type_name = trim((string)($d->type_name ?? ''));
        if ($resolved_type_name === '') {
            $resolved_type_name = type_name_by_code($type_code);
        }
        if ($resolved_type_name === '') {
            $resolved_type_name = 'Type ' . $type_code;
        }
        $type_name = $mysqli->real_escape_string($resolved_type_name);

        $type_q = $mysqli->query("SHOW COLUMNS FROM paragraphs LIKE 'type_code'");
        if ($type_q && $type_q->num_rows > 0) {
            $mysqli->query("INSERT INTO paragraphs (title,content,type_code,type_name) VALUES ('$title','$content','$type_code','$type_name')");
        } else {
            $mysqli->query("INSERT INTO paragraphs (title,content) VALUES ('$title','$content')");
        }
        echo json_encode(["status" => "ok", "id" => $mysqli->insert_id]);
        break;

    case 'delete_paragraph':
        $d  = req_body();
        $id = intval($d->id ?? 0);
        if ($id <= 0) {
            echo json_encode(["status" => "fail", "message" => "Invalid paragraph id"]);
            break;
        }
        $mysqli->query("DELETE FROM paragraphs WHERE id='$id'");
        echo json_encode(["status" => "ok"]);
        break;

    // -- LAB SESSIONS ----------------------------------------
    case 'get_sessions':
        $res  = $mysqli->query("SELECT ls.*, c.name as center_name FROM lab_sessions ls LEFT JOIN centers c ON ls.center_id=c.id ORDER BY ls.created_at DESC LIMIT 50");
        $rows = [];
        while ($r = $res->fetch_assoc()) $rows[] = $r;
        echo json_encode($rows);
        break;

    case 'create_session':
        $d         = req_body();
        $center_id = intval($d->center_id ?? 0);
        if ($center_id <= 0) {
            echo json_encode(["status" => "fail", "message" => "Center is required"]);
            break;
        }
        $center_q = $mysqli->query("SELECT id FROM centers WHERE id='$center_id' LIMIT 1");
        if (!$center_q || $center_q->num_rows === 0) {
            echo json_encode(["status" => "fail", "message" => "Center not found"]);
            break;
        }
        $password  = str_pad(rand(100000, 999999), 6, "0", STR_PAD_LEFT);
        $mysqli->query("UPDATE lab_sessions SET status='expired' WHERE center_id='$center_id' AND status='active'");
        $mysqli->query("INSERT INTO lab_sessions (center_id, session_password, status, created_at) VALUES ('$center_id','$password','active',NOW())");
        echo json_encode(["status" => "ok", "password" => $password, "id" => $mysqli->insert_id]);
        break;

    case 'expire_session':
        $d  = req_body();
        $id = intval($d->id ?? 0);
        if ($id <= 0) {
            echo json_encode(["status" => "fail", "message" => "Invalid session id"]);
            break;
        }
        $mysqli->query("UPDATE lab_sessions SET status='expired' WHERE id='$id'");
        echo json_encode(["status" => "ok"]);
        break;

    // -- ANALYTICS --------------------------------------------
    case 'get_analytics':
        $res = $mysqli->query("
            SELECT
                c.id, c.name as center_name,
                COUNT(DISTINCT u.id) as total_candidates,
                SUM(CASE WHEN u.logged_once='1' THEN 1 ELSE 0 END) as logged_in,
                SUM(CASE WHEN u.tested='1' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN u.tested='1' AND r.wpm >= COALESCE(u.wpm_required, 0) AND COALESCE(u.wpm_required, 0) > 0 THEN 1 ELSE 0 END) as passed,
                SUM(CASE WHEN u.tested='1' AND r.wpm < COALESCE(u.wpm_required, 0) AND COALESCE(u.wpm_required, 0) > 0 THEN 1 ELSE 0 END) as failed,
                COALESCE(ROUND(AVG(CASE WHEN r.wpm > 0 THEN r.wpm END),1),0) as avg_wpm
            FROM centers c
            LEFT JOIN users u ON u.center_id=c.id
            LEFT JOIN results r ON r.uid=u.id
            GROUP BY c.id, c.name
            ORDER BY c.name
        ");
        $centers = [];
        while ($r = $res->fetch_assoc()) $centers[] = $r;

        $live_res = $mysqli->query("
            SELECT lc.*, u.name, u.roll_no, c.name as center_name
            FROM live_candidates lc
            JOIN users u ON u.id=lc.user_id
            JOIN centers c ON c.id=lc.center_id
            WHERE lc.finished_at IS NULL AND lc.started_at > DATE_SUB(NOW(), INTERVAL 2 HOUR)
            ORDER BY lc.started_at DESC
        ");
        $live = [];
        while ($r = $live_res->fetch_assoc()) $live[] = $r;

        $totals = $mysqli->query("SELECT
            COUNT(*) as total,
            SUM(logged_once) as logged,
            SUM(tested) as tested
            FROM users WHERE type > 0")->fetch_assoc();

        echo json_encode(["centers" => $centers, "live" => $live, "totals" => $totals]);
        break;

    case 'get_results':
        $center = intval($_GET['center_id'] ?? 0);
        $where  = $center ? "WHERE (r.center_id='$center' OR (r.center_id IS NULL AND u.center_id='$center'))" : "";
                $res    = $mysqli->query("SELECT r.*, u.name, u.cnic, u.wpm_required, c.name as center_name,
                                                                                CASE
                                                                                    WHEN COALESCE(u.wpm_required, 0) > 0 AND r.wpm >= u.wpm_required THEN 'Passed'
                                                                                    WHEN COALESCE(u.wpm_required, 0) > 0 THEN 'Failed'
                                                                                    ELSE '—'
                                                                                END as status
                                  FROM results r
                                  LEFT JOIN users u ON u.id=r.uid
                                  LEFT JOIN centers c ON c.id=r.center_id
                                  $where
                                  ORDER BY r.date DESC LIMIT 200");
        $rows = [];
        while ($r = $res->fetch_assoc()) $rows[] = $r;
        echo json_encode($rows);
        break;

    case 'get_result_card':
        $raw = json_decode(file_get_contents("php://input"));
        $roll_no = trim($raw->roll_no ?? ($_GET['roll_no'] ?? ''));
        $cnic = trim($raw->cnic ?? ($_GET['cnic'] ?? ''));
        $center = intval($raw->center_id ?? ($_GET['center_id'] ?? 0));

        if ($roll_no === '' && $cnic === '') {
            echo json_encode(["status" => "fail", "message" => "Roll No or CNIC is required"]);
            break;
        }

        $conditions = [];
        if ($roll_no !== '') {
            $conditions[] = "u.roll_no='" . $mysqli->real_escape_string($roll_no) . "'";
        }
        if ($cnic !== '') {
            $conditions[] = "u.cnic='" . $mysqli->real_escape_string($cnic) . "'";
        }
        $centerWhere = $center > 0 ? " AND u.center_id='$center'" : "";

        $cand = $mysqli->query("SELECT u.*, c.name as center_name, tc.name as type_name
                               FROM users u
                               LEFT JOIN centers c ON c.id=u.center_id
                               LEFT JOIN type_codes tc ON tc.code=u.type
                               WHERE (" . implode(' OR ', $conditions) . ")
                               $centerWhere
                               AND u.type > 0
                               ORDER BY u.id DESC
                               LIMIT 1");

        if (!$cand || $cand->num_rows === 0) {
            echo json_encode(["status" => "notfound", "message" => "Candidate not found"]);
            break;
        }

        $candidate = $cand->fetch_assoc();
        $uid = intval($candidate['id']);

        $result_q = $mysqli->query("SELECT r.*, 
                                     CASE
                                         WHEN COALESCE(u.wpm_required, 0) > 0 AND r.wpm >= u.wpm_required THEN 'Passed'
                                         WHEN COALESCE(u.wpm_required, 0) > 0 THEN 'Failed'
                                         ELSE '—'
                                     END as status
                                     FROM results r
                                     LEFT JOIN users u ON u.id=r.uid
                                     WHERE r.uid='$uid'
                                     ORDER BY r.date DESC
                                     LIMIT 1");
        $result = $result_q && $result_q->num_rows > 0 ? $result_q->fetch_assoc() : null;

        $live_q = $mysqli->query("SELECT id, started_at, finished_at FROM live_candidates WHERE user_id='$uid' AND finished_at IS NULL ORDER BY started_at DESC LIMIT 1");
        $live = $live_q && $live_q->num_rows > 0 ? $live_q->fetch_assoc() : null;

        $submission_status = 'submitted';
        if (!$result) {
            $submission_status = $live ? 'in_progress' : 'pending';
        }

        echo json_encode([
            "status" => "ok",
            "submission_status" => $submission_status,
            "candidate" => $candidate,
            "result" => $result,
            "live_attempt" => $live,
        ]);
        break;

    case 'export_results':
        $res = $mysqli->query("SELECT r.id, r.roll_no, u.name, u.cnic, c.name as center_name, c.code as center_code, r.correct_words, r.incorrect_words, r.wpm, r.accuracy, r.time, r.date,
                              CASE
                                  WHEN COALESCE(u.wpm_required, 0) > 0 AND r.wpm >= u.wpm_required THEN 'Passed'
                                  WHEN COALESCE(u.wpm_required, 0) > 0 THEN 'Failed'
                                  ELSE '—'
                              END as status
                              FROM results r
                              LEFT JOIN users u ON u.id=r.uid
                              LEFT JOIN centers c ON c.id=r.center_id
                              ORDER BY r.date DESC");

        header("Content-Type: text/csv; charset=utf-8");
        header("Content-Disposition: attachment; filename=admin_results_" . date('Ymd_His') . ".csv");

        $out = fopen("php://output", "w");
        fputcsv($out, ["Result ID", "Roll No", "Candidate Name", "CNIC", "Center Code", "Center Name", "Status", "Correct Words", "Incorrect Words", "WPM", "Accuracy", "Time", "Date"]);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                fputcsv($out, [
                    $row['id'],
                    $row['roll_no'],
                    $row['name'],
                    $row['cnic'],
                    $row['center_code'],
                    $row['center_name'],
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
        break;

    default:
        echo json_encode(["error" => "Unknown action"]);
}
?>
