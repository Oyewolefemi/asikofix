<?php
// kiosk/store.php
// This page displays a specific vendor's products

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

include 'header.php'; 

// 1. GET VENDOR ID
$vendor_id = isset($_GET['vendor_id']) ? intval($_GET['vendor_id']) : 0;

if ($vendor_id === 0) {
    echo "<script>window.location.href='kiosks.php';</script>";
    exit;
}

// 2. FETCH VENDOR DETAILS
try {
    $stmt = $pdo->prepare("SELECT store_name, store_slug, store_logo FROM admins WHERE id = ?");
    $stmt->execute([$vendor_id]);
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$vendor) {
        // Vendor not found
        echo "<div class='container my-12 text-center'><h1 class='text-2xl font-bold'>Store Not Found</h1><a href='kiosks.php' class='text-blue-500'>Return to Directory</a></div>";
        include 'footer.php';
        exit;
    }
} catch (Exception $e) {
    printError("System Error.");
    exit;
}

// 3. FETCH PRODUCTS (Filtered by admin_id)
// We reuse the sorting/pagination logic from products.php for consistency
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

$search_query = sanitize(trim($_GET['search'] ?? ''));
$sort_options = [
    'name_asc' => 'name ASC',
    'price_asc' => 'COALESCE(sale_price, price) ASC',
    'price_desc' => 'COALESCE(sale_price, price) DESC',
    'newest' => 'created_at DESC'
];
$sort_key = $_GET['sort'] ?? 'newest';
$order_by = $sort_options[$sort_key] ?? 'created_at DESC';

// Build Query
$where_conditions = ["admin_id = ?"];
$params = [$vendor_id];

if ($search_query !== '') {
    $where_conditions[] = "(name LIKE ? OR description LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Count Total for this Vendor
$count_sql = "SELECT COUNT(*) FROM products $where_clause";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_products = (int)$count_stmt->fetchColumn();
$total_pages = (int)ceil($total_products / $per_page);

// Fetch Vendor's Products
$sql = "SELECT * FROM products $where_clause ORDER BY $order_by LIMIT ? OFFSET ?"; 
$stmt = $pdo->prepare($sql);

$param_index = 1; 
foreach ($params as $value) $stmt->bindValue($param_index++, $value);
$stmt->bindValue($param_index++, $per_page, PDO::PARAM_INT);
$stmt->bindValue($param_index++, $offset, PDO::PARAM_INT);
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Logo Handling
$vendor_logo = !empty($vendor['store_logo']) ? $vendor['store_logo'] : 'kiosk/uploads/logos/default_logo.png';
?>

<link rel="stylesheet" href="assets/css/products.css?v=<?= time() ?>"> 

<main>
    <div class="bg-gray-900 text-white py-12">
        <div class="container mx-auto px-4 flex flex-col md:flex-row items-center gap-6">
            <img src="<?= htmlspecialchars($vendor_logo) ?>" alt="Store Logo" class="w-24 h-24 rounded-full border-4 border-white shadow-lg object-cover bg-white">
            <div class="text-center md:text-left">
                <h1 class="text-3xl md:text-5xl font-bold mb-2"><?= htmlspecialchars($vendor['store_name']) ?></h1>
                <p class="text-gray-400">Welcome to our official store.</p>
            </div>
            <div class="md:ml-auto">
                 <span class="bg-green-600 text-white px-4 py-1 rounded-full text-sm font-bold shadow">✔ Verified Vendor</span>
            </div>
        </div>
    </div>

    <div class="container mx-auto px-4 py-8">
        
        <div class="flex flex-col md:flex-row justify-between items-center mb-8 gap-4">
            <h2 class="text-xl font-bold text-gray-700">Products (<?= $total_products ?>)</h2>
            
            <form method="GET" class="flex gap-2 w-full md:w-auto">
                <input type="hidden" name="vendor_id" value="<?= $vendor_id ?>">
                <input type="text" name="search" placeholder="Search this store..." value="<?= htmlspecialchars($search_query) ?>" class="border p-2 rounded w-full md:w-64">
                <select name="sort" class="border p-2 rounded" onchange="this.form.submit()">
                    <option value="newest" <?= $sort_key === 'newest' ? 'selected' : '' ?>>Newest</option>
                    <option value="price_asc" <?= $sort_key === 'price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
                    <option value="price_desc" <?= $sort_key === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
                </select>
            </form>
        </div>

        <?php if (empty($products)): ?>
            <div class="text-center py-20 bg-gray-50 rounded-lg">
                <h3 class="text-2xl text-gray-400 font-bold mb-2">Shelf Empty</h3>
                <p class="text-gray-500">This vendor hasn't added any products yet.</p>
                <a href="kiosks.php" class="text-blue-600 font-bold mt-4 inline-block">Browse other stores</a>
            </div>
        <?php else: ?>
            <div class="products-grid">
                <?php foreach ($products as $product): ?>
                    <?php 
                        $product_image = getProductImage($product['image_path']);
                        $has_sale = !empty($product['sale_price']) && $product['sale_price'] > 0;
                        $display_price = $has_sale ? $product['sale_price'] : $product['price'];
                    ?>
                    <article class="card">
                        <a href="product-detail.php?id=<?= $product['id'] ?>" class="card-link">
                            <div class="card-body">
                                <p class="category"><?= htmlspecialchars($product['category']) ?></p>
                                <h3 class="card-title"><?= htmlspecialchars($product['name']) ?></h3>
                                <div class="price">
                                    <span class="<?= $has_sale ? 'text-red-600' : 'text-luxury-black' ?>">
                                        <?= formatCurrency($display_price) ?>
                                    </span>
                                    <?php if ($has_sale): ?>
                                        <span class="text-xs text-gray-500 line-through ml-1"><?= formatCurrency($product['price']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if($product['stock_quantity'] > 0): ?>
                                    <p class="text-xs text-green-600 mt-1">In stock</p>
                                <?php else: ?>
                                    <p class="text-xs text-red-600 mt-1">Out of stock</p>
                                <?php endif; ?>
                            </div>

                            <div class="card-img">
                                <?php if ($product_image): ?>
                                    <img src="<?= htmlspecialchars($product_image) ?>" loading="lazy">
                                <?php else: ?>
                                    <div class="placeholder"><span>📦</span></div>
                                <?php endif; ?>
                            </div>
                        </a>
                        <div class="card-actions">
                             <a href="product-detail.php?id=<?= $product['id'] ?>" class="btn btn-secondary btn-sm w-full">View Details</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <?php if ($total_pages > 1): ?>
                <div class="pagination mt-8 flex justify-center gap-2">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?vendor_id=<?= $vendor_id ?>&page=<?= $i ?>&sort=<?= $sort_key ?>" 
                           class="px-3 py-1 border rounded <?= $i == $page ? 'bg-gray-800 text-white' : 'bg-white text-gray-700' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</main>

<?php include 'footer.php'; ?>