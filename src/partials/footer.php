<footer class="site-footer border-t pt-14 pb-9 mt-16">
  <style>
    .site-footer {
      background:
        linear-gradient(180deg, rgba(19,42,79,0.98), rgba(8,12,23,1));
      border-color: rgba(255,255,255,0.12);
      color: rgba(247,244,237,0.78);
    }
    .site-footer a { color: rgba(247,244,237,0.78); }
    .site-footer a:hover { color: var(--gold-bright); }
    .site-footer .footer-muted { color: rgba(247,244,237,0.58); }
    .site-footer .footer-faint { color: rgba(247,244,237,0.42); }
    .site-footer .footer-card-border { border-color: rgba(255,255,255,0.16); }
  </style>
  <?php
    $hotelAddress = hotelAddress();
    $hotelPhone = hotelPhone();
    $hotelEmail = hotelEmail();
  ?>
  <div class="max-w-7xl mx-auto px-6 grid grid-cols-1 md:grid-cols-4 gap-x-8 gap-y-10 mb-10">
    <!-- Brand -->
    <div>
      <div class="flex items-center gap-2.5 mb-3">
        <div class="w-8 h-8 rounded bg-[var(--lapis)] flex items-center justify-center">
          <span class="font-display text-[#f7f4ed] text-sm font-semibold">S</span>
        </div>
        <span class="font-display text-xl text-white">Sapphire <span class="text-[var(--gold-bright)]">Stay</span></span>
      </div>
      <p class="text-sm leading-snug max-w-[17ch] footer-muted">
        <?= getSetting('hotel_tagline', "A crystalline sanctuary in the Himalayan foothills.") ?>
      </p>
      <div class="flex gap-2 mt-4">
        <?php foreach (['instagram','twitter','facebook'] as $s): ?>
          <a href="#" class="w-8 h-8 rounded flex items-center justify-center border footer-card-border transition"><i data-lucide="<?= $s ?>" class="w-3.5 h-3.5"></i></a>
        <?php endforeach; ?>
      </div>
    </div>

    <div>
      <div class="uppercase tracking-[0.14em] text-[10px] mb-3 text-[var(--gold-bright)]">Explore</div>
      <ul class="space-y-[7px] text-sm">
        <li><a href="<?= APP_URL ?>/index.php" class="transition">Home</a></li>
        <li><a href="<?= APP_URL ?>/pages/rooms.php" class="transition">Rooms &amp; Suites</a></li>
        <li><a href="<?= APP_URL ?>/pages/gallery.php" class="transition">Gallery</a></li>
        <li><a href="<?= APP_URL ?>/pages/contact.php" class="transition">Contact</a></li>
      </ul>
    </div>

    <div>
      <div class="uppercase tracking-[0.14em] text-[10px] mb-3 text-[var(--gold-bright)]">Visit</div>
      <div class="text-sm leading-relaxed">
        <?= e($hotelAddress) ?><br>
        <a href="tel:<?= e(phoneHref($hotelPhone)) ?>" class="transition block mt-1.5"><?= e($hotelPhone) ?></a>
        <a href="mailto:<?= e($hotelEmail) ?>" class="transition"><?= e($hotelEmail) ?></a>
      </div>
      <div class="mt-3 text-xs footer-faint">Check-in 14:00 &nbsp;·&nbsp; Check-out 12:00</div>
    </div>

    <div>
      <div class="uppercase tracking-[0.14em] text-[10px] mb-3 text-[var(--gold-bright)]">At Your Service</div>
      <div class="flex flex-wrap gap-1.5 mb-5">
        <span class="text-xs border footer-card-border px-3 py-px rounded">Visa</span>
        <span class="text-xs border footer-card-border px-3 py-px rounded">Mastercard</span>
        <span class="text-xs border footer-card-border px-3 py-px rounded inline-flex items-center gap-1">Stripe</span>
      </div>
      <div class="text-[11px] leading-snug footer-faint">
        Privacy · Terms · Cancellations
      </div>
    </div>
  </div>

  <div class="max-w-7xl mx-auto px-6 pt-5 border-t footer-card-border text-[10px] flex flex-col md:flex-row items-center justify-between gap-2 footer-faint">
    <div>&copy; <?= date('Y') ?> Sapphire Stay, Muzaffarabad. All rights reserved.</div>
    <div class="flex items-center gap-1.5"><i data-lucide="shield-check" class="w-3 h-3 text-[var(--gold-bright)]"></i> Secure stays &amp; Stripe payments</div>
  </div>
</footer>

<script>
if (typeof lucide !== 'undefined') lucide.createIcons();
// Reveal observer (port of old fade-up)
const _revealObs = new IntersectionObserver((entries) => {
  entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('in'); _revealObs.unobserve(e.target); } });
}, { threshold: 0.08 });
document.querySelectorAll('.reveal').forEach(el => _revealObs.observe(el));
// Date mins
const _d = new Date().toISOString().split('T')[0];
document.querySelectorAll('input[type="date"]').forEach(i => { if(!i.min) i.min = _d; });
</script>
