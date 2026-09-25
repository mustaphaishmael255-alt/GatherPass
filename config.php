<?php
/**
 * GatherPass — Configuration
 * Discover. Book. Enter.
 *
 * Loads settings from .env when available, otherwise falls back to defaults
 * suitable for local development (localhost / root / no password).
 */

// ── Load .env if present ─────────────────────────────────────────────
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
 $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
 foreach ($lines as $line) {
 $line = trim($line);
 if ($line === '' || $line[0] === '#') continue;
 if (strpos($line, '=') === false) continue;
 [$key, $value] = explode('=', $line, 2);
 $key = trim($key);
 $value = trim($value);
 if (!array_key_exists($key, $_ENV)) {
 $_ENV[$key] = $value;
 putenv("$key=$value");
 }
 }
}

// ── Helper ────────────────────────────────────────────────────────────
function env(string $key, $default = null) {
 $val = getenv($key);
 return ($val !== false) ? $val : $default;
}

// ── Database ──────────────────────────────────────────────────────────
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_PORT', (int) env('DB_PORT', 3306));
define('DB_NAME', env('DB_NAME', 'gatherpass'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASSWORD', ''));

// ── Application ───────────────────────────────────────────────────────
define('APP_URL', env('APP_URL', 'http://localhost'));
define('APP_ENV', env('APP_ENV', 'development'));
define('PLATFORM_FEE_PERCENTAGE', (float) env('PLATFORM_FEE_PERCENTAGE', 0.05));
define('PLATFORM_FEE_LABEL', (PLATFORM_FEE_PERCENTAGE * 100) . '%');

// ── GatherPass Branding ───────────────────────────────────────────────
define('APP_NAME', 'GatherPass');
define('APP_TAGLINE', 'Discover. Book. Enter.');
define('APP_DESCRIPTION', 'Discover events, book tickets instantly, and enter with secure QR passes.');
define('APP_CURRENCY', 'USD');
define('APP_CURRENCY_SYMBOL', '

// ── Database connection ───────────────────────────────────────────────
function getConnection() {
 mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

 $conn = new mysqli();
 $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);
 $conn->options(MYSQLI_OPT_READ_TIMEOUT, 30);

 $maxRetries = 3;
 for ($i = 0; $i < $maxRetries; $i++) {
 try {
 if ($conn->real_connect(DB_HOST, DB_USER, DB_PASS, null, DB_PORT)) break;
 } catch (Exception $e) {
 if ($i >= $maxRetries - 1) {
 if (APP_ENV === 'production') {
 http_response_code(503);
 echo json_encode(['error' => 'Service temporarily unavailable']);
 exit;
 }
 die("Database connection failed after {$maxRetries} attempts: " . $e->getMessage());
 }
 usleep(100_000);
 }
 }

 $conn->set_charset('utf8mb4');
 $conn->query("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

 if (!$conn->select_db(DB_NAME)) {
 die("Failed to select database: " . $conn->error);
 }

 return $conn;
}

// ── Session ───────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
 session_start();
}

// ── Auth helpers ──────────────────────────────────────────────────────
function isLoggedIn(): bool {
 return !empty($_SESSION['planner_id']);
}

function requireAuth(): void {
 if (!isLoggedIn()) {
 header('Content-Type: application/json');
 echo json_encode(['success' =>false, 'message' => 'Authentication required']);
 exit;
 }
}

function getCurrentPlannerId(): ?int {
 return $_SESSION['planner_id'] ?? null;
}

function setPlannerSession(array $planner): void {
 $_SESSION['planner_id'] = $planner['id'];
 $_SESSION['planner_name'] = $planner['full_name'];
 $_SESSION['planner_email'] = $planner['email'];
}

function clearPlannerSession(): void {
 unset($_SESSION['planner_id'], $_SESSION['planner_name'], $_SESSION['planner_email']);
 session_destroy();
}
?>
);

// ── Database connection ───────────────────────────────────────────────
function getConnection() {
 mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

 $conn = new mysqli();
 $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);
 $conn->options(MYSQLI_OPT_READ_TIMEOUT, 30);

 $maxRetries = 3;
 for ($i = 0; $i < $maxRetries; $i++) {
 try {
 if ($conn->real_connect(DB_HOST, DB_USER, DB_PASS, null, DB_PORT)) break;
 } catch (Exception $e) {
 if ($i >= $maxRetries - 1) {
 if (APP_ENV === 'production') {
 http_response_code(503);
 echo json_encode(['error' => 'Service temporarily unavailable']);
 exit;
 }
 die("Database connection failed after {$maxRetries} attempts: " . $e->getMessage());
 }
 usleep(100_000);
 }
 }

 $conn->set_charset('utf8mb4');
 $conn->query("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

 if (!$conn->select_db(DB_NAME)) {
 die("Failed to select database: " . $conn->error);
 }

 return $conn;
}

// ── Session ───────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
 session_start();
}

// ── Auth helpers ──────────────────────────────────────────────────────
function isLoggedIn(): bool {
 return !empty($_SESSION['planner_id']);
}

function requireAuth(): void {
 if (!isLoggedIn()) {
 header('Content-Type: application/json');
 echo json_encode(['success' =>false, 'message' => 'Authentication required']);
 exit;
 }
}

function getCurrentPlannerId(): ?int {
 return $_SESSION['planner_id'] ?? null;
}

function setPlannerSession(array $planner): void {
 $_SESSION['planner_id'] = $planner['id'];
 $_SESSION['planner_name'] = $planner['full_name'];
 $_SESSION['planner_email'] = $planner['email'];
}

function clearPlannerSession(): void {
 unset($_SESSION['planner_id'], $_SESSION['planner_name'], $_SESSION['planner_email']);
 session_destroy();
}
?>
