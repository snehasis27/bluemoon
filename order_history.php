<?php
require_once __DIR__ . '/config.php';
requireLogin();
$db = getDB();
$user = currentUser();

$stmt = $db->prepare('SELECT o.* FROM orders o WHERE o.user_id=? ORDER BY o.created_at DESC');
$stmt->execute([$user['id']]);
$orders = $stmt->fetchAll();

$pageTitle = 'Order History';
require_once __DIR__ . '/templates/header.php';
?>
<div class="page-header">
    <div class="container">
        <h1>📋 My Orders</h1>
        <p>
            <?= count($orders) ?> order
            <?= count($orders) !== 1 ? 's' : '' ?> placed
        </p>
    </div>
</div>

<div class="container section-sm">
    <?php if (empty($orders)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">📋</div>
            <h3>No orders yet</h3>
            <p>You haven't placed any orders. Let's fix that!</p>
            <a href="<?= BASE_URL ?>/menu.php" class="btn btn-primary btn-lg"><i class="fa fa-utensils"></i> Order Now</a>
        </div>
    <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:16px;">
            <?php foreach ($orders as $o): ?>
                <div class="card" style="padding:0;overflow:hidden;">
                    <div
                        style="display:flex;align-items:center;justify-content:space-between;padding:20px 24px;border-bottom:1px solid rgba(255,255,255,0.05);">
                        <div>
                            <div style="font-family:var(--font-head);font-size:16px;font-weight:600;">Order #
                                <?= $o['id'] ?>
                            </div>
                            <div style="font-size:13px;color:var(--text-muted);margin-top:2px;">
                                <?= date('d M Y, h:i A', strtotime($o['created_at'])) ?>
                            </div>
                        </div>
                        <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
                            <span class="status status-<?= e($o['order_status']) ?>">
                                <?= ucwords(str_replace('_', ' ', $o['order_status'])) ?>
                            </span>
                            <span style="font-size:18px;font-weight:700;">
                                <?= CURRENCY_SYMBOL . number_format($o['total_amount'], 2) ?>
                            </span>
                        </div>
                    </div>
                    <div
                        style="padding:16px 24px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
                        <div style="font-size:13px;color:var(--text-muted);">
                            Payment: <span class="status status-<?= e($o['payment_status']) ?>" style="font-size:11px;">
                                <?= ucfirst(e($o['payment_status'])) ?>
                            </span>
                            &nbsp;·&nbsp; Method:
                            <?= strtoupper(e($o['payment_method'])) ?>
                        </div>
                        <div style="display:flex;gap:10px;">
                            <a href="<?= BASE_URL ?>/order_tracking.php?id=<?= $o['id'] ?>" class="btn btn-outline btn-sm">
                                <i class="fa fa-location-dot"></i> Track
                            </a>
                            <a href="<?= BASE_URL ?>/invoice.php?id=<?= $o['id'] ?>" target="_blank"
                                class="btn btn-outline btn-sm">
                                <i class="fa fa-file-invoice"></i> Invoice
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/templates/footer.php'; ?>