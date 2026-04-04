<?php
// search.php
require_once 'includes/header.php';
$pageTitle = 'Tìm kiếm';

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$cat_id = isset($_GET['cat_id']) ? (int)$_GET['cat_id'] : 0;
$min_price = isset($_GET['min_price']) ? (float)$_GET['min_price'] : 0;
$max_price = isset($_GET['max_price']) ? (float)$_GET['max_price'] : 0;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 12;
$offset = ($page - 1) * $per_page;

$where = "p.status='active'";
if ($q !== '') $where .= " AND p.name LIKE '%" . sanitize($conn, $q) . "%'";
if ($cat_id > 0) $where .= " AND p.category_id=$cat_id";
if ($min_price > 0) $where .= " AND ROUND(p.import_price*(1+p.profit_rate/100)) >= $min_price";
if ($max_price > 0) $where .= " AND ROUND(p.import_price*(1+p.profit_rate/100)) <= $max_price";

$total = $conn->query("SELECT COUNT(*) as cnt FROM products p WHERE $where")->fetch_assoc()['cnt'];
$total_pages = ceil($total / $per_page);

$products = $conn->query("SELECT p.*, c.name as cat_name, ROUND(p.import_price*(1+p.profit_rate/100)) as sell_price
    FROM products p JOIN categories c ON p.category_id=c.id
    WHERE $where ORDER BY p.name LIMIT $per_page OFFSET $offset");

$categories = $conn->query("SELECT * FROM categories ORDER BY name");
$params = http_build_query(array_filter(['q'=>$q,'cat_id'=>$cat_id,'min_price'=>$min_price,'max_price'=>$max_price]));
?>

<div class="container my-4">
    <h3 class="section-title mb-4">Tìm Kiếm Sản Phẩm</h3>

    <!-- Search Form -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" id="searchForm">
                <div class="row g-3">
                    <div class="col-md-12">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" name="q" class="form-control form-control-lg" placeholder="Nhập tên sản phẩm..." value="<?= htmlspecialchars($q) ?>">
                            <button class="btn btn-primary" type="submit">Tìm kiếm</button>
                        </div>
                    </div>

                    <div class="col-12">
                        <button class="btn btn-link text-decoration-none p-0" type="button" data-bs-toggle="collapse" data-bs-target="#advSearch">
                            <i class="bi bi-sliders me-1"></i>Tìm kiếm nâng cao
                        </button>
                    </div>

                    <div class="col-12 collapse <?= ($cat_id || $min_price || $max_price) ? 'show' : '' ?>" id="advSearch">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Danh mục</label>
                                <select name="cat_id" class="form-select">
                                    <option value="">-- Tất cả danh mục --</option>
                                    <?php
                                    $categories->data_seek(0);
                                    while ($c = $categories->fetch_assoc()):
                                    ?>
                                    <option value="<?= $c['id'] ?>" <?= $cat_id == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Giá từ (₫)</label>
                                <input type="number" name="min_price" class="form-control" placeholder="VD: 500000" value="<?= $min_price ?: '' ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Giá đến (₫)</label>
                                <input type="number" name="max_price" class="form-control" placeholder="VD: 5000000" value="<?= $max_price ?: '' ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Results -->
    <?php if ($q || $cat_id || $min_price || $max_price): ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="text-muted mb-0">Tìm thấy <strong><?= $total ?></strong> sản phẩm <?= $q ? "cho \"<strong>" . htmlspecialchars($q) . "</strong>\"" : '' ?></p>
        <?php if ($q || $cat_id || $min_price || $max_price): ?>
        <a href="search.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x me-1"></i>Xóa bộ lọc</a>
        <?php endif; ?>
    </div>

    <?php if ($total === 0): ?>
    <div class="alert alert-info text-center py-5">
        <i class="bi bi-search fs-2 d-block mb-3"></i>
        Không tìm thấy sản phẩm phù hợp. Hãy thử từ khóa khác.
    </div>
    <?php else: ?>
    <div class="row g-4">
        <?php while ($p = $products->fetch_assoc()): ?>
        <div class="col-sm-6 col-lg-3">
            <div class="card product-card h-100 shadow-sm">
                <?php if ($p['image'] && file_exists('uploads/' . $p['image'])): ?>
                <img src="uploads/<?= htmlspecialchars($p['image']) ?>" class="card-img-top product-img" alt="">
                <?php else: ?>
                <div class="product-img d-flex align-items-center justify-content-center bg-light">
                    <i class="bi bi-shoe fs-1 text-secondary"></i>
                </div>
                <?php endif; ?>
                <div class="card-body d-flex flex-column">
                    <span class="badge text-white small mb-2" style="background:#ff6b35"><?= htmlspecialchars($p['cat_name']) ?></span>
                    <h6 class="flex-grow-1"><?= htmlspecialchars($p['name']) ?></h6>
                    <p class="price-tag mb-2"><?= formatPrice($p['sell_price']) ?></p>
                    <a href="product.php?id=<?= $p['id'] ?>" class="btn btn-primary btn-sm">Xem chi tiết</a>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <nav class="mt-4">
        <ul class="pagination justify-content-center">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <li class="page-item <?= $i==$page?'active':'' ?>">
                <a class="page-link" href="?<?= $params ?>&page=<?= $i ?>"><?= $i ?></a>
            </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
    <?php endif; ?>
    <?php else: ?>
    <div class="text-center py-5 text-muted">
        <i class="bi bi-search fs-1 d-block mb-3"></i>
        Nhập từ khóa để tìm kiếm sản phẩm
    </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
