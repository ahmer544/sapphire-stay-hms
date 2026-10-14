<?php
require_once '../config/database.php';
require_once '../config/helpers.php';

if(isLoggedIn()) redirect('index.php');

$error = '';
if($_SERVER['REQUEST_METHOD']==='POST') {
    $email = strtolower(trim($_POST['email']  ?? ''));
    $pass  = $_POST['password'] ?? '';

    if(!$email||!$pass) { $error = 'Please enter email and password.'; }
    else {
        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE email=? AND is_active=1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if($user && password_verify($pass, $user['password_hash'])) {
            loginUser($user);
            $dest = match($user['role']) {
                'admin','staff' => '../admin/dashboard.php',
                default         => '../index.php'
            };
            header('Location: ' . $dest);
            exit;
        }
        $error = 'Invalid email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Sign In – Sapphire Stay</title>
  <?php include '../partials/head.php'; ?>
  <style>
    /* Auth split-screen refinements */
    input:-webkit-autofill { -webkit-box-shadow: 0 0 0px 1000px #fff inset; -webkit-text-fill-color: var(--text); }
    .auth-image { filter: contrast(0.92) saturate(0.9); }
  </style>
</head>
<body class="min-h-screen flex">
  <!-- Left: image panel -->
  <div class="hidden lg:flex lg:w-1/2 relative overflow-hidden">
    <img src="../imgs/room6.jpeg" alt="Luxury Room" class="absolute inset-0 w-full h-full object-cover"/>
    <div class="absolute inset-0 bg-gradient-to-r from-[#07101f]/95 via-[#07101f]/72 to-[#07101f]/35"></div>
    <div class="relative z-10 flex flex-col justify-end p-14">
      <a href="../index.php" class="flex items-center gap-3 mb-auto">
        <div class="w-9 h-9 rounded-full bg-gradient-to-br from-[var(--lapis)] to-[var(--gold)] flex items-center justify-center">
          <span class="text-white font-display font-semibold text-sm">S</span>
        </div>
        <span class="font-display text-2xl tracking-wider text-white">Sapphire Stay</span>
      </a>
      <div class="auth-copy-panel">
        <p class="text-[var(--gold-bright)] tracking-[0.32em] text-xs uppercase mb-3 font-semibold">Welcome Back</p>
        <h2 class="font-display text-5xl font-light text-white leading-tight mb-4">
          Your Luxury<br/>Awaits You
        </h2>
        <p class="text-white/85 text-sm leading-relaxed">Sign in to access your bookings, preferences, and exclusive member benefits.</p>
      </div>
    </div>
  </div>

  <!-- Right: form -->
  <div class="flex-1 flex items-center justify-center px-6 py-16 bg-[var(--bg)] grain">
    <div class="w-full max-w-md">
      <div class="lg:hidden flex items-center gap-3 mb-8">
        <div class="w-9 h-9 rounded-full bg-[var(--lapis)] flex items-center justify-center">
          <span class="text-[#f7f4ed] font-display font-semibold text-sm">S</span>
        </div>
        <span class="font-display text-2xl text-[var(--lapis)]">Sapphire Stay</span>
      </div>

      <h1 class="font-display text-4xl font-light text-[var(--lapis)] mb-2">Sign In</h1>
      <p class="text-[var(--text-muted)] text-sm mb-8">Access your account and bookings</p>

      <?php if($error): ?>
      <div class="bg-red-50 border border-red-200 text-red-800 text-sm rounded-xl p-4 mb-6 flex items-center gap-3">
        <i data-lucide="alert-circle" class="w-4 h-4 flex-shrink-0 text-red-600"></i><?=e($error)?>
      </div>
      <?php endif; ?>
      <?php if(isset($_GET['registered'])): ?>
      <div class="bg-green-50 border border-green-200 text-green-800 text-sm rounded-xl p-4 mb-6 flex items-center gap-3">
        <i data-lucide="check-circle" class="w-4 h-4 flex-shrink-0 text-green-600"></i>Account created! Sign in below.
      </div>
      <?php endif; ?>

      <form method="POST" class="space-y-5">
        <div>
          <label class="form-label block mb-1.5">Email Address</label>
          <div class="relative">
            <i data-lucide="mail" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-[var(--text-faint)]"></i>
            <input type="email" name="email" required value="<?=e($_POST['email']??'')?>"
                   class="w-full pl-11 pr-4 py-3.5 bg-white border border-[var(--stone)] rounded-xl text-[var(--text)] text-sm focus:outline-none focus:border-[var(--gold)] transition"
                   placeholder="you@email.com"/>
          </div>
        </div>
        <div>
          <label class="form-label block mb-1.5">Password</label>
          <div class="relative">
            <i data-lucide="lock" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-[var(--text-faint)]"></i>
            <input type="password" name="password" id="password" required
                   class="w-full pl-11 pr-12 py-3.5 bg-white border border-[var(--stone)] rounded-xl text-[var(--text)] text-sm focus:outline-none focus:border-[var(--gold)] transition"
                   placeholder="Your password"/>
            <button type="button" onclick="togglePass()" class="absolute right-4 top-1/2 -translate-y-1/2 text-[var(--text-faint)] hover:text-[var(--text)]">
              <i data-lucide="eye" class="w-4 h-4" id="eye-icon"></i>
            </button>
          </div>
        </div>

        <button type="submit"
                class="btn-primary w-full py-3.5 rounded-xl font-medium tracking-widest text-sm transition-all duration-300 shadow-md">
          SIGN IN
        </button>
      </form>

      <p class="text-[var(--text-muted)] text-sm text-center mt-8">
        Don't have an account? <a href="register.php" class="text-[var(--gold)] hover:underline font-medium transition">Create one</a>
      </p>

      <!-- Demo credentials -->
      <div class="bg-white border border-[var(--stone)] rounded-xl p-4 mt-8 text-xs text-[var(--text-muted)] space-y-1 shadow-sm">
        <p class="text-[var(--lapis)] mb-2 font-medium">Demo Accounts</p>
        <p>👑 Admin: admin@sapphirestay.com / Admin@1234</p>
        <p>🧑‍💼 Staff: staff@sapphirestay.com / Staff@1234</p>
      </div>
    </div>
  </div>
<script>
lucide.createIcons();
function togglePass(){
  const p=document.getElementById('password');
  const e=document.getElementById('eye-icon');
  if(p.type==='password'){p.type='text';e.setAttribute('data-lucide','eye-off');}
  else{p.type='password';e.setAttribute('data-lucide','eye');}
  lucide.createIcons();
}
</script>
</body>
</html>
