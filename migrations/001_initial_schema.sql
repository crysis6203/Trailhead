-- ============================================================================
-- TRAILHEAD (v2) — INITIAL SCHEMA
-- Run this once against a fresh, empty database.
-- ============================================================================

CREATE TABLE scouts (
    id                  INT PRIMARY KEY,
    name                VARCHAR(120) NOT NULL,
    member_type         ENUM('youth','leader') NOT NULL,
    age                 TINYINT NULL,
    patrol              VARCHAR(60) NULL,
    position            VARCHAR(60) NULL,
    last_rank_approved  VARCHAR(30) NULL,
    renewal_status      VARCHAR(20) NULL,
    expiration_date     DATE NULL,
    last_synced_at      DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE ranks (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    name        VARCHAR(30) NOT NULL UNIQUE,
    sort_order  TINYINT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE rank_versions (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    rank_id         INT NOT NULL,
    version_year    SMALLINT NOT NULL,
    is_current      BOOLEAN NOT NULL DEFAULT TRUE,
    UNIQUE KEY uq_rank_version (rank_id, version_year),
    FOREIGN KEY (rank_id) REFERENCES ranks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE rank_requirements (
    id                  INT PRIMARY KEY AUTO_INCREMENT,
    rank_version_id     INT NOT NULL,
    requirement_code    VARCHAR(10) NOT NULL,
    display_text        TEXT NOT NULL,
    sort_order          SMALLINT NOT NULL,
    UNIQUE KEY uq_rank_req (rank_version_id, requirement_code),
    FOREIGN KEY (rank_version_id) REFERENCES rank_versions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- The missing analog to scout_merit_badge_progress: which rank(s) is a
-- scout actively working on? Needed because "in progress" isn't derivable
-- from last_rank_approved alone — per Chris, Scout/Tenderfoot/Second Class/
-- First Class can all be in progress simultaneously, while Star/Life/Eagle
-- are mutually exclusive (only one at a time).
CREATE TABLE scout_rank_progress (
    id                  INT PRIMARY KEY AUTO_INCREMENT,
    scout_id            INT NOT NULL,
    rank_id             INT NOT NULL,
    rank_version_id     INT NOT NULL,
    status              ENUM('in_progress','completed','awarded') NOT NULL DEFAULT 'in_progress',
    last_synced_at      DATETIME NOT NULL,
    UNIQUE KEY uq_scout_rank (scout_id, rank_id),
    FOREIGN KEY (scout_id) REFERENCES scouts(id) ON DELETE CASCADE,
    FOREIGN KEY (rank_id) REFERENCES ranks(id) ON DELETE CASCADE,
    FOREIGN KEY (rank_version_id) REFERENCES rank_versions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE merit_badges (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    name            VARCHAR(80) NOT NULL UNIQUE,
    eagle_required  BOOLEAN NOT NULL DEFAULT FALSE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE merit_badge_versions (
    id                  INT PRIMARY KEY AUTO_INCREMENT,
    merit_badge_id      INT NOT NULL,
    version_year        SMALLINT NOT NULL,
    is_current          BOOLEAN NOT NULL DEFAULT FALSE,
    expiration_date     DATE NULL,
    UNIQUE KEY uq_mb_version (merit_badge_id, version_year),
    FOREIGN KEY (merit_badge_id) REFERENCES merit_badges(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE merit_badge_requirements (
    id                      INT PRIMARY KEY AUTO_INCREMENT,
    merit_badge_version_id  INT NOT NULL,
    parent_requirement_id   INT NULL,
    requirement_code        VARCHAR(15) NOT NULL,
    display_text            TEXT NOT NULL,
    is_checkable            BOOLEAN NOT NULL DEFAULT TRUE,
    sort_order              SMALLINT NOT NULL,
    UNIQUE KEY uq_mb_req (merit_badge_version_id, requirement_code),
    FOREIGN KEY (merit_badge_version_id) REFERENCES merit_badge_versions(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_requirement_id) REFERENCES merit_badge_requirements(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE scout_merit_badge_progress (
    id                      INT PRIMARY KEY AUTO_INCREMENT,
    scout_id                INT NOT NULL,
    merit_badge_id          INT NOT NULL,
    merit_badge_version_id  INT NULL,
    status                  ENUM('not_started','in_progress','completed','awarded') NOT NULL,
    last_synced_at          DATETIME NOT NULL,
    UNIQUE KEY uq_scout_mb (scout_id, merit_badge_id),
    FOREIGN KEY (scout_id) REFERENCES scouts(id) ON DELETE CASCADE,
    FOREIGN KEY (merit_badge_id) REFERENCES merit_badges(id) ON DELETE CASCADE,
    FOREIGN KEY (merit_badge_version_id) REFERENCES merit_badge_versions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- APP-OWNED: signoffs. The pull (roster/requirement sync) must NEVER write
-- to this table — only the capture UI (insert) and the push task (status
-- update) touch it.
-- ----------------------------------------------------------------------------
CREATE TABLE signoffs (
    id                          INT PRIMARY KEY AUTO_INCREMENT,
    scout_id                    INT NOT NULL,
    requirement_type            ENUM('rank','merit_badge','award','leadership') NOT NULL,
    rank_requirement_id         INT NULL,
    merit_badge_requirement_id  INT NULL,
    comment                     VARCHAR(150) NULL,
    completion_date             DATE NOT NULL,
    noted_at                    DATETIME NOT NULL,
    status                      ENUM('pending','pushed','confirmed','flagged') NOT NULL DEFAULT 'pending',
    pushed_at                   DATETIME NULL,
    confirmed_at                DATETIME NULL,
    flag_reason                 TEXT NULL,
    FOREIGN KEY (scout_id) REFERENCES scouts(id) ON DELETE CASCADE,
    FOREIGN KEY (rank_requirement_id) REFERENCES rank_requirements(id) ON DELETE SET NULL,
    FOREIGN KEY (merit_badge_requirement_id) REFERENCES merit_badge_requirements(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE sync_runs (
    id                      INT PRIMARY KEY AUTO_INCREMENT,
    run_type                ENUM('scheduled','manual_push','manual_pull','manual_both','catch_up') NOT NULL,
    scheduled_for            DATETIME NULL,
    started_at              DATETIME NOT NULL,
    completed_at            DATETIME NULL,
    push_confirmed_count    INT NOT NULL DEFAULT 0,
    push_flagged_count      INT NOT NULL DEFAULT 0,
    pull_updated_count      INT NOT NULL DEFAULT 0,
    status                  ENUM('success','partial','failed') NULL,
    fail_reason             TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed the seven fixed ranks (static, rarely if ever changes)
INSERT INTO ranks (name, sort_order) VALUES
    ('Scout', 1), ('Tenderfoot', 2), ('Second Class', 3), ('First Class', 4),
    ('Star Scout', 5), ('Life Scout', 6), ('Eagle Scout', 7);
