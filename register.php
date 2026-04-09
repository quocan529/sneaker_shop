<?php
// register.php
require_once 'includes/db.php';
// db.php started the USER session
if (isLoggedIn()) redirect('index.php');

$error = '';
$success = '';

// Read success flash from GET (after PRG redirect - form is now empty)
if (isset($_GET['success'])) {
    $success = 'Đăng ký thành công! <a href="login.php">Đăng nhập ngay</a>';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($conn, $_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';
    $fullname = sanitize($conn, $_POST['full_name'] ?? '');
    $email    = sanitize($conn, $_POST['email'] ?? '');
    $phone    = sanitize($conn, $_POST['phone'] ?? '');
    $address  = sanitize($conn, $_POST['address'] ?? '');
    $ward     = sanitize($conn, $_POST['ward'] ?? '');
    $district = sanitize($conn, $_POST['district'] ?? '');
    $city     = sanitize($conn, $_POST['city'] ?? '');

    if (!$username || !$password || !$fullname || !$email || !$phone || !$address || !$ward || !$district || !$city) {
        $error = 'Vui lòng điền đầy đủ thông tin.';
    } elseif (strlen($password) < 6) {
        $error = 'Mật khẩu phải có ít nhất 6 ký tự.';
    } elseif ($password !== $confirm) {
        $error = 'Mật khẩu xác nhận không khớp.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email không hợp lệ.';
    } elseif (!preg_match('/^0[0-9]{9,10}$/', $phone)) {
        $error = 'Số điện thoại phải bắt đầu bằng số 0 và có 10-11 chữ số (VD: 0901234567).';
    } else {
        $check = $conn->query("SELECT id FROM users WHERE username='$username'");
        if ($check->num_rows > 0) {
            $error = 'Tên đăng nhập đã tồn tại.';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username,password,full_name,email,phone,address,ward,district,city,role) VALUES (?,?,?,?,?,?,?,?,?,'customer')");
            $stmt->bind_param('sssssssss', $username, $hashed, $fullname, $email, $phone, $address, $ward, $district, $city);
            if ($stmt->execute()) {
                // PRG: redirect to clear form fields completely
                redirect('register.php?success=1');
            } else {
                $error = 'Có lỗi xảy ra, vui lòng thử lại.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký - SneakerShop</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background: linear-gradient(135deg,#1a1a2e,#0f3460); min-height:100vh; padding: 30px 0; }
        .card { border-radius: 16px; }
        .btn-primary { background:#ff6b35; border-color:#ff6b35; }
        .btn-primary:hover { background:#e55a24; border-color:#e55a24; }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="text-center mb-4">
                    <a href="index.php" class="text-decoration-none">
                        <h2 class="fw-bold text-white"><i class="bi bi-lightning-fill" style="color:#ff6b35"></i> SneakerShop</h2>
                    </a>
                </div>
                <div class="card shadow-lg border-0 p-4">
                    <h4 class="text-center mb-4 fw-bold">Đăng ký tài khoản</h4>

                    <?php if ($error): ?>
                    <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                    <div class="alert alert-success"><?= $success ?></div>
                    <?php endif; ?>

                    <form method="POST" novalidate onsubmit="return validateForm()">
                        <h6 class="text-muted fw-bold mb-3">THÔNG TIN TÀI KHOẢN</h6>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Tên đăng nhập <span class="text-danger">*</span></label>
                                <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Họ và tên <span class="text-danger">*</span></label>
                                <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Mật khẩu <span class="text-danger">*</span></label>
                                <input type="password" name="password" id="password" class="form-control" autocomplete="new-password">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Xác nhận mật khẩu <span class="text-danger">*</span></label>
                                <input type="password" name="confirm_password" id="confirm_password" class="form-control" autocomplete="new-password">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="text" name="email" class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Số điện thoại <span class="text-danger">*</span></label>
                                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                            </div>
                        </div>

                        <h6 class="text-muted fw-bold mb-3">ĐỊA CHỈ GIAO HÀNG</h6>
                        <div class="row g-3 mb-4">
                            <div class="col-12">
                                <label class="form-label">Địa chỉ (số nhà, tên đường) <span class="text-danger">*</span></label>
                                <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($_POST['address'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Tỉnh/Thành phố <span class="text-danger">*</span></label>
                                <select id="city" name="city" class="form-control"></select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Quận/Huyện <span class="text-danger">*</span></label>
                                <select id="district" name="district" class="form-control"></select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Phường/Xã <span class="text-danger">*</span></label>
                                <select id="ward" name="ward" class="form-control"></select>
                            </div>

                        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                            <i class="bi bi-person-plus me-2"></i>Đăng ký
                        </button>
                    </form>
                    <hr>
                    <p class="text-center text-muted mb-0">Đã có tài khoản? <a href="login.php" style="color:#ff6b35">Đăng nhập</a></p>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function validateForm() {
        const errors = [];
        const username = document.querySelector('[name=username]').value.trim();
        const fullname = document.querySelector('[name=full_name]').value.trim();
        const email    = document.querySelector('[name=email]').value.trim();
        const phone    = document.querySelector('[name=phone]').value.trim();
        const pw       = document.getElementById('password').value;
        const cpw      = document.getElementById('confirm_password').value;
        const address  = document.querySelector('[name=address]').value.trim();
        const ward     = document.querySelector('[name=ward]').value.trim();
        const district = document.querySelector('[name=district]').value.trim();
        const city     = document.querySelector('[name=city]').value.trim();

        if (!username) errors.push('• Tên đăng nhập không được để trống');
        else if (username.length < 4) errors.push('• Tên đăng nhập phải có ít nhất 4 ký tự');
        else if (!/^[a-zA-Z0-9_]+$/.test(username)) errors.push('• Tên đăng nhập chỉ được chứa chữ cái, số và dấu gạch dưới');

        if (!fullname) errors.push('• Họ và tên không được để trống');

        if (!pw) errors.push('• Mật khẩu không được để trống');
        else if (pw.length < 6) errors.push('• Mật khẩu phải có ít nhất 6 ký tự');

        if (!cpw) errors.push('• Vui lòng xác nhận mật khẩu');
        else if (pw !== cpw) errors.push('• Mật khẩu xác nhận không khớp với mật khẩu đã nhập');

        if (!email) errors.push('• Email không được để trống');
        else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) errors.push('• Địa chỉ email không hợp lệ (VD: ten@email.com)');

        if (!phone) errors.push('• Số điện thoại không được để trống');
        else if (!/^0[0-9]{9,10}$/.test(phone)) errors.push('• Số điện thoại phải bắt đầu bằng số 0 và có 10-11 chữ số (VD: 0901234567)');

        if (!address) errors.push('• Địa chỉ (số nhà, tên đường) không được để trống');
        if (!ward) errors.push('• Phường/Xã không được để trống');
        if (!district) errors.push('• Quận/Huyện không được để trống');
        if (!city) errors.push('• Tỉnh/Thành phố không được để trống');

        if (errors.length > 0) {
            // Show errors in a styled div instead of alert
            let box = document.getElementById('jsErrors');
            if (!box) {
                box = document.createElement('div');
                box.id = 'jsErrors';
                document.querySelector('form').prepend(box);
            }
            box.className = 'alert alert-danger mb-3';
            box.innerHTML = '<strong><i class="bi bi-exclamation-circle me-2"></i>Vui lòng kiểm tra lại:</strong><ul class="mb-0 mt-2">'
                + errors.map(e => '<li>' + e.slice(2) + '</li>').join('') + '</ul>';
            box.scrollIntoView({behavior: 'smooth', block: 'center'});
            return false;
        }
        return true;
    }
    </script>
    <script>
    document.addEventListener("DOMContentLoaded", function () {

        const city    = document.getElementById("city");
        const district= document.getElementById("district");
        const ward    = document.getElementById("ward");

        // Load tỉnh
        fetch("https://provinces.open-api.vn/api/p/")
            .then(res => res.json())
            .then(data => {
                city.innerHTML = '<option value="">-- Chọn tỉnh/thành --</option>';
                data.forEach(p => {
                    city.innerHTML += `<option value="${p.name}" data-id="${p.code}">${p.name}</option>`;
                });
            });

        // Khi chọn tỉnh → load huyện
        city.addEventListener("change", function () {
            const code = this.options[this.selectedIndex].dataset.id;

            district.innerHTML = '<option value="">-- Chọn quận/huyện --</option>';


            if (!code) return;

            fetch(`https://provinces.open-api.vn/api/p/${code}?depth=2`)
                .then(res => res.json())
                .then(data => {
                    data.districts.forEach(d => {
                        district.innerHTML += `<option value="${d.name}" data-id="${d.code}">${d.name}</option>`;
                    });
                });
        });

        // Khi chọn huyện → load xã
        district.addEventListener("change", function () {
            const code = this.options[this.selectedIndex].dataset.id;

            ward.innerHTML = '<option value="">-- Chọn phường/xã --</option>';

            if (!code) return;

            fetch(`https://provinces.open-api.vn/api/d/${code}?depth=2`)
                .then(res => res.json())
                .then(data => {
                    data.wards.forEach(w => {
                        ward.innerHTML += `<option value="${w.name}">${w.name}</option>`;
                    });
                });
        });

    });
    </script>
</body>
</html>
