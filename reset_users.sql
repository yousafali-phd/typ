-- ============================================================
--  RESET & RETEST SCRIPT
--  Use this to reset test users for retesting
-- ============================================================

-- Reset all test users to allow them to login and test again
UPDATE users 
SET tested = '0', 
    logged_once = '0', 
    allow_retest = '0',
    last_login = NULL
WHERE type = '1';

-- Show updated users
SELECT 
    id,
    name,
    cnic,
    roll_no,
    center_id,
    tested,
    logged_once,
    allow_retest,
    last_login
FROM users 
WHERE type = '1'
ORDER BY id;

-- ============================================================
-- You can also reset a specific user:
-- ============================================================
-- UPDATE users 
-- SET tested = '0', logged_once = '0', allow_retest = '0'
-- WHERE cnic = '1234567890123' AND roll_no = 'ROLL001';
