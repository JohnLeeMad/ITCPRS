<?php
/**
 * includes/auth.php
 * ─────────────────────────────────────────────────────────────
 * Central authentication backend for ITCPRS.
 * Handles: registration, login, logout, session management,
 *          auth-checking, and role-based access control.
 *
 * ALL auth logic lives here. Pages just call the functions below.
 * Uses MySQLi (procedural style).
 * ─────────────────────────────────────────────────────────────
 */

require_once __DIR__ . '/../config/db.php';
// $conn is now available from db.php

// ─────────────────────────────────────────────────────────────
//  SESSION SETUP
// ─────────────────────────────────────────────────────────────

function auth_session_start(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

// ─────────────────────────────────────────────────────────────
//  ALLOWED ROLES
// ─────────────────────────────────────────────────────────────

define('ROLES', ['admin', 'secretary', 'staff', 'driver']);

// =============================================================
//  REGISTRATION
// =============================================================

/**
 * Validate all registration fields.
 * Returns an array of error strings (empty = all valid).
 */
function validate_registration(array $data): array {
    $errors = [];

    // Full name
    $full_name = trim($data['full_name'] ?? '');
    if ($full_name === '') {
        $errors[] = 'Full name is required.';
    } elseif (mb_strlen($full_name) > 150) {
        $errors[] = 'Full name must not exceed 150 characters.';
    }

    // Username – letters, numbers, dots, hyphens, underscores, 3–60 chars
    $username = trim($data['username'] ?? '');
    if ($username === '') {
        $errors[] = 'Username is required.';
    } elseif (!preg_match('/^[a-zA-Z0-9._\-]{3,60}$/', $username)) {
        $errors[] = 'Username must be 3–60 characters: letters, numbers, dots, hyphens, or underscores only.';
    }

    // Email
    $email = trim($data['email'] ?? '');
    if ($email === '') {
        $errors[] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }

    // Contact number
    $contact = trim($data['contact_number'] ?? '');
    if ($contact === '') {
        $errors[] = 'Contact number is required.';
    } elseif (!preg_match('/^[0-9+\-\s()]{7,20}$/', $contact)) {
        $errors[] = 'Enter a valid contact number.';
    }

    // Role
    if (!in_array($data['role'] ?? '', ROLES, true)) {
        $errors[] = 'Please select a valid role.';
    }

    // Password rules
    $password = $data['password'] ?? '';
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter.';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one number.';
    }

    // Confirm password
    if ($password !== ($data['confirm_password'] ?? '')) {
        $errors[] = 'Passwords do not match.';
    }

    return $errors;
}

/**
 * Register a new user.
 *
 * Returns: ['success' => true,  'user_id' => int]
 *       or ['success' => false, 'errors'  => string[]]
 */
function register_user(array $data): array {
    global $conn;

    // 1. Validate input
    $errors = validate_registration($data);
    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }

    $full_name = trim($data['full_name']);
    $username  = trim($data['username']);
    $email     = strtolower(trim($data['email']));
    $contact   = trim($data['contact_number']);
    $role      = $data['role'];
    $password  = $data['password'];

    // 2. Check uniqueness (username OR email already exists)
    $stmt = mysqli_prepare($conn,
        "SELECT id, username, email FROM users WHERE username = ? OR email = ? LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, 'ss', $username, $email);
    mysqli_stmt_execute($stmt);
    $result   = mysqli_stmt_get_result($stmt);
    $existing = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($existing) {
        if ($existing['username'] === $username) {
            return ['success' => false, 'errors' => ['That username is already taken. Please choose another.']];
        }
        return ['success' => false, 'errors' => ['An account with that email address already exists.']];
    }

    // 3. Hash the password (bcrypt, cost 12)
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    // 4. Insert the new user
    $stmt = mysqli_prepare($conn,
        "INSERT INTO users (full_name, username, email, contact_number, password_hash, role)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    mysqli_stmt_bind_param($stmt, 'ssssss', $full_name, $username, $email, $contact, $hash, $role);

    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return ['success' => false, 'errors' => ['Registration failed due to a server error. Please try again.']];
    }

    $new_id = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    return ['success' => true, 'user_id' => (int) $new_id];
}

// =============================================================
//  LOGIN
// =============================================================

/**
 * Attempt login with username OR email + password.
 * Pass $remember = true to extend the session cookie to 30 days.
 *
 * Returns: ['success' => true,  'user' => [...]]
 *       or ['success' => false, 'error' => '...']
 */
function login_user(string $identifier, string $password, bool $remember = false): array {
    global $conn;

    if (trim($identifier) === '' || $password === '') {
        return ['success' => false, 'error' => 'Please fill in all fields.'];
    }

    $identifier = trim($identifier);

    // Accept username OR email in one query
    $stmt = mysqli_prepare($conn,
        "SELECT id, full_name, username, email, contact_number, password_hash, role, status
         FROM users
         WHERE email = ? OR username = ?
         LIMIT 1"
    );
    $email_lower = strtolower($identifier);
    mysqli_stmt_bind_param($stmt, 'ss', $email_lower, $identifier);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user   = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    // Intentionally vague – prevents username/email enumeration
    if (!$user || !password_verify($password, $user['password_hash'])) {
        return ['success' => false, 'error' => 'Invalid username/email or password. Please try again.'];
    }

    if ($user['status'] !== 'active') {
        return ['success' => false, 'error' => 'Your account is inactive or suspended. Please contact the administrator.'];
    }

    // Regenerate session ID to prevent session fixation attacks
    session_regenerate_id(true);

    // Store safe subset in session – never store the password hash
    $_SESSION['user'] = [
        'id'             => (int) $user['id'],
        'full_name'      => $user['full_name'],
        'username'       => $user['username'],
        'email'          => $user['email'],
        'contact_number' => $user['contact_number'],
        'role'           => $user['role'],
    ];
    $_SESSION['logged_in']     = true;
    $_SESSION['login_time']    = time();
    $_SESSION['last_activity'] = time();

    // Update last_login timestamp in DB
    $upd = mysqli_prepare($conn, "UPDATE users SET last_login = NOW() WHERE id = ?");
    mysqli_stmt_bind_param($upd, 'i', $user['id']);
    mysqli_stmt_execute($upd);
    mysqli_stmt_close($upd);

    // Remember-me: extend cookie to 30 days
    if ($remember) {
        setcookie(session_name(), session_id(), [
            'expires'  => time() + (30 * 24 * 60 * 60),
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    return ['success' => true, 'user' => $_SESSION['user']];
}

// =============================================================
//  LOGOUT
// =============================================================

/**
 * Destroy the session and redirect to the login page.
 */
function logout_user(string $redirect = '/modules/auth/login.php'): void {
    $_SESSION = [];

    // Delete the session cookie
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(
            session_name(), '',
            time() - 42000,
            $p['path'],
            $p['domain'],
            $p['secure'],
            $p['httponly']
        );
    }

    session_destroy();
    header('Location: ' . $redirect);
    exit;
}

// =============================================================
//  AUTH CHECKERS  — use at the top of every protected page
// =============================================================

/**
 * Redirect to login if the user is not authenticated.
 * Also enforces a 2-hour idle timeout.
 *
 * Usage:  require_login();
 */
function require_login(string $login_url = '/modules/auth/login.php'): void {
    auth_session_start();

    if (empty($_SESSION['logged_in']) || empty($_SESSION['user'])) {
        // Remember where they were trying to go
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '/';
        header('Location: ' . $login_url);
        exit;
    }

    // Idle timeout: 2 hours of inactivity logs the user out
    $timeout = 2 * 60 * 60;
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
        logout_user($login_url . '?reason=timeout');
    }

    $_SESSION['last_activity'] = time();
}

/**
 * Restrict a page to one or more roles.
 * Redirects to 403 page if the current user's role doesn't match.
 *
 * Usage:  require_role('admin');
 *         require_role(['admin', 'secretary']);
 */
function require_role(string|array $roles, string $denied_url = '/403.php'): void {
    require_login();

    $allowed = is_array($roles) ? $roles : [$roles];
    $current = $_SESSION['user']['role'] ?? '';

    if (!in_array($current, $allowed, true)) {
        header('Location: ' . $denied_url);
        exit;
    }
}

// =============================================================
//  CONVENIENCE HELPERS
// =============================================================

/** True if the user has an active logged-in session. */
function is_logged_in(): bool {
    return !empty($_SESSION['logged_in']) && !empty($_SESSION['user']);
}

/** Returns the full session user array, or null. */
function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

/** Returns the current user's role string, or null. */
function current_role(): ?string {
    return $_SESSION['user']['role'] ?? null;
}

/** True if the current user has one of the given roles. */
function has_role(string|array $roles): bool {
    $allowed = is_array($roles) ? $roles : [$roles];
    return in_array(current_role(), $allowed, true);
}

/** Shortcut: true if current user is admin. */
function is_admin(): bool {
    return current_role() === 'admin';
}

// =============================================================
//  CSRF PROTECTION
// =============================================================

/**
 * Returns a per-session CSRF token (generated once).
 *
 * Add to every form:
 *   <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify the CSRF token submitted with a POST form.
 * Call at the very top of every POST handler.
 * Terminates with HTTP 403 if the token is invalid.
 */
function verify_csrf(): void {
    $submitted = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $submitted)) {
        http_response_code(403);
        die('Invalid request. Please go back and try again.');
    }
}