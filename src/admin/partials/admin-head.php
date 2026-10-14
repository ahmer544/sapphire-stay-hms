<?php include __DIR__ . '/../../partials/head.php'; ?>
<style>
  body.admin-shell {
    --bg: #09090f;
    --bg-alt: #11131b;
    --text: #f5f1e8;
    --text-muted: rgba(245,241,232,0.68);
    --text-faint: rgba(245,241,232,0.42);
    --lapis: #172b4d;
    --lapis-600: #224674;
    --gold: #d4a843;
    --gold-bright: #f2c86f;
    --stone: rgba(255,255,255,0.10);
    --border: rgba(255,255,255,0.10);
    --glass: rgba(255,255,255,0.045);
    --role-accent: #d4a843;
    --role-accent-soft: rgba(212,168,67,0.14);
    background:
      radial-gradient(circle at top left, rgba(212,168,67,0.10), transparent 32rem),
      linear-gradient(135deg, #09090f 0%, #111827 52%, #0a1020 100%);
    color: var(--text);
    min-height: 100vh;
  }

  body.admin-shell[data-role="staff"] {
    --role-accent: #60a5fa;
    --role-accent-soft: rgba(96,165,250,0.15);
    --gold: #60a5fa;
    --gold-bright: #93c5fd;
  }

  body.admin-shell main {
    min-width: 0;
    background:
      linear-gradient(180deg, rgba(255,255,255,0.035), transparent 18rem),
      transparent;
  }

  body.admin-shell .admin-sidebar {
    background: rgba(8,12,23,0.96) !important;
    border-color: rgba(255,255,255,0.10) !important;
    box-shadow: 16px 0 50px rgba(0,0,0,0.20);
  }

  body.admin-shell .admin-sidebar a,
  body.admin-shell .admin-sidebar div {
    border-color: rgba(255,255,255,0.10);
  }

  body.admin-shell .admin-sidebar .sidebar-link {
    color: rgba(245,241,232,0.62);
  }

  body.admin-shell .admin-sidebar .sidebar-link:hover {
    background: rgba(255,255,255,0.07);
    color: #fff;
  }

  body.admin-shell .admin-sidebar .sidebar-link.is-active {
    background: linear-gradient(135deg, var(--role-accent-soft), rgba(255,255,255,0.06));
    color: #fff;
    border: 1px solid rgba(212,168,67,0.40);
  }

  body.admin-shell[data-role="staff"] .admin-sidebar .sidebar-link.is-active {
    border-color: rgba(96,165,250,0.42);
  }

  body.admin-shell .admin-topbar,
  body.admin-shell .glass.border-b {
    background: rgba(9,9,15,0.82);
    border-color: rgba(255,255,255,0.10);
    backdrop-filter: blur(18px);
    -webkit-backdrop-filter: blur(18px);
  }

  body.admin-shell .glass,
  body.admin-shell .stat-card,
  body.admin-shell .lapis-card {
    background: rgba(255,255,255,0.055);
    border: 1px solid rgba(255,255,255,0.10);
    box-shadow: 0 24px 70px rgba(0,0,0,0.20);
  }

  body.admin-shell .bg-white {
    background-color: rgba(255,255,255,0.055) !important;
  }

  body.admin-shell .border-\[var\(--stone\)\] {
    border-color: rgba(255,255,255,0.10) !important;
  }

  body.admin-shell .text-\[var\(--lapis\)\],
  body.admin-shell .text-\[var\(--text\)\] {
    color: var(--text) !important;
  }

  body.admin-shell .text-\[var\(--text-muted\)\] {
    color: var(--text-muted) !important;
  }

  body.admin-shell .text-\[var\(--text-faint\)\] {
    color: var(--text-faint) !important;
  }

  body.admin-shell input,
  body.admin-shell textarea,
  body.admin-shell select {
    background: rgba(255,255,255,0.065) !important;
    border-color: rgba(255,255,255,0.12) !important;
    color: #fff !important;
  }

  body.admin-shell input::placeholder,
  body.admin-shell textarea::placeholder {
    color: rgba(255,255,255,0.28);
  }

  body.admin-shell select option {
    background: #111827;
    color: #fff;
  }

  body.admin-shell table {
    color: rgba(245,241,232,0.78);
  }

  body.admin-shell th {
    color: rgba(245,241,232,0.38) !important;
  }

  body.admin-shell tr {
    border-color: rgba(255,255,255,0.07) !important;
  }

  body.admin-shell .text-sapphire-300,
  body.admin-shell .text-sapphire-400,
  body.admin-shell .text-gold-400,
  body.admin-shell .text-gold-500 {
    color: var(--role-accent) !important;
  }

  body.admin-shell .bg-gradient-to-r.from-gold-600,
  body.admin-shell .bg-gradient-to-r.from-gold-600.to-gold-500 {
    background: linear-gradient(135deg, var(--role-accent), var(--gold-bright)) !important;
  }

  body.admin-shell .admin-kicker {
    color: var(--role-accent);
    letter-spacing: .22em;
    text-transform: uppercase;
    font-size: 10px;
  }

  @media (max-width: 900px) {
    body.admin-shell {
      flex-direction: column;
    }
    body.admin-shell .admin-sidebar {
      width: 100%;
      min-height: auto;
      height: auto;
      position: relative;
    }
  }
</style>
