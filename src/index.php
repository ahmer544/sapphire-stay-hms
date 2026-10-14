<?php
require_once 'config/database.php';
require_once 'config/helpers.php';

$requestPath = parse_url($_SERVER['REDIRECT_URL'] ?? $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
if (preg_match('#/admin/(guests|rooms|payments|checkin|invoices|invoice-print)\.php$#', $requestPath, $matches)) {
  $page = $matches[1];
  $params = $_GET;
  unset($params['page']);
  header('Location: ' . adminUrl($page, $params));
  exit;
}

$hotelAddress = hotelAddress();
$hotelPhone = hotelPhone();
$hotelEmail = hotelEmail();
$isCustomer = isCustomerAccount();
$isBackOffice = isBackOfficeAccount();
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Sapphire Stay – Where Every Moment Shines</title>
  <meta name="description" content="A crystalline sanctuary in the Himalayan foothills. Book luminous rooms and suites at Sapphire Stay, Muzaffarabad."/>
  <?php include 'partials/head.php'; ?>
</head>
<body>

<?php include 'partials/nav.php'; ?>
<!-- HERO — actual hotel photography, Lapis & Chinar -->
<section class="relative h-screen min-h-[720px] flex items-end overflow-hidden">
  <!-- Use real hotel exterior image -->
  <img src="imgs/bg_Removedlogo.png" alt="Sapphire Stay Muzaffarabad — Himalayan foothills hotel"
       class="absolute top-16 right-8 w-[280px] md:w-[360px] h-auto object-contain opacity-25 pointer-events-none" decoding="async" fetchpriority="high"/>
  <div class="absolute inset-0" style="background:linear-gradient(180deg,rgba(247,244,237,0.10) 0%,rgba(15,23,36,0.18) 45%,rgba(247,244,237,0.88) 100%)"></div>
  <div class="absolute inset-0" style="background:linear-gradient(90deg,rgba(19,42,79,0.55) 0%,transparent 65%)"></div>

  <!-- Sapphire Stay logo -->
 

  <div class="relative z-10 max-w-5xl mx-auto px-6 pb-20 w-full">
    <div class="max-w-xl">
      <div class="inline-flex items-center gap-2 text-[10px] tracking-[0.22em] text-[var(--gold-bright)] mb-4 reveal">
        <span class="h-px w-6 bg-[var(--gold-bright)] inline-block"></span> MUZAFFARABAD · AJK · HIMALAYAN FOOTHILLS
      </div>
      <h1 class="reveal font-display text-[58px] md:text-[76px] leading-[.93] text-white mb-5" style="animation-delay:80ms;text-shadow:0 2px 24px rgba(15,23,36,0.35)">
        Where the mountains<br><em class="not-italic" style="color:var(--gold-bright)">keep their light.</em>
      </h1>
      <p class="reveal text-white/90 text-[15px] leading-relaxed max-w-sm mb-8" style="animation-delay:180ms">
        A private sanctuary of stone, mist, and rare clarity — perched in the Kashmiri Himalayan foothills.
      </p>
      <div class="reveal flex flex-wrap gap-3" style="animation-delay:280ms">
        <a href="pages/rooms.php" class="btn-primary px-8 py-3.5 rounded-full text-xs tracking-[0.16em] shadow-lg">EXPLORE THE ROOMS</a>
        <a href="#booking-bar" style="background:rgba(255,255,255,0.15);border:1px solid rgba(255,255,255,0.35);color:#fff;backdrop-filter:blur(8px)" class="px-7 py-3.5 rounded-full text-xs tracking-[0.16em] transition hover:border-[var(--gold-bright)]">RESERVE YOUR STAY</a>
      </div>
    </div>
  </div>

  <!-- Hotel image strip -->
  <div class="absolute bottom-0 right-0 w-[42%] h-[55%] overflow-hidden hidden lg:block" style="clip-path:polygon(12% 0,100% 0,100% 100%,0% 100%)">
    <img src="imgs/balcony.jpeg" alt="Balcony view" class="w-full h-full object-cover opacity-70"/>
  </div>

  <div class="absolute bottom-8 left-1/2 -translate-x-1/2 text-[10px] tracking-widest text-white/50 flex flex-col items-center gap-1 z-10">
    SCROLL <i data-lucide="chevron-down" class="w-3 h-3"></i>
  </div>
</section>

<!-- BOOKING PLINTH — overlaps hero bottom as stone tablet -->
<div id="booking-bar" class="relative z-20 -mt-10 max-w-[960px] mx-auto px-5">
  <div class="lapis-card rounded-3xl p-6 md:p-8 shadow-2xl grain">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-5">
      <div>
        <div class="uppercase text-[10px] tracking-[0.22em] text-[var(--text-faint)] mb-0.5">Plan your escape</div>
        <div class="font-display text-[22px] text-[var(--lapis)] leading-tight">Reserve your nights in Kashmir</div>
      </div>
      <div class="text-xs text-[var(--text-faint)] text-right hidden sm:block">Check-in 2PM &bull; Check-out 12PM</div>
    </div>
    <form action="pages/rooms.php" method="GET" class="grid grid-cols-2 md:grid-cols-5 gap-3 items-end">
      <div class="col-span-1">
        <label class="form-label block mb-1.5">ARRIVE</label>
        <input type="date" name="check_in" id="hero_ci" required min="<?= date('Y-m-d') ?>"
               class="w-full rounded-xl px-4 py-3 text-sm border border-[var(--stone)] focus:border-[var(--gold)]">
      </div>
      <div class="col-span-1">
        <label class="form-label block mb-1.5">DEPART</label>
        <input type="date" name="check_out" id="hero_co" required min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
               class="w-full rounded-xl px-4 py-3 text-sm border border-[var(--stone)] focus:border-[var(--gold)]">
      </div>
      <div class="col-span-1">
        <label class="form-label block mb-1.5">GUESTS</label>
        <select name="guests" class="w-full rounded-xl px-4 py-3 text-sm border border-[var(--stone)] focus:border-[var(--gold)]">
          <?php for($i=1;$i<=6;$i++): ?><option value="<?= $i ?>"><?= $i ?> Guest<?= $i>1?'s':'' ?></option><?php endfor; ?>
        </select>
      </div>
      <div class="col-span-1">
        <label class="form-label block mb-1.5">TYPE</label>
        <select name="category" class="w-full rounded-xl px-4 py-3 text-sm border border-[var(--stone)] focus:border-[var(--gold)]">
          <option value="">Any</option><option value="1">Standard</option><option value="2">Deluxe</option><option value="3">Suite</option><option value="4">Penthouse</option>
        </select>
      </div>
      <div class="col-span-2 md:col-span-1">
        <button type="submit" class="btn-primary w-full py-3.5 rounded-2xl text-xs tracking-[0.16em] font-semibold shadow-md">CHECK AVAILABILITY</button>
      </div>
    </form>
    <p id="hero_date_err" class="hidden mt-2 text-xs text-red-600 tracking-wide"></p>
  </div>
</div>



<!-- SVG symbols (Chinar leaf etc) -->
<svg xmlns="http://www.w3.org/2000/svg" style="display:none">
  <symbol id="chinar-leaf" viewBox="0 0 24 24" fill="currentColor">
    <path d="M12 2 Q8 6 6 12 Q10 14 12 20 Q14 14 18 12 Q16 6 12 2 Z M12 4 Q9 8 8 13 Q11 14 12 18 Q13 14 16 13 Q15 8 12 4"/>
  </symbol>
</svg>

<!-- FEATURED ROOMS — stone journal cards -->
<section class="py-20 px-6 max-w-7xl mx-auto">
  <div class="flex items-end justify-between mb-8">
    <div>
      <div class="uppercase tracking-[0.2em] text-xs text-[var(--text-faint)]">The Collection</div>
      <h2 class="font-display text-5xl md:text-6xl text-[var(--lapis)]">Featured Rooms</h2>
    </div>
    <a href="pages/rooms.php" class="hidden md:inline text-xs tracking-widest text-[var(--gold)] hover:underline">ALL ROOMS →</a>
  </div>

  <?php
  $db = getDB();
  $featured = $db->query("SELECT r.*, rc.name AS category_name FROM rooms r LEFT JOIN room_categories rc ON rc.id=r.category_id WHERE r.is_featured=1 AND r.status='available' LIMIT 6")->fetchAll();
  ?>

  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php foreach($featured as $i => $room): ?>
    <div class="room-card lapis-card rounded-3xl overflow-hidden reveal group cursor-pointer"
         style="animation-delay: <?= 80 + $i*70 ?>ms"
         onclick="window.location='pages/room-detail.php?id=<?= $room['id'] ?>'">
      <div class="relative overflow-hidden h-[218px]">
        <img src="<?= e($room['thumbnail']) ?>" alt="<?= e($room['name']) ?>" class="w-full h-full object-cover"/>
        <div class="absolute inset-0 bg-gradient-to-t from-black/40 via-black/10 to-transparent"></div>
        <div class="absolute top-4 left-4">
          <span class="inline-block bg-white/90 text-[var(--lapis)] text-[10px] px-3 py-px tracking-widest rounded"><?= e($room['category_name']) ?></span>
        </div>
        <div class="absolute top-4 right-4 chinar">
          <svg width="22" height="22"><use href="#chinar-leaf"/></svg>
        </div>
        <div class="absolute bottom-4 right-4 text-right">
          <div class="inline-block rounded-lg bg-white/95 px-3 py-1 shadow-sm">
            <span class="price text-[var(--lapis)] text-2xl tabular-nums">PKR <?= currencyRaw((float)$room['price_per_night']) ?></span>
            <span class="text-[var(--text-muted)] text-xs block -mt-1">per night</span>
          </div>
        </div>
      </div>
      <div class="p-6 info bg-white">
        <h3 class="font-display text-2xl text-[var(--lapis)] mb-1"><?= e($room['name']) ?></h3>
        <p class="text-[var(--text-muted)] text-sm leading-snug line-clamp-2 mb-4"><?= e($room['description']) ?></p>
        <div class="flex items-center gap-x-4 text-xs text-[var(--text-faint)] mb-5">
          <span class="flex items-center gap-1"><i data-lucide="users" class="w-3.5 h-3.5"></i><?= $room['capacity'] ?></span>
          <span class="flex items-center gap-1"><i data-lucide="bed" class="w-3.5 h-3.5"></i><?= e($room['bed_type']) ?></span>
          <?php if ($room['size_sqft']): ?><span><?= (int)$room['size_sqft'] ?> sqft</span><?php endif; ?>
        </div>
        <div class="text-[var(--gold)] text-xs tracking-[0.14em] border-t border-[var(--stone)] pt-3 flex justify-between items-center">
          <span><?= $isBackOffice ? 'VIEW DETAILS' : 'VIEW & RESERVE' ?></span>
          <span class="group-hover:translate-x-0.5 transition">→</span>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- AMENITIES — inlaid tablets -->
<section class="py-16 px-6 max-w-7xl mx-auto border-t border-[var(--stone)]">
  <div class="text-center mb-9">
    <div class="uppercase tracking-[0.18em] text-xs text-[var(--text-faint)]">The Details</div>
    <h3 class="font-display text-4xl text-[var(--lapis)] tracking-tight">Everything is considered</h3>
  </div>
  <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
    <?php $amenities = [
      ['icon'=>'wifi','title'=>'Wi-Fi','desc'=>'Fast & reliable throughout'],
      ['icon'=>'briefcase','title'=>'Workspace','desc'=>'Quiet desk + natural light'],
      ['icon'=>'car','title'=>'Parking','desc'=>'Complimentary on site'],
      ['icon'=>'wind','title'=>'Climate','desc'=>'Year-round comfort'],
      ['icon'=>'tv','title'=>'Entertainment','desc'=>'Curated in-room viewing'],
      ['icon'=>'bath','title'=>'Bath','desc'=>'Private marble sanctuary'],
      ['icon'=>'shield','title'=>'Safety','desc'=>'Alarms & secure entry'],
      ['icon'=>'map-pin','title'=>'Location','desc'=>'Steps from the Neelum'],
    ]; foreach($amenities as $a): ?>
    <div class="lapis-card rounded-2xl p-5 text-sm flex gap-4 items-start group">
      <div class="mt-0.5 text-[var(--gold)]"><i data-lucide="<?= $a['icon'] ?>" class="w-4 h-4"></i></div>
      <div>
        <div class="font-medium text-[var(--lapis)]"><?= $a['title'] ?></div>
        <div class="text-[var(--text-muted)] text-xs"><?= $a['desc'] ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- VIDEO REEL — framed moments -->
<section class="py-12 px-6 max-w-7xl mx-auto">
  <div class="mb-6 flex items-center gap-3">
    <div class="h-px flex-1 bg-[var(--stone)]"></div>
    <div class="uppercase text-xs tracking-[0.2em] text-[var(--text-faint)]">The Land</div>
    <div class="h-px flex-1 bg-[var(--stone)]"></div>
  </div>
  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <?php
    $vids = [
      "imgs/WhatsApp Video 2026-04-07 at 3.20.25 PM (1).mp4",
      "imgs/WhatsApp Video 2026-04-07 at 3.20.25 PM (2).mp4",
      "imgs/WhatsApp Video 2026-04-07 at 3.20.25 PM (3).mp4",
    ];
    foreach($vids as $v): ?>
    <div class="rounded-2xl overflow-hidden h-64 md:h-[278px] relative ring-1 ring-[var(--stone)] group">
      <video autoplay muted loop playsinline class="w-full h-full object-cover group-hover:scale-[1.03] transition-transform duration-700">
        <source src="<?= e($v) ?>" type="video/mp4"/>
      </video>
      <div class="absolute inset-0 bg-gradient-to-t from-[var(--lapis)]/30 to-transparent"></div>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- GALLERY PREVIEW — editorial broken grid -->
<section class="py-16 px-6 max-w-7xl mx-auto">
  <div class="flex items-baseline justify-between mb-5">
    <div class="font-display text-4xl text-[var(--lapis)]">A few frames from the hills</div>
    <a href="pages/gallery.php" class="text-xs tracking-widest text-[var(--gold)] hover:underline hidden md:block">FULL GALLERY →</a>
  </div>
  <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
    <?php
    $imgs = [
      ['imgs/banner.jpeg',   'The red-roof arrival'],
      ['imgs/room2.jpeg',    'Deluxe calm'],
      ['imgs/room6.jpeg',    'Penthouse light'],
      ['imgs/balcony.jpeg',  'Morning on the ridge'],
      ['imgs/bathroom.jpeg', 'Carved stone bath'],
      ['imgs/turf.jpeg',     'The lawn & peaks'],
      ['imgs/room5.jpeg',    'Suite with view'],
      ['imgs/room3.jpeg',    'Twin marble'],
    ];
    foreach($imgs as $idx => [$src, $cap]):
      $tall = in_array($idx, [0,5]);
    ?>
    <div class="relative overflow-hidden rounded-2xl group <?= $tall ? 'md:row-span-2' : '' ?> aspect-[16/10] md:aspect-auto bg-[var(--stone)]">
      <img src="<?= $src ?>" alt="<?= $cap ?>" class="w-full h-full object-cover group-hover:scale-[1.04] transition-transform duration-700"/>
      <div class="absolute inset-x-0 bottom-0 p-3 text-[10px] text-white/80 tracking-widest bg-gradient-to-t from-black/60"><?= $cap ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- TESTIMONIALS — carved stone cards -->
<section class="py-12 px-6 max-w-5xl mx-auto">
  <div class="text-center mb-8">
    <div class="text-[var(--text-faint)] text-xs tracking-[0.2em]">GUESTS</div>
    <h3 class="font-display text-4xl text-[var(--lapis)]">What they wrote in the book</h3>
  </div>
  <?php
  $reviews = $db->query("SELECT rv.*, u.name AS guest FROM reviews rv JOIN users u ON u.id=rv.user_id WHERE rv.is_approved=1 ORDER BY rv.created_at DESC LIMIT 3")->fetchAll();
  $reviews = dedupeReviews($reviews);
  if(!$reviews): $reviews = [
    ['guest'=>'Ahmad Raza','rating'=>5,'title'=>'Absolutely Stunning','body'=>'Muzaffarabad mein aise hotel ki umeed nahi thi. Penthouse suite ne dil khush kar diya – worth every rupee!'],
    ['guest'=>'Fatima Malik','rating'=>5,'title'=>'Beyond Expectations','body'=>'Service ka level extraordinary tha. Sapphire Stay defines what luxury hospitality truly means in Pakistan.'],
    ['guest'=>'Hassan Kamran','rating'=>5,'title'=>'A Dream Experience','body'=>'Balcony se paharon ka nazar, impeccable room service – I never wanted to leave Muzaffarabad ever again.'],
  ]; endif; ?>
  <div class="grid md:grid-cols-3 gap-5">
    <?php foreach($reviews as $r): ?>
    <div class="lapis-card rounded-2xl p-7 text-sm">
      <div class="flex gap-px mb-4 text-[var(--gold)]"><?php for($s=0;$s<5;$s++): ?><i data-lucide="star" class="w-3.5 h-3.5"></i><?php endfor; ?></div>
      <div class="font-display text-lg text-[var(--lapis)] mb-1"><?= e($r['title']) ?></div>
      <div class="text-[var(--text-muted)] leading-snug">"<?= e($r['body']) ?>"</div>
      <div class="mt-4 text-xs text-[var(--gold)]">— <?= e($r['guest']) ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- FINAL CTA — stone tablet -->
<section class="relative py-20 overflow-hidden border-t border-[var(--stone)]">
  <div class="max-w-4xl mx-auto px-6 text-center">
    <div class="chinar mx-auto mb-3 opacity-30"><svg width="38" height="38"><use href="#chinar-leaf"/></svg></div>
    <div class="font-display text-[42px] md:text-6xl text-[var(--lapis)] leading-none mb-3">The mountains are waiting.<br>So is your room.</div>
    <p class="text-[var(--text-muted)] max-w-sm mx-auto mb-6">Limited nights. Unforgettable mornings. Reserve the one that feels like it was written for you.</p>
    <a href="pages/rooms.php" class="btn-primary inline-block px-9 py-3.5 rounded-2xl text-sm tracking-[0.14em]">VIEW AVAILABLE ROOMS</a>
  </div>
</section>

<?php include 'partials/footer.php'; ?>

<script>
if (typeof lucide !== 'undefined') lucide.createIcons();
// Final reveal observer (in case more .reveal added later)
const _r = new IntersectionObserver(es => es.forEach(e => { if(e.isIntersecting){ e.target.classList.add('in'); _r.unobserve(e.target); }}), {threshold:.1});
document.querySelectorAll('.reveal').forEach(el => _r.observe(el));

// Front page date sync
(function(){
  const ci = document.getElementById('hero_ci');
  const co = document.getElementById('hero_co');
  if(!ci || !co) return;
  const today = '<?= date('Y-m-d') ?>';
  ci.addEventListener('change', function(){
    const next = new Date(this.value);
    next.setDate(next.getDate() + 1);
    const nextStr = next.toISOString().split('T')[0];
    co.min = nextStr;
    if(co.value && co.value <= this.value) co.value = nextStr;
  });
  co.addEventListener('change', function(){
    if(ci.value && this.value <= ci.value){
      const d = new Date(ci.value);
      d.setDate(d.getDate() + 1);
      this.value = d.toISOString().split('T')[0];
    }
  });
})();
</script>
</body>
</html>
