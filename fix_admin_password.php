<?php
/**
 * One-time script to fix admin/demo user passwords in the database.
 * DELETE THIS FILE after running it!
 * Access: http://localhost/bluemoon/fix_admin_password.php
 */
require_once __DIR__ . '/config.php';

$users = [
    ['email' => 'admin@bluemoon.com', 'plain' => 'Admin@123'],
    ['email' => 'delivery@bluemoon.com', 'plain' => 'Delivery@123'],
    ['email' => 'customer@bluemoon.com', 'plain' => 'Customer@123'],
];

$db = getDB();
$results = [];

foreach ($users as $u) {
    $hash = password_hash($u['plain'], PASSWORD_BCRYPT, ['cost' => 10]);
    $stmt = $db->prepare("UPDATE users SET password = ? WHERE email = ?");
    $stmt->execute([$hash, $u['email']]);
    $affected = $stmt->rowCount();
    $results[] = [
        'email' => $u['email'],
        'password' => $u['plain'],
        'updated' => $affected,
        'hash' => $hash,
    ];
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>Fix Passwords</title>
    <style>
        body {
            font-family: monospace;
            background: #111;
            color: #eee;
            padding: 20px;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        th,
        td {
            border: 1px solid #444;
            padding: 8px 12px;
            text-align: left;
        }

        th {
            background: #222;
        }

        .ok {
            color: #4f4;
        }

        .warn {
            color: #f84;
        }

        .hash {
            font-size: 11px;
            color: #aaa;
            word-break: break-all;
        }
    </style>
</head>

<body>
    <h2>🔧 Bluemoon – Password Fix Utility</h2>
    <table>
        <tr>
            <th>Email</th>
            <th>Password</th>
            <th>Status</th>
            <th>New Hash</th>
        </tr>
        <?php foreach ($results as $r): ?>
            <tr>
                <td>
                    <?= htmlspecialchars($r['email']) ?>
                </td>
                <td>
                    <?= htmlspecialchars($r['password']) ?>
                </td>
                <td class="<?= $r['updated'] ? 'ok' : 'warn' ?>">
                    <?= $r['updated'] ? '✅ Updated' : '⚠️ Not found (check email)' ?>
                </td>
                <td class="hash">
                    <?= htmlspecialchars($r['hash']) ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
    <br>
    <p class="warn">⚠️ <strong>DELETE this file immediately after running!</strong><br>
        Path: <code><?= __FILE__ ?></code></p>
    <br>
    <p>✅ Done! <a href="<?= BASE_URL ?>/login.php" style="color:#8af;">Go to Login →</a></p>
    <hr>
    <p style="color:#888;font-size:12px;">Hashes generated with <code>PASSWORD_BCRYPT cost=10</code></p>
</body>

</html>