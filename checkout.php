<?php
require_once __DIR__ . '/config.php';
requireLogin();
$db = getDB();
$user = currentUser();
$cart = getCart();

if (empty($cart))
    redirect(BASE_URL . '/cart.php');

// Enrich cart
$items = [];
$subtotal = 0;
foreach ($cart as $id => $item) {
    $p = $db->prepare('SELECT id,name,price,image FROM products WHERE id=? AND is_active=1');
    $p->execute([$id]);
    $row = $p->fetch();
    if ($row) {
        $row['qty'] = $item['qty'];
        $row['total'] = $row['price'] * $row['qty'];
        $subtotal += $row['total'];
        $items[] = $row;
    }
}

$couponDiscount = $_SESSION['coupon_discount'] ?? 0;
$couponCode = $_SESSION['coupon_code'] ?? '';
$deliveryFee = $subtotal >= FREE_DELIVERY_ABOVE ? 0 : DELIVERY_FEE;
$total = max(0, $subtotal - $couponDiscount + $deliveryFee);

// Addresses
$addresses = $db->prepare('SELECT * FROM addresses WHERE user_id=? ORDER BY is_default DESC');
$addresses->execute([$user['id']]);
$addresses = $addresses->fetchAll();

// Merchant UPI ID
define('MERCHANT_UPI', '9007196036@fam');
define('MERCHANT_NAME', 'Bluemoon Restaurant');

// Handle order placement
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        $payMethod = $_POST['payment_method'] ?? 'cod';
        $addressId = (int) ($_POST['address_id'] ?? 0);
        $newName = trim($_POST['new_name'] ?? '');
        $newPhone = trim($_POST['new_phone'] ?? '');
        $newLine1 = trim($_POST['new_line1'] ?? '');
        $newCity = trim($_POST['new_city'] ?? '');
        $newPincode = trim($_POST['new_pincode'] ?? '');

        // Payment-method-specific data
        $payNotes = '';
        if ($payMethod === 'upi') {
            $payNotes = 'QR Code Payment to ' . MERCHANT_UPI;
        } elseif ($payMethod === 'otherupi') {
            $customerUpi = trim($_POST['customer_upi_id'] ?? '');
            if ($customerUpi) {
                if (!preg_match('/^[a-zA-Z0-9._\-]+@[a-zA-Z0-9]+$/', $customerUpi)) {
                    $error = 'Invalid UPI ID format. Example: yourname@upi';
                }
                $payNotes = 'Other UPI App | Customer UPI: ' . $customerUpi;
            } else {
                $payNotes = 'Other UPI App payment';
            }
        } elseif ($payMethod === 'netbanking') {
            $bank = trim($_POST['netbanking_bank'] ?? '');
            $payNotes = 'Net Banking' . ($bank ? ' | Bank: ' . $bank : '');
        } elseif ($payMethod === 'card') {
            $cardNumber = preg_replace('/\D/', '', $_POST['card_number'] ?? '');
            $cardName = trim($_POST['card_name'] ?? '');
            $cardExpiry = trim($_POST['card_expiry'] ?? '');
            $cardCvv = trim($_POST['card_cvv'] ?? '');
            if (strlen($cardNumber) < 13 || strlen($cardNumber) > 19) {
                $error = 'Please enter a valid card number.';
            } elseif (!$cardName) {
                $error = 'Please enter the cardholder name.';
            } elseif (!preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $cardExpiry)) {
                $error = 'Please enter a valid expiry date (MM/YY).';
            } elseif (strlen($cardCvv) < 3) {
                $error = 'Please enter a valid CVV.';
            }
            if (!$error) {
                $last4 = substr($cardNumber, -4);
                $payNotes = 'Card ending ' . $last4 . ' | Name: ' . $cardName . ' | Expiry: ' . $cardExpiry;
            }
        }

        if (!$error) {
            // Build delivery address string
            $delivAddr = '';
            if ($addressId) {
                $a = $db->prepare('SELECT * FROM addresses WHERE id=? AND user_id=?');
                $a->execute([$addressId, $user['id']]);
                $a = $a->fetch();
                if ($a)
                    $delivAddr = "{$a['name']}, {$a['phone']}\n{$a['line1']}" . ($a['line2'] ? ", {$a['line2']}" : '') . "\n{$a['city']}, {$a['state']} – {$a['pincode']}";
            } elseif ($newLine1 && $newCity && $newPincode) {
                $delivAddr = "$newName, $newPhone\n$newLine1\n$newCity – $newPincode";
                $save = $_POST['save_address'] ?? '';
                if ($save) {
                    $db->prepare('INSERT INTO addresses (user_id,label,name,phone,line1,city,pincode) VALUES (?,?,?,?,?,?,?)')
                        ->execute([$user['id'], 'Home', $newName, $newPhone, $newLine1, $newCity, $newPincode]);
                    $addressId = $db->lastInsertId();
                }
            }

            if (!$delivAddr) {
                $error = 'Please select or enter a delivery address.';
            } else {
                $db->beginTransaction();
                try {
                    // Create order
                    $payStatus = ($payMethod === 'cod') ? 'pending' : 'pending';
                    $db->prepare('INSERT INTO orders (user_id,address_id,delivery_address,subtotal,delivery_fee,discount_amount,total_amount,coupon_code,payment_method,payment_status,order_status) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
                        ->execute([$user['id'], $addressId ?: null, $delivAddr, $subtotal, $deliveryFee, $couponDiscount, $total, $couponCode, $payMethod, $payStatus, 'placed']);
                    $orderId = $db->lastInsertId();
                    // Order items
                    foreach ($items as $item) {
                        $db->prepare('INSERT INTO order_items (order_id,product_id,product_name,quantity,unit_price,total_price) VALUES (?,?,?,?,?,?)')
                            ->execute([$orderId, $item['id'], $item['name'], $item['qty'], $item['price'], $item['total']]);
                    }
                    // Payment record
                    $db->prepare('INSERT INTO payments (order_id,method,amount,status,notes) VALUES (?,?,?,?,?)')
                        ->execute([$orderId, $payMethod, $total, 'pending', $payNotes]);
                    // Status history
                    $db->prepare('INSERT INTO order_status_history (order_id,status,note,changed_by) VALUES (?,?,?,?)')
                        ->execute([$orderId, 'placed', 'Order placed by customer', $user['id']]);
                    // Screenshot upload
                    if ($payMethod === 'screenshot' && isset($_FILES['screenshot']) && $_FILES['screenshot']['error'] === 0) {
                        $ext = strtolower(pathinfo($_FILES['screenshot']['name'], PATHINFO_EXTENSION));
                        $name = 'pay_' . $orderId . '_' . time() . '.' . $ext;
                        move_uploaded_file($_FILES['screenshot']['tmp_name'], SCREENSHOT_PATH . $name);
                        $db->prepare('UPDATE payments SET screenshot=? WHERE order_id=? ORDER BY id DESC LIMIT 1')
                            ->execute([$name, $orderId]);
                    }
                    $db->commit();
                    unset($_SESSION['cart'], $_SESSION['coupon_code'], $_SESSION['coupon_discount']);
                    setFlash('success', 'Order #' . $orderId . ' placed successfully! 🎉');
                    redirect(BASE_URL . '/order_tracking.php?id=' . $orderId);
                } catch (Exception $e) {
                    $db->rollBack();
                    $error = 'Failed to place order. Please try again.';
                }
            }
        }
    }
}

// Build UPI payment string for QR code (generated client-side)
$upiString = 'upi://pay?pa=' . urlencode(MERCHANT_UPI) . '&pn=' . urlencode(MERCHANT_NAME) . '&am=' . number_format($total, 2, '.', '') . '&cu=INR&tn=' . urlencode('Bluemoon Food Order');

$pageTitle = 'Checkout';
$metaDescription = 'Complete your Bluemoon food order securely.';
require_once __DIR__ . '/templates/header.php';
?>
<style>
    /* ── Amazon-style Payment Section ─────────────────────────── */

    .amz-payment-box {
        background: var(--glass-bg, rgba(30, 30, 50, 0.6));
        border: 1px solid var(--glass-border);
        border-radius: var(--radius-md);
        overflow: hidden;
    }

    .amz-section-title {
        font-size: 13px;
        font-weight: 700;
        color: var(--text-secondary);
        letter-spacing: 0.05em;
        text-transform: uppercase;
        padding: 12px 18px;
        border-bottom: 1px solid var(--glass-border);
        background: rgba(255, 255, 255, 0.03);
    }

    /* Offer / balance row */
    .amz-balance-row {
        padding: 16px 18px;
        border-bottom: 1px solid var(--glass-border);
    }

    .amz-balance-label {
        font-size: 13px;
        color: var(--text-muted);
        margin-bottom: 4px;
    }

    .amz-balance-unavail {
        font-size: 13px;
        color: #f87171;
        margin-bottom: 10px;
    }

    .amz-offer-row {
        display: flex;
        gap: 8px;
        align-items: center;
    }

    .amz-offer-row input {
        flex: 1;
        padding: 7px 12px;
        border: 1px solid var(--glass-border);
        border-radius: 6px;
        background: rgba(255, 255, 255, 0.06);
        color: var(--text-primary);
        font-size: 13px;
        outline: none;
    }

    .amz-offer-row input:focus {
        border-color: var(--primary);
    }

    .amz-apply-btn {
        padding: 7px 18px;
        border: 1px solid var(--glass-border);
        border-radius: 6px;
        background: rgba(255, 255, 255, 0.08);
        color: var(--text-primary);
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        white-space: nowrap;
        transition: background 0.2s;
    }

    .amz-apply-btn:hover {
        background: rgba(255, 255, 255, 0.14);
    }

    /* Payment method list */
    .amz-methods-title {
        font-size: 14px;
        font-weight: 600;
        padding: 14px 18px 8px;
        color: var(--text-primary);
        border-bottom: 1px solid var(--glass-border);
    }

    .amz-method-item {
        border-bottom: 1px solid var(--glass-border);
        transition: background 0.15s;
    }

    .amz-method-item:last-child {
        border-bottom: none;
    }

    .amz-method-row {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 13px 18px;
        cursor: pointer;
    }

    .amz-method-row input[type=radio] {
        accent-color: #f90;
        width: 16px;
        height: 16px;
        flex-shrink: 0;
        cursor: pointer;
    }

    .amz-method-label {
        font-size: 14px;
        color: var(--text-primary);
        flex: 1;
    }

    .amz-method-icons {
        display: flex;
        gap: 5px;
        align-items: center;
        flex-wrap: wrap;
    }

    .amz-method-icons img,
    .amz-method-icons span {
        height: 22px;
        border-radius: 3px;
        font-size: 20px;
        line-height: 1;
    }

    .amz-info-note {
        font-size: 12px;
        color: var(--text-muted);
        background: rgba(251, 191, 36, 0.07);
        border: 1px solid rgba(251, 191, 36, 0.18);
        border-radius: 6px;
        padding: 8px 12px;
        margin: 0 18px 12px;
        display: flex;
        align-items: flex-start;
        gap: 7px;
    }

    .amz-info-note i {
        color: #fbbf24;
        margin-top: 1px;
        flex-shrink: 0;
    }

    /* Sub-panels inside a method */
    .amz-sub-panel {
        display: none;
        padding: 0 18px 16px 46px;
        animation: fadeInUp 0.2s ease;
    }

    .amz-sub-panel.visible {
        display: block;
    }

    /* Payment label */
    .payment-label {
        font-weight: 600;
        font-size: 14px;
    }

    /* ── UPI Payment Confirmation Modal ────────────────────────── */
    .upi-pay-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.75);
        backdrop-filter: blur(6px);
        z-index: 9999;
        align-items: center;
        justify-content: center;
    }

    .upi-pay-overlay.active {
        display: flex;
    }

    .upi-pay-modal {
        background: linear-gradient(145deg, #1a1a2e, #16213e);
        border: 1px solid rgba(99, 102, 241, 0.3);
        border-radius: 20px;
        padding: 36px 32px;
        max-width: 420px;
        width: 90%;
        text-align: center;
        box-shadow: 0 32px 80px rgba(0, 0, 0, 0.6);
        animation: fadeInUp 0.35s ease;
    }

    .upi-pay-modal .upi-modal-icon {
        font-size: 52px;
        margin-bottom: 12px;
        animation: pulse 1.5s infinite;
    }

    @keyframes pulse {

        0%,
        100% {
            transform: scale(1);
        }

        50% {
            transform: scale(1.08);
        }
    }

    .upi-pay-modal h3 {
        font-size: 20px;
        font-weight: 700;
        margin-bottom: 6px;
        color: #fff;
    }

    .upi-pay-modal .modal-amount {
        font-size: 36px;
        font-weight: 800;
        background: linear-gradient(135deg, #4ade80, #22c55e);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        margin: 10px 0;
    }

    .upi-pay-modal .modal-to {
        font-size: 13px;
        color: rgba(255, 255, 255, 0.55);
        margin-bottom: 6px;
    }

    .upi-pay-modal .modal-upi-id {
        font-size: 15px;
        font-weight: 700;
        color: #a5b4fc;
        margin-bottom: 24px;
        font-family: 'Courier New', monospace;
    }

    .upi-pay-modal .modal-steps {
        background: rgba(255, 255, 255, 0.04);
        border-radius: 12px;
        padding: 14px 18px;
        margin-bottom: 24px;
        text-align: left;
        font-size: 13px;
        color: rgba(255, 255, 255, 0.65);
        line-height: 1.9;
    }

    .upi-pay-modal .modal-steps b {
        color: #fff;
    }

    .btn-upi-confirm {
        display: block;
        width: 100%;
        padding: 15px;
        background: linear-gradient(135deg, #22c55e, #16a34a);
        color: #fff;
        border: none;
        border-radius: 12px;
        font-size: 16px;
        font-weight: 700;
        cursor: pointer;
        margin-bottom: 12px;
        transition: transform 0.15s, box-shadow 0.15s;
        box-shadow: 0 8px 24px rgba(34, 197, 94, 0.35);
    }

    .btn-upi-confirm:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 32px rgba(34, 197, 94, 0.5);
    }

    .btn-upi-cancel {
        background: none;
        border: 1px solid rgba(255, 255, 255, 0.15);
        color: rgba(255, 255, 255, 0.55);
        border-radius: 10px;
        padding: 10px;
        width: 100%;
        font-size: 14px;
        cursor: pointer;
        transition: background 0.2s;
    }

    .btn-upi-cancel:hover {
        background: rgba(255, 255, 255, 0.06);
    }

    .modal-tick-anim {
        display: none;
        font-size: 60px;
        animation: popIn 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    }

    @keyframes popIn {
        from {
            transform: scale(0);
            opacity: 0;
        }

        to {
            transform: scale(1);
            opacity: 1;
        }
    }

    /* ── UPI Panel ──────────────────────────────────────────────── */
    .upi-panel {
        margin-top: 20px;
        padding: 24px;
        background: rgba(99, 102, 241, 0.08);
        border: 1px solid rgba(99, 102, 241, 0.25);
        border-radius: var(--radius-md);
        animation: fadeInUp 0.3s ease;
    }

    .upi-qr-row {
        display: flex;
        gap: 28px;
        align-items: flex-start;
        flex-wrap: wrap;
    }

    .qr-box {
        flex-shrink: 0;
        background: #fff;
        border-radius: 16px;
        padding: 10px;
        box-shadow: 0 4px 24px rgba(0, 0, 0, 0.4);
        display: flex;
        align-items: center;
        justify-content: center;
        width: 140px;
        height: 140px;
    }

    .qr-box img {
        width: 120px;
        height: 120px;
        display: block;
    }

    .upi-info {
        flex: 1;
        min-width: 200px;
    }

    .upi-info h4 {
        font-size: 15px;
        font-weight: 700;
        margin-bottom: 14px;
        background: linear-gradient(135deg, #818cf8, #6366f1);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .upi-steps {
        list-style: none;
        padding: 0;
        margin: 0 0 14px;
    }

    .upi-steps li {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 13px;
        color: var(--text-secondary);
        margin-bottom: 8px;
    }

    .upi-steps li .step-num {
        width: 22px;
        height: 22px;
        border-radius: 50%;
        background: linear-gradient(135deg, #818cf8, #6366f1);
        color: #fff;
        font-size: 11px;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .upi-id-box {
        display: flex;
        align-items: center;
        gap: 8px;
        background: rgba(255, 255, 255, 0.06);
        border: 1px solid rgba(99, 102, 241, 0.35);
        border-radius: 10px;
        padding: 10px 14px;
        margin-bottom: 16px;
    }

    .upi-id-text {
        font-size: 14px;
        font-weight: 700;
        letter-spacing: 0.04em;
        color: #a5b4fc;
        flex: 1;
        font-family: 'Courier New', monospace;
    }

    .btn-copy {
        background: linear-gradient(135deg, #818cf8, #6366f1);
        color: #fff;
        border: none;
        border-radius: 8px;
        padding: 6px 14px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        flex-shrink: 0;
    }

    .btn-copy:hover {
        transform: scale(1.05);
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.5);
    }

    .upi-divider {
        text-align: center;
        font-size: 12px;
        color: var(--text-muted);
        margin: 14px 0;
        position: relative;
    }

    .upi-divider::before,
    .upi-divider::after {
        content: '';
        position: absolute;
        top: 50%;
        width: 42%;
        height: 1px;
        background: var(--glass-border);
    }

    .upi-divider::before {
        left: 0;
    }

    .upi-divider::after {
        right: 0;
    }

    .upi-own-id label {
        font-size: 13px;
        color: var(--text-secondary);
        margin-bottom: 6px;
        display: block;
    }

    .upi-amount-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: rgba(34, 197, 94, 0.15);
        border: 1px solid rgba(34, 197, 94, 0.3);
        color: #4ade80;
        padding: 4px 12px;
        border-radius: 50px;
        font-size: 13px;
        font-weight: 700;
        margin-bottom: 12px;
    }

    /* ── Card Panel ─────────────────────────────────────────────── */
    .card-panel {
        margin-top: 20px;
        animation: fadeInUp 0.3s ease;
    }

    /* Card Preview */
    .card-preview-wrap {
        perspective: 1000px;
        width: 100%;
        max-width: 360px;
        height: 200px;
        margin: 0 auto 28px;
    }

    .card-preview-inner {
        position: relative;
        width: 100%;
        height: 100%;
        transform-style: preserve-3d;
        transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .card-preview-inner.flipped {
        transform: rotateY(180deg);
    }

    .card-face {
        position: absolute;
        inset: 0;
        border-radius: 18px;
        backface-visibility: hidden;
        -webkit-backface-visibility: hidden;
        padding: 22px 26px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
        overflow: hidden;
    }

    .card-front {
        background: linear-gradient(135deg, #1a1a2e 0%, #16213e 40%, #0f3460 100%);
    }

    .card-back {
        background: linear-gradient(135deg, #0f3460 0%, #16213e 60%, #1a1a2e 100%);
        transform: rotateY(180deg);
    }

    .card-chip {
        width: 42px;
        height: 32px;
        background: linear-gradient(135deg, #d4af37 0%, #f5d78e 50%, #b8860b 100%);
        border-radius: 6px;
        position: relative;
        overflow: hidden;
    }

    .card-chip::before {
        content: '';
        position: absolute;
        top: 50%;
        left: 0;
        right: 0;
        height: 1px;
        background: rgba(0, 0, 0, 0.3);
    }

    .card-chip::after {
        content: '';
        position: absolute;
        left: 50%;
        top: 0;
        bottom: 0;
        width: 1px;
        background: rgba(0, 0, 0, 0.3);
    }

    .card-contactless {
        font-size: 20px;
        color: rgba(255, 255, 255, 0.7);
        margin-left: auto;
    }

    .card-number-display {
        font-family: 'Courier New', monospace;
        font-size: 18px;
        font-weight: 600;
        letter-spacing: 0.18em;
        color: #fff;
        text-shadow: 0 2px 8px rgba(0, 0, 0, 0.5);
    }

    .card-bottom-row {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
    }

    .card-holder-display,
    .card-expiry-display {
        color: rgba(255, 255, 255, 0.8);
        text-transform: uppercase;
    }

    .card-holder-display small,
    .card-expiry-display small {
        display: block;
        font-size: 9px;
        color: rgba(255, 255, 255, 0.45);
        letter-spacing: 0.1em;
        margin-bottom: 2px;
    }

    .card-holder-display span,
    .card-expiry-display span {
        font-size: 13px;
        font-weight: 600;
    }

    .card-brand-display {
        font-size: 28px;
    }

    /* Back of card */
    .card-mag-stripe {
        background: linear-gradient(90deg, #333 0%, #555 50%, #333 100%);
        height: 40px;
        margin: 0 -26px;
        margin-top: 8px;
    }

    .card-sig-area {
        display: flex;
        align-items: center;
        gap: 12px;
        background: rgba(255, 255, 255, 0.9);
        border-radius: 4px;
        padding: 8px 12px;
        margin-top: 14px;
    }

    .card-sig-label {
        font-size: 10px;
        color: #666;
        flex: 1;
    }

    .card-cvv-display {
        font-family: 'Courier New', monospace;
        font-size: 16px;
        font-weight: 700;
        color: #000;
        background: #fff;
        padding: 4px 10px;
        border-radius: 4px;
        letter-spacing: 0.15em;
        min-width: 48px;
        text-align: center;
    }

    /* Card form */
    .card-form {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .card-input-wrap {
        position: relative;
    }

    .card-input-wrap .card-type-icon {
        position: absolute;
        right: 14px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 22px;
        pointer-events: none;
    }

    .form-row-2 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px;
    }

    /* Shimmer on card */
    .card-face::after {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(105deg, transparent 40%, rgba(255, 255, 255, 0.07) 50%, transparent 60%);
        animation: cardShimmer 3s ease-in-out infinite;
    }

    @keyframes cardShimmer {
        0% {
            transform: translateX(-100%);
        }

        100% {
            transform: translateX(200%);
        }
    }

    /* Screenshot wrap */
    .upload-box {
        border: 2px dashed var(--glass-border);
        border-radius: var(--radius-md);
        padding: 28px;
        text-align: center;
        cursor: pointer;
        transition: border-color 0.2s, background 0.2s;
    }

    .upload-box:hover {
        border-color: var(--primary);
        background: rgba(59, 130, 246, 0.05);
    }

    .upload-icon {
        font-size: 32px;
        margin-bottom: 8px;
    }

    .upload-text {
        font-size: 14px;
        color: var(--text-secondary);
    }

    /* Animations */
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Responsive */
    @media (max-width: 768px) {
        .upi-qr-row {
            flex-direction: column;
            align-items: center;
        }

        .form-row-2 {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="page-header">
    <div class="container">
        <h1>🔐 Checkout</h1>
        <p>Complete your order securely.</p>
    </div>
</div>

<div class="container section-sm">
    <?php if ($error): ?>
        <div class="alert alert-danger mb-3" style="margin-bottom:20px;">
            <i class="fa fa-circle-xmark"></i> <?= e($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="checkoutForm">
        <?= csrf_field() ?>
        <div style="display:grid;grid-template-columns:1fr 360px;gap:32px;align-items:start;">

            <!-- Left: Address + Payment -->
            <div style="display:flex;flex-direction:column;gap:24px;">

                <!-- Delivery Address -->
                <div class="card" style="padding:28px;">
                    <h2 style="font-family:var(--font-head);font-size:18px;font-weight:600;margin-bottom:20px;">📍
                        Delivery Address</h2>
                    <?php if ($addresses): ?>
                        <div style="display:flex;flex-direction:column;gap:12px;margin-bottom:20px;">
                            <?php foreach ($addresses as $addr): ?>
                                <label
                                    style="display:flex;align-items:flex-start;gap:12px;padding:16px;border:2px solid var(--glass-border);border-radius:var(--radius-md);cursor:pointer;transition:border-color 0.2s;"
                                    class="addr-label" data-id="<?= $addr['id'] ?>">
                                    <input type="radio" name="address_id" value="<?= $addr['id'] ?>" <?= $addr['is_default'] ? 'checked' : '' ?> oninput="showSavedAddr()"
                                        style="margin-top:3px;accent-color:var(--primary);">
                                    <div>
                                        <div style="font-weight:600;margin-bottom:3px;"><?= e($addr['label']) ?> –
                                            <?= e($addr['name']) ?>
                                        </div>
                                        <div style="font-size:13px;color:var(--text-muted);">
                                            <?= e($addr['line1']) ?>         <?= $addr['line2'] ? ', ' . e($addr['line2']) : '' ?>
                                        </div>
                                        <div style="font-size:13px;color:var(--text-muted);"><?= e($addr['city']) ?>,
                                            <?= e($addr['state']) ?> – <?= e($addr['pincode']) ?>
                                        </div>
                                        <div style="font-size:13px;color:var(--text-muted);"><?= e($addr['phone']) ?></div>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" onclick="toggleNewAddr()" class="btn btn-outline btn-sm" id="addAddrBtn">
                            <i class="fa fa-plus"></i> Add New Address
                        </button>
                    <?php endif; ?>

                    <div id="newAddrForm"
                        style="<?= $addresses ? 'display:none;' : '' ?>margin-top:20px;padding-top:20px;border-top:1px solid var(--glass-border);">
                        <div class="grid-2" style="gap:16px;">
                            <div class="form-group"><label class="form-label">Full Name</label><input type="text"
                                    name="new_name" class="form-control" placeholder="Name"
                                    value="<?= e($user['name']) ?>"></div>
                            <div class="form-group"><label class="form-label">Phone</label><input type="text"
                                    name="new_phone" class="form-control" placeholder="Mobile"
                                    value="<?= e($user['phone']) ?>"></div>
                        </div>
                        <div class="form-group"><label class="form-label">Address Line 1</label><input type="text"
                                name="new_line1" class="form-control" placeholder="House no, Street, Area"></div>
                        <div class="grid-2" style="gap:16px;">
                            <div class="form-group"><label class="form-label">City</label><input type="text"
                                    name="new_city" class="form-control" placeholder="City" value="Kolkata"></div>
                            <div class="form-group"><label class="form-label">Pincode</label><input type="text"
                                    name="new_pincode" class="form-control" placeholder="6-digit pincode" maxlength="6">
                            </div>
                        </div>
                        <label class="custom-check" style="margin-top:8px;">
                            <input type="checkbox" name="save_address" value="1" checked>
                            <span class="check-box"></span>
                            <span style="font-size:13px;color:var(--text-secondary);">Save this address for next
                                time</span>
                        </label>
                    </div>
                </div>

                <!-- Payment Method -->
                <div class="card" style="padding:28px;">
                    <h2 style="font-family:var(--font-head);font-size:18px;font-weight:600;margin-bottom:20px;">💳
                        Payment Method</h2>

                    <div class="amz-payment-box">

                        <!-- ── Your available balance ── -->
                        <div class="amz-section-title">Your available balance</div>
                        <div class="amz-balance-row">
                            <div class="amz-balance-label">Offer / Gift Card Code</div>
                            <div class="amz-balance-unavail"><i class="fa fa-circle-info"></i> No active balance. Apply
                                a gift card or offer below.</div>
                            <div class="amz-offer-row">
                                <input type="text" placeholder="Enter Code" id="offerCodeInput">
                                <button type="button" class="amz-apply-btn" onclick="applyOfferCode()">Apply</button>
                            </div>
                        </div>

                        <!-- ── Another payment method ── -->
                        <div class="amz-methods-title">Another payment method</div>

                        <!-- Credit / Debit Card -->
                        <div class="amz-method-item" id="opt-card">
                            <label class="amz-method-row">
                                <input type="radio" name="payment_method" value="card" onchange="switchPayment('card')">
                                <span class="amz-method-label">Credit or debit card</span>
                                <span class="amz-method-icons">
                                    <span title="Visa">💙</span>
                                    <span title="Mastercard">🔴</span>
                                    <span title="Amex">🟦</span>
                                    <span title="RuPay">🇮🇳</span>
                                </span>
                            </label>
                        </div>

                        <!-- Net Banking -->
                        <div class="amz-method-item" id="opt-netbanking">
                            <label class="amz-method-row">
                                <input type="radio" name="payment_method" value="netbanking"
                                    onchange="switchPayment('netbanking')">
                                <span class="amz-method-label">Net Banking</span>
                            </label>
                            <div class="amz-sub-panel" id="netbankingPanel">
                                <select class="form-control" name="netbanking_bank"
                                    style="max-width:280px;font-size:13px;">
                                    <option value="">Choose an Option</option>
                                    <option>State Bank of India</option>
                                    <option>HDFC Bank</option>
                                    <option>ICICI Bank</option>
                                    <option>Axis Bank</option>
                                    <option>Kotak Mahindra Bank</option>
                                    <option>Punjab National Bank</option>
                                    <option>Bank of Baroda</option>
                                    <option>Canara Bank</option>
                                    <option>Union Bank of India</option>
                                    <option>Other Bank</option>
                                </select>
                            </div>
                        </div>

                        <!-- Scan & Pay with UPI -->
                        <div class="amz-method-item" id="opt-upi">
                            <label class="amz-method-row">
                                <input type="radio" name="payment_method" value="upi" onchange="switchPayment('upi')">
                                <span class="amz-method-label">Scan and Pay with <strong>UPI</strong></span>
                                <span class="amz-method-icons">📱</span>
                            </label>
                            <div class="amz-info-note" id="upiScanNote" style="display:none;">
                                <i class="fa fa-circle-info"></i>
                                <span>You will need to Scan the QR code on the payment page to complete the
                                    payment.</span>
                            </div>
                        </div>

                        <!-- Other UPI Apps -->
                        <div class="amz-method-item" id="opt-otherupi">
                            <label class="amz-method-row">
                                <input type="radio" name="payment_method" value="otherupi"
                                    onchange="switchPayment('otherupi')">
                                <span class="amz-method-label">Other UPI Apps</span>
                            </label>
                            <div class="amz-sub-panel" id="otherupiPanel">
                                <div style="margin-bottom:8px;font-size:12px;color:var(--text-muted);">Enter your UPI ID
                                    (optional – for reference)</div>
                                <div style="position:relative;max-width:300px;">
                                    <input type="text" name="customer_upi_id" class="form-control"
                                        placeholder="e.g. yourname@okaxis" autocomplete="off"
                                        oninput="validateUpiInput(this)" style="padding-right:44px;font-size:13px;">
                                    <span id="upiValidIcon"
                                        style="position:absolute;right:14px;top:50%;transform:translateY(-50%);font-size:18px;display:none;"></span>
                                </div>
                                <p style="font-size:11px;color:var(--text-muted);margin-top:6px;">
                                    <i class="fa fa-shield-halved"></i> We do not store your UPI credentials.
                                </p>
                            </div>
                        </div>

                        <!-- Upload Screenshot -->
                        <div class="amz-method-item" id="opt-screenshot">
                            <label class="amz-method-row">
                                <input type="radio" name="payment_method" value="screenshot"
                                    onchange="switchPayment('screenshot')">
                                <span class="amz-method-label">Upload Payment Screenshot</span>
                                <span class="amz-method-icons">📸</span>
                            </label>
                        </div>

                        <!-- Cash / Pay on Delivery -->
                        <div class="amz-method-item" id="opt-cod">
                            <label class="amz-method-row">
                                <input type="radio" name="payment_method" value="cod" checked
                                    onchange="switchPayment('cod')">
                                <span class="amz-method-label">Cash on Delivery / Pay on Delivery</span>
                            </label>
                            <div class="amz-info-note">
                                <i class="fa fa-circle-info"></i>
                                <span>Cash, UPI and Cards accepted at delivery.</span>
                            </div>
                        </div>

                    </div><!-- /.amz-payment-box -->

                    <!-- ── UPI Panel ──────────────────────────── -->
                    <div id="upiPanel" class="upi-panel" style="display:none;">
                        <div class="upi-amount-badge">
                            <i class="fa fa-indian-rupee-sign"></i>
                            Pay ₹<?= number_format($total, 2) ?>
                        </div>
                        <div class="upi-qr-row">
                            <!-- QR Code -->
                            <div>
                                <div class="qr-box">
                                    <div id="upiQrCode"></div>
                                </div>
                                <p style="text-align:center;font-size:11px;color:var(--text-muted);margin-top:8px;">Scan
                                    to pay</p>
                            </div>
                            <!-- Info -->
                            <div class="upi-info">
                                <h4>⚡ Pay via any UPI App</h4>
                                <ol class="upi-steps">
                                    <li><span class="step-num">1</span> Open GPay / PhonePe / Paytm</li>
                                    <li><span class="step-num">2</span> Scan the QR code <strong>or</strong> use the UPI
                                        ID below</li>
                                    <li><span class="step-num">3</span> Enter amount
                                        <strong>₹<?= number_format($total, 2) ?></strong>
                                    </li>
                                    <li><span class="step-num">4</span> Complete payment &amp; place order</li>
                                </ol>
                                <div class="upi-id-box">
                                    <span class="upi-id-text" id="merchantUpiText"><?= MERCHANT_UPI ?></span>
                                    <button type="button" class="btn-copy" onclick="copyUpiId()">
                                        <i class="fa fa-copy"></i> Copy
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="upi-divider">or enter your UPI ID for confirmation</div>

                        <div class="upi-own-id">
                            <label>Your UPI ID <span style="color:var(--text-muted);font-weight:400;">(optional – for
                                    reference only)</span></label>
                            <div style="position:relative;">
                                <input type="text" name="customer_upi_id" class="form-control"
                                    placeholder="e.g. yourname@okaxis" autocomplete="off"
                                    oninput="validateUpiInput(this)" style="padding-right:44px;">
                                <span id="upiValidIcon"
                                    style="position:absolute;right:14px;top:50%;transform:translateY(-50%);font-size:18px;display:none;"></span>
                            </div>
                            <p style="font-size:11px;color:var(--text-muted);margin-top:6px;">
                                <i class="fa fa-shield-halved"></i> We do not store your UPI credentials. This is for
                                order reference only.
                            </p>
                        </div>
                    </div>

                    <!-- ── Card Panel ──────────────────────────── -->
                    <div id="cardPanel" class="card-panel" style="display:none;">

                        <!-- Live Card Preview -->
                        <div class="card-preview-wrap">
                            <div class="card-preview-inner" id="cardInner">
                                <!-- Front -->
                                <div class="card-face card-front">
                                    <div style="display:flex;align-items:center;justify-content:space-between;">
                                        <div class="card-chip"></div>
                                        <span class="card-contactless">((</span>
                                    </div>
                                    <div class="card-number-display" id="cardNumDisplay">•••• •••• •••• ••••</div>
                                    <div class="card-bottom-row">
                                        <div class="card-holder-display">
                                            <small>CARD HOLDER</small>
                                            <span id="cardNameDisplay">YOUR NAME</span>
                                        </div>
                                        <div class="card-expiry-display">
                                            <small>EXPIRES</small>
                                            <span id="cardExpDisplay">MM/YY</span>
                                        </div>
                                        <div class="card-brand-display" id="cardBrandDisplay">💳</div>
                                    </div>
                                </div>
                                <!-- Back -->
                                <div class="card-face card-back">
                                    <div class="card-mag-stripe"></div>
                                    <div class="card-sig-area">
                                        <div class="card-sig-label">AUTHORIZED SIGNATURE</div>
                                        <div class="card-cvv-display" id="cardCvvDisplay">•••</div>
                                    </div>
                                    <p
                                        style="font-size:10px;color:rgba(255,255,255,0.4);margin-top:auto;text-align:center;">
                                        This card is property of the issuing bank
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Card Form Fields -->
                        <div class="card-form">
                            <!-- Card Number -->
                            <div class="form-group" style="margin:0;">
                                <label class="form-label">Card Number</label>
                                <div class="card-input-wrap">
                                    <input type="text" name="card_number" id="cardNumberInput" class="form-control"
                                        placeholder="1234 5678 9012 3456" maxlength="19" autocomplete="cc-number"
                                        oninput="handleCardNumber(this)" onfocus="flipCard(false)"
                                        style="letter-spacing:0.08em;font-family:'Courier New',monospace;">
                                    <span class="card-type-icon" id="cardTypeIcon">💳</span>
                                </div>
                            </div>

                            <!-- Cardholder Name -->
                            <div class="form-group" style="margin:0;">
                                <label class="form-label">Cardholder Name</label>
                                <input type="text" name="card_name" id="cardNameInput" class="form-control"
                                    placeholder="As printed on card" autocomplete="cc-name"
                                    oninput="document.getElementById('cardNameDisplay').textContent = this.value.toUpperCase() || 'YOUR NAME'"
                                    onfocus="flipCard(false)">
                            </div>

                            <!-- Expiry + CVV -->
                            <div class="form-row-2">
                                <div class="form-group" style="margin:0;">
                                    <label class="form-label">Expiry Date</label>
                                    <input type="text" name="card_expiry" id="cardExpiryInput" class="form-control"
                                        placeholder="MM/YY" maxlength="5" autocomplete="cc-exp"
                                        oninput="handleExpiry(this)" onfocus="flipCard(false)">
                                </div>
                                <div class="form-group" style="margin:0;">
                                    <label class="form-label">CVV</label>
                                    <div style="position:relative;">
                                        <input type="password" name="card_cvv" id="cardCvvInput" class="form-control"
                                            placeholder="•••" maxlength="4" autocomplete="cc-csc"
                                            oninput="document.getElementById('cardCvvDisplay').textContent = this.value || '•••'"
                                            onfocus="flipCard(true)" onblur="flipCard(false)">
                                        <i class="fa fa-question-circle"
                                            style="position:absolute;right:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:14px;"
                                            title="3 digits on back of card"></i>
                                    </div>
                                </div>
                            </div>

                            <!-- Security note -->
                            <div
                                style="display:flex;align-items:center;gap:8px;padding:10px 14px;background:rgba(34,197,94,0.08);border:1px solid rgba(34,197,94,0.2);border-radius:10px;font-size:12px;color:var(--text-secondary);">
                                <i class="fa fa-lock" style="color:var(--success);"></i>
                                Your card details are encrypted and never stored on our servers.
                            </div>
                        </div>
                    </div>

                    <!-- ── Screenshot Panel ────────────────────── -->
                    <div id="screenshotWrap" style="display:none;margin-top:16px;">
                        <div class="upload-box" onclick="document.getElementById('screenshotFile').click()">
                            <div class="upload-icon"><i class="fa fa-image"></i></div>
                            <div class="upload-text">Click to upload payment screenshot</div>
                            <div style="font-size:12px;color:var(--text-muted);margin-top:4px;">JPG, PNG (max 5MB)</div>
                            <img id="screenshotPreview"
                                style="display:none;width:100%;max-height:180px;object-fit:contain;margin-top:12px;border-radius:8px;">
                        </div>
                        <input type="file" name="screenshot" id="screenshotFile" accept="image/*"
                            data-preview="screenshotPreview" style="display:none;">
                    </div>

                </div><!-- /.card payment -->
            </div><!-- /left col -->

            <!-- Right: Order Summary -->
            <div class="card" style="padding:28px;position:sticky;top:90px;">
                <h3 style="font-family:var(--font-head);font-size:18px;font-weight:600;margin-bottom:20px;">Order
                    Summary</h3>
                <?php foreach ($items as $it): ?>
                    <div
                        style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:12px;font-size:14px;">
                        <span style="color:var(--text-secondary);"><?= e($it['name']) ?> <span
                                style="color:var(--text-muted);">×<?= $it['qty'] ?></span></span>
                        <span style="font-weight:600;"><?= CURRENCY_SYMBOL . number_format($it['total'], 2) ?></span>
                    </div>
                <?php endforeach; ?>
                <div
                    style="border-top:1px solid var(--glass-border);padding-top:16px;margin-top:4px;display:flex;flex-direction:column;gap:10px;font-size:14px;">
                    <div style="display:flex;justify-content:space-between;color:var(--text-secondary);">
                        <span>Subtotal</span><span><?= CURRENCY_SYMBOL . number_format($subtotal, 2) ?></span>
                    </div>
                    <?php if ($couponDiscount > 0): ?>
                        <div style="display:flex;justify-content:space-between;color:var(--success);">
                            <span>Discount</span><span>–<?= CURRENCY_SYMBOL . number_format($couponDiscount, 2) ?></span>
                        </div>
                    <?php endif; ?>
                    <div style="display:flex;justify-content:space-between;color:var(--text-secondary);">
                        <span>Delivery</span>
                        <span><?= $deliveryFee > 0 ? CURRENCY_SYMBOL . number_format($deliveryFee, 2) : '<span style="color:var(--success);">FREE</span>' ?></span>
                    </div>
                    <div
                        style="display:flex;justify-content:space-between;font-size:20px;font-weight:700;border-top:1px solid var(--glass-border);padding-top:12px;">
                        <span>Total</span><span><?= CURRENCY_SYMBOL . number_format($total, 2) ?></span>
                    </div>
                </div>
                <?php if ($couponCode): ?>
                    <div
                        style="margin-top:12px;padding:8px 12px;background:rgba(34,197,94,0.1);border-radius:8px;font-size:13px;color:var(--success);border:1px solid rgba(34,197,94,0.25);">
                        ✓ Coupon <strong><?= e($couponCode) ?></strong> applied
                    </div>
                <?php endif; ?>

                <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top:24px;"
                    id="placeOrderBtn">
                    <i class="fa fa-check-circle"></i> Place Order
                </button>
                <p style="font-size:12px;color:var(--text-muted);text-align:center;margin-top:10px;">
                    <i class="fa fa-shield-halved"></i> 100% Secure Checkout
                </p>
            </div>

        </div><!-- /grid -->
    </form>
</div>

<!-- ── UPI Payment Confirmation Modal ──────────────────────────── -->
<div class="upi-pay-overlay" id="upiPayOverlay">
    <div class="upi-pay-modal" id="upiPayModal">
        <!-- Step 1: Initiate -->
        <div id="modalStep1">
            <div class="upi-modal-icon">📲</div>
            <h3>Pay via UPI App</h3>
            <div class="modal-to">Pay ₹ to</div>
            <div class="modal-amount" id="modalAmount"></div>
            <div class="modal-to">Sending payment to</div>
            <div class="modal-upi-id"><?= MERCHANT_UPI ?></div>
            <div class="modal-steps">
                <b>1.</b> Click <b>"Open UPI App"</b> below — your UPI app will launch<br>
                <b>2.</b> Verify the amount &amp; complete the payment<br>
                <b>3.</b> Come back here and tap <b>"I've Paid"</b>
            </div>
            <a id="upiDeepLink" href="#" target="_blank" class="btn-upi-confirm"
                style="text-decoration:none;display:block;">
                📲 Open UPI App to Pay
            </a>
            <button type="button" class="btn-upi-confirm"
                style="background:linear-gradient(135deg,#818cf8,#6366f1);box-shadow:0 8px 24px rgba(99,102,241,0.35);margin-top:0;"
                onclick="showPayConfirm()">
                ✅ I've Paid – Place Order
            </button>
            <button type="button" class="btn-upi-cancel" onclick="closeUpiModal()">
                ✕ Cancel
            </button>
        </div>
        <!-- Step 2: Placing order animation -->
        <div id="modalStep2" style="display:none;">
            <div class="modal-tick-anim" id="modalTick">✅</div>
            <div id="modalPlacingSpinner" style="font-size:36px;margin-bottom:12px;">⏳</div>
            <h3>Placing your order…</h3>
            <p style="font-size:13px;color:rgba(255,255,255,0.5);margin-top:8px;">Please wait, do not close this page.
            </p>
        </div>
    </div>
</div>

<script>
    /* ── Address helpers ──────────────────────────────────────────── */
    function toggleNewAddr() {
        const f = document.getElementById('newAddrForm');
        f.style.display = f.style.display === 'none' ? 'block' : 'none';
        document.querySelectorAll('[name=address_id]').forEach(r => r.checked = false);
    }
    function showSavedAddr() {
        document.getElementById('newAddrForm').style.display = 'none';
    }
    document.querySelectorAll('.addr-label').forEach(l => {
        l.addEventListener('click', () => {
            document.querySelectorAll('.addr-label').forEach(ll => ll.style.borderColor = 'var(--glass-border)');
            l.style.borderColor = 'var(--primary)';
        });
    });
    document.querySelectorAll('[name=address_id]:checked').forEach(r => {
        r.closest('.addr-label').style.borderColor = 'var(--primary)';
    });

    /* ── Payment method switcher ─────────────────────────────────── */
    function switchPayment(method) {
        // Highlight selected row
        ['cod', 'upi', 'card', 'netbanking', 'otherupi', 'screenshot'].forEach(m => {
            const el = document.getElementById('opt-' + m);
            if (el) el.style.background = (m === method) ? 'rgba(249,168,37,0.07)' : '';
        });
        // Show/hide sub-panels
        document.getElementById('upiPanel').style.display = method === 'upi' ? 'block' : 'none';
        document.getElementById('upiScanNote').style.display = method === 'upi' ? 'flex' : 'none';
        document.getElementById('cardPanel').style.display = method === 'card' ? 'block' : 'none';
        document.getElementById('screenshotWrap').style.display = method === 'screenshot' ? 'block' : 'none';
        // Net banking
        const nbPanel = document.getElementById('netbankingPanel');
        if (nbPanel) nbPanel.classList.toggle('visible', method === 'netbanking');
        // Other UPI
        const ouPanel = document.getElementById('otherupiPanel');
        if (ouPanel) ouPanel.classList.toggle('visible', method === 'otherupi');

        // Update button label
        const btn = document.getElementById('placeOrderBtn');
        if (method === 'otherupi') {
            btn.innerHTML = '<i class="fa fa-mobile-screen-button"></i> Pay & Place Order';
            btn.setAttribute('data-upi-flow', '1');
        } else {
            btn.removeAttribute('data-upi-flow');
            if (method === 'upi') btn.innerHTML = '<i class="fa fa-mobile-screen-button"></i> Confirm & Place Order';
            else if (method === 'card') btn.innerHTML = '<i class="fa fa-credit-card"></i> Pay & Place Order';
            else btn.innerHTML = '<i class="fa fa-check-circle"></i> Place Order';
        }
    }

    /* ── UPI Payment Modal Flow ──────────────────────────────────── */
    var _upiPayConfirmed = false;

    function openUpiPayModal() {
        const upiId = document.querySelector('[name=customer_upi_id]').value.trim();
        if (!upiId) {
            alert('Please enter your UPI ID first (e.g. yourname@okaxis).');
            return false;
        }
        if (!/^[a-zA-Z0-9._\-]+@[a-zA-Z0-9]+$/.test(upiId)) {
            alert('Invalid UPI ID format. Example: yourname@upi');
            return false;
        }
        // Build UPI deep link (pay TO merchant)
        const amount = '<?= number_format($total, 2, '.', '') ?>';
        const upiLink = 'upi://pay?pa=<?= urlencode(MERCHANT_UPI) ?>&pn=<?= urlencode(MERCHANT_NAME) ?>&am=' + amount + '&cu=INR&tn=<?= urlencode('Bluemoon Food Order') ?>';
        document.getElementById('modalAmount').textContent = '₹<?= number_format($total, 2) ?>';
        document.getElementById('upiDeepLink').href = upiLink;
        document.getElementById('modalStep1').style.display = 'block';
        document.getElementById('modalStep2').style.display = 'none';
        document.getElementById('upiPayOverlay').classList.add('active');
        return true;
    }

    function showPayConfirm() {
        document.getElementById('modalStep1').style.display = 'none';
        document.getElementById('modalStep2').style.display = 'block';
        document.getElementById('modalTick').style.display = 'block';
        document.getElementById('modalPlacingSpinner').style.display = 'none';
        _upiPayConfirmed = true;
        // Submit form after short delay for UX
        setTimeout(function () {
            document.getElementById('checkoutForm').submit();
        }, 900);
    }

    function closeUpiModal() {
        document.getElementById('upiPayOverlay').classList.remove('active');
        _upiPayConfirmed = false;
    }

    // Close overlay on backdrop click
    document.getElementById('upiPayOverlay').addEventListener('click', function (e) {
        if (e.target === this) closeUpiModal();
    });

    /* ── Offer code helper ───────────────────────────────────────── */
    function applyOfferCode() {
        const code = document.getElementById('offerCodeInput').value.trim();
        if (!code) return;
        alert('Offer code "' + code + '" is not valid or already applied.');
    }

    /* ── UPI helpers ─────────────────────────────────────────────── */
    function copyUpiId() {
        const upiId = document.getElementById('merchantUpiText').textContent;
        navigator.clipboard.writeText(upiId).then(() => {
            const btn = document.querySelector('.btn-copy');
            btn.innerHTML = '<i class="fa fa-check"></i> Copied!';
            btn.style.background = 'linear-gradient(135deg,#22c55e,#16a34a)';
            setTimeout(() => {
                btn.innerHTML = '<i class="fa fa-copy"></i> Copy';
                btn.style.background = '';
            }, 2000);
        }).catch(() => {
            // Fallback
            const el = document.createElement('textarea');
            el.value = upiId; document.body.appendChild(el);
            el.select(); document.execCommand('copy'); document.body.removeChild(el);
        });
    }

    function validateUpiInput(input) {
        const val = input.value.trim();
        const icon = document.getElementById('upiValidIcon');
        icon.style.display = val ? 'block' : 'none';
        if (!val) return;
        const valid = /^[a-zA-Z0-9._\-]+@[a-zA-Z0-9]+$/.test(val);
        icon.textContent = valid ? '✅' : '❌';
    }

    /* ── Card helpers ─────────────────────────────────────────────── */
    function flipCard(toBack) {
        document.getElementById('cardInner').classList.toggle('flipped', toBack);
    }

    const cardBrands = {
        visa: { pattern: /^4/, icon: '💙', label: 'VISA' },
        mastercard: { pattern: /^5[1-5]|^2[2-7]/, icon: '🔴', label: 'MC' },
        rupay: { pattern: /^6[0-9]{15}|^508[5-9]|^60698[5-9]|^6069[89]|^607[01]/, icon: '🇮🇳', label: 'RuPay' },
        amex: { pattern: /^3[47]/, icon: '🟦', label: 'AMEX' },
    };

    function detectCardType(num) {
        for (const [type, brand] of Object.entries(cardBrands)) {
            if (brand.pattern.test(num)) return brand;
        }
        return { icon: '💳', label: '' };
    }

    function handleCardNumber(input) {
        // Strip non-digits, limit to 16 digits
        let raw = input.value.replace(/\D/g, '').substring(0, 16);
        // Format with spaces
        let formatted = raw.match(/.{1,4}/g)?.join(' ') || '';
        input.value = formatted;

        // Live card preview
        let display = raw.padEnd(16, '•').match(/.{1,4}/g).join(' ');
        document.getElementById('cardNumDisplay').textContent = display;

        // Card type
        const brand = detectCardType(raw);
        document.getElementById('cardTypeIcon').textContent = brand.icon;
        document.getElementById('cardBrandDisplay').textContent = brand.icon;
    }

    function handleExpiry(input) {
        let raw = input.value.replace(/\D/g, '').substring(0, 4);
        if (raw.length >= 3) {
            raw = raw.substring(0, 2) + '/' + raw.substring(2);
        }
        input.value = raw;
        document.getElementById('cardExpDisplay').textContent = raw || 'MM/YY';
    }

    /* ── Form validation on submit ───────────────────────────────── */
    document.getElementById('checkoutForm').addEventListener('submit', function (e) {
        const method = document.querySelector('[name=payment_method]:checked')?.value;

        // For otherupi: intercept and show modal (unless already confirmed)
        if (method === 'otherupi' && !_upiPayConfirmed) {
            e.preventDefault();
            openUpiPayModal();
            return;
        }

        if (method === 'card') {
            const num = document.getElementById('cardNumberInput').value.replace(/\D/g, '');
            const name = document.querySelector('[name=card_name]').value.trim();
            const exp = document.querySelector('[name=card_expiry]').value.trim();
            const cvv = document.querySelector('[name=card_cvv]').value.trim();
            if (num.length < 13) { alert('Please enter a valid card number.'); e.preventDefault(); return; }
            if (!name) { alert('Please enter the cardholder name.'); e.preventDefault(); return; }
            if (!/^(0[1-9]|1[0-2])\/\d{2}$/.test(exp)) { alert('Please enter a valid expiry date (MM/YY).'); e.preventDefault(); return; }
            if (cvv.length < 3) { alert('Please enter a valid CVV.'); e.preventDefault(); return; }
        }
    });
</script>
<script src="<?= BASE_URL ?>/assets/js/qrcode.min.js?v=1"></script>
<script>
    (function () {
        var upiString = <?= json_encode($upiString) ?>;
        var container = document.getElementById('upiQrCode');
        if (container && typeof QRCode !== 'undefined') {
            new QRCode(container, {
                text: upiString,
                width: 118,
                height: 118,
                colorDark: '#000000',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.M
            });
        }
    })();
</script>
<?php require_once __DIR__ . '/templates/footer.php'; ?>