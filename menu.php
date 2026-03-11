<?php
require_once __DIR__ . '/config.php';
$db = getDB();

// Filters
$categoryId = (int) ($_GET['category'] ?? 0);
$veg = $_GET['veg'] ?? '';
$search = trim($_GET['q'] ?? '');
$maxPrice = (int) ($_GET['max_price'] ?? 500);
$sort = $_GET['sort'] ?? 'popular';

// Build query
$where = ['p.is_active = 1'];
$params = [];
if ($categoryId) {
    $where[] = 'p.category_id = ?';
    $params[] = $categoryId;
}
if ($veg === '1') {
    $where[] = 'p.is_veg = 1';
}
if ($veg === '0') {
    $where[] = 'p.is_veg = 0';
}
if ($search) {
    $where[] = '(p.name LIKE ? OR p.description LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($maxPrice > 0) {
    $where[] = 'p.price <= ?';
    $params[] = $maxPrice;
}

$orderBy = match ($sort) {
    'price_asc' => 'p.price ASC',
    'price_desc' => 'p.price DESC',
    'rating' => 'p.rating DESC',
    'newest' => 'p.created_at DESC',
    default => 'p.rating_count DESC'
};

$sql = 'SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON c.id=p.category_id WHERE ' . implode(' AND ', $where) . " ORDER BY $orderBy";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// All categories for tabs
$categories = $db->query('SELECT * FROM categories WHERE is_active=1 ORDER BY sort_order')->fetchAll();

$pageTitle = 'Menu';
$metaDescription = 'Browse our full menu – burgers, biryani, pizza, rolls, desserts and more!';
require_once __DIR__ . '/templates/header.php';
?>
<!-- Google Fonts for ornamental heading & script subtitle -->
<link
    href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;1,700&family=Dancing+Script:wght@500;600&display=swap"
    rel="stylesheet">

<style>
    .menu-ornament-header {
        text-align: center;
        padding: 48px 20px 36px;
        position: relative;
    }

    .menu-ornament-title {
        font-family: 'Playfair Display', Georgia, serif;
        font-size: clamp(2.6rem, 7vw, 4.2rem);
        font-weight: 700;
        font-style: italic;
        background: linear-gradient(135deg, #c9a84c 0%, #f5d68a 40%, #b8872a 70%, #e8c96e 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        letter-spacing: 0.02em;
        line-height: 1.15;
        margin: 0;
        display: inline-block;
        text-shadow: none;
        filter: drop-shadow(0 2px 8px rgba(201, 168, 76, 0.25));
    }

    .menu-ornament-svg {
        display: block;
        margin: 0 auto;
        width: min(420px, 88vw);
        opacity: 0.88;
    }

    .menu-ornament-sub {
        display: block;
        width: 100%;
        text-align: center;
        margin: 18px auto 0;
        font-family: 'Dancing Script', cursive;
        font-size: clamp(1.4rem, 3.5vw, 2rem);
        font-weight: 500;
        color: #c9a84c;
        letter-spacing: 0.03em;
        line-height: 1.4;
        text-shadow: 0 1px 6px rgba(201, 168, 76, 0.18);
    }

    /* ── Glass filter bar ───────────────────────────────── */
    .filter-row {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 12px;
        padding: 14px 18px;
        background: rgba(10, 22, 40, 0.55);
        backdrop-filter: blur(18px);
        -webkit-backdrop-filter: blur(18px);
        border: 1px solid rgba(255, 255, 255, 0.10);
        border-radius: 16px;
        box-shadow: 0 4px 24px rgba(0, 0, 0, 0.35);
        position: relative;
        z-index: 100;
        overflow: visible;
    }

    /* Search input – glossy glass */
    .filter-row input[type="text"],
    #liveSearch {
        background: linear-gradient(160deg,
                rgba(255, 255, 255, 0.18) 0%,
                rgba(255, 255, 255, 0.07) 40%,
                rgba(255, 255, 255, 0.04) 100%) !important;
        border: 1px solid rgba(255, 255, 255, 0.28) !important;
        border-bottom-color: rgba(255, 255, 255, 0.10) !important;
        backdrop-filter: blur(20px) saturate(180%) !important;
        -webkit-backdrop-filter: blur(20px) saturate(180%) !important;
        color: var(--text-primary) !important;
        border-radius: 50px !important;
        box-shadow:
            0 8px 32px rgba(0, 0, 0, 0.35),
            inset 0 1px 0 rgba(255, 255, 255, 0.30),
            inset 0 -1px 0 rgba(0, 0, 0, 0.15) !important;
        transition: box-shadow 0.25s, border-color 0.25s;
    }

    .filter-row input[type="text"]:focus,
    #liveSearch:focus {
        border-color: rgba(255, 255, 255, 0.45) !important;
        box-shadow:
            0 8px 32px rgba(0, 0, 0, 0.40),
            0 0 0 3px rgba(255, 255, 255, 0.10),
            inset 0 1px 0 rgba(255, 255, 255, 0.35),
            inset 0 -1px 0 rgba(0, 0, 0, 0.15) !important;
        outline: none;
    }

    /* Veg toggle – realistic switch */
    .filter-toggle {
        display: flex;
        align-items: center;
        gap: 0;
        background: linear-gradient(180deg,
                rgba(10, 22, 40, 0.80) 0%,
                rgba(20, 35, 60, 0.75) 100%);
        border: 1px solid rgba(255, 255, 255, 0.12);
        border-radius: 50px;
        padding: 4px;
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        box-shadow:
            0 6px 20px rgba(0, 0, 0, 0.45),
            inset 0 1px 0 rgba(255, 255, 255, 0.08),
            inset 0 -1px 0 rgba(0, 0, 0, 0.25);
        position: relative;
    }

    .filter-toggle-btn {
        padding: 7px 16px;
        border-radius: 50px;
        font-size: 13px;
        font-weight: 600;
        color: rgba(255, 255, 255, 0.50);
        background: transparent;
        border: none;
        cursor: pointer;
        transition: color 0.2s, transform 0.15s;
        position: relative;
        z-index: 1;
        letter-spacing: 0.02em;
    }

    .filter-toggle-btn.active {
        color: #fff;
        background: linear-gradient(135deg,
                rgba(59, 130, 246, 0.90) 0%,
                rgba(37, 99, 235, 0.95) 100%);
        box-shadow:
            0 3px 12px rgba(59, 130, 246, 0.55),
            inset 0 1px 0 rgba(255, 255, 255, 0.25),
            inset 0 -1px 0 rgba(0, 0, 0, 0.20);
        transform: translateY(-0.5px);
    }

    .filter-toggle-btn.active.veg-active {
        background: linear-gradient(135deg,
                rgba(34, 197, 94, 0.85) 0%,
                rgba(22, 163, 74, 0.95) 100%);
        box-shadow:
            0 3px 12px rgba(34, 197, 94, 0.50),
            inset 0 1px 0 rgba(255, 255, 255, 0.25),
            inset 0 -1px 0 rgba(0, 0, 0, 0.15);
    }

    .filter-toggle-btn.active.nonveg-active {
        background: linear-gradient(135deg,
                rgba(239, 68, 68, 0.85) 0%,
                rgba(220, 38, 38, 0.95) 100%);
        box-shadow:
            0 3px 12px rgba(239, 68, 68, 0.50),
            inset 0 1px 0 rgba(255, 255, 255, 0.25),
            inset 0 -1px 0 rgba(0, 0, 0, 0.15);
    }

    .filter-toggle-btn:hover:not(.active) {
        color: rgba(255, 255, 255, 0.80);
        background: rgba(255, 255, 255, 0.07);
    }

    /* Price range container – no outline */
    .filter-price-range {
        display: flex;
        align-items: center;
        gap: 8px;
        background: transparent;
        border: none;
        border-radius: 50px;
        padding: 6px 14px;
    }

    #priceRange {
        outline: none !important;
        box-shadow: none !important;
        border: none !important;
    }

    /* ── Custom glass sort dropdown ─────────────────────── */
    .sort-dropdown {
        position: relative;
    }

    .sort-trigger {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 9px 18px;
        border-radius: 50px;
        background: linear-gradient(160deg,
                rgba(255, 255, 255, 0.14) 0%,
                rgba(255, 255, 255, 0.05) 100%);
        border: 1px solid rgba(255, 255, 255, 0.22);
        border-bottom-color: rgba(255, 255, 255, 0.08);
        backdrop-filter: blur(18px) saturate(160%);
        -webkit-backdrop-filter: blur(18px) saturate(160%);
        color: var(--text-primary, #fff);
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        white-space: nowrap;
        box-shadow:
            0 6px 24px rgba(0, 0, 0, 0.35),
            inset 0 1px 0 rgba(255, 255, 255, 0.28),
            inset 0 -1px 0 rgba(0, 0, 0, 0.14);
        transition: box-shadow 0.2s, border-color 0.2s;
        user-select: none;
    }

    .sort-trigger:hover {
        border-color: rgba(255, 255, 255, 0.35);
        box-shadow:
            0 8px 28px rgba(0, 0, 0, 0.42),
            inset 0 1px 0 rgba(255, 255, 255, 0.32),
            inset 0 -1px 0 rgba(0, 0, 0, 0.14);
    }

    .sort-trigger .sort-arrow {
        display: inline-block;
        transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        font-style: normal;
        font-size: 10px;
        opacity: 0.7;
    }

    .sort-dropdown.open .sort-arrow {
        transform: rotate(180deg);
    }

    .sort-panel {
        position: absolute;
        top: calc(100% + 10px);
        right: 0;
        min-width: 200px;
        background: linear-gradient(160deg,
                rgba(12, 25, 50, 0.92) 0%,
                rgba(8, 18, 38, 0.96) 100%);
        border: 1px solid rgba(255, 255, 255, 0.15);
        border-radius: 20px;
        backdrop-filter: blur(28px) saturate(180%);
        -webkit-backdrop-filter: blur(28px) saturate(180%);
        box-shadow:
            0 20px 60px rgba(0, 0, 0, 0.60),
            0 4px 16px rgba(0, 0, 0, 0.40),
            inset 0 1px 0 rgba(255, 255, 255, 0.18),
            inset 0 -1px 0 rgba(0, 0, 0, 0.20);
        overflow: hidden;
        z-index: 9999;
        /* start state */
        opacity: 0;
        transform: translateY(-8px) scale(0.97);
        pointer-events: none;
        transition:
            opacity 0.25s cubic-bezier(0.34, 1.1, 0.64, 1),
            transform 0.28s cubic-bezier(0.34, 1.56, 0.64, 1);
    }

    .sort-dropdown.open .sort-panel {
        opacity: 1;
        transform: translateY(0) scale(1);
        pointer-events: all;
    }

    .sort-option {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 18px;
        font-size: 13px;
        font-weight: 500;
        color: rgba(255, 255, 255, 0.65);
        cursor: pointer;
        transition: background 0.15s, color 0.15s;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }

    .sort-option:last-child {
        border-bottom: none;
    }

    .sort-option:hover {
        background: rgba(255, 255, 255, 0.08);
        color: #fff;
    }

    .sort-option.selected {
        color: #fff;
        background: rgba(59, 130, 246, 0.18);
    }

    .sort-option .sort-check {
        opacity: 0;
        color: #60a5fa;
        font-size: 15px;
        transition: opacity 0.15s;
    }

    .sort-option.selected .sort-check {
        opacity: 1;
    }

    /* top highlight strip */
    .sort-panel::before {
        content: '';
        display: block;
        height: 1px;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.25), transparent);
        margin: 0 20px;
    }

    /* Category tabs row – glass bar */
    .cat-tabs {
        display: flex;
        flex-wrap: nowrap;
        overflow-x: auto;
        gap: 8px;
        padding: 10px 16px;
        background: rgba(10, 22, 40, 0.50);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid rgba(255, 255, 255, 0.09);
        border-radius: 14px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.28);
        margin-bottom: 24px;
        scrollbar-width: none;
    }

    .cat-tabs::-webkit-scrollbar {
        display: none;
    }

    .cat-tab {
        flex-shrink: 0;
        padding: 7px 18px;
        border-radius: 50px;
        font-size: 13px;
        font-weight: 500;
        color: var(--text-secondary);
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.08);
        cursor: pointer;
        transition: background 0.2s, color 0.2s, border-color 0.2s;
        white-space: nowrap;
    }

    .cat-tab:hover {
        background: rgba(255, 255, 255, 0.10);
        color: var(--text-primary);
    }

    .cat-tab.active {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        color: #fff;
        border-color: transparent;
        box-shadow: 0 2px 12px var(--primary-glow);
    }
</style>

<div class="menu-ornament-header">

    <!-- Top ornamental curl SVG -->
    <svg class="menu-ornament-svg" viewBox="0 0 420 80" fill="none" xmlns="http://www.w3.org/2000/svg"
        aria-hidden="true">
        <defs>
            <linearGradient id="goldGradTop" x1="0" y1="0" x2="420" y2="0" gradientUnits="userSpaceOnUse">
                <stop offset="0%" stop-color="#b8872a" />
                <stop offset="50%" stop-color="#f5d68a" />
                <stop offset="100%" stop-color="#b8872a" />
            </linearGradient>
        </defs>
        <!-- Central circle finial -->
        <circle cx="210" cy="10" r="5" stroke="url(#goldGradTop)" stroke-width="2" fill="none" />
        <!-- Left big scroll -->
        <path
            d="M210 14 C210 30, 175 30, 160 22 C145 14, 140 4, 152 4 C164 4, 163 18, 150 20 C137 22, 120 14, 108 20 C96 26, 92 42, 100 50 C108 58, 122 56, 128 48 C134 40, 126 30, 116 34"
            stroke="url(#goldGradTop)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" fill="none" />
        <!-- Left small inner curl -->
        <path d="M116 34 C110 37, 108 44, 114 46 C120 48, 126 42, 122 38" stroke="url(#goldGradTop)" stroke-width="1.8"
            stroke-linecap="round" fill="none" />
        <!-- Left outer tail -->
        <path d="M100 50 C85 62, 55 66, 30 60 C15 56, 8 50, 10 44 C12 38, 22 38, 28 44 C34 50, 28 58, 20 56"
            stroke="url(#goldGradTop)" stroke-width="2" stroke-linecap="round" fill="none" />

        <!-- Right big scroll (mirror) -->
        <path
            d="M210 14 C210 30, 245 30, 260 22 C275 14, 280 4, 268 4 C256 4, 257 18, 270 20 C283 22, 300 14, 312 20 C324 26, 328 42, 320 50 C312 58, 298 56, 292 48 C286 40, 294 30, 304 34"
            stroke="url(#goldGradTop)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" fill="none" />
        <!-- Right small inner curl -->
        <path d="M304 34 C310 37, 312 44, 306 46 C300 48, 294 42, 298 38" stroke="url(#goldGradTop)" stroke-width="1.8"
            stroke-linecap="round" fill="none" />
        <!-- Right outer tail -->
        <path
            d="M320 50 C335 62, 365 66, 390 60 C405 56, 412 50, 410 44 C408 38, 398 38, 392 44 C386 50, 392 58, 400 56"
            stroke="url(#goldGradTop)" stroke-width="2" stroke-linecap="round" fill="none" />
    </svg>

    <h1 class="menu-ornament-title">Our Menu</h1>

    <!-- Bottom ornamental curl SVG (flipped) -->
    <svg class="menu-ornament-svg" viewBox="0 0 420 80" fill="none" xmlns="http://www.w3.org/2000/svg"
        aria-hidden="true" style="transform:scaleY(-1);margin-top:4px;">
        <defs>
            <linearGradient id="goldGradBot" x1="0" y1="0" x2="420" y2="0" gradientUnits="userSpaceOnUse">
                <stop offset="0%" stop-color="#b8872a" />
                <stop offset="50%" stop-color="#f5d68a" />
                <stop offset="100%" stop-color="#b8872a" />
            </linearGradient>
        </defs>
        <circle cx="210" cy="10" r="5" stroke="url(#goldGradBot)" stroke-width="2" fill="none" />
        <path
            d="M210 14 C210 30, 175 30, 160 22 C145 14, 140 4, 152 4 C164 4, 163 18, 150 20 C137 22, 120 14, 108 20 C96 26, 92 42, 100 50 C108 58, 122 56, 128 48 C134 40, 126 30, 116 34"
            stroke="url(#goldGradBot)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" fill="none" />
        <path d="M116 34 C110 37, 108 44, 114 46 C120 48, 126 42, 122 38" stroke="url(#goldGradBot)" stroke-width="1.8"
            stroke-linecap="round" fill="none" />
        <path d="M100 50 C85 62, 55 66, 30 60 C15 56, 8 50, 10 44 C12 38, 22 38, 28 44 C34 50, 28 58, 20 56"
            stroke="url(#goldGradBot)" stroke-width="2" stroke-linecap="round" fill="none" />
        <path
            d="M210 14 C210 30, 245 30, 260 22 C275 14, 280 4, 268 4 C256 4, 257 18, 270 20 C283 22, 300 14, 312 20 C324 26, 328 42, 320 50 C312 58, 298 56, 292 48 C286 40, 294 30, 304 34"
            stroke="url(#goldGradBot)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" fill="none" />
        <path d="M304 34 C310 37, 312 44, 306 46 C300 48, 294 42, 298 38" stroke="url(#goldGradBot)" stroke-width="1.8"
            stroke-linecap="round" fill="none" />
        <path
            d="M320 50 C335 62, 365 66, 390 60 C405 56, 412 50, 410 44 C408 38, 398 38, 392 44 C386 50, 392 58, 400 56"
            stroke="url(#goldGradBot)" stroke-width="2" stroke-linecap="round" fill="none" />
    </svg>

    <p class="menu-ornament-sub">Fresh, delicious food made with love &nbsp;·&nbsp; <?= count($products) ?> dishes
        available</p>
</div>

<div class="container section-sm">
    <!-- Search + Sort -->
    <div class="filter-row" style="margin-bottom:20px;">
        <div class="filter-search" style="flex:1;min-width:200px;">
            <div class="input-group">
                <i class="fa fa-magnifying-glass input-icon"></i>
                <input type="text" class="form-control" id="liveSearch" placeholder="Search dishes..."
                    value="<?= e($search) ?>" oninput="filterProducts()">
            </div>
        </div>
        <div class="filter-toggle" id="vegToggle">
            <button class="filter-toggle-btn <?= $veg === '' ? 'active' : '' ?>" onclick="setVeg('')">All</button>
            <button class="filter-toggle-btn veg-btn <?= $veg === '1' ? 'active veg-active' : '' ?>"
                onclick="setVeg('1')">🟢 Veg</button>
            <button class="filter-toggle-btn nonveg-btn <?= $veg === '0' ? 'active nonveg-active' : '' ?>"
                onclick="setVeg('0')">🔴 Non-Veg</button>
        </div>
        <div class="filter-price-range">
            <span style="font-size:13px;color:var(--text-muted);">Max ₹</span>
            <input type="range" id="priceRange" min="50" max="500" step="10" value="<?= $maxPrice ?>"
                oninput="document.getElementById('priceVal').textContent=this.value;filterProducts();">
            <span id="priceVal" style="font-size:13px;color:var(--text-primary);font-weight:600;">
                <?= $maxPrice ?>
            </span>
        </div>
        <!-- Custom glass sort dropdown -->
        <?php
        $sortLabels = [
            'popular' => 'Most Popular',
            'rating' => 'Top Rated',
            'price_asc' => 'Price: Low–High',
            'price_desc' => 'Price: High–Low',
            'newest' => 'Newest',
        ];
        ?>
        <div class="sort-dropdown" id="sortDropdown">
            <div class="sort-trigger" id="sortTrigger" onclick="toggleSortDropdown()">
                <span id="sortLabel"><?= $sortLabels[$sort] ?? 'Most Popular' ?></span>
                <i class="sort-arrow">▼</i>
            </div>
            <div class="sort-panel" id="sortPanel">
                <?php foreach ($sortLabels as $val => $label): ?>
                    <div class="sort-option <?= $sort === $val ? 'selected' : '' ?>"
                        onclick="pickSort('<?= $val ?>', '<?= $label ?>')" data-val="<?= $val ?>">
                        <?= $label ?>
                        <span class="sort-check">✓</span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Category Tabs -->
    <div class="cat-tabs" id="catTabs">
        <button class="cat-tab <?= !$categoryId ? 'active' : '' ?>" onclick="setCategory(0)">All</button>
        <?php foreach ($categories as $cat): ?>
            <button class="cat-tab <?= $categoryId == $cat['id'] ? 'active' : '' ?>"
                onclick="setCategory(<?= $cat['id'] ?>)">
                <?= e($cat['icon']) ?>
                <?= e($cat['name']) ?>
            </button>
        <?php endforeach; ?>
    </div>

    <!-- Products Grid -->
    <div class="food-grid" id="productsGrid">
        <?php if (empty($products)): ?>
            <div class="empty-state" style="grid-column:1/-1;">
                <div class="empty-state-icon">🍽️</div>
                <h3>No dishes found</h3>
                <p>Try adjusting your filters or search.</p>
                <a href="<?= BASE_URL ?>/menu.php" class="btn btn-primary">Clear Filters</a>
            </div>
        <?php else:
            foreach ($products as $p):
                $img = $p['image'] ? BASE_URL . '/assets/uploads/products/' . e($p['image']) : BASE_URL . '/assets/images/food/placeholder.svg';
                $vegd = $p['is_veg'] ? '<span class="badge badge-veg">🟢 Veg</span>' : '<span class="badge badge-nonveg">🔴 Non-Veg</span>';
                $orig = $p['original_price'] > 0 ? '<span class="original">' . CURRENCY_SYMBOL . number_format($p['original_price'], 0) . '</span>' : '';
                $stars = str_repeat('★', round($p['rating'])) . str_repeat('☆', 5 - round($p['rating']));
                $oos = $p['stock_status'] === 'out_of_stock';
                ?>
                <div class="card food-card" data-name="<?= strtolower(e($p['name'])) ?>" data-price="<?= $p['price'] ?>"
                    data-veg="<?= $p['is_veg'] ?>" data-cat="<?= $p['category_id'] ?>">
                    <div class="food-card-img-wrap">
                        <a href="<?= BASE_URL ?>/product_detail.php?id=<?= $p['id'] ?>">
                            <img src="<?= $img ?>" alt="<?= e($p['name']) ?>" class="card-img" loading="lazy">
                        </a>
                        <div class="food-badge">
                            <?= $vegd ?>
                            <?= $oos ? '<span class="badge" style="background:rgba(239,68,68,0.2);color:#f87171;border-color:rgba(239,68,68,0.4);">Out of Stock</span>' : '' ?>
                        </div>
                    </div>
                    <div class="food-card-body">
                        <div class="food-name">
                            <a href="<?= BASE_URL ?>/product_detail.php?id=<?= $p['id'] ?>">
                                <?= e($p['name']) ?>
                            </a>
                        </div>
                        <div class="food-desc">
                            <?= e($p['description']) ?>
                        </div>
                        <div class="food-footer">
                            <div class="food-footer-top">
                                <span class="food-price">
                                    <?= CURRENCY_SYMBOL . number_format($p['price'], 0) ?>
                                    <?= $orig ?>
                                </span>
                                <div class="food-rating"><span class="stars">
                                        <?= $stars ?>
                                    </span> (
                                    <?= $p['rating_count'] ?>)
                                </div>
                            </div>
                            <?php if (!$oos): ?>
                                <div class="card-btn-group">
                                    <button class="add-to-cart-btn" data-product-id="<?= $p['id'] ?>"
                                        data-product-name="<?= e($p['name']) ?>" data-product-price="<?= $p['price'] ?>">
                                        <i class="fa fa-plus"></i> Add to Cart
                                    </button>
                                    <button class="buy-now-btn" data-product-id="<?= $p['id'] ?>"
                                        data-product-name="<?= e($p['name']) ?>">
                                        <i class="fa fa-bolt"></i> Buy Now
                                    </button>
                                </div>
                            <?php else: ?>
                                <button class="btn btn-outline btn-sm" disabled>Unavailable</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; endif; ?>
    </div>
</div>

<script>
    let currentCategory = <?= $categoryId ?>;
    let currentVeg = '<?= e($veg) ?>';

    function setCategory(id) {
        currentCategory = id;
        filterProducts();
        document.querySelectorAll('.cat-tab').forEach((b, i) => b.classList.toggle('active', i === (id === 0 ? 0 : <?= json_encode(array_column($categories, 'id')) ?>.indexOf(id) + 1)));
    }
    function setVeg(v) {
        currentVeg = v;
        filterProducts();
        const btns = document.querySelectorAll('.filter-toggle-btn');
        btns.forEach(b => {
            b.classList.remove('active', 'veg-active', 'nonveg-active');
        });
        if (v === '') {
            btns[0].classList.add('active');
        } else if (v === '1') {
            btns[1].classList.add('active', 'veg-active');
        } else {
            btns[2].classList.add('active', 'nonveg-active');
        }
    }
    function applySort(s) {
        const u = new URL(window.location); u.searchParams.set('sort', s); window.location = u;
    }

    /* ── Custom sort dropdown ── */
    function toggleSortDropdown() {
        document.getElementById('sortDropdown').classList.toggle('open');
    }
    function pickSort(val, label) {
        // Update label
        document.getElementById('sortLabel').textContent = label;
        // Update selected state
        document.querySelectorAll('.sort-option').forEach(o => {
            o.classList.toggle('selected', o.dataset.val === val);
        });
        // Close panel
        document.getElementById('sortDropdown').classList.remove('open');
        // Navigate
        applySort(val);
    }
    // Close on outside click
    document.addEventListener('click', function (e) {
        const dd = document.getElementById('sortDropdown');
        if (dd && !dd.contains(e.target)) dd.classList.remove('open');
    });
    function filterProducts() {
        const q = document.getElementById('liveSearch').value.toLowerCase();
        const maxP = parseInt(document.getElementById('priceRange').value);
        document.querySelectorAll('.food-card').forEach(card => {
            const nameMatch = !q || card.dataset.name.includes(q);
            const priceMatch = parseFloat(card.dataset.price) <= maxP;
            const vegMatch = currentVeg === '' || card.dataset.veg === currentVeg;
            const catMatch = !currentCategory || parseInt(card.dataset.cat) === currentCategory;
            card.style.display = (nameMatch && priceMatch && vegMatch && catMatch) ? '' : 'none';
        });
    }
    // Activate category tabs properly
    document.querySelectorAll('.cat-tab').forEach((btn, i) => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.cat-tab').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
        });
    });
</script>
<?php require_once __DIR__ . '/templates/footer.php'; ?>