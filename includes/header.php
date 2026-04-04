<?php
// includes/header.php
require_once __DIR__ . '/db.php';
// db.php đã start session và định nghĩa hàm isLoggedIn() nếu có

// Lấy danh mục cho menu dropdown
$cats = $conn->query("SELECT * FROM categories ORDER BY name");
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Thẻ base này là giải pháp đơn giản nhất: tất cả đường dẫn tương đối sẽ tự động có prefix /TMDT-UD_sneaker_shop/ -->
    <base href="/TMDT-UD_sneaker_shop/">
    
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - ' : '' ?>SneakerShop</title>
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <style>
        .navbar-brand {
            font-weight: 800;
            font-size: 1.5rem;
            color: #ff6b35 !important;
        }
        .product-card {
            transition: transform .2s, box-shadow .2s;
            border: none;
            border-radius: 12px;
            overflow: hidden;
        }
        .product-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }
        .product-img {
            height: 220px;
            object-fit: cover;
            background: #f8f9fa;
        }
        .badge-category {
            background: #ff6b35;
        }
        .btn-primary {
            background: #ff6b35;
            border-color: #ff6b35;
        }
        .btn-primary:hover {
            background: #e55a24;
            border-color: #e55a24;
        }
        .price-tag {
            color: #ff6b35;
            font-weight: 700;
            font-size: 1.1rem;
        }
        footer {
            background: #1a1a2e;
            color: #ccc;
        }
        .section-title {
            border-left: 4px solid #ff6b35;
            padding-left: 12px;
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-lightning-fill"></i> SneakerShop
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Trang chủ</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Danh mục
                        </a>
                        <ul class="dropdown-menu">
                            <?php while ($cat = $cats->fetch_assoc()): ?>
                                <li>
                                    <a class="dropdown-item" href="category.php?id=<?= $cat['id'] ?>">
                                        <?= htmlspecialchars($cat['name']) ?>
                                    </a>
                                </li>
                            <?php endwhile; ?>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="search.php">Tìm kiếm</a>
                    </li>
                </ul>

                <!-- Form tìm kiếm -->
                <form class="d-flex me-3" action="search.php" method="GET">
                    <input class="form-control form-control-sm me-2" type="search" name="q" placeholder="Tìm sneaker..." style="width: 200px;" aria-label="Search">
                    <button class="btn btn-sm btn-primary" type="submit">
                        <i class="bi bi-search"></i>
                    </button>
                </form>

                <!-- Phần tài khoản / giỏ hàng -->
                <ul class="navbar-nav align-items-center">
                    <?php if (function_exists('isLoggedIn') && isLoggedIn()): ?>
                        <li class="nav-item">
                            <a class="nav-link px-2" href="cart.php" title="Giỏ hàng">
                                <i class="bi bi-cart3 fs-5" style="vertical-align: middle;"></i>
                            </a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle d-flex align-items-center gap-1" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-person-circle fs-5"></i>
                                <span><?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Tài khoản') ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <a class="dropdown-item" href="my_orders.php">
                                        <i class="bi bi-bag-check me-2"></i> Đơn hàng của tôi
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item text-danger" href="login.php?action=logout">
                                        <i class="bi bi-box-arrow-right me-2"></i> Đăng xuất
                                    </a>
                                </li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link d-flex align-items-center" href="login.php">
                                <i class="bi bi-box-arrow-in-right me-1"></i> Đăng nhập
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="btn btn-primary btn-sm px-3" href="register.php">Đăng ký</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Đóng thẻ body và html sẽ được mở ở footer.php -->