<?php
require_once __DIR__ . '/config.php';
$db = getDB();

// Fetch featured products
$featured = $db->query("SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON c.id=p.category_id WHERE p.is_featured=1 AND p.is_active=1 ORDER BY p.rating DESC LIMIT 8")->fetchAll();

// Fetch popular products
$popular = $db->query("SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON c.id=p.category_id WHERE p.is_popular=1 AND p.is_active=1 ORDER BY p.rating_count DESC LIMIT 4")->fetchAll();

// Fetch categories
$categories = $db->query("SELECT * FROM categories WHERE is_active=1 ORDER BY sort_order LIMIT 8")->fetchAll();

$pageTitle = 'Home';
$metaDescription = 'Order delicious food online with Bluemoon – fast delivery, amazing taste.';
require_once __DIR__ . '/templates/header.php';

function productCard(array $p, string $baseUrl): string
{
    $img = $p['image'] ? $baseUrl . '/assets/uploads/products/' . e($p['image']) : $baseUrl . '/assets/images/food/placeholder.svg';
    $veg = $p['is_veg'] ? '<span class="badge badge-veg">🟢 Veg</span>' : '<span class="badge badge-nonveg">🔴 Non-Veg</span>';
    $orig = $p['original_price'] > 0 ? '<span class="original">' . CURRENCY_SYMBOL . number_format($p['original_price'], 0) . '</span>' : '';
    $stars = str_repeat('★', round($p['rating'])) . str_repeat('☆', 5 - round($p['rating']));
    return <<<HTML
    <div class="card food-card animate-on-scroll">
      <div class="food-card-img-wrap">
        <a href="{$baseUrl}/product_detail.php?id={$p['id']}">
          <img src="{$img}" alt="{$p['name']}" class="card-img" loading="lazy">
        </a>
        <div class="food-badge">{$veg}</div>
      </div>
      <div class="food-card-body">
        <div class="food-name"><a href="{$baseUrl}/product_detail.php?id={$p['id']}">{$p['name']}</a></div>
        <div class="food-desc">{$p['description']}</div>
        <div class="food-footer">
          <div>
            <span class="food-price">₹{$p['price']}{$orig}</span>
            <div class="food-rating"><span class="stars">{$stars}</span> ({$p['rating_count']})</div>
          </div>
          <button class="add-to-cart-btn" data-product-id="{$p['id']}" data-product-name="{$p['name']}" data-product-price="{$p['price']}">
            <i class="fa fa-plus"></i> Add
          </button>
        </div>
      </div>
    </div>
    HTML;
}
?>

<!-- HERO -->
<section class="hero">
    <div class="hero-bg"></div>
    <div class="container hero-grid">
        <div class="hero-content">
            <div class="hero-tag">🌙 Kolkata's Favourite Food App</div>
            <h1 class="hero-title">
                Taste the <span class="highlight">Night</span>,<br>Delivered Right.
            </h1>
            <p class="hero-desc">Fresh, delicious food from the best restaurants — at your doorstep in 30 minutes or
                less.</p>
            <div class="hero-actions">
                <a href="<?= BASE_URL ?>/menu.php" class="btn btn-primary btn-lg">
                    <i class="fa fa-utensils"></i> Order Now
                </a>
                <a href="#how-it-works" class="btn btn-outline btn-lg">How It Works</a>
            </div>
            <div class="hero-stats">
                <div class="hero-stat">
                    <div class="hero-stat-num">500+</div>
                    <div class="hero-stat-label">Happy Customers</div>
                </div>
                <div class="hero-stat">
                    <div class="hero-stat-num">30 min</div>
                    <div class="hero-stat-label">Avg Delivery</div>
                </div>
                <div class="hero-stat">
                    <div class="hero-stat-num">4.8★</div>
                    <div class="hero-stat-label">App Rating</div>
                </div>
            </div>
        </div>
        <div class="hero-visual">
            <div class="hero-img-wrap">
                <div class="hero-img-ring hero-img-ring-1"></div>
                <div class="hero-img-ring hero-img-ring-2"></div>
                <img src="<?= BASE_URL ?>/assets/images/food/placeholder.svg" alt="Delicious Food" class="hero-food-img"
                    id="heroFoodImg">
                <div class="hero-float-card hero-float-card-1">
                    <div style="font-size:12px;color:var(--text-muted);">🎉 New Order!</div>
                    <div style="font-size:13px;font-weight:600;">Chicken Biryani</div>
                    <div style="font-size:12px;color:var(--success);">Being prepared...</div>
                </div>
                <div class="hero-float-card hero-float-card-2">
                    <div style="display:flex;align-items:center;gap:8px;">
                        <div style="font-size:22px;">⚡</div>
                        <div>
                            <div style="font-size:12px;font-weight:600;">Fast Delivery</div>
                            <div style="font-size:11px;color:var(--text-muted);">In 30 minutes</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CATEGORIES -->
<section class="section-sm">
    <div class="container">
        <div class="section-header" style="margin-bottom:36px;">
            <div class="section-label">Browse by Category</div>
            <h2 class="section-title">What's on Your Mind?</h2>
        </div>
        <div class="category-grid">
            <?php foreach ($categories as $cat): ?>
                <a href="<?= BASE_URL ?>/menu.php?category=<?= $cat['id'] ?>" class="card"
                    style="text-align:center;padding:20px 12px;transition:all 0.25s;">
                    <div style="font-size:32px;margin-bottom:8px;">
                        <?= e($cat['icon']) ?>
                    </div>
                    <div style="font-size:13px;font-weight:600;">
                        <?= e($cat['name']) ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- FEATURED ITEMS -->
<section class="section">
    <div class="container">
        <div class="section-header">
            <div class="section-label">✨ Chef's Picks</div>
            <h2 class="section-title">Featured Dishes</h2>
            <p class="section-subtitle">Handpicked favourites loved by hundreds of happy customers.</p>
        </div>
        <div class="food-grid">
            <?php foreach ($featured as $p):
                echo productCard($p, BASE_URL);
            endforeach; ?>
        </div>
        <div style="text-align:center;margin-top:40px;">
            <a href="<?= BASE_URL ?>/menu.php" class="btn btn-outline btn-lg">View Full Menu <i
                    class="fa fa-arrow-right"></i></a>
        </div>
    </div>
</section>

<!-- HOW IT WORKS -->
<section class="section" id="how-it-works"
    style="background:rgba(255,255,255,0.02);border-top:1px solid var(--glass-border);border-bottom:1px solid var(--glass-border);">
    <div class="container">
        <div class="section-header">
            <div class="section-label">Easy Steps</div>
            <h2 class="section-title">How It Works</h2>
        </div>
        <div class="grid-4">
            <?php
            $steps = [
                ['icon' => '🍔', 'num' => '01', 'title' => 'Browse Menu', 'desc' => 'Explore our wide range of dishes from multiple categories.'],
                ['icon' => '🛒', 'num' => '02', 'title' => 'Add to Cart', 'desc' => 'Choose your favourites and add them to your cart easily.'],
                ['icon' => '💳', 'num' => '03', 'title' => 'Make Payment', 'desc' => 'Pay securely via UPI, card, or cash on delivery.'],
                ['icon' => '🛵', 'num' => '04', 'title' => 'Get Delivery', 'desc' => 'Your fresh food arrives at your door within 30 minutes.'],
            ];
            foreach ($steps as $i => $s): ?>
                <div class="card" style="padding:32px 24px;text-align:center;"
                    class="animate-on-scroll delay-<?= $i + 1 ?>">
                    <div style="font-size:48px;margin-bottom:12px;">
                        <?= $s['icon'] ?>
                    </div>
                    <div style="font-size:11px;font-weight:700;color:var(--primary);letter-spacing:2px;margin-bottom:8px;">
                        <?= $s['num'] ?>
                    </div>
                    <h3 style="font-family:var(--font-head);font-weight:600;margin-bottom:8px;">
                        <?= $s['title'] ?>
                    </h3>
                    <p style="font-size:14px;color:var(--text-muted);line-height:1.6;">
                        <?= $s['desc'] ?>
                    </p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- POPULAR RIGHT NOW -->
<section class="section">
    <div class="container">
        <div class="section-header">
            <div class="section-label">🔥 Trending</div>
            <h2 class="section-title">Popular Right Now</h2>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:24px;">
            <?php foreach ($popular as $p):
                echo productCard($p, BASE_URL);
            endforeach; ?>
        </div>
    </div>
</section>

<!-- TESTIMONIALS -->
<section class="section" style="background:rgba(255,255,255,0.02);border-top:1px solid var(--glass-border);">
    <div class="container">
        <div class="section-header">
            <div class="section-label">💬 Reviews</div>
            <h2 class="section-title">What Our Customers Say</h2>
        </div>
        <div class="grid-3">
            <?php
            $testimonials = [
                ['name' => 'Aarav Sharma', 'role' => 'Regular Customer', 'rating' => 5, 'text' => 'Best biryani in Kolkata! Always arrives hot and fresh. Bluemoon is my go-to app every Friday night.', 'avatar' => 'A'],
                ['name' => 'Priya Dey', 'role' => 'Food Blogger', 'rating' => 5, 'text' => 'The glassmorphism UI is stunning and the food is even better. Paneer Tikka Pizza is an absolute must-try!', 'avatar' => 'P'],
                ['name' => 'Rohan Ghosh', 'role' => 'Office Lunch Orderer', 'rating' => 4, 'text' => 'Super fast delivery and great coupon codes. The chicken dum biryani has me hooked!', 'avatar' => 'R'],
            ];
            foreach ($testimonials as $t): ?>
                <div class="card" style="padding:28px;">
                    <div style="color:var(--warning);font-size:16px;margin-bottom:16px;">
                        <?= str_repeat('★', $t['rating']) ?>
                    </div>
                    <p style="font-size:15px;line-height:1.7;color:var(--text-secondary);margin-bottom:20px;">"
                        <?= $t['text'] ?>"
                    </p>
                    <div style="display:flex;align-items:center;gap:12px;">
                        <div class="avatar-circle">
                            <?= $t['avatar'] ?>
                        </div>
                        <div>
                            <div style="font-weight:600;font-size:14px;">
                                <?= $t['name'] ?>
                            </div>
                            <div style="font-size:12px;color:var(--text-muted);">
                                <?= $t['role'] ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- CTA BANNER -->
<section class="section">
    <div class="container">
        <div class="card"
            style="padding:clamp(32px, 5vw, 64px);text-align:center;background:linear-gradient(135deg,rgba(59,130,246,0.15),rgba(129,140,248,0.12));border-color:rgba(59,130,246,0.25);">
            <div style="font-size:48px;margin-bottom:16px;">🌙</div>
            <h2 style="font-family:var(--font-head);font-size:36px;font-weight:800;margin-bottom:12px;">Ready to Order?
            </h2>
            <p style="color:var(--text-secondary);font-size:16px;max-width:460px;margin:0 auto 32px;">Use code <strong
                    style="color:var(--primary);">WELCOME10</strong> on your first order for 10% off!</p>
            <div style="display:flex;gap:16px;justify-content:center;flex-wrap:wrap;">
                <a href="<?= BASE_URL ?>/menu.php" class="btn btn-primary btn-lg"><i class="fa fa-utensils"></i> Browse
                    Menu</a>
                <a href="<?= BASE_URL ?>/register.php" class="btn btn-outline btn-lg">Create Account</a>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/templates/footer.php'; ?>