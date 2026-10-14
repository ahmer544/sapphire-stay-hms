<?php
// Shared helpers — include after config/database.php

// ── Session ──────────────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Auth helpers ─────────────────────────────────────────────────────────────
function authUser(): ?array {
    return $_SESSION['user'] ?? null;
}

function isLoggedIn(): bool {
    return isset($_SESSION['user']);
}

function currentRole(): string {
    return (string)($_SESSION['user']['role'] ?? '');
}

function isCustomerAccount(?array $user = null): bool {
    $user ??= authUser();
    return ($user['role'] ?? '') === 'customer';
}

function isBackOfficeAccount(?array $user = null): bool {
    $user ??= authUser();
    return in_array($user['role'] ?? '', ['admin', 'staff'], true);
}

function requireLogin(string $redirect = '/sapphire/auth/login.php'): void {
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/auth/login.php');
        exit;
    }
}

function requireRole(string ...$roles): void {
    requireLogin();
    $user = authUser();
    if (!in_array($user['role'], $roles, true)) {
        header('Location: ' . APP_URL . '/index.php?error=unauthorised');
        exit;
    }
}

function requireAdmin(): void {
    requireRole('admin');
}

function requireCustomer(): void {
    requireLogin();
    if (!isCustomerAccount()) {
        header('Location: ' . APP_URL . '/admin/dashboard.php');
        exit;
    }
}

function loginUser(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id'    => $user['id'],
        'name'  => $user['name'],
        'email' => $user['email'],
        'role'  => $user['role'],
        'avatar'=> $user['avatar'] ?? null,
    ];
}

function logoutUser(): void {
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_unset();
    session_destroy();
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function generateBookingRef(): string {
    $db  = getDB();
    $year = date('Y');
    $stmt = $db->query("SELECT COUNT(*) FROM bookings WHERE YEAR(created_at)=$year");
    $seq  = (int)$stmt->fetchColumn() + 1;
    return 'SAP-' . $year . str_pad($seq, 4, '0', STR_PAD_LEFT);
}

function generateInvoiceNumber(): string {
    $db   = getDB();
    $stmt = $db->query("SELECT COUNT(*) FROM invoices");
    $seq  = (int)$stmt->fetchColumn() + 1;
    return 'INV-' . date('Ymd') . '-' . str_pad($seq, 3, '0', STR_PAD_LEFT);
}

function currency(float $amount): string {
    return '₨ ' . number_format($amount, 0);
}

function currencyRaw(float $amount): string {
    return number_format($amount, 0);
}

function stripeMinimumPkrAmount(): int {
    return 150;
}

function stripeChargeAmount(float $amount): int {
    return (int) max(0, round($amount));
}

function bookingPendingHoldMinutes(): int {
    return 15;
}

function bookingStatusOptions(): array {
    return ['pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled', 'expired', 'payment_failed'];
}

function bookingPendingHoldCondition(string $alias = 'b'): string {
    $alias = preg_replace('/[^a-zA-Z0-9_]/', '', $alias) ?: 'b';
    return "($alias.status='pending' AND $alias.created_at >= DATE_SUB(NOW(), INTERVAL " . bookingPendingHoldMinutes() . " MINUTE))";
}

function bookingConfirmedCondition(string $alias = 'b'): string {
    $alias = preg_replace('/[^a-zA-Z0-9_]/', '', $alias) ?: 'b';
    return "($alias.status IN ('confirmed','checked_in'))";
}

function bookingBlockingCondition(string $alias = 'b'): string {
    return '(' . bookingConfirmedCondition($alias) . ' OR ' . bookingPendingHoldCondition($alias) . ')';
}

function ensureBookingLifecycleSchema(?PDO $db = null): void {
    static $ready = false;
    if ($ready) {
        return;
    }

    $db ??= getDB();

    try {
        $db->exec("ALTER TABLE bookings MODIFY status ENUM('pending','confirmed','checked_in','checked_out','cancelled','expired','payment_failed') NOT NULL DEFAULT 'pending'");
    } catch (Throwable $e) {
        // Older MySQL installs may reject repeated enum changes; keep the app usable.
    }

    try {
        $db->exec(
            "CREATE TABLE IF NOT EXISTS booking_extensions (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                booking_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                old_check_out DATE NOT NULL,
                new_check_out DATE NOT NULL,
                extra_nights SMALLINT UNSIGNED NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                currency VARCHAR(3) NOT NULL DEFAULT 'PKR',
                status ENUM('pending','confirmed','cancelled','expired','payment_failed') NOT NULL DEFAULT 'pending',
                paid_at DATETIME DEFAULT NULL,
                confirmed_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_booking_extensions_booking (booking_id),
                KEY idx_booking_extensions_user_status (user_id, status),
                KEY idx_booking_extensions_status_created (status, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    } catch (Throwable $e) {
        // The extension feature will fail gracefully if the table cannot be created.
    }

    try {
        if (!dbColumnExists('payments', 'extension_id')) {
            $db->exec("ALTER TABLE payments ADD COLUMN extension_id INT UNSIGNED DEFAULT NULL AFTER booking_id");
            $db->exec("ALTER TABLE payments ADD KEY idx_extension_id (extension_id)");
        }
    } catch (Throwable $e) {
        // Ignore; old databases can still process normal booking payments.
    }

    $ready = true;
}

function expirePendingBookings(?PDO $db = null): int {
    static $ran = false;
    if ($ran) {
        return 0;
    }
    $ran = true;

    $db ??= getDB();
    ensureBookingLifecycleSchema($db);
    $minutes = bookingPendingHoldMinutes();
    $expired = 0;

    try {
        $db->beginTransaction();

        $bookingIds = $db->query("
            SELECT b.id
            FROM bookings b
            WHERE b.status='pending'
              AND b.created_at < DATE_SUB(NOW(), INTERVAL $minutes MINUTE)
              AND NOT EXISTS (
                SELECT 1 FROM payments p
                WHERE p.booking_id=b.id AND p.status='completed' AND p.extension_id IS NULL
              )
        ")->fetchAll(PDO::FETCH_COLUMN);

        if ($bookingIds) {
            $placeholders = implode(',', array_fill(0, count($bookingIds), '?'));
            $db->prepare("UPDATE payments SET status='failed' WHERE status IN('pending','processing') AND extension_id IS NULL AND booking_id IN ($placeholders)")
                ->execute($bookingIds);
            $db->prepare("UPDATE bookings SET status='expired', updated_at=NOW() WHERE id IN ($placeholders)")
                ->execute($bookingIds);
            $expired += count($bookingIds);
        }

        if (dbColumnExists('payments', 'extension_id')) {
            $extensionIds = $db->query("
                SELECT e.id
                FROM booking_extensions e
                WHERE e.status='pending'
                  AND e.created_at < DATE_SUB(NOW(), INTERVAL $minutes MINUTE)
                  AND NOT EXISTS (
                    SELECT 1 FROM payments p
                    WHERE p.extension_id=e.id AND p.status='completed'
                  )
            ")->fetchAll(PDO::FETCH_COLUMN);

            if ($extensionIds) {
                $placeholders = implode(',', array_fill(0, count($extensionIds), '?'));
                $db->prepare("UPDATE payments SET status='failed' WHERE status IN('pending','processing') AND extension_id IN ($placeholders)")
                    ->execute($extensionIds);
                $db->prepare("UPDATE booking_extensions SET status='expired', updated_at=NOW() WHERE id IN ($placeholders)")
                    ->execute($extensionIds);
            }
        }

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
    }

    return $expired;
}

function normalizeHotelTime(string $time, string $default = '12:00:00'): string {
    $time = trim($time);
    if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?$/', $time, $m)) {
        return $m[1] . ':' . $m[2] . ':' . ($m[3] ?? '00');
    }
    return $default;
}

function hotelCheckOutTime(): string {
    return normalizeHotelTime(getSetting('check_out_time', '12:00'), '12:00:00');
}

function checkoutDeadlineExpression(string $alias = 'b'): string {
    $alias = preg_replace('/[^a-zA-Z0-9_]/', '', $alias) ?: 'b';
    return "TIMESTAMP($alias.check_out, '" . hotelCheckOutTime() . "')";
}

function autoCheckoutExpiredBookings(?PDO $db = null): int {
    // Checkout must be handled by staff/admin from the desk. This function remains
    // as a compatibility no-op so older includes never silently release rooms.
    return 0;
}

function refreshBookingLifecycle(?PDO $db = null): void {
    $db ??= getDB();
    ensureBookingLifecycleSchema($db);
    expirePendingBookings($db);
}

function roomHasBlockingBooking(PDO $db, int $roomId, string $checkIn, string $checkOut, ?int $excludeBookingId = null): bool {
    $condition = bookingBlockingCondition('b');
    $params = [$roomId, $checkIn, $checkOut];
    $excludeSql = '';

    if ($excludeBookingId !== null) {
        $excludeSql = ' AND b.id<>?';
        $params[] = $excludeBookingId;
    }

    $stmt = $db->prepare("
        SELECT b.id
        FROM bookings b
        WHERE b.room_id=?
          AND $condition
          AND NOT (b.check_out<=? OR b.check_in>=?)
          $excludeSql
        LIMIT 1
    ");
    $stmt->execute($params);
    return (bool)$stmt->fetchColumn();
}

function syncRoomStatusForBooking(PDO $db, int $bookingId): void {
    $stmt = $db->prepare("SELECT room_id, status FROM bookings WHERE id=?");
    $stmt->execute([$bookingId]);
    $booking = $stmt->fetch();
    if (!$booking) {
        return;
    }

    $roomId = (int)$booking['room_id'];
    if ($booking['status'] === 'checked_in') {
        $db->prepare("UPDATE rooms SET status='occupied' WHERE id=? AND status<>'maintenance'")
            ->execute([$roomId]);
        return;
    }

    $activeStmt = $db->prepare("SELECT COUNT(*) FROM bookings WHERE room_id=? AND status='checked_in'");
    $activeStmt->execute([$roomId]);
    if ((int)$activeStmt->fetchColumn() === 0) {
        $db->prepare("UPDATE rooms SET status='available' WHERE id=? AND status='occupied'")
            ->execute([$roomId]);
    }
}

function confirmBookingIfAvailable(PDO $db, int $bookingId, ?int $userId = null, ?int $handledBy = null): array {
    ensureBookingLifecycleSchema($db);
    $started = false;

    try {
        if (!$db->inTransaction()) {
            $db->beginTransaction();
            $started = true;
        }

        $sql = "SELECT * FROM bookings WHERE id=?";
        $params = [$bookingId];
        if ($userId !== null) {
            $sql .= " AND user_id=?";
            $params[] = $userId;
        }
        $sql .= " FOR UPDATE";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $booking = $stmt->fetch();

        if (!$booking) {
            throw new RuntimeException('Booking not found.');
        }

        if (in_array($booking['status'], ['confirmed', 'checked_in', 'checked_out'], true)) {
            if ($started) {
                $db->commit();
            }
            return ['ok' => true, 'booking' => $booking];
        }

        if ($booking['status'] !== 'pending') {
            throw new RuntimeException('This booking is no longer pending payment.');
        }

        $paidStmt = $db->prepare("SELECT id FROM payments WHERE booking_id=? AND extension_id IS NULL AND method='stripe' AND status='completed' LIMIT 1");
        $paidStmt->execute([$bookingId]);
        if (!$paidStmt->fetchColumn()) {
            throw new RuntimeException('Stripe payment is required before this booking can be confirmed.');
        }

        if (roomHasBlockingBooking($db, (int)$booking['room_id'], $booking['check_in'], $booking['check_out'], (int)$booking['id'])) {
            throw new RuntimeException('This room is no longer available for the selected dates.');
        }

        $setHandled = $handledBy !== null ? ', handled_by=' . (int)$handledBy : '';
        $db->prepare("UPDATE bookings SET status='confirmed', updated_at=NOW()$setHandled WHERE id=?")
            ->execute([$bookingId]);

        if ($started) {
            $db->commit();
        }
        return ['ok' => true, 'booking' => $booking];
    } catch (Throwable $e) {
        if ($started && $db->inTransaction()) {
            $db->rollBack();
        }
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

function confirmExtensionIfAvailable(PDO $db, int $extensionId, ?int $userId = null): array {
    ensureBookingLifecycleSchema($db);
    $started = false;

    try {
        if (!$db->inTransaction()) {
            $db->beginTransaction();
            $started = true;
        }

        $sql = "
            SELECT e.*, b.room_id, b.check_in, b.check_out AS current_check_out, b.nights,
                   b.subtotal, b.tax_amount, b.total_amount, b.status AS booking_status
            FROM booking_extensions e
            JOIN bookings b ON b.id=e.booking_id
            WHERE e.id=?
        ";
        $params = [$extensionId];
        if ($userId !== null) {
            $sql .= " AND e.user_id=?";
            $params[] = $userId;
        }
        $sql .= " FOR UPDATE";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $extension = $stmt->fetch();

        if (!$extension) {
            throw new RuntimeException('Extension request not found.');
        }

        if ($extension['status'] === 'confirmed') {
            if ($started) {
                $db->commit();
            }
            return ['ok' => true, 'extension' => $extension];
        }

        if ($extension['status'] !== 'pending') {
            throw new RuntimeException('This extension request is no longer pending.');
        }

        if (!in_array($extension['booking_status'], ['confirmed', 'checked_in'], true)) {
            throw new RuntimeException('Only active confirmed bookings can be extended.');
        }

        if ($extension['new_check_out'] <= $extension['current_check_out']) {
            throw new RuntimeException('The selected extension date is no longer valid.');
        }

        $paidStmt = $db->prepare("SELECT id FROM payments WHERE extension_id=? AND method='stripe' AND status='completed' LIMIT 1");
        $paidStmt->execute([$extensionId]);
        if (!$paidStmt->fetchColumn()) {
            throw new RuntimeException('Stripe payment is required before this extension can be confirmed.');
        }

        if (roomHasBlockingBooking($db, (int)$extension['room_id'], $extension['current_check_out'], $extension['new_check_out'], (int)$extension['booking_id'])) {
            throw new RuntimeException('The room is no longer available for the extension dates.');
        }

        $newNights = max(1, (int)round((strtotime($extension['new_check_out']) - strtotime($extension['check_in'])) / 86400));
        $newSubtotal = (float)$extension['subtotal'] + (float)$extension['amount'];
        $newTotal = (float)$extension['total_amount'] + (float)$extension['amount'];

        $db->prepare("UPDATE bookings SET check_out=?, nights=?, subtotal=?, total_amount=?, updated_at=NOW() WHERE id=?")
            ->execute([$extension['new_check_out'], $newNights, $newSubtotal, $newTotal, $extension['booking_id']]);
        $db->prepare("UPDATE invoices SET total=? WHERE booking_id=?")
            ->execute([$newTotal, $extension['booking_id']]);
        $db->prepare("UPDATE booking_extensions SET status='confirmed', paid_at=NOW(), confirmed_at=NOW(), updated_at=NOW() WHERE id=?")
            ->execute([$extensionId]);

        if ($started) {
            $db->commit();
        }
        return ['ok' => true, 'extension' => $extension];
    } catch (Throwable $e) {
        if ($started && $db->inTransaction()) {
            $db->rollBack();
        }
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

function hotelAddress(): string {
    return 'Gojra Bypass Road, Muzaffarabad, Pakistan, 13100.';
}

function hotelPhone(): string {
    return getSetting('hotel_phone', '+92 317 9219995');
}

function hotelEmail(): string {
    return getSetting('hotel_email', 'sapphire.stay@gmail.com');
}

function phoneHref(string $phone): string {
    return preg_replace('/(?!^\+)\D+/', '', $phone);
}

function adminUrl(string $page = 'dashboard', array $params = []): string {
    $path = 'admin/dashboard.php';

    if ($page !== 'dashboard') {
        $params = ['page' => $page] + $params;
    }

    $query = $params ? ('?' . http_build_query($params)) : '';
    return APP_URL . '/' . $path . $query;
}

function redirectUrl(string $url): never {
    header('Location: ' . $url);
    exit;
}

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): never {
    header('Location: ' . APP_URL . '/' . ltrim($path, '/'));
    exit;
}

function flash(string $key, string $msg): void {
    $_SESSION['flash'][$key] = $msg;
}

function getFlash(string $key): ?string {
    $msg = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $msg;
}

function dbColumnExists(string $table, string $column): bool {
    static $cache = [];

    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    try {
        $stmt = getDB()->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $stmt->execute([$column]);
        $cache[$key] = (bool)$stmt->fetch();
    } catch (Throwable $e) {
        $cache[$key] = false;
    }

    return $cache[$key];
}

function invoicesSupportGeneratedBy(): bool {
    return dbColumnExists('invoices', 'generated_by');
}

function makeWalkInEmail(?string $email, string $name = 'guest'): string {
    $email = strtolower(trim((string)$email));
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return $email;
    }

    $slug = preg_replace('/[^a-z0-9]+/i', '.', strtolower(trim($name)));
    $slug = trim((string)$slug, '.');
    if ($slug === '') {
        $slug = 'guest';
    }

    return sprintf('%s.walkin.%s@local.sapphirestay', $slug, uniqid());
}

function dedupeReviews(array $reviews): array {
    $seen = [];
    $unique = [];

    foreach ($reviews as $review) {
        $signature = implode('|', [
            (string)($review['guest'] ?? ''),
            (string)($review['user_id'] ?? ''),
            (string)($review['room_id'] ?? ''),
            (string)($review['rating'] ?? ''),
            trim((string)($review['title'] ?? '')),
            trim((string)($review['body'] ?? '')),
        ]);

        if (isset($seen[$signature])) {
            continue;
        }

        $seen[$signature] = true;
        $unique[] = $review;
    }

    return $unique;
}

function ensureMessagingSchema(): void {
    static $ready = false;

    if ($ready) {
        return;
    }

    try {
        getDB()->exec(
            "CREATE TABLE IF NOT EXISTS messages (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                sender_id INT UNSIGNED NOT NULL,
                recipient_id INT UNSIGNED NOT NULL,
                subject VARCHAR(180) DEFAULT NULL,
                body TEXT NOT NULL,
                is_read TINYINT(1) NOT NULL DEFAULT 0,
                read_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                INDEX idx_recipient_read (recipient_id, is_read),
                INDEX idx_sender_recipient (sender_id, recipient_id),
                CONSTRAINT fk_messages_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_messages_recipient FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    } catch (Throwable $e) {
        return;
    }

    $ready = true;
}

function messageUnreadCount(?int $userId = null): int {
    $userId ??= authUser()['id'] ?? 0;
    if ($userId <= 0) {
        return 0;
    }

    ensureMessagingSchema();

    try {
        $stmt = getDB()->prepare("SELECT COUNT(*) FROM messages WHERE recipient_id=? AND is_read=0");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function markConversationRead(int $recipientId, int $senderId): void {
    ensureMessagingSchema();

    try {
        getDB()->prepare("UPDATE messages SET is_read=1, read_at=NOW() WHERE recipient_id=? AND sender_id=? AND is_read=0")
            ->execute([$recipientId, $senderId]);
    } catch (Throwable $e) {
        // Ignore silently to keep UI usable even if schema is missing.
    }
}

function sendDirectMessage(int $senderId, int $recipientId, string $body, ?string $subject = null): void {
    ensureMessagingSchema();

    $body = trim($body);
    $subject = trim((string)$subject);

    if ($senderId <= 0 || $recipientId <= 0 || $body === '') {
        throw new InvalidArgumentException('A recipient and message are required.');
    }

    getDB()->prepare("INSERT INTO messages (sender_id, recipient_id, subject, body) VALUES (?,?,?,?)")
        ->execute([$senderId, $recipientId, $subject !== '' ? $subject : null, $body]);
}

function uploadFile(string $inputName, string $dir = 'uploads/'): ?string {
    if (!isset($_FILES[$inputName]) || $_FILES[$inputName]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $mime    = mime_content_type($_FILES[$inputName]['tmp_name']);
    $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
    if (!in_array($mime, $allowed)) return null;

    $ext  = pathinfo($_FILES[$inputName]['name'], PATHINFO_EXTENSION);
    $name = uniqid('img_', true) . '.' . strtolower($ext);
    $dest = __DIR__ . '/../' . $dir . $name;
    if (!is_dir(dirname($dest))) mkdir(dirname($dest), 0755, true);
    move_uploaded_file($_FILES[$inputName]['tmp_name'], $dest);
    return $dir . $name;
}
