<?php
require_once __DIR__ . '/config.php';
if (isLoggedIn())
    redirect(BASE_URL . '/');

$step = 'form';   // form | otp
$error = '';
$info = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Invalid token. Please refresh.';
    } else {
        $action = $_POST['action'] ?? 'register';

        if ($action === 'register') {
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $pass = $_POST['password'] ?? '';
            $conf = $_POST['confirm_password'] ?? '';

            // Validate
            $errs = [];
            if (strlen($name) < 2)
                $errs[] = 'Name must be at least 2 characters.';
            if (!filter_var($email, FILTER_VALIDATE_EMAIL))
                $errs[] = 'Enter a valid email address.';
            if (strlen($pass) < 6)
                $errs[] = 'Password must be at least 6 characters.';
            if ($pass !== $conf)
                $errs[] = 'Passwords do not match.';
            if (!preg_match('/^\d{10}$/', $phone))
                $errs[] = 'Enter a valid 10-digit phone number.';

            if (!$errs) {
                $db = getDB();
                $exists = $db->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
                $exists->execute([$email]);
                if ($exists->fetch()) {
                    $error = 'An account with this email already exists.';
                } else {
                    $otp = generateOTP();
                    $expires = date('Y-m-d H:i:s', time() + OTP_EXPIRY_MINUTES * 60);
                    $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);

                    // Store pending registration in session
                    $_SESSION['pending_reg'] = compact('name', 'email', 'phone', 'hash', 'otp', 'expires');
                    $step = 'otp';
                    $info = "OTP sent to <strong>$email</strong>. (Demo OTP: <strong>$otp</strong>)";
                }
            } else {
                $error = implode(' ', $errs);
            }
        }

        if ($action === 'verify_otp') {
            $entered = trim(implode('', $_POST['otp_digits'] ?? []));
            $pending = $_SESSION['pending_reg'] ?? null;
            if (!$pending) {
                $error = 'Session expired. Please start again.';
            } elseif ($entered !== $pending['otp']) {
                $error = 'Incorrect OTP. Please try again.';
                $step = 'otp';
            } elseif (strtotime($pending['expires']) < time()) {
                $error = 'OTP expired. Please register again.';
                unset($_SESSION['pending_reg']);
            } else {
                $db = getDB();
                $stmt = $db->prepare('INSERT INTO users (name,email,phone,password,role,is_active,email_verified) VALUES (?,?,?,?,?,1,1)');
                $stmt->execute([$pending['name'], $pending['email'], $pending['phone'], $pending['hash'], 'customer']);
                $userId = $db->lastInsertId();
                unset($_SESSION['pending_reg']);
                session_regenerate_id(true);
                $_SESSION['user_id'] = $userId;
                $_SESSION['user_role'] = 'customer';
                setFlash('success', 'Welcome to Bluemoon, ' . $pending['name'] . '! 🎉');
                redirect(BASE_URL . '/');
            }
        }
    }
}

$pageTitle = 'Create Account';
require_once __DIR__ . '/templates/header.php';
?>
<div
    style="min-height:calc(100vh - var(--nav-h));display:flex;align-items:center;justify-content:center;padding:48px 20px;">
    <div style="width:100%;max-width:480px;">
        <div class="card" style="padding:40px;">

            <?php if ($step === 'form'): ?>
                <div style="text-align:center;margin-bottom:32px;">
                    <div style="font-size:48px;margin-bottom:12px;">🌙</div>
                    <h1 style="font-family:var(--font-head);font-size:26px;font-weight:700;">Create Account</h1>
                    <p style="color:var(--text-muted);font-size:14px;margin-top:6px;">Join Bluemoon and start ordering!</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><i class="fa fa-circle-xmark"></i>
                        <?= e($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" id="registerForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="register">
                    <div class="form-group">
                        <label class="form-label">Full Name</label>
                        <div class="input-group">
                            <i class="fa fa-user input-icon"></i>
                            <input type="text" name="name" class="form-control" placeholder="Your full name"
                                value="<?= e($_POST['name'] ?? '') ?>" required minlength="2">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <div class="input-group">
                            <i class="fa fa-envelope input-icon"></i>
                            <input type="email" name="email" class="form-control" placeholder="you@example.com"
                                value="<?= e($_POST['email'] ?? '') ?>" required autocomplete="email">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <div class="input-group">
                            <i class="fa fa-phone input-icon"></i>
                            <input type="tel" name="phone" class="form-control" placeholder="10-digit mobile number"
                                value="<?= e($_POST['phone'] ?? '') ?>" pattern="\d{10}" maxlength="10" required>
                        </div>
                    </div>
                    <div class="grid-2" style="gap:16px;">
                        <div class="form-group">
                            <label class="form-label">Password</label>
                            <div class="input-group">
                                <i class="fa fa-lock input-icon"></i>
                                <input type="password" name="password" id="pass1" class="form-control"
                                    placeholder="Min 6 chars" required minlength="6">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Confirm Password</label>
                            <div class="input-group">
                                <i class="fa fa-lock input-icon"></i>
                                <input type="password" name="confirm_password" id="pass2" class="form-control"
                                    placeholder="Repeat password" required>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block btn-lg">
                        <i class="fa fa-user-plus"></i> Create Account
                    </button>
                </form>

            <?php elseif ($step === 'otp'): ?>
                <div style="text-align:center;margin-bottom:32px;">
                    <div style="font-size:48px;margin-bottom:12px;">📧</div>
                    <h1 style="font-family:var(--font-head);font-size:24px;font-weight:700;">Verify Your Email</h1>
                    <?php if ($info): ?>
                        <div class="alert alert-info" style="margin-top:16px;">
                            <?= $info ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <?= e($error) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <form method="POST" id="otpForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="verify_otp">
                    <div style="display:flex;gap:10px;justify-content:center;margin-bottom:28px;" id="otpInputs">
                        <?php for ($i = 0; $i < 6; $i++): ?>
                            <input type="text" name="otp_digits[]" maxlength="1" class="form-control" id="otp<?= $i ?>"
                                style="width:50px;height:56px;text-align:center;font-size:22px;font-weight:700;padding:0;"
                                inputmode="numeric" pattern="\d" required>
                        <?php endfor; ?>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block btn-lg">
                        <i class="fa fa-circle-check"></i> Verify & Continue
                    </button>
                    <p style="text-align:center;margin-top:16px;font-size:13px;color:var(--text-muted);">
                        Didn't get it? <button type="button" id="resendOtp" class="btn btn-outline btn-sm" disabled>
                            Resend in <span id="otpTimer">60</span>s
                        </button>
                    </p>
                </form>

                <script>
                    // Auto-focus OTP inputs
                    const inputs = document.querySelectorAll('#otpInputs input');
                    inputs.forEach((el, i) => {
                        el.addEventListener('input', () => { if (el.value && i < inputs.length - 1) inputs[i + 1].focus(); });
                        el.addEventListener('keydown', e => { if (e.key === 'Backspace' && !el.value && i > 0) inputs[i - 1].focus(); });
                    });
                    startOtpCountdown(60, document.getElementById('otpTimer'), document.getElementById('resendOtp'));
                </script>
            <?php endif; ?>

            <div style="text-align:center;margin-top:24px;padding-top:24px;border-top:1px solid var(--glass-border);">
                <p style="font-size:14px;color:var(--text-muted);">
                    Already have an account? <a href="<?= BASE_URL ?>/login.php"
                        style="color:var(--primary);font-weight:600;">Sign In</a>
                </p>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/templates/footer.php'; ?>