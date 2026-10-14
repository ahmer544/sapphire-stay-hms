<?php
require_once '../config/database.php';
require_once '../config/helpers.php';

$db = getDB();
$hotelAddress = hotelAddress();
$hotelPhone = hotelPhone();
$hotelEmail = hotelEmail();

$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name']    ?? '');
    $email   = strtolower(trim($_POST['email'] ?? ''));
    $phone   = trim($_POST['phone']   ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (!$name || !$email || !$message) { $error = 'Please fill in all required fields.'; }
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $error = 'Invalid email address.'; }
    else {
        $db->prepare("INSERT INTO contact_messages (name,email,phone,subject,message) VALUES (?,?,?,?,?)")
           ->execute([$name, $email, $phone, $subject, $message]);
        $success = 'Thank you! Our front desk is open 24/7 and will get back to you soon.';
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Contact Us – Sapphire Stay, Muzaffarabad</title>
  <meta name="description" content="Contact Sapphire Stay in Muzaffarabad at <?= e($hotelAddress) ?>."/>
  <?php include '../partials/head.php'; ?>
</head>
<body>
<?php include '../partials/nav.php'; ?>

<!-- Hero Header - Lapis & Chinar -->
<div class="page-hero relative pt-28 pb-16 text-center overflow-hidden">
  <img src="../imgs/banner.jpeg" alt="Sapphire Stay" class="absolute inset-0 w-full h-full object-cover opacity-25"/>
  <div class="absolute inset-0 mist-overlay"></div>
  <div class="relative z-10 px-6">
    <div class="page-hero-panel">
    <div class="uppercase tracking-[0.2em] text-xs text-[var(--text-faint)] mb-2">Get In Touch</div>
    <h1 class="font-display text-5xl md:text-6xl text-[var(--lapis)] mb-3">
      Contact Us
    </h1>
    <p class="text-[var(--text-muted)]">Open 24/7 – Muzaffarabad's finest hospitality</p>
    </div>
  </div>
</div>

<div class="max-w-6xl mx-auto px-6 pb-24 grid grid-cols-1 lg:grid-cols-5 gap-10">

  <!-- ── Info Panel ── -->
  <div class="lg:col-span-2 space-y-5 fade-up">

    <!-- Address -->
    <div class="bg-white border border-[var(--stone)] rounded-2xl p-6 flex gap-4 hover:border-[var(--gold)]/30 transition-all duration-300 shadow-sm">
      <div class="w-12 h-12 bg-[var(--bg-alt)] rounded-xl flex items-center justify-center flex-shrink-0">
        <i data-lucide="map-pin" class="w-5 h-5 text-[var(--gold)]"></i>
      </div>
      <div>
        <p class="text-[var(--text-faint)] text-xs tracking-widest uppercase mb-2 font-medium">Address</p>
        <p class="text-[var(--text)] text-sm leading-relaxed font-semibold"><?= e($hotelAddress) ?></p>
      </div>
    </div>

    <!-- Phone -->
    <div class="bg-white border border-[var(--stone)] rounded-2xl p-6 flex gap-4 hover:border-[var(--gold)]/30 transition-all duration-300 shadow-sm">
      <div class="w-12 h-12 bg-[var(--bg-alt)] rounded-xl flex items-center justify-center flex-shrink-0">
        <i data-lucide="phone" class="w-5 h-5 text-[var(--gold)]"></i>
      </div>
      <div>
        <p class="text-[var(--text-faint)] text-xs tracking-widest uppercase mb-2 font-medium">Phone</p>
        <p class="text-[var(--text)] text-sm leading-relaxed font-semibold">
          <a href="tel:<?= e(phoneHref($hotelPhone)) ?>" class="hover:text-[var(--gold)] transition"><?= e($hotelPhone) ?></a>
        </p>
      </div>
    </div>

    <!-- Email -->
    <div class="bg-white border border-[var(--stone)] rounded-2xl p-6 flex gap-4 hover:border-[var(--gold)]/30 transition-all duration-300 shadow-sm">
      <div class="w-12 h-12 bg-[var(--bg-alt)] rounded-xl flex items-center justify-center flex-shrink-0">
        <i data-lucide="mail" class="w-5 h-5 text-[var(--gold)]"></i>
      </div>
      <div>
        <p class="text-[var(--text-faint)] text-xs tracking-widest uppercase mb-2 font-medium">Email</p>
        <p class="text-[var(--text)] text-sm leading-relaxed font-semibold">
          <a href="mailto:<?= e($hotelEmail) ?>" class="hover:text-[var(--gold)] transition"><?= e($hotelEmail) ?></a>
        </p>
      </div>
    </div>

    <!-- Hours -->
    <div class="bg-white border border-[var(--stone)] rounded-2xl p-6 flex gap-4 hover:border-[var(--gold)]/30 transition-all duration-300 shadow-sm">
      <div class="w-12 h-12 bg-[var(--bg-alt)] rounded-xl flex items-center justify-center flex-shrink-0">
        <i data-lucide="clock" class="w-5 h-5 text-[var(--gold)]"></i>
      </div>
      <div>
        <p class="text-[var(--text-faint)] text-xs tracking-widest uppercase mb-2 font-medium">Hours</p>
        <p class="text-[var(--text)] text-sm leading-relaxed">
          Open 24/7<br/>Check-in: 2:00 PM<br/>Check-out: 12:00 PM
        </p>
      </div>
    </div>

    <!-- Map Embed -->
    <div class="bg-white border border-[var(--stone)] rounded-2xl overflow-hidden h-48 shadow-sm">
      <iframe
        src="https://maps.google.com/maps?q=Sapphire+Stay,+9F36%2BWP,+Muzaffarabad,+AJK,+Pakistan&output=embed&z=17"
        width="100%" height="192" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade">
      </iframe>
    </div>
  </div>

  <!-- ── Contact Form ── -->
  <div class="lg:col-span-3 fade-up">
    <div class="bg-white border border-[var(--stone)] rounded-2xl p-8 shadow-sm">
      <h2 class="font-display text-4xl text-[var(--lapis)] mb-2">Send a Message</h2>
      <p class="text-[var(--text-muted)] text-sm mb-8">Front desk open 24/7 for reservations and guest support.</p>

      <?php if ($success): ?>
      <div class="bg-green-50 border border-green-200 text-green-800 rounded-xl p-4 flex items-center gap-3 mb-6">
        <i data-lucide="check-circle" class="w-5 h-5 flex-shrink-0 text-green-600"></i>
        <?= e($success) ?>
      </div>
      <?php endif; ?>
      <?php if ($error): ?>
      <div class="bg-red-50 border border-red-200 text-red-800 rounded-xl p-4 flex items-center gap-3 mb-6">
        <i data-lucide="alert-circle" class="w-5 h-5 flex-shrink-0 text-red-600"></i>
        <?= e($error) ?>
      </div>
      <?php endif; ?>

      <form method="POST" class="space-y-5">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
          <div>
            <label class="form-label block mb-2">Full Name *</label>
            <input type="text" name="name" required value="<?= e($_POST['name'] ?? '') ?>"
                   class="w-full bg-white border border-[var(--stone)] rounded-xl px-4 py-3 text-[var(--text)] text-sm focus:outline-none focus:border-[var(--gold)] transition" placeholder="Ahmad Khan"/>
          </div>
          <div>
            <label class="form-label block mb-2">Email *</label>
            <input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>"
                   class="w-full bg-white border border-[var(--stone)] rounded-xl px-4 py-3 text-[var(--text)] text-sm focus:outline-none focus:border-[var(--gold)] transition" placeholder="you@email.com"/>
          </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
          <div>
            <label class="form-label block mb-2">Phone</label>
            <input type="tel" name="phone" value="<?= e($_POST['phone'] ?? '') ?>"
                   class="w-full bg-white border border-[var(--stone)] rounded-xl px-4 py-3 text-[var(--text)] text-sm focus:outline-none focus:border-[var(--gold)] transition" placeholder="+92 317 9219995"/>
          </div>
          <div>
            <label class="form-label block mb-2">Subject</label>
            <select name="subject"
                    class="w-full bg-white border border-[var(--stone)] rounded-xl px-4 py-3 text-[var(--text)] text-sm focus:outline-none focus:border-[var(--gold)] transition">
              <option value="">Select a topic</option>
              <option value="Reservation Inquiry">Reservation Inquiry</option>
              <option value="Special Requests">Special Requests</option>
              <option value="Event Planning">Event Planning</option>
              <option value="Feedback">Feedback</option>
              <option value="Other">Other</option>
            </select>
          </div>
        </div>
        <div>
          <label class="form-label block mb-2">Message *</label>
          <textarea name="message" rows="6" required
                    class="w-full bg-white border border-[var(--stone)] rounded-xl px-4 py-3 text-[var(--text)] text-sm focus:outline-none focus:border-[var(--gold)] transition resize-none"
                    placeholder="How can we help you?"><?= e($_POST['message'] ?? '') ?></textarea>
        </div>
        <button type="submit"
                class="btn-primary w-full py-4 rounded-xl font-medium tracking-widest text-sm transition-all duration-300 shadow-md">
          SEND MESSAGE
        </button>
      </form>
    </div>
  </div>
</div>

<?php include '../partials/footer.php'; ?>
<script>
lucide.createIcons();
const obs = new IntersectionObserver(e => e.forEach(el => { if(el.isIntersecting){el.target.classList.add('visible');obs.unobserve(el.target);}}),{threshold:.1});
document.querySelectorAll('.fade-up').forEach(el => obs.observe(el));
</script>
</body>
</html>
