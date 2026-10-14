<?php
require_once '../config/database.php';
require_once '../config/helpers.php';
if(isLoggedIn()) redirect('index.php');

$error = $success = '';
if($_SERVER['REQUEST_METHOD']==='POST') {
    $name  = trim($_POST['name']     ?? '');
    $email = strtolower(trim($_POST['email']    ?? ''));
    $phone = trim($_POST['phone']    ?? '');
    $pass  = $_POST['password']  ?? '';
    $pass2 = $_POST['password2'] ?? '';

    if(!$name||!$email||!$pass) { $error='All fields are required.'; }
    elseif(!filter_var($email,FILTER_VALIDATE_EMAIL)) { $error='Invalid email address.'; }
    elseif(strlen($pass)<8) { $error='Password must be at least 8 characters.'; }
    elseif($pass!==$pass2) { $error='Passwords do not match.'; }
    else {
        $db = getDB();
        $exists = $db->prepare("SELECT id FROM users WHERE email=?");
        $exists->execute([$email]);
        if($exists->fetch()) { $error='Email already registered.'; }
        else {
            $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost'=>12]);
            $db->prepare("INSERT INTO users (name,email,phone,password_hash,role) VALUES (?,?,?,?,'customer')")
               ->execute([$name,$email,$phone,$hash]);
            header('Location: login.php?registered=1');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Register – Sapphire Stay</title>
  <?php include '../partials/head.php'; ?>
</head>
<body class="min-h-screen flex">
  <div class="hidden lg:flex lg:w-1/2 relative overflow-hidden">
    <img src="../imgs/balcony.jpeg" alt="Balcony" class="absolute inset-0 w-full h-full object-cover"/>
    <div class="absolute inset-0 bg-gradient-to-r from-[#07101f]/95 via-[#07101f]/72 to-[#07101f]/35"></div>
    <div class="relative z-10 flex flex-col justify-end p-14">
      <a href="../index.php" class="flex items-center gap-3 mb-auto">
        <div class="w-9 h-9 rounded-full bg-gradient-to-br from-[var(--lapis)] to-[var(--gold)] flex items-center justify-center">
          <span class="text-white font-display text-sm">S</span>
        </div>
        <span class="font-display text-2xl tracking-wider text-white">Sapphire Stay</span>
      </a>
      <div class="auth-copy-panel">
        <p class="text-[var(--gold-bright)] tracking-[0.32em] text-xs uppercase mb-3 font-semibold">Become a Member</p>
        <h2 class="font-display text-5xl font-light text-white mb-4">Begin Your<br/>Sapphire Journey</h2>
        <p class="text-white/85 text-sm leading-relaxed">Unlock exclusive member rates, early check-in, and priority room upgrades.</p>
      </div>
    </div>
  </div>
  <div class="flex-1 flex items-center justify-center px-6 py-16 bg-[var(--bg)] grain">
    <div class="w-full max-w-md">
      <h1 class="font-display text-4xl font-light text-[var(--lapis)] mb-2">Create Account</h1>
      <p class="text-[var(--text-muted)] text-sm mb-8">Join Sapphire Stay for exclusive benefits</p>
      <?php if($error): ?>
      <div class="bg-red-50 border border-red-200 text-red-800 text-sm rounded-xl p-4 mb-6 flex items-center gap-3">
        <i data-lucide="alert-circle" class="w-4 h-4 flex-shrink-0 text-red-600"></i><?=e($error)?>
      </div>
      <?php endif; ?>
      <form method="POST" class="space-y-4">
        <div>
          <label class="form-label block mb-1.5">Full Name</label>
          <div class="relative">
            <i data-lucide="user" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-[var(--text-faint)]"></i>
            <input type="text" name="name" required value="<?=e($_POST['name']??'')?>"
                   class="w-full pl-11 pr-4 py-3.5 bg-white border border-[var(--stone)] rounded-xl text-[var(--text)] text-sm focus:outline-none focus:border-[var(--gold)] transition" placeholder="John Smith"/>
          </div>
        </div>
        <div>
          <label class="form-label block mb-1.5">Email</label>
          <div class="relative">
            <i data-lucide="mail" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-[var(--text-faint)]"></i>
            <input type="email" name="email" required value="<?=e($_POST['email']??'')?>"
                   class="w-full pl-11 pr-4 py-3.5 bg-white border border-[var(--stone)] rounded-xl text-[var(--text)] text-sm focus:outline-none focus:border-[var(--gold)] transition" placeholder="you@email.com"/>
          </div>
        </div>
        <div>
          <label class="form-label block mb-1.5">Phone (Optional)</label>
          <div class="relative">
            <i data-lucide="phone" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-[var(--text-faint)]"></i>
            <input type="tel" name="phone" value="<?=e($_POST['phone']??'')?>"
                   class="w-full pl-11 pr-4 py-3.5 bg-white border border-[var(--stone)] rounded-xl text-[var(--text)] text-sm focus:outline-none focus:border-[var(--gold)] transition" placeholder="+92 317 9219995"/>
          </div>
        </div>
        <div>
          <label class="form-label block mb-1.5">Password</label>
          <div class="relative">
            <i data-lucide="lock" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-[var(--text-faint)]"></i>
            <input type="password" name="password" required
                   class="w-full pl-11 pr-4 py-3.5 bg-white border border-[var(--stone)] rounded-xl text-[var(--text)] text-sm focus:outline-none focus:border-[var(--gold)] transition" placeholder="Min. 8 characters"/>
          </div>
        </div>
        <div>
          <label class="form-label block mb-1.5">Confirm Password</label>
          <div class="relative">
            <i data-lucide="lock" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-[var(--text-faint)]"></i>
            <input type="password" name="password2" required
                   class="w-full pl-11 pr-4 py-3.5 bg-white border border-[var(--stone)] rounded-xl text-[var(--text)] text-sm focus:outline-none focus:border-[var(--gold)] transition" placeholder="Confirm password"/>
          </div>
        </div>
        <button type="submit"
                class="btn-primary w-full py-3.5 rounded-xl font-medium tracking-widest text-sm transition-all duration-300 shadow-md mt-2">
          CREATE ACCOUNT
        </button>
      </form>
      <p class="text-[var(--text-muted)] text-sm text-center mt-8">Already have an account? <a href="login.php" class="text-[var(--gold)] hover:underline font-medium transition">Sign in</a></p>
    </div>
  </div>
<script>lucide.createIcons();</script>
</body>
</html>
