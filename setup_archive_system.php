<?php
/**
 * Archive System Setup Script
 * This script creates the archive metadata table used for backup records
 */

require_once("api_backend/mysqli.php");

// Check if archives table exists
$check_table = $mysqli->query("
    SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES 
    WHERE TABLE_SCHEMA='typing_april_12' AND TABLE_NAME='archives'
");

if ($check_table && $check_table->num_rows > 0) {
    $status = '<div style="background:#d1fae5;border:1px solid #10b981;color:#065f46;padding:16px;border-radius:8px;margin:20px;">
        <h3 style="margin:0 0 8px 0">✓ Archive System Already Setup</h3>
        <p style="margin:0">The archive metadata table already exists in your database.</p>
    </div>';
} else {
    // Create the archives table
    $create_result = $mysqli->query("
        CREATE TABLE IF NOT EXISTS `archives` (
          `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          `archive_name` VARCHAR(255) NOT NULL UNIQUE,
          `db_name` VARCHAR(255) NOT NULL UNIQUE COMMENT 'Backup key like backup_20260516_Exam_Q1_2026',
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `created_by` INT UNSIGNED,
          `status` VARCHAR(50) DEFAULT 'active' COMMENT 'active, archived, deleted',
          INDEX `idx_created_at` (`created_at`),
          INDEX `idx_archive_name` (`archive_name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
          COMMENT='Archive metadata table to track backup records and restore files'
    ");

    if ($create_result) {
        $status = '<div style="background:#d1fae5;border:1px solid #10b981;color:#065f46;padding:16px;border-radius:8px;margin:20px;">
            <h3 style="margin:0 0 8px 0">✓ Archive System Setup Complete</h3>
            <p style="margin:0">The archive metadata table has been created successfully. You can now use the Archive & Backup feature in the admin dashboard.</p>
        </div>';
    } else {
        $status = '<div style="background:#fee2e2;border:1px solid #ef4444;color:#7f1d1d;padding:16px;border-radius:8px;margin:20px;">
            <h3 style="margin:0 0 8px 0">✗ Setup Failed</h3>
            <p style="margin:0">Error: ' . $mysqli->error . '</p>
        </div>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archive System Setup</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 500px;
            width: 100%;
            padding: 40px;
        }
        h1 {
            font-size: 24px;
            color: #1f2937;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        h1 i { font-size: 28px; color: #667eea; }
        .subtitle {
            color: #6b7280;
            font-size: 14px;
            margin-bottom: 24px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="material-icons">archive</i> Archive System Setup</h1>
        <p class="subtitle">Initialize the archive & backup system</p>
        <?php echo $status; ?>
        <div style="margin-top:20px;">
            <a href="admin/" style="display:inline-block;background:#667eea;color:white;padding:10px 20px;border-radius:6px;text-decoration:none;font-weight:500;">← Back to Admin</a>
        </div>
    </div>
</body>
</html>
