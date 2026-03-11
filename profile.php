<?php
require_once __DIR__ . '/config.php';
requireLogin();
$db = getDB();
$user = currentUser();
$error = '';
$success = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        if (strlen($name) < 2)
            $error = 'Name must be at least 2 characters.';
        elseif (!preg_match('/^\d{10}$/', $phone))
            $error = 'Enter a valid 10-digit phone number.';
        else {
            $db->prepare('UPDATE users SET name=?, phone=? WHERE id=?')->execute([$name, $phone, $user['id']]);
            $success = 'Profile updated successfully!';
        }
    } elseif ($action === 'change_password') {
        $cur = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $conf = $_POST['confirm_password'] ?? '';
        $full = $db->prepare('SELECT password FROM users WHERE id=?');
        $full->execute([$user['id']]);
        $full = $full->fetchColumn();
        if (!password_verify($cur, $full))
            $error = 'Current password is incorrect.';
        elseif (strlen($new) < 6)
            $error = 'New password must be at least 6 characters.';
        elseif ($new !== $conf)
            $error = 'Passwords do not match.';
        else {
            $hash = password_hash($new, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
            $db->prepare('UPDATE users SET password=? WHERE id=?')->execute([$hash, $user['id']]);
            $success = 'Password changed successfully!';
        }
    } elseif ($action === 'add_address') {
        $label = trim($_POST['label'] ?? 'Home');
        $aName = trim($_POST['a_name'] ?? $user['name']);
        $aPhone = trim($_POST['a_phone'] ?? $user['phone']);
        $line1 = trim($_POST['line1'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $pincode = trim($_POST['pincode'] ?? '');
        if ($line1 && $city && $pincode) {
            $db->prepare('INSERT INTO addresses (user_id,label,name,phone,line1,city,pincode) VALUES (?,?,?,?,?,?,?)')
                ->execute([$user['id'], $label, $aName, $aPhone, $line1, $city, $pincode]);
            $success = 'Address added!';
        } else
            $error = 'Please fill all address fields.';
    } elseif ($action === 'delete_address') {
        $aId = (int) ($_POST['address_id'] ?? 0);
        $db->prepare('DELETE FROM addresses WHERE id=? AND user_id=?')->execute([$aId, $user['id']]);
        $success = 'Address removed.';
    }
    // Refresh user
    $user = currentUser();
}

// Fetch addresses
$addrs = $db->prepare('SELECT * FROM addresses WHERE user_id=? ORDER BY is_default DESC, id DESC');
$addrs->execute([$user['id']]);
$addrs = $addrs->fetchAll();

// Order stats
$stats = $db->prepare('SELECT COUNT(*) AS total, SUM(total_amount) AS spent FROM orders WHERE user_id=?');
$stats->execute([$user['id']]);
$stats = $stats->fetch();

$pageTitle = 'My Profile';
require_once __DIR__ . '/templates/header.php';
?>
<div class="page-header">
    <div class="container">
        <h1>👤 My Profile</h1>
        <p>Manage your account and addresses.</p>
    </div>
</div>

<div class="container section-sm">
    <?php if ($error): ?>
        <div class="alert alert-danger" style="margin-bottom:20px;">
            <?= e($error) ?>
        </div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success" style="margin-bottom:20px;">
            <?= e($success) ?>
        </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:300px 1fr;gap:32px;align-items:start;">
        <!-- Left: avatar + stats -->
        <div style="display:flex;flex-direction:column;gap:20px;">
            <div class="card" style="padding:32px;text-align:center;">
                <div class="avatar-circle" style="width:80px;height:80px;font-size:32px;margin:0 auto 16px;">
                    <?= strtoupper(substr($user['name'], 0, 1)) ?>
                </div>
                <div style="font-family:var(--font-head);font-size:20px;font-weight:700;">
                    <?= e($user['name']) ?>
                </div>
                <div style="font-size:13px;color:var(--text-muted);margin-top:4px;">
                    <?= e($user['email']) ?>
                </div>
                <div style="font-size:13px;color:var(--text-muted);">
                    <?= e($user['phone']) ?>
                </div>
                <div
                    style="display:flex;gap:16px;justify-content:center;margin-top:20px;padding-top:20px;border-top:1px solid var(--glass-border);">
                    <div style="text-align:center;">
                        <div style="font-weight:700;font-size:20px;">
                            <?= $stats['total'] ?>
                        </div>
                        <div style="font-size:12px;color:var(--text-muted);">Orders</div>
                    </div>
                    <div style="text-align:center;">
                        <div style="font-weight:700;font-size:20px;">
                            <?= CURRENCY_SYMBOL . number_format($stats['spent'] ?? 0, 0) ?>
                        </div>
                        <div style="font-size:12px;color:var(--text-muted);">Spent</div>
                    </div>
                </div>
            </div>
            <a href="<?= BASE_URL ?>/order_history.php" class="btn btn-outline"><i class="fa fa-clock-rotate-left"></i>
                Order History</a>
        </div>

        <!-- Right: forms -->
        <div style="display:flex;flex-direction:column;gap:24px;">
            <!-- Profile Info -->
            <div class="card" style="padding:28px;">
                <h2 style="font-family:var(--font-head);font-size:18px;font-weight:600;margin-bottom:20px;">Personal
                    Information</h2>
                <form method="POST">
                    <?= csrf_field() ?><input type="hidden" name="action" value="update_profile">
                    <div class="grid-2" style="gap:16px;">
                        <div class="form-group"><label class="form-label">Full Name</label>
                            <div class="input-group"><i class="fa fa-user input-icon"></i><input type="text" name="name"
                                    class="form-control" value="<?= e($user['name']) ?>" required minlength="2"></div>
                        </div>
                        <div class="form-group"><label class="form-label">Phone</label>
                            <div class="input-group"><i class="fa fa-phone input-icon"></i><input type="tel"
                                    name="phone" class="form-control" value="<?= e($user['phone']) ?>" pattern="\d{10}"
                                    maxlength="10"></div>
                        </div>
                    </div>
                    <div class="form-group"><label class="form-label">Email (read-only)</label><input type="email"
                            class="form-control" value="<?= e($user['email']) ?>" disabled></div>
                    <button type="submit" class="btn btn-primary"><i class="fa fa-floppy-disk"></i> Save
                        Changes</button>
                </form>
            </div>

            <!-- Change Password -->
            <div class="card" style="padding:28px;">
                <h2 style="font-family:var(--font-head);font-size:18px;font-weight:600;margin-bottom:20px;">Change
                    Password</h2>
                <form method="POST">
                    <?= csrf_field() ?><input type="hidden" name="action" value="change_password">
                    <div class="form-group"><label class="form-label">Current Password</label>
                        <div class="input-group"><i class="fa fa-lock input-icon"></i><input type="password"
                                name="current_password" class="form-control" required></div>
                    </div>
                    <div class="grid-2" style="gap:16px;">
                        <div class="form-group"><label class="form-label">New Password</label>
                            <div class="input-group"><i class="fa fa-lock input-icon"></i><input type="password"
                                    name="new_password" class="form-control" required minlength="6"></div>
                        </div>
                        <div class="form-group"><label class="form-label">Confirm</label>
                            <div class="input-group"><i class="fa fa-lock input-icon"></i><input type="password"
                                    name="confirm_password" class="form-control" required></div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fa fa-shield-halved"></i> Update
                        Password</button>
                </form>
            </div>

            <!-- Addresses -->
            <div class="card" style="padding:28px;">
                <h2 style="font-family:var(--font-head);font-size:18px;font-weight:600;margin-bottom:20px;">📍 Saved
                    Addresses</h2>
                <?php foreach ($addrs as $a): ?>
                    <div
                        style="padding:16px;border:1px solid var(--glass-border);border-radius:var(--radius-md);margin-bottom:12px;display:flex;justify-content:space-between;align-items:flex-start;">
                        <div>
                            <div style="font-weight:600;margin-bottom:4px;">
                                <?= e($a['label']) ?> –
                                <?= e($a['name']) ?>
                            </div>
                            <div style="font-size:13px;color:var(--text-muted);">
                                <?= e($a['line1']) ?>,
                                <?= e($a['city']) ?> –
                                <?= e($a['pincode']) ?>
                            </div>
                            <div style="font-size:13px;color:var(--text-muted);">
                                <?= e($a['phone']) ?>
                            </div>
                        </div>
                        <form method="POST" style="margin:0;">
                            <?= csrf_field() ?><input type="hidden" name="action" value="delete_address"><input
                                type="hidden" name="address_id" value="<?= $a['id'] ?>">
                            <button type="submit" class="btn btn-outline btn-sm" style="color:var(--danger);"
                                data-confirm="Remove this address?"><i class="fa fa-trash"></i></button>
                        </form>
                    </div>
                <?php endforeach; ?>

                <details style="margin-top:16px;">
                    <summary style="cursor:pointer;font-weight:600;font-size:14px;color:var(--primary);"><i
                            class="fa fa-plus"></i> Add New Address</summary>
                    <form method="POST" style="margin-top:16px;">
                        <?= csrf_field() ?><input type="hidden" name="action" value="add_address">
                        <div class="grid-2" style="gap:12px;">
                            <div class="form-group"><label class="form-label">Label</label><select name="label"
                                    class="form-control">
                                    <option>Home</option>
                                    <option>Work</option>
                                    <option>Other</option>
                                </select></div>
                            <div class="form-group"><label class="form-label">Contact Name</label><input type="text"
                                    name="a_name" class="form-control" value="<?= e($user['name']) ?>"></div>
                            <div class="form-group"><label class="form-label">Phone</label><input type="text"
                                    name="a_phone" class="form-control" value="<?= e($user['phone']) ?>" maxlength="10">
                            </div>
                            <div class="form-group"><label class="form-label">Pincode</label><input type="text"
                                    name="pincode" class="form-control" maxlength="6"></div>
                        </div>
                        <div class="form-group"><label class="form-label">Street Address</label><input type="text"
                                name="line1" class="form-control" placeholder="House no, Street, Area" required></div>
                        <div class="form-group"><label class="form-label">City</label><input type="text" name="city"
                                class="form-control" value="Kolkata" required></div>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-plus"></i> Add
                            Address</button>
                    </form>
                </details>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/templates/footer.php'; ?>