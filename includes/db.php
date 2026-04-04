<?php
// includes/db.php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'sneaker_shop');

// Session names - admin uses separate cookie to allow simultaneous login
define('USER_SESSION_NAME',  'sneaker_user_sess');
define('ADMIN_SESSION_NAME', 'sneaker_admin_sess');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset('utf8mb4');

if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

// Helper functions
function sanitize($conn, $str) {
    return $conn->real_escape_string(trim($str));
}

function getSellPrice($import_price, $profit_rate) {
    return $import_price * (1 + $profit_rate / 100);
}

function formatPrice($price) {
    return number_format($price, 0, ',', '.') . ' ₫';
}

function isLoggedIn() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'customer') {
        return false;
    }
    // Check DB status - if locked, clear session data but do NOT call session_destroy()
    // (session_destroy sends headers which causes "headers already sent" if called after HTML)
    global $conn;
    $uid = (int)$_SESSION['user_id'];
    $result = $conn->query("SELECT status FROM users WHERE id=$uid AND role='customer' LIMIT 1");
    if (!$result || $result->num_rows === 0) {
        $_SESSION = [];
        return false;
    }
    $row = $result->fetch_assoc();
    if ($row['status'] === 'locked') {
        $_SESSION = [];
        return false;
    }
    return true;
}

// Call this BEFORE any HTML output to fully destroy session of locked/deleted users
function kickIfLocked() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'customer') {
        return;
    }
    global $conn;
    $uid = (int)$_SESSION['user_id'];
    $result = $conn->query("SELECT status FROM users WHERE id=$uid AND role='customer' LIMIT 1");
    if (!$result || $result->num_rows === 0 || $result->fetch_assoc()['status'] === 'locked') {
        $_SESSION = [];
        session_destroy();
        redirect('/sneaker_shop/login.php');
    }
}

function isAdmin() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function generateCode($prefix) {
    global $conn;
    // Tạo mã theo format: PREFIX + YYYYMMDD + số thứ tự 3 chữ số (toàn cục)
    // Ví dụ: DH20260328001, PN20260328001
    $date = date('Ymd');
    if ($prefix === 'DH') {
        $count = $conn->query("SELECT COUNT(*) as c FROM orders")->fetch_assoc()['c'];
    } elseif ($prefix === 'PN') {
        $count = $conn->query("SELECT COUNT(*) as c FROM import_receipts")->fetch_assoc()['c'];
    } else {
        return $prefix . date('YmdHis') . rand(100, 999);
    }
    $seq = str_pad($count + 1, 3, '0', STR_PAD_LEFT);
    return $prefix . $date . $seq;
}

// Start USER session (only if not already started by admin side)
// Admin files set their own session name before including db.php
if (session_status() === PHP_SESSION_NONE) {
    session_name(USER_SESSION_NAME);
    session_start();
}

// Auto-kick locked/deleted users BEFORE any HTML is output.
// This is safe because db.php is always included before header.php outputs HTML.
// We only do this for user sessions (not admin).
if (session_name() === USER_SESSION_NAME) {
    kickIfLocked();
}
?>