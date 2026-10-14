<?php
require_once '../config/database.php';
require_once '../config/helpers.php';
requireCustomer();

$db   = getDB();
$bId  = (int)($_GET['id'] ?? 0);
$user = authUser();

$booking = $db->prepare("SELECT b.*, r.name AS room_name, r.thumbnail FROM bookings b JOIN rooms r ON r.id=b.room_id WHERE b.id=? AND b.user_id=?");
$booking->execute([$bId, $user['id']]);
$booking = $booking->fetch();
if(!$booking) redirect('pages/bookings.php');

$inv = $db->prepare("SELECT * FROM invoices WHERE booking_id=?");
$inv->execute([$bId]);
$inv = $inv->fetch();

$payment = $db->prepare("SELECT * FROM payments WHERE booking_id=? ORDER BY id DESC LIMIT 1");
$payment->execute([$bId]);
$payment = $payment->fetch();

$isConfirmed = in_array($booking['status'], ['confirmed','checked_in','checked_out'], true);
$pageTitle = $isConfirmed ? 'Booking Confirmed' : 'Booking Received';
$pageMessage = $isConfirmed
  ? 'Your booking has been confirmed. We look forward to hosting you!'
  : 'Your booking is pending payment. It is not confirmed yet and may expire if payment is not completed.';
$iconName = $isConfirmed ? 'check-circle' : 'clock';
$iconColor = $isConfirmed ? 'text-green-400' : 'text-gold-400';
$iconBg = $isConfirmed ? 'bg-green-500/20' : 'bg-gold-500/15';
$amountLabel = $isConfirmed ? 'Total Paid' : 'Total Amount';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= e($pageTitle) ?> – Sapphire Stay</title>
  <?php include '../partials/head.php'; ?>
  <script>tailwind.config={theme:{extend:{colors:{sapphire:{500:'#3350f5',600:'#1e2eeb',700:'#181ed7',900:'#1b2089',950:'#150f57'},gold:{400:'#fbbf24',500:'#d4a843',600:'#b8860b'}},fontFamily:{display:['"Cormorant Garamond"','serif'],sans:['"Jost"','sans-serif']}}}}</script>
</head>
<body>
<?php include '../partials/nav.php'; ?>
<div class="min-h-screen pt-28 pb-20 px-6 flex items-center justify-center">
  <div class="max-w-2xl w-full text-center">
    <!-- Success icon -->
    <div class="w-24 h-24 <?= $iconBg ?> rounded-full flex items-center justify-center mx-auto mb-6 pop">
      <i data-lucide="<?= $iconName ?>" class="w-12 h-12 <?= $iconColor ?>"></i>
    </div>
    <p class="text-gold-500 tracking-[0.4em] text-xs uppercase mb-3"><?= e($pageTitle) ?></p>
    <h1 class="font-display text-5xl font-light text-white mb-3">
      Welcome to <em class="text-gold-shimmer not-italic">Sapphire Stay</em>
    </h1>
    <p class="text-white/50 mb-10"><?= e($pageMessage) ?></p>

    <!-- Booking card -->
    <div class="lapis-card rounded-2xl overflow-hidden text-left mb-8">
      <img src="../<?=e($booking['thumbnail'])?>" alt="Room" class="w-full h-48 object-cover"/>
      <div class="p-8 space-y-4">
        <div class="flex justify-between">
          <div>
            <p class="text-white/40 text-xs tracking-widest uppercase mb-1">Booking Reference</p>
            <p class="text-gold-400 font-mono text-xl font-medium"><?=e($booking['booking_ref'])?></p>
          </div>
          <?php if($inv): ?>
          <div class="text-right">
            <p class="text-white/40 text-xs tracking-widest uppercase mb-1">Invoice</p>
            <p class="text-white/70 font-mono"><?=e($inv['invoice_number'])?></p>
          </div>
          <?php endif; ?>
        </div>
        <div class="grid grid-cols-2 gap-4 text-sm">
          <div><p class="text-white/40 mb-1">Room</p><p class="text-white"><?=e($booking['room_name'])?></p></div>
          <div><p class="text-white/40 mb-1">Guests</p><p class="text-white"><?=$booking['adults']?> Adults, <?=$booking['children']?> Children</p></div>
          <div><p class="text-white/40 mb-1">Check-In</p><p class="text-white"><?=date('D, d M Y',strtotime($booking['check_in']))?></p></div>
          <div><p class="text-white/40 mb-1">Check-Out</p><p class="text-white"><?=date('D, d M Y',strtotime($booking['check_out']))?></p></div>
          <div><p class="text-white/40 mb-1">Duration</p><p class="text-white"><?=$booking['nights']?> Night(s)</p></div>
          <div><p class="text-white/40 mb-1"><?= e($amountLabel) ?></p><p class="text-gold-400 font-medium"><?=currency($booking['total_amount'])?></p></div>
          <?php if($payment): ?>
          <div><p class="text-white/40 mb-1">Payment</p><p class="text-white capitalize"><?=e($payment['method'])?> · <?=e($payment['status'])?></p></div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="flex flex-wrap gap-4 justify-center">
      <a href="../pages/bookings.php"
         class="bg-gradient-to-r from-gold-600 to-gold-500 hover:from-gold-500 hover:to-gold-400 text-white px-8 py-3 rounded-full tracking-widest text-sm transition-all duration-300">
        MY BOOKINGS
      </a>
      <a href="../index.php"
         class="glass border border-white/20 hover:border-gold-500 text-white px-8 py-3 rounded-full tracking-widest text-sm transition-all duration-300">
        BACK TO HOME
      </a>
    </div>
  </div>
</div>
<?php include '../partials/footer.php'; ?>
<script>lucide.createIcons();</script>
</body>
</html>
