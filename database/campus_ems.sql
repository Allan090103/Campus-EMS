-- ============================================================
--  CampusEMS - Database Schema & Seed Data
--  SECJ3483 Web Technology Group Project
--
--  Three related tables:
--    users  --< events       (organizer_id  -> users.id)
--    users  --< registrations (user_id      -> users.id)
--    events --< registrations (event_id     -> events.id)
--
--  Import:  mysql -u root -p < database/campus_ems.sql
--  (XAMPP)  Import this file via phpMyAdmin, or run it in the SQL tab.
-- ============================================================

DROP DATABASE IF EXISTS campus_ems;
CREATE DATABASE campus_ems CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE campus_ems;

-- ------------------------------------------------------------
-- Table 1: users
--   Holds students, organizers and admins.
--   role        : controls what the user is allowed to do.
--   status      : admin can deactivate an account (active/inactive).
--   password_hash: bcrypt hash (never store plain passwords).
-- ------------------------------------------------------------
CREATE TABLE users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(120)  NOT NULL,
    email         VARCHAR(150)  NOT NULL UNIQUE,
    password_hash VARCHAR(255)  NOT NULL,
    role          ENUM('student','organizer','admin') NOT NULL DEFAULT 'student',
    status        ENUM('active','inactive','pending')  NOT NULL DEFAULT 'active',
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ------------------------------------------------------------
-- Table 2: events
--   Each event belongs to one organizer (organizer_id FK).
--   event_datetime: single DATETIME (date + time merged).
--   ON DELETE CASCADE: if an organizer account is removed,
--   their events go with it.
-- ------------------------------------------------------------
CREATE TABLE events (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    title          VARCHAR(160)  NOT NULL,
    description    TEXT          NOT NULL,
    event_datetime DATETIME      NOT NULL,
    venue          VARCHAR(160)  NOT NULL,
    capacity       INT           NOT NULL,
    category       ENUM('Technology','Career','Cultural','Arts','Sports','Social') NOT NULL,
    organizer_id   INT           NOT NULL,
    status         ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_events_organizer
        FOREIGN KEY (organizer_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ------------------------------------------------------------
-- Table 3: registrations  (junction between users & events)
--   A student registers for an event.
--   attended : organizer marks this true after the event.
--   UNIQUE(user_id, event_id): a student cannot register twice.
-- ------------------------------------------------------------
CREATE TABLE registrations (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    user_id       INT NOT NULL,
    event_id      INT NOT NULL,
    status        ENUM('confirmed','cancelled') NOT NULL DEFAULT 'confirmed',
    attended      TINYINT(1) NOT NULL DEFAULT 0,
    registered_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reg_user
        FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
    CONSTRAINT fk_reg_event
        FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    CONSTRAINT uq_user_event UNIQUE (user_id, event_id)
);

-- Helpful indexes for the queries we run most.
CREATE INDEX idx_events_organizer ON events(organizer_id);
CREATE INDEX idx_events_datetime  ON events(event_datetime);
CREATE INDEX idx_reg_event        ON registrations(event_id);
CREATE INDEX idx_reg_user         ON registrations(user_id);

-- ============================================================
--  SEED DATA
--  All seeded passwords are the SAME for easy demo testing:
--      Password:  password123
--  The hash below is a real bcrypt hash of "password123".
-- ============================================================
-- Real, verified bcrypt hash of "password123" (cost 10):
SET @pw := '$2y$10$VOyEIVcnofInznfNFtoKTehvjat8qCMP12tEv8YAj1/VXsWXeU77u';
-- Real, verified bcrypt hash of "12345678" (cost 10):
SET @pw2 := '$2y$10$dxo6ZxN.pkAxodLZaWMDBelXFdA58S9JEGbOX7ToDqdBfBvWfD6yC';

-- ---- Users -------------------------------------------------
-- 5 admins, 2 organizers, several students.
INSERT INTO users (name, email, password_hash, role, status) VALUES
('System Admin',     'admin@university.edu',          @pw,  'admin',     'active'),
('Rith',             'rith@utm.my',                   @pw2, 'admin',     'active'),
('Mus',              'mus@utm.my',                    @pw2, 'admin',     'active'),
('Irfan',            'irfan@utm.my',                  @pw2, 'admin',     'active'),
('Allan',            'allan@utm.my',                  @pw2, 'admin',     'active'),
('CS Department',    'cs.dept@university.edu',         @pw,  'organizer', 'active'),
('Career Services',  'career.services@university.edu', @pw,  'organizer', 'active'),
('John Doe',         'john.doe@university.edu',        @pw,  'student',   'active'),
('Jane Smith',       'jane.smith@university.edu',      @pw,  'student',   'active'),
('Mike Johnson',     'mike.johnson@university.edu',    @pw,  'student',   'active'),
('Sarah Williams',   'sarah.williams@university.edu',  @pw,  'student',   'inactive'),
('Emily Davis',      'emily.davis@university.edu',     @pw,  'student',   'active');

-- ids:  1=admin  2=CS Dept(org)  3=Career Services(org)
--       4=John  5=Jane  6=Mike  7=Sarah  8=Emily

-- ---- Events ------------------------------------------------
-- Mix of FUTURE events (registerable) and PAST events (for attendance/history).
INSERT INTO events (title, description, event_datetime, venue, capacity, category, organizer_id, status) VALUES
('Tech Summit 2026',          'A full-day summit on emerging technologies, AI, and cloud computing with industry speakers.', '2026-07-15 09:00:00', 'Engineering Building Auditorium', 200, 'Technology', 2, 'approved'),
('Web Development Workshop',   'Hands-on workshop covering modern frontend frameworks and REST API design.',               '2026-07-18 14:00:00', 'Computing Lab A',                50, 'Technology', 2, 'approved'),
('Python Bootcamp',           'Intensive three-session bootcamp on Python for data and automation.',                       '2026-07-30 10:00:00', 'Computer Science Lab',           75, 'Technology', 2, 'approved'),
('Career Fair Spring',        'Meet recruiters from leading companies. Bring your resume.',                                '2026-07-20 10:00:00', 'Student Center Hall A',         500, 'Career',     3, 'approved'),
('Resume Workshop',           'Learn to craft a standout resume with the Career Services team.',                           '2026-04-20 15:00:00', 'Career Services Office',         60, 'Career',     3, 'approved'),
('Cultural Night',            'An evening celebrating campus diversity through performance and food.',                      '2026-07-22 18:00:00', 'Main Campus Theater',           300, 'Cultural',   3, 'approved'),
('Spring Mixer 2026',         'Casual networking social to kick off the new semester.',                                    '2026-04-15 17:00:00', 'Campus Courtyard',              150, 'Social',     2, 'approved');

-- ids: 1=Tech Summit 2=Web Dev WS 3=Python BC 4=Career Fair
--      5=Resume WS(past) 6=Cultural Night 7=Spring Mixer(past)

-- ---- Registrations ----------------------------------------
-- Future event sign-ups (status confirmed, not yet attended).
INSERT INTO registrations (user_id, event_id, status, attended, registered_at) VALUES
(4, 4, 'confirmed', 0, '2026-05-05 12:00:00'),   -- John  -> Career Fair (upcoming)
(4, 1, 'confirmed', 0, '2026-05-07 09:30:00'),   -- John  -> Tech Summit (upcoming)
(5, 1, 'confirmed', 0, '2026-05-06 10:00:00'),   -- Jane  -> Tech Summit
(5, 6, 'confirmed', 0, '2026-05-08 11:00:00'),   -- Jane  -> Cultural Night
(6, 2, 'confirmed', 0, '2026-05-09 14:00:00'),   -- Mike  -> Web Dev Workshop
(8, 4, 'confirmed', 0, '2026-05-10 16:00:00'),   -- Emily -> Career Fair
-- Past event registrations (attended = history records).
(4, 5, 'confirmed', 1, '2026-04-10 08:00:00'),   -- John  -> Resume Workshop (attended)
(4, 7, 'confirmed', 1, '2026-04-01 08:00:00'),   -- John  -> Spring Mixer    (attended)
(5, 7, 'confirmed', 1, '2026-04-02 08:00:00'),   -- Jane  -> Spring Mixer    (attended)
(6, 5, 'confirmed', 0, '2026-04-11 08:00:00');   -- Mike  -> Resume Workshop (did NOT attend)

-- ============================================================
--  Demo accounts (all use password: password123)
--    admin@university.edu          (admin)
--    cs.dept@university.edu        (organizer)
--    career.services@university.edu(organizer)
--    john.doe@university.edu       (student)
-- ============================================================
