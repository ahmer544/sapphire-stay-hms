<?php
// Shared nav partial — Lapis & Chinar redesign (PR1)
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<!-- Lapis & Chinar nav (luminous stone, no theme toggle — light signature) -->
<style>
  /* Nav specific refinements on top of head.php system */
  #navbar {
    background: rgba(247,244,237,0.96);
    border-bottom: 1px solid var(--stone);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    box-shadow: 0 6px 20px -10px rgba(15,23,36,0.08);
  }
  .nav-link {
    position: relative;
    color: var(--text);
    transition: color .2s ease;
  }
  .nav-link:hover { color: var(--gold); }
  .nav-link.gold-vein::after {
    background: linear-gradient(90deg, var(--gold), var(--gold-bright));
  }
  .nav-logo-leaf {
    display: inline-block;
    vertical-align: middle;
    margin-left: 2px;
    opacity: .85;
  }
  .nav-cta {
    background: var(--lapis);
    color: #f7f4ed;
    border: 1px solid var(--lapis-600);
  }
  .nav-cta:hover {
    background: #0c1c35;
    border-color: var(--gold);
  }
</style>

<nav id="navbar" class="fixed top-0 inset-x-0 z-50 transition-all duration-500 py-4">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 flex items-center justify-between gap-4">
    <!-- Brand logo -->
    <a href="<?= APP_URL ?>/index.php" class="flex items-center gap-3 shrink-0 group">
      <img src="<?= APP_URL ?>/bg_Removedlogo.png" alt="Sapphire Stay" class="h-16 w-20 object-contain transition group-hover:scale-105"/>
    </a>

    <?php
      $isBackOffice = isset($_SESSION['user']) && isBackOfficeAccount($_SESSION['user']);
      $isCustomer   = isset($_SESSION['user']) && isCustomerAccount($_SESSION['user']);
      $messageCount = isset($_SESSION['user']) ? messageUnreadCount((int)$_SESSION['user']['id']) : 0;
      $messagesHref = $isBackOffice ? adminUrl('messages') : (APP_URL.'/pages/messages.php');
    ?>

    <!-- Desktop -->
    <div class="hidden xl:flex items-center gap-7 min-w-0">
      <ul class="flex items-center gap-7 text-[13px] font-semibold tracking-[0.12em] text-[var(--text)]">
        <li><a href="<?= APP_URL ?>/index.php" class="nav-link gold-vein transition">HOME</a></li>
        <li><a href="<?= APP_URL ?>/pages/rooms.php" class="nav-link gold-vein transition">ROOMS</a></li>
        <li><a href="<?= APP_URL ?>/pages/gallery.php" class="nav-link gold-vein transition">GALLERY</a></li>
        <li><a href="<?= APP_URL ?>/pages/contact.php" class="nav-link gold-vein transition">CONTACT</a></li>
        <?php if ($isCustomer): ?>
          <li><a href="<?= APP_URL ?>/pages/bookings.php" class="nav-link gold-vein transition">MANAGE BOOKINGS</a></li>
        <?php endif; ?>
        <?php if (isset($_SESSION['user'])): ?>
          <li>
            <a href="<?= $messagesHref ?>" class="nav-link gold-vein transition flex items-center gap-1.5">
              MESSAGES
              <?php if ($messageCount > 0): ?>
                <span class="ml-0.5 min-w-[17px] h-[17px] px-1 rounded-full bg-[var(--gold)] text-[9px] font-semibold flex items-center justify-center text-[#f7f4ed]"><?= $messageCount ?></span>
              <?php endif; ?>
            </a>
          </li>
        <?php endif; ?>
      </ul>

      <div class="flex items-center gap-2.5 text-sm">
        <?php if (isset($_SESSION['user'])): ?>
          <?php if ($isBackOffice): ?>
            <a href="<?= APP_URL ?>/admin/dashboard.php" class="text-xs tracking-widest text-[var(--gold)] hover:text-[var(--gold-bright)] px-3 py-1.5">DASHBOARD</a>
          <?php endif; ?>
          <a href="<?= APP_URL ?>/auth/logout.php" class="px-4 py-1.5 text-xs tracking-widest rounded-full border border-[var(--stone)] hover:border-[var(--gold)] hover:text-[var(--gold)] transition">SIGN OUT</a>
        <?php else: ?>
          <a href="<?= APP_URL ?>/auth/login.php" class="px-4 py-1.5 text-xs tracking-widest rounded-full border border-[var(--stone)] hover:border-[var(--gold)] hover:text-[var(--gold)] transition">SIGN IN</a>
          <a href="<?= APP_URL ?>/pages/rooms.php" class="nav-cta text-xs tracking-[0.14em] px-6 py-2.5 rounded-full transition">BOOK YOUR STAY</a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Mobile burger -->
    <div class="xl:hidden">
      <button type="button" id="nav-mobile-toggle" class="p-2 text-[var(--text-muted)]" aria-label="Open menu" aria-expanded="false">
        <i data-lucide="menu" class="w-5 h-5"></i>
      </button>
    </div>
  </div>

  <!-- Mobile drawer -->
  <div id="nav-mobile-menu" class="hidden xl:hidden border-t bg-[var(--bg)]" style="border-color:var(--stone)">
    <div class="px-5 py-6 flex flex-col gap-1 text-sm tracking-widest text-[var(--text-muted)]">
      <?php foreach ([['index.php','HOME'], ['pages/rooms.php','ROOMS'], ['pages/gallery.php','GALLERY'], ['pages/contact.php','CONTACT']] as [$h,$l]): ?>
        <a href="<?= APP_URL ?>/<?= $h ?>" class="py-2.5"><?= $l ?></a>
      <?php endforeach; ?>
      <?php if ($isCustomer): ?><a href="<?= APP_URL ?>/pages/bookings.php" class="py-2.5">MANAGE BOOKINGS</a><?php endif; ?>
      <?php if (isset($_SESSION['user'])): ?>
        <a href="<?= $messagesHref ?>" class="py-2.5 flex justify-between">MESSAGES <?php if($messageCount>0): ?><span class="text-[10px] bg-[var(--gold)] text-[#f7f4ed] px-1.5 rounded"><?= $messageCount ?></span><?php endif; ?></a>
        <?php if ($isBackOffice): ?><a href="<?= APP_URL ?>/admin/dashboard.php" class="py-2.5 text-[var(--gold)]">DASHBOARD</a><?php endif; ?>
        <a href="<?= APP_URL ?>/auth/logout.php" class="py-2.5 text-[#b45a5a]">SIGN OUT</a>
      <?php else: ?>
        <a href="<?= APP_URL ?>/auth/login.php" class="py-2.5 text-[var(--gold)] font-semibold">SIGN IN</a>
        <a href="<?= APP_URL ?>/pages/rooms.php" class="py-2.5 text-[var(--lapis)] font-semibold">BOOK YOUR STAY</a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<script>
(function(){
  const nav = document.getElementById('navbar');
  const btn = document.getElementById('nav-mobile-toggle');
  const menu = document.getElementById('nav-mobile-menu');

  function onScroll(){
    if(!nav) return;
    const s = window.scrollY > 50;
    nav.style.boxShadow = s ? '0 6px 20px -10px rgba(15,23,36,0.12)' : '0 6px 20px -10px rgba(15,23,36,0.08)';
    nav.style.paddingTop = s ? '12px' : '20px';
    nav.style.paddingBottom = s ? '12px' : '20px';
  }
  window.addEventListener('scroll', onScroll, {passive:true});
  onScroll();

  if (btn && menu) {
    btn.addEventListener('click', () => {
      const hidden = menu.classList.toggle('hidden');
      btn.setAttribute('aria-expanded', String(!hidden));
      btn.innerHTML = hidden ? '<i data-lucide="menu" class="w-5 h-5"></i>' : '<i data-lucide="x" class="w-5 h-5"></i>';
      if (typeof lucide !== 'undefined') lucide.createIcons();
    });
  }
})();
</script>
