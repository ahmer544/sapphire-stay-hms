<?php
require_once '../config/database.php';
require_once '../config/helpers.php';
requireCustomer();

$db = getDB();
refreshBookingLifecycle($db);
$user = authUser();

$bookingId = (int)($_GET['id'] ?? $_POST['booking_id'] ?? 0);
if ($bookingId <= 0) {
    redirect('pages/bookings.php');
}

$stmt = $db->prepare("
    SELECT b.*, r.name AS room_name, r.thumbnail, r.price_per_night, r.room_number
    FROM bookings b
    JOIN rooms r ON r.id=b.room_id
    WHERE b.id=? AND b.user_id=?
      AND b.status IN ('confirmed','checked_in')
");
$stmt->execute([$bookingId, $user['id']]);
$booking = $stmt->fetch();
if (!$booking) {
    flash('error', 'Only active confirmed bookings can be extended.');
    redirect('pages/bookings.php');
}

$error = getFlash('error');
$success = getFlash('success');
$minCheckout = date('Y-m-d', strtotime($booking['check_out'] . ' +1 day'));
$newCheckout = $_POST['new_check_out'] ?? $minCheckout;
$extraNights = max(1, (int)round((strtotime($newCheckout) - strtotime($booking['check_out'])) / 86400));
$extraAmount = max(0, round((float)$booking['price_per_night'] * $extraNights, 0));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newCheckout = trim($_POST['new_check_out'] ?? '');
    $newTs = strtotime($newCheckout);
    $currentTs = strtotime($booking['check_out']);

    if (!$newTs || $newTs <= $currentTs) {
        flash('error', 'Choose a new check-out date after your current check-out date.');
        redirect('booking/extend.php?id=' . $bookingId);
    }

    $extraNights = max(1, (int)round(($newTs - $currentTs) / 86400));
    $extraAmount = round((float)$booking['price_per_night'] * $extraNights, 0);

    if (stripeChargeAmount((float)$extraAmount) < stripeMinimumPkrAmount()) {
        flash('error', 'Stripe cannot process this extension amount because it is below the card minimum. The extension was not held.');
        redirect('booking/extend.php?id=' . $bookingId);
    }

    if (roomHasBlockingBooking($db, (int)$booking['room_id'], $booking['check_out'], $newCheckout, (int)$booking['id'])) {
        flash('error', 'This room is not available for the selected extension dates.');
        redirect('booking/extend.php?id=' . $bookingId);
    }

    try {
        $db->beginTransaction();
        $db->prepare("UPDATE booking_extensions SET status='cancelled' WHERE booking_id=? AND user_id=? AND status='pending'")
            ->execute([$bookingId, $user['id']]);
        $db->prepare("
            INSERT INTO booking_extensions (booking_id,user_id,old_check_out,new_check_out,extra_nights,amount,currency,status)
            VALUES (?,?,?,?,?,?,'PKR','pending')
        ")->execute([$bookingId, $user['id'], $booking['check_out'], $newCheckout, $extraNights, $extraAmount]);
        $extensionId = (int)$db->lastInsertId();
        $db->commit();
        redirect('payment/checkout.php?extension_id=' . $extensionId);
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        flash('error', $e->getMessage() ?: 'Unable to create extension request.');
        redirect('booking/extend.php?id=' . $bookingId);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Extend Booking - Sapphire Stay</title>
  <?php include '../partials/head.php'; ?>
</head>
<body>
<?php include '../partials/nav.php'; ?>
<div class="min-h-screen pt-28 pb-20 px-6">
  <div class="max-w-4xl mx-auto">
    <div class="mb-8">
      <p class="text-gold-500 tracking-[0.4em] text-xs uppercase mb-2">Manage Booking</p>
      <h1 class="font-display text-5xl font-light text-white">Extend Your Stay</h1>
      <p class="text-white/40 mt-3">Choose a later check-out date. The extension is confirmed only after the extra payment is completed.</p>
    </div>

    <?php if ($error): ?>
      <div class="mb-5 bg-red-500/20 border border-red-500/40 text-red-300 text-sm rounded-xl p-4"><?= e($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="mb-5 bg-green-500/20 border border-green-500/40 text-green-300 text-sm rounded-xl p-4"><?= e($success) ?></div>
    <?php endif; ?>

    <div class="glass rounded-2xl overflow-hidden">
      <div class="grid grid-cols-1 md:grid-cols-5">
        <img src="../<?= e($booking['thumbnail']) ?>" alt="<?= e($booking['room_name']) ?>" class="md:col-span-2 w-full h-64 md:h-full object-cover"/>
        <div class="md:col-span-3 p-7 space-y-6">
          <div>
            <h2 class="font-display text-3xl text-white"><?= e($booking['room_name']) ?></h2>
            <p class="text-white/35 text-xs tracking-widest mt-1">Room <?= e($booking['room_number']) ?> · <?= e($booking['booking_ref']) ?></p>
          </div>

          <div class="grid grid-cols-2 gap-4 text-sm">
            <div><p class="text-white/30 text-xs mb-1">Check-In</p><p><?= date('d M Y', strtotime($booking['check_in'])) ?></p></div>
            <div><p class="text-white/30 text-xs mb-1">Current Check-Out</p><p><?= date('d M Y', strtotime($booking['check_out'])) ?></p></div>
            <div><p class="text-white/30 text-xs mb-1">Current Nights</p><p><?= (int)$booking['nights'] ?></p></div>
            <div><p class="text-white/30 text-xs mb-1">Rate</p><p class="text-gold-400"><?= currency((float)$booking['price_per_night']) ?> / night</p></div>
          </div>

          <form method="POST" class="space-y-5">
            <input type="hidden" name="booking_id" value="<?= (int)$booking['id'] ?>"/>
            <div>
              <label class="text-white/35 text-xs tracking-widest uppercase block mb-2">New Check-Out Date</label>
              <input type="date" name="new_check_out" id="new-checkout" min="<?= e($minCheckout) ?>" value="<?= e($newCheckout) ?>" required
                     class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white text-sm focus:outline-none focus:border-gold-500"/>
            </div>
            <div class="rounded-xl p-4 bg-white/[0.035] border border-white/10 text-sm">
              <div class="flex justify-between text-white/55"><span>Extra nights</span><span id="extra-nights"><?= $extraNights ?></span></div>
              <div class="flex justify-between text-white/55 mt-1"><span>Extra amount</span><span id="extra-amount"><?= currency($extraAmount) ?></span></div>
              <div class="text-white/30 text-xs mt-3">Availability and amount are checked again on the server before payment.</div>
            </div>
            <div class="flex flex-wrap gap-3">
              <button type="submit" class="bg-gradient-to-r from-gold-600 to-gold-500 text-white px-6 py-3 rounded-xl text-xs tracking-widest">CONTINUE TO PAYMENT</button>
              <a href="../pages/bookings.php" class="glass text-white/50 hover:text-white px-6 py-3 rounded-xl text-xs tracking-widest">BACK</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include '../partials/footer.php'; ?>
<script>
lucide.createIcons();
const currentCheckout = new Date('<?= e($booking['check_out']) ?>T00:00:00');
const rate = <?= json_encode((float)$booking['price_per_night']) ?>;
const nightsEl = document.getElementById('extra-nights');
const amountEl = document.getElementById('extra-amount');
document.getElementById('new-checkout').addEventListener('change', (event) => {
  const next = new Date(event.target.value + 'T00:00:00');
  const nights = Math.max(1, Math.round((next - currentCheckout) / 86400000));
  nightsEl.textContent = nights;
  amountEl.textContent = '₨ ' + Math.round(nights * rate).toLocaleString();
});
</script>
</body>
</html>
