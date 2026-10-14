<?php
require_once '../config/database.php';
require_once '../config/helpers.php';

$user     = authUser();
$isAdmin  = ($user['role'] ?? '') === 'admin';
$db       = getDB();

$success = '';
$error   = '';

function deleteGalleryImagesByPaths(PDO $db, array $paths): int {
    $paths = array_values(array_unique(array_filter(array_map('strval', $paths), static function ($path) {
        return trim($path) !== '';
    })));

    if (!$paths) {
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($paths), '?'));
    $db->prepare("DELETE FROM gallery WHERE image_path IN ($placeholders)")->execute($paths);

    foreach ($paths as $path) {
        $file = dirname(__DIR__) . '/' . ltrim($path, '/');
        if (str_contains(basename($file), 'gallery_') && file_exists($file)) {
            unlink($file);
        }
    }

    return count($paths);
}

// ── Admin: Upload new image ───────────────────────────────────────────────────
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload') {
    $title    = trim($_POST['title']    ?? '');
    $category = trim($_POST['category'] ?? 'general');
    $order    = (int)($_POST['sort_order'] ?? 0);
    $allowed  = ['rooms','exterior','amenities','general'];
    if (!in_array($category, $allowed)) $category = 'general';

    if (empty($_FILES['image']['tmp_name'])) {
        $error = 'Please select an image file.';
    } else {
        $ext  = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $ok   = ['jpg','jpeg','png','webp','gif'];
        if (!in_array($ext, $ok)) {
            $error = 'Invalid file type. Allowed: JPG, PNG, WEBP, GIF.';
        } elseif ($_FILES['image']['size'] > 8 * 1024 * 1024) {
            $error = 'File too large. Maximum size is 8 MB.';
        } else {
            $fname    = 'gallery_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $destDir  = dirname(__DIR__) . '/imgs/gallery/';
            $destPath = $destDir . $fname;
            if (!is_dir($destDir) && !mkdir($destDir, 0755, true)) {
                $error = 'Upload failed. Check server write permissions on /imgs/gallery/.';
            } elseif (move_uploaded_file($_FILES['image']['tmp_name'], $destPath)) {
                $db->prepare("INSERT INTO gallery (title, image_path, category, sort_order) VALUES (?,?,?,?)")
                   ->execute([$title ?: null, 'imgs/gallery/' . $fname, $category, $order]);
                $success = 'Image uploaded successfully.';
            } else {
                $error = 'Upload failed. Check server write permissions on /imgs/gallery/.';
            }
        }
    }
}

// ── Admin: Delete image ───────────────────────────────────────────────────────
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        $row = $db->prepare("SELECT image_path FROM gallery WHERE id=?");
        $row->execute([$id]);
        $img = $row->fetch();
        if ($img) {
            deleteGalleryImagesByPaths($db, [$img['image_path']]);
        }
    }
    header('Location: ' . APP_URL . '/pages/gallery.php?deleted=1'); exit;
}

// ── Admin: Bulk delete images ─────────────────────────────────────────────────
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_delete') {
    $ids = array_values(array_unique(array_filter(array_map('intval', $_POST['ids'] ?? []), static function ($id) {
        return $id > 0;
    })));

    if ($ids) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare("SELECT DISTINCT image_path FROM gallery WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        $paths = array_column($stmt->fetchAll(), 'image_path');
        deleteGalleryImagesByPaths($db, $paths);
    }

    header('Location: ' . APP_URL . '/pages/gallery.php?bulk_deleted=1'); exit;
}

if (isset($_GET['deleted'])) $success = 'Image deleted.';
if (isset($_GET['bulk_deleted'])) $success = 'Selected images deleted.';

// ── Load gallery from DB ──────────────────────────────────────────────────────
$rows = $db->query("SELECT * FROM gallery ORDER BY sort_order ASC, id DESC")->fetchAll();
$uniqueRows = [];
$seenPaths = [];

foreach ($rows as $row) {
    $path = trim((string)($row['image_path'] ?? ''));
    if ($path === '' || isset($seenPaths[$path])) {
        continue;
    }

    $seenPaths[$path] = true;
    $uniqueRows[] = $row;
}

$rows = $uniqueRows;

$jsImages = [];
foreach ($rows as $r) {
    $jsImages[] = [
        'id'       => (int)$r['id'],
        'src'      => '../' . $r['image_path'],
        'title'    => $r['title'] ?? '',
        'category' => $r['category'] ?? 'general',
    ];
}
$jsJson = json_encode($jsImages, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG);
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Gallery – Sapphire Stay</title>
  <meta name="description" content="Explore the spaces and details of Sapphire Stay through our curated gallery."/>
  <?php include '../partials/head.php'; ?>
  <style>
    body { font-family: 'Spectral', serif; background: var(--bg); color: var(--text); }
    .gallery-item{overflow:hidden;border-radius:1rem;position:relative;}
    .gallery-item.is-selected{box-shadow:0 0 0 2px var(--gold), 0 10px 30px rgba(0,0,0,0.1);}
    .gallery-item img{transition:transform .5s ease;display:block;width:100%;height:auto;cursor:pointer;}
    .gallery-item:hover img{transform:scale(1.07);}
    .admin-overlay{opacity:0;transition:opacity .2s;pointer-events:none;}
    .gallery-item:hover .admin-overlay{opacity:1;pointer-events:auto;}
    .select-check{
      position:absolute;
      top:10px;
      left:10px;
      z-index:5;
      width:22px;
      height:22px;
      accent-color:var(--gold);
      cursor:pointer;
    }
    .masonry{columns:2;column-gap:12px;} @media(min-width:768px){.masonry{columns:3;}} @media(min-width:1280px){.masonry{columns:4;}}
    .masonry-item{break-inside:avoid;margin-bottom:12px;}
    .modal-bg{background:rgba(0,0,0,0.45);backdrop-filter:blur(6px);}
    .drop-zone{border:2px dashed rgba(163,124,58,0.3);transition:border-color .2s,background .2s;}
    .drop-zone.drag-over{border-color:var(--gold);background:rgba(163,124,58,0.06);}
    .admin-bar{
      position:sticky;
      top:78px;
      margin-top:78px;
      background:rgba(255,255,255,0.96);
      backdrop-filter:blur(20px);
      -webkit-backdrop-filter:blur(20px);
      border-bottom:1px solid var(--stone);
      box-shadow:0 8px 30px rgba(0,0,0,0.05);
    }
    @media(max-width:767px){
      .admin-bar{top:68px;margin-top:68px;}
      .admin-bar button{width:100%;justify-content:center;}
    }
  </style>
</head>
<body x-data="galleryApp()" x-init="init()">
<?php include '../partials/nav.php'; ?>

<?php if ($success): ?>
<div class="fixed top-24 left-1/2 -translate-x-1/2 z-[60] px-6 py-3 rounded-xl text-sm flex items-center gap-3 shadow-xl bg-green-50 border border-green-200 text-green-800"
     x-data="{v:true}" x-show="v" x-init="setTimeout(()=>v=false,3500)">
  <i data-lucide="check-circle" class="w-4 h-4 text-green-600"></i> <?= e($success) ?>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="fixed top-24 left-1/2 -translate-x-1/2 z-[60] px-6 py-3 rounded-xl text-sm flex items-center gap-3 shadow-xl bg-red-50 border border-red-200 text-red-800">
  <i data-lucide="alert-circle" class="w-4 h-4 text-red-600"></i> <?= e($error) ?>
</div>
<?php endif; ?>

<?php if ($isAdmin): ?>
<!-- ═══ Admin Toolbar ═══ -->
<div class="admin-bar z-40 flex items-center justify-between px-6 py-3 gap-4 flex-wrap">
  <div class="flex items-center gap-3">
    <div class="w-7 h-7 rounded-lg flex items-center justify-center bg-[var(--bg-alt)]">
      <i data-lucide="shield" class="w-3.5 h-3.5 text-[var(--gold)]"></i>
    </div>
    <span class="text-xs tracking-widest text-[var(--gold)] uppercase font-semibold">Admin · Gallery Manager</span>
    <span class="text-[var(--text-muted)] text-xs"><?= count($rows) ?> gallery photos</span>
  </div>
  <div class="flex items-center gap-2 flex-wrap">
    <button x-show="filter !== 'video' && filtered.length > 0" x-cloak
            @click="toggleSelectAll(); $nextTick(()=>lucide.createIcons())"
            class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs tracking-widest text-[var(--text)] transition hover:bg-[var(--bg-alt)] border border-[var(--stone)] bg-white">
      <i data-lucide="check-square" class="w-4 h-4 text-[var(--text-muted)]"></i>
      <span x-text="allFilteredSelected ? 'UNSELECT ALL' : 'SELECT ALL'"></span>
    </button>
    <button x-show="selectedIds.length > 0" x-cloak
            @click="clearSelection()"
            class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs tracking-widest text-[var(--text-muted)] transition hover:text-[var(--text)] border border-[var(--stone)] bg-white">
      CLEAR
    </button>
    <button x-show="selectedIds.length > 0" x-cloak
            @click="bulkDeleteModal=true; $nextTick(()=>lucide.createIcons())"
            class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs tracking-widest text-white transition hover:brightness-115 bg-red-600 border border-red-700 shadow-sm">
      <i data-lucide="trash-2" class="w-4 h-4"></i>
      DELETE <span x-text="selectedIds.length"></span>
    </button>
    <button @click="uploadModal=true; $nextTick(()=>lucide.createIcons())"
            class="flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm tracking-widest text-white transition-all hover:brightness-110 btn-primary shadow-sm">
      <i data-lucide="image-plus" class="w-4 h-4"></i> ADD IMAGE
    </button>
  </div>
</div>
<?php endif; ?>

<!-- Header -->
<div class="page-hero <?= $isAdmin ? 'pt-16' : 'pt-28' ?> pb-12 text-center">
  <div class="page-hero-panel">
  <p class="text-[var(--gold)] tracking-[0.4em] text-xs uppercase mb-3 font-semibold">Visual Journey</p>
  <h1 class="font-display text-6xl text-[var(--lapis)]">Our Gallery</h1>
  <p class="text-[var(--text-muted)] mt-4 text-sm">A curated glimpse into the Sapphire Stay experience</p>
  </div>
</div>

<!-- Filter tabs -->
<div class="max-w-4xl mx-auto px-6 mb-10">
  <div class="flex flex-wrap gap-3 justify-center">
    <?php foreach (['all'=>'ALL','rooms'=>'ROOMS','exterior'=>'EXTERIOR','amenities'=>'AMENITIES','general'=>'GENERAL','video'=>'VIDEOS'] as $v=>$l): ?>
    <button @click="filter='<?= $v ?>'"
            :class="filter==='<?= $v ?>' ? 'bg-[var(--gold)] text-white border-[var(--gold)]' : 'border border-[var(--stone)] bg-white text-[var(--text-muted)] hover:text-[var(--text)] hover:border-[var(--gold)]'"
            class="px-5 py-2 rounded-full text-xs tracking-widest border transition-all shadow-sm"><?= $l ?></button>
    <?php endforeach; ?>
  </div>
</div>

<!-- ═══ Photo Masonry Grid ═══ -->
<div class="max-w-7xl mx-auto px-6 pb-16" x-show="filter !== 'video'">
  <div class="masonry" id="masonryGrid">
    <template x-for="img in filtered" :key="img.id">
      <div class="masonry-item gallery-item bg-white border border-[var(--stone)]/40 p-2 shadow-sm" :class="{ 'is-selected': isSelected(img.id) }">
        <?php if ($isAdmin): ?>
        <input type="checkbox" class="select-check"
               :value="String(img.id)"
               x-model="selectedIds"
               @click.stop
               :aria-label="'Select ' + (img.title || img.category)"/>
        <?php endif; ?>
        <img :src="img.src" :alt="img.title" loading="lazy" class="rounded-lg"
             @click="lightbox=true; lbSrc=img.src; lbTitle=img.title"/>
        <!-- caption -->
        <div class="absolute bottom-2 left-2 right-2 px-3 py-2 rounded-b-lg"
             style="background:linear-gradient(to top, rgba(0,0,0,0.65), transparent);">
          <p class="text-white text-xs truncate" x-text="img.title || img.category"></p>
        </div>
        <?php if ($isAdmin): ?>
        <!-- delete overlay (admin only) -->
        <div class="admin-overlay absolute inset-2 flex items-center justify-center rounded-lg"
             style="background:rgba(0,0,0,0.45);">
          <button @click.stop="confirmDelete(img.id)"
                  class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-medium bg-red-600 text-white border border-red-700 hover:brightness-110 transition">
            <i data-lucide="trash-2" class="w-4 h-4"></i> Delete
          </button>
        </div>
        <?php endif; ?>
      </div>
    </template>
    <!-- empty state -->
    <div x-show="filtered.length === 0" class="text-center py-20 text-[var(--text-faint)]" style="column-span:all;">
      <i data-lucide="image-off" class="w-12 h-12 mx-auto mb-3 opacity-40"></i>
      <p class="text-sm tracking-widest">No images in this category yet.</p>
      <?php if ($isAdmin): ?>
      <button @click="uploadModal=true" class="mt-3 text-[var(--gold)] text-xs hover:underline">+ Add the first image</button>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ═══ Video grid ═══ -->
<div class="max-w-7xl mx-auto px-6 pb-16" x-show="filter === 'video'" x-transition>
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php
    $videos = glob(dirname(__DIR__) . '/imgs/*.mp4') ?: [];
    foreach ($videos as $v):
      $fname = basename($v);
    ?>
    <div class="rounded-2xl overflow-hidden bg-white border border-[var(--stone)] shadow-sm">
      <video controls class="w-full h-56 object-cover" preload="metadata">
        <source src="../imgs/<?= rawurlencode($fname) ?>" type="video/mp4"/>
      </video>
      <div class="px-4 py-3"><p class="text-[var(--text-muted)] text-xs font-semibold">Property Video</p></div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($videos)): ?>
    <p class="text-[var(--text-faint)] text-sm col-span-3 text-center py-16">No videos uploaded yet.</p>
    <?php endif; ?>
  </div>
</div>

<!-- ═══ Lightbox ═══ -->
<div x-show="lightbox" x-transition
     class="fixed inset-0 z-[999] bg-black/95 flex flex-col items-center justify-center p-6"
     @keydown.escape.window="lightbox=false"
     @click.self="lightbox=false">
  <button @click="lightbox=false" class="absolute top-6 right-6 text-white/50 hover:text-white">
    <i data-lucide="x" class="w-8 h-8"></i>
  </button>
  <img :src="lbSrc" class="max-h-[85vh] max-w-full rounded-xl shadow-2xl"/>
  <p x-text="lbTitle" class="text-white/70 text-sm mt-4 tracking-widest font-semibold"></p>
</div>

<?php if ($isAdmin): ?>
<!-- ═══ Upload Modal ═══ -->
<div x-show="uploadModal" x-transition
     class="fixed inset-0 z-[998] modal-bg flex items-center justify-center p-4"
     @keydown.escape.window="uploadModal=false"
     @click.self="uploadModal=false">
  <div class="w-full max-w-lg rounded-2xl p-8 relative bg-white border border-[var(--stone)] shadow-xl">
    <button @click="uploadModal=false" class="absolute top-5 right-5 text-[var(--text-faint)] hover:text-[var(--text)]">
      <i data-lucide="x" class="w-5 h-5"></i>
    </button>
    <h2 class="font-display text-3xl text-[var(--lapis)] mb-1">Add Image</h2>
    <p class="text-[var(--text-muted)] text-xs mb-7">Upload a new photo to the gallery database</p>

    <form method="POST" enctype="multipart/form-data" class="space-y-5">
      <input type="hidden" name="action" value="upload"/>

      <!-- Drop zone -->
      <div class="drop-zone rounded-xl p-8 text-center cursor-pointer bg-[var(--bg-alt)]/40"
           id="dropZone"
           @dragover.prevent="$el.classList.add('drag-over')"
           @dragleave="$el.classList.remove('drag-over')"
           @drop.prevent="handleDrop($event)"
           @click="$refs.fileInput.click()">
        <input type="file" name="image" accept="image/*" class="hidden" x-ref="fileInput" @change="previewFile($event)"/>
        <div x-show="!previewSrc">
          <i data-lucide="upload-cloud" class="w-10 h-10 mx-auto mb-3 text-[var(--gold)] opacity-70"></i>
          <p class="text-[var(--text-muted)] text-sm">Drag & drop or <span class="text-[var(--gold)] font-medium">click to browse</span></p>
          <p class="text-[var(--text-faint)] text-xs mt-1">JPG · PNG · WEBP · GIF — max 8 MB</p>
        </div>
        <div x-show="previewSrc">
          <img :src="previewSrc" class="max-h-40 mx-auto rounded-lg object-contain pointer-events-none"/>
          <p class="text-[var(--gold)] text-xs mt-2 font-medium" x-text="previewName"></p>
        </div>
      </div>

      <!-- Title -->
      <div>
        <label class="form-label block mb-1.5">Title <span class="text-[var(--text-faint)]">(optional)</span></label>
        <input type="text" name="title" placeholder="e.g. Luxury Suite Interior"
               class="w-full bg-white border border-[var(--stone)] rounded-xl px-4 py-3 text-sm text-[var(--text)] focus:outline-none focus:border-[var(--gold)] transition"/>
      </div>

      <!-- Category + Order -->
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="form-label block mb-1.5">Category</label>
          <select name="category" class="w-full bg-white border border-[var(--stone)] rounded-xl px-4 py-3 text-sm text-[var(--text)] focus:outline-none focus:border-[var(--gold)] transition">
            <option value="rooms">Rooms</option>
            <option value="exterior">Exterior</option>
            <option value="amenities">Amenities</option>
            <option value="general">General</option>
          </select>
        </div>
        <div>
          <label class="form-label block mb-1.5">Sort Order</label>
          <input type="number" name="sort_order" value="0" min="0"
                 class="w-full bg-white border border-[var(--stone)] rounded-xl px-4 py-3 text-sm text-[var(--text)] focus:outline-none focus:border-[var(--gold)] transition"/>
        </div>
      </div>

      <button type="submit" :disabled="!previewSrc"
              class="w-full py-3.5 rounded-xl text-sm tracking-widest text-white font-medium transition disabled:opacity-40 disabled:cursor-not-allowed btn-primary shadow-sm">
        <i data-lucide="upload" class="w-4 h-4 inline mr-2 -mt-0.5"></i> UPLOAD TO GALLERY
      </button>
    </form>
  </div>
</div>

<!-- ═══ Delete Confirm Modal ═══ -->
<div x-show="deleteModal" x-transition
     class="fixed inset-0 z-[999] modal-bg flex items-center justify-center p-4"
     @click.self="deleteModal=false">
  <div class="rounded-2xl p-8 max-w-sm w-full text-center bg-white border border-[var(--stone)] shadow-xl animate-fade-in">
    <div class="w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-5 bg-red-50 border border-red-100">
      <i data-lucide="trash-2" class="w-6 h-6 text-red-600"></i>
    </div>
    <h3 class="font-display text-2xl text-[var(--lapis)] mb-2">Delete Image?</h3>
    <p class="text-[var(--text-muted)] text-sm mb-7">This will permanently remove the photo from the gallery.</p>
    <div class="flex gap-3">
      <button @click="deleteModal=false" class="flex-1 py-3 rounded-xl text-sm border border-[var(--stone)] text-[var(--text-muted)] hover:text-[var(--text)] bg-white transition">
        CANCEL
      </button>
      <form method="POST" class="flex-1">
        <input type="hidden" name="action" value="delete"/>
        <input type="hidden" name="id" :value="deleteId"/>
        <button type="submit" class="w-full py-3 rounded-xl text-sm text-white bg-red-600 border border-red-700 hover:brightness-110 transition shadow-sm">
          DELETE
        </button>
      </form>
    </div>
  </div>
</div>

<!-- ═══ Bulk Delete Confirm Modal ═══ -->
<div x-show="bulkDeleteModal" x-transition
     class="fixed inset-0 z-[999] modal-bg flex items-center justify-center p-4"
     @click.self="bulkDeleteModal=false">
  <div class="rounded-2xl p-8 max-w-md w-full text-center bg-white border border-[var(--stone)] shadow-xl">
    <div class="w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-5 bg-red-50 border border-red-100">
      <i data-lucide="trash-2" class="w-6 h-6 text-red-600"></i>
    </div>
    <h3 class="font-display text-2xl text-[var(--lapis)] mb-2">Delete Selected Images?</h3>
    <p class="text-[var(--text-muted)] text-sm mb-7">
      This will permanently remove <span class="text-[var(--text)] font-semibold" x-text="selectedIds.length"></span>
      selected <span x-text="selectedIds.length === 1 ? 'photo' : 'photos'"></span> from the gallery.
    </p>
    <div class="flex gap-3">
      <button @click="bulkDeleteModal=false" class="flex-1 py-3 rounded-xl text-sm border border-[var(--stone)] text-[var(--text-muted)] hover:text-[var(--text)] bg-white transition">
        CANCEL
      </button>
      <form method="POST" class="flex-1">
        <input type="hidden" name="action" value="bulk_delete"/>
        <template x-for="id in selectedIds" :key="'delete-' + id">
          <input type="hidden" name="ids[]" :value="id"/>
        </template>
        <button type="submit" class="w-full py-3 rounded-xl text-sm text-white bg-red-600 border border-red-700 hover:brightness-110 transition shadow-sm">
          DELETE SELECTED
        </button>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<?php include '../partials/footer.php'; ?>
<script>lucide.createIcons();</script>
<script>
function galleryApp() {
  return {
    images: <?= $jsJson ?>,
    filter: 'all',
    selectedIds: [],
    lightbox: false, lbSrc: '', lbTitle: '',
    uploadModal: false,
    deleteModal: false, deleteId: null,
    bulkDeleteModal: false,
    previewSrc: '', previewName: '',

    init() { this.$nextTick(() => lucide.createIcons()); },

    get filtered() {
      if (this.filter === 'all') return this.images;
      if (this.filter === 'video') return [];
      return this.images.filter(i => i.category === this.filter);
    },

    get visibleIds() {
      return this.filtered.map(i => String(i.id));
    },

    get allFilteredSelected() {
      const ids = this.visibleIds;
      return ids.length > 0 && ids.every(id => this.selectedIds.includes(id));
    },

    isSelected(id) {
      return this.selectedIds.includes(String(id));
    },

    toggleSelectAll() {
      const ids = this.visibleIds;
      if (!ids.length) return;
      if (this.allFilteredSelected) {
        this.selectedIds = this.selectedIds.filter(id => !ids.includes(id));
      } else {
        this.selectedIds = Array.from(new Set([...this.selectedIds, ...ids]));
      }
    },

    clearSelection() {
      this.selectedIds = [];
      this.bulkDeleteModal = false;
    },

    previewFile(e) {
      const f = e.target.files[0]; if (!f) return;
      this.previewName = f.name;
      const r = new FileReader();
      r.onload = ev => this.previewSrc = ev.target.result;
      r.readAsDataURL(f);
    },

    handleDrop(e) {
      const f = e.dataTransfer.files[0]; if (!f) return;
      try { const dt = new DataTransfer(); dt.items.add(f); this.$refs.fileInput.files = dt.files; } catch(_) {}
      this.previewName = f.name;
      const r = new FileReader();
      r.onload = ev => this.previewSrc = ev.target.result;
      r.readAsDataURL(f);
      document.getElementById('dropZone')?.classList.remove('drag-over');
    },

    confirmDelete(id) {
      this.deleteId = id;
      this.deleteModal = true;
      this.$nextTick(() => lucide.createIcons());
    },
  };
}
</script>
</body>
</html>
