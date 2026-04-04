<?php
// login.php
require_once 'includes/db.php';
// db.php started the USER session (sneaker_user_sess)

// Logout user session only
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $_SESSION = [];
    session_destroy();
    redirect('index.php');
}

// If already logged in as customer, go home
if (isLoggedIn()) redirect('index.php');

$error = '';
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'index.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($conn, $_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$username || !$password) {
        $error = 'Vui lòng nhập đầy đủ thông tin.';
    } else {
        $result = $conn->query("SELECT * FROM users WHERE username='$username' AND role='customer'");
        $user = $result->fetch_assoc();
        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] === 'locked') {
                $error = 'Tài khoản đã bị khóa. Vui lòng liên hệ quản trị viên.';
            } else {
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['username']  = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role']      = 'customer';
                redirect($redirect);
            }
        } else {
            $error = 'Tên đăng nhập hoặc mật khẩu không đúng.';
        }
    }
}

$pageTitle = 'Đăng nhập';
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - SneakerShop</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #1a1a2e, #0f3460);
            min-height: 100vh;
        }

        .card {
            border-radius: 16px;
        }

        .btn-primary {
            background: #ff6b35;
            border-color: #ff6b35;
        }

        .btn-primary:hover {
            background: #e55a24;
            border-color: #e55a24;
        }
    </style>
</head>

<body class="d-flex align-items-center justify-content-center">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-sm-10 col-md-6 col-lg-4">
                <div class="text-center mb-4">
                    <a href="index.php" class="text-decoration-none">
                        <h2 class="fw-bold text-white"><i class="bi bi-lightning-fill" style="color:#ff6b35"></i> SneakerShop</h2>
                    </a>
                </div>
                <div class="card shadow-lg border-0 p-4">
                    <h4 class="text-center mb-4 fw-bold">Đăng nhập</h4>

                    <?php if ($error): ?>
                        <div class="alert alert-danger py-2"><i class="bi bi-exclamation-circle me-2"></i><?= $error ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Tên đăng nhập</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person"></i></span>
                                <input type="text" name="username" class="form-control" placeholder="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Mật khẩu</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" name="password" class="form-control" autocomplete="current-password" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Đăng nhập
                        </button>
                    </form>

                    <hr>
                    <p class="text-center text-muted mb-0">Chưa có tài khoản?
                        <a href="register.php" style="color:#ff6b35">Đăng ký ngay</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>