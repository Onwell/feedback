<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'ncc_feedback_db');

// Session configuration
define('SESSION_LIFETIME', 3600 * 24 * 7); // 7 days

// Database connection function
function getDB() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}

// Session management
function sessionStart() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function isLoggedIn() {
    sessionStart();
    return isset($_SESSION['ncc_user']) && !empty($_SESSION['ncc_user']);
}

function getSessionUser() {
    sessionStart();
    return $_SESSION['ncc_user'] ?? null;
}

function setSessionUser($user) {
    sessionStart();
    $_SESSION['ncc_user'] = $user;
}

function clearSession() {
    sessionStart();
    unset($_SESSION['ncc_user']);
    session_destroy();
}

// Authentication functions
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function redirect($url) {
    header("Location: $url");
    exit;
}

// Check if user has admin role
function isAdmin() {
    $user = getSessionUser();
    return $user && isset($user['role']) && $user['role'] === 'admin';
}
?>