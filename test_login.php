<?php
/**
 * LOGIN DEBUG & VERIFICATION SCRIPT
 * Access: http://localhost/typing/test_login.php
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 Typing Test - Login System Diagnostic</h1>";
echo "<style>body{font-family:Arial;padding:20px;background:#f5f5f5} .box{background:white;padding:15px;margin:10px 0;border-radius:8px;border:1px solid #ddd} .success{color:green} .error{color:red} .info{color:#0066cc} h3{margin-top:0;border-bottom:2px solid #0066cc;padding-bottom:8px}</style>";

// 1. Check database connection
echo "<div class='box'>";
echo "<h3>1️⃣ Database Connection Test</h3>";
require_once("api_backend/mysqli.php");
if ($mysqli->connect_error) {
    echo "<p class='error'>❌ Database connection FAILED: " . $mysqli->connect_error . "</p>";
    exit;
} else {
    echo "<p class='success'>✅ Database connected successfully</p>";
    echo "<p class='info'>Host: " . $mysqli->host_info . "</p>";
}
echo "</div>";

// 2. Check required tables
echo "<div class='box'>";
echo "<h3>2️⃣ Database Tables Check</h3>";
$tables = ['users', 'lab_sessions', 'centers', 'paragraphs', 'results'];
$all_ok = true;
foreach ($tables as $table) {
    $result = $mysqli->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows > 0) {
        echo "<p class='success'>✅ Table '$table' exists</p>";
    } else {
        echo "<p class='error'>❌ Table '$table' NOT FOUND</p>";
        $all_ok = false;
    }
}
if (!$all_ok) {
    echo "<p class='error'><strong>⚠️ Some tables are missing. Run setup_database.sql in phpMyAdmin!</strong></p>";
}
echo "</div>";

// 3. Check for active sessions
echo "<div class='box'>";
echo "<h3>3️⃣ Active Lab Sessions</h3>";
$sessions = $mysqli->query("SELECT ls.*, c.name as center_name FROM lab_sessions ls 
                            LEFT JOIN centers c ON ls.center_id=c.id 
                            WHERE ls.status='active' AND DATE(ls.created_at)=CURDATE()");
if ($sessions->num_rows > 0) {
    echo "<table border='1' cellpadding='8' style='border-collapse:collapse;width:100%'>";
    echo "<tr style='background:#0066cc;color:white'><th>Session ID</th><th>Center</th><th>Password</th><th>Created</th></tr>";
    while ($row = $sessions->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['center_name']}</td>";
        echo "<td><strong style='color:green;font-size:18px'>{$row['session_password']}</strong></td>";
        echo "<td>{$row['created_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p class='error'>❌ No active lab sessions found for today!</p>";
    echo "<p class='info'>💡 <strong>Create a session from admin panel</strong> or run this SQL:</p>";
    echo "<pre style='background:#f9f9f9;padding:10px;border:1px solid #ddd'>INSERT INTO lab_sessions (center_id, session_password, status, created_at) 
VALUES (1, 'TEST123', 'active', NOW());</pre>";
}
echo "</div>";

// 4. Check for test users
echo "<div class='box'>";
echo "<h3>4️⃣ Sample Test Users</h3>";
$users = $mysqli->query("SELECT u.*, c.name as center_name FROM users u 
                        LEFT JOIN centers c ON u.center_id=c.id 
                        WHERE u.type > 0 LIMIT 5");
if ($users->num_rows > 0) {
    echo "<table border='1' cellpadding='8' style='border-collapse:collapse;width:100%'>";
    echo "<tr style='background:#0066cc;color:white'><th>Name</th><th>CNIC</th><th>Roll No</th><th>Center</th><th>Tested</th><th>Logged</th></tr>";
    while ($row = $users->fetch_assoc()) {
        $tested_badge = $row['tested'] == '1' ? '<span style="color:green">✓ YES</span>' : '<span style="color:orange">○ NO</span>';
        $logged_badge = $row['logged_once'] == '1' ? '<span style="color:green">✓ YES</span>' : '<span style="color:gray">○ NO</span>';
        echo "<tr>";
        echo "<td>{$row['name']}</td>";
        echo "<td><strong>{$row['cnic']}</strong></td>";
        echo "<td><strong>{$row['roll_no']}</strong></td>";
        echo "<td>{$row['center_name']}</td>";
        echo "<td>$tested_badge</td>";
        echo "<td>$logged_badge</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p class='error'>❌ No test users found in database!</p>";
    echo "<p class='info'>💡 <strong>Add users from admin panel</strong> or run this SQL:</p>";
    echo "<pre style='background:#f9f9f9;padding:10px;border:1px solid #ddd'>INSERT INTO users (name, cnic, roll_no, center_id, type) 
VALUES ('Test Candidate', '1234567890123', 'ROLL001', 1, '1');</pre>";
}
echo "</div>";

// 5. Login Test Form
echo "<div class='box'>";
echo "<h3>5️⃣ Quick Login Test</h3>";
echo "<p>Use the credentials from the tables above to test login functionality:</p>";
echo "<form method='POST' style='background:#f9f9f9;padding:15px;border:1px solid #ddd;border-radius:4px'>";
echo "<label><strong>CNIC (13 digits):</strong><br><input type='text' name='test_cnic' value='1234567890123' style='width:300px;padding:8px;margin:5px 0'></label><br>";
echo "<label><strong>Roll Number:</strong><br><input type='text' name='test_roll' value='ROLL001' style='width:300px;padding:8px;margin:5px 0'></label><br>";
echo "<label><strong>Session Password:</strong><br><input type='text' name='test_otp' value='TEST123' style='width:300px;padding:8px;margin:5px 0'></label><br>";
echo "<button type='submit' name='test_login' style='padding:10px 20px;background:#0066cc;color:white;border:none;border-radius:4px;cursor:pointer;margin-top:10px'>🔐 Test Login</button>";
echo "</form>";

if (isset($_POST['test_login'])) {
    echo "<div style='margin-top:15px;padding:12px;background:#fffbea;border:1px solid #f9a825;border-radius:4px'>";
    echo "<h4 style='margin-top:0'>🧪 Login Test Results:</h4>";
    
    $test_cnic = $mysqli->real_escape_string($_POST['test_cnic']);
    $test_roll = $mysqli->real_escape_string($_POST['test_roll']);
    $test_otp = $mysqli->real_escape_string($_POST['test_otp']);
    
    // Check session password
    $session_check = $mysqli->query("SELECT * FROM lab_sessions WHERE session_password='$test_otp' AND status='active' AND DATE(created_at)=CURDATE()");
    if ($session_check->num_rows == 0) {
        echo "<p class='error'>❌ Session password '$test_otp' is invalid or expired</p>";
    } else {
        echo "<p class='success'>✅ Session password is valid</p>";
        $session_data = $session_check->fetch_assoc();
        $center_id = $session_data['center_id'];
        
        // Check user
        $user_check = $mysqli->query("SELECT * FROM users WHERE cnic='$test_cnic' AND roll_no='$test_roll' AND center_id='$center_id' AND type > 0");
        if ($user_check->num_rows == 0) {
            echo "<p class='error'>❌ User not found with CNIC: $test_cnic, Roll: $test_roll, Center ID: $center_id</p>";
        } else {
            $user_data = $user_check->fetch_assoc();
            echo "<p class='success'>✅ User found: {$user_data['name']}</p>";
            
            if ($user_data['tested'] == '1' && $user_data['allow_retest'] != '1') {
                echo "<p class='error'>⚠️ User has already completed the test</p>";
            } elseif ($user_data['logged_once'] == '1' && $user_data['tested'] == '0') {
                echo "<p class='error'>⚠️ User is already logged in on another device</p>";
            } else {
                echo "<p class='success'><strong>✅✅✅ LOGIN WOULD SUCCEED!</strong></p>";
                echo "<p class='info'>User can proceed to the typing test.</p>";
            }
        }
    }
    echo "</div>";
}
echo "</div>";

// 6. API Endpoints Check
echo "<div class='box'>";
echo "<h3>6️⃣ API Endpoints Status</h3>";
$endpoints = [
    'login.php' => 'api_backend/login.php',
    'get_paragraph.php' => 'api_backend/get_paragraph.php',
    'save_result.php' => 'api_backend/save_result.php'
];
foreach ($endpoints as $name => $path) {
    if (file_exists($path)) {
        echo "<p class='success'>✅ $name - File exists</p>";
    } else {
        echo "<p class='error'>❌ $name - File NOT FOUND at $path</p>";
    }
}
echo "</div>";

// 7. Recommendations
echo "<div class='box'>";
echo "<h3>7️⃣ Setup Checklist</h3>";
echo "<ol style='line-height:1.8'>";
echo "<li>✅ All API files now use correct mysqli.php path (FIXED)</li>";
echo "<li>Ensure database 'typing_april_12' exists in phpMyAdmin</li>";
echo "<li>Run setup_database.sql to create all tables</li>";
echo "<li>Create centers in admin panel or via SQL</li>";
echo "<li>Create lab session with password from admin panel</li>";
echo "<li>Import or add test candidates</li>";
echo "<li>Test login from main page: <a href='index.html'>index.html</a></li>";
echo "</ol>";
echo "</div>";

echo "<hr><p style='text-align:center;color:#999'>Diagnostic completed - " . date('Y-m-d H:i:s') . "</p>";
?>
