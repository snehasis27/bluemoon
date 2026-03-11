<?php
require_once __DIR__ . '/config.php';
requireLogin();
$db = getDB();
$user = currentUser();
$oid = (int) ($_GET['id'] ?? 0);
if (!$oid)
    redirect(BASE_URL . '/order_history.php');

// Fetch order (ensure it belongs to this user)
$stmt = $db->prepare('SELECT o.*, u.name AS customer_name, u.phone AS customer_phone FROM orders o JOIN users u ON u.id=o.user_id WHERE o.id=? AND o.user_id=? LIMIT 1');
$stmt->execute([$oid, $user['id']]);
$order = $stmt->fetch();
if (!$order) {
    setFlash('error', 'Order not found.');
    redirect(BASE_URL . '/order_history.php');
}

// Fetch items
$items = $db->prepare('SELECT oi.*, p.image FROM order_items oi LEFT JOIN products p ON p.id=oi.product_id WHERE oi.order_id=?');
$items->execute([$oid]);
$items = $items->fetchAll();

// Status steps
$statuses = ['placed', 'confirmed', 'preparing', 'out_for_delivery', 'delivered'];
$icons = ['🍽️', '✅', '👨‍🍳', '🛵', '🎉'];
$labels = ['Order Placed', 'Confirmed', 'Preparing', 'Out for Delivery', 'Delivered'];
$currentIdx = array_search($order['order_status'], $statuses);

$pageTitle = 'Order #' . $oid . ' – Tracking';
require_once __DIR__ . '/templates/header.php';
?>
<div class="page-header">
    <div class="container">
        <h1>📦 Track Order #
            <?= $oid ?>
        </h1>
        <p>Placed
            <?= timeAgo($order['created_at']) ?> · <span class="status status-<?= e($order['order_status']) ?>">
                <?= ucwords(str_replace('_', ' ', $order['order_status'])) ?>
            </span>
        </p>
    </div>
</div>

<div class="container section-sm">
    <?php if ($order['order_status'] === 'cancelled'): ?>
        <div class="alert alert-danger" style="margin-bottom:24px;"><i class="fa fa-ban"></i> This order was cancelled.
        </div>
    <?php endif; ?>

    <!-- Tracker -->
    <div class="card" style="padding:40px;margin-bottom:32px;">
        <div class="order-tracker" id="orderTracker">
            <?php foreach ($statuses as $i => $s):
                $done = $currentIdx !== false && $i < $currentIdx;
                $active = $currentIdx !== false && $i === $currentIdx;
                $cls = $done ? 'done' : ($active ? 'active' : '');
                ?>
                <div class="tracker-step <?= $cls ?>">
                    <div class="tracker-icon">
                        <?= $done ? '✅' : $icons[$i] ?>
                    </div>
                    <div class="tracker-label">
                        <?= $labels[$i] ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if (!in_array($order['order_status'], ['delivered', 'cancelled'])): ?>
            <p style="text-align:center;font-size:13px;color:var(--text-muted);margin-top:20px;">
                <i class="fa fa-rotate fa-spin"></i> Updating automatically every 10 seconds…
            </p>
        <?php endif; ?>
    </div>

    <div class="grid-2" style="gap:24px;">
        <!-- Order items -->
        <div class="card" style="padding:24px;">
            <h3 style="font-family:var(--font-head);font-size:16px;font-weight:600;margin-bottom:16px;">Items Ordered
            </h3>
            <?php foreach ($items as $it):
                $img = $it['image'] ? BASE_URL . '/assets/uploads/products/' . e($it['image']) : BASE_URL . '/assets/images/food/placeholder.svg';
                ?>
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;">
                    <img src="<?= $img ?>" alt="<?= e($it['product_name']) ?>"
                        style="width:50px;height:50px;border-radius:10px;object-fit:cover;">
                    <div style="flex:1;">
                        <div style="font-size:14px;font-weight:600;">
                            <?= e($it['product_name']) ?>
                        </div>
                        <div style="font-size:12px;color:var(--text-muted);">
                            <?= CURRENCY_SYMBOL . number_format($it['unit_price'], 2) ?> ×
                            <?= $it['quantity'] ?>
                        </div>
                    </div>
                    <div style="font-weight:700;">
                        <?= CURRENCY_SYMBOL . number_format($it['total_price'], 2) ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <div
                style="border-top:1px solid var(--glass-border);padding-top:16px;margin-top:8px;font-size:14px;display:flex;flex-direction:column;gap:8px;">
                <div style="display:flex;justify-content:space-between;color:var(--text-secondary);">
                    <span>Subtotal</span><span>
                        <?= CURRENCY_SYMBOL . number_format($order['subtotal'], 2) ?>
                    </span></div>
                <?php if ($order['discount_amount'] > 0): ?>
                    <div style="display:flex;justify-content:space-between;color:var(--success);">
                        <span>Discount</span><span>–
                            <?= CURRENCY_SYMBOL . number_format($order['discount_amount'], 2) ?>
                        </span></div>
                <?php endif; ?>
                <div style="display:flex;justify-content:space-between;color:var(--text-secondary);">
                    <span>Delivery</span><span>
                        <?= $order['delivery_fee'] > 0 ? CURRENCY_SYMBOL . number_format($order['delivery_fee'], 2) : 'FREE' ?>
                    </span></div>
                <div
                    style="display:flex;justify-content:space-between;font-size:18px;font-weight:700;border-top:1px solid var(--glass-border);padding-top:10px;">
                    <span>Total</span><span>
                        <?= CURRENCY_SYMBOL . number_format($order['total_amount'], 2) ?>
                    </span></div>
            </div>
        </div>

        <!-- Delivery info -->
        <div style="display:flex;flex-direction:column;gap:20px;">
            <div class="card" style="padding:24px;">
                <h3 style="font-family:var(--font-head);font-size:16px;font-weight:600;margin-bottom:14px;">📍 Delivery
                    Address</h3>
                <p style="font-size:14px;color:var(--text-secondary);line-height:1.7;">
                    <?= nl2br(e($order['delivery_address'])) ?>
                </p>
            </div>
            <div class="card" style="padding:24px;">
                <h3 style="font-family:var(--font-head);font-size:16px;font-weight:600;margin-bottom:14px;">💳 Payment
                </h3>
                <div style="font-size:14px;color:var(--text-secondary);">Method: <strong>
                        <?= strtoupper(e($order['payment_method'])) ?>
                    </strong></div>
                <div style="font-size:14px;color:var(--text-secondary);margin-top:6px;">Status: <span
                        class="status status-<?= e($order['payment_status']) ?>">
                        <?= ucfirst(e($order['payment_status'])) ?>
                    </span></div>
            </div>
            <div style="display:flex;gap:12px;">
                <a href="<?= BASE_URL ?>/invoice.php?id=<?= $oid ?>" class="btn btn-outline" target="_blank">
                    <i class="fa fa-file-invoice"></i> Invoice
                </a>
                <a href="<?= BASE_URL ?>/order_history.php" class="btn btn-outline">
                    <i class="fa fa-list"></i> All Orders
                </a>
            </div>
        </div>
    </div>
</div>

<?php if (!in_array($order['order_status'], ['delivered', 'cancelled'])): ?>
    <script>
        const ORDER_ID = <?= $oid ?>;
        const statusClasses = ['placed', 'confirmed', 'preparing', 'out_for_delivery', 'delivered'];
        let pollTimer = startOrderPolling(ORDER_ID, function (status) {
            const idx = statusClasses.indexOf(status);
            document.querySelectorAll('.tracker-step').forEach((step, i) => {
                step.classList.remove('done', 'active');
                const icon = step.querySelector('.tracker-icon');
                if (i < idx) { step.classList.add('done'); icon.textContent = '✅'; }
                if (i === idx) { step.classList.add('active'); }
            });
            if (status === 'delivered' || status === 'cancelled') clearInterval(pollTimer);
        }, 10000);
    </script>
<?php endif; ?>
<?php require_once __DIR__ . '/templates/footer.php'; ?>