<?php
// index.php
require_once 'includes/header.php';

$pageTitle = 'Trang chủ';

// Featured products
$sql = "SELECT p.*, c.name as cat_name,
        ROUND(p.import_price * (1 + p.profit_rate/100)) as sell_price
        FROM products p JOIN categories c ON p.category_id = c.id
        WHERE p.status = 'active' AND p.stock_quantity > 0
        ORDER BY p.created_at DESC LIMIT 8";
$products = $conn->query($sql);

// Categories with count
$categories = $conn->query("SELECT c.*, COUNT(p.id) as product_count 
    FROM categories c LEFT JOIN products p ON c.id = p.category_id AND p.status='active'
    GROUP BY c.id ORDER BY c.name");
?>

<!-- Hero Banner -->
<div class="bg-dark text-white py-5 mb-4" style="background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%) !important;">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h1 class="display-4 fw-bold mb-3">Sneaker <span style="color:#ff6b35">Chính Hãng</span></h1>
                <p class="lead mb-4">Bộ sưu tập sneaker đa dạng từ Nike, Adidas, Jordan và nhiều thương hiệu nổi tiếng.</p>
                <a href="search.php" class="btn btn-primary btn-lg me-2">
                    <i class="bi bi-search me-1"></i> Khám phá ngay
                </a>
                <a href="category.php" class="btn btn-outline-light btn-lg">Xem tất cả</a>
            </div>
            <div class="col-lg-6 text-center d-none d-lg-block">
                <i class="bi bi-shoe" style="font-size: 12rem; color: #ff6b35; opacity: 0.3;"></i>
            </div>
        </div>
    </div>
</div>

<div class="container">
    <!-- Categories -->
    <div class="mb-5">
        <h3 class="section-title mb-4">Danh Mục Sản Phẩm</h3>
        <div class="row g-3">
            <?php while ($cat = $categories->fetch_assoc()): ?>
            <div class="col-6 col-md-3">
                <a href="category.php?id=<?= $cat['id'] ?>" class="text-decoration-none">
                    <div class="card text-center p-3 h-100 border-0 shadow-sm" style="border-radius:12px">
                        <i class="bi bi-grid fs-2 text-warning mb-2"></i>
                        <h6 class="mb-1 text-dark"><?= htmlspecialchars($cat['name']) ?></h6>
                        <small class="text-muted"><?= $cat['product_count'] ?> sản phẩm</small>
                    </div>
                </a>
            </div>
            <?php endwhile; ?>
        </div>
    </div>

    <!-- Featured Products -->
    <div class="mb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="section-title mb-0">Sản Phẩm Mới Nhất</h3>
            <a href="search.php" class="btn btn-outline-secondary btn-sm">Xem tất cả <i class="bi bi-arrow-right"></i></a>
        </div>
        <div class="row g-4">
            <?php while ($p = $products->fetch_assoc()): ?>
            <div class="col-6 col-md-4 col-lg-3">
                <div class="card product-card h-100 shadow-sm">
                    <?php if ($p['image'] && file_exists('uploads/' . $p['image'])): ?>
                    <img src="uploads/<?= htmlspecialchars($p['image']) ?>" class="card-img-top product-img" alt="<?= htmlspecialchars($p['name']) ?>">
                    <?php else: ?>
                    <div class="product-img d-flex align-items-center justify-content-center bg-light">
                        <i class="bi bi-shoe fs-1 text-secondary"></i>
                    </div>
                    <?php endif; ?>
                    <div class="card-body d-flex flex-column">
                        <span class="badge badge-category text-white small mb-2"><?= htmlspecialchars($p['cat_name']) ?></span>
                        <h6 class="card-title"><?= htmlspecialchars($p['name']) ?></h6>
                        <p class="price-tag mt-auto mb-2"><?= formatPrice($p['sell_price']) ?></p>
                        <a href="product.php?id=<?= $p['id'] ?>" class="btn btn-primary btn-sm">
                            <i class="bi bi-eye me-1"></i>Xem chi tiết
                        </a>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>

    <!-- Banner strip -->
    <div class="row g-3 mb-5">
        <div class="col-md-4">
            <div class="p-4 rounded-3 text-center" style="background:#fff3ee; border: 1px solid #ffd4c2">
                <i class="bi bi-truck fs-2 text-warning mb-2"></i>
                <h6>Giao Hàng Nhanh</h6>
                <small class="text-muted">Nội thành 2 giờ, toàn quốc 1-3 ngày</small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="p-4 rounded-3 text-center" style="background:#fff3ee; border: 1px solid #ffd4c2">
                <i class="bi bi-shield-check fs-2 text-warning mb-2"></i>
                <h6>Hàng Chính Hãng</h6>
                <small class="text-muted">Cam kết 100% authentic có giấy tờ</small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="p-4 rounded-3 text-center" style="background:#fff3ee; border: 1px solid #ffd4c2">
                <i class="bi bi-arrow-repeat fs-2 text-warning mb-2"></i>
                <h6>Đổi Trả 7 Ngày</h6>
                <small class="text-muted">Không hài lòng, đổi ngay miễn phí</small>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>