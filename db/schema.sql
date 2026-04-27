-- Trailhead v1 Database Schema
-- Run this against your MySQL database to initialize Trailhead

CREATE DATABASE IF NOT EXISTS trailhead CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE trailhead;

-- -------------------------------------------------------
-- Users (Troop leaders who log into Trailhead)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username     VARCHAR(80)  NOT NULL UNIQUE,
    password     VARCHAR(255) NOT NULL,  -- bcrypt hash
    display_name VARCHAR(120) NOT NULL,
    created_at   DATETIME     DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- -------------------------------------------------------
-- Scouts
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS scouts (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name    VARCHAR(120) NOT NULL,
    bsa_id       VARCHAR(40)  DEFAULT NULL,  -- optional BSA member ID
    troop        VARCHAR(40)  DEFAULT NULL,
    notes        TEXT         DEFAULT NULL,
    created_at   DATETIME     DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- -------------------------------------------------------
-- Sessions (a batch of advancements to be entered)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS sessions (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id      INT UNSIGNED NOT NULL,
    title        VARCHAR(200) NOT NULL,
    notes        TEXT         DEFAULT NULL,
    status       ENUM('draft','ready','running','complete','error') DEFAULT 'draft',
    created_at   DATETIME     DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- -------------------------------------------------------
-- Advancements (line items within a session)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS advancements (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id      INT UNSIGNED NOT NULL,
    scout_id        INT UNSIGNED NOT NULL,
    advancement     VARCHAR(200) NOT NULL,  -- e.g. "Tenderfoot Req 1a"
    type            ENUM('rank','merit_badge','award','other') DEFAULT 'other',
    date_completed  DATE         DEFAULT NULL,
    status          ENUM('pending','approved','skipped','error') DEFAULT 'pending',
    result_notes    TEXT         DEFAULT NULL,
    created_at      DATETIME     DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (scout_id)   REFERENCES scouts(id)   ON DELETE CASCADE
);

-- -------------------------------------------------------
-- Run History (log of automation runs)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS run_history (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id   INT UNSIGNED NOT NULL,
    user_id      INT UNSIGNED NOT NULL,
    started_at   DATETIME     DEFAULT CURRENT_TIMESTAMP,
    finished_at  DATETIME     DEFAULT NULL,
    status       ENUM('running','complete','error') DEFAULT 'running',
    log          TEXT         DEFAULT NULL,
    FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE
);
