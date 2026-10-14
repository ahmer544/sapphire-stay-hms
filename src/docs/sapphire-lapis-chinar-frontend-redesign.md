# Frontend Redesign: Lapis & Chinar — A Crystalline Portal for Sapphire Stay

**Author:** Grok (systems architect, delegated via frontend-design skill)  
**Date:** 2026-06-03  
**Status:** Draft (Revised - Round 1)  

---

## Overview

Sapphire Stay is a boutique luxury hotel in Muzaffarabad, Azad Jammu and Kashmir, nestled in the Himalayan foothills. Its guest-facing frontend (index, rooms listing, room detail + booking, gallery, contact, auth flows, guest bookings management, payment checkout, success states) currently uses a dark glassmorphism aesthetic with Cormorant Garamond + Jost, sapphire blue (#3350f5) + gold (#d4a843) accents, full-bleed heroes, fade-up reveals, and Tailwind CDN + Alpine + Lucide.

This document proposes a complete, production-grade guest-facing frontend redesign that is **strikingly site-specific and unforgettable**. The chosen direction — **"Lapis & Chinar"** — draws directly from the property name (sapphire crystal = depth, clarity, rare blue stone) and the local ecology/culture (Chinar (Platanus orientalis) leaf motifs drawn from the regional ecology and Kashmiri context — visible in the property setting and photos such as balcony.jpeg and turf.jpeg; reviews in sapphire_stay.sql reference Neelum Valley). The current logo (nav.php:109-117, index.php:144-150) is text-based (gradient circle + "Sapphire Stay" + "MUZAFFARABAD" tag); the redesign introduces the Chinar leaf as a new signature motif. Kashmiri mountain mist, walnut, stone inlay, and dramatic yet serene foothills views are visible in balcony.jpeg, turf.jpeg, hero imagery, and reviews.

All existing PHP logic, DB queries (e.g. featured rooms in index.php:357, availability CASE in rooms.php:52-71, conflicting booking checks in room-detail.php:43-66), form POST targets (booking/create.php, self POSTs for contact/extend), hidden fields, redirects (to booking-success.php, payment/checkout.php), session/auth (requireCustomer etc in helpers.php), includes, and image paths (`imgs/` and `imgs/gallery/`) remain **exactly unchanged or minimally adjusted only for markup compatibility**. The redesign is visual, structural, and interaction-layer only.

The result is a luminous, artisanal, editorial experience that feels like a private stone-bound travel journal rather than a generic hotel website. It uses distinctive Google Fonts (Cinzel + Spectral), a cohesive light-dominant stone-and-crystal palette with sharp aged-gold accents, rich CSS texture/gradient/mist treatments, staggered CSS-driven motion, asymmetric and overlapping spatial compositions, and recurring Chinar-leaf + lapis-vein micro-details. Every page is fully responsive, accessible (WCAG-aware focus, ARIA, keyboard), and performant using existing assets.

---

## Background & Motivation

**Current state (explored via full reads of key files):**
- `index.php`: Hardcoded nav (not using partials/nav.php), hero with `imgs/hero.jpeg` (currently logo-like image) + overlay, quick booking bar (GET to rooms.php with JS date guards), featured rooms grid from DB (`is_featured=1`), amenities 2x4, 3-col WhatsApp video reel, 8-image 2/4-col grid preview with two row-spanning tall items (index.php:496 `row-span-2`; not pure masonry — see gallery.php for CSS columns), testimonials from reviews table + dedupeReviews(), gold-shimmer CTAs.
- `pages/rooms.php`: Complex server-side filters (dates clamp, capacity, category from room_categories, bed_type, max_price range, sort), dynamic `display_status` via bookingBlockingCondition + pending/confirmed, 2-col grid of cards with status badges, per-night prices (dates used server-side for display_status + passed to room-detail for calc).
- `pages/room-detail.php`: Image gallery + Alpine lightbox, amenities join, reviews, complex bookedRanges + nextAvailableDate logic, sticky booking widget POSTing exact fields to `../booking/create.php` (room_id, check_in, check_out, guests, special_requests), price JS, login/ backoffice guards.
- `pages/gallery.php`: DB-driven masonry (CSS columns) + Alpine filter tabs + lightbox + admin upload/delete modals (action=upload etc), video glob from imgs/*.mp4.
- `pages/contact.php`: Info cards + self-POST to contact_messages, map iframe (inverted).
- `pages/bookings.php`: requireCustomer(), lists with payment_status join, extend/cancel/payment links to booking/* and payment/*.
- `auth/login.php`, `auth/register.php`: Split-screen image + form (room6.jpeg / balcony.jpeg), demo creds, no nav include.
- `partials/nav.php`: Full theme toggle (data-theme light/dark with massive CSS var overrides + !important hacks), mobile burger, message count, location "MUZAFFARABAD".
- `partials/footer.php`: 4-col with dynamic hotel*() helpers, social, payments badges.
- `config/helpers.php`: All the booking lifecycle, currencyRaw, e(), adminUrl, etc.
- `payment/checkout.php`: Stripe Elements + summary card (POSTs to create-intent/confirm), extension support.
- Duplication: Every page duplicates Tailwind config, font <link>, base <style> for .glass / .text-gold-shimmer / body dark.
- Visual pain: Generic dark luxury (glass + gold shimmer + tracking-widest labels), predictable grids, no strong local character despite photos showing modern cream/marble rooms + red-roof building + snow-capped peaks + glass balcony + turf.
- Business: Booking funnel must be flawless (date validation, availability, Stripe + fallback, extensions). Current works but does not *feel* like a rare mountain sapphire escape.

**Why now:** The property name and location demand something memorable and ownable. Current UI could be any 5-star site in Dubai or Bali. A bold, context-true redesign differentiates in a competitive regional luxury market, increases conversion through delight and trust, and aligns with "production-grade" frontend-design principles (intentional typography/color/motion/spatial/backgrounds, no AI slop).

---

## Goals & Non-Goals

**Goals:**
- Deliver one cohesive, BOLD, production-grade aesthetic ("Lapis & Chinar") that is instantly memorable and true to Sapphire + Muzaffarabad Himalayan/Kashmiri context.
- Preserve 100% of existing functionality and contracts (form fields/names/actions, PHP includes, queries, redirects, auth, payments, availability logic).
- Exceptional typography (distinctive Google pair), color (dominant + sharp accents via CSS vars), motion (orchestrated staggered reveals + surprising hovers), spatial (asymmetry, overlap, diagonal), backgrounds (grain, mist gradients, vein patterns, motifs).
- Reusable component-like partials to reduce future duplication while keeping changes incremental.
- Fully responsive (mobile-first), accessible (focus visible in gold, ARIA labels, semantic, no custom-cursor that breaks), performant (existing images, minimal new JS).
- Beautiful error/success/loading states throughout funnel.
- Cohesive light refresh for admin where it touches guest perception (sidebar + dashboard header only; see PR10 for exact bound).

**Non-Goals:**
- Change backend, DB schema, Stripe flows, booking lifecycle, file uploads, admin business logic.
- Introduce build step, new frameworks, or remove CDN Tailwind/Alpine/Lucide.
- Full dark mode parity (commit to luminous primary light aesthetic; dark as secondary "twilight" sections or future).
- New images, content rewrites, or performance optimizations beyond frontend.
- Rebuild 404 or every admin page in depth (focus guest + *light cohesion only*: specifically admin/partials/sidebar.php (vars/fonts) + admin/dashboard.php (head/header); no layout, motifs, forms, queries, or other admin pages). 404.php remains redirect-only (out of scope for visual). pages/messages.php lightly touched in PR8 for consistency only (includes nav, guest auth).
- Generic luxury tropes (no purple gradients, no Inter/Cormorant reuse, no centered everything, no flat cards).

---

## Proposed Design

### Conceptual Direction & Differentiation
**"Lapis & Chinar" (Crystalline Heritage / Veiled Mountain Crystal)**

The interface is a polished lapis lazuli artifact set against warm Himalayan limestone, with recurring Chinar (Platanus orientalis) leaf motifs introduced as a signature element drawn from the regional ecology and Kashmiri context (property photos show mountain/foothills setting; reviews reference Neelum Valley). The current logo is text/gradient only (no leaf). It evokes:
- Clarity and depth of the sapphire crystal.
- Mist and stone of the foothills (dramatic peaks in balcony.jpeg, turf.jpeg, reviews).
- Artisanal local craft (inlay, brass, wood, shawl geometry) without pastiche.
- Editorial travel journal: large expressive type, generous but purposeful space, "found" details.

**Unforgettable hook:** Visitors will remember the feeling of "turning a stone page" — staggered reveals that part like morning fog, cards that sit like inlaid panels with vein borders, gold Chinar accents that catch light on hover, and typography that looks carved from the same mountains visible from the real balconies. It is site-specific in a way no other hotel site is.

### Typography
- **Display / Headlines:** Cinzel (Google Fonts, weights 400-700). Chiseled, monumental, slightly condensed serifs. Perfect for "SAPPHIRE STAY", room names, section titles. Uppercase sparingly for labels. Feels like a gem facet or temple inscription.
- **Body / Reading:** Spectral (Google Fonts, 300-700 + italics). Refined transitional serif with beautiful oldstyle figures, subtle contrast, excellent for descriptions, reviews, form copy. Warm and literary — pairs with Cinzel for an unexpected, elevated, non-generic hotel voice.
- **UI / Small:** Spectral for consistency; fall back to system-ui for code-like (refs, prices) where needed. Generous tracking on labels (0.08em–0.2em), tighter on prose.

In `<head>` (new shared):
```html
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600;700&family=Spectral:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet">
```
Tailwind extend: `fontFamily: { display: ['Cinzel', 'serif'], body: ['Spectral', 'serif'] }`.

### Color & Theme (CSS Variables — Cohesive)
Primary: luminous warm-stone light theme (`--bg: #f7f4ed`) for "clarity" of sapphire. Deep lapis as dominant structural/text color for gravity. Sharp aged-gold (chinar brass) as the memorable accent pop. Subtle cool mist and moss for nature.

```css
:root {
  --bg: #f7f4ed;           /* warm limestone paper */
  --bg-alt: #f0e9dc;
  --bg-deep: #0f1724;      /* twilight pockets / dark sections */
  --text: #1a1814;
  --text-muted: #5c564b;
  --text-faint: #8a8173;
  --lapis: #132a4f;        /* dominant deep blue-stone */
  --lapis-600: #1e4a7a;
  --lapis-300: #8fa4c4;    /* mist veins */
  --gold: #a37c3a;         /* aged chinar gold — sharp accent */
  --gold-bright: #c9a15e;
  --gold-soft: #d4b37a;
  --mist: #d4d0c3;
  --stone: #c3b8a3;
  --success: #2f4633;
  --error: #6b2f2f;
  --border: rgba(26,24,20,0.12);
  --glass: rgba(255,255,255,0.65);
  --noise: url('data:image/svg+xml;utf8,<svg...>'); /* fine granite grain */
}
```

**Theme commitment (light-first luminous signature):** Primary is the warm-stone light (no full dark parity). Dark/"twilight" only as *pockets* (hero overlays, modals, certain sections) via `.twilight { --bg: var(--bg-deep); --text: #e8e8ec; ... }` utility (applied explicitly in markup on .hero, .modal etc.). 

Update/replace the massive theme system in `partials/nav.php` (lines 5-103: anti-flash, :root + [data-theme=light] with !important hacks, #navbar hardcodes, .theme-btn sun/moon SVGs + toggle JS at 221) and per-page dark hardcodes. In PR1: new light-first :root (see head.php sketch), anti-flash defaults to 'light', *remove the toggle button entirely from redesigned nav* (and mobile). Auth pages (nav-less) inherit via head include for light palette. Old localStorage 'ss_theme' is ignored or cleared on first new load (new default 'light' for v1 luminous). Twilight pockets use the .twilight class (no data-theme attr needed). Footer script updated for new classes if observers affected.

**Dominant + sharp:** Lapis and gold dominate attention; stone/mist fill space evenly but with purpose.

### Motion Strategy (CSS-first, High-Impact)
- **Page load orchestration:** One well-choreographed sequence per page. Elements get `.reveal` class + `style="animation-delay: N0ms"`. CSS:
  ```css
  .reveal { opacity:0; transform:translateY(24px); transition: all 600ms cubic-bezier(0.22,1,0.36,1); }
  .reveal.in { opacity:1; transform:none; }
  ```
  Stagger: nav 0, hero headline 120, sub 240, booking 380, first room card 520, etc. (see index.php script observer update).
- Scroll-triggered: Reuse/extend existing IntersectionObserver for `.fade-up` → rename to `.reveal` + richer (scale + blur for "parting mist").
- Hovers: Image scale + slight rotate + gold vein line draw (via ::after width). Buttons: lift + gold underline sweep. Cards: soft shadow + "lift from stone" (translateY + box-shadow).
- Form focus: Border grows a gold "crystal edge" via scaleX on pseudo.
- Gallery lightbox: slow dissolve + scale.
- Prefer no heavy JS libs beyond existing Alpine. For scroll-linked (optional): tiny vanilla on hero image parallax (transform translate on scroll, throttled).
- Reduced motion: respect `prefers-reduced-motion`.

### Spatial Composition
- **Asymmetry & overlap:** Home hero text block left-weighted, booking widget overlaps hero bottom edge as a "stone plinth" panel (negative margin or absolute with z). Room cards: image 55% height, info offset with left rule. Gallery: CSS grid with 2-3 featured images spanning 2 cols/rows, breaking the rhythm like a mountain ridgeline.
- Diagonal flow: Section dividers use 8-12deg subtle skew or diagonal gold hairlines. CTAs placed off-center.
- Density: Generous margins (px-8/12 on mobile, max-w-7xl with breathing), controlled clusters (info in tight groups of 3-4).
- Grid-breaking: On rooms page, filters sidebar can "float" or pin asymmetrically; featured room can break row with larger treatment.

### Backgrounds & Visual Details
- **Grain / texture:** Subtle SVG noise on `--bg` and cards (`background-image: var(--noise), linear-gradient...`).
- **Mist & atmosphere:** Multi-layer gradients (radial from top for light falloff, linear bottom for grounding). Hero uses existing image + `linear-gradient(180deg, rgba(247,244,237,0.35) 0%, transparent 35%, #f7f4ed 92%)` + soft blur overlay.
- **Veins & motifs:** 
  - Thin double borders or inset shadows for "inlaid stone".
  - Recurring inline SVG Chinar leaf (simple 5-lobe path, gold fill 8-15% opacity) as watermark in corners, dividers, or success icons.
  - Lapis vein lines: `background: linear-gradient(90deg, transparent, var(--lapis-300) 50%, transparent)` on subtle elements.
- Custom desktop cursor: Optional elegant thin cross (gem facet) on .hero and gallery only; default elsewhere. (Accessibility: pointer for interactive.)
- Decorative: Room number badges get small chinar stamp; price always in Cinzel with gold.

#### Concrete Snippets (for production-grade fidelity)
**1. Chinar leaf SVG (reusable symbol + watermark usage):**
```html
<svg style="display:none">
  <symbol id="chinar-leaf" viewBox="0 0 24 24" fill="currentColor">
    <!-- simple 5-lobe Chinar/Platanus inspired -->
    <path d="M12 2 Q8 6 6 12 Q10 14 12 20 Q14 14 18 12 Q16 6 12 2 Z M12 4 Q9 8 8 13 Q11 14 12 18 Q13 14 16 13 Q15 8 12 4"/>
  </symbol>
</svg>
<!-- usage as watermark -->
<div class="absolute top-2 right-2 opacity-10 text-[color:var(--gold)]">
  <svg width="32" height="32"><use href="#chinar-leaf"/></svg>
</div>
```

**2. .reveal + stagger (for index hero + cards; update existing observer):**
```html
<h1 class="reveal font-display ..." style="animation-delay:120ms">Where the mountains...</h1>
<!-- in JS (port index:569 / footer:98) -->
document.querySelectorAll('.reveal').forEach((el,i) => { 
  if (el.style.animationDelay) {} else el.style.animationDelay = (i*80)+'ms';
  observer.observe(el); 
});
/* CSS already in head: .reveal {..} .reveal.in {..} */
@media (prefers-reduced-motion) { .reveal { transition: none !important; transform: none !important; } }
```

**3. Booking plinth overlap (home hero bottom):**
```html
<!-- hero section ends with image/gradient -->
<div class="relative z-20 -mt-12 max-w-5xl mx-auto px-6">
  <div class="lapis-card rounded-2xl p-6 shadow-2xl border border-[color:var(--stone)]">
    <!-- 5-field booking form here, asymmetric layout e.g. dates left heavy -->
  </div>
</div>
<!-- or absolute variant: <div class="absolute bottom-0 left-0 right-0 ..."> -->
```

**4. Nav link gold vein (update ::after):**
```css
.nav-link { position:relative; }
.nav-link::after {
  content:''; position:absolute; bottom:-2px; left:0;
  width:0; height:2px; background: linear-gradient(to right, var(--gold), var(--gold-bright));
  transition: width .3s cubic-bezier(0.22,1,0.36,1);
}
.nav-link:hover::after { width:100%; }
```

These ensure the "unforgettable" quality is executable without guesswork. Current code has similar ::after in nav.php:82. 

### Major Page / Section Treatments (Specific)

**Nav (partials/nav.php + index inline):**
- Logo: Cinzel "SAPPHIRE" + small inline Chinar leaf SVG + "STAY" in gold. Location line "MUZAFFARABAD · HIMALAYAN FOOTHILLS" in faint Spectral.
- Links: tracking-widest uppercase, gold vein ::after on hover (animated width + color to --gold).
- Actions: "BOOK YOUR ESCAPE" primary in lapis with gold edge; SIGN IN pill with border.
- Mobile: elegant full-height drawer with leaf motif.
- **Theme toggle removed** in redesigned nav (PR1): luminous light is the v1 signature (see Color & Theme + head sketch). No sun/moon buttons. Twilight pockets via explicit .twilight class on elements (hero, certain cards/modals). Update anti-flash + delete toggle JS. (If future twilight mode needed, a crystal icon toggle can be added post-launch.) Auth pages (split, no nav) get light styles via head.

**Footer (partials/footer.php):**
- 4-col becomes 3 + wide brand. Brand block with large Cinzel + Chinar SVG.
- Links with left gold rule on hover.
- Contact block keeps dynamic helpers.
- Bottom: "Crafted in the shadow of the mountains" + secure note with tiny vein line.

**Home (index.php):**
- Hero: Full-bleed (existing or better mountain view), heavy left text block in Cinzel (massive 7xl "Where the mountains hold the sky"), subhead in Spectral. Overlapping bottom "booking plinth" — a wide asymmetric stone-textured panel containing the 5-field form (dates, guests, category, CTA). Form fields: floating labels or left icons, bottom gold rules.
- Featured rooms: 3-col becomes broken 1+2 or masonry with one tall "Penthouse" featured. Cards: image with subtle diagonal clip or bottom stone plinth, category tag as chinar-stamped, price large Cinzel bottom-right, hover: mist veil + leaf appears.
- Amenities: 4-col grid of "stone tablets" — icon in deep lapis circle, title Cinzel, desc faint. Hover lifts tablet.
- Video reel: 3 videos in elegant framed "balcony windows" with slight overlap, play on view or always muted loop.
- Gallery preview: Editorial 4-col with 2 large featured (banner + turf) spanning, captions in elegant serif overlay on hover.
- Testimonials: Large pull-quotes, 3-col but center one offset, stars in gold.
- CTA: Full-bleed misty banner with diagonal text "The rarest stays are the ones you remember."

**Rooms Listing (pages/rooms.php):**
- Header: Large Cinzel title + count + date range as elegant badge.
- Filters: Left sidebar becomes beautiful "journal panel" — stone texture, Cinzel "Refine Your Escape", custom range slider with gold track, segmented buttons for category/bed (pills with vein), date inputs with icons. Sticky on desktop.
- Grid: 2-col (xl 3? but keep intimate), sculptural cards: large image top, left gold vertical rule, name + rating in Cinzel, meta row, dynamic nights/total calc in Spectral, status as small lapis tag. "VIEW & RESERVE" button in gold that fills on hover.
- Empty: Poetic "No rooms emerge from the mist for those dates."

**Room Detail + Booking (pages/room-detail.php):**
- Hero image full-bleed with floating room meta (category, number, status) in elegant pill cluster.
- Thumbs: Horizontal scroll with gold ring on active. (Preserve body `x-data="{ lightbox: false, lbSrc: '' }"` + Alpine directives on thumbs/lightbox div exactly.)
- Content: 2/3 + 1/3. Left: name huge Cinzel, meta icons (users/bed/floor) in row, description. Amenities as "inlaid" 2-col list with check in gold. Reviews as stacked cards with guest name + date.
- Booking widget (sticky): "Crystal Reserve" panel — price hero in Cinzel + gold, status badge, conflict list if any (as timeline), form with refined inputs (icons left, Spectral labels), live price receipt with subtle lines, CTA "RESERVE THIS CRYSTAL" or "HELD FOR PAYMENT". Keep exact POST.
- Related rooms: Small horizontal scroller at bottom.

**Gallery (pages/gallery.php):**
- Header editorial.
- Filters: Horizontal scrollable "embroidered" tags (all, rooms, exterior...) with active underline vein.
- Grid: Advanced CSS grid + masonry fallback. Large featured images (2x span) break the grid like a ridgeline. Each: image + subtle bottom gradient caption. Hover: slow zoom + Chinar watermark fades in + gold frame.
- Lightbox: Full cinematic (dark lapis veil, large image, title below in Spectral, close leaf button).
- Admin bar: Restyled with same system (gold accents).
- Videos: Framed as "field recordings" in 3-col.

**Contact (pages/contact.php):**
- Split: Left info "tablets" stacked asymmetrically (address larger), map in a stone frame (perhaps remove invert or enhance).
- Right: Large form "Write to the foothills" — generous padding, inputs with left gold line accents, subject as nice select. Success state: full panel with leaf + message "Your words have reached us."

**Auth (auth/login.php, register.php):**
- Split screen preserved (these pages intentionally remain nav-less; see pages/messages.php for comparison which does include nav). New overlays, mist/leaf watermarks on images (balcony.jpeg / room6.jpeg), stone-card form panels, Cinzel titles, icon inputs with Spectral, primary button "ENTER THE MOUNTAINS". Light palette via head include.
- Demo creds moved to elegant footnote.

**Guest Bookings (pages/bookings.php, booking-success.php, booking/extend.php):**
- List: Horizontal room image + details in clean rows, status as colored stone badges (confirmed = moss green, pending = gold). Actions as gold text links or pills.
- Success: Centered "journal entry" card with pop animation, large ref in Cinzel, leaf check.
- Extend: Same as detail widget, clean date picker + live calc.

**Payment Checkout (payment/checkout.php + related):**
- Refined summary card left (or top on mobile).
- Right: "Complete the holding" heading, Stripe #card-element in elegant stone-bordered box (restyle existing), trust row with "Secure crystal transaction", manual proof note if re-enabled.
- Beautiful states for processing / success redirect (keep redirect).

**Other (messages.php, booking-success, etc.):** Consistent typography, cards, buttons, flash banners in new palette.

### Code Structure & Reusability
- **New minimal files (PR 1):**
  - `partials/head.php`: Shared meta, font links, Tailwind script + config (new palette), base <style> block with all :root + .reveal + .lapis-card + .chinar-leaf + grain/mist/vein CSS, noise data:URL svg. (See full sketch below; keeps pages self-contained.)
  - `partials/nav.php` + `partials/footer.php`: Full redesign (already partials, update in place; PR1 lands with zero visual change to bodies).
  - `partials/room-card.php`: Reusable sculptural card. Accepts `$room` (array shape from queries: id, name, thumbnail, description, price_per_night, capacity, bed_type, size_sqft, floor, category_name, is_featured, avg_rating?, display_status? from rooms.php query), optional `$checkIn`/`$checkOut`/`$guests` (for nights/total calc in rooms context), `$basePath` ('' for root, '../' for pages/), `$context` ('index' | 'rooms' | 'bookings' to toggle badges/status/calc). Outputs new markup; caller handles onclick/paths. Used in index (no status, direct thumbnail), rooms (../ prefix, display_status badges, is_featured star, avg_rating, conditional nights/total), bookings (horizontal list row variant).
  - `partials/booking-form.php`: Core date/guests/special fields + price receipt skeleton (stable IDs). *Complex logic and <script> stay in consuming page* (e.g. room-detail.php's 450+ lines updatePrice/guards; index quick-bar is separate GET form). Partial emits form with IDs; page attaches handlers. Specialized by context or params (POST vs GET, hidden room_id vs none).
  - `partials/icons.php` (optional) or inline `<svg>` defs for Chinar leaf (one reusable symbol).
- Existing per-page `<head>` replaced with `<?php include __DIR__ . (strpos(__DIR__,'pages')!==false || strpos(__DIR__,'auth')!==false || strpos(__DIR__,'booking')!==false || strpos(__DIR__,'payment')!==false ? '/../' : '') . 'partials/head.php'; ?>` + page-specific `<style>` overrides (e.g. gallery masonry, room-detail input style=). Subdir pages use `../partials/...` and `../imgs/`; root pages (index, auth) use direct. DB thumbnails are relative 'imgs/xxx' so cards use $basePath prefix.
- Index.php: Refactor to `<?php include 'partials/nav.php'; ?>` (standardize; currently duplicates nav HTML).
- Keep all inline `<script>` for date guards/price/observers (update selectors/IDs/classes if markup changes; preserve logic exactly). See "Preserved Interactive Contracts" below.
- No new DB or backend changes. Tailwind for layout/spacing; custom CSS (vars, textures, motion, motifs) in head partial + per-page overrides.
- **PR1 rule:** Introduce head/nav/footer + base CSS *only*; do not change any page bodies or visual markup yet. Subsequent PRs fully replace page content (new markup only; no old body kept in same file for transition).

This keeps changes reviewable and incremental. Extraction reduces the 20+ duplicated heads (verified across index.php:28-131, pages/rooms.php:92+, room-detail.php:97+, gallery.php:135-181 incl. masonry/admin styles, auth/*.php, payment/checkout.php, bookings.php, extend.php, contact.php, footer.php observers) while page-specific CSS (e.g. gallery .masonry, .drop-zone, admin-bar; room-detail input style= and Alpine) lives in consuming page or small overrides.

#### Preserved Interactive Contracts
New markup **must** emit or preserve these DOM IDs/attrs/classes so existing (and lightly ported) inline scripts + Alpine continue to work without breakage. Every relevant PR must include "Port + manual test of all inline <script> (date guards, price calc, lightbox, form submit, filter tabs, mobile nav, observers) after markup change."

**Date / Booking bar guards (index quick form GET to rooms.php):**
- `id="hero_ci"`, `id="hero_co"` (inputs; min= today logic, change handlers, submit guard at index.php:304-341 creating #hero_date_err)
- Form submit validation.

**Rooms filters (pages/rooms.php GET):**
- `id="rooms_ci"`, `id="rooms_co"` (inputs + handlers at rooms.php:223-257 for min/next + #rooms_date_err)

**Room detail booking widget (POST to ../booking/create.php):**
- Form: `id="booking-form"`
- Dates: `id="bk_ci"`, `id="bk_co"`
- Price live: `id="price-summary"`, `id="nights-count"`, `id="subtotal"`, `id="grand-total"`
- Script at room-detail.php:450-482 (updatePrice(), ci/co listeners, submit guard; relies on pricePerNight const, exact element IDs)
- Hidden: `name="room_id"`, `name="check_in"`, `name="check_out"`, `name="guests"`, `name="special_requests"`

**Alpine / Lightbox:**
- room-detail: `<body x-data="{ lightbox: false, lbSrc: '' }">` + thumbs `@click="lightbox=true;lbSrc=..."` + `<div x-show="lightbox" ...>` (must not break x-data or directives)
- Gallery: `<body x-data="galleryApp()" x-init="init()">` + `x-for="img in filtered"`, `:key`, `@click` for lightbox/filter, `x-show` for modals/tabs, `x-model` for selects, admin drag/drop handlers. (gallery.php:183+ full Alpine app)

**Observers / Global (footer.php:98-104, index.php:569-574, contact.php:210-212):**
- `.fade-up` (transition to `.reveal` class + `.in`; observer code ported/updated; style="transition-delay: Xs" on some)
- Date input min=today auto (in footer script)

**Other:**
- Nav: `#navbar`, `#nav-mobile-toggle`, `#nav-mobile-menu` (mobile burger/scroll sync at nav.php:228+)
- Gallery masonry: `.masonry`, `.masonry-item` (CSS columns + .masonry-item)
- Misc inline styles on inputs (room-detail:348 etc) may be replaced by vars but focus states preserved.
- No change to any form `action`, `method`, input `name`s, or PHP POST handling.

**Usage examples in partials:**
```php
<!-- room-card.php usage (e.g. in rooms.php) -->
<?php include __DIR__ . '/../partials/room-card.php'; ?>
<!-- with vars: -->
<?php
$roomCard = $room; // from query
$roomCard['base_path'] = '../';
include __DIR__ . '/../partials/room-card.php'; // inside partial: uses $room + $base_path + $context='rooms'
?>
```

**head.php concrete outline (PR1):**
```html
<!-- partials/head.php -->
<meta ...>
<title>...</title>
<script src="https://cdn.tailwindcss.com"></script>
<script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600;700&family=Spectral:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet">
<script src="https://unpkg.com/lucide@latest"></script>
<script>
  tailwind.config = { theme: { extend: {
    colors: { lapis: {500:'#132a4f', ...}, gold:{...} /* new dominant/sharp */ },
    fontFamily: { display: ['"Cinzel"', 'serif'], body: ['"Spectral"', 'serif'] }
  }}}
</script>
<style>
  :root { /* full new vars from doc: --bg, --lapis, --gold, --noise etc */ }
  body { font-family: 'Spectral', serif; background: var(--bg); color: var(--text); }
  .font-display { font-family: 'Cinzel', serif; }
  .reveal { opacity:0; transform:translateY(24px); transition:all 600ms cubic-bezier(0.22,1,0.36,1); }
  .reveal.in { opacity:1; transform:none; }
  .lapis-card { /* stone texture, veins, shadows */ background: var(--bg-alt); border:1px solid var(--border); }
  .chinar-leaf { /* fill:var(--gold); */ }
  /* .glass updated or replaced by new .lapis-glass using vars + backdrop */
  /* noise: background-image: var(--noise), ... ; */
  /* vein ::after, mist gradients, reduced-motion @media etc. */
  /* keep some .glass for transition if needed, but migrate */
</style>
<!-- page bodies add <style> overrides after include -->
```

#### Theme Migration in PR1
Replace entire theme <style> + anti-flash + toggle JS in nav.php (and per-page dark hardcodes) with light-first :root above. Anti-flash: `localStorage.getItem('ss_theme')||'light'`. Remove sun/moon toggle button from redesigned nav (luminous is v1 signature); twilight pockets via `.twilight { --bg: var(--bg-deep); color: ... }` on .hero, modals, etc. Auth pages (nav-less) include head for light styles. Old 'ss_theme' localStorage: new default light; optionally clear on first new load or ignore. Footer observer/date script updated if needed.

This addresses extraction risks for real working code.

---

## API / Interface Changes
**None to backend contracts.**

- Form actions/fields identical (e.g. room-detail.php:311 `action="../booking/create.php"`, inputs `room_id, check_in, check_out, guests, special_requests`).
- GET filters on rooms.php unchanged.
- Links, redirects, session keys same.
- Frontend-only: New class names (`.lapis-card`, `.chinar-accent`, `.reveal`), data attrs for Alpine where kept, new element IDs only for new JS (scoped). **All existing interactive DOM contracts preserved** (see "Preserved Interactive Contracts" in Code Structure: hero_ci/hero_co, rooms_ci/rooms_co, bk_ci/bk_co, booking-form, price-summary/*, Alpine x-data on body for room-detail+gallery, .fade-up->.reveal, nav mobile ids, etc.). New markup must emit them or update attached scripts.
- Nav/footer public API (links) preserved.

Before/after example (booking CTA):
```html
<!-- before -->
<button class="bg-gradient-to-r from-gold-600...">CONFIRM BOOKING</button>

<!-- after (new) -->
<button class="btn-crystal">Reserve this facet of the mountains</button>
```

---

## Data Model Changes
None. Pure frontend. (No migrations, no schema.)

---

## Alternatives Considered

**1. Refined Dark Brutalism ("Granite Night")**
- Keep/extend current dark (#09090f), heavy use of raw stone textures, brutalist asymmetry, Cinzel uppercase everywhere, minimal gold.
- Trade-offs: More continuity with existing (less migration risk for team), dramatic for "night in the mountains". Loses "clarity" of sapphire name; feels generic luxury dark after many sites; less differentiation from current; harder to read long reviews/forms for international guests. Loses the luminous, journal-like hospitality warmth that suits the region's bright days + hospitality.

**2. Soft Pastel Organic ("Misty Pashmina")**
- Very light, blush + sage + sky, heavy illustration/leaf patterns, rounded everything, soft sans + light serif.
- Trade-offs: Highly memorable and "soft mountain escape". But mismatches the real photos (modern marble rooms, strong architecture, dramatic peaks) and "sapphire" precious-stone strength. Too sweet for a premium boutique positioning; risks feeling generic wellness rather than rare crystal luxury. Lower contrast on text.

**3. (Chosen) Lapis & Chinar** wins on intentionality, local truth, contrast with current, production polish, and "one thing remembered" (the carved stone journal portal).

---

## Security & Privacy Considerations
- No new data collection or endpoints. All forms, auth, payments identical to today (server validation in create.php, helpers.php roomHasBlockingBooking, Stripe in payment/*, contact_messages insert).
- Client-side: Enhanced visual validation + date guards (keep existing JS + improve UX messages with new toast style in palette). Never trust client.
- XSS: All output already uses `e()` (helpers.php:544); new markup must continue (room names, descriptions, titles).
- Auth: requireLogin etc unchanged; public pages remain public.
- Images: Existing /imgs/ served same; no new uploads on guest redesign (admin gallery stays).
- Threat: Styling changes do not introduce new attack surface. Recommend adding CSP headers at server if not present (future).
- Accessibility as security: High contrast in new light palette (test lapis on stone), focus states, no motion that triggers vestibular issues (reduced-motion support).

---

## Observability
- Frontend: Minimal. Add lightweight custom event on key actions (booking form submit, filter apply, gallery open) via `window.dispatchEvent` or simple `data-track` + tiny inline listener that `console.log` or (if present) pushes to existing analytics. Example in head partial:
  ```js
  function track(event, data={}) { /* console or gtag */ }
  ```
- Visual states: Beautiful error banners (red stone), success (moss + leaf), loading (subtle shimmer on CTAs or skeleton cards with grain).
- Server errors: Existing flashes (getFlash) restyled.
- No new metrics/alerts needed for pure redesign; existing booking success rate observable via backend.
- Performance: Existing images; suggest `loading="lazy"` + `decoding="async"` on non-hero; measure LCP via browser.

---

## Rollout Plan
Pure static PHP files with no central router or build → no easy runtime feature flag for full page bodies. Foundations-first + batching is required to avoid mixed-state UX.

**Foundations PR1 lands new partials + updated nav/footer + head (zero visual change on *any* page bodies).**

Subsequent PRs (2+) **fully replace** the page content (new markup only; no conditional old/new body kept in the same file). 

**Staging strategy (updated for pure-PHP reality):**
- **Internal/staff preview first:** Role check (`isBackOfficeAccount()`) or `?preview=lapis` cookie (set via a simple preview.php or query param handler in head) that forces include of new head + redesigned body partials *if the new partial exists for that page*. Easy to add a 1-line include switch guarded by the preview flag. No rand/10% needed initially.
- **Cohesive batch:** Merge PRs 2-4 (home + rooms + room-detail, the core guest funnel) together or in rapid sequence *before* exposing to guests, to avoid inconsistent aesthetic between pages (home new, rooms still old).
- Complete guest-facing PRs 2-8 in sequence, then one deploy for the bulk guest experience switch (via the preview flag or simple define flip once stable).
- Admin last / never deep (see PR10 bound).
- "Duplicate pages (rooms-v2.php)" only for *emergency* hotfix rollback of a single page, not primary strategy.

**Rollback:** Git revert of the specific page PR(s) or full tag revert. Old markup lives in git history (not comments in prod files). Flip preview flag off.

**Risk during incremental:** "During incremental PR merges to main (before final guest batch), pages will have mixed old/new designs until PRs 2-4 (or 2-8) complete — high risk of user confusion + brand inconsistency. Mitigate by: (a) keeping new work on a feature branch until core batch ready; (b) staff-only preview during early PRs; (c) explicit note in each PR: 'This PR changes only X; do not merge to prod guest traffic until batch Y complete.'"

**Testing:** Manual cross-browser (focus on date pickers, Stripe Elements restyle, Alpine lightboxes), mobile real device, accessibility (axe or keyboard tour), booking happy path end-to-end (create → checkout → success) *on each PR + full batch*. Port+test all contracts (see Preserved Interactive Contracts + PR1/PR2 etc. descriptions).

**Post-launch:** Monitor via existing logs + new track() events. Tweak spacing/contrast from real guest feedback.

See PR Plan for updated per-PR rollout notes. This is realistic for file-per-page PHP.

---

## Open Questions
- Final font pair confirmation (Cinzel + Spectral vs Cinzel + Fraunces or Vollkorn) after loading samples on target devices.
- (Resolved in design) Keep/simplify/remove the existing theme toggle in nav.php (current massive var system is brittle for new design)? **Decision: removed entirely in PR1** for luminous signature; twilight pockets via .twilight class only. See Color & Theme, Nav, PR1, Key Decisions.
- How much Chinar SVG motif repetition is "enough" vs too much? (Propose 3-5 tasteful uses per page.)
- Admin depth: Full restyle or just fonts/vars + sidebar cohesion? (Current doc focuses guest.)
- Measure of success: Conversion lift on booking bar? Time-on-gallery? Qualitative "feels like the mountains" feedback.
- Image optimization: Some assets (hero.jpeg is logo-ish) may benefit from better hero photography in future, but out of scope.

---

## Risks
Prompt requires "Call out risks explicitly with severity and mitigation". Consolidated here (some promoted from Open Questions / Rollout notes). Each PR must address relevant risks in testing.

- **Risk: Mixed old/new page designs during incremental PR merges (home new, rooms still old glassmorphism).** Severity: high. Mitigation: PR1 zero-visual foundations only; batch PRs 2-4 (home+rooms+detail) before exposing to guest traffic; use staff-only ?preview=lapis or role preview during early merges; explicit "do not merge for prod guests until batch complete" in PRs. See revised Rollout Plan. (Affects brand consistency.)
- **Risk: New light palette contrast / readability on real devices (e.g. --lapis #132a4f on --bg #f7f4ed stone; gold accents).** Severity: medium. Mitigation: explicit contrast audit + manual test in PR10 + per-page PRs; use tools (axe, browser contrast check); tweak vars if needed before launch. Reduced-motion support required.
- **Risk: Google Fonts CDN load (Cinzel + Spectral) latency / FOUT on slow mobile (Kashmir region).** Severity: low. Mitigation: preconnect in head.php sketch; font-display:swap; self-host fallback possible later. Test on throttled network.
- **Risk: Removing theme toggle breaks existing user localStorage 'ss_theme' prefs (and FOUC on first loads).** Severity: low. Mitigation: new anti-flash defaults 'light' (||'light'); optionally clear old key on first new page load; luminous is intentional signature (no full dark parity). Twilight pockets scoped.
- **Risk: Over-use of Chinar motif or vein details feels busy (or under-use loses character).** Severity: low. Mitigation: bound to 3-5 tasteful uses per page (watermarks, dividers, stamps); review in PR10 "Chinar motif count review".
- **Risk: Partial extraction (room-card, booking-form) changes DOM enough to break ported inline scripts or Alpine (x-data, IDs).** Severity: high (but mitigated by design). Mitigation: "Preserved Interactive Contracts" table + "emit stable IDs; scripts in consuming page" rule; every PR tests the specific contracts (hero_*, bk_*, price-*, Alpine bodies, observers).
- **Risk: Auth pages (login/register) redesign + nav-less split may have inconsistent header treatment.** Severity: low. Mitigation: "Auth pages remain nav-less split screens with new overlays/motifs + light palette applied to form panels only"; they include head.php for fonts/vars. (See Auth treatment and Non-Goals for details.)
- **Risk: Performance regression from new CSS (gradients, pseudo, grain data:URL, more elements).** Severity: low. Mitigation: existing images + lazy; Tailwind CDN; measure LCP; no new heavy JS.

Add `track(event, data)` example (already in Observability) + call it from key actions in PRs for post-launch monitoring.

---

## References
- `design_skills.md` (frontend-design principles: bold direction, typography, color, motion, spatial, backgrounds).
- Current implementation: `index.php`, `pages/rooms.php:52` (availability SQL), `pages/room-detail.php:311` (booking POST), `partials/nav.php:105` (theme), `config/helpers.php:544` (e()), `booking/create.php:46` (insert), `sapphire_stay.sql` (rooms, reviews, gallery).
- Location: Muzaffarabad, AJK (Himalayan foothills, Chinar trees/leaf ecology, Neelum Valley references in reviews).
- Visuals: `imgs/balcony.jpeg` (mountain view + modern build + glass balcony + turf + red roof + peaks), `imgs/turf.jpeg`, room interiors (marble/cream), gallery DB entries. Current logo (no Chinar leaf motif) in `partials/nav.php:109-117` and `index.php:144-150`.
- Prior art: High-end heritage properties (Aman-style restraint + local craft sites); editorial magazine layouts (Kinfolk, Travel + Leisure crystal features); stone/jewelry sites with vein textures.
- Stripe + booking flows must stay identical per task.

---

## PR Plan

Realistic, incremental, reviewable strategy (guest-first, 1-2 files per small PR where possible; each builds on previous; can ship partial value early). **Foundations PR1 = zero visual change.** PRs 2-4 (core funnel) batched for cohesive guest switch where possible. See Rollout Plan for mixed-state risk mitigation + preview strategy (role/?preview=lapis cookie). Every PR desc requires: "Port and test all inline <script> after markup change (date guards, price, lightbox, Alpine, observers, mobile nav). Emit/ preserve all Interactive DOM Contracts."

1. **PR title:** "Add Lapis & Chinar design system foundations + shared partials"  
   **Files:** `partials/head.php` (new), update `partials/nav.php`, `partials/footer.php`, `index.php` (switch to include nav). (No change to helpers.php.)  
   **Deps:** None.  
   **Description:** Introduce `partials/head.php` with consolidated base + full new :root vars (light stone dominant), Tailwind extend (Cinzel/Spectral), .reveal/.lapis-card/.chinar-leaf/grain/mist/vein CSS + noise SVG. Replace entire old theme <style>+script+toggle in nav.php (light-first default, remove toggle button). Redesign nav+footer with motifs but zero visual change to page bodies. Update index to include nav. *Do not change any page bodies or visuals yet.* Port observer/date scripts. Add Preserved Contracts tests note. (Addresses extraction details for real working code.)

2. **PR title:** "Redesign home page (index.php) with Lapis & Chinar aesthetic"  
   **Files:** `index.php` (hero, booking plinth, featured rooms via new room-card partial, amenities, video, gallery preview, testimonials, CTA).  
   **Deps:** PR 1.  
   **Description:** Full visual + layout overhaul per spec (asymmetric plinth overlap, staggered reveals, new cards). Use room-card partial (index context). Keep all DB/JS logic *exactly*; port inline date guard script (hero_ci/hero_co) + observer (fade-up -> reveal). Must emit preserved IDs. Add chinar motifs. Test quick booking bar end-to-end.

3. **PR title:** "Redesign rooms listing and filters (pages/rooms.php)"  
   **Files:** `pages/rooms.php`, `partials/room-card.php` (enhance).  
   **Deps:** PR 1-2.  
   **Description:** New journal sidebar filters, sculptural cards (rooms context: status badges, ../ paths, conditional nights/total), status/price treatments, empty state, header. Preserve all filter SQL + date JS (port rooms_ci/rooms_co handlers + observer). Must emit preserved IDs. Test filters + availability display.

4. **PR title:** "Redesign room detail and booking widget (pages/room-detail.php)"  
   **Files:** `pages/room-detail.php`, `partials/booking-form.php` (new).  
   **Deps:** PR 1,3.  
   **Description:** Hero + thumbs + lightbox (preserve body x-data="{ lightbox: false, lbSrc: '' }", @click, x-show), left content, right sticky "Crystal Reserve" widget (exact POST to booking/create.php preserved with hidden names), conflict UI, price live update (emit #bk_ci, #bk_co, #booking-form, #price-summary, #nights-count, #subtotal, #grand-total), related. Beautiful unavailable states. *Booking-form partial emits stable IDs; page's 450-line script (updatePrice, guards using bk_* and price elements) attaches/ported.* Preserve Alpine lightbox. Test full create flow + availability.

5. **PR title:** "Redesign gallery with editorial cinematic layout (pages/gallery.php)"  
   **Files:** `pages/gallery.php`.  
   **Deps:** PR 1.  
   **Description:** New grid with featured spans (2x), filter tags ("embroidered"), hover details + leaf, enhanced lightbox, restyle admin toolbar/modals. Preserve Alpine (body x-data="galleryApp()", x-for, x-show, filters, modals, drag/drop) + upload logic + masonry CSS fallback. Port any inline. Test lightbox + admin flows.

6. **PR title:** "Redesign contact page and forms (pages/contact.php)"  
   **Files:** `pages/contact.php`.  
   **Deps:** PR 1.  
   **Description:** Asymmetric info tablets + map frame, elegant form, success/error states with motifs. Preserve POST.

7. **PR title:** "Redesign auth pages (auth/login.php, auth/register.php)"  
   **Files:** `auth/login.php`, `auth/register.php`.  
   **Deps:** PR 1.  
   **Description:** Split-screen with new overlays/motifs, stone-card forms, icons, CTAs. Preserve logic + demo notes.

8. **PR title:** "Redesign guest bookings, success, extend, and messages (pages/bookings.php, pages/booking-success.php, booking/extend.php, pages/messages.php)"  
   **Files:** Listed + `booking/extend.php`.  
   **Deps:** PR 1,4.  
   **Description:** List rows, status badges, action buttons, success journal card, extend form, messages list (messages.php includes nav + uses guest auth). Preserve requireCustomer + links + any inline.

9. **PR title:** "Redesign payment/checkout and flow states (payment/checkout.php + confirm if needed)"  
   **Files:** `payment/checkout.php`.  
   **Deps:** PR 1,8.  
   **Description:** Refined summary + payment panel, Stripe element restyle, trust, states. Keep all Stripe/JS/PHP exactly.

10. **PR title:** "Polish, accessibility, consistency, light admin refresh, and cross-page QA"  
    **Files:** Remaining guest (e.g. pages/messages.php if visual tweaks), 404 (no visual change), global tweaks. Light admin only: (1) admin/partials/sidebar.php (new fonts + CSS vars for logo/links, no layout/motif/business changes); (2) admin/dashboard.php (include head or minimal font/var + header).  
    **Deps:** All prior.  
    **Description:** Final motion tuning (reduced-motion @media), contrast audit (lapis on stone), Chinar motif count review (3-5/page), remove old .glass/.shimmer duplication across files, full manual booking funnel test (create->checkout->success with preserved IDs/contracts), a11y (focus, axe, keyboard), cross-browser. No deeper admin. Update any missed (e.g. messages.php). Docs if needed.

---

## Key Decisions

- **Aesthetic choice "Lapis & Chinar" over dark continuation or soft organic:** Directly derives from name + real location (Chinar leaf ecology from regional context + photos + reviews; current logo is text-based only), provides strong differentiation and memorability while supporting luminous "clarity" reading experience. The motif is *introduced* in redesign (not "from existing logo"). (See Alternatives.)
- **Typography: Cinzel (display) + Spectral (body) via Google CDN:** Unexpected, characterful, elevated. Cinzel for stone/gem monumentality; Spectral for warm literary body that elevates descriptions/reviews without generic sans. Avoids all current + guideline-forbidden families.
- **Primary light stone + dominant lapis + sharp aged-gold palette (CSS vars):** Achieves "dominant colors with sharp accents" (skill). Light base honors mountain daylight and name clarity; lapis provides depth/weight; gold is the local craft pop. Dark only for pockets/contrast.
- **Staggered orchestrated page-load reveals + CSS motion only (no new libs):** Matches skill emphasis on high-impact one sequence vs scattered micros. Uses existing IntersectionObserver pattern (index.php:569, footer:98) extended.
- **Asymmetric + overlapping + grid-breaking layouts + rich textures (grain, mist, veins, Chinar SVG):** Avoids predictable hotel grids and "AI slop". Creates depth and place-specific atmosphere using only CSS + 1-2 small inline SVGs. Matches "spatial composition" and "backgrounds & visual details".
- **Extract minimal reusable PHP partials (head, room-card, booking-form) while keeping all pages self-contained where possible:** Reduces duplication (current 20+ duplicated heads) for maintainability without introducing build or complex component system. Incremental for PRs.
- **Zero changes to booking/payment/auth contracts + staged file rollout via setting/define:** Hard requirement. Ensures no broken flows; allows safe incremental delivery and instant rollback.
- **Keep Tailwind CDN + Alpine + Lucide + existing images exactly:** Practical constraint; new aesthetic achieved through custom CSS layers and structural HTML changes only.
- **Theme: luminous light-first signature with twilight pockets; toggle removed in PR1 nav redesign:** Current massive data-theme + !important system (nav.php:5-103) is brittle and dark-first. New design commits to light (clarity of "sapphire") per skill (cohesive dominant palette); dark only scoped pockets. Toggle removal simplifies; localStorage migration handled in anti-flash. (Addresses FOUC/prefs risk.)

---

*End of design document. Implementation will produce a distinctive, production-grade, context-perfect guest experience that makes Sapphire Stay unforgettable.*
