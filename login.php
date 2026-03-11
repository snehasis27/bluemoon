<?php
require_once __DIR__ . '/config.php';
if (isLoggedIn())
    redirect(BASE_URL . '/');

$error = '';
$next = $_GET['redirect'] ?? $_GET['next'] ?? BASE_URL . '/';
// Sanitize: only allow relative paths or same-site URLs
if (!str_starts_with($next, '/') && !str_starts_with($next, BASE_URL)) {
    $next = BASE_URL . '/';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Invalid form token. Please refresh and try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $pass = $_POST['password'] ?? '';
        if (!$email || !$pass) {
            $error = 'Please enter your email and password.';
        } else {
            $db = getDB();
            $stmt = $db->prepare('SELECT * FROM users WHERE email=? AND is_active=1 LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            if ($user && password_verify($pass, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = $user['role'];
                setFlash('success', 'Welcome back, ' . $user['name'] . '! 👋');
                // Role-based redirect
                match ($user['role']) {
                    'admin' => redirect(BASE_URL . '/admin/'),
                    'delivery' => redirect(BASE_URL . '/delivery/'),
                    default => redirect($next !== BASE_URL . '/' ? $next : BASE_URL . '/')
                };
            } else {
                $error = 'Invalid email or password.';
            }
        }
    }
}

$pageTitle = 'Login';
require_once __DIR__ . '/templates/header.php';
?>
<div
    style="min-height:calc(100vh - var(--nav-h));display:flex;align-items:center;justify-content:center;padding:48px 20px;">
    <div style="width:100%;max-width:440px;">
        <!-- Card -->
        <div class="card" style="padding:40px;">
            <div style="text-align:center;margin-bottom:32px;">
                <div style="font-size:48px;margin-bottom:12px;">🌙</div>
                <h1 style="font-family:var(--font-head);font-size:26px;font-weight:700;">Welcome back!</h1>
                <p style="color:var(--text-muted);font-size:14px;margin-top:6px;">Sign in to your Bluemoon account</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger" style="margin-bottom:20px;">
                    <i class="fa fa-circle-xmark"></i>
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="loginForm">
                <?= csrf_field() ?>
                <input type="hidden" name="next" value="<?= e($next) ?>">

                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <div class="input-group">
                        <i class="fa fa-envelope input-icon"></i>
                        <input type="email" name="email" id="email" class="form-control" placeholder="you@example.com"
                            value="<?= e($_POST['email'] ?? '') ?>" required autocomplete="email">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-group">
                        <i class="fa fa-lock input-icon"></i>
                        <input type="password" name="password" id="password" class="form-control"
                            placeholder="Your password" required autocomplete="current-password">
                        <i class="fa fa-eye input-suffix" id="togglePass"></i>
                    </div>
                </div>

                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;">
                    <label class="custom-check">
                        <input type="checkbox" name="remember">
                        <span class="check-box"></span>
                        <span style="font-size:13px;color:var(--text-secondary);">Remember me</span>
                    </label>
                    <a href="<?= BASE_URL ?>/forgot_password.php" style="font-size:13px;color:var(--primary);">Forgot
                        password?</a>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg" data-orig-text="Sign In">
                    <i class="fa fa-right-to-bracket"></i> Sign In
                </button>
            </form>

            <div style="text-align:center;margin-top:24px;padding-top:24px;border-top:1px solid var(--glass-border);">
                <p style="font-size:14px;color:var(--text-muted);">
                    Don't have an account? <a href="<?= BASE_URL ?>/register.php"
                        style="color:var(--primary);font-weight:600;">Sign Up</a>
                </p>
            </div>

            <!-- Demo credentials info -->
            <div class="alert alert-info" style="margin-top:20px;font-size:13px;">
                <div><strong>Demo accounts:</strong></div>
                <div>Admin: admin@bluemoon.com / Admin@123</div>
                <div>Delivery: delivery@bluemoon.com / Delivery@123</div>
                <div>Customer: customer@bluemoon.com / Customer@123</div>
            </div>
        </div>
    </div>
</div>

<script>
    // Toggle password visibility
    document.getElementById('togglePass')?.addEventListener('click', function () {
        const input = document.getElementById('password');
        input.type = input.type === 'password' ? 'text' : 'password';
        this.classList.toggle('fa-eye'); this.classList.toggle('fa-eye-slash');
    });
</script>
<?php require_once __DIR__ . '/templates/footer.php'; ?>