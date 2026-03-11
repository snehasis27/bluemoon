<?php
require_once __DIR__ . '/config.php';
$db = getDB();
$cart = getCart();

// Enrich cart items from DB
$enriched = [];
$subtotal = 0;
foreach ($cart as $id => $item) {
    $p = $db->prepare('SELECT id,name,price,image,stock_status FROM products WHERE id=? AND is_active=1');
    $p->execute([$id]);
    $row = $p->fetch();
    if ($row) {
        $row['qty'] = $item['qty'];
        $row['total'] = $row['price'] * $item['qty'];
        $subtotal += $row['total'];
        $enriched[] = $row;
    }
}

// Coupon
$couponCode = $_SESSION['coupon_code'] ?? '';
$couponDiscount = $_SESSION['coupon_discount'] ?? 0;
$deliveryFee = ($subtotal > 0 && $subtotal >= FREE_DELIVERY_ABOVE) ? 0 : ($subtotal > 0 ? DELIVERY_FEE : 0);
$total = max(0, $subtotal - $couponDiscount + $deliveryFee);

$pageTitle = 'My Cart';
require_once __DIR__ . '/templates/header.php';
?>
<div class="page-header">
    <div class="container">
        <h1>🛒 My Cart</h1>
        <p>
            <?= count($enriched) ?> item
            <?= count($enriched) !== 1 ? 's' : '' ?> in your cart
        </p>
    </div>
</div>

<div class="container section-sm">
    <?php if (empty($enriched)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">🛒</div>
            <h3>Your cart is empty</h3>
            <p>Add some delicious items from our menu!</p>
            <a href="<?= BASE_URL ?>/menu.php" class="btn btn-primary btn-lg"><i class="fa fa-utensils"></i> Browse Menu</a>
        </div>
    <?php else: ?>
        <div style="display:grid;grid-template-columns:1fr 360px;gap:32px;align-items:start;">

            <!-- Cart Items -->
            <div>
                <div class="card" style="overflow:visible;">
                    <div style="padding:24px;border-bottom:1px solid var(--glass-border);">
                        <h2 style="font-family:var(--font-head);font-size:18px;font-weight:600;">Cart Items</h2>
                    </div>
                    <div id="cartItemsList">
                        <?php foreach ($enriched as $item):
                            $img = $item['image'] ? BASE_URL . '/assets/uploads/products/' . e($item['image']) : BASE_URL . '/assets/images/food/placeholder.svg';
                            ?>
                            <div class="cart-item" id="cartRow-<?= $item['id'] ?>"
                                style="display:flex;align-items:center;gap:16px;padding:20px 24px;border-bottom:1px solid rgba(255,255,255,0.04);">
                                <img src="<?= $img ?>" alt="<?= e($item['name']) ?>"
                                    style="width:72px;height:72px;border-radius:var(--radius-md);object-fit:cover;flex-shrink:0;">
                                <div style="flex:1;">
                                    <div style="font-weight:600;margin-bottom:4px;">
                                        <?= e($item['name']) ?>
                                    </div>
                                    <div style="font-size:13px;color:var(--text-muted);">
                                        <?= CURRENCY_SYMBOL . number_format($item['price'], 2) ?> each
                                    </div>
                                </div>
                                <div class="qty-wrap" data-cart-item-id="<?= $item['id'] ?>">
                                    <button class="qty-btn" data-dir="down"><i class="fa fa-minus"
                                            style="font-size:11px;"></i></button>
                                    <span class="qty-value">
                                        <?= $item['qty'] ?>
                                    </span>
                                    <input type="hidden" value="<?= $item['qty'] ?>">
                                    <button class="qty-btn" data-dir="up"><i class="fa fa-plus"
                                            style="font-size:11px;"></i></button>
                                </div>
                                <div style="text-align:right;min-width:80px;">
                                    <div style="font-weight:700;font-size:16px;" id="itemTotal-<?= $item['id'] ?>">
                                        <?= CURRENCY_SYMBOL . number_format($item['total'], 2) ?>
                                    </div>
                                    <button onclick="removeItem(<?= $item['id'] ?>)"
                                        style="font-size:12px;color:var(--danger);margin-top:4px;background:none;border:none;cursor:pointer;">
                                        <i class="fa fa-trash"></i> Remove
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="padding:16px 24px;display:flex;gap:12px;">
                        <a href="<?= BASE_URL ?>/menu.php" class="btn btn-outline btn-sm"><i class="fa fa-arrow-left"></i>
                            Continue Shopping</a>
                        <button onclick="clearCart()" class="btn btn-outline btn-sm" style="color:var(--danger);">
                            <i class="fa fa-trash"></i> Clear Cart
                        </button>
                    </div>
                </div>
            </div>

            <!-- Summary -->
            <div>
                <!-- Coupon -->
                <div class="card" style="padding:24px;margin-bottom:20px;">
                    <h3 style="font-size:16px;font-weight:600;margin-bottom:16px;">🎁 Coupon Code</h3>
                    <div style="display:flex;gap:10px;">
                        <input type="text" id="couponInput" class="form-control" placeholder="Enter coupon code"
                            value="<?= e($couponCode) ?>" style="flex:1;">
                        <button onclick="applyCoupon()" class="btn btn-primary" id="couponBtn">Apply</button>
                    </div>
                    <div id="couponMsg" style="font-size:13px;margin-top:8px;"></div>
                    <?php if ($couponCode): ?>
                        <div
                            style="display:flex;align-items:center;justify-content:space-between;margin-top:10px;padding:8px 12px;background:rgba(34,197,94,0.1);border-radius:8px;border:1px solid rgba(34,197,94,0.3);">
                            <span style="font-size:13px;color:var(--success);">✓ <strong>
                                    <?= e($couponCode) ?>
                                </strong> applied</span>
                            <button onclick="removeCoupon()"
                                style="font-size:12px;color:var(--danger);background:none;border:none;cursor:pointer;">Remove</button>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Order Summary -->
                <div class="card" style="padding:24px;">
                    <h3 style="font-size:16px;font-weight:600;margin-bottom:20px;">Order Summary</h3>
                    <div style="display:flex;flex-direction:column;gap:12px;">
                        <div style="display:flex;justify-content:space-between;font-size:14px;color:var(--text-secondary);">
                            <span>Subtotal</span>
                            <span id="summarySubtotal">
                                <?= CURRENCY_SYMBOL . number_format($subtotal, 2) ?>
                            </span>
                        </div>
                        <?php if ($couponDiscount > 0): ?>
                            <div style="display:flex;justify-content:space-between;font-size:14px;color:var(--success);">
                                <span>Coupon discount</span>
                                <span>–
                                    <?= CURRENCY_SYMBOL . number_format($couponDiscount, 2) ?>
                                </span>
                            </div>
                        <?php endif; ?>
                        <div style="display:flex;justify-content:space-between;font-size:14px;color:var(--text-secondary);">
                            <span>Delivery fee</span>
                            <span id="summaryDelivery">
                                <?= $deliveryFee > 0 ? CURRENCY_SYMBOL . number_format($deliveryFee, 2) : '<span style="color:var(--success);">FREE</span>' ?>
                            </span>
                        </div>
                        <?php if ($subtotal < FREE_DELIVERY_ABOVE && $subtotal > 0): ?>
                            <div style="font-size:12px;color:var(--text-muted);">
                                Add
                                <?= CURRENCY_SYMBOL . number_format(FREE_DELIVERY_ABOVE - $subtotal, 0) ?> more for free delivery
                            </div>
                        <?php endif; ?>
                        <div
                            style="border-top:1px solid var(--glass-border);padding-top:12px;display:flex;justify-content:space-between;font-size:20px;font-weight:700;">
                            <span>Total</span>
                            <span id="summaryTotal">
                                <?= CURRENCY_SYMBOL . number_format($total, 2) ?>
                            </span>
                        </div>
                    </div>
                    <a href="<?= BASE_URL ?>/checkout.php" class="btn btn-primary btn-block btn-lg"
                        style="margin-top:24px;">
                        <i class="fa fa-lock"></i> Proceed to Checkout
                    </a>
                    <p style="font-size:12px;color:var(--text-muted);text-align:center;margin-top:12px;">
                        <i class="fa fa-shield-halved"></i> Secure & encrypted checkout
                    </p>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    async function removeItem(productId) {
        const res = await apiFetch('/api/cart.php', { action: 'remove', product_id: productId });
        if (res.success) {
            document.getElementById('cartRow-' + productId)?.remove();
            updateCartBadge(res.cart_count);
            if (res.cart_count === 0) location.reload();
            else updateSummary(res);
        }
    }
    async function clearCart() {
        if (!confirm('Clear all items from cart?')) return;
        const res = await apiFetch('/api/cart.php', { action: 'clear' });
        if (res.success) { updateCartBadge(0); location.reload(); }
    }
    async function applyCoupon() {
        const code = document.getElementById('couponInput').value.trim();
        if (!code) return;
        const res = await apiFetch('/api/coupons.php', { action: 'apply', code });
        const msg = document.getElementById('couponMsg');
        if (res.success) {
            msg.innerHTML = `<span style="color:var(--success);">✓ ${res.message}</span>`;
            setTimeout(() => location.reload(), 800);
        } else {
            msg.innerHTML = `<span style="color:var(--danger);">✗ ${res.message}</span>`;
        }
    }
    async function removeCoupon() {
        await apiFetch('/api/coupons.php', { action: 'remove' });
        location.reload();
    }
    function updateSummary(res) {
        if (res.subtotal !== undefined) {
            document.getElementById('summarySubtotal').textContent = '₹' + parseFloat(res.subtotal).toFixed(2);
            document.getElementById('summaryTotal').textContent = '₹' + parseFloat(res.total).toFixed(2);
        }
    }
    // Override qty update to refresh totals
    window.refreshCartTotals = function (res) { updateSummary(res); };
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>