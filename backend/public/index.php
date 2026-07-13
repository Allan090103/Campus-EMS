<?php
/**
 * CampusEMS REST API  -  PHP Slim 4
 * SECJ3483 Web Technology Group Project
 *
 * All routes are defined in this single file (following the structure of
 * our course sample), grouped by feature with clear section comments:
 *
 *   AUTH         /api/auth/register, /api/auth/login
 *   EVENTS       full CRUD  (GET/POST/PUT/DELETE)
 *   REGISTRATIONS register, cancel, list, mark attendance
 *   USERS/PROFILE own profile + admin user management
 *   DASHBOARD    admin platform summary
 *
 * Run:  composer install
 *       php -S localhost:8000 -t public
 */

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

use App\Helpers\Json;
use App\Helpers\Token;
use App\Middleware\CorsMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/db.php';

$config = require __DIR__ . '/../config/config.php';

$app = AppFactory::create();
$app->addBodyParsingMiddleware();          // parses JSON request bodies into arrays
$app->add(new CorsMiddleware());           // CORS for the Vue dev server
$app->addErrorMiddleware(true, true, true);

// Small reusable middleware instances.
$auth          = new AuthMiddleware($config);
$adminOnly     = new RoleMiddleware(['admin']);
$organizerSide = new RoleMiddleware(['organizer', 'admin']);

// Helper: read JSON body as array.
$body = fn(Request $r): array => (array) ($r->getParsedBody() ?? []);

/* =====================================================================
 *  ROOT
 * ===================================================================== */
$app->get('/', function (Request $req, Response $res) {
    return Json::write($res, ['message' => 'CampusEMS API is running.']);
});

/* =====================================================================
 *  AUTH
 * ===================================================================== */

// Register a new account (student or organizer only - never admin).
$app->post('/api/auth/register', function (Request $req, Response $res) use ($body, $config) {
    $d = $body($req);
    $name     = trim($d['name'] ?? '');
    $email    = trim($d['email'] ?? '');
    $password = $d['password'] ?? '';
    $role     = $d['role'] ?? 'student';

    // ---- Backend validation (never trust the frontend) ----
    if ($name === '' || $email === '' || $password === '') {
        return Json::error($res, 'Name, email and password are required.', 422);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return Json::error($res, 'Please provide a valid email address.', 422);
    }
    if (strlen($password) < 8) {
        return Json::error($res, 'Password must be at least 8 characters.', 422);
    }
    if (!in_array($role, ['student', 'organizer'], true)) {
        return Json::error($res, 'Role must be student or organizer.', 422);
    }
    if (!str_ends_with(strtolower($email), '@utm.my')) {
        return Json::error($res, 'Only @utm.my email addresses are allowed.', 422);
    }

    $pdo = getDBConnection();

    // Unique email check.
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return Json::error($res, 'An account with this email already exists.', 409);
    }

    // Organizers start as pending until an admin approves them.
    $status = ($role === 'organizer') ? 'pending' : 'active';

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare(
        'INSERT INTO users (name, email, password_hash, role, status) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$name, $email, $hash, $role, $status]);
    $id = (int) $pdo->lastInsertId();

    if ($status === 'pending') {
        return Json::write($res, [
            'pending' => true,
            'message' => 'Your organizer account has been submitted and is awaiting admin approval.',
        ], 202);
    }

    $user = ['id' => $id, 'name' => $name, 'email' => $email, 'role' => $role];
    $token = Token::create($user, $config);

    return Json::write($res, ['token' => $token, 'user' => $user], 201);
});

// Login: verify credentials, return a signed JWT.
$app->post('/api/auth/login', function (Request $req, Response $res) use ($body, $config) {
    $d = $body($req);
    $email    = trim($d['email'] ?? '');
    $password = $d['password'] ?? '';

    if ($email === '' || $password === '') {
        return Json::error($res, 'Email and password are required.', 422);
    }

    $pdo = getDBConnection();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return Json::error($res, 'Invalid email or password.', 401);
    }
    if ($user['status'] === 'pending') {
        return Json::error($res, 'Your account is pending admin approval.', 403);
    }
    if ($user['status'] === 'inactive') {
        return Json::error($res, 'This account has been deactivated. Contact an administrator.', 403);
    }

    $token = Token::create($user, $config);
    return Json::write($res, [
        'token' => $token,
        'user'  => [
            'id' => (int) $user['id'], 'name' => $user['name'],
            'email' => $user['email'], 'role' => $user['role'],
        ],
    ]);
});

/* =====================================================================
 *  EVENTS  -  full CRUD
 * ===================================================================== */

// Reusable SELECT that attaches organizer name + live registration count.
function eventsBaseQuery(): string
{
    return "SELECT e.*, u.name AS organizer_name,
                   (SELECT COUNT(*) FROM registrations r
                      WHERE r.event_id = e.id AND r.status = 'confirmed') AS registered_count
            FROM events e
            JOIN users u ON u.id = e.organizer_id";
}

function optionalUser(Request $req, array $config): ?array
{
    $header = $req->getHeaderLine('Authorization');

    if (!preg_match('/Bearer\s+(.+)/i', $header, $m)) {
        return null;
    }

    try {
        return Token::decode($m[1], $config);
    } catch (\Throwable $e) {
        return null;
    }
}

// GET /api/events  (public) - list upcoming approved events only, optional ?category= & ?search=
$app->get('/api/events', function (Request $req, Response $res) {
    $params = $req->getQueryParams();
    $sql = eventsBaseQuery();
    $where = ["e.status = 'approved'", 'e.event_datetime > NOW()'];
    $args  = [];

    if (!empty($params['category']) && $params['category'] !== 'All') {
        $where[] = 'e.category = ?';
        $args[]  = $params['category'];
    }
    if (!empty($params['search'])) {
        $where[] = '(e.title LIKE ? OR e.venue LIKE ? OR u.name LIKE ?)';
        $term = '%' . $params['search'] . '%';
        array_push($args, $term, $term, $term);
    }
    $sql .= ' WHERE ' . implode(' AND ', $where);
    $sql .= ' ORDER BY e.event_datetime ASC';

    $pdo = getDBConnection();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($args);
    return Json::write($res, $stmt->fetchAll());
});

// GET /api/admin/events  (admin) - all events regardless of status
$app->get('/api/admin/events', function (Request $req, Response $res) {
    $params = $req->getQueryParams();
    $sql = eventsBaseQuery();
    $where = [];
    $args  = [];

    if (!empty($params['status']) && $params['status'] !== 'All') {
        $where[] = 'e.status = ?';
        $args[]  = $params['status'];
    }
    if (!empty($params['category']) && $params['category'] !== 'All') {
        $where[] = 'e.category = ?';
        $args[]  = $params['category'];
    }
    if (!empty($params['search'])) {
        $where[] = '(e.title LIKE ? OR e.venue LIKE ? OR u.name LIKE ?)';
        $term = '%' . $params['search'] . '%';
        array_push($args, $term, $term, $term);
    }
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY e.event_datetime ASC';

    $pdo = getDBConnection();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($args);
    return Json::write($res, $stmt->fetchAll());
})->add($adminOnly)->add($auth);

// GET /api/organizer/events  (organizer/admin) - only the caller's own events
$app->get('/api/organizer/events', function (Request $req, Response $res) {
    $user = $req->getAttribute('user');
    $pdo  = getDBConnection();
    $stmt = $pdo->prepare(eventsBaseQuery() . ' WHERE e.organizer_id = ? ORDER BY e.event_datetime ASC');
    $stmt->execute([$user['sub']]);
    return Json::write($res, $stmt->fetchAll());
})->add($organizerSide)->add($auth);

// GET /api/events/{id}  (public) - single event
$app->get('/api/events/{id}', function (Request $req, Response $res, array $a) use ($config) {
    $pdo  = getDBConnection();
    $stmt = $pdo->prepare(eventsBaseQuery() . ' WHERE e.id = ?');
    $stmt->execute([$a['id']]);
    $event = $stmt->fetch();
    if (!$event) {
        return Json::error($res, 'Event not found.', 404);
    }

    $user = optionalUser($req, $config);
    $isAdmin = ($user['role'] ?? null) === 'admin';
    $isOwnerOrganizer = ($user['role'] ?? null) === 'organizer'
        && (int) $event['organizer_id'] === (int) $user['sub'];
    $isPubliclyVisible = $event['status'] === 'approved'
        && strtotime($event['event_datetime']) > time();

    if (!$isPubliclyVisible && !$isAdmin && !$isOwnerOrganizer) {
        return Json::error($res, 'Event not found.', 404);
    }

    return Json::write($res, $event);
});

// Shared validation for create/update.
function validateEvent(array $d): ?string
{
    foreach (['title', 'description', 'event_datetime', 'venue', 'capacity', 'category'] as $f) {
        if (!isset($d[$f]) || $d[$f] === '') {
            return "Field '$f' is required.";
        }
    }
    if (!is_numeric($d['capacity']) || (int) $d['capacity'] <= 0) {
        return 'Capacity must be a number greater than 0.';
    }
    if (strtotime($d['event_datetime']) === false) {
        return 'Event date/time is invalid.';
    }
    $valid = ['Technology', 'Career', 'Cultural', 'Arts', 'Sports', 'Social'];
    if (!in_array($d['category'], $valid, true)) {
        return 'Invalid category.';
    }
    return null;
}

// POST /api/events  (organizer/admin) - create
$app->post('/api/events', function (Request $req, Response $res) use ($body) {
    $user = $req->getAttribute('user');
    $d = $body($req);

    if ($err = validateEvent($d)) {
        return Json::error($res, $err, 422);
    }
    // Future-date rule (only enforced on create).
    if (strtotime($d['event_datetime']) <= time()) {
        return Json::error($res, 'Event date must be in the future.', 422);
    }

    $pdo = getDBConnection();
    $stmt = $pdo->prepare(
        'INSERT INTO events (title, description, event_datetime, venue, capacity, category, organizer_id, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    // organizer_id comes from the TOKEN, never from the request body.
    // New events start as pending until an admin approves them.
    $stmt->execute([
        $d['title'], $d['description'], $d['event_datetime'],
        $d['venue'], (int) $d['capacity'], $d['category'], $user['sub'], 'pending',
    ]);
    $id = (int) $pdo->lastInsertId();

    $stmt = $pdo->prepare(eventsBaseQuery() . ' WHERE e.id = ?');
    $stmt->execute([$id]);
    return Json::write($res, $stmt->fetch(), 201);
})->add($organizerSide)->add($auth);

// PUT /api/events/{id}  (organizer-owner / admin) - update
$app->put('/api/events/{id}', function (Request $req, Response $res, array $a) use ($body) {
    $user = $req->getAttribute('user');
    $pdo  = getDBConnection();

    $stmt = $pdo->prepare('SELECT * FROM events WHERE id = ?');
    $stmt->execute([$a['id']]);
    $event = $stmt->fetch();
    if (!$event) {
        return Json::error($res, 'Event not found.', 404);
    }
    // Ownership: organizers may only edit their own events.
    if ($user['role'] !== 'admin' && (int) $event['organizer_id'] !== (int) $user['sub']) {
        return Json::error($res, 'You can only edit your own events.', 403);
    }

    $d = $body($req);
    if ($err = validateEvent($d)) {
        return Json::error($res, $err, 422);
    }

    $stmt = $pdo->prepare(
        'UPDATE events SET title=?, description=?, event_datetime=?, venue=?, capacity=?, category=?
         WHERE id=?'
    );
    $stmt->execute([
        $d['title'], $d['description'], $d['event_datetime'],
        $d['venue'], (int) $d['capacity'], $d['category'], $a['id'],
    ]);

    $stmt = $pdo->prepare(eventsBaseQuery() . ' WHERE e.id = ?');
    $stmt->execute([$a['id']]);
    return Json::write($res, $stmt->fetch());
})->add($organizerSide)->add($auth);

// DELETE /api/events/{id}  (organizer-owner / admin)
$app->delete('/api/events/{id}', function (Request $req, Response $res, array $a) {
    $user = $req->getAttribute('user');
    $pdo  = getDBConnection();

    $stmt = $pdo->prepare('SELECT organizer_id FROM events WHERE id = ?');
    $stmt->execute([$a['id']]);
    $event = $stmt->fetch();
    if (!$event) {
        return Json::error($res, 'Event not found.', 404);
    }
    if ($user['role'] !== 'admin' && (int) $event['organizer_id'] !== (int) $user['sub']) {
        return Json::error($res, 'You can only delete your own events.', 403);
    }

    $pdo->prepare('DELETE FROM events WHERE id = ?')->execute([$a['id']]);
    return Json::write($res, ['message' => 'Event deleted.']);
})->add($organizerSide)->add($auth);

// PUT /api/events/{id}/status  (admin only) - approve or reject an event
$app->put('/api/events/{id}/status', function (Request $req, Response $res, array $a) use ($body) {
    $d      = $body($req);
    $status = $d['status'] ?? '';

    if (!in_array($status, ['approved', 'rejected', 'pending'], true)) {
        return Json::error($res, 'Status must be approved, rejected, or pending.', 422);
    }

    $pdo  = getDBConnection();
    $stmt = $pdo->prepare('SELECT id FROM events WHERE id = ?');
    $stmt->execute([$a['id']]);
    if (!$stmt->fetch()) {
        return Json::error($res, 'Event not found.', 404);
    }

    $pdo->prepare('UPDATE events SET status = ? WHERE id = ?')->execute([$status, $a['id']]);

    $stmt = $pdo->prepare(eventsBaseQuery() . ' WHERE e.id = ?');
    $stmt->execute([$a['id']]);
    return Json::write($res, $stmt->fetch());
})->add($adminOnly)->add($auth);

// GET /api/events/{id}/registrations  (organizer-owner / admin) - participant list
$app->get('/api/events/{id}/registrations', function (Request $req, Response $res, array $a) {
    $user = $req->getAttribute('user');
    $pdo  = getDBConnection();

    $stmt = $pdo->prepare('SELECT * FROM events WHERE id = ?');
    $stmt->execute([$a['id']]);
    $event = $stmt->fetch();
    if (!$event) {
        return Json::error($res, 'Event not found.', 404);
    }
    if ($user['role'] !== 'admin' && (int) $event['organizer_id'] !== (int) $user['sub']) {
        return Json::error($res, 'You can only view participants for your own events.', 403);
    }

    $stmt = $pdo->prepare(
        "SELECT r.id, r.attended, r.registered_at, u.name, u.email
         FROM registrations r
         JOIN users u ON u.id = r.user_id
         WHERE r.event_id = ? AND r.status = 'confirmed'
         ORDER BY u.name ASC"
    );
    $stmt->execute([$a['id']]);
    return Json::write($res, ['event' => $event, 'participants' => $stmt->fetchAll()]);
})->add($organizerSide)->add($auth);

/* =====================================================================
 *  REGISTRATIONS
 * ===================================================================== */

// GET /api/registrations/my  (any authed user) - own registrations + event info
$app->get('/api/registrations/my', function (Request $req, Response $res) {
    $user = $req->getAttribute('user');
    $pdo  = getDBConnection();
    $stmt = $pdo->prepare(
        "SELECT r.id, r.status, r.attended, r.registered_at,
                e.id AS event_id, e.title, e.event_datetime, e.venue, e.category
         FROM registrations r
         JOIN events e ON e.id = r.event_id
         WHERE r.user_id = ? AND r.status = 'confirmed'
         ORDER BY e.event_datetime DESC"
    );
    $stmt->execute([$user['sub']]);
    return Json::write($res, $stmt->fetchAll());
})->add($auth);

// POST /api/registrations  (student) - register for an event
$app->post('/api/registrations', function (Request $req, Response $res) use ($body) {
    $user = $req->getAttribute('user');
    $d = $body($req);
    $eventId = (int) ($d['event_id'] ?? 0);
    if (!$eventId) {
        return Json::error($res, 'event_id is required.', 422);
    }

    $pdo = getDBConnection();

    $stmt = $pdo->prepare('SELECT * FROM events WHERE id = ?');
    $stmt->execute([$eventId]);
    $event = $stmt->fetch();
    if (!$event) {
        return Json::error($res, 'Event not found.', 404);
    }
    if ($event['status'] !== 'approved') {
        return Json::error($res, 'This event is not open for registration.', 409);
    }
    if (strtotime($event['event_datetime']) <= time()) {
        return Json::error($res, 'Registration is closed for past events.', 409);
    }

    // Business rule: cannot register for the same event twice.
    $stmt = $pdo->prepare('SELECT id FROM registrations WHERE user_id = ? AND event_id = ?');
    $stmt->execute([$user['sub'], $eventId]);
    if ($stmt->fetch()) {
        return Json::error($res, 'You are already registered for this event.', 409);
    }

    // Business rule: cannot register once capacity is full.
    $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM registrations WHERE event_id = ? AND status='confirmed'");
    $stmt->execute([$eventId]);
    if ((int) $stmt->fetch()['c'] >= (int) $event['capacity']) {
        return Json::error($res, 'This event is full.', 409);
    }

    $stmt = $pdo->prepare('INSERT INTO registrations (user_id, event_id) VALUES (?, ?)');
    $stmt->execute([$user['sub'], $eventId]);
    return Json::write($res, ['message' => 'Registered successfully.', 'id' => (int) $pdo->lastInsertId()], 201);
})->add(new RoleMiddleware(['student', 'admin']))->add($auth);

// PUT /api/registrations/{id}/attend  (organizer/admin) - mark attendance
$app->put('/api/registrations/{id}/attend', function (Request $req, Response $res, array $a) use ($body) {
    $user = $req->getAttribute('user');
    $pdo  = getDBConnection();

    // Load registration + its event so we can check ownership and date.
    $stmt = $pdo->prepare(
        'SELECT r.id, e.organizer_id, e.event_datetime
         FROM registrations r JOIN events e ON e.id = r.event_id
         WHERE r.id = ?'
    );
    $stmt->execute([$a['id']]);
    $row = $stmt->fetch();
    if (!$row) {
        return Json::error($res, 'Registration not found.', 404);
    }
    if ($user['role'] !== 'admin' && (int) $row['organizer_id'] !== (int) $user['sub']) {
        return Json::error($res, 'You can only mark attendance for your own events.', 403);
    }
    // Business rule: attendance can only be marked after the event has happened.
    if (strtotime($row['event_datetime']) > time()) {
        return Json::error($res, 'Attendance can only be marked after the event date.', 422);
    }

    $attended = !empty($body($req)['attended']) ? 1 : 0;
    $pdo->prepare('UPDATE registrations SET attended = ? WHERE id = ?')->execute([$attended, $a['id']]);
    return Json::write($res, ['message' => 'Attendance updated.', 'attended' => (bool) $attended]);
})->add($organizerSide)->add($auth);

// DELETE /api/registrations/{id}  (owner student / admin) - cancel registration
$app->delete('/api/registrations/{id}', function (Request $req, Response $res, array $a) {
    $user = $req->getAttribute('user');
    $pdo  = getDBConnection();

    $stmt = $pdo->prepare('SELECT user_id FROM registrations WHERE id = ?');
    $stmt->execute([$a['id']]);
    $reg = $stmt->fetch();
    if (!$reg) {
        return Json::error($res, 'Registration not found.', 404);
    }
    if ($user['role'] !== 'admin' && (int) $reg['user_id'] !== (int) $user['sub']) {
        return Json::error($res, 'You can only cancel your own registration.', 403);
    }

    $pdo->prepare('DELETE FROM registrations WHERE id = ?')->execute([$a['id']]);
    return Json::write($res, ['message' => 'Registration cancelled.']);
})->add($auth);

/* =====================================================================
 *  PROFILE  (own account)
 * ===================================================================== */

// GET /api/profile
$app->get('/api/profile', function (Request $req, Response $res) {
    $user = $req->getAttribute('user');
    $pdo  = getDBConnection();
    $stmt = $pdo->prepare('SELECT id, name, email, role, status, created_at FROM users WHERE id = ?');
    $stmt->execute([$user['sub']]);
    return Json::write($res, $stmt->fetch());
})->add($auth);

// PUT /api/profile - update own name/email, optional password change
$app->put('/api/profile', function (Request $req, Response $res) use ($body) {
    $user = $req->getAttribute('user');
    $d = $body($req);
    $name  = trim($d['name'] ?? '');
    $email = trim($d['email'] ?? '');

    if ($name === '' || $email === '') {
        return Json::error($res, 'Name and email are required.', 422);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return Json::error($res, 'Please provide a valid email address.', 422);
    }

    $pdo = getDBConnection();
    // Email must stay unique (ignoring the user's own row).
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ?');
    $stmt->execute([$email, $user['sub']]);
    if ($stmt->fetch()) {
        return Json::error($res, 'That email is already in use.', 409);
    }

    // Optional password change.
    if (!empty($d['password'])) {
        if (strlen($d['password']) < 8) {
            return Json::error($res, 'New password must be at least 8 characters.', 422);
        }
        $hash = password_hash($d['password'], PASSWORD_BCRYPT);
        $pdo->prepare('UPDATE users SET name=?, email=?, password_hash=? WHERE id=?')
            ->execute([$name, $email, $hash, $user['sub']]);
    } else {
        $pdo->prepare('UPDATE users SET name=?, email=? WHERE id=?')
            ->execute([$name, $email, $user['sub']]);
    }

    $stmt = $pdo->prepare('SELECT id, name, email, role, status FROM users WHERE id = ?');
    $stmt->execute([$user['sub']]);
    return Json::write($res, $stmt->fetch());
})->add($auth);

/* =====================================================================
 *  USERS  (admin only)
 * ===================================================================== */

// GET /api/users - all users + an "events" count (created for organizers,
// registered for students) used by the admin user-management table.
$app->get('/api/users', function (Request $req, Response $res) {
    $pdo = getDBConnection();
    $sql = "SELECT u.id, u.name, u.email, u.role, u.status, u.created_at,
                   CASE
                     WHEN u.role = 'organizer'
                       THEN (SELECT COUNT(*) FROM events e WHERE e.organizer_id = u.id)
                     ELSE (SELECT COUNT(*) FROM registrations r WHERE r.user_id = u.id AND r.status='confirmed')
                   END AS event_count
            FROM users u
            ORDER BY u.created_at ASC";
    return Json::write($res, $pdo->query($sql)->fetchAll());
})->add($adminOnly)->add($auth);

// PUT /api/users/{id} - admin updates a user's role and/or status
$app->put('/api/users/{id}', function (Request $req, Response $res, array $a) use ($body) {
    $d = $body($req);
    $pdo = getDBConnection();

    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$a['id']]);
    $user = $stmt->fetch();
    if (!$user) {
        return Json::error($res, 'User not found.', 404);
    }

    $name   = trim($d['name']   ?? $user['name']);
    $email  = trim($d['email']  ?? $user['email']);
    $role   = $d['role']        ?? $user['role'];
    $status = $d['status']      ?? $user['status'];

    if (!in_array($role, ['student', 'organizer', 'admin'], true)) {
        return Json::error($res, 'Invalid role.', 422);
    }
    if (!in_array($status, ['active', 'inactive'], true)) {
        return Json::error($res, 'Invalid status.', 422);
    }

    $pdo->prepare('UPDATE users SET name=?, email=?, role=?, status=? WHERE id=?')
        ->execute([$name, $email, $role, $status, $a['id']]);

    $stmt = $pdo->prepare('SELECT id, name, email, role, status FROM users WHERE id = ?');
    $stmt->execute([$a['id']]);
    return Json::write($res, $stmt->fetch());
})->add($adminOnly)->add($auth);

// DELETE /api/users/{id} - admin deletes a user
$app->delete('/api/users/{id}', function (Request $req, Response $res, array $a) {
    $admin = $req->getAttribute('user');
    if ((int) $a['id'] === (int) $admin['sub']) {
        return Json::error($res, 'You cannot delete your own admin account.', 422);
    }
    $pdo = getDBConnection();
    $stmt = $pdo->prepare('SELECT id FROM users WHERE id = ?');
    $stmt->execute([$a['id']]);
    if (!$stmt->fetch()) {
        return Json::error($res, 'User not found.', 404);
    }
    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$a['id']]);
    return Json::write($res, ['message' => 'User deleted.']);
})->add($adminOnly)->add($auth);

/* =====================================================================
 *  DASHBOARD  (admin platform summary)
 * ===================================================================== */
$app->get('/api/dashboard/summary', function (Request $req, Response $res) {
    $pdo = getDBConnection();
    $totalUsers   = (int) $pdo->query('SELECT COUNT(*) c FROM users')->fetch()['c'];
    $totalEvents  = (int) $pdo->query('SELECT COUNT(*) c FROM events')->fetch()['c'];
    $totalRegs    = (int) $pdo->query("SELECT COUNT(*) c FROM registrations WHERE status='confirmed'")->fetch()['c'];
    $totalCap     = (int) ($pdo->query('SELECT COALESCE(SUM(capacity),0) c FROM events')->fetch()['c']);
    $upcoming     = (int) $pdo->query('SELECT COUNT(*) c FROM events WHERE event_datetime > NOW()')->fetch()['c'];

    return Json::write($res, [
        'total_users'         => $totalUsers,
        'total_events'        => $totalEvents,
        'total_registrations' => $totalRegs,
        'total_capacity'      => $totalCap,
        'upcoming_events'     => $upcoming,
        'avg_utilization'     => $totalCap > 0 ? round($totalRegs / $totalCap * 100, 1) : 0,
    ]);
})->add($adminOnly)->add($auth);

$app->run();
