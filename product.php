<?php
// product.php — ALL logic before header.php to prevent "headers already sent"
require_once 'includes/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$sql = "SELECT p.*, c.name as cat_name,
        ROUND(p.import_price * (1 + p.profit_rate/100)) as sell_price
        FROM products p JOIN categories c ON p.category_id = c.id
        WHERE p.id=$id AND p.status='active'";
$product = $conn->query($sql)->fetch_assoc();

if (!$product) {
    // Still need header for proper page render
    $pageTitle = 'Không tìm thấy';
    require_once 'includes/header.php';
    echo "<div class='container my-5'><div class='alert alert-danger'>Sản phẩm không tồn tại.</div></div>";
    require_once 'includes/footer.php';
    exit;
}

// Parse sizes
$sizes = [];
if (!empty($product['available_sizes'])) {
    $sizes = array_filter(array_map('trim', explode(',', $product['available_sizes'])));
}
$genderLabel = ['nam' => 'Nam', 'nu' => 'Nữ', 'unisex' => 'Unisex'];

// Handle POST — must be before header.php outputs HTML
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_cart'])) {
    if (!isLoggedIn()) {
        redirect('login.php?redirect=product.php?id=' . $id);
    }
    $qty = max(1, (int)$_POST['quantity']);
    $selected_size = sanitize($conn, $_POST['selected_size'] ?? '');
    $available = $product['stock_quantity'] - $product['reserved_quantity'];


    if (!empty($sizes) && !$selected_size) {
        redirect('product.php?id=' . $id . '&err=size');
    } 
    if ($qty > $available) {
        redirect('product.php?id=' . $id . '&err=stock');
    } else {
        if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
        $cart_key = $id . '_' . $selected_size;
        $found = false;

        $totalQty = 0;

        if (isset($_SESSION['cart'])) {
            foreach ($_SESSION['cart'] as $item) {
                if ($item['product_id'] == $id) {
                    $totalQty += $item['qty'];
                }
            }
        }

        // Nếu vượt stock thì không cho thêm vào giỏ hàng
        if ($totalQty + $qty > $available) {
            redirect('product.php?id=' . $id . '&err=stock');
        }     

        foreach ($_SESSION['cart'] as &$item) {
            if ($item['cart_key'] === $cart_key) {
                $item['qty'] += $qty;
                $found = true;
                break;
            }
        }
        unset($item);
        if (!$found) {
            $_SESSION['cart'][] = [
                'cart_key'   => $cart_key,
                'product_id' => $id,
                'name'       => $product['name'] . ($selected_size ? " (Size $selected_size)" : ''),
                'price'      => $product['sell_price'],
                'qty'        => $qty,
                'image'      => $product['image'],
                'size'       => $selected_size,
            ];
        }
        redirect('product.php?id=' . $id . '&added=1' . ($selected_size ? '&sz=' . urlencode($selected_size) : ''));
    }
}

// Flash messages via GET (after PRG redirect)
$msg = '';
if (isset($_GET['added'])) {
    $sz = isset($_GET['sz']) ? ' (Size ' . htmlspecialchars($_GET['sz']) . ')' : '';
    $msg = '<div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>Đã thêm vào giỏ hàng'.$sz.'! <a href="cart.php">Xem giỏ hàng</a></div>';
} elseif (isset($_GET['err'])) {
    if ($_GET['err'] === 'size')  $msg = '<div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>Vui lòng chọn size trước khi thêm vào giỏ hàng.</div>';
    if ($_GET['err'] === 'stock') $msg = '<div class="alert alert-warning">Số lượng tồn kho không đủ!</div>';
}

// NOW safe to output HTML
$pageTitle = $product['name'];
require_once 'includes/header.php';
?>

<div class="container my-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Trang chủ</a></li>
            <li class="breadcrumb-item"><a href="category.php?id=<?= $product['category_id'] ?>"><?= htmlspecialchars($product['cat_name']) ?></a></li>
            <li class="breadcrumb-item active"><?= htmlspecialchars($product['name']) ?></li>
        </ol>
    </nav>

    <?= $msg ?>

    <div class="card border-0 shadow-sm p-4">
        <div class="row g-4">
            <!-- Product Image -->
            <div class="col-md-5">
                <?php if ($product['image'] && file_exists('uploads/' . $product['image'])): ?>
                <img src="uploads/<?= htmlspecialchars($product['image']) ?>" class="img-fluid rounded-3 w-100" style="max-height:420px;object-fit:cover" alt="<?= htmlspecialchars($product['name']) ?>">
                <?php else: ?>
                <div class="bg-light rounded-3 d-flex align-items-center justify-content-center" style="height:380px">
                    <i class="bi bi-shoe" style="font-size:8rem;color:#ddd"></i>
                </div>
                <?php endif; ?>
            </div>

            <!-- Product Info -->
            <div class="col-md-7">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge text-white" style="background:#ff6b35"><?= htmlspecialchars($product['cat_name']) ?></span>
                    <?php if ($product['brand']): ?>
                    <span class="badge bg-dark"><?= htmlspecialchars($product['brand']) ?></span>
                    <?php endif; ?>
                    <?php if ($product['gender']): ?>
                    <span class="badge bg-secondary"><?= $genderLabel[$product['gender']] ?? '' ?></span>
                    <?php endif; ?>
                </div>

                <h2 class="fw-bold mb-1"><?= htmlspecialchars($product['name']) ?></h2>

                <!-- Meta info -->
                <div class="text-muted small mb-3 d-flex flex-wrap gap-3">
                    <span><i class="bi bi-upc me-1"></i>Mã SP: <strong class="text-dark"><?= htmlspecialchars($product['code']) ?></strong></span>
                    <?php if ($product['color']): ?>
                    <span><i class="bi bi-palette me-1"></i>Màu: <strong class="text-dark"><?= htmlspecialchars($product['color']) ?></strong></span>
                    <?php endif; ?>
                    <?php if ($product['origin']): ?>
                    <span><i class="bi bi-globe me-1"></i>Xuất xứ: <strong class="text-dark"><?= htmlspecialchars($product['origin']) ?></strong></span>
                    <?php endif; ?>
                </div>

                <!-- Price box -->
                <div class="p-3 rounded-3 mb-3 d-flex align-items-center gap-3" style="background:#fff3ee">
                    <div class="fs-1 fw-bold" style="color:#ff6b35"><?= formatPrice($product['sell_price']) ?></div>
                </div>

                <!-- Stock status -->
                <?php if ($product['stock_quantity'] - $product['reserved_quantity'] > 0): ?>
                <p class="mb-3">
                    <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2">
                        <i class="bi bi-check-circle-fill me-1"></i>Còn hàng ·&nbsp;<?= $product['stock_quantity'] - $product['reserved_quantity'] ?> <?= htmlspecialchars($product['unit']) ?>
                    </span>
                </p>
                <?php else: ?>
                <p class="mb-3"><span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-2"><i class="bi bi-x-circle-fill me-1"></i>Hết hàng</span></p>
                <?php endif; ?>

                <!-- Description -->
                <?php if ($product['description']): ?>
                <div class="mb-3">
                    <p class="text-muted mb-0"><?= nl2br(htmlspecialchars($product['description'])) ?></p>
                </div>
                <?php endif; ?>

                <!-- Material -->
                <?php if ($product['material']): ?>
                <p class="text-muted small mb-3"><i class="bi bi-layers me-1"></i>Chất liệu: <strong class="text-dark"><?= htmlspecialchars($product['material']) ?></strong></p>
                <?php endif; ?>

                <?php if ($product['stock_quantity'] > 0): ?>
                <form method="POST" id="addCartForm">
                    <!-- Size selector -->
                    <?php if (!empty($sizes)): ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Chọn size: <span class="text-danger">*</span>
                            <span id="sizeSelected" class="ms-2 text-muted fw-normal small"></span>
                        </label>
                        <div class="d-flex flex-wrap gap-2 mb-2" id="sizeGrid">
                            <?php foreach ($sizes as $sz): ?>
                            <button type="button" class="btn btn-outline-secondary size-btn"
                                    style="width:52px;height:44px;font-size:.9rem;border-radius:8px"
                                    data-size="<?= htmlspecialchars($sz) ?>"
                                    onclick="selectSize(this)">
                                <?= htmlspecialchars($sz) ?>
                            </button>
                            <?php endforeach; ?>
                        </div>
                        <div class="d-flex align-items-start gap-2 p-2 rounded" style="background:#fff8e1;border:1px solid #ffe082;font-size:.82rem;color:#795548">
                            <i class="bi bi-info-circle-fill mt-1 flex-shrink-0" style="color:#f59e0b"></i>
                            <span>Size là yêu cầu của bạn khi đặt hàng. Chúng tôi sẽ xác nhận tình trạng tồn kho theo size sau khi nhận đơn. Nếu size không còn hàng, nhân viên sẽ liên hệ tư vấn thay thế.</span>
                        </div>
                        <input type="hidden" name="selected_size" id="selectedSizeInput" value="">
                    </div>
                    <?php endif; ?>

                    <!-- Quantity -->
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <label class="fw-semibold">Số lượng:</label>
                        <div class="input-group" style="width:140px">
                            <button type="button" class="btn btn-outline-secondary" onclick="changeQty(-1)">−</button>
                            <input type="number" id="qty" name="quantity" class="form-control text-center fw-bold" value="1" min="1" max="<?= $product['stock_quantity'] - $product['reserved_quantity'] ?>"">
                            <button type="button" class="btn btn-outline-secondary" onclick="changeQty(1)">+</button>
                        </div>
                    </div>

                    <button type="submit" name="add_cart" class="btn btn-primary btn-lg px-5">
                        <i class="bi bi-cart-plus me-2"></i>Thêm vào giỏ hàng
                    </button>
                </form>
                <?php endif; ?>

                <!-- Specs table -->
                <?php
                $specs = array_filter([
                    'Thương hiệu'  => $product['brand'],
                    'Danh mục'     => $product['cat_name'],
                    'Đối tượng'    => $genderLabel[$product['gender']] ?? null,
                    'Màu sắc'      => $product['color'],
                    'Chất liệu'    => $product['material'],
                    'Xuất xứ'      => $product['origin'],
                    'Đơn vị'       => $product['unit'],
                ]);
                if ($specs):
                ?>
                <div class="mt-4 pt-3 border-top">
                    <h6 class="fw-bold mb-3">Thông số kỹ thuật</h6>
                    <table class="table table-sm table-borderless mb-0" style="max-width:400px">
                        <?php foreach ($specs as $label => $val): ?>
                        <tr>
                            <td class="text-muted" style="width:130px"><?= $label ?></td>
                            <td class="fw-semibold"><?= htmlspecialchars($val) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Related products -->
    <?php
    $related = $conn->query("SELECT p.*, ROUND(p.import_price*(1+p.profit_rate/100)) as sell_price
        FROM products p WHERE p.category_id={$product['category_id']} AND p.id!=$id AND p.status='active' AND p.stock_quantity>0 LIMIT 4");
    if ($related->num_rows > 0):
    ?>
    <div class="mt-5">
        <h4 class="section-title mb-4">Sản phẩm cùng danh mục</h4>
        <div class="row g-4">
            <?php while ($rp = $related->fetch_assoc()): ?>
            <div class="col-sm-6 col-lg-3">
                <div class="card product-card shadow-sm h-100">
                    <div class="product-img d-flex align-items-center justify-content-center bg-light">
                        <?php if ($rp['image'] && file_exists('uploads/' . $rp['image'])): ?>
                        <img src="uploads/<?= htmlspecialchars($rp['image']) ?>" style="height:100%;width:100%;object-fit:cover" alt="">
                        <?php else: ?>
                        <i class="bi bi-shoe fs-1 text-secondary"></i>
                        <?php endif; ?>
                    </div>
                    <div class="card-body d-flex flex-column">
                        <?php if ($rp['brand']): ?>
                        <small class="text-muted"><?= htmlspecialchars($rp['brand']) ?></small>
                        <?php endif; ?>
                        <h6 class="flex-grow-1 mt-1"><?= htmlspecialchars($rp['name']) ?></h6>
                        <p class="price-tag mb-2"><?= formatPrice($rp['sell_price']) ?></p>
                        <a href="product.php?id=<?= $rp['id'] ?>" class="btn btn-sm btn-outline-primary w-100">Xem chi tiết</a>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function changeQty(delta) {
    const input = document.getElementById('qty');
    const max = parseInt(input.max);
    const val = parseInt(input.value) + delta;
    if (val >= 1 && val <= max) input.value = val;
}

function selectSize(btn) {
    // Deselect all
    document.querySelectorAll('.size-btn').forEach(b => {
        b.classList.remove('btn-dark');
        b.classList.add('btn-outline-secondary');
    });
    // Select this one
    btn.classList.remove('btn-outline-secondary');
    btn.classList.add('btn-dark');
    const sz = btn.getAttribute('data-size');
    document.getElementById('selectedSizeInput').value = sz;
    document.getElementById('sizeSelected').textContent = '→ Size ' + sz + ' đã chọn';
}
</script>

<?php require_once 'includes/footer.php'; ?>

