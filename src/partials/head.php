<?php
/**
 * Lapis & Chinar — Shared head partial (PR1 foundations)
 * Consolidates fonts, Tailwind config, CSS vars, base styles, icons, Alpine.
 * Pages include this after their <title> + meta description.
 * Usage:
 *   <title>...</title>
 *   <meta name="description" .../>
 *   <?php include __DIR__ . '/partials/head.php'; ?>
 *   </head>
 */
?>
<!-- Lapis & Chinar Design System -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600;700&family=Spectral:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script src="https://unpkg.com/lucide@latest"></script>

<script>
  tailwind.config = {
    theme: {
      extend: {
        colors: {
          lapis: {
            50: '#f0f4fa',
            100: '#d9e4f0',
            300: '#8fa4c4',
            600: '#1e4a7a',
            700: '#132a4f',
            900: '#0c1c35',
          },
          gold: {
            300: '#d4b37a',
            400: '#c9a15e',
            500: '#a37c3a',
            600: '#7a5a2a',
          },
          stone: '#c3b8a3',
          mist: '#d4d0c3',
        },
        fontFamily: {
          display: ['Cinzel', 'serif'],
          body: ['Spectral', 'serif'],
          sans: ['Spectral', 'serif'], // intentional for refined literary feel
        }
      }
    }
  }
</script>

<style>
  :root {
    --bg: #f7f4ed;           /* warm limestone paper */
    --bg-alt: #f0e9dc;
    --bg-deep: #0f1724;      /* twilight pockets */
    --text: #1a1814;
    --text-muted: #4c463d;
    --text-faint: #6f6558;
    --lapis: #132a4f;
    --lapis-600: #1e4a7a;
    --lapis-300: #8fa4c4;
    --gold: #a37c3a;
    --gold-bright: #c9a15e;
    --gold-soft: #d4b37a;
    --mist: #d4d0c3;
    --stone: #c3b8a3;
    --success: #2f4633;
    --error: #6b2f2f;
    --border: rgba(26,24,20,0.12);
    --glass: rgba(255,255,255,0.65);
    --noise: url('data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22100%22 height=%22100%22><filter id=%22n%22><feTurbulence type=%22fractalNoise%22 baseFrequency=%220.9%22 numOctaves=%222%22 stitchTiles=%22stitch%22/></filter><rect width=%22100%22 height=%22100%22 filter=%22url(%23n)%22 opacity=%220.035%22/></svg>');
  }

  /* Luminous stone base */
  html, body {
    background: var(--bg);
    color: var(--text);
    font-family: 'Spectral', serif;
    -webkit-font-smoothing: antialiased;
  }

  .font-display { font-family: 'Cinzel', serif; letter-spacing: 0; }

  /* Cards — inlaid stone / journal panels */
  .lapis-card {
    background: #fff;
    border: 1px solid var(--stone);
    box-shadow: 0 10px 30px -15px rgba(15, 23, 36, 0.12), inset 0 1px 0 rgba(255,255,255,0.7);
    transition: transform .4s cubic-bezier(0.22,1,0.36,1), box-shadow .4s ease, border-color .3s;
  }
  .lapis-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 20px 45px -12px rgba(15, 23, 36, 0.18);
    border-color: var(--gold);
  }

  /* Subtle glass alternative for overlays */
  .stone-glass {
    background: var(--glass);
    border: 1px solid rgba(195,184,163,0.4);
    backdrop-filter: blur(12px);
  }

  /* Chinar leaf motif (gold, low opacity) */
  .chinar {
    color: var(--gold);
    opacity: 0.12;
    transition: opacity .3s ease;
  }
  .lapis-card:hover .chinar,
  .group:hover .chinar { opacity: 0.22; }

  /* Vein details */
  .vein {
    position: relative;
  }
  .vein::after {
    content: '';
    position: absolute;
    left: 0; right: 0; bottom: -1px;
    height: 1px;
    background: linear-gradient(90deg, transparent, var(--lapis-300) 40%, transparent);
    opacity: .35;
  }

  /* Gold vein hover for links / CTAs */
  .gold-vein {
    position: relative;
  }
  .gold-vein::after {
    content: '';
    position: absolute;
    bottom: -2px; left: 0;
    width: 0; height: 1px;
    background: linear-gradient(90deg, var(--gold), var(--gold-bright));
    transition: width .45s cubic-bezier(0.22,1,0.36,1);
  }
  .gold-vein:hover::after { width: 100%; }

  /* Reveal / staggered motion (parting mist) */
  .reveal {
    opacity: 0;
    transform: translateY(22px) scale(0.985);
    transition: opacity 620ms cubic-bezier(0.22,1,0.36,1),
                transform 620ms cubic-bezier(0.22,1,0.36,1);
    will-change: transform, opacity;
  }
  .reveal.in {
    opacity: 1;
    transform: none;
  }
  @media (prefers-reduced-motion: reduce) {
    .reveal, .reveal.in { transition: none !important; transform: none !important; }
  }

  /* Atmospheric mist on heroes / deep sections */
  .mist-overlay {
    background:
      linear-gradient(180deg, rgba(247,244,237,0.32) 0%, rgba(247,244,237,0.08) 32%, transparent 55%),
      linear-gradient(0deg, var(--bg) 82%, transparent 96%);
  }

  /* Twilight pockets (used on hero, modals, certain cards) */
  .twilight {
    --bg: var(--bg-deep);
    --text: #e8e8ec;
    --text-muted: #a8b0c0;
    --text-faint: #6f788a;
    --border: rgba(255,255,255,0.08);
    --gold: #c9a15e;
    color: var(--text);
  }

  /* Typography polish */
  h1, h2, .font-display { font-feature-settings: "tnum", "lnum"; }
  .tracking-ultra { letter-spacing: 0.18em; }

  .page-hero {
    margin-bottom: 2.75rem;
    border-bottom: 1px solid rgba(195,184,163,0.8);
  }

  .page-hero-panel {
    display: block;
    width: fit-content;
    margin-left: auto;
    margin-right: auto;
    max-width: min(920px, calc(100vw - 2rem));
    padding: 1.35rem 1.75rem;
    background: rgba(247,244,237,0.91);
    border: 1px solid rgba(195,184,163,0.86);
    border-radius: 18px;
    box-shadow: 0 22px 65px rgba(15,23,36,0.16);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
  }

  .page-hero-panel.panel-wide {
    width: 100%;
    max-width: 100%;
    margin-left: 0;
    margin-right: 0;
  }

  .page-hero-panel p {
    color: var(--text-muted);
  }

  .auth-copy-panel {
    max-width: 34rem;
    padding: 1.5rem;
    border-radius: 20px;
    background: rgba(8,12,23,0.68);
    border: 1px solid rgba(255,255,255,0.18);
    box-shadow: 0 24px 70px rgba(0,0,0,0.24);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
  }

  @media (max-width: 640px) {
    .page-hero {
      margin-bottom: 2rem;
    }
    .page-hero-panel {
      padding: 1.1rem 1rem;
      border-radius: 14px;
    }
  }

  /* Form refinements — crystal edge focus */
  input[type="text"], input[type="email"], input[type="tel"], input[type="date"], input[type="number"],
  textarea, select {
    background: #fff;
    border: 1px solid var(--border);
    color: var(--text);
    transition: border-color .2s, box-shadow .2s;
  }
  input:focus, textarea:focus, select:focus {
    border-color: var(--gold);
    box-shadow: 0 0 0 3px rgba(163,124,58,0.08);
    outline: none;
  }
  .form-label {
    font-size: 10px;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--text-faint);
  }

  /* Buttons */
  .btn-primary {
    background: var(--lapis);
    color: #f7f4ed;
    border: 1px solid var(--lapis-600);
    transition: all .3s cubic-bezier(0.22,1,0.36,1);
  }
  .btn-primary:hover {
    background: #0c1c35;
    border-color: var(--gold);
    transform: translateY(-1px);
  }
  .btn-ghost {
    border: 1px solid var(--stone);
    color: var(--text);
    background: transparent;
  }
  .btn-ghost:hover {
    border-color: var(--gold);
    color: var(--gold);
  }

  /* Room / editorial cards */
  .room-card {
    overflow: hidden;
    transition: box-shadow .45s ease;
  }
  .room-card img {
    transition: transform 750ms cubic-bezier(0.22,1,0.36,1);
  }
  .room-card:hover img {
    transform: scale(1.045) rotate(0.4deg);
  }
  .room-card .info {
    border-left: 3px solid var(--stone);
    transition: border-color .3s;
  }
  .room-card:hover .info { border-left-color: var(--gold); }

  /* Gallery editorial */
  .gallery-item {
    position: relative;
    overflow: hidden;
  }
  .gallery-item img {
    transition: transform 900ms cubic-bezier(0.22,1,0.36,1);
  }
  .gallery-item:hover img { transform: scale(1.08); }
  .gallery-item .overlay {
    background: linear-gradient(to top, rgba(15,23,36,0.55), transparent 55%);
    transition: opacity .3s;
  }

  /* Scrollbar in new palette */
  ::-webkit-scrollbar { width: 5px; height: 5px; }
  ::-webkit-scrollbar-track { background: var(--bg-alt); }
  ::-webkit-scrollbar-thumb { background: var(--lapis-300); border-radius: 99px; }
  ::-webkit-scrollbar-thumb:hover { background: var(--gold); }

  /* Subtle grain on main surfaces */
  .grain {
    background-image: var(--noise);
  }

  /* Decorative hairline dividers */
  .hairline {
    height: 1px;
    background: linear-gradient(90deg, transparent, var(--stone), transparent);
    opacity: .6;
  }

  /* Price in display for emphasis */
  .price {
    font-family: 'Cinzel', serif;
    letter-spacing: -.02em;
  }

  /* === Legacy support during phased rollout (old pages still using .glass / .fade-up) === */
  .glass { background: var(--glass); border-color: var(--stone); }
  .fade-up { opacity:0; transform:translateY(18px); transition:opacity .6s ease, transform .6s ease; }
  .fade-up.visible { opacity:1; transform:none; }
  .hero-gradient { background: linear-gradient(to bottom, rgba(247,244,237,.25), transparent 45%, var(--bg) 92%); }
  .text-gold-shimmer { color: var(--gold); background: none; -webkit-text-fill-color: currentColor; }
</style>

<?php /* Lucide icons will be created by pages after DOM or via their scripts */ ?>
