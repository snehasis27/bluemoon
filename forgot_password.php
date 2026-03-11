<?php
require_once __DIR__ . '/config.php';
if (isLoggedIn())
    redirect(BASE_URL . '/');

$step = 'request'; // request | sent | reset | done
$error = '';
$token = $_GET['token'] ?? '';

// If token in URL, go to reset step
if ($token) {
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM users WHERE reset_token=? AND reset_token_expires > NOW() LIMIT 1');
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    $step = $user ? 'reset' : 'invalid';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Invalid token. Please refresh.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'request') {
            $email = trim($_POST['email'] ?? '');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid email address.';
            } else {
                $db = getDB();
                $stmt = $db->prepare('SELECT id,name FROM users WHERE email=? AND is_active=1 LIMIT 1');
                $stmt->execute([$email]);
                $u = $stmt->fetch();
                if ($u) {
                    $tok = bin2hex(random_bytes(32));
                    $expires = date('Y-m-d H:i:s', time() + 3600);
                    $db->prepare('UPDATE users SET reset_token=?, reset_token_expires=? WHERE id=?')
                        ->execute([$tok, $expires, $u['id']]);
                    $link = BASE_URL . '/forgot_password.php?token=' . $tok;
                    // In production, send email. For demo, show the link.
                    $_SESSION['reset_link'] = $link;
                    $_SESSION['reset_email'] = $email;
                }
                // Don't reveal whether email exists
                $step = 'sent';
            }
        }

        if ($action === 'reset') {
            $newPass = $_POST['password'] ?? '';
            $confPass = $_POST['confirm_password'] ?? '';
            $tok = $_POST['token'] ?? '';
            if (strlen($newPass) < 6) {
                $error = 'Password must be at least 6 characters.';
                $step = 'reset';
            } elseif ($newPass !== $confPass) {
                $error = 'Passwords do not match.';
                $step = 'reset';
            } else {
                $db = getDB();
                $stmt = $db->prepare('SELECT id FROM users WHERE reset_token=? AND reset_token_expires > NOW() LIMIT 1');
                $stmt->execute([$tok]);
                $u = $stmt->fetch();
                if ($u) {
                    $hash = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
                    $db->prepare('UPDATE users SET password=?, reset_token=NULL, reset_token_expires=NULL WHERE id=?')
                        ->execute([$hash, $u['id']]);
                    $step = 'done';
                } else {
                    $error = 'Invalid or expired link.';
                    $step = 'invalid';
                }
            }
        }
    }
}

$pageTitle = 'Reset Password';
require_once __DIR__ . '/templates/header.php';
?>
<div
    style="min-height:calc(100vh - var(--nav-h));display:flex;align-items:center;justify-content:center;padding:48px 20px;">
    <div style="width:100%;max-width:440px;">
        <div class="card" style="padding:40px;">

            <?php if ($step === 'request'): ?>
                <div style="text-align:center;margin-bottom:28px;">
                    <div style="font-size:48px;">🔐</div>
                    <h1 style="font-family:var(--font-head);font-size:24px;font-weight:700;margin-top:12px;">Forgot
                        Password?</h1>
                    <p style="color:var(--text-muted);font-size:14px;margin-top:6px;">Enter your email and we'll send a
                        reset link.</p>
                </div>
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <?= e($error) ?>
                    </div>
                <?php endif; ?>
                <form method="POST">
                    <?= csrf_field() ?><input type="hidden" name="action" value="request">
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <div class="input-group">
                            <i class="fa fa-envelope input-icon"></i>
                            <input type="email" name="email" class="form-control" placeholder="you@example.com" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block btn-lg"><i class="fa fa-paper-plane"></i> Send
                        Reset Link</button>
                </form>

            <?php elseif ($step === 'sent'): ?>
                <div style="text-align:center;">
                    <div style="font-size:56px;margin-bottom:16px;">📬</div>
                    <h2 style="font-family:var(--font-head);font-size:22px;font-weight:700;margin-bottom:12px;">Check Your
                        Email</h2>
                    <p style="color:var(--text-muted);font-size:14px;line-height:1.7;margin-bottom:20px;">
                        If an account exists for <strong style="color:var(--text-primary);">
                            <?= e($_SESSION['reset_email'] ?? '') ?>
                        </strong>,
                        a password reset link has been sent.
                    </p>
                    <?php if (!empty($_SESSION['reset_link'])): ?>
                        <div class="alert alert-info" style="text-align:left;font-size:13px;">
                            <strong>Demo – Reset Link:</strong><br>
                            <a href="<?= e($_SESSION['reset_link']) ?>" style="color:var(--primary);word-break:break-all;">
                                <?= e($_SESSION['reset_link']) ?>
                            </a>
                        </div>
                        <?php unset($_SESSION['reset_link']); endif; ?>
                </div>

            <?php elseif ($step === 'reset'): ?>
                <div style="text-align:center;margin-bottom:28px;">
                    <div style="font-size:48px;">🔑</div>
                    <h1 style="font-family:var(--font-head);font-size:24px;font-weight:700;margin-top:12px;">Set New
                        Password</h1>
                </div>
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <?= e($error) ?>
                    </div>
                <?php endif; ?>
                <form method="POST">
                    <?= csrf_field() ?><input type="hidden" name="action" value="reset">
                    <input type="hidden" name="token" value="<?= e($token) ?>">
                    <div class="form-group">
                        <label class="form-label">New Password</label>
                        <div class="input-group">
                            <i class="fa fa-lock input-icon"></i>
                            <input type="password" name="password" class="form-control" placeholder="Min 6 characters"
                                required minlength="6">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Confirm Password</label>
                        <div class="input-group">
                            <i class="fa fa-lock input-icon"></i>
                            <input type="password" name="confirm_password" class="form-control"
                                placeholder="Repeat password" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block btn-lg"><i class="fa fa-shield-halved"></i> Reset
                        Password</button>
                </form>

            <?php elseif ($step === 'done'): ?>
                <div style="text-align:center;">
                    <div style="font-size:56px;margin-bottom:16px;">✅</div>
                    <h2 style="font-family:var(--font-head);font-size:22px;font-weight:700;margin-bottom:12px;">Password
                        Reset!</h2>
                    <p style="color:var(--text-muted);font-size:14px;margin-bottom:24px;">Your password has been updated
                        successfully.</p>
                    <a href="<?= BASE_URL ?>/login.php" class="btn btn-primary btn-block"><i
                            class="fa fa-right-to-bracket"></i> Sign In Now</a>
                </div>

            <?php else: ?>
                <div style="text-align:center;">
                    <div style="font-size:56px;margin-bottom:16px;">❌</div>
                    <h2 style="font-family:var(--font-head);font-size:22px;font-weight:700;margin-bottom:12px;">Invalid or
                        Expired Link</h2>
                    <p style="color:var(--text-muted);font-size:14px;margin-bottom:24px;">This reset link has expired or is
                        invalid. Please request a new one.</p>
                    <a href="<?= BASE_URL ?>/forgot_password.php" class="btn btn-primary btn-block">Try Again</a>
                </div>
            <?php endif; ?>

            <div style="text-align:center;margin-top:24px;padding-top:20px;border-top:1px solid var(--glass-border);">
                <a href="<?= BASE_URL ?>/login.php" style="font-size:13px;color:var(--text-muted);">← Back to Login</a>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/templates/footer.php'; ?>