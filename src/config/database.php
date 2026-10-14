<?php
/**
 * Sapphire Stay – Database Configuration
 * Change only the values below. Keep keys intact.
 */

define('DB_HOST',    'localhost');
define('DB_PORT',    '3306');
define('DB_NAME',    'sapphire_stay');
define('DB_USER',    'root');       // ← your MySQL username
define('DB_PASS',    '');           // ← your MySQL password
define('DB_CHARSET', 'utf8mb4');

// ── Stripe Keys ──────────────────────────────────────────────────────────────
// Replace with your REAL keys from https://dashboard.stripe.com/apikeys
define('STRIPE_PUBLISHABLE_KEY', 'YOUR_STRIPE_SECRET_KEY_HERE');
define('STRIPE_SECRET_KEY',      ''YOUR_STRIPE_SECRET_KEY_HERE');
define('STRIPE_WEBHOOK_SECRET',  'whsec_REPLACE_WITH_YOUR_WEBHOOK_SECRET');
define('STRIPE_CURRENCY',        'pkr');

// ── App ──────────────────────────────────────────────────────────────────────
define('APP_NAME',    'Sapphire Stay');

function detectAppUrl(string $fallback = '/sapphire'): string {
    $override = trim((string)getenv('SAPPHIRE_APP_URL'));
    if ($override !== '') {
        return rtrim($override, '/');
    }

    $documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
    $appRoot = realpath(__DIR__ . '/..');

    if ($documentRoot && $appRoot) {
        $documentRoot = rtrim(str_replace('\\', '/', $documentRoot), '/');
        $appRoot = rtrim(str_replace('\\', '/', $appRoot), '/');

        if ($appRoot === $documentRoot) {
            return '';
        }

        if (strpos($appRoot, $documentRoot . '/') === 0) {
            return '/' . trim(substr($appRoot, strlen($documentRoot)), '/');
        }
    }

    return rtrim($fallback, '/');
}

define('APP_URL',     detectAppUrl());  // no trailing slash
define('APP_VERSION', '1.0.0');
define('TIMEZONE',    'Asia/Karachi');

date_default_timezone_set(TIMEZONE);

// ── PDO Connection ───────────────────────────────────────────────────────────
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn  = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
        $opts = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $opts);
        } catch (PDOException $e) {
            http_response_code(500);
            // Friendly error for browser pages instead of raw JSON
            if (PHP_SAPI !== 'cli' && !isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                header('Content-Type: text/html; charset=utf-8');
                echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Database Error</title></head><body style="font-family:sans-serif;padding:2rem;background:#f8f1e3;color:#1a1814;">';
                echo '<h1 style="color:#132a4f;">Database connection failed</h1>';
                echo '<p>Please make sure MySQL is running and the <code>sapphire_stay</code> database has been imported from <code>sapphire_stay.sql</code>.</p>';
                echo '<p>Error details (for developer): ' . htmlspecialchars($e->getMessage()) . '</p>';
                echo '<p><a href="/sapphire/">Return to home</a></p>';
                echo '</body></html>';
            } else {
                echo json_encode(['error' => 'DB error: ' . $e->getMessage()]);
            }
            exit;
        }
    }
    return $pdo;
}

// ── Dynamic settings helper ──────────────────────────────────────────────────
function getSetting(string $key, string $default = ''): string {
    static $cache = [];
    if (!isset($cache[$key])) {
        try {
            $s = getDB()->prepare("SELECT `value` FROM settings WHERE `key`=?");
            $s->execute([$key]);
            $row = $s->fetchColumn();
            $cache[$key] = $row !== false ? $row : $default;
        } catch (Exception $e) {
            return $default;
        }
    }
    return $cache[$key];
}
