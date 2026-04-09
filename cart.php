<?php
// cart.php - ALL logic BEFORE header.php (PRG pattern to fix ERR_CACHE_MISS)
require_once 'includes/db.php';
if (!isLoggedIn()) redirect('login.php?redirect=cart.php');

// Handle POST before any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update'])) {
        $qtys = $_POST['qty'] ?? [];
        foreach ($qtys as $idx => $qty) {
            $qty = (int)$qty;

            if (!isset($_SESSION['cart'][$idx])) continue;

            $pid = (int)$_SESSION['cart'][$idx]['product_id'];

            $p = $conn->query("
                SELECT stock_quantity, reserved_quantity 
                FROM products 
                WHERE id = $pid
            ")->fetch_assoc();

            $available = $p['stock_quantity'] - $p['reserved_quantity'];

            if ($qty <= 0) {
                unset($_SESSION['cart'][$idx]);
            } elseif ($qty > $available) {
                $_SESSION['cart'][$idx]['qty'] = max(1, $available);
            } else {
                $_SESSION['cart'][$idx]['qty'] = $qty;
            }
        }
        $_SESSION['cart'] = array_values($_SESSION['cart']);
        redirect('cart.php?msg=updated');
    }
    if (isset($_POST['remove'])) {
        $idx = (int)$_POST['remove'];
        array_splice($_SESSION['cart'], $idx, 1);
        redirect('cart.php?msg=removed');
    }
}

// Flash messages from GET (after PRG redirect)
$msg = '';
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'updated') $msg = '<div class="alert alert-success alert-dismissible"><i class="bi bi-check-circle me-2"></i>Đã cập nhật giỏ hàng.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    if ($_GET['msg'] === 'removed') $msg = '<div class="alert alert-info alert-dismissible"><i class="bi bi-trash me-2"></i>Đã xóa sản phẩm khỏi giỏ hàng.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}

$cart  = $_SESSION['cart'] ?? [];
$total = 0;
foreach ($cart as $item) $total += $item['price'] * $item['qty'];

$pageTitle = 'Giỏ hàng';
require_once 'includes/header.php';
?>

<div class="container my-4">
    <h3 class="section-title mb-4">Giỏ Hàng Của Tôi</h3>
    <?= $msg ?>

    <?php if (empty($cart)): ?>
    <div class="text-center py-5">
        <i class="bi bi-cart-x" style="font-size:5rem;color:#ccc"></i>
        <h4 class="mt-3 text-muted">Giỏ hàng trống</h4>
        <a href="index.php" class="btn btn-primary mt-3"><i class="bi bi-arrow-left me-2"></i>Tiếp tục mua sắm</a>
    </div>
    <?php else: ?>
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <form method="POST" action="cart.php">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Sản phẩm</th>
                                    <th class="text-center">Đơn giá</th>
                                    <th class="text-center">Số lượng</th>
                                    <th class="text-center">Thành tiền</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cart as $i => $item): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-3">
                                            <?php if (!empty($item['image']) && file_exists('uploads/'.$item['image'])): ?>
                                            <img src="uploads/<?= htmlspecialchars($item['image']) ?>" width="60" height="60" style="object-fit:cover;border-radius:8px">
                                            <?php else: ?>
                                            <div class="bg-light rounded d-flex align-items-center justify-content-center" style="width:60px;height:60px">
                                                <i class="bi bi-shoe text-secondary"></i>
                                            </div>
                                            <?php endif; ?>
                                            <div>
                                                <div class="fw-semibold"><?= htmlspecialchars($item['name']) ?></div>
                                                <?php if (!empty($item['size'])): ?>
                                                <small class="text-muted">Size: <?= htmlspecialchars($item['size']) ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center"><?= formatPrice($item['price']) ?></td>
                                    <td class="text-center" style="width:130px">
                                        <input type="number" name="qty[<?= $i ?>]" value="<?= $item['qty'] ?>" min="1" max="99" class="form-control form-control-sm text-center">
                                    </td>
                                    <td class="text-center fw-bold" style="color:#ff6b35"><?= formatPrice($item['price'] * $item['qty']) ?></td>
                                    <td class="text-center">
                                        <button type="submit" name="remove" value="<?= $i ?>" class="btn btn-sm btn-outline-danger"
                                                onclick="return confirm('Xóa sản phẩm này khỏi giỏ hàng?')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <div class="p-3 border-top d-flex justify-content-between align-items-center">
                            <a href="index.php" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-arrow-left me-1"></i>Tiếp tục mua sắm
                            </a>
                            <button type="submit" name="update" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-arrow-repeat me-1"></i>Cập nhật giỏ hàng
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header fw-bold text-white" style="background:#ff6b35">
                    <i class="bi bi-receipt me-2"></i>Tóm tắt đơn hàng
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Số sản phẩm:</span>
                        <strong><?= array_sum(array_column($cart, 'qty')) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-3 p-2 rounded" style="background:#fff3ee">
                        <span class="fw-bold">Tổng cộng:</span>
                        <strong style="color:#ff6b35;font-size:1.2rem"><?= formatPrice($total) ?></strong>
                    </div>
                    <a href="checkout.php" class="btn btn-primary w-100 py-2 fw-semibold">
                        <i class="bi bi-credit-card me-2"></i>Tiến hành thanh toán
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
