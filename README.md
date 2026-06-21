# CampusEMS — Campus Event Management System

SECJ3483 Web Technology — Group Project (Section 01)

A full-stack single-page application for managing campus events. Students
browse and register for events, organizers create events and track
attendance, and administrators oversee all users and events.

## Tech stack

| Layer      | Technology                                  |
|------------|---------------------------------------------|
| Frontend   | Vue 3 (Composition API) + Vue Router + Pinia |
| HTTP       | Axios                                        |
| Backend    | PHP Slim 4 (REST API)                        |
| Auth       | JWT (firebase/php-jwt), role-based access    |
| Database   | MySQL via PDO (prepared statements)          |

## Project structure

```
campus-ems/
├── backend/                 PHP Slim 4 REST API
│   ├── config/
│   │   ├── db.php           PDO connection (edit DB port here)
│   │   └── config.php       JWT secret & token lifetime
│   ├── src/
│   │   ├── Helpers/         Json + Token helpers
│   │   └── Middleware/      CORS, Auth (JWT), Role
│   ├── public/index.php     ALL API routes
│   └── composer.json
├── database/
│   └── campus_ems.sql       Schema + seed data (3 related tables)
└── frontend/                Vue 3 SPA
    └── src/
        ├── views/           One file per page
        ├── components/      Navbar, EventCard, StatCard, icons
        ├── stores/auth.js   Pinia auth store (JWT in memory)
        ├── services/api.js  Axios instance
        └── router/index.js  Routes + role guards
```

## Prerequisites

- **XAMPP** (or MAMP) — provides PHP 8+ and MySQL
- **Composer** — https://getcomposer.org
- **Node.js 18+** and npm — https://nodejs.org

## Setup

### 1. Database

1. Start **MySQL** from the XAMPP control panel.
2. Open phpMyAdmin (http://localhost/phpmyadmin) → **Import** →
   choose `database/campus_ems.sql` → **Go**.
   (Or from a terminal: `mysql -u root < database/campus_ems.sql`)

This creates the `campus_ems` database with three related tables
(`users`, `events`, `registrations`) and sample data.

### 2. Backend (PHP Slim API)

```bash
cd backend
composer install            # installs Slim + php-jwt into vendor/
php -S localhost:8000 -t public
```

The API now runs at **http://localhost:8000**.
Quick check: open http://localhost:8000 → you should see a JSON message.

> If your MySQL is not on port 3306 (MAMP often uses 8889), edit
> `backend/config/db.php` and change `$port`.

### 3. Frontend (Vue SPA)

```bash
cd frontend
npm install
npm run dev
```

Open the URL it prints (**http://localhost:5173**).

## Demo accounts

All seeded accounts use the password: **`password123`**

| Role      | Email                          |
|-----------|--------------------------------|
| Admin     | admin@university.edu           |
| Organizer | cs.dept@university.edu         |
| Organizer | career.services@university.edu |
| Student   | john.doe@university.edu        |

You can also register a new Student or Organizer from the Register page.

## Feature walkthrough (for the demo)

- **Auth** — Register / Login issue a JWT. The token is attached to every
  API request; protected routes are blocked without it (401) and by role
  (403).
- **Student** — Browse/search/filter events, register (capacity &
  duplicate checks enforced), view *My Registrations* (upcoming vs past),
  cancel, and edit profile.
- **Organizer** — Dashboard with live stats, full event CRUD
  (create/edit/delete own events only), and mark attendance for past
  events (log in as `cs.dept@university.edu` and open the seeded past
  event *Spring Mixer 2026*, or `career.services@university.edu` for
  *Resume Workshop*).
- **Admin** — Manage all users (edit role/status, delete) and view all
  events with utilization bars.

## User Manual

### Getting Started

1. Open the app at **http://localhost:5173**
2. Click **Register here** to create a new account, or use one of the demo accounts below.
3. All new accounts must use a **@utm.my** email address.

---

### Role: Student

#### Browsing Events
- After logging in you are taken to the **Events** page.
- Use the **search bar** to find events by title, venue, or organizer name.
- Use the **category pills** (Technology, Career, Cultural, etc.) to filter by type.
- Click an event card to view its full details — date, venue, capacity, and description.

#### Registering for an Event
- On the event detail page click **Register for this Event**.
- You cannot register for the same event twice.
- You cannot register if the event is already at full capacity.

#### My Registrations
- Click **My Registrations** in the navbar.
- **Upcoming** tab — shows confirmed future registrations. Click **Cancel** to withdraw.
- **Past** tab — shows completed events and whether you attended.

#### Profile
- Click **Profile** in the navbar to update your name, email, or password.

---

### Role: Organizer

> **Note:** New organizer accounts require admin approval before you can log in. After registering, wait for the admin to approve your account.

#### Dashboard
- After login you land on the **Organizer Dashboard** showing your event stats.
- Each event displays a **Status** badge:
  - **Pending** (yellow) — awaiting admin approval, not yet visible to students.
  - **Approved** (green) — live and visible to students.
  - **Rejected** (red) — not approved; you may edit and resubmit.

#### Creating an Event
- Click **Create New Event** on the dashboard.
- Fill in the title, description, date/time, venue, capacity, and category.
- The event date must be in the future.
- Once submitted the event is **pending** until an admin approves it.

#### Editing / Deleting an Event
- Click **Edit** next to any event to update its details.
- Click **Delete** to permanently remove an event and all its registrations.

#### Marking Attendance
- Click on an event title to open the **Attendance** page (only available for past events).
- Tick the checkbox next to each participant who attended and click **Save**.

---

### Role: Admin

> Admin accounts: `admin@university.edu` (password: `password123`) or the four UTM admin accounts (password: `12345678`).

#### User Management (`/admin/users`)
- View all users with stats: total, active, students, organizers, and **pending** accounts.
- Use the **Pending** filter pill to see organizer accounts awaiting approval.
- Click **Approve** next to a pending organizer to activate their account.
- Click **Edit** to change a user's name, email, role, or status.
- Click **Delete** to permanently remove a user and all their data.

#### Event Management (`/admin/events`)
- View **all** events regardless of status (pending, approved, rejected).
- Use the **status filter pills** (Pending / Approved / Rejected) to narrow the list.
- For **pending** events: click **Approve** to make the event live, or **Reject** to decline it.
- For **approved** events: click **Reject** to take it offline.
- For **rejected** events: click **Approve** to publish it.
- Click the trash icon to permanently delete an event.

---

### Demo Accounts

All seeded accounts use the password: **`password123`**

| Role      | Email                           |
|-----------|---------------------------------|
| Admin     | `admin@university.edu`          |
| Organizer | `cs.dept@university.edu`        |
| Organizer | `career.services@university.edu`|
| Student   | `john.doe@university.edu`       |

Additional admin accounts (password: **`12345678`**):

| Name  | Email           |
|-------|-----------------|
| Rith  | `rith@utm.my`   |
| Mus   | `mus@utm.my`    |
| Irfan | `irfan@utm.my`  |
| Allan | `allan@utm.my`  |

---

## API endpoints

```
POST   /api/auth/register
POST   /api/auth/login

GET    /api/events                       (public, supports ?category= &search=)
GET    /api/events/{id}                   (public)
GET    /api/organizer/events              (organizer/admin)
POST   /api/events                        (organizer/admin)
PUT    /api/events/{id}                   (owner organizer/admin)
DELETE /api/events/{id}                   (owner organizer/admin)
GET    /api/events/{id}/registrations     (owner organizer/admin)

GET    /api/registrations/my              (auth)
POST   /api/registrations                 (student)
PUT    /api/registrations/{id}/attend     (organizer/admin)
DELETE /api/registrations/{id}            (owner/admin)

GET    /api/profile                       (auth)
PUT    /api/profile                       (auth)
GET    /api/users                         (admin)
PUT    /api/users/{id}                    (admin)
DELETE /api/users/{id}                    (admin)

GET    /api/dashboard/summary             (admin)
```

## Notes

- The JWT is stored in **memory** (Pinia), not localStorage — a security
  choice from our proposal. Refreshing the browser logs you out. To make
  the token survive a refresh, set `PERSIST = true` in
  `frontend/src/stores/auth.js`.
- All database queries use **PDO prepared statements** to prevent SQL
  injection. Passwords are stored as **bcrypt** hashes.
- Validation runs on **both** the frontend and the backend.
