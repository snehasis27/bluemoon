<?php
require_once __DIR__ . '/config.php';
requireLogin();
$db = getDB();
$user = currentUser();
$oid = (int) ($_GET['id'] ?? 0);

$stmt = $db->prepare('SELECT * FROM orders WHERE id=? AND user_id=? LIMIT 1');
$stmt->execute([$oid, $user['id']]);
$order = $stmt->fetch();
if (!$order)
    die('Order not found.');

$items = $db->prepare('SELECT * FROM order_items WHERE order_id=?');
$items->execute([$oid]);
$items = $items->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Invoice #
        <?= $oid ?> – Bluemoon
    </title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #fff;
            color: #1a1a2e;
            font-size: 14px;
        }

        .invoice {
            max-width: 760px;
            margin: 40px auto;
            padding: 48px;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
        }

        .inv-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 40px;
            padding-bottom: 24px;
            border-bottom: 2px solid #e2e8f0;
        }

        .logo {
            font-size: 28px;
            font-weight: 800;
            color: #1e40af;
            letter-spacing: -1px;
        }

        .logo span {
            color: #3b82f6;
        }

        .inv-title {
            font-size: 28px;
            font-weight: 700;
            color: #1e40af;
        }

        .inv-num {
            font-size: 14px;
            color: #64748b;
            margin-top: 4px;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 32px;
            margin-bottom: 36px;
        }

        .label {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #94a3b8;
            margin-bottom: 6px;
        }

        .value {
            font-size: 14px;
            color: #1e293b;
            line-height: 1.6;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }

        th {
            background: #f8fafc;
            padding: 12px 16px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #e2e8f0;
        }

        td {
            padding: 14px 16px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
        }

        tr:last-child td {
            border-bottom: none;
        }

        .totals {
            max-width: 280px;
            margin-left: auto;
            border-top: 2px solid #e2e8f0;
            padding-top: 16px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            font-size: 14px;
            color: #64748b;
        }

        .total-row.final {
            font-size: 20px;
            font-weight: 800;
            color: #1e40af;
            border-top: 2px solid #e2e8f0;
            padding-top: 12px;
            margin-top: 6px;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-paid,
        .badge-success {
            background: #dcfce7;
            color: #166534;
        }

        .badge-pending {
            background: #fef9c3;
            color: #854d0e;
        }

        .footer {
            margin-top: 48px;
            padding-top: 24px;
            border-top: 1px solid #e2e8f0;
            text-align: center;
            color: #94a3b8;
            font-size: 13px;
        }

        .print-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 24px;
            background: #3b82f6;
            color: #fff;
            border: none;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(59, 130, 246, 0.4);
        }

        @media print {
            .print-btn {
                display: none;
            }

            .invoice {
                border: none;
            }

            body {
                background: #fff;
            }
        }
    </style>
</head>

<body>
    <button class="print-btn" onclick="window.print()">🖨️ Print / Save PDF</button>
    <div class="invoice">
        <div class="inv-header">
            <div>
                <div class="logo">🌙 Blue<span>moon</span></div>
                <div style="font-size:13px;color:#64748b;margin-top:6px;">
                    <?= SITE_ADDRESS ?>
                </div>
                <div style="font-size:13px;color:#64748b;">
                    <?= SITE_EMAIL ?> ·
                    <?= SITE_PHONE ?>
                </div>
            </div>
            <div style="text-align:right;">
                <div class="inv-title">INVOICE</div>
                <div class="inv-num">#
                    <?= str_pad($oid, 6, '0', STR_PAD_LEFT) ?>
                </div>
                <div style="font-size:13px;color:#64748b;margin-top:4px;">Date:
                    <?= date('d M Y', strtotime($order['created_at'])) ?>
                </div>
            </div>
        </div>

        <div class="grid-2">
            <div>
                <div class="label">Bill To</div>
                <div class="value">
                    <strong>
                        <?= e($user['name']) ?>
                    </strong><br>
                    <?= e($user['email']) ?><br>
                    <?= e($user['phone'] ?? '') ?>
                </div>
            </div>
            <div>
                <div class="label">Delivery Address</div>
                <div class="value">
                    <?= nl2br(e($order['delivery_address'])) ?>
                </div>
            </div>
            <div>
                <div class="label">Payment Method</div>
                <div class="value">
                    <?= strtoupper(e($order['payment_method'])) ?>
                </div>
            </div>
            <div>
                <div class="label">Payment Status</div>
                <div class="value">
                    <span class="status-badge badge-<?= $order['payment_status'] ?>">
                        <?= ucfirst(e($order['payment_status'])) ?>
                    </span>
                </div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Item</th>
                    <th style="text-align:center;">Qty</th>
                    <th style="text-align:right;">Unit Price</th>
                    <th style="text-align:right;">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $i => $it): ?>
                    <tr>
                        <td style="color:#94a3b8;">
                            <?= $i + 1 ?>
                        </td>
                        <td>
                            <?= e($it['product_name']) ?>
                        </td>
                        <td style="text-align:center;">
                            <?= $it['quantity'] ?>
                        </td>
                        <td style="text-align:right;">
                            <?= CURRENCY_SYMBOL . number_format($it['unit_price'], 2) ?>
                        </td>
                        <td style="text-align:right;font-weight:600;">
                            <?= CURRENCY_SYMBOL . number_format($it['total_price'], 2) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="totals">
            <div class="total-row"><span>Subtotal</span><span>
                    <?= CURRENCY_SYMBOL . number_format($order['subtotal'], 2) ?>
                </span></div>
            <?php if ($order['discount_amount'] > 0): ?>
                <div class="total-row" style="color:#16a34a;"><span>Discount (
                        <?= e($order['coupon_code']) ?>)
                    </span><span>–
                        <?= CURRENCY_SYMBOL . number_format($order['discount_amount'], 2) ?>
                    </span></div>
            <?php endif; ?>
            <div class="total-row"><span>Delivery Fee</span><span>
                    <?= $order['delivery_fee'] > 0 ? CURRENCY_SYMBOL . number_format($order['delivery_fee'], 2) : 'FREE' ?>
                </span></div>
            <div class="total-row final"><span>Grand Total</span><span>
                    <?= CURRENCY_SYMBOL . number_format($order['total_amount'], 2) ?>
                </span></div>
        </div>

        <div class="footer">
            <p>Thank you for ordering from Bluemoon! 🌙</p>
            <p style="margin-top:6px;">For support:
                <?= SITE_EMAIL ?> ·
                <?= SITE_PHONE ?>
            </p>
        </div>
    </div>
</body>

</html>