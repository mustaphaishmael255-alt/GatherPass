-- ============================================================
-- GatherPass — Database Schema & Seed Data
-- Discover. Book. Enter.
-- ============================================================

CREATE DATABASE IF NOT EXISTS gatherpass
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE gatherpass;

-- ── Planners (Event Organisers) ──────────────────────────────
CREATE TABLE IF NOT EXISTS planners (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    full_name       VARCHAR(255) NOT NULL,
    email           VARCHAR(255) NOT NULL UNIQUE,
    password        VARCHAR(255) NOT NULL,
    phone           VARCHAR(20)  DEFAULT NULL,
    is_active       BOOLEAN      DEFAULT TRUE,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Events ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS events (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    planner_id          INT            NOT NULL,
    name                VARCHAR(255)   NOT NULL,
    description         TEXT           DEFAULT NULL,
    event_date          DATETIME       NOT NULL,
    venue               VARCHAR(255)   DEFAULT NULL,
    base_ticket_price   DECIMAL(10,2)  DEFAULT 0.00,
    available_tickets   INT            DEFAULT 100,
    picture             VARCHAR(500)   DEFAULT NULL,
    is_active           BOOLEAN        DEFAULT TRUE,
    created_at          TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (planner_id) REFERENCES planners(id) ON DELETE CASCADE,
    INDEX idx_planner   (planner_id),
    INDEX idx_active    (is_active),
    INDEX idx_date      (event_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Tickets ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS tickets (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    event_id            INT            NOT NULL,
    buyer_name          VARCHAR(255)   NOT NULL,
    buyer_email         VARCHAR(255)   NOT NULL,
    buyer_phone         VARCHAR(30)    DEFAULT NULL,
    quantity            INT            DEFAULT 1,
    ticket_type         VARCHAR(100)   DEFAULT 'General Admission',
    ticket_price        DECIMAL(10,2)  DEFAULT 0.00,
    platform_fee        DECIMAL(10,2)  DEFAULT 0.00,
    total_paid          DECIMAL(10,2)  DEFAULT 0.00,
    ticket_token        VARCHAR(64)    NOT NULL UNIQUE,
    status              VARCHAR(20)    DEFAULT 'valid',
    checked_in_at       DATETIME       DEFAULT NULL,
    created_at          TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    INDEX idx_event   (event_id),
    INDEX idx_token   (ticket_token),
    INDEX idx_status  (status),
    INDEX idx_buyer   (buyer_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ══════════════════════════════════════════════════════════════
-- SEED DATA — fictional events with permission-cleared images
-- ══════════════════════════════════════════════════════════════

-- Demo planner account (password: gatherpass2026)
INSERT INTO planners (full_name, email, password, phone) VALUES
('GatherPass Demo', 'demo@gatherpass.app',
 '$2y$10$8K1p/a0dL1LXMIgoEDFrwOfM.Y1qZs0sPdBL8BOBqXcMZ0eW0yD9u',
 '+233000000000')
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name);

-- Three seed events
INSERT INTO events (planner_id, name, description, event_date, venue, base_ticket_price, available_tickets, picture, is_active) VALUES
(1,
 'Accra Tech Summit 2026',
 'The premier West-African technology conference featuring keynotes on AI, cloud computing, and fintech. Three days of workshops, live demos, and networking with 50+ industry leaders.',
 '2026-12-15 09:00:00',
 'Accra International Conference Centre',
 100.00,
 400,
 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=800&q=80',
 TRUE),
(1,
 'Back Yard Party',
 'An evening of live Afrobeats, great food, and good vibes. Local DJs, food trucks, a bonfire zone, and starlit dancing under the open sky.',
 '2026-09-28 17:00:00',
 'Garden City, Kumasi',
 50.00,
 200,
 'https://images.unsplash.com/photo-1533174072545-7a4b6ad7a6c3?w=800&q=80',
 TRUE),
(1,
 'Startup Pitch Night',
 'Watch ten handpicked startups pitch to a panel of investors and mentors. Audience voting decides the People''s Choice award. Refreshments included.',
 '2026-11-05 18:30:00',
 'Impact Hub, Osu',
 30.00,
 150,
 'https://images.unsplash.com/photo-1559223607-a43c990c692c?w=800&q=80',
 TRUE);

SELECT 'GatherPass database setup completed!' AS message;
