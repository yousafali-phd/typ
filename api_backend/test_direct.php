<?php
// Simple debug script to test if login.php is accessible
// Access: http://localhost/typing/api_backend/test_direct.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Direct API Test</h2>";
echo "<p>If you see this, the file path is working!</p>";

// Test database connection
require_once("mysqli.php");

if ($mysqli->connect_error) {
    echo "<p style='color:red'>❌ Database Error: " . $mysqli->connect_error . "</p>";
} else {
    echo "<p style='color:green'>✅ Database Connected!</p>";
}

// Test simple login logic
$test_data = [
    'cnic' => '1234567890123',
    'rollno' => 'ROLL001',
    'otp' => 'TEST123'
];

echo "<h3>Testing with data:</h3>";
echo "<pre>" . print_r($test_data, true) . "</pre>";

$cnic = $mysqli->real_escape_string($test_data['cnic']);
$rollno = $mysqli->real_escape_string($test_data['rollno']);
$otp = $mysqli->real_escape_string($test_data['otp']);

// Check session
$session_query = $mysqli->query("SELECT * FROM lab_sessions WHERE session_password='$otp' AND status='active' AND DATE(created_at)=CURDATE()");
echo "<p>Session query result: " . $session_query->num_rows . " rows</p>";

if ($session_query->num_rows > 0) {
    $session = $session_query->fetch_assoc();
    $center_id = $session['center_id'];
    echo "<p style='color:green'>✅ Session found for center ID: $center_id</p>";
    
    // Check user
    $user_query = $mysqli->query("SELECT * FROM users WHERE cnic='$cnic' AND roll_no='$rollno' AND center_id='$center_id' AND type > 0");
    echo "<p>User query result: " . $user_query->num_rows . " rows</p>";
    
    if ($user_query->num_rows > 0) {
        $user = $user_query->fetch_assoc();
        echo "<p style='color:green'>✅ User found: " . $user['name'] . "</p>";
        echo "<pre>" . print_r($user, true) . "</pre>";
    } else {
        echo "<p style='color:red'>❌ User not found!</p>";
    }
} else {
    echo "<p style='color:red'>❌ No active session found!</p>";
}
?>
