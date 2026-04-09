<?php
// checkout.php - ALL logic BEFORE header.php to prevent "headers already sent"
require_once 'includes/db.php';
if (!isLoggedIn()) redirect('login.php?redirect=checkout.php');

$cart = $_SESSION['cart'] ?? [];
if (empty($cart) && !isset($_GET['success'])) redirect('cart.php');

$user_id = $_SESSION['user_id'];
$user = $conn->query("SELECT * FROM users WHERE id=$user_id")->fetch_assoc();

$total = 0;
foreach ($cart as $item) $total += $item['price'] * $item['qty'];

$error = '';
$order_done = false;
$ord = null;

// Handle POST before any output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $use_saved = isset($_POST['use_saved_address']) && $_POST['use_saved_address'] == '1';
    $receiver  = sanitize($conn, $_POST['receiver_name'] ?? '');
    $phone     = sanitize($conn, $_POST['receiver_phone'] ?? '');
    $address   = $use_saved ? $user['address']  : sanitize($conn, $_POST['shipping_address'] ?? '');
    $ward      = $use_saved ? $user['ward']      : sanitize($conn, $_POST['ward'] ?? '');
    $district  = $use_saved ? $user['district']  : sanitize($conn, $_POST['district'] ?? '');
    $city      = $use_saved ? $user['city']      : sanitize($conn, $_POST['city'] ?? '');
    $payment   = sanitize($conn, $_POST['payment_method'] ?? 'cash');
    $notes     = sanitize($conn, $_POST['notes'] ?? '');

    if (!$receiver || !$phone || !$address || !$ward || !$district || !$city) {
        $error = 'Vui lòng điền đầy đủ thông tin giao hàng.';
    } elseif (!preg_match('/^0[0-9]{9,10}$/', $phone)) {
        $error = 'Số điện thoại không hợp lệ. Phải bắt đầu bằng số 0 và có 10-11 chữ số.';
    } else {
        $conn->begin_transaction();

        try {
            // 1. Tạo order trước
            $order_code = generateCode('DH');
            $stmtOrder = $conn->prepare("
                INSERT INTO orders 
                (order_code,user_id,receiver_name,receiver_phone,shipping_address,ward,district,city,payment_method,total_amount,notes) 
                VALUES (?,?,?,?,?,?,?,?,?,?,?)
            ");
            $stmtOrder->bind_param('sisssssssds', $order_code, $user_id, $receiver, $phone, $address, $ward, $district, $city, $payment, $total, $notes);
            
            if (!$stmtOrder->execute()) {
                throw new Exception("Không tạo được đơn hàng");
            }

            $order_id = $conn->insert_id;

            // 2. Reserve hàng
            foreach ($cart as $item) {
                $pid = (int)$item['product_id'];
                $qty = (int)$item['qty'];

                $stmtReserve = $conn->prepare("
                    UPDATE products
                    SET reserved_quantity = reserved_quantity + ?
                    WHERE id = ?
                    AND (stock_quantity - reserved_quantity) >= ?
                ");
                $stmtReserve->bind_param("iii", $qty, $pid, $qty);
                $stmtReserve->execute();

                if ($stmtReserve->affected_rows == 0) {
                    throw new Exception("Sản phẩm ID $pid không đủ hàng");
                }

                // 3. Insert order details
                $price = (float)$item['price'];
                $conn->query("
                    INSERT INTO order_details (order_id,product_id,quantity,unit_price) 
                    VALUES ($order_id,$pid,$qty,$price)
                ");
            }


            $conn->commit();

            $_SESSION['cart'] = [];
            redirect('checkout.php?success=' . $order_id);

        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}

// Success page via PRG
if (isset($_GET['success'])) {
    $oid = (int)$_GET['success'];
    $ord = $conn->query("SELECT * FROM orders WHERE id=$oid AND user_id=$user_id")->fetch_assoc();
    if ($ord) $order_done = true;
}

$pageTitle = $order_done ? 'Đặt hàng thành công' : 'Thanh toán';
require_once 'includes/header.php';
?>
<div class="container my-4">
    <h3 class="section-title mb-4"><?= $order_done ? 'Xác nhận đơn hàng' : 'Thanh Toán' ?></h3>

    <?php if ($order_done): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-check-circle-fill" style="font-size:5rem;color:#28a745"></i>
            <h3 class="text-success fw-bold mt-3">Đặt hàng thành công!</h3>
            <p class="text-muted mb-1">Mã đơn hàng: <strong style="color:#ff6b35"><?= htmlspecialchars($ord['order_code']) ?></strong></p>
            <p class="text-muted mb-4">Chúng tôi sẽ liên hệ xác nhận đơn hàng sớm nhất.</p>
            <div class="card mx-auto text-start" style="max-width:520px">
                <div class="card-header fw-bold bg-light">Tóm tắt đơn hàng</div>
                <div class="card-body">
                    <?php
                    $details = $conn->query("SELECT od.*, p.name FROM order_details od JOIN products p ON od.product_id=p.id WHERE od.order_id={$ord['id']}");
                    while ($d = $details->fetch_assoc()):
                    ?>
                    <div class="d-flex justify-content-between mb-1 small">
                        <span><?= htmlspecialchars($d['name']) ?> <span class="text-muted">×<?= $d['quantity'] ?></span></span>
                        <strong><?= formatPrice($d['unit_price'] * $d['quantity']) ?></strong>
                    </div>
                    <?php endwhile; ?>
                    <hr class="my-2">
                    <div class="d-flex justify-content-between fw-bold" style="color:#ff6b35">
                        <span>Tổng cộng:</span><span><?= formatPrice($ord['total_amount']) ?></span>
                    </div>
                    <hr class="my-2">
                    <div class="small text-muted">
                        <p class="mb-1"><i class="bi bi-person me-1"></i><?= htmlspecialchars($ord['receiver_name']) ?> · <?= htmlspecialchars($ord['receiver_phone']) ?></p>
                        <p class="mb-1"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($ord['shipping_address'].', '.$ord['ward'].', '.$ord['district'].', '.$ord['city']) ?></p>
                        <?php $pm=['cash'=>'Tiền mặt (COD)','transfer'=>'Chuyển khoản','online'=>'Trực tuyến']; ?>
                        <p class="mb-0"><i class="bi bi-credit-card me-1"></i><?= $pm[$ord['payment_method']] ?></p>
                        <?php if ($ord['payment_method']==='transfer'): ?>
                        <div class="alert alert-info mt-2 py-2 small mb-0">
                            <strong>Chuyển khoản:</strong> Vietcombank · STK: 1234567890 · Chủ TK: SNEAKER SHOP<br>
                            Nội dung: <strong><?= htmlspecialchars($ord['order_code']) ?></strong>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="mt-4 d-flex gap-2 justify-content-center">
                <a href="my_orders.php" class="btn btn-primary"><i class="bi bi-bag-check me-2"></i>Xem đơn hàng</a>
                <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-house me-2"></i>Về trang chủ</a>
            </div>
        </div>
    </div>

    <?php else: ?>
    <?php if ($error): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i><?= $error ?></div>
    <?php endif; ?>
    <form method="POST" id="checkoutForm" onsubmit="return validateCheckout()">
        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header fw-bold bg-white border-0"><i class="bi bi-truck me-2"></i>Thông tin giao hàng</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Người nhận <span class="text-danger">*</span></label>
                                <input type="text" name="receiver_name" class="form-control" value="<?= htmlspecialchars($user['full_name']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Số điện thoại <span class="text-danger">*</span></label>
                                <input type="text" name="receiver_phone" class="form-control" value="<?= htmlspecialchars($user['phone']) ?>" required>
                            </div>
                        </div>
                        <div class="mt-3">
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="useSaved" name="use_saved_address" value="1" checked onchange="toggleAddress(this)">
                                <label class="form-check-label" for="useSaved">Dùng địa chỉ đã lưu trong tài khoản</label>
                            </div>
                            <div id="savedAddress" class="p-3 bg-light rounded mb-2">
                                <i class="bi bi-geo-alt me-2 text-muted"></i>
                                <strong><?= htmlspecialchars($user['address'].', '.$user['ward'].', '.$user['district'].', '.$user['city']) ?></strong>
                            </div>
                            <div id="newAddress" style="display:none">
                                <div class="row g-2">
                                    <div class="col-12">
                                        <input type="text" name="shipping_address" class="form-control" placeholder="Số nhà, tên đường">
                                    </div>

                                    <div class="col-md-4">
                                        <select name="city" id="city" class="form-control">
                                            <option value="">Chọn Tỉnh/TP</option>
                                        </select>
                                    </div>

                                    <div class="col-md-4">
                                        <select name="district" id="district" class="form-control">
                                            <option value="">Chọn Quận/Huyện</option>
                                        </select>
                                    </div>

                                    <div class="col-md-4">
                                        <select name="ward" id="ward" class="form-control">
                                            <option value="">Chọn Phường/Xã</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <label class="form-label">Ghi chú (tùy chọn)</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Yêu cầu đặc biệt, giờ giao..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="card border-0 shadow-sm">
                    <div class="card-header fw-bold bg-white border-0"><i class="bi bi-credit-card me-2"></i>Phương thức thanh toán</div>
                    <div class="card-body">
                        <div class="form-check mb-3 p-3 border rounded">
                            <input class="form-check-input" type="radio" name="payment_method" id="cash" value="cash" checked onchange="showPayment('cash')">
                            <label class="form-check-label fw-semibold" for="cash"><i class="bi bi-cash-coin me-2 text-success"></i>Tiền mặt khi nhận hàng (COD)</label>
                        </div>
                        <div class="form-check mb-3 p-3 border rounded">
                            <input class="form-check-input" type="radio" name="payment_method" id="transfer" value="transfer" onchange="showPayment('transfer')">
                            <label class="form-check-label fw-semibold" for="transfer"><i class="bi bi-bank me-2 text-primary"></i>Chuyển khoản ngân hàng</label>
                        </div>
                        <div id="transferInfo" class="alert alert-info small py-2 mb-3" style="display:none">
                            Vietcombank · STK: <strong>1234567890</strong> · Chủ TK: SNEAKER SHOP
                        </div>
                        <div class="form-check p-3 border rounded">
                            <input class="form-check-input" type="radio" name="payment_method" id="online" value="online" onchange="showPayment('online')">
                            <label class="form-check-label fw-semibold" for="online"><i class="bi bi-phone me-2 text-warning"></i>Thanh toán trực tuyến (MoMo, VNPay...)</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm" style="position:sticky;top:80px">
                    <div class="card-header fw-bold text-white" style="background:#ff6b35"><i class="bi bi-receipt me-2"></i>Đơn hàng của bạn</div>
                    <div class="card-body">
                        <?php foreach ($cart as $item): ?>
                        <div class="d-flex justify-content-between mb-2 small">
                            <span class="text-truncate me-2" style="max-width:200px"><?= htmlspecialchars($item['name']) ?> <span class="badge bg-secondary"><?= $item['qty'] ?></span></span>
                            <strong class="text-nowrap"><?= formatPrice($item['price'] * $item['qty']) ?></strong>
                        </div>
                        <?php endforeach; ?>
                        <hr>
                        <div class="d-flex justify-content-between fw-bold fs-5">
                            <span>Tổng cộng:</span>
                            <span style="color:#ff6b35"><?= formatPrice($total) ?></span>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 mt-3 py-2 fw-semibold">
                            <i class="bi bi-bag-check me-2"></i>Đặt hàng ngay
                        </button>
                        <a href="cart.php" class="btn btn-outline-secondary w-100 mt-2 btn-sm">
                            <i class="bi bi-arrow-left me-1"></i>Quay lại giỏ hàng
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </form>
    <?php endif; ?>
</div>
<script>
function toggleAddress(cb) {
    document.getElementById('savedAddress').style.display = cb.checked ? 'block' : 'none';
    document.getElementById('newAddress').style.display   = cb.checked ? 'none'  : 'block';
}
function showPayment(m) {
    document.getElementById('transferInfo').style.display = m === 'transfer' ? 'block' : 'none';
}
function validateCheckout() {
    const phone = document.querySelector('[name=receiver_phone]').value.trim();
    if (!/^0[0-9]{9,10}$/.test(phone)) {
        alert('Số điện thoại không hợp lệ!\nPhải bắt đầu bằng số 0 và có 10-11 chữ số.');
        return false;
    }
    if (!document.getElementById('useSaved').checked) {
        const f = ['shipping_address','ward','district','city'];
        for (let n of f) { if (!document.querySelector('[name='+n+']').value.trim()) { alert('Vui lòng điền đầy đủ địa chỉ giao hàng!'); return false; } }
    }
    return true;
}
</script>
<script>
document.addEventListener("DOMContentLoaded", function () {

    const cityEl = document.getElementById("city");
    const districtEl = document.getElementById("district");
    const wardEl = document.getElementById("ward");

    // Load tỉnh
    fetch('https://provinces.open-api.vn/api/p/')
        .then(res => res.json())
        .then(data => {
            data.forEach(p => {
                cityEl.innerHTML += `<option value="${p.name}" data-code="${p.code}">${p.name}</option>`;
            });
        });

    // Khi chọn tỉnh → load quận
    cityEl.addEventListener("change", function () {
        const code = this.options[this.selectedIndex].dataset.code;

        districtEl.innerHTML = '<option value="">Chọn Quận/Huyện</option>';
        wardEl.innerHTML = '<option value="">Chọn Phường/Xã</option>';

        if (!code) return;

        fetch(`https://provinces.open-api.vn/api/p/${code}?depth=2`)
            .then(res => res.json())
            .then(data => {
                data.districts.forEach(d => {
                    districtEl.innerHTML += `<option value="${d.name}" data-code="${d.code}">${d.name}</option>`;
                });
            });
    });

    // Khi chọn quận → load phường
    districtEl.addEventListener("change", function () {
        const code = this.options[this.selectedIndex].dataset.code;

        wardEl.innerHTML = '<option value="">Chọn Phường/Xã</option>';

        if (!code) return;

        fetch(`https://provinces.open-api.vn/api/d/${code}?depth=2`)
            .then(res => res.json())
            .then(data => {
                data.wards.forEach(w => {
                    wardEl.innerHTML += `<option value="${w.name}">${w.name}</option>`;
                });
            });
    });

});
</script>
<?php require_once 'includes/footer.php'; ?>