<?php
require_once '../config/database.php';
require_once '../config/helpers.php';

$db  = getDB();
refreshBookingLifecycle($db);
$id  = (int)($_GET['id'] ?? 0);
if(!$id) redirect('pages/rooms.php');

$room = $db->prepare("SELECT r.*, rc.name AS category_name FROM rooms r LEFT JOIN room_categories rc ON rc.id=r.category_id WHERE r.id=?");
$room->execute([$id]);
$room = $room->fetch();
if(!$room) redirect('pages/rooms.php');

$images    = $db->prepare("SELECT MIN(id) AS id, room_id, image_path, MAX(caption) AS caption, MIN(sort_order) AS sort_order
                           FROM room_images
                           WHERE room_id=?
                           GROUP BY room_id, image_path
                           ORDER BY sort_order, id");
$images->execute([$id]);
$images    = $images->fetchAll();

$amenities = $db->prepare("SELECT a.* FROM amenities a JOIN room_amenities ra ON ra.amenity_id=a.id WHERE ra.room_id=?");
$amenities->execute([$id]);
$amenities = $amenities->fetchAll();

$reviews   = $db->prepare("SELECT rv.*, u.name AS guest FROM reviews rv JOIN users u ON u.id=rv.user_id WHERE rv.room_id=? AND rv.is_approved=1 ORDER BY rv.created_at DESC");
$reviews->execute([$id]);
$reviews   = dedupeReviews($reviews->fetchAll());
$avgRating = count($reviews) ? array_sum(array_column($reviews,'rating'))/count($reviews) : 0;

// Booking form values
$checkIn  = $_GET['check_in']  ?? '';
$checkOut = $_GET['check_out'] ?? '';
$guests   = max(1,(int)($_GET['guests'] ?? 1));
$availabilityStart = $checkIn ?: date('Y-m-d');
$availabilityEnd = $checkOut ?: date('Y-m-d', strtotime('+1 day'));

if ($availabilityEnd <= $availabilityStart) {
  $availabilityEnd = date('Y-m-d', strtotime($availabilityStart . ' +1 day'));
}

$confirmedCondition = bookingConfirmedCondition('b');
$pendingCondition = bookingPendingHoldCondition('b');
$blockingCondition = bookingBlockingCondition('b');

$bookedRangesStmt = $db->prepare("
  SELECT b.check_in, b.check_out, b.status
  FROM bookings b
  WHERE b.room_id=?
    AND $blockingCondition
    AND b.check_out >= CURDATE()
  ORDER BY b.check_in ASC
");
$bookedRangesStmt->execute([$id]);
$bookedRanges = $bookedRangesStmt->fetchAll();

$roomBookedStmt = $db->prepare("SELECT b.check_in, b.check_out, b.status FROM bookings b WHERE b.room_id=? AND $blockingCondition AND NOT(b.check_out<=? OR b.check_in>=?) ORDER BY b.check_in ASC");
$roomBookedStmt->execute([$id, $availabilityStart, $availabilityEnd]);
$conflictingRanges = $roomBookedStmt->fetchAll();
$hasConfirmedConflict = false;
$hasPendingConflict = false;
foreach ($conflictingRanges as $range) {
  if (in_array($range['status'], ['confirmed','checked_in'], true)) {
    $hasConfirmedConflict = true;
  } elseif ($range['status'] === 'pending') {
    $hasPendingConflict = true;
  }
}
$isBooked = $hasConfirmedConflict || $hasPendingConflict;
$displayStatus = $hasConfirmedConflict ? 'booked' : ($hasPendingConflict ? 'pending' : ($room['status'] === 'maintenance' ? 'maintenance' : 'available'));
$isBookable = $displayStatus === 'available';
$isBackOfficeViewer = isBackOfficeAccount();
$nextAvailableDate = null;
if ($isBooked) {
  $candidate = $availabilityStart;
  $foundConflict = false;
  foreach ($bookedRanges as $range) {
    $overlapsSelected = $range['check_in'] < $availabilityEnd && $range['check_out'] > $availabilityStart;
    $extendsBlockedWindow = $foundConflict && $range['check_in'] <= $candidate && $range['check_out'] > $candidate;
    if ($overlapsSelected || $extendsBlockedWindow) {
      $foundConflict = true;
      if ($range['check_out'] > $candidate) {
        $candidate = $range['check_out'];
      }
    }
  }
  $nextAvailableDate = $foundConflict ? $candidate : null;
}
$nights   = 0;
$total    = 0;
if($checkIn && $checkOut) {
    $nights = max(1,(strtotime($checkOut)-strtotime($checkIn))/86400);
    $total  = $room['price_per_night'] * $nights;
}

$flash = getFlash('error');
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?=e($room['name'])?> – Sapphire Stay</title>
  <meta name="description" content="<?=e(substr($room['description']??'',0,160))?>"/>
  <?php include '../partials/head.php'; ?>
  <style>
    body { font-family: 'Spectral', serif; background: var(--bg); color: var(--text); }
    .gallery-thumb { cursor:pointer; transition: all .3s; }
    .gallery-thumb:hover { transform: scale(1.04); }
    .booking-widget { position: sticky; top: 90px; }
  </style>
</head>
<body x-data="{ lightbox: false, lbSrc: '' }">
<?php include '../partials/nav.php'; ?>

<!-- Main image hero -->
<div class="relative h-96 md:h-[520px] overflow-hidden mt-16">
  <img src="../<?=e($room['thumbnail'])?>" alt="<?=e($room['name'])?>"
       class="w-full h-full object-cover" id="hero-img"/>
  <div class="absolute inset-0 bg-gradient-to-t from-black/45 via-transparent to-transparent"></div>
  <div class="absolute left-6 bottom-8">
    <span class="bg-white/95 text-[var(--lapis)] text-xs px-3 py-1 rounded-full font-medium tracking-widest mr-3 border border-[var(--stone)] shadow-sm"><?=e($room['category_name'])?></span>
    <span class="bg-white/80 text-[var(--text-muted)] text-xs px-3 py-1 rounded-full border border-[var(--stone)] shadow-sm">Room <?=e($room['room_number'])?></span>
    <?php 
      $heroStatusClass = $displayStatus === 'available' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : ($displayStatus === 'pending' ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-rose-50 text-rose-700 border-rose-200'); 
    ?>
    <span class="text-xs px-3 py-1 rounded-full uppercase border font-semibold tracking-widest shadow-sm <?= $heroStatusClass ?>"><?= e(str_replace('_', ' ', $displayStatus)) ?></span>
  </div>
</div>

<!-- Thumbnails -->
<?php if($images): ?>
<div class="max-w-7xl mx-auto px-6 -mt-6 relative z-10">
  <div class="flex gap-3 overflow-x-auto pb-2">
    <?php foreach($images as $img): ?>
    <img src="../<?=e($img['image_path'])?>" alt="<?=e($img['caption']??'')?>"
         class="gallery-thumb h-20 w-32 object-cover rounded-lg flex-shrink-0 ring-2 ring-transparent hover:ring-[var(--gold)]"
         @click="lightbox=true;lbSrc='../<?=e($img['image_path'])?>'"/>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- Lightbox -->
<div x-show="lightbox" x-transition class="fixed inset-0 z-[999] bg-black/90 flex items-center justify-center p-6"
     @click.self="lightbox=false">
  <button @click="lightbox=false" class="absolute top-6 right-6 text-white/50 hover:text-white">
    <i data-lucide="x" class="w-8 h-8"></i>
  </button>
  <img :src="lbSrc" class="max-h-[85vh] max-w-full rounded-xl shadow-2xl"/>
</div>

<!-- Content grid -->
<div class="max-w-7xl mx-auto px-6 py-12 grid grid-cols-1 lg:grid-cols-3 gap-10">
  <!-- Left: details (editorial journal style) -->
  <div class="lg:col-span-2 space-y-10">

    <!-- Room info -->
    <div class="page-hero-panel panel-wide mb-8">
      <div class="flex items-start justify-between gap-4 mb-4">
        <h1 class="font-display text-5xl text-[var(--lapis)]"><?=e($room['name'])?></h1>
        <?php if($avgRating): ?>
        <div class="flex items-center gap-2 bg-white border border-[var(--stone)] px-4 py-2 rounded-full shadow-sm">
          <i data-lucide="star" class="w-4 h-4 text-[var(--gold)] fill-[var(--gold)]"></i>
          <span class="text-[var(--text)] font-semibold"><?=number_format($avgRating,1)?></span>
          <span class="text-[var(--text-faint)] text-sm">(<?= count($reviews) ?>)</span>
        </div>
        <?php endif; ?>
      </div>
      <div class="flex flex-wrap gap-4 text-[var(--text-muted)] text-sm mb-6">
        <span class="flex items-center gap-2"><i data-lucide="users" class="w-4 h-4 text-[var(--gold)]"></i> Up to <?=$room['capacity']?> guests</span>
        <span class="flex items-center gap-2"><i data-lucide="bed" class="w-4 h-4 text-[var(--gold)]"></i> <?=$room['bed_type']?> bed</span>
        <span class="flex items-center gap-2"><i data-lucide="layers" class="w-4 h-4 text-[var(--gold)]"></i> Floor <?=$room['floor']?></span>
        <?php if($room['size_sqft']): ?>
        <span class="flex items-center gap-2"><i data-lucide="maximize" class="w-4 h-4 text-[var(--gold)]"></i> <?=$room['size_sqft']?> sqft</span>
        <?php endif; ?>
      </div>
      <p class="text-[var(--text)] leading-relaxed text-base"><?=nl2br(e($room['description']))?></p>
    </div>

    <!-- Amenities -->
    <?php if($amenities): ?>
    <div>
      <h2 class="font-display text-3xl text-[var(--lapis)] mb-4">Room Amenities</h2>
      <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
        <?php foreach($amenities as $a): ?>
        <div class="lapis-card px-4 py-3 flex items-center gap-3 text-sm">
          <i data-lucide="check" class="w-4 h-4 text-[var(--gold)] flex-shrink-0"></i>
          <span class="text-[var(--text-muted)]"><?=e($a['name'])?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Reviews -->
    <?php if($reviews): ?>
    <div>
      <h2 class="font-display text-3xl text-[var(--lapis)] mb-4">Guest Reviews</h2>
      <div class="space-y-4">
        <?php foreach($reviews as $r): ?>
        <div class="lapis-card p-6 text-sm">
          <div class="flex items-start justify-between mb-3">
            <div>
              <p class="font-medium text-[var(--lapis)]"><?=e($r['guest'])?></p>
              <p class="text-[var(--text-faint)] text-xs"><?=date('M j, Y',strtotime($r['created_at']))?></p>
            </div>
            <div class="flex gap-0.5 text-[var(--gold)]">
              <?php for($s=1;$s<=5;$s++): ?>
                <i data-lucide="star" class="w-3.5 h-3.5 <?=$s<=$r['rating']?'fill-current':''?>"></i>
              <?php endfor; ?>
            </div>
          </div>
          <?php if($r['title']): ?><p class="font-medium text-[var(--lapis)] mb-1"><?=e($r['title'])?></p><?php endif; ?>
          <p class="text-[var(--text-muted)]"><?=e($r['body'])?></p>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Right: Booking widget (Crystal Reserve) -->
  <div class="lg:col-span-1">
    <div class="lapis-card booking-widget sticky top-24 rounded-3xl overflow-hidden shadow-md">

      <!-- Card Header: price hero -->
      <div class="relative px-7 pt-7 pb-5 text-center border-b border-[var(--stone)]">
        <p class="text-xs tracking-[0.35em] uppercase mb-1 text-[var(--text-faint)]">Starting from</p>
        <div class="font-display leading-none mb-1 text-[var(--lapis)]" style="font-size:clamp(2rem,4vw,2.75rem);">
          PKR <span class="text-[var(--gold)] font-semibold"><?= currencyRaw((float)$room['price_per_night']) ?></span>
        </div>
        <p class="text-xs tracking-widest text-[var(--text-muted)]">per night · tax-free</p>

        <!-- Status badge -->
        <div class="absolute top-4 right-4">
          <?php
            $badgeMap = [
              'available'   => ['bg'=>'rgba(16,185,129,0.08)','bc'=>'rgba(16,185,129,0.2)','tc'=>'#047857','lbl'=>'Available'],
              'booked'      => ['bg'=>'rgba(239,68,68,0.08)','bc'=>'rgba(239,68,68,0.2)','tc'=>'#b91c1c','lbl'=>'Booked'],
              'pending'     => ['bg'=>'rgba(245,158,11,0.08)','bc'=>'rgba(245,158,11,0.2)','tc'=>'#b45309','lbl'=>'Pending'],
              'maintenance' => ['bg'=>'rgba(120,113,108,0.08)','bc'=>'rgba(120,113,108,0.2)','tc'=>'#57534e','lbl'=>'Maintenance'],
            ];
            $b = $badgeMap[$displayStatus] ?? $badgeMap['available'];
          ?>
          <span class="text-[10px] tracking-widest px-2.5 py-1 rounded-full font-semibold border"
                style="background:<?=$b['bg']?>;border-color:<?=$b['bc']?>;color:<?=$b['tc']?>;">
            <?=$b['lbl']?>
          </span>
        </div>
      </div>

      <!-- Flash / booked warning -->
      <?php if($flash): ?>
      <div class="mx-5 mt-4 text-sm rounded-xl px-4 py-3 bg-red-50 border border-red-200 text-red-800"><?=e($flash)?></div>
      <?php endif; ?>
      <?php if(!$isBookable): ?>
      <div class="mx-5 mt-4 text-sm rounded-xl px-4 py-3 bg-amber-50 border border-amber-200 text-amber-800">
        <?php if($hasPendingConflict): ?>
          This room is temporarily held for pending payment. Try again shortly or choose another date.
        <?php elseif($hasConfirmedConflict): ?>
          This room is booked for selected dates. Try different dates or choose another room.
        <?php else: ?>
          This room is not available right now.
        <?php endif; ?>
      </div>
      <?php endif; ?>
      <?php if($conflictingRanges): ?>
      <div class="mx-5 mt-4 rounded-xl px-4 py-3 bg-[var(--bg-alt)] border border-[var(--stone)] shadow-sm">
        <p class="text-xs tracking-widest uppercase mb-2 text-red-700 font-semibold"><?= $hasConfirmedConflict ? 'Booked for these dates' : 'Pending payment hold' ?></p>
        <div class="space-y-1.5">
          <?php foreach($conflictingRanges as $range): ?>
          <div class="flex items-center justify-between gap-3 text-sm">
            <span class="text-[var(--text)]"><?= date('D, d M Y', strtotime($range['check_in'])) ?></span>
            <span class="text-[var(--text-faint)]">to</span>
            <span class="text-[var(--text)]"><?= date('D, d M Y', strtotime($range['check_out'])) ?></span>
            <span class="text-[10px] uppercase tracking-widest font-semibold" style="color:<?= $range['status'] === 'pending' ? '#b45309' : '#b91c1c' ?>;"><?= $range['status'] === 'pending' ? 'Pending' : 'Booked' ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php if($nextAvailableDate): ?>
        <a href="room-detail.php?id=<?= $room['id'] ?>&check_in=<?= e($nextAvailableDate) ?>&check_out=<?= e(date('Y-m-d', strtotime($nextAvailableDate . ' +1 day'))) ?>&guests=<?= $guests ?>"
           class="inline-flex items-center gap-2 mt-3 text-xs tracking-widest text-[var(--gold)] hover:underline transition">
          <i data-lucide="calendar-plus" class="w-3.5 h-3.5"></i>
          TRY <?= date('D, d M Y', strtotime($nextAvailableDate)) ?>
        </a>
        <?php endif; ?>
      </div>
      <?php elseif($bookedRanges): ?>
      <div class="mx-5 mt-4 rounded-xl px-4 py-3 bg-[var(--bg-alt)] border border-[var(--stone)] shadow-sm">
        <p class="text-xs tracking-widest uppercase mb-2 text-[var(--text-faint)]">Upcoming unavailable dates</p>
        <div class="space-y-1.5">
          <?php foreach(array_slice($bookedRanges, 0, 3) as $range): ?>
          <div class="flex items-center justify-between gap-3 text-xs">
            <span class="text-[var(--text-muted)]"><?= date('d M Y', strtotime($range['check_in'])) ?></span>
            <span class="text-[var(--text-faint)]">to</span>
            <span class="text-[var(--text-muted)]"><?= date('d M Y', strtotime($range['check_out'])) ?></span>
            <span class="text-[var(--gold)] font-medium"><?= $range['status'] === 'pending' ? 'Pending' : 'Booked' ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <form action="../booking/create.php" method="POST" id="booking-form" class="px-7 py-5 space-y-0">

        <?php if(!isLoggedIn()): ?>
        <!-- Not logged in state -->
        <div class="rounded-2xl p-6 text-center bg-[var(--bg-alt)] border border-[var(--stone)] shadow-sm">
          <div class="w-12 h-12 rounded-2xl flex items-center justify-center mx-auto mb-3 bg-[var(--stone)]/30">
            <i data-lucide="lock" class="w-5 h-5 text-[var(--gold)]"></i>
          </div>
          <p class="text-sm mb-4 text-[var(--text-muted)]">Sign in to complete your reservation</p>
          <a href="../auth/login.php" class="btn-primary inline-flex items-center gap-2 px-6 py-2.5 rounded-xl text-sm tracking-widest text-white transition-all">
            <i data-lucide="log-in" class="w-4 h-4"></i> SIGN IN
          </a>
        </div>

        <?php elseif($isBackOfficeViewer): ?>
        <div class="rounded-2xl p-6 text-center bg-[var(--bg-alt)] border border-[var(--stone)] shadow-sm">
          <div class="w-12 h-12 rounded-2xl flex items-center justify-center mx-auto mb-3 bg-[var(--stone)]/30">
            <i data-lucide="shield-alert" class="w-5 h-5 text-[var(--gold)]"></i>
          </div>
          <p class="text-sm mb-4 text-[var(--text-muted)]">Staff and admin accounts cannot create customer reservations from the public site.</p>
          <a href="../admin/dashboard.php" class="btn-primary inline-flex items-center gap-2 px-6 py-2.5 rounded-xl text-sm tracking-widest text-white transition-all">
            <i data-lucide="layout-dashboard" class="w-4 h-4"></i> DASHBOARD
          </a>
        </div>

        <?php else: ?>
        <input type="hidden" name="room_id" value="<?=$room['id']?>"/>

        <!-- ── Row 1: Dates ── -->
        <div class="grid grid-cols-2 gap-3 mb-4">
          <div>
            <label class="block text-[10px] tracking-[0.2em] uppercase mb-1.5 font-medium text-[var(--text-muted)]">
              <i data-lucide="calendar" class="w-3 h-3 inline-block mr-1 align-middle text-[var(--gold)]"></i>Check-In
            </label>
            <input type="date" name="check_in" id="bk_ci" value="<?=e($checkIn)?>" required
                   min="<?= date('Y-m-d') ?>"
                   class="w-full bg-white border border-[var(--stone)] rounded-xl px-3 py-2.5 text-[var(--text)] text-sm focus:outline-none focus:border-[var(--gold)] transition-all"/>
          </div>
          <div>
            <label class="block text-[10px] tracking-[0.2em] uppercase mb-1.5 font-medium text-[var(--text-muted)]">
              <i data-lucide="calendar-check" class="w-3 h-3 inline-block mr-1 align-middle text-[var(--gold)]"></i>Check-Out
            </label>
            <input type="date" name="check_out" id="bk_co" value="<?=e($checkOut)?>" required
                   min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                   class="w-full bg-white border border-[var(--stone)] rounded-xl px-3 py-2.5 text-[var(--text)] text-sm focus:outline-none focus:border-[var(--gold)] transition-all"/>
          </div>
        </div>

        <!-- ── Row 2: Guests ── -->
        <div class="mb-4">
          <label class="block text-[10px] tracking-[0.2em] uppercase mb-1.5 font-medium text-[var(--text-muted)]">
            <i data-lucide="users" class="w-3 h-3 inline-block mr-1 align-middle text-[var(--gold)]"></i>Guests
          </label>
          <select name="guests"
                  class="w-full bg-white border border-[var(--stone)] rounded-xl px-3 py-2.5 text-[var(--text)] text-sm focus:outline-none focus:border-[var(--gold)] cursor-pointer">
            <?php for($i=1;$i<=$room['capacity'];$i++): ?>
              <option value="<?=$i?>" <?=$i===$guests?'selected':''?>><?=$i?> <?=$i===1?'Guest':'Guests'?></option>
            <?php endfor; ?>
          </select>
        </div>

        <!-- ── Row 3: Special requests ── -->
        <div class="mb-5">
          <label class="block text-[10px] tracking-[0.2em] uppercase mb-1.5 font-medium text-[var(--text-muted)]">
            <i data-lucide="message-square" class="w-3 h-3 inline-block mr-1 align-middle text-[var(--gold)]"></i>Special Requests
          </label>
          <textarea name="special_requests" rows="2" placeholder="Dietary needs, room preferences…"
                    class="w-full bg-white border border-[var(--stone)] rounded-xl px-3 py-2.5 text-[var(--text)] text-sm focus:outline-none focus:border-[var(--gold)] resize-none transition-all"></textarea>
        </div>

        <!-- ── Price breakdown receipt ── -->
        <div id="price-summary" class="rounded-2xl p-4 mb-5 space-y-2 bg-[var(--bg-alt)] border border-[var(--stone)] shadow-sm">
          <div class="flex justify-between items-center text-xs text-[var(--text-muted)]">
            <span class="flex items-center gap-1.5"><i data-lucide="moon" class="w-3 h-3"></i> <span id="nights-count"><?=$nights?:1?></span> night(s)</span>
            <span>×  PKR <?= number_format((float)$room['price_per_night'], 0) ?></span>
          </div>
          <div class="flex justify-between items-center text-xs text-[var(--text-muted)]">
            <span>Subtotal</span>
            <span id="subtotal">PKR <?= number_format($room['price_per_night']*($nights?:1), 0) ?></span>
          </div>
          <div class="flex justify-between items-center text-xs text-[var(--text-muted)]">
            <span>Tax</span><span>₨ 0 <span class="text-[10px] opacity-60">(tax-free)</span></span>
          </div>
          <div class="h-px my-1 bg-[var(--stone)]"></div>
          <div class="flex justify-between items-center font-semibold text-[var(--text)]">
            <span class="text-sm tracking-wide">Total</span>
            <span id="grand-total" class="text-base text-[var(--gold)] font-bold">PKR <?= number_format($room['price_per_night']*($nights?:1), 0) ?></span>
          </div>
        </div>

        <!-- ── CTA ── -->
        <button type="submit" <?= $isBookable ? '' : 'disabled' ?>
                class="w-full py-4 rounded-2xl font-semibold tracking-[0.2em] text-sm text-white transition-all duration-300 <?= $isBookable ? 'btn-primary shadow-md' : 'bg-stone-100 text-stone-400 border border-stone-200 cursor-not-allowed' ?>">
          <?php if($isBookable): ?>
            <span class="flex items-center justify-center gap-2">
              <i data-lucide="shield-check" class="w-4 h-4"></i> CONFIRM BOOKING
            </span>
          <?php else: ?>
            <span class="flex items-center justify-center gap-2">
              <i data-lucide="calendar-x" class="w-4 h-4"></i> ROOM UNAVAILABLE
            </span>
          <?php endif; ?>
        </button>

        <!-- Trust badges -->
        <div class="flex items-center justify-center gap-4 mt-4 pt-4 border-t border-[var(--stone)]">
          <span class="flex items-center gap-1 text-[10px] tracking-widest text-[var(--text-faint)]">
            <i data-lucide="shield" class="w-3 h-3"></i> SECURE
          </span>
          <span class="flex items-center gap-1 text-[10px] tracking-widest text-[var(--text-faint)]">
            <i data-lucide="ban" class="w-3 h-3"></i> FREE CANCEL
          </span>
          <span class="flex items-center gap-1 text-[10px] tracking-widest text-[var(--text-faint)]">
            <i data-lucide="zap" class="w-3 h-3"></i> INSTANT
          </span>
        </div>

        <?php endif; ?>
      </form>
    </div>
  </div>
</div>

<?php include '../partials/footer.php'; ?>
<script>
lucide.createIcons();
const pricePerNight = <?=$room['price_per_night']?>;
const ci = document.getElementById('bk_ci');
const co = document.getElementById('bk_co');
const today = '<?= date('Y-m-d') ?>';

function updatePrice(){
  if(!ci||!co||!ci.value||!co.value) return;
  const nights = Math.max(1,Math.round((new Date(co.value)-new Date(ci.value))/86400000));
  const sub    = pricePerNight * nights;
  const fmt    = n => 'PKR ' + Math.round(n).toLocaleString();
  document.getElementById('nights-count').textContent = nights;
  document.getElementById('subtotal').textContent = fmt(sub);
  document.getElementById('grand-total').textContent = fmt(sub);
}
if(ci){
  ci.addEventListener('change', function(){
    var next = new Date(this.value); next.setDate(next.getDate()+1);
    var ns = next.toISOString().split('T')[0];
    co.min = ns;
    if(co.value && co.value <= this.value) co.value = ns;
    updatePrice();
  });
}
if(co) co.addEventListener('change', updatePrice);

// Form validation
var form = document.getElementById('booking-form');
if(form) form.addEventListener('submit', function(e){
  if(!ci||!co) return;
  if(ci.value < today){ e.preventDefault(); alert('Check-in cannot be in the past.'); return; }
  if(co.value <= ci.value){ e.preventDefault(); alert('Check-out must be after check-in.'); return; }
});
</script>
</body>
</html>
