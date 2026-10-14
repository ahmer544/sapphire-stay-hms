<?php
require_once '../config/database.php';
require_once '../config/helpers.php';
requireRole('admin','staff');
$db   = getDB();
$user = authUser();
refreshBookingLifecycle($db);
$checkinError = getFlash('checkin_error');
$checkinSuccess = getFlash('checkin_success');

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'], $_GET['id'])) {
  $action = $_GET['action'];
  $id = (int)$_GET['id'];

  if ($action === 'checkin') {
    $db->prepare("UPDATE bookings SET status='checked_in',checked_in_at=NOW(),handled_by=? WHERE id=? AND status='confirmed'")
       ->execute([$user['id'], $id]);
    syncRoomStatusForBooking($db, $id);
  }

  if ($action === 'checkout') {
    $db->prepare("UPDATE bookings SET status='checked_out',checked_out_at=NOW(),handled_by=? WHERE id=? AND status='checked_in'")
       ->execute([$user['id'], $id]);
    syncRoomStatusForBooking($db, $id);
    flash('checkin_success', 'Guest checked out successfully.');
  }

  redirect('admin/checkin.php');
}

// ── Actions ───────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'checkin') {
        $id = (int)$_POST['booking_id'];
        $db->prepare("UPDATE bookings SET status='checked_in',checked_in_at=NOW(),handled_by=? WHERE id=? AND status='confirmed'")
           ->execute([$user['id'], $id]);
        syncRoomStatusForBooking($db, $id);
    }

    if ($action === 'checkout') {
        $id = (int)$_POST['booking_id'];
        $db->prepare("UPDATE bookings SET status='checked_out',checked_out_at=NOW(),handled_by=? WHERE id=? AND status='checked_in'")
           ->execute([$user['id'], $id]);
        syncRoomStatusForBooking($db, $id);
        flash('checkin_success', 'Guest checked out successfully.');
    }

    if ($action === 'request_extension') {
        $id = (int)($_POST['booking_id'] ?? 0);
        $extraNights = max(1, min(60, (int)($_POST['extra_nights'] ?? 1)));

        try {
            $bookingStmt = $db->prepare("
                SELECT b.*, r.price_per_night, r.name AS room_name, r.room_number, u.name AS guest
                FROM bookings b
                JOIN rooms r ON r.id=b.room_id
                JOIN users u ON u.id=b.user_id
                WHERE b.id=? AND b.status='checked_in'
                FOR UPDATE
            ");

            $db->beginTransaction();
            $bookingStmt->execute([$id]);
            $booking = $bookingStmt->fetch();
            if (!$booking) {
                throw new RuntimeException('Only checked-in stays can be extended from the desk.');
            }

            $newCheckout = date('Y-m-d', strtotime($booking['check_out'] . ' +' . $extraNights . ' day'));
            $extraAmount = round((float)$booking['price_per_night'] * $extraNights, 0);

            if (stripeChargeAmount((float)$extraAmount) < stripeMinimumPkrAmount()) {
                throw new RuntimeException('Stripe cannot process this extension amount because it is below the card minimum.');
            }

            if (roomHasBlockingBooking($db, (int)$booking['room_id'], $booking['check_out'], $newCheckout, (int)$booking['id'])) {
                throw new RuntimeException('This room has another confirmed booking in the requested extension range.');
            }

            $db->prepare("UPDATE booking_extensions SET status='cancelled', updated_at=NOW() WHERE booking_id=? AND status='pending'")
                ->execute([$id]);

            $currency = defined('STRIPE_CURRENCY') ? strtoupper((string)STRIPE_CURRENCY) : 'PKR';
            $db->prepare("
                INSERT INTO booking_extensions (booking_id,user_id,old_check_out,new_check_out,extra_nights,amount,currency,status)
                VALUES (?,?,?,?,?,?,?,'pending')
            ")->execute([$id, $booking['user_id'], $booking['check_out'], $newCheckout, $extraNights, $extraAmount, $currency]);

            $db->commit();
            flash('checkin_success', 'Extension request created for ' . $extraNights . ' night(s). The guest must complete Stripe payment before the checkout date changes.');
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            flash('checkin_error', $e->getMessage() ?: 'Unable to create extension request.');
        }
    }
    redirectUrl(adminUrl('checkin'));
}

$today = date('Y-m-d');
$deadlineExpr = checkoutDeadlineExpression('b');

$checkoutAlerts = $db->query("
    SELECT b.*,u.name AS guest,u.email,u.phone,r.name AS room_name,r.room_number,
           $deadlineExpr AS checkout_deadline,
           TIMESTAMPDIFF(MINUTE, NOW(), $deadlineExpr) AS minutes_to_deadline
    FROM bookings b
    JOIN users u ON u.id=b.user_id
    JOIN rooms r ON r.id=b.room_id
    WHERE b.status='checked_in'
      AND $deadlineExpr <= DATE_ADD(NOW(), INTERVAL 2 HOUR)
    ORDER BY $deadlineExpr ASC
")->fetchAll();

// Today's arrivals (confirmed)
$arrivals = $db->query("
    SELECT b.*,u.name AS guest,u.email,u.phone,r.name AS room_name,r.room_number
    FROM bookings b JOIN users u ON u.id=b.user_id JOIN rooms r ON r.id=b.room_id
    WHERE b.check_in='$today' AND b.status='confirmed'
    ORDER BY b.booking_ref
")->fetchAll();

// Today's departures (checked_in)
$departures = $db->query("
    SELECT b.*,u.name AS guest,u.email,u.phone,r.name AS room_name,r.room_number,
           $deadlineExpr AS checkout_deadline,
           TIMESTAMPDIFF(MINUTE, NOW(), $deadlineExpr) AS minutes_to_deadline
    FROM bookings b JOIN users u ON u.id=b.user_id JOIN rooms r ON r.id=b.room_id
    WHERE b.check_out='$today' AND b.status='checked_in'
    ORDER BY b.booking_ref
")->fetchAll();

// Currently checked in
$inHouse = $db->query("
    SELECT b.*,u.name AS guest,u.phone,r.name AS room_name,r.room_number,
           $deadlineExpr AS checkout_deadline,
           TIMESTAMPDIFF(MINUTE, NOW(), $deadlineExpr) AS minutes_to_deadline
    FROM bookings b JOIN users u ON u.id=b.user_id JOIN rooms r ON r.id=b.room_id
    WHERE b.status='checked_in'
    ORDER BY b.checked_in_at DESC
")->fetchAll();

$pendingExtensionRows = $db->query("
    SELECT e.*
    FROM booking_extensions e
    WHERE e.status='pending'
    ORDER BY e.created_at DESC
")->fetchAll();
$pendingExtensions = [];
foreach ($pendingExtensionRows as $extension) {
  $pendingExtensions[(int)$extension['booking_id']] = $extension;
}

if (!function_exists('renderDeskStayActions')) {
  function renderDeskStayActions(array $booking, array $pendingExtensions): void {
    $bookingId = (int)$booking['id'];
    $pending = $pendingExtensions[$bookingId] ?? null;
    ?>
    <div class="mt-3 flex flex-wrap gap-2 items-center">
      <form method="POST">
        <input type="hidden" name="action" value="checkout"/>
        <input type="hidden" name="booking_id" value="<?= $bookingId ?>"/>
        <button type="submit"
                class="bg-blue-800 hover:bg-blue-900 text-white text-xs px-3 py-1.5 rounded-lg transition whitespace-nowrap uppercase tracking-widest font-semibold">
          Check Out
        </button>
      </form>
      <?php if ($pending): ?>
        <span class="border border-amber-400/40 bg-amber-400/10 text-amber-200 text-[11px] px-3 py-1.5 rounded-lg">
          Extension pending Stripe: <?= (int)$pending['extra_nights'] ?> night(s), <?= currency((float)$pending['amount']) ?>
        </span>
      <?php else: ?>
      <form method="POST" class="flex items-center gap-1.5">
        <input type="hidden" name="action" value="request_extension"/>
        <input type="hidden" name="booking_id" value="<?= $bookingId ?>"/>
        <input type="number" name="extra_nights" min="1" max="60" value="1"
               class="w-16 bg-white/5 border border-white/10 rounded-lg px-2 py-1.5 text-white text-xs"/>
        <button type="submit"
                class="border border-[var(--gold)]/50 text-[var(--gold-bright)] hover:bg-[var(--gold)]/10 text-xs px-3 py-1.5 rounded-lg transition whitespace-nowrap uppercase tracking-widest font-semibold">
          Extend Nights
        </button>
      </form>
      <?php endif; ?>
    </div>
    <?php
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Check-in / Check-out – Sapphire Stay</title>
  <?php include 'partials/admin-head.php'; ?>
  <style>
    /* Specific styles for status actions */
  </style>
</head>
<body class="admin-shell flex min-h-screen" data-role="<?= e(currentRole()) ?>">
<?php include 'partials/sidebar.php'; ?>
<main class="flex-1 overflow-auto">

  <div class="border-b border-[var(--stone)] bg-white px-6 py-4 sticky top-0 z-30">
    <h1 class="font-display text-2xl text-[var(--lapis)]">Check-in / Check-out</h1>
    <p class="text-[var(--text-faint)] text-xs"><?= date('l, d F Y') ?> · Check-out time <?= e(substr(hotelCheckOutTime(), 0, 5)) ?></p>
  </div>

  <div class="p-6 space-y-6">
    <?php if ($checkinError): ?>
    <div class="bg-red-500/20 border border-red-500/40 text-red-300 text-sm rounded-xl p-3 flex items-center gap-2">
      <i data-lucide="alert-circle" class="w-4 h-4"></i> <?= e($checkinError) ?>
    </div>
    <?php endif; ?>
    <?php if ($checkinSuccess): ?>
    <div class="bg-green-500/20 border border-green-500/40 text-green-300 text-sm rounded-xl p-3 flex items-center gap-2">
      <i data-lucide="check-circle" class="w-4 h-4"></i> <?= e($checkinSuccess) ?>
    </div>
    <?php endif; ?>

    <?php if ($checkoutAlerts): ?>
    <div class="glass rounded-2xl overflow-hidden border border-amber-400/25">
      <div class="px-5 py-4 border-b border-white/10 flex items-center justify-between gap-4">
        <div>
          <h2 class="font-display text-xl text-white">Checkout Attention</h2>
          <p class="text-white/35 text-xs">Guests due within 2 hours or already past checkout time.</p>
        </div>
        <span class="text-xs tracking-widest text-[var(--gold-bright)]"><?= count($checkoutAlerts) ?> alert(s)</span>
      </div>
      <div class="divide-y divide-white/10">
        <?php foreach ($checkoutAlerts as $alert): ?>
        <?php
          $mins = (int)$alert['minutes_to_deadline'];
          $isOverdue = $mins < 0;
          $tone = $isOverdue ? 'text-rose-200 border-rose-400/40 bg-rose-500/10' : 'text-amber-200 border-amber-400/40 bg-amber-400/10';
        ?>
        <div class="px-5 py-4 flex flex-col xl:flex-row xl:items-center justify-between gap-3">
          <div>
            <div class="flex items-center gap-2 flex-wrap">
              <span class="text-white font-semibold"><?= e($alert['guest']) ?></span>
              <span class="text-white/35 text-xs">Room <?= e($alert['room_number']) ?> · <?= e($alert['booking_ref']) ?></span>
              <span class="border <?= $tone ?> text-[11px] px-2.5 py-1 rounded-full">
                <?= $isOverdue ? 'Expired ' . abs($mins) . ' min ago' : 'Expiring in ' . $mins . ' min' ?>
              </span>
            </div>
            <div class="text-white/40 text-xs mt-1">
              Checkout deadline: <?= date('d M Y, H:i', strtotime($alert['checkout_deadline'])) ?>
              <?php if (!empty($alert['phone'])): ?> · <?= e($alert['phone']) ?><?php endif; ?>
            </div>
          </div>
          <?php renderDeskStayActions($alert, $pendingExtensions); ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- Arrivals -->
    <div class="bg-white border border-[var(--stone)] shadow-sm rounded-2xl overflow-hidden">
      <div class="flex items-center gap-3 px-5 py-4 border-b border-[var(--stone)] bg-[var(--bg-alt)]/15">
        <div class="w-9 h-9 bg-emerald-100 rounded-xl flex items-center justify-center">
          <i data-lucide="log-in" class="w-5 h-5 text-emerald-800"></i>
        </div>
        <div>
          <h3 class="text-[var(--lapis)] font-semibold">Today's Arrivals</h3>
          <p class="text-[var(--text-faint)] text-xs"><?= count($arrivals) ?> expected</p>
        </div>
      </div>
      <?php if ($arrivals): foreach ($arrivals as $a): ?>
      <div class="px-5 py-4 border-b border-[var(--stone)]/40">
        <div class="flex items-start justify-between gap-3">
          <div>
            <div class="text-[var(--text)] font-semibold"><?= e($a['guest']) ?></div>
            <div class="text-[var(--text-muted)] text-xs mt-0.5 font-medium">Room <?= e($a['room_number']) ?> – <?= e($a['room_name']) ?></div>
            <div class="text-[var(--text-faint)] text-xs mt-0.5"><?= e($a['booking_ref']) ?> &bull; <?= $a['nights'] ?> night(s) &bull; <?= currency($a['total_amount']) ?></div>
            <?php if ($a['phone']): ?><div class="text-[var(--text-faint)] text-xs"><?= e($a['phone']) ?></div><?php endif; ?>
          </div>
          <form method="POST">
            <input type="hidden" name="action" value="checkin"/>
            <input type="hidden" name="booking_id" value="<?= $a['id'] ?>"/>
            <button type="submit"
                    class="bg-emerald-800 hover:bg-emerald-900 text-white text-xs px-3 py-1.5 rounded-lg transition whitespace-nowrap uppercase tracking-widest font-semibold">
              ✓ Check In
            </button>
          </form>
        </div>
      </div>
      <?php endforeach; else: ?>
      <div class="px-5 py-8 text-center text-[var(--text-faint)] text-sm">No arrivals today</div>
      <?php endif; ?>
    </div>

    <!-- Departures -->
    <div class="bg-white border border-[var(--stone)] shadow-sm rounded-2xl overflow-hidden">
      <div class="flex items-center gap-3 px-5 py-4 border-b border-[var(--stone)] bg-[var(--bg-alt)]/15">
        <div class="w-9 h-9 bg-blue-100 rounded-xl flex items-center justify-center">
          <i data-lucide="log-out" class="w-5 h-5 text-blue-800"></i>
        </div>
        <div>
          <h3 class="text-[var(--lapis)] font-semibold">Today's Departures</h3>
          <p class="text-[var(--text-faint)] text-xs"><?= count($departures) ?> expected</p>
        </div>
      </div>
      <?php if ($departures): foreach ($departures as $d): ?>
      <div class="px-5 py-4 border-b border-[var(--stone)]/40">
        <div class="flex items-start justify-between gap-3">
          <div>
            <div class="text-[var(--text)] font-semibold"><?= e($d['guest']) ?></div>
            <div class="text-[var(--text-muted)] text-xs mt-0.5 font-medium">Room <?= e($d['room_number']) ?> – <?= e($d['room_name']) ?></div>
            <div class="text-[var(--text-faint)] text-xs mt-0.5"><?= e($d['booking_ref']) ?> &bull; <?= currency($d['total_amount']) ?></div>
          </div>
          <?php renderDeskStayActions($d, $pendingExtensions); ?>
        </div>
      </div>
      <?php endforeach; else: ?>
      <div class="px-5 py-8 text-center text-[var(--text-faint)] text-sm">No departures today</div>
      <?php endif; ?>
    </div>

    <!-- Currently in-house -->
    <div class="bg-white border border-[var(--stone)] shadow-sm rounded-2xl overflow-hidden">
      <div class="flex items-center gap-3 px-5 py-4 border-b border-[var(--stone)] bg-[var(--bg-alt)]/15">
        <div class="w-9 h-9 bg-amber-100 rounded-xl flex items-center justify-center">
          <i data-lucide="home" class="w-5 h-5 text-amber-800"></i>
        </div>
        <div>
          <h3 class="text-[var(--lapis)] font-semibold">Currently In-House</h3>
          <p class="text-[var(--text-faint)] text-xs"><?= count($inHouse) ?> guests</p>
        </div>
      </div>
      <?php if ($inHouse): foreach ($inHouse as $g): ?>
      <div class="px-5 py-4 border-b border-[var(--stone)]/40">
        <div class="text-[var(--text)] font-semibold"><?= e($g['guest']) ?></div>
        <div class="flex justify-between text-xs text-[var(--text-muted)] mt-1 font-medium">
          <span>Room <?= e($g['room_number']) ?></span>
          <span>Out: <?= date('d M', strtotime($g['check_out'])) ?> · <?= e(substr(hotelCheckOutTime(), 0, 5)) ?></span>
        </div>
        <?php if ((int)$g['minutes_to_deadline'] < 0): ?>
        <div class="mt-2 text-rose-300 text-xs">Checkout expired <?= abs((int)$g['minutes_to_deadline']) ?> min ago.</div>
        <?php elseif ((int)$g['minutes_to_deadline'] <= 120): ?>
        <div class="mt-2 text-amber-300 text-xs">Checkout expires in <?= (int)$g['minutes_to_deadline'] ?> min.</div>
        <?php endif; ?>
        <?php renderDeskStayActions($g, $pendingExtensions); ?>
      </div>
      <?php endforeach; else: ?>
      <div class="px-5 py-8 text-center text-[var(--text-faint)] text-sm">Hotel is empty</div>
      <?php endif; ?>
    </div>
  </div>
  </div>
</main>
<script>lucide.createIcons();</script>
</body>
</html>
