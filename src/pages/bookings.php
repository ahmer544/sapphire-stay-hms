<?php
require_once '../config/database.php';
require_once '../config/helpers.php';
requireCustomer();

$db   = getDB();
$user = authUser();
refreshBookingLifecycle($db);
$bookings = $db->prepare("SELECT b.*, r.name AS room_name, r.thumbnail, p.status AS payment_status
    FROM bookings b
    JOIN rooms r ON r.id=b.room_id
    LEFT JOIN payments p ON p.id=(
      SELECT p2.id FROM payments p2
      WHERE p2.booking_id=b.id AND p2.extension_id IS NULL
      ORDER BY p2.id DESC LIMIT 1
    )
    WHERE b.user_id=?
    ORDER BY b.created_at DESC");
$bookings->execute([$user['id']]);
$bookings = $bookings->fetchAll();

$extensionsStmt = $db->prepare("SELECT * FROM booking_extensions WHERE user_id=? AND status='pending' ORDER BY created_at DESC");
$extensionsStmt->execute([$user['id']]);
$pendingExtensions = [];
foreach ($extensionsStmt->fetchAll() as $extension) {
    $pendingExtensions[(int)$extension['booking_id']] = $extension;
}

$flashError = getFlash('error');
$extensionSuccess = isset($_GET['extended']) ? 'Booking extension confirmed.' : null;
$extensionPending = null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>My Bookings – Sapphire Stay</title>
  <?php include '../partials/head.php'; ?>
  <style>
    body { font-family: 'Spectral', serif; background: var(--bg); color: var(--text); }
    .badge-pending { background: rgba(251, 191, 36, 0.15); color: #b45309; border: 1px solid rgba(251, 191, 36, 0.3); }
    .badge-confirmed { background: rgba(74, 222, 128, 0.15); color: #15803d; border: 1px solid rgba(74, 222, 128, 0.3); }
    .badge-checked_in { background: rgba(96, 165, 250, 0.15); color: #1d4ed8; border: 1px solid rgba(96, 165, 250, 0.3); }
    .badge-checked_out { background: rgba(148, 163, 184, 0.15); color: #475569; border: 1px solid rgba(148, 163, 184, 0.3); }
    .badge-cancelled { background: rgba(248, 113, 113, 0.15); color: #b91c1c; border: 1px solid rgba(248, 113, 113, 0.3); }
    .badge-expired { background: rgba(148, 163, 184, 0.12); color: #475569; border: 1px solid rgba(148, 163, 184, 0.25); }
    .badge-payment_failed { background: rgba(248, 113, 113, 0.15); color: #b91c1c; border: 1px solid rgba(248, 113, 113, 0.3); }
  </style>
</head>
<body>
<?php include '../partials/nav.php'; ?>

<div class="min-h-screen pt-28 pb-20 px-6">
  <div class="max-w-5xl mx-auto">
    <div class="page-hero-panel mb-12">
      <p class="text-[var(--gold)] tracking-[0.4em] text-xs uppercase mb-2">Account</p>
      <h1 class="font-display text-5xl text-[var(--lapis)]">Manage Bookings</h1>
      <div class="mt-5">
        <a href="messages.php" class="inline-flex items-center gap-2 border border-[var(--stone)] px-4 py-2.5 rounded-xl text-sm tracking-widest text-[var(--gold)] hover:bg-[var(--gold)] hover:text-white transition">
          <i data-lucide="messages-square" class="w-4 h-4"></i> MESSAGE STAFF
        </a>
      </div>
    </div>

    <?php if($flashError): ?>
      <div class="mb-5 bg-red-50 border border-red-200 text-red-800 text-sm rounded-xl p-4"><?= e($flashError) ?></div>
    <?php endif; ?>
    <?php if($extensionSuccess || $extensionPending): ?>
      <div class="mb-5 bg-green-50 border border-green-200 text-green-800 text-sm rounded-xl p-4"><?= e($extensionSuccess ?? $extensionPending) ?></div>
    <?php endif; ?>

    <?php if(empty($bookings)): ?>
    <div class="bg-white border border-[var(--stone)] rounded-2xl p-16 text-center shadow-sm">
      <i data-lucide="calendar-x" class="w-16 h-16 text-[var(--text-faint)] mx-auto mb-6 opacity-50"></i>
      <h3 class="font-display text-3xl text-[var(--text-muted)] mb-3">No bookings yet</h3>
      <p class="text-[var(--text-faint)] mb-8">Explore our luxury rooms and make your first booking.</p>
      <a href="rooms.php" class="inline-block bg-gradient-to-r from-gold-600 to-gold-500 text-white px-8 py-3 rounded-full tracking-widest text-sm transition-all">BROWSE ROOMS</a>
    </div>
    <?php else: ?>
    <div class="space-y-4">
      <?php foreach($bookings as $b): ?>
      <?php $pendingExtension = $pendingExtensions[(int)$b['id']] ?? null; ?>
      <div class="bg-white border border-[var(--stone)] rounded-2xl overflow-hidden shadow-sm hover:border-[var(--gold)]/40 transition-colors">
        <div class="flex flex-col md:flex-row">
          <img src="../<?=e($b['thumbnail'])?>" alt="<?=e($b['room_name'])?>"
               class="w-full md:w-44 h-36 md:h-auto object-cover flex-shrink-0"/>
          <div class="flex-1 p-6">
            <div class="flex flex-wrap items-start gap-3 mb-3">
              <h3 class="font-display text-2xl text-[var(--lapis)]"><?=e($b['room_name'])?></h3>
              <span class="badge-<?=$b['status']?> text-xs px-3 py-1 rounded-full"><?=ucfirst(str_replace('_',' ',$b['status']))?></span>
              <span class="ml-auto text-[var(--gold)] font-mono text-sm font-semibold"><?=e($b['booking_ref'])?></span>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm text-[var(--text-muted)] mb-4">
              <div><p class="text-[var(--text-faint)] text-xs mb-0.5">Check-In</p><span class="text-[var(--text)]"><?=date('d M Y',strtotime($b['check_in']))?></span></div>
              <div><p class="text-[var(--text-faint)] text-xs mb-0.5">Check-Out</p><span class="text-[var(--text)]"><?=date('d M Y',strtotime($b['check_out']))?></span></div>
              <div><p class="text-[var(--text-faint)] text-xs mb-0.5">Nights</p><span class="text-[var(--text)] font-medium"><?=$b['nights']?></span></div>
              <div><p class="text-[var(--text-faint)] text-xs mb-0.5">Total</p><span class="text-[var(--gold)] font-semibold"><?=currency($b['total_amount'])?></span></div>
            </div>
            <div class="flex flex-wrap gap-3">
              <?php if($b['status']==='pending'): ?>
              <a href="../payment/checkout.php?booking_id=<?=$b['id']?>"
                 class="bg-gradient-to-r from-gold-600 to-gold-500 text-white px-5 py-2 rounded-lg text-xs tracking-widest transition-all">
                COMPLETE PAYMENT
              </a>
              <a href="../booking/cancel.php?id=<?=$b['id']?>"
                 onclick="return confirm('Cancel this booking?')"
                 class="border border-red-200 text-red-600 px-5 py-2 rounded-lg text-xs tracking-widest hover:bg-red-50 transition">
                CANCEL
              </a>
              <?php elseif(in_array($b['status'], ['confirmed','checked_in'], true)): ?>
              <?php if($pendingExtension): ?>
              <a href="../payment/checkout.php?extension_id=<?=$pendingExtension['id']?>"
                 class="border border-[var(--stone)] text-[var(--gold)] hover:border-[var(--gold)] px-5 py-2 rounded-lg text-xs tracking-widest transition">
                PAY EXTENSION <?= currency((float)$pendingExtension['amount']) ?>
              </a>
              <?php else: ?>
              <a href="../booking/extend.php?id=<?=$b['id']?>"
                 class="border border-[var(--stone)] text-[var(--gold)] hover:border-[var(--gold)] px-5 py-2 rounded-lg text-xs tracking-widest transition">
                EXTEND STAY
              </a>
              <?php endif; ?>
              <?php elseif(in_array($b['status'], ['expired','payment_failed'], true)): ?>
              <a href="room-detail.php?id=<?=$b['room_id']?>&check_in=<?=e($b['check_in'])?>&check_out=<?=e($b['check_out'])?>"
                 class="border border-[var(--stone)] text-[var(--text-muted)] hover:text-[var(--text)] px-5 py-2 rounded-lg text-xs tracking-widest transition">
                BOOK AGAIN
              </a>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php include '../partials/footer.php'; ?>
<script>lucide.createIcons();</script>
</body>
</html>
