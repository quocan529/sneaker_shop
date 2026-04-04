<?php
// checkout.php
require_once 'includes/db.php';
if (!isLoggedIn()) redirect('login.php?redirect=checkout.php');

$user_id = $_SESSION['user_id'];
$user    = $conn->query("SELECT * FROM users WHERE id=$user_id")->fetch_assoc();

// ══════════════════════════════════════════════════════════════════
// REPAY FLOW — Đổi phương thức / thanh toán lại đơn awaiting_payment
// ══════════════════════════════════════════════════════════════════
$repay_order = null;
if (isset($_GET['repay'])) {
    $repay_id    = (int)$_GET['repay'];
    $repay_order = $conn->query(
        "SELECT * FROM orders WHERE id=$repay_id AND user_id=$user_id AND status='awaiting_payment'"
    )->fetch_assoc();
    if (!$repay_order) redirect('my_orders.php');
}

// POST: xử lý repay (đổi phương thức hoặc thanh toán lại ZaloPay)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['repay_order_id'])) {
    $repay_id  = (int)$_POST['repay_order_id'];
    $repay_ord = $conn->query(
        "SELECT * FROM orders WHERE id=$repay_id AND user_id=$user_id AND status='awaiting_payment'"
    )->fetch_assoc();
    if (!$repay_ord) redirect('my_orders.php');

    $payment    = sanitize($conn, $_POST['payment_method'] ?? 'cash');
    $online_sub = sanitize($conn, $_POST['online_sub'] ?? 'zalopay');

    if ($payment === 'online' && $online_sub === 'zalopay') {
        // Thanh toán lại qua ZaloPay → update payment_method, chuyển sang zalopay_create
        $conn->query("UPDATE orders SET payment_method='online' WHERE id=$repay_id");
        redirect('zalo_pay/zalopay_create.php?order_id=' . $repay_id);
    } else {
        // Đổi sang COD/Transfer → trừ tồn kho lúc này, status='pending'
        $items = $conn->query("SELECT product_id, quantity FROM order_details WHERE order_id=$repay_id");
        while ($item = $items->fetch_assoc()) {
            $pid = (int)$item['product_id'];
            $qty = (int)$item['quantity'];
            $conn->query("UPDATE products SET stock_quantity = stock_quantity - $qty WHERE id=$pid AND stock_quantity >= $qty");
        }
        $pm_safe = $conn->real_escape_string($payment);
        $conn->query("UPDATE orders SET payment_method='$pm_safe', status='pending', payment_status='pending' WHERE id=$repay_id");
        $_SESSION['cart'] = [];
        redirect('checkout.php?success=' . $repay_id);
    }
}

// ══════════════════════════════════════════════════════════════════
// NORMAL CHECKOUT FLOW
// ══════════════════════════════════════════════════════════════════
$cart = $_SESSION['cart'] ?? [];
// Cho phép vào nếu: có giỏ hàng, hoặc success page, hoặc đang repay
if (empty($cart) && !isset($_GET['success']) && !$repay_order) redirect('cart.php');

$total = 0;
foreach ($cart as $item) $total += $item['price'] * $item['qty'];

$error      = '';
$order_done = false;
$ord        = null;

// POST: tạo đơn mới
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['repay_order_id'])) {
    $use_saved  = isset($_POST['use_saved_address']) && $_POST['use_saved_address'] == '1';
    $receiver   = sanitize($conn, $_POST['receiver_name']    ?? '');
    $phone      = sanitize($conn, $_POST['receiver_phone']   ?? '');
    $address    = $use_saved ? $user['address']  : sanitize($conn, $_POST['shipping_address'] ?? '');
    $ward       = $use_saved ? $user['ward']      : sanitize($conn, $_POST['ward']             ?? '');
    $district   = $use_saved ? $user['district']  : sanitize($conn, $_POST['district']         ?? '');
    $city       = $use_saved ? $user['city']      : sanitize($conn, $_POST['city']             ?? '');
    $payment    = sanitize($conn, $_POST['payment_method'] ?? 'cash');
    $online_sub = sanitize($conn, $_POST['online_sub']     ?? 'zalopay');
    $notes      = sanitize($conn, $_POST['notes']          ?? '');

    if (!$receiver || !$phone || !$address || !$ward || !$district || !$city) {
        $error = 'Vui lòng điền đầy đủ thông tin giao hàng.';
    } elseif (!preg_match('/^0[0-9]{9,10}$/', $phone)) {
        $error = 'Số điện thoại không hợp lệ. Phải bắt đầu bằng số 0 và có 10-11 chữ số.';
    } else {
        $order_code = generateCode('DH');

        // ZaloPay: tạo đơn với status='awaiting_payment', KHÔNG trừ tồn kho
        // COD/Transfer: tạo đơn với status='pending', trừ tồn kho ngay
        $is_zalopay   = ($payment === 'online' && $online_sub === 'zalopay');
        $init_status  = $is_zalopay ? 'awaiting_payment' : 'pending';

        $stmt = $conn->prepare(
            "INSERT INTO orders (order_code,user_id,receiver_name,receiver_phone,shipping_address,ward,district,city,payment_method,total_amount,notes,status)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)"
        );
        $stmt->bind_param('sisssssssdss',
            $order_code, $user_id, $receiver, $phone,
            $address, $ward, $district, $city,
            $payment, $total, $notes, $init_status
        );

        if ($stmt->execute()) {
            $order_id = $conn->insert_id;
            foreach ($cart as $item) {
                $pid   = (int)$item['product_id'];
                $qty   = (int)$item['qty'];
                $price = (float)$item['price'];
                $conn->query("INSERT INTO order_details (order_id,product_id,quantity,unit_price) VALUES ($order_id,$pid,$qty,$price)");

                // Chỉ trừ tồn kho nếu KHÔNG phải ZaloPay
                if (!$is_zalopay) {
                    $conn->query("UPDATE products SET stock_quantity = stock_quantity - $qty WHERE id=$pid AND stock_quantity >= $qty");
                }
            }

            if ($is_zalopay) {
                // Giữ lại cart, chuyển sang trang thanh toán ZaloPay
                redirect('zalo_pay/zalopay_create.php?order_id=' . $order_id);
            }

            // COD / Transfer
            $_SESSION['cart'] = [];
            redirect('checkout.php?success=' . $order_id);
        } else {
            $error = 'Có lỗi khi tạo đơn hàng. Vui lòng thử lại.';
        }
    }
}

// Success page via PRG
if (isset($_GET['success'])) {
    $oid = (int)$_GET['success'];
    $ord = $conn->query("SELECT * FROM orders WHERE id=$oid AND user_id=$user_id")->fetch_assoc();
    if ($ord) $order_done = true;
}

$pageTitle = $order_done ? 'Đặt hàng thành công' : ($repay_order ? 'Thanh toán đơn hàng' : 'Thanh toán');
require_once 'includes/header.php';
?>

<div class="container my-4">
    <h3 class="section-title mb-4">
        <?= $order_done ? 'Xác nhận đơn hàng' : ($repay_order ? 'Thanh toán đơn ' . htmlspecialchars($repay_order['order_code']) : 'Thanh Toán') ?>
    </h3>

    <?php if ($order_done): ?>
    <!-- ✅ THÀNH CÔNG -->
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

    <?php elseif ($repay_order): ?>
    <!-- 🔄 REPAY FORM — Đổi phương thức / Thanh toán lại -->
    <?php if ($error): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i><?= $error ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="repay_order_id" value="<?= $repay_order['id'] ?>">
        <div class="row g-4">
            <div class="col-lg-7">
                <!-- Thông tin giao hàng (chỉ đọc) -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header fw-bold bg-white border-0"><i class="bi bi-truck me-2"></i>Thông tin giao hàng</div>
                    <div class="card-body">
                        <div class="p-3 bg-light rounded">
                            <p class="mb-1"><i class="bi bi-person me-2"></i><strong><?= htmlspecialchars($repay_order['receiver_name']) ?></strong> · <?= htmlspecialchars($repay_order['receiver_phone']) ?></p>
                            <p class="mb-0"><i class="bi bi-geo-alt me-2"></i><?= htmlspecialchars($repay_order['shipping_address'].', '.$repay_order['ward'].', '.$repay_order['district'].', '.$repay_order['city']) ?></p>
                        </div>
                    </div>
                </div>

                <!-- Phương thức thanh toán -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header fw-bold bg-white border-0"><i class="bi bi-credit-card me-2"></i>Chọn phương thức thanh toán</div>
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
                            <label class="form-check-label fw-semibold" for="online"><i class="bi bi-phone me-2 text-warning"></i>Thanh toán trực tuyến</label>
                        </div>
                        <div id="onlineSubOptions" style="display:none" class="mt-3 ps-2">
                            <input type="hidden" name="online_sub" value="zalopay">
                            <div class="d-flex align-items-center gap-2 p-2 border rounded" style="background:#f0f6ff;border-color:#0068ff!important">
                                <svg width="32" height="32" viewBox="0 0 36 36" fill="none"><rect width="36" height="36" rx="8" fill="#0068FF"/><text x="18" y="15" text-anchor="middle" font-size="6.5" font-weight="bold" fill="white" font-family="Arial">Zalo</text><text x="18" y="26" text-anchor="middle" font-size="6.5" font-weight="bold" fill="white" font-family="Arial">Pay</text></svg>
                                <span class="fw-semibold" style="color:#0068ff">ZaloPay</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card border-0 shadow-sm" style="position:sticky;top:80px">
                    <div class="card-header fw-bold text-white" style="background:#ff6b35"><i class="bi bi-receipt me-2"></i>Đơn hàng của bạn</div>
                    <div class="card-body">
                        <?php
                        $rep_items = $conn->query("SELECT od.*, p.name FROM order_details od JOIN products p ON od.product_id=p.id WHERE od.order_id={$repay_order['id']}");
                        while ($ri = $rep_items->fetch_assoc()):
                        ?>
                        <div class="d-flex justify-content-between mb-2 small">
                            <span class="text-truncate me-2" style="max-width:200px"><?= htmlspecialchars($ri['name']) ?> <span class="badge bg-secondary"><?= $ri['quantity'] ?></span></span>
                            <strong class="text-nowrap"><?= formatPrice($ri['unit_price'] * $ri['quantity']) ?></strong>
                        </div>
                        <?php endwhile; ?>
                        <hr>
                        <div class="d-flex justify-content-between fw-bold fs-5">
                            <span>Tổng cộng:</span>
                            <span style="color:#ff6b35"><?= formatPrice($repay_order['total_amount']) ?></span>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 mt-3 py-2 fw-semibold">
                            <i class="bi bi-bag-check me-2"></i>Xác nhận thanh toán
                        </button>
                        <a href="my_orders.php?id=<?= $repay_order['id'] ?>" class="btn btn-outline-secondary w-100 mt-2 btn-sm">
                            <i class="bi bi-arrow-left me-1"></i>Quay lại
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <?php else: ?>
    <!-- NORMAL CHECKOUT FORM -->
    <?php if ($error): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i><?= $error ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['zp_error'])): ?>
    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($_GET['zp_error']) ?></div>
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
                                    <div class="col-12"><input type="text" name="shipping_address" class="form-control" placeholder="Số nhà, tên đường"></div>
                                    <div class="col-md-4"><input type="text" name="ward" class="form-control" placeholder="Phường/Xã"></div>
                                    <div class="col-md-4"><input type="text" name="district" class="form-control" placeholder="Quận/Huyện"></div>
                                    <div class="col-md-4"><input type="text" name="city" class="form-control" placeholder="Tỉnh/TP"></div>
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
                        <div class="form-check p-3 border rounded" id="onlineBlock">
                            <input class="form-check-input" type="radio" name="payment_method" id="online" value="online" onchange="showPayment('online')">
                            <label class="form-check-label fw-semibold" for="online"><i class="bi bi-phone me-2 text-warning"></i>Thanh toán trực tuyến</label>
                        </div>
                        <div id="onlineSubOptions" style="display:none" class="mt-3">
                            <p class="text-muted small mb-2 ps-1">Chọn ví / cổng thanh toán:</p>
                            <div class="d-flex flex-column gap-2">
                                <label class="online-opt disabled-opt w-100" title="Sắp ra mắt">
                                    <input type="radio" name="online_sub" value="momo" disabled style="display:none">
                                    <div class="opt-row" style="opacity:.4;cursor:not-allowed">
                                        <svg width="36" height="36" viewBox="0 0 36 36" fill="none"><rect width="36" height="36" rx="8" fill="#A50064"/><text x="18" y="24" text-anchor="middle" font-size="12" font-weight="bold" fill="white" font-family="Arial">MoMo</text></svg>
                                        <span class="opt-name">MoMo</span>
                                        <span class="badge bg-secondary ms-auto" style="font-size:.65rem">Sắp ra mắt</span>
                                    </div>
                                </label>
                                <label class="online-opt disabled-opt w-100" title="Sắp ra mắt">
                                    <input type="radio" name="online_sub" value="vnpay" disabled style="display:none">
                                    <div class="opt-row" style="opacity:.4;cursor:not-allowed">
                                        <svg width="36" height="36" viewBox="0 0 36 36" fill="none"><rect width="36" height="36" rx="8" fill="#005BAA"/><text x="18" y="15" text-anchor="middle" font-size="7" font-weight="bold" fill="white" font-family="Arial">VN</text><text x="18" y="26" text-anchor="middle" font-size="7" font-weight="bold" fill="#E31B23" font-family="Arial">PAY</text></svg>
                                        <span class="opt-name">VNPay</span>
                                        <span class="badge bg-secondary ms-auto" style="font-size:.65rem">Sắp ra mắt</span>
                                    </div>
                                </label>
                                <label class="online-opt w-100 selected" id="zalopayOpt">
                                    <input type="radio" name="online_sub" value="zalopay" id="zalopayRadio" checked style="display:none">
                                    <div class="opt-row">
                                        <svg width="36" height="36" viewBox="0 0 36 36" fill="none"><rect width="36" height="36" rx="8" fill="#0068FF"/><text x="18" y="15" text-anchor="middle" font-size="6.5" font-weight="bold" fill="white" font-family="Arial">Zalo</text><text x="18" y="26" text-anchor="middle" font-size="6.5" font-weight="bold" fill="white" font-family="Arial">Pay</text></svg>
                                        <span class="opt-name" style="color:#0068ff;font-weight:600">ZaloPay</span>
                                        <span class="badge ms-auto" style="background:#0068ff;font-size:.65rem">Khả dụng</span>
                                    </div>
                                </label>
                            </div>
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

<style>
.online-opt { cursor: pointer; display: block; }
.opt-row { display:flex;align-items:center;gap:12px;border:2px solid #dee2e6;border-radius:10px;padding:10px 14px;background:#fff;transition:border-color .2s,box-shadow .2s; }
.opt-name { font-size:.95rem;font-weight:500;color:#333; }
.online-opt:not(.disabled-opt):hover .opt-row { border-color:#0068ff;background:#f0f6ff; }
.online-opt.selected .opt-row { border-color:#0068ff;background:#f0f6ff;box-shadow:0 0 0 3px rgba(0,104,255,.12); }
</style>

<script>
function toggleAddress(cb) {
    document.getElementById('savedAddress').style.display = cb.checked ? 'block' : 'none';
    document.getElementById('newAddress').style.display   = cb.checked ? 'none'  : 'block';
}
function showPayment(m) {
    document.getElementById('transferInfo').style.display    = m === 'transfer' ? 'block' : 'none';
    document.getElementById('onlineSubOptions').style.display = m === 'online'   ? 'block' : 'none';
}
document.addEventListener('DOMContentLoaded', function() {
    var zOpt = document.getElementById('zalopayOpt');
    if (zOpt) {
        zOpt.classList.add('selected');
        zOpt.addEventListener('click', function() {
            document.querySelectorAll('.online-opt:not(.disabled-opt)').forEach(el => el.classList.remove('selected'));
            this.classList.add('selected');
            document.getElementById('zalopayRadio').checked = true;
        });
    }
});
function validateCheckout() {
    const phone = document.querySelector('[name=receiver_phone]').value.trim();
    if (!/^0[0-9]{9,10}$/.test(phone)) { alert('Số điện thoại không hợp lệ!\nPhải bắt đầu bằng số 0 và có 10-11 chữ số.'); return false; }
    if (!document.getElementById('useSaved').checked) {
        const f = ['shipping_address','ward','district','city'];
        for (let n of f) { if (!document.querySelector('[name='+n+']').value.trim()) { alert('Vui lòng điền đầy đủ địa chỉ giao hàng!'); return false; } }
    }
    return true;
}
</script>
<?php require_once 'includes/footer.php'; ?>