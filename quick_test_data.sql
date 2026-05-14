-- ============================================================
--  QUICK SETUP - Test Data for Immediate Testing
--  Run this AFTER setup_database.sql
-- ============================================================

-- 1. Create test centers (if not exists)
INSERT IGNORE INTO `centers` (id, name, code) VALUES 
  (1, 'Main Examination Center', 'MEC-01'),
  (2, 'Secondary Center Kohat', 'SCK-02');

-- 2. Create active lab session for TODAY
-- This creates a session with password "TEST123"
INSERT INTO `lab_sessions` (center_id, session_password, status, created_at) 
VALUES (1, 'TEST123', 'active', NOW());

-- 3. Add sample test candidates
INSERT IGNORE INTO `users` (name, cnic, roll_no, center_id, type, tested, logged_once, allow_retest) 
VALUES 
  ('Ahmed Khan', '1234567890123', 'ROLL001', 1, '1', '0', '0', '0'),
  ('Sara Ali', '9876543210987', 'ROLL002', 1, '1', '0', '0', '0'),
  ('Muhammad Bilal', '1111222233334', 'ROLL003', 1, '1', '0', '0', '0'),
  ('Ayesha Malik', '5555666677778', 'ROLL004', 1, '1', '0', '0', '0'),
  ('Imran Ahmed', '9999888877776', 'ROLL005', 1, '1', '0', '0', '0');

-- 4. Verify the setup
SELECT 'Lab Sessions Created:' as Status;
SELECT * FROM lab_sessions WHERE status='active' AND DATE(created_at)=CURDATE();

SELECT 'Test Candidates Created:' as Status;
SELECT id, name, cnic, roll_no, center_id, tested FROM users WHERE type > 0 LIMIT 10;

-- ============================================================
--  LOGIN CREDENTIALS FOR TESTING:
-- ============================================================
--  CNIC: 1234567890123
--  Roll Number: ROLL001
--  Password: TEST123
-- ============================================================

-- IMPORTANT NOTES:
-- 1. The session password "TEST123" is valid for TODAY only
-- 2. To create a new session for another day, run:
--    INSERT INTO lab_sessions (center_id, session_password, status, created_at) 
--    VALUES (1, 'YOURPASS', 'active', NOW());
-- 3. Users with tested='1' cannot login again unless allow_retest='1'
-- 4. Users with logged_once='1' and tested='0' are considered "in progress"
