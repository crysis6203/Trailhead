-- Trailhead Schema Update
-- Extends existing run_history table and adds run_items for structured callback results
-- Run this in the SQL command window against database: evzwcygjrf

-- Step 1: Add automation columns to existing run_history table
-- These are all NULL-safe so existing rows are not affected
ALTER TABLE run_history
ADD COLUMN IF NOT EXISTS status ENUM('pending','running','complete','failed') NULL DEFAULT NULL AFTER summary,
ADD COLUMN IF NOT EXISTS completed_at TIMESTAMP NULL DEFAULT NULL AFTER status,
ADD COLUMN IF NOT EXISTS total_items INT NULL DEFAULT NULL AFTER completed_at,
ADD COLUMN IF NOT EXISTS succeeded INT NULL DEFAULT NULL AFTER total_items,
ADD COLUMN IF NOT EXISTS failed INT NULL DEFAULT NULL AFTER succeeded,
ADD COLUMN IF NOT EXISTS needs_review INT NULL DEFAULT NULL AFTER failed;

-- Step 2: Create run_items table for per-requirement results
-- One row per individual requirement attempted by Computer
CREATE TABLE IF NOT EXISTS run_items (
id INT AUTO_INCREMENT PRIMARY KEY,
run_history_id INT NOT NULL,
scout_name VARCHAR(200) NOT NULL,
type ENUM('rank','merit_badge','award') NOT NULL DEFAULT 'rank',
item_name VARCHAR(200) NOT NULL,
requirement VARCHAR(50) NOT NULL,
completion_date DATE NOT NULL,
status ENUM('entered','already_approved','failed','needs_review') NOT NULL,
note TEXT NULL DEFAULT NULL,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
FOREIGN KEY (run_history_id) REFERENCES run_history(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
