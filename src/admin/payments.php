<?php
require_once '../config/database.php';
require_once '../config/helpers.php';
requireRole('admin','staff');
$db   = getDB();
$user = authUser();
refreshBookingLifecycle($db);
$paymentError = getFlash('payment_error');
$paymentSuccess = getFlash('payment_success');

$payments = $db->query("
    SELECT p.*, b.booking_ref, b.total_amount AS booking_total, u.name AS guest_name
    FROM payments p
    JOIN bookings b ON b.id=p.booking_id
    JOIN users u ON u.id=p.user_id
    ORDER BY p.created_at DESC
")->fetchAll();

// Pending (no payment recorded)
$pendingBookings = $db->query("
    SELECT b.*, u.name AS guest_name, r.name AS room_name
    FROM bookings b
    JOIN users u ON u.id=b.user_id
    JOIN rooms r ON r.id=b.room_id
    WHERE b.status='pending'
      AND b.id NOT IN (SELECT booking_id FROM payments WHERE status='completed')
    ORDER BY b.created_at DESC
")->fetchAll();

$pendingExtensions = $db->query("
    SELECT e.*, b.booking_ref, u.name AS guest_name, r.name AS room_name
    FROM booking_extensions e
    JOIN bookings b ON b.id=e.booking_id
    JOIN users u ON u.id=e.user_id
    JOIN rooms r ON r.id=b.room_id
    WHERE e.status='pending'
      AND e.id NOT IN (SELECT extension_id FROM payments WHERE extension_id IS NOT NULL AND status='completed')
    ORDER BY e.created_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Payments – Sapphire Stay Admin</title>
  <?php include 'partials/admin-head.php'; ?>
  <style>
    .badge-completed { background: rgba(16,185,129,0.1); color: #047857; border: 1px solid rgba(16,185,129,0.25); }
    .badge-pending { background: rgba(245,158,11,0.1); color: #b45309; border: 1px solid rgba(245,158,11,0.25); }
    .badge-processing { background: rgba(59,130,246,0.1); color: #1d4ed8; border: 1px solid rgba(59,130,246,0.25); }
    .badge-failed { background: rgba(239,68,68,0.1); color: #b91c1c; border: 1px solid rgba(239,68,68,0.25); }
    .badge-refunded { background: rgba(148,163,184,0.1); color: #475569; border: 1px solid rgba(148,163,184,0.25); }
  </style>
</head>
<body class="admin-shell flex min-h-screen" data-role="<?= e(currentRole()) ?>">
<?php include 'partials/sidebar.php'; ?>
<main class="flex-1 overflow-auto">

  <div class="border-b border-[var(--stone)] bg-white px-6 py-4 sticky top-0 z-30">
    <h1 class="font-display text-2xl text-[var(--lapis)]">Payments</h1>
  </div>

  <div class="p-6 space-y-6">

    <?php if ($paymentSuccess): ?>
    <div class="bg-green-500/20 border border-green-500/40 text-green-300 text-sm rounded-xl p-3 flex items-center gap-2">
      <i data-lucide="check-circle" class="w-4 h-4"></i> <?= e($paymentSuccess) ?>
    </div>
    <?php endif; ?>
    <?php if ($paymentError): ?>
    <div class="bg-red-500/20 border border-red-500/40 text-red-300 text-sm rounded-xl p-3 flex items-center gap-2">
      <i data-lucide="alert-circle" class="w-4 h-4"></i> <?= e($paymentError) ?>
    </div>
    <?php endif; ?>

    <!-- Pending bookings – Stripe only -->
    <?php if ($pendingBookings): ?>
    <div class="bg-white border border-[var(--stone)] shadow-sm rounded-2xl overflow-hidden">
      <div class="px-5 py-4 border-b border-[var(--stone)] bg-[var(--bg-alt)]/15">
        <h3 class="font-display text-lg text-[var(--lapis)]">Awaiting Payment (<?= count($pendingBookings) ?>)</h3>
        <p class="text-[var(--text-faint)] text-xs mt-1">Stripe-only bookings waiting for card payment</p>
      </div>
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-[var(--stone)]">
            <?php foreach (['Ref','Guest','Room','Total','Status'] as $h): ?>
            <th class="px-5 py-3 text-left text-[var(--text-faint)] font-medium text-xs tracking-widest uppercase"><?= $h ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pendingBookings as $pb): ?>
          <tr class="border-b border-[var(--stone)]/40 hover:bg-[var(--bg-alt)]/10 transition">
            <td class="px-5 py-4 text-[var(--gold)] font-mono text-xs font-semibold"><?= e($pb['booking_ref']) ?></td>
            <td class="px-5 py-4 text-[var(--text)] font-semibold"><?= e($pb['guest_name']) ?></td>
            <td class="px-5 py-4 text-[var(--text-muted)] text-xs"><?= e($pb['room_name']) ?></td>
            <td class="px-5 py-4 text-[var(--text)] font-bold"><?= currency($pb['total_amount']) ?></td>
            <td class="px-5 py-4 text-amber-700 text-xs font-semibold">Awaiting Stripe</td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <?php if ($pendingExtensions): ?>
    <div class="bg-white border border-[var(--stone)] shadow-sm rounded-2xl overflow-hidden">
      <div class="px-5 py-4 border-b border-[var(--stone)] bg-[var(--bg-alt)]/15">
        <h3 class="font-display text-lg text-[var(--lapis)]">Awaiting Extension Payment (<?= count($pendingExtensions) ?>)</h3>
        <p class="text-[var(--text-faint)] text-xs mt-1">Stripe-only extension requests waiting for card payment</p>
      </div>
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-[var(--stone)]">
            <?php foreach (['Ref','Guest','Room','Extension','Amount','Status'] as $h): ?>
            <th class="px-5 py-3 text-left text-[var(--text-faint)] font-medium text-xs tracking-widest uppercase"><?= $h ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pendingExtensions as $ext): ?>
          <tr class="border-b border-[var(--stone)]/40 hover:bg-[var(--bg-alt)]/10 transition">
            <td class="px-5 py-4 text-[var(--gold)] font-mono text-xs font-semibold"><?= e($ext['booking_ref']) ?></td>
            <td class="px-5 py-4 text-[var(--text)] font-semibold"><?= e($ext['guest_name']) ?></td>
            <td class="px-5 py-4 text-[var(--text-muted)] text-xs"><?= e($ext['room_name']) ?></td>
            <td class="px-5 py-4 text-[var(--text-muted)] text-xs"><?= date('d M', strtotime($ext['old_check_out'])) ?> → <?= date('d M Y', strtotime($ext['new_check_out'])) ?></td>
            <td class="px-5 py-4 text-[var(--text)] font-bold"><?= currency($ext['amount']) ?></td>
            <td class="px-5 py-4 text-amber-700 text-xs font-semibold">Awaiting Stripe</td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <!-- All payments -->
    <div class="bg-white border border-[var(--stone)] rounded-2xl overflow-hidden overflow-x-auto shadow-sm">
      <div class="px-5 py-4 border-b border-[var(--stone)] bg-[var(--bg-alt)]/15">
        <h3 class="font-display text-lg text-[var(--lapis)]">All Payments</h3>
      </div>
      <table class="w-full text-sm min-w-[750px]">
        <thead>
          <tr class="border-b border-[var(--stone)]">
            <?php foreach (['Booking','Guest','Amount','Method','Status','Stripe PI / Ref','Paid At'] as $h): ?>
            <th class="px-5 py-3 text-left text-[var(--text-faint)] font-medium text-xs tracking-widest uppercase"><?= $h ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($payments as $p): ?>
          <tr class="border-b border-[var(--stone)]/40 hover:bg-[var(--bg-alt)]/10 transition">
            <td class="px-5 py-4 text-[var(--gold)] font-mono text-xs font-semibold"><?= e($p['booking_ref']) ?></td>
            <td class="px-5 py-4 text-[var(--text)] font-semibold"><?= e($p['guest_name']) ?></td>
            <td class="px-5 py-4 text-[var(--text)] font-bold"><?= currency($p['amount']) ?></td>
            <td class="px-5 py-4 text-[var(--text-muted)] capitalize"><?= $p['method'] ?></td>
            <td class="px-5 py-4">
              <span class="badge-<?= $p['status'] ?> text-xs px-2.5 py-1 rounded-full font-semibold"><?= ucfirst($p['status']) ?></span>
            </td>
            <td class="px-5 py-4 text-[var(--text-faint)] text-xs font-mono truncate max-w-[140px]">
              <?= $p['stripe_payment_intent_id'] ? e(substr($p['stripe_payment_intent_id'],0,20)).'…' : '—' ?>
            </td>
            <td class="px-5 py-4 text-[var(--text-muted)] text-xs font-medium">
              <?= $p['paid_at'] ? date('d M Y H:i', strtotime($p['paid_at'])) : '—' ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($payments)): ?>
          <tr><td colspan="7" class="text-center py-10 text-[var(--text-faint)]">No payments recorded</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>
<script>lucide.createIcons();</script>
</body>
</html>
