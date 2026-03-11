<?php
require_once __DIR__ . '/config.php';
$db = getDB();
$pid = (int) ($_GET['id'] ?? 0);
if (!$pid)
    redirect(BASE_URL . '/menu.php');

$stmt = $db->prepare('SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON c.id=p.category_id WHERE p.id=? AND p.is_active=1 LIMIT 1');
$stmt->execute([$pid]);
$product = $stmt->fetch();
if (!$product) {
    setFlash('error', 'Product not found.');
    redirect(BASE_URL . '/menu.php');
}

// Reviews (approved only)
$reviews = $db->prepare('SELECT r.*, u.name AS user_name FROM reviews r JOIN users u ON u.id=r.user_id WHERE r.product_id=? AND r.status=? ORDER BY r.created_at DESC');
$reviews->execute([$pid, 'approved']);
$reviews = $reviews->fetchAll();

// User already reviewed?
$userReviewed = false;
if (isLoggedIn()) {
    $chk = $db->prepare('SELECT id FROM reviews WHERE product_id=? AND user_id=? LIMIT 1');
    $chk->execute([$pid, currentUser()['id']]);
    $userReviewed = (bool) $chk->fetch();
}

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isLoggedIn() && verify_csrf()) {
    $rating = (int) ($_POST['rating'] ?? 5);
    $comment = trim($_POST['comment'] ?? '');
    if ($rating >= 1 && $rating <= 5 && !$userReviewed) {
        $db->prepare('INSERT INTO reviews (user_id,product_id,rating,comment,status) VALUES (?,?,?,?,?)')
            ->execute([currentUser()['id'], $pid, $rating, $comment, 'pending']);
        setFlash('success', 'Review submitted! It will appear after approval. Thank you!');
        redirect(BASE_URL . '/product_detail.php?id=' . $pid);
    }
}

$img = $product['image'] ? BASE_URL . '/assets/uploads/products/' . e($product['image']) : BASE_URL . '/assets/images/food/placeholder.svg';
$stars = str_repeat('★', round($product['rating'])) . str_repeat('☆', 5 - round($product['rating']));
$vegBadge = $product['is_veg'] ? '<span class="badge badge-veg">🟢 Veg</span>' : '<span class="badge badge-nonveg">🔴 Non-Veg</span>';

$pageTitle = $product['name'];
$metaDescription = substr(strip_tags($product['description'] ?? ''), 0, 155);
require_once __DIR__ . '/templates/header.php';
?>
<div class="container section-sm">
    <a href="<?= BASE_URL ?>/menu.php"
        style="font-size:13px;color:var(--text-muted);display:inline-flex;align-items:center;gap:6px;margin-bottom:24px;">
        <i class="fa fa-arrow-left"></i> Back to Menu
    </a>

    <!-- Product Detail -->
    <div class="grid-2" style="gap:48px;align-items:start;margin-bottom:48px;">
        <!-- Image -->
        <div>
            <div style="border-radius:var(--radius-lg);overflow:hidden;border:1px solid var(--glass-border);">
                <img src="<?= $img ?>" alt="<?= e($product['name']) ?>"
                    style="width:100%;aspect-ratio:1;object-fit:cover;">
            </div>
        </div>
        <!-- Info -->
        <div>
            <div style="display:flex;gap:8px;margin-bottom:16px;">
                <?= $vegBadge ?>
                <span class="badge badge-new">📦
                    <?= e($product['category_name']) ?>
                </span>
                <?php if ($product['stock_status'] === 'out_of_stock'): ?><span class="badge"
                        style="background:rgba(239,68,68,0.2);color:#f87171;border-color:rgba(239,68,68,0.4);">Out of
                        Stock</span>
                <?php endif; ?>
            </div>
            <h1 style="font-family:var(--font-head);font-size:32px;font-weight:700;margin-bottom:12px;">
                <?= e($product['name']) ?>
            </h1>
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
                <span style="color:var(--warning);font-size:18px;">
                    <?= $stars ?>
                </span>
                <span style="color:var(--text-muted);font-size:14px;">
                    <?= number_format($product['rating'], 1) ?> (
                    <?= $product['rating_count'] ?> reviews)
                </span>
            </div>
            <p style="color:var(--text-secondary);line-height:1.7;font-size:15px;margin-bottom:24px;">
                <?= e($product['description']) ?>
            </p>
            <div style="margin-bottom:28px;">
                <span style="font-size:36px;font-weight:800;">
                    <?= CURRENCY_SYMBOL . number_format($product['price'], 2) ?>
                </span>
                <?php if ($product['original_price'] > 0): ?>
                    <span style="font-size:18px;color:var(--text-muted);text-decoration:line-through;margin-left:10px;">
                        <?= CURRENCY_SYMBOL . number_format($product['original_price'], 2) ?>
                    </span>
                    <span
                        style="background:rgba(34,197,94,0.15);color:var(--success);font-size:14px;font-weight:600;padding:4px 10px;border-radius:50px;margin-left:8px;">
                        <?= round((1 - $product['price'] / $product['original_price']) * 100) ?>% OFF
                    </span>
                <?php endif; ?>
            </div>
            <?php if ($product['stock_status'] !== 'out_of_stock'): ?>
                <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
                    <button class="add-to-cart-btn" style="padding:14px 28px;font-size:16px;"
                        data-product-id="<?= $product['id'] ?>" data-product-name="<?= e($product['name']) ?>"
                        data-product-price="<?= $product['price'] ?>">
                        <i class="fa fa-basket-shopping"></i> Add to Cart
                    </button>
                    <button class="buy-now-btn buy-now-btn-lg" data-product-id="<?= $product['id'] ?>"
                        data-product-name="<?= e($product['name']) ?>">
                        <i class="fa fa-bolt"></i> Buy Now
                    </button>
                    <a href="<?= BASE_URL ?>/menu.php" class="btn btn-outline">Browse More</a>
                </div>
            <?php else: ?>
                <div class="alert alert-warning"><i class="fa fa-triangle-exclamation"></i> Currently out of stock.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Reviews -->
    <div style="max-width:760px;">
        <h2 style="font-family:var(--font-head);font-size:24px;font-weight:700;margin-bottom:24px;">Customer Reviews
        </h2>

        <?php if (empty($reviews)): ?>
            <div class="alert alert-info">No reviews yet. Be the first to review!</div>
        <?php else: ?>
            <?php foreach ($reviews as $r):
                $rStars = str_repeat('★', $r['rating']) . str_repeat('☆', 5 - $r['rating']);
                ?>
                <div class="review-card">
                    <div class="review-header">
                        <div class="reviewer-info">
                            <div class="reviewer-avatar">
                                <?= strtoupper(substr($r['user_name'], 0, 1)) ?>
                            </div>
                            <div>
                                <div class="reviewer-name">
                                    <?= e($r['user_name']) ?>
                                </div>
                                <div class="reviewer-date">
                                    <?= date('d M Y', strtotime($r['created_at'])) ?>
                                </div>
                            </div>
                        </div>
                        <div class="review-rating">
                            <?= $rStars ?>
                        </div>
                    </div>
                    <?php if ($r['comment']): ?>
                        <div class="review-comment">
                            <?= e($r['comment']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; endif; ?>

        <!-- Add Review -->
        <?php if (isLoggedIn() && !$userReviewed && $product['stock_status'] !== 'out_of_stock'): ?>
            <div class="card" style="padding:28px;margin-top:24px;">
                <h3 style="font-family:var(--font-head);font-size:18px;font-weight:600;margin-bottom:20px;">Write a Review
                </h3>
                <form method="POST">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label class="form-label">Your Rating</label>
                        <div class="star-rating">
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                <input type="radio" name="rating" id="star<?= $i ?>" value="<?= $i ?>">
                                <label for="star<?= $i ?>">★</label>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Comment <span style="color:var(--text-muted);">(optional)</span></label>
                        <textarea name="comment" class="form-control" rows="4" placeholder="Share your thoughts…"
                            style="resize:vertical;"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fa fa-paper-plane"></i> Submit Review</button>
                </form>
            </div>
        <?php elseif (!isLoggedIn()): ?>
            <div class="alert alert-info" style="margin-top:20px;">
                <a href="<?= BASE_URL ?>/login.php" style="color:var(--primary);font-weight:600;">Log in</a> to write a
                review.
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/templates/footer.php'; ?>