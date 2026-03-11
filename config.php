<?php
/**
 * Bluemoon - Central Configuration File
 * Update only this file when deploying to cPanel / production.
 */

// ─── Environment ────────────────────────────────────────────────────────────
define('ENVIRONMENT', 'development'); // 'development' | 'production'

// ─── Database ────────────────────────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'bluemoon_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ─── Site Settings ───────────────────────────────────────────────────────────
define('SITE_NAME', 'Bluemoon');
define('SITE_TAGLINE', 'Taste the night, delivered right.');
define('SITE_EMAIL', 'hello@bluemoon.com');
define('SITE_PHONE', '+91 9876543210');
define('SITE_ADDRESS', '123 Moon Street, Kolkata, West Bengal 700001');
define('CURRENCY_SYMBOL', '₹');
define('TIMEZONE', 'Asia/Kolkata');

// ─── Base URL (auto-detects local vs production) ─────────────────────────────
if (ENVIRONMENT === 'development') {
    define('BASE_URL', 'http://localhost/bluemoon');
} else {
    define('BASE_URL', 'https://yourdomain.com'); // ← Change this for cPanel
}

// ─── File Paths ───────────────────────────────────────────────────────────────
define('ROOT_PATH', __DIR__);
define('UPLOAD_PATH', ROOT_PATH . '/assets/uploads');
define('PRODUCT_IMG_PATH', UPLOAD_PATH . '/products/');
define('SCREENSHOT_PATH', UPLOAD_PATH . '/screenshots/');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5 MB

// ─── Session & Security ──────────────────────────────────────────────────────
define('SESSION_NAME', 'bluemoon_session');
define('SESSION_LIFETIME', 7200); // 2 hours
define('BCRYPT_COST', 12);

// ─── OTP Settings ────────────────────────────────────────────────────────────
define('OTP_EXPIRY_MINUTES', 10);
define('OTP_LENGTH', 6);
define('OTP_RATE_LIMIT', 5); // max requests per hour

// ─── Order Settings ──────────────────────────────────────────────────────────
define('MIN_ORDER_AMOUNT', 100);  // ₹100
define('FREE_DELIVERY_ABOVE', 500); // free delivery over ₹500
define('DELIVERY_FEE', 40);  // ₹40

// ─── Timezone ────────────────────────────────────────────────────────────────
date_default_timezone_set(TIMEZONE);

// ─── Error Reporting ─────────────────────────────────────────────────────────
if (ENVIRONMENT === 'development') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// ─── Session Init ─────────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path' => '/',
        'secure' => (ENVIRONMENT === 'production'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ─── Database Connection ─────────────────────────────────────────────────────
function getDB(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                DB_HOST,
                DB_PORT,
                DB_NAME,
                DB_CHARSET
            );
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            if (ENVIRONMENT === 'development') {
                die('<div style="font:14px monospace;padding:20px;color:#f87171;background:#0f172a;">
                     <b>DB Error:</b> ' . htmlspecialchars($e->getMessage()) . '</div>');
            } else {
                die('Service temporarily unavailable. Please try again later.');
            }
        }
    }
    return $pdo;
}

// ─── CSRF Helpers ────────────────────────────────────────────────────────────
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function verify_csrf(): bool
{
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

// ─── Auth Helpers ─────────────────────────────────────────────────────────────
function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']);
}

function currentUser(): ?array
{
    if (!isLoggedIn())
        return null;
    static $user = null;
    if ($user === null) {
        $db = getDB();
        $stmt = $db->prepare('SELECT id, name, email, phone, role FROM users WHERE id = ? AND is_active = 1');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch() ?: null;
    }
    return $user;
}

function requireLogin(string $redirect = '/login.php'): void
{
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . $redirect . '?next=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

function requireRole(string $role): void
{
    requireLogin();
    $user = currentUser();
    if (!$user || $user['role'] !== $role) {
        header('Location: ' . BASE_URL . '/');
        exit;
    }
}

// ─── Flash Messages ───────────────────────────────────────────────────────────
function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array
{
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// ─── Utility Helpers ──────────────────────────────────────────────────────────
function e(string $str): string
{
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function formatPrice(float $amount): string
{
    return CURRENCY_SYMBOL . number_format($amount, 2);
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function jsonResponse(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function generateOTP(): string
{
    return str_pad((string) random_int(0, 10 ** OTP_LENGTH - 1), OTP_LENGTH, '0', STR_PAD_LEFT);
}

function slugify(string $text): string
{
    return strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $text), '-'));
}

function timeAgo(string $datetime): string
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60)
        return 'just now';
    if ($diff < 3600)
        return floor($diff / 60) . 'm ago';
    if ($diff < 86400)
        return floor($diff / 3600) . 'h ago';
    return floor($diff / 86400) . 'd ago';
}

function sanitizeFileName(string $name): string
{
    return preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($name));
}

// ─── Cart Helpers (session-based) ────────────────────────────────────────────
function getCart(): array
{
    return $_SESSION['cart'] ?? [];
}

function cartCount(): int
{
    return array_sum(array_column(getCart(), 'qty'));
}

function cartTotal(): float
{
    $total = 0;
    foreach (getCart() as $item) {
        $total += $item['price'] * $item['qty'];
    }
    return $total;
}
