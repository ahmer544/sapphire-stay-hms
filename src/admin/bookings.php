<?php
require_once '../config/database.php';
require_once '../config/helpers.php';
requireRole('admin','staff');
$db   = getDB();
$user = authUser();
refreshBookingLifecycle($db);
$canManageBookings = ($user['role'] ?? '') === 'admin';

$bookingError = getFlash('booking_error');
$bookingSuccess = getFlash('booking_success');
$supportsInvoiceGeneratedBy = invoicesSupportGeneratedBy();
$editBookingId = (int)($_GET['edit'] ?? 0);


if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate_invoice') {
    if (!$canManageBookings) {
        flash('booking_error', 'Only admins can generate invoices from the bookings page.');
        redirect('admin/bookings.php');
    }

    $bookingId = (int)$_POST['booking_id'];
    $booking   = $db->prepare("SELECT * FROM bookings WHERE id=?");
    $booking->execute([$bookingId]);
    $booking   = $booking->fetch();
    if ($booking) {
        $existing = $db->prepare("SELECT id FROM invoices WHERE booking_id=?");
        $existing->execute([$bookingId]);
        if (!$existing->fetch()) {
            $invNum = 'INV-' . date('Ymd') . '-' . str_pad($bookingId, 4, '0', STR_PAD_LEFT);
        if ($supportsInvoiceGeneratedBy) {
          $db->prepare("INSERT INTO invoices (booking_id,invoice_number,total,generated_by) VALUES (?,?,?,?)")
             ->execute([$bookingId, $invNum, $booking['total_amount'], $user['id']]);
        } else {
          $db->prepare("INSERT INTO invoices (booking_id,invoice_number,total) VALUES (?,?,?)")
             ->execute([$bookingId, $invNum, $booking['total_amount']]);
        }
        }
    }
    redirectUrl(adminUrl('invoices'));
}

  if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_booking') {
    if (!$canManageBookings) {
      flash('booking_error', 'Only admins can edit bookings.');
      redirect('admin/bookings.php');
    }

    $bookingId = (int)($_POST['booking_id'] ?? 0);
    $roomId = (int)($_POST['room_id'] ?? 0);
    $checkIn = trim($_POST['check_in'] ?? '');
    $checkOut = trim($_POST['check_out'] ?? '');
    $guests = max(1, (int)($_POST['guests'] ?? $_POST['adults'] ?? 1));
    $adults   = $guests;
    $children = 0;
    $special = trim($_POST['special_requests'] ?? '');
    $newStatus = trim($_POST['status'] ?? 'pending');

    if ($bookingId <= 0 || $roomId <= 0 || $checkIn === '' || $checkOut === '') {
      flash('booking_error', 'Room, check-in, and check-out are required to update a booking.');
      redirectUrl(APP_URL . '/admin/bookings.php?edit=' . $bookingId);
    }

    $ci = strtotime($checkIn);
    $co = strtotime($checkOut);
    if (!$ci || !$co || $co <= $ci) {
      flash('booking_error', 'Check-out must be after check-in.');
      redirectUrl(APP_URL . '/admin/bookings.php?edit=' . $bookingId);
    }

    try {
      $db->beginTransaction();

      $currentStmt = $db->prepare("SELECT * FROM bookings WHERE id=?");
      $currentStmt->execute([$bookingId]);
      $currentBooking = $currentStmt->fetch();
      if (!$currentBooking) {
        throw new RuntimeException('Booking not found.');
      }

      $roomStmt = $db->prepare("SELECT * FROM rooms WHERE id=?");
      $roomStmt->execute([$roomId]);
      $room = $roomStmt->fetch();
      if (!$room) {
        throw new RuntimeException('Selected room not found.');
      }

      if (roomHasBlockingBooking($db, $roomId, $checkIn, $checkOut, $bookingId)) {
        throw new RuntimeException('This room is already booked in the selected date range.');
      }

      $nights = max(1, (int)round(($co - $ci) / 86400));
      $subtotal = round((float)$room['price_per_night'] * $nights, 0);
      $tax = 0;
      $total = $subtotal;

      $db->prepare("UPDATE bookings SET room_id=?, check_in=?, check_out=?, nights=?, adults=?, children=?, subtotal=?, tax_amount=?, total_amount=?, special_requests=?, status=?, handled_by=? WHERE id=?")
        ->execute([$roomId, $checkIn, $checkOut, $nights, $adults, $children, $subtotal, $tax, $total, $special !== '' ? $special : null, $newStatus, $user['id'], $bookingId]);

      if ($newStatus === 'checked_in') {
        $db->prepare("UPDATE bookings SET checked_in_at=COALESCE(checked_in_at, NOW()) WHERE id=?")->execute([$bookingId]);
      } elseif ($newStatus === 'checked_out') {
        $db->prepare("UPDATE bookings SET checked_out_at=COALESCE(checked_out_at, NOW()) WHERE id=?")->execute([$bookingId]);
      }
      syncRoomStatusForBooking($db, $bookingId);

      $db->prepare("UPDATE invoices SET total=? WHERE booking_id=?")->execute([$total, $bookingId]);
      $db->prepare("UPDATE payments SET amount=? WHERE booking_id=? AND extension_id IS NULL AND status='completed'")->execute([$total, $bookingId]);

      $db->commit();
      flash('booking_success', 'Booking updated successfully.');
      redirect('admin/bookings.php');
    } catch (Throwable $e) {
      if ($db->inTransaction()) {
        $db->rollBack();
      }
      flash('booking_error', $e->getMessage() ?: 'Unable to update the booking right now.');
      redirectUrl(APP_URL . '/admin/bookings.php?edit=' . $bookingId);
    }
  }

  if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_booking') {
    if (!$canManageBookings) {
      flash('booking_error', 'Only admins can delete bookings.');
      redirect('admin/bookings.php');
    }

    $bookingId = (int)($_POST['booking_id'] ?? 0);

    try {
      $db->beginTransaction();
      $db->prepare("DELETE FROM payments WHERE booking_id=?")->execute([$bookingId]);
      $db->prepare("DELETE FROM invoices WHERE booking_id=?")->execute([$bookingId]);
      $db->prepare("DELETE FROM bookings WHERE id=?")->execute([$bookingId]);
      $db->commit();
      flash('booking_success', 'Booking deleted successfully.');
    } catch (Throwable $e) {
      if ($db->inTransaction()) {
        $db->rollBack();
      }
      flash('booking_error', 'Unable to delete this booking right now.');
    }

    redirect('admin/bookings.php');
  }

  if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_walkin') {
    if (!$canManageBookings) {
      flash('booking_error', 'Only admins can create walk-in bookings.');
      redirect('admin/bookings.php');
    }

    $guestName = trim($_POST['guest_name'] ?? '');
    $guestEmail = trim($_POST['guest_email'] ?? '');
    $guestPhone = trim($_POST['guest_phone'] ?? '');
    $roomId = (int)($_POST['room_id'] ?? 0);
    $checkIn = trim($_POST['check_in'] ?? '');
    $checkOut = trim($_POST['check_out'] ?? '');
    $guests = max(1, (int)($_POST['guests'] ?? $_POST['adults'] ?? 1));
    $adults   = $guests;
    $children = 0;
    $special = trim($_POST['special_requests'] ?? '');

    if ($guestName === '' || $roomId <= 0 || $checkIn === '' || $checkOut === '') {
      flash('booking_error', 'Guest name, room, check-in, and check-out are required for a walk-in booking.');
      redirect('admin/bookings.php');
    }

    $ci = strtotime($checkIn);
    $co = strtotime($checkOut);
    if (!$ci || !$co || $co <= $ci) {
      flash('booking_error', 'Check-out must be after check-in for a walk-in booking.');
      redirect('admin/bookings.php');
    }

    $nights = max(1, (int)round(($co - $ci) / 86400));

    try {
      $db->beginTransaction();

      $roomStmt = $db->prepare("SELECT * FROM rooms WHERE id=? AND status<>'maintenance'");
      $roomStmt->execute([$roomId]);
      $room = $roomStmt->fetch();

      if (!$room) {
        throw new RuntimeException('Selected room is not currently available.');
      }

      if (roomHasBlockingBooking($db, $roomId, $checkIn, $checkOut)) {
        throw new RuntimeException('That room already has a booking in the selected date range.');
      }

      $email = makeWalkInEmail($guestEmail, $guestName);
      $guestStmt = $db->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
      $guestStmt->execute([$email]);
      $guestId = (int)($guestStmt->fetchColumn() ?: 0);

      if ($guestId <= 0) {
        $password = bin2hex(random_bytes(6));
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $db->prepare("INSERT INTO users (name,email,phone,password_hash,role) VALUES (?,?,?,?,'customer')")
          ->execute([$guestName, $email, $guestPhone !== '' ? $guestPhone : null, $hash]);
        $guestId = (int)$db->lastInsertId();
      } elseif ($guestPhone !== '') {
        $db->prepare("UPDATE users SET name=?, phone=COALESCE(NULLIF(?, ''), phone) WHERE id=?")
          ->execute([$guestName, $guestPhone, $guestId]);
      }

      $subtotal = round((float)$room['price_per_night'] * $nights, 0);
      $tax = 0;
      $total = $subtotal;
      $status = 'pending';
      $ref = generateBookingRef();

      $db->prepare("INSERT INTO bookings (booking_ref,user_id,room_id,check_in,check_out,nights,adults,children,subtotal,tax_amount,total_amount,special_requests,status,handled_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([$ref, $guestId, $roomId, $checkIn, $checkOut, $nights, $adults, $children, $subtotal, $tax, $total, $special !== '' ? $special : null, $status, $user['id']]);

      $bookingId = (int)$db->lastInsertId();
      $invoiceNumber = generateInvoiceNumber();

      if ($supportsInvoiceGeneratedBy) {
        $db->prepare("INSERT INTO invoices (booking_id,invoice_number,total,generated_by) VALUES (?,?,?,?)")
          ->execute([$bookingId, $invoiceNumber, $total, $user['id']]);
      } else {
        $db->prepare("INSERT INTO invoices (booking_id,invoice_number,total) VALUES (?,?,?)")
          ->execute([$bookingId, $invoiceNumber, $total]);
      }

      $db->commit();
      flash('booking_success', 'Walk-in booking created as pending. Customer must complete Stripe payment before it is confirmed.');
    } catch (Throwable $e) {
      if ($db->inTransaction()) {
        $db->rollBack();
      }
      flash('booking_error', $e->getMessage() ?: 'Unable to create the walk-in booking right now.');
    }

    redirect('admin/bookings.php');
  }

// Filters
$status = $_GET['status'] ?? '';
$search = trim($_GET['q'] ?? '');
$where  = []; $params = [];
if ($status) { $where[] = 'b.status=?'; $params[] = $status; }
if ($search) { $where[] = "(u.name LIKE ? OR b.booking_ref LIKE ? OR r.name LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }
$w = $where ? 'WHERE '.implode(' AND ',$where) : '';

// Update status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    if (!$canManageBookings) {
        flash('booking_error', 'Only admins can update booking status from this page. Staff should use Check-in / Out.');
        redirect('admin/bookings.php');
    }

    $newStatus = $_POST['new_status'] ?? '';
    $bookingId = (int)($_POST['booking_id'] ?? 0);
    if (!in_array($newStatus, bookingStatusOptions(), true)) {
        flash('booking_error', 'Invalid booking status.');
        redirect('admin/bookings.php');
    }
    if ($newStatus === 'confirmed') {
        $result = confirmBookingIfAvailable($db, $bookingId, null, (int)$user['id']);
        if (!$result['ok']) {
            flash('booking_error', $result['error'] ?? 'Unable to confirm booking.');
            redirect('admin/bookings.php');
        }
    } else {
        $stampSql = '';
        if ($newStatus === 'checked_in') {
            $stampSql = ', checked_in_at=COALESCE(checked_in_at, NOW())';
        } elseif ($newStatus === 'checked_out') {
            $stampSql = ', checked_out_at=COALESCE(checked_out_at, NOW())';
        }
        $db->prepare("UPDATE bookings SET status=?,handled_by=?,updated_at=NOW()$stampSql WHERE id=?")
           ->execute([$newStatus,$user['id'],$bookingId]);
        syncRoomStatusForBooking($db, $bookingId);
    }
    header('Location: bookings.php' . ($status ? "?status=$status" : ''));
    exit;
}

$stmt = $db->prepare("SELECT b.*,u.name AS guest_name,u.email,u.phone,r.name AS room_name,
    (SELECT status FROM payments WHERE booking_id=b.id ORDER BY id DESC LIMIT 1) AS pay_status
    FROM bookings b JOIN users u ON u.id=b.user_id JOIN rooms r ON r.id=b.room_id
    $w ORDER BY b.created_at DESC");
$stmt->execute($params);
$bookings = $stmt->fetchAll();

$blockingCondition = bookingBlockingCondition('b');
$availableRooms = $db->query("SELECT r.id, r.room_number, r.name, r.price_per_night FROM rooms r WHERE r.status<>'maintenance' AND NOT EXISTS (SELECT 1 FROM bookings b WHERE b.room_id=r.id AND $blockingCondition AND CURDATE() >= b.check_in AND CURDATE() < b.check_out) ORDER BY r.room_number")->fetchAll();

$bookingOptions = $db->query("SELECT id, room_number, name, price_per_night FROM rooms ORDER BY room_number")->fetchAll();
$editBooking = null;
if ($canManageBookings && $editBookingId > 0) {
  $editStmt = $db->prepare("SELECT * FROM bookings WHERE id=?");
  $editStmt->execute([$editBookingId]);
  $editBooking = $editStmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Bookings – Sapphire Stay Admin</title>
  <?php include 'partials/admin-head.php'; ?>
  <style>
    /* Admin specific polish for this page */
    .admin-table th { font-weight: 500; color: var(--text-faint); text-transform: uppercase; letter-spacing: .08em; font-size: 10px; }
    .badge-checked_out { background: rgba(148,163,184,0.1); color: #475569; border: 1px solid rgba(148,163,184,0.25); }
    .badge-cancelled { background: rgba(239,68,68,0.1); color: #b91c1c; border: 1px solid rgba(239,68,68,0.25); }
    .badge-expired { background: rgba(148,163,184,0.08); color: #64748b; border: 1px solid rgba(148,163,184,0.2); }
    .badge-payment_failed { background: rgba(239,68,68,0.1); color: #b91c1c; border: 1px solid rgba(239,68,68,0.25); }
    select option { background: #fff; color: var(--text); }
  </style>
</head>
<body class="admin-shell flex min-h-screen" data-role="<?= e(currentRole()) ?>">
<?php include 'partials/sidebar.php'; ?>
<main class="flex-1 overflow-auto">

  <div class="admin-topbar px-6 py-4 flex items-center justify-between sticky top-0 z-30">
    <div>
      <h1 class="font-display text-2xl text-[var(--lapis)]">Bookings</h1>
      <p class="text-[var(--text-faint)] text-xs"><?= count($bookings) ?> records</p>
    </div>
  </div>

  <div class="p-6 space-y-5">
    <?php if ($bookingError): ?>
    <div class="bg-red-500/20 border border-red-500/40 text-red-300 text-sm rounded-xl p-3 flex items-center gap-2">
      <i data-lucide="alert-circle" class="w-4 h-4"></i> <?= e($bookingError) ?>
    </div>
    <?php endif; ?>

    <?php if ($bookingSuccess): ?>
    <div class="bg-green-500/20 border border-green-500/40 text-green-300 text-sm rounded-xl p-3 flex items-center gap-2">
      <i data-lucide="check-circle" class="w-4 h-4"></i> <?= e($bookingSuccess) ?>
    </div>
    <?php endif; ?>

    <?php if ($editBooking): ?>
    <div class="bg-white border border-[var(--stone)] shadow-md rounded-2xl p-6 mb-6">
      <div class="flex items-center justify-between gap-4 mb-4 flex-wrap">
        <div>
          <h2 class="font-display text-xl text-[var(--lapis)]">Edit Booking <?= e($editBooking['booking_ref']) ?></h2>
          <p class="text-[var(--text-muted)] text-xs mt-1">Update booking dates, room, guests, status, and notes.</p>
        </div>
        <a href="<?= APP_URL ?>/admin/bookings.php" class="text-[var(--text-faint)] hover:text-[var(--text)] text-xs tracking-widest transition">CLOSE</a>
      </div>

      <form method="POST" class="space-y-4">
        <input type="hidden" name="action" value="edit_booking"/>
        <input type="hidden" name="booking_id" value="<?= $editBooking['id'] ?>"/>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div>
            <label class="text-[var(--text-faint)] text-xs tracking-widest uppercase block mb-1">Room</label>
            <select name="room_id" required class="w-full bg-white border border-[var(--stone)] rounded-xl px-4 py-2.5 text-[var(--text)] text-sm focus:outline-none focus:border-[var(--gold)]">
              <?php foreach ($bookingOptions as $room): ?>
              <option value="<?= $room['id'] ?>" <?= (int)$room['id'] === (int)$editBooking['room_id'] ? 'selected' : '' ?>>Room <?= e($room['room_number']) ?> — <?= e($room['name']) ?> (<?= currency((float)$room['price_per_night']) ?>/night)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="text-[var(--text-faint)] text-xs tracking-widest uppercase block mb-1">Check-In</label>
            <input type="date" name="check_in" required value="<?= e($editBooking['check_in']) ?>" class="w-full bg-white border border-[var(--stone)] rounded-xl px-4 py-2.5 text-[var(--text)] text-sm focus:outline-none focus:border-[var(--gold)]"/>
          </div>
          <div>
            <label class="text-[var(--text-faint)] text-xs tracking-widest uppercase block mb-1">Check-Out</label>
            <input type="date" name="check_out" required value="<?= e($editBooking['check_out']) ?>" class="w-full bg-white border border-[var(--stone)] rounded-xl px-4 py-2.5 text-[var(--text)] text-sm focus:outline-none focus:border-[var(--gold)]"/>
          </div>
          <div>
            <label class="text-[var(--text-faint)] text-xs tracking-widest uppercase block mb-1">Status</label>
            <select name="status" class="w-full bg-white border border-[var(--stone)] rounded-xl px-4 py-2.5 text-[var(--text)] text-sm focus:outline-none focus:border-[var(--gold)]">
              <?php foreach (bookingStatusOptions() as $s): ?>
              <option value="<?= $s ?>" <?= $s === $editBooking['status'] ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $s)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="text-[var(--text-faint)] text-xs tracking-widest uppercase block mb-1">Guests</label>
            <input type="number" name="guests" min="1" value="<?= max(1, (int)$editBooking['adults'] + (int)$editBooking['children']) ?>" class="w-full bg-white border border-[var(--stone)] rounded-xl px-4 py-2.5 text-[var(--text)] text-sm focus:outline-none focus:border-[var(--gold)]"/>
          </div>
          <div>
            <!-- Spacer/Placeholder -->
          </div>
          <div>
            <label class="text-[var(--text-faint)] text-xs tracking-widest uppercase block mb-1">Special Requests</label>
            <input type="text" name="special_requests" value="<?= e((string)$editBooking['special_requests']) ?>" class="w-full bg-white border border-[var(--stone)] rounded-xl px-4 py-2.5 text-[var(--text)] text-sm focus:outline-none focus:border-[var(--gold)]"/>
          </div>
        </div>

        <div class="flex justify-end gap-3 pt-2">
          <a href="<?= APP_URL ?>/admin/bookings.php" class="px-4 py-2.5 rounded-xl border border-[var(--stone)] text-[var(--text-muted)] hover:text-[var(--text)] text-sm transition">Cancel</a>
          <button type="submit" class="btn-primary px-5 py-2.5 rounded-xl text-xs tracking-widest">SAVE BOOKING</button>
        </div>
      </form>
    </div>
    <?php endif; ?>

    <?php if ($canManageBookings): ?>
    <!-- Walk-In Booking Form -->
    <div class="bg-white border border-[var(--stone)] shadow-sm rounded-2xl overflow-hidden">
      <!-- Header bar -->
      <div class="flex items-center gap-4 px-6 py-5 border-b border-[var(--stone)] bg-[var(--bg-alt)]/20">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 bg-[var(--lapis)] text-[#f7f4ed]">
          <i data-lucide="door-open" class="w-5 h-5"></i>
        </div>
        <div>
          <h2 class="font-display text-lg text-[var(--lapis)]">Walk-In Booking</h2>
          <p class="text-[var(--text-faint)] text-[10px] tracking-widest uppercase mt-0.5">Front-desk reservation — Stripe payment required</p>
        </div>
        <div class="ml-auto hidden sm:flex items-center gap-2 text-xs text-[var(--text-faint)] tracking-widest uppercase">
          <i data-lucide="calendar-clock" class="w-3.5 h-3.5"></i>
          <?= date('D, d M Y') ?>
        </div>
      </div>

      <form method="POST" class="p-6 space-y-6">
        <input type="hidden" name="action" value="create_walkin"/>

        <!-- Section 1: Guest Identity -->
        <div>
          <div class="flex items-center gap-2 mb-3">
            <span class="w-5 h-5 rounded-full text-[10px] font-bold flex items-center justify-center shrink-0 text-white bg-[var(--gold)]">1</span>
            <span class="text-[var(--text-faint)] text-[10px] tracking-[0.2em] uppercase font-medium">Guest Information</span>
            <div class="flex-1 h-px bg-[var(--stone)] opacity-50"></div>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="relative">
              <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[var(--text-faint)]"><i data-lucide="user" class="w-4 h-4"></i></span>
              <input type="text" name="guest_name" required placeholder="Full name *"
                     class="w-full pl-10 pr-4 py-3 bg-white border border-[var(--stone)] rounded-xl text-[var(--text)] text-sm placeholder-[var(--text-faint)] focus:outline-none focus:border-[var(--gold)] transition-all duration-200"/>
            </div>
            <div class="relative">
              <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[var(--text-faint)]"><i data-lucide="mail" class="w-4 h-4"></i></span>
              <input type="email" name="guest_email" placeholder="Email (optional)"
                     class="w-full pl-10 pr-4 py-3 bg-white border border-[var(--stone)] rounded-xl text-[var(--text)] text-sm placeholder-[var(--text-faint)] focus:outline-none focus:border-[var(--gold)] transition-all duration-200"/>
            </div>
            <div class="relative">
              <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[var(--text-faint)]"><i data-lucide="phone" class="w-4 h-4"></i></span>
              <input type="text" name="guest_phone" placeholder="+92 (optional)"
                     class="w-full pl-10 pr-4 py-3 bg-white border border-[var(--stone)] rounded-xl text-[var(--text)] text-sm placeholder-[var(--text-faint)] focus:outline-none focus:border-[var(--gold)] transition-all duration-200"/>
            </div>
          </div>
        </div>

        <!-- Section 2: Reservation Details -->
        <div>
          <div class="flex items-center gap-2 mb-3">
            <span class="w-5 h-5 rounded-full text-[10px] font-bold flex items-center justify-center shrink-0 text-white bg-[var(--gold)]">2</span>
            <span class="text-[var(--text-faint)] text-[10px] tracking-[0.2em] uppercase font-medium">Reservation Details</span>
            <div class="flex-1 h-px bg-[var(--stone)] opacity-50"></div>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="relative">
              <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[var(--text-faint)] pointer-events-none"><i data-lucide="bed-double" class="w-4 h-4"></i></span>
              <select name="room_id" required
                      class="w-full pl-10 pr-4 py-3 bg-white border border-[var(--stone)] rounded-xl text-[var(--text)] text-sm focus:outline-none appearance-none transition-all duration-200 cursor-pointer">
                <option value="">Select room *</option>
                <?php foreach ($availableRooms as $room): ?>
                <option value="<?= $room['id'] ?>">
                  #<?= e($room['room_number']) ?> — <?= e($room['name']) ?> · <?= currency((float)$room['price_per_night']) ?>/night
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="relative">
              <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[var(--text-faint)] pointer-events-none"><i data-lucide="calendar" class="w-4 h-4"></i></span>
              <input type="date" name="check_in" required value="<?= date('Y-m-d') ?>"
                     class="w-full pl-10 pr-4 py-3 bg-white border border-[var(--stone)] rounded-xl text-[var(--text)] text-sm focus:outline-none focus:border-[var(--gold)] transition-all duration-200"/>
            </div>
            <div class="relative">
              <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[var(--text-faint)] pointer-events-none"><i data-lucide="calendar-check" class="w-4 h-4"></i></span>
              <input type="date" name="check_out" required value="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                     class="w-full pl-10 pr-4 py-3 bg-white border border-[var(--stone)] rounded-xl text-[var(--text)] text-sm focus:outline-none focus:border-[var(--gold)] transition-all duration-200"/>
            </div>
            <div class="relative">
              <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[var(--text-faint)] pointer-events-none"><i data-lucide="users" class="w-4 h-4"></i></span>
              <input type="number" name="guests" min="1" max="10" value="1" placeholder="Guests"
                     class="w-full pl-10 pr-4 py-3 bg-white border border-[var(--stone)] rounded-xl text-[var(--text)] text-sm focus:outline-none focus:border-[var(--gold)] transition-all duration-200"/>
            </div>
          </div>
        </div>

        <!-- Section 3: Notes -->
        <div>
          <div class="flex items-center gap-2 mb-3">
            <span class="w-5 h-5 rounded-full text-[10px] font-bold flex items-center justify-center shrink-0 text-white bg-[var(--gold)]">3</span>
            <span class="text-[var(--text-faint)] text-[10px] tracking-[0.2em] uppercase font-medium">Notes</span>
            <div class="flex-1 h-px bg-[var(--stone)] opacity-50"></div>
          </div>
          <div class="grid grid-cols-1 gap-3">
            <div class="relative">
              <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[var(--text-faint)] pointer-events-none"><i data-lucide="message-square" class="w-4 h-4"></i></span>
              <input type="text" name="special_requests" placeholder="Special requests or notes (optional)"
                     class="w-full pl-10 pr-4 py-3 bg-white border border-[var(--stone)] rounded-xl text-[var(--text)] text-sm placeholder-[var(--text-faint)] focus:outline-none focus:border-[var(--gold)] transition-all duration-200"/>
            </div>
          </div>
        </div>

        <!-- Footer action -->
        <div class="flex items-center justify-between pt-4 border-t border-[var(--stone)]">
          <p class="text-[var(--text-faint)] text-xs">Fields marked <span class="text-[var(--gold)]">*</span> are required</p>
          <button type="submit" class="btn-primary flex items-center gap-2 px-6 py-3 rounded-xl text-xs tracking-widest">
            <i data-lucide="plus-circle" class="w-4 h-4"></i>
            CREATE WALK-IN BOOKING
          </button>
        </div>
      </form>
    </div>
    <?php endif; ?>

    <!-- Filters -->
    <form method="GET" class="bg-white border border-[var(--stone)] rounded-2xl px-5 py-4 flex flex-wrap gap-4 items-end shadow-sm">
      <div class="flex-1 min-w-[200px]">
        <label class="text-[var(--text-faint)] text-xs tracking-widest uppercase block mb-1">Search</label>
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Name / ref / room…"
               class="w-full bg-white border border-[var(--stone)] rounded-xl px-4 py-2.5 text-[var(--text)] text-sm focus:outline-none focus:border-[var(--gold)] transition"/>
      </div>
      <div>
        <label class="text-[var(--text-faint)] text-xs tracking-widest uppercase block mb-1">Status</label>
        <select name="status" class="bg-white border border-[var(--stone)] rounded-xl px-4 py-2.5 text-[var(--text)] text-sm focus:outline-none focus:border-[var(--gold)] transition">
          <option value="">All</option>
          <?php foreach (bookingStatusOptions() as $s): ?>
          <option value="<?= $s ?>" <?= $s===$status?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="btn-primary px-6 py-3 rounded-xl text-xs tracking-widest font-semibold shadow-sm">FILTER</button>
      <a href="bookings.php" class="text-[var(--text-faint)] hover:text-[var(--gold)] text-xs tracking-widest py-3 px-3 transition uppercase font-semibold">Clear</a>
    </form>

    <!-- Status tabs -->
    <div class="flex gap-2 flex-wrap">
      <?php
      $tabs = [''=>'All','pending'=>'Pending','confirmed'=>'Confirmed','checked_in'=>'Checked In','checked_out'=>'Checked Out','cancelled'=>'Cancelled','expired'=>'Expired','payment_failed'=>'Failed Payment'];
      foreach ($tabs as $val => $label):
        $cnt = $db->prepare("SELECT COUNT(*) FROM bookings" . ($val ? " WHERE status='$val'" : ''));
        $cnt->execute(); $cnt = $cnt->fetchColumn();
        $isActive = ($val === $status);
      ?>
      <a href="?status=<?= $val ?>"
         class="px-4 py-1.5 rounded-full text-xs transition-all border <?= $isActive ? 'bg-[var(--lapis)] border-[var(--lapis)] text-[#f7f4ed]' : 'bg-white border-[var(--stone)] text-[var(--text-muted)] hover:border-[var(--gold)] hover:text-[var(--gold)]' ?>">
        <?= $label ?> <span class="opacity-60">(<?= $cnt ?>)</span>
      </a>
      <?php endforeach; ?>
    </div>

    <!-- Table -->
    <div class="bg-white border border-[var(--stone)] rounded-2xl overflow-hidden overflow-x-auto shadow-sm">
      <table class="w-full text-sm min-w-[900px]">
        <thead>
          <tr class="border-b border-[var(--stone)] bg-[var(--bg-alt)]/15">
            <?php foreach (['Ref','Guest','Room','Dates','Nights','Total','Status','Paid?','Actions'] as $h): ?>
            <th class="px-5 py-3 text-left text-[var(--text-faint)] font-medium text-xs tracking-widest uppercase"><?= $h ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($bookings as $b): ?>
          <tr class="border-b border-[var(--stone)]/40 hover:bg-[var(--bg-alt)]/10 transition">
            <td class="px-5 py-4 text-[var(--gold)] font-mono text-xs font-semibold"><?= e($b['booking_ref']) ?></td>
            <td class="px-5 py-4">
              <div class="text-[var(--text)] font-semibold"><?= e($b['guest_name']) ?></div>
              <div class="text-[var(--text-faint)] text-xs"><?= e($b['phone'] ?? $b['email'] ?? '') ?></div>
            </td>
            <td class="px-5 py-4 text-[var(--text-muted)] text-xs font-medium"><?= e($b['room_name']) ?></td>
            <td class="px-5 py-4 text-[var(--text-muted)] text-xs">
              <?= date('d M', strtotime($b['check_in'])) ?> → <?= date('d M Y', strtotime($b['check_out'])) ?>
            </td>
            <td class="px-5 py-4 text-[var(--text-muted)]"><?= $b['nights'] ?></td>
            <td class="px-5 py-4 text-[var(--text)] font-bold"><?= currency($b['total_amount']) ?></td>
            <td class="px-5 py-4">
              <?php if ($canManageBookings): ?>
              <form method="POST" class="inline">
                <input type="hidden" name="action" value="update_status"/>
                <input type="hidden" name="booking_id" value="<?= $b['id'] ?>"/>
                <select name="new_status" onchange="this.form.submit()"
                        class="bg-transparent border-none text-xs focus:outline-none text-[var(--lapis)] font-semibold cursor-pointer">
                  <?php foreach (bookingStatusOptions() as $s): ?>
                  <option value="<?= $s ?>" <?= $s===$b['status']?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
                  <?php endforeach; ?>
                </select>
              </form>
              <?php else: ?>
              <span class="badge-<?= $b['status'] ?> text-xs px-2.5 py-1 rounded-full"><?= ucfirst(str_replace('_',' ',$b['status'])) ?></span>
              <?php endif; ?>
            </td>
            <td class="px-5 py-4">
              <?php if ($b['pay_status'] === 'completed'): ?>
                <span class="text-emerald-700 text-xs font-semibold">✓ Paid</span>
              <?php elseif ($b['pay_status'] === 'pending'): ?>
                <span class="text-amber-700 text-xs font-semibold">Pending</span>
              <?php elseif ($b['pay_status']): ?>
                <span class="text-rose-700 text-xs font-semibold"><?= ucfirst($b['pay_status']) ?></span>
              <?php else: ?>
                <span class="text-[var(--text-faint)] text-xs">—</span>
              <?php endif; ?>
            </td>
            <td class="px-5 py-4">
              <div class="flex gap-1.5">
                <?php if ($canManageBookings): ?>
                <a href="<?= APP_URL ?>/admin/bookings.php?edit=<?= $b['id'] ?>" title="Edit Booking"
                   class="border border-[var(--stone)] text-[var(--text-muted)] hover:border-[var(--gold)] hover:text-[var(--gold)] px-2.5 py-1.5 rounded-lg text-xs transition">
                  <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                </a>
                <!-- Generate invoice -->
                <form method="POST" class="inline">
                  <input type="hidden" name="action" value="generate_invoice"/>
                  <input type="hidden" name="booking_id" value="<?= $b['id'] ?>"/>
                  <button type="submit" title="Generate Invoice"
                          class="border border-[var(--stone)] text-[var(--text-muted)] hover:border-[var(--lapis)] hover:text-[var(--lapis)] px-2.5 py-1.5 rounded-lg text-xs transition">
                    <i data-lucide="file-text" class="w-3.5 h-3.5"></i>
                  </button>
                </form>
                <form method="POST" class="inline" onsubmit="return confirm('Delete this booking and its invoice/payment records?')">
                  <input type="hidden" name="action" value="delete_booking"/>
                  <input type="hidden" name="booking_id" value="<?= $b['id'] ?>"/>
                  <button type="submit" title="Delete Booking"
                          class="border border-[var(--stone)] text-red-500 hover:bg-red-50 px-2.5 py-1.5 rounded-lg text-xs transition">
                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                  </button>
                </form>
                <?php else: ?>
                <span class="text-[var(--text-faint)] text-xs">View only</span>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($bookings)): ?>
          <tr><td colspan="9" class="text-center py-10 text-[var(--text-faint)]">No bookings found</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>
<script>lucide.createIcons();</script>
</body>
</html>
