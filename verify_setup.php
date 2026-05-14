<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup Verification</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 16px;
            padding: 40px;
            max-width: 800px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 {
            color: #1a237e;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .check-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 18px;
            margin-bottom: 10px;
            border-radius: 10px;
            background: #f8f9fa;
            border-left: 4px solid #ccc;
        }
        .check-item.success {
            background: #d4edda;
            border-left-color: #28a745;
        }
        .check-item.error {
            background: #f8d7da;
            border-left-color: #dc3545;
        }
        .check-item.warning {
            background: #fff3cd;
            border-left-color: #ffc107;
        }
        .icon {
            font-size: 24px;
            font-weight: bold;
        }
        .icon.success { color: #28a745; }
        .icon.error { color: #dc3545; }
        .icon.warning { color: #ffc107; }
        .message {
            flex: 1;
        }
        .title {
            font-weight: 600;
            margin-bottom: 4px;
            font-size: 15px;
        }
        .detail {
            font-size: 13px;
            color: #666;
        }
        .section {
            margin-top: 30px;
        }
        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #1a237e;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #1a237e;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            margin-top: 20px;
            transition: background 0.2s;
        }
        .btn:hover {
            background: #283593;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 13px;
        }
        .data-table th {
            background: #f1f3f5;
            padding: 10px;
            text-align: left;
            font-weight: 600;
            color: #495057;
        }
        .data-table td {
            padding: 10px;
            border-bottom: 1px solid #e9ecef;
        }
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-danger { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Database Setup Verification</h1>
        <p class="subtitle">Typing Test System - Center Integration Check</p>

        <?php
        require_once('api_backend/mysqli.php');

        $checks = [
            'db_connection' => false,
            'users_table' => false,
            'center_id_column' => false,
            'centers_table' => false,
            'lab_sessions_table' => false,
            'live_candidates_table' => false,
            'paragraphs_table' => false
        ];
        $errors = [];
        $warnings = [];

        // Check 1: Database Connection
        echo '<div class="section">';
        echo '<div class="section-title">📡 Connection Status</div>';
        if ($mysqli->ping()) {
            $checks['db_connection'] = true;
            echo '<div class="check-item success">';
            echo '<span class="icon success">✓</span>';
            echo '<div class="message">';
            echo '<div class="title">Database Connected</div>';
            echo '<div class="detail">Successfully connected to: ' . $mysqli->host_info . '</div>';
            echo '</div></div>';
        } else {
            echo '<div class="check-item error">';
            echo '<span class="icon error">✗</span>';
            echo '<div class="message">';
            echo '<div class="title">Database Connection Failed</div>';
            echo '<div class="detail">Error: ' . $mysqli->connect_error . '</div>';
            echo '</div></div>';
            $errors[] = 'Cannot connect to database';
        }
        echo '</div>';

        if ($checks['db_connection']) {
            // Check 2: Tables existence
            echo '<div class="section">';
            echo '<div class="section-title">🗄️ Table Structure</div>';

            $tables_to_check = [
                'users' => 'Users table (main candidate data)',
                'centers' => 'Centers table (exam centers)',
                'lab_sessions' => 'Lab Sessions table (session passwords)',
                'live_candidates' => 'Live Candidates table (active tests)',
                'paragraphs' => 'Paragraphs table (typing test content)',
                'results' => 'Results table (test results)'
            ];

            foreach ($tables_to_check as $table => $desc) {
                $result = $mysqli->query("SHOW TABLES LIKE '$table'");
                if ($result && $result->num_rows > 0) {
                    $checks[$table . '_table'] = true;
                    echo '<div class="check-item success">';
                    echo '<span class="icon success">✓</span>';
                    echo '<div class="message">';
                    echo '<div class="title">Table: ' . $table . '</div>';
                    echo '<div class="detail">' . $desc . '</div>';
                    echo '</div></div>';
                } else {
                    echo '<div class="check-item error">';
                    echo '<span class="icon error">✗</span>';
                    echo '<div class="message">';
                    echo '<div class="title">Missing Table: ' . $table . '</div>';
                    echo '<div class="detail">' . $desc . ' not found</div>';
                    echo '</div></div>';
                    $errors[] = "Table '$table' is missing";
                }
            }
            echo '</div>';

            // Check 3: Users table structure
            echo '<div class="section">';
            echo '<div class="section-title">🔧 Users Table Structure</div>';

            $columns_result = $mysqli->query("SHOW COLUMNS FROM users");
            $columns = [];
            while ($col = $columns_result->fetch_assoc()) {
                $columns[] = $col['Field'];
            }

            $required_columns = ['id', 'roll_no', 'cnic', 'center_id', 'tested', 'allow_retest', 'logged_once', 'name'];
            
            foreach ($required_columns as $req_col) {
                if (in_array($req_col, $columns)) {
                    if ($req_col == 'center_id') $checks['center_id_column'] = true;
                    echo '<div class="check-item success">';
                    echo '<span class="icon success">✓</span>';
                    echo '<div class="message">';
                    echo '<div class="title">Column: ' . $req_col . '</div>';
                    echo '<div class="detail">Present in users table</div>';
                    echo '</div></div>';
                } else {
                    echo '<div class="check-item error">';
                    echo '<span class="icon error">✗</span>';
                    echo '<div class="message">';
                    echo '<div class="title">Missing Column: ' . $req_col . '</div>';
                    echo '<div class="detail">Required column not found in users table</div>';
                    echo '</div></div>';
                    $errors[] = "Column '$req_col' missing from users table";
                }
            }
            echo '</div>';

            // Check 4: Data verification
            echo '<div class="section">';
            echo '<div class="section-title">📊 Data Summary</div>';

            // Count centers
            $centers_count = $mysqli->query("SELECT COUNT(*) as count FROM centers")->fetch_assoc()['count'];
            echo '<div class="check-item ' . ($centers_count > 0 ? 'success' : 'warning') . '">';
            echo '<span class="icon ' . ($centers_count > 0 ? 'success' : 'warning') . '">' . ($centers_count > 0 ? '✓' : '⚠') . '</span>';
            echo '<div class="message">';
            echo '<div class="title">Centers: ' . $centers_count . '</div>';
            echo '<div class="detail">' . ($centers_count > 0 ? 'Exam centers configured' : 'No centers added yet - Add centers in admin panel') . '</div>';
            echo '</div></div>';

            // Count users
            $users_count = $mysqli->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
            $users_with_center = $mysqli->query("SELECT COUNT(*) as count FROM users WHERE center_id IS NOT NULL AND center_id > 0")->fetch_assoc()['count'];
            
            echo '<div class="check-item ' . ($users_count > 0 ? 'success' : 'warning') . '">';
            echo '<span class="icon ' . ($users_count > 0 ? 'success' : 'warning') . '">' . ($users_count > 0 ? '✓' : '⚠') . '</span>';
            echo '<div class="message">';
            echo '<div class="title">Candidates: ' . $users_count . ' total, ' . $users_with_center . ' with centers</div>';
            echo '<div class="detail">' . ($users_count > 0 ? 'Candidate data exists' : 'No candidates added yet') . '</div>';
            echo '</div></div>';

            // Show centers if they exist
            if ($centers_count > 0) {
                echo '<div class="section">';
                echo '<div class="section-title">📍 Exam Centers</div>';
                echo '<table class="data-table">';
                echo '<thead><tr><th>#</th><th>Center Name</th><th>Code</th><th>Candidates</th><th>Status</th></tr></thead><tbody>';
                
                $centers_result = $mysqli->query("
                    SELECT c.*, COUNT(u.id) as candidate_count 
                    FROM centers c 
                    LEFT JOIN users u ON u.center_id = c.id 
                    GROUP BY c.id 
                    ORDER BY c.name
                ");
                
                $i = 1;
                while ($center = $centers_result->fetch_assoc()) {
                    echo '<tr>';
                    echo '<td>' . $i++ . '</td>';
                    echo '<td><strong>' . htmlspecialchars($center['name']) . '</strong></td>';
                    echo '<td>' . htmlspecialchars($center['code'] ?? '-') . '</td>';
                    echo '<td>' . $center['candidate_count'] . '</td>';
                    echo '<td><span class="badge ' . ($center['is_active'] ? 'badge-success' : 'badge-danger') . '">' . ($center['is_active'] ? 'Active' : 'Inactive') . '</span></td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
                echo '</div>';
            }

            echo '</div>';

            // Overall status
            echo '<div class="section">';
            echo '<div class="section-title">🎯 Overall Status</div>';
            
            $all_critical_checks = $checks['db_connection'] && $checks['users_table'] && 
                                  $checks['center_id_column'] && $checks['centers_table'];
            
            if ($all_critical_checks) {
                echo '<div class="check-item success">';
                echo '<span class="icon success">✓</span>';
                echo '<div class="message">';
                echo '<div class="title">✅ Setup Complete!</div>';
                echo '<div class="detail">All critical components are in place. The Candidates page should work correctly.</div>';
                echo '</div></div>';
                
                echo '<a href="admin/" class="btn">Open Admin Dashboard →</a>';
            } else {
                echo '<div class="check-item error">';
                echo '<span class="icon error">✗</span>';
                echo '<div class="message">';
                echo '<div class="title">⚠️ Setup Incomplete</div>';
                echo '<div class="detail">Please run the update_database.sql script to fix the issues.</div>';
                echo '</div></div>';
                
                if (count($errors) > 0) {
                    echo '<div style="margin-top: 15px; padding: 15px; background: #fff3cd; border-radius: 8px; border-left: 4px solid #ffc107;">';
                    echo '<strong>Issues Found:</strong><ul style="margin: 10px 0 0 20px;">';
                    foreach ($errors as $error) {
                        echo '<li>' . $error . '</li>';
                    }
                    echo '</ul></div>';
                }
            }
            echo '</div>';
        }
        ?>
    </div>
</body>
</html>
