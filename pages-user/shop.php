<?php
// shop.php

require_once __DIR__ . '/../includes/session-init.php';
require_once '../config/db_connection.php';

// Check if user can view exclusive products
$hasMembershipAccess = false;
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("
        SELECT mt.can_access_exclusive
        FROM users u
        LEFT JOIN memberships m ON u.user_id = m.user_id
        LEFT JOIN membership_types mt ON m.membership_type_id = mt.membership_type_id
        WHERE u.user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $hasMembershipAccess = $result['can_access_exclusive'] ?? false;
}

// Build query for products
$search = $_GET['search'] ?? '';
$category_id = $_GET['category_id'] ?? null;
$showExclusive = $hasMembershipAccess;

$query = "
    SELECT p.*,
           c.category_name,
           (SELECT image_url
            FROM product_images
            WHERE product_id = p.product_id
              AND is_primary = 1
            LIMIT 1) AS primary_image
    FROM products p
    JOIN categories c ON p.category_id = c.category_id
    WHERE p.stock > 0
";

$params = [];

// Apply search
if ($search) {
    $query .= " AND (p.product_name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Apply category filter
if ($category_id) {
    $query .= " AND p.category_id = ?";
    $params[] = $category_id;
}

// Hide exclusive products for guests
if (!$showExclusive) {
    $query .= " AND p.is_exclusive = FALSE";
}

// Sort by most recently created
$query .= " ORDER BY p.created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch categories for filter dropdown
$categories = $pdo->query("SELECT * FROM categories")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Shop - BunniShop</title>
    <link rel="stylesheet" href="../assets/css/shop.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="../assets/images/iconlogo/bunniwinkleIcon.ico">
</head>
<body>
    <?php include '../includes/user-navbar.php'; ?>

    <div class="shop-container container my-4">
        <div class="shop-header text-center mb-4">
            <h1>Our Products</h1>

            <?php if ($showExclusive): ?>
                <div class="alert alert-info">You have access to exclusive products!</div>
            <?php endif; ?>

            <!-- Search & Filter Form -->
            <form method="GET" class="search-filter d-flex justify-content-center align-items-center gap-3 mt-3">
                <div class="search-box">
                    <input type="text" name="search" class="form-control" placeholder="Search products..."
                           value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                </div>
                <div class="filter-dropdown">
                    <select name="category_id" class="form-select">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['category_id'] ?>"
                                <?= ($category_id == $cat['category_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['category_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($search || $category_id): ?>
                    <a href="shop.php" class="btn btn-outline-secondary">Clear Filters</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="product-grid row g-4">
            <?php if (empty($products)): ?>
                <!-- No results -->
                <div class="col-12 text-center py-5">
                    <h3>No products found</h3>
                    <p>Try adjusting your search or filter criteria</p>
                    <a href="shop.php" class="btn btn-primary">View All Products</a>
                </div>
            <?php else: ?>
                <!-- Product listing -->
                <?php foreach ($products as $product): ?>
                    <div class="col-sm-6 col-md-4 col-lg-3">
                        <div class="product-card border p-3 h-100">
                            <?php if ($product['is_exclusive']): ?>
                                <div class="exclusive-badge">Exclusive</div>
                            <?php endif; ?>

                            <!-- Product Image & Quick View trigger -->
                            <div class="product-image position-relative">
                                <?php if ($product['primary_image']): ?>
                                    <img src="../assets/images/products/<?= htmlspecialchars($product['primary_image']) ?>"
                                         alt="<?= htmlspecialchars($product['product_name']) ?>"
                                         class="img-fluid">
                                <?php else: ?>
                                    <div class="no-image-placeholder bg-light d-flex align-items-center justify-content-center"
                                         style="height:200px;">
                                        <i class="fas fa-image fa-3x text-muted"></i>
                                    </div>
                                <?php endif; ?>

                                <div class="quick-view position-absolute top-50 start-50 translate-middle bg-light p-2 rounded"
                                     data-product-id="<?= $product['product_id'] ?>" style="cursor:pointer;">
                                    <i class="fas fa-eye"></i> Quick View
                                </div>
                            </div>

                            <!-- Product Info -->
                            <div class="product-info mt-2">
                                <h3 class="h6"><?= htmlspecialchars($product['product_name']) ?></h3>
                                <div class="price fs-5">₱<?= number_format($product['price'], 2) ?></div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <!-- Quick View Modal -->
    <div class="modal fade" id="quickViewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content"><!-- loaded via AJAX --></div>
        </div>
    </div>

    <!-- JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Load product details into quick view modal
    $(document).on('click', '.quick-view', function () {
        const productId = $(this).data('product-id');
        $.get('quick-view.php', { product_id: productId }, function (response) {
            $('#quickViewModal .modal-content').html(response);
            $('#quickViewModal').modal('show');
        });
    });

    // Intercept add-to-cart form submissions from the Quick View
    $(document).on('submit', '.add-to-cart-form', function (e) {
        e.preventDefault();
        const form = $(this);
        const btn = form.find('button[type=\"submit\"]');
        const originalText = btn.html();

        // Indicate loading
        btn.prop('disabled', true).html('<span class=\"spinner-border spinner-border-sm\"></span> Adding...');

        $.ajax({
            url: 'cart_actions.php',  // separate file for cart logic
            type: 'POST',
            data: form.serialize(),
            dataType: 'json',
            success: function (response) {
                if (response.redirect) {
                    window.location.href = response.redirect;
                } else if (response.success) {
                    alert(response.message);
                    $('#quickViewModal').modal('hide');
                    window.location.reload();
                } else {
                    alert(response.error || 'Something went wrong.');
                }
            },
            error: function () {
                alert('Failed to add to cart. Please try again.');
            },
            complete: function () {
                btn.prop('disabled', false).html(originalText);
            }
        });
    });
    </script>
</body>
</html>
