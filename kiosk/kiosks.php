<?php
// kiosk/kiosks.php
require_once 'config.php';
require_once 'header.php'; // Ensure your header has a link to this page

// Fetch all admins who have a store set up (excluding Super Admin if they don't sell)
// We assume admins with 'store_name' are vendors
try {
    $stmt = $pdo->query("SELECT * FROM admins WHERE store_name IS NOT NULL AND store_slug IS NOT NULL ORDER BY created_at DESC");
    $stores = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stores = [];
}
?>

<div class="container mx-auto px-4 py-8">
    
    <div class="text-center mb-12">
        <h1 class="text-4xl font-bold text-gray-800 mb-2">Explore Our Stores</h1>
        <p class="text-gray-500 text-lg">Browse specific vendors and find exactly what you need.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <?php if (count($stores) > 0): ?>
            <?php foreach ($stores as $store): ?>
                <?php 
                    // Fallback for logo
                    $logo = !empty($store['store_logo']) ? $store['store_logo'] : 'kiosk/uploads/logos/default_logo.png';
                    // Link to the specific store page (we will build store.php next)
                    $storeLink = "store.php?vendor_id=" . $store['id'];
                ?>
                <div class="bg-white rounded-lg shadow-md hover:shadow-xl transition-shadow duration-300 overflow-hidden border border-gray-100 group">
                    <div class="h-32 bg-gray-100 flex items-center justify-center relative">
                        <div class="absolute inset-0 bg-gradient-to-br from-blue-50 to-blue-100 opacity-50"></div>
                        
                        <img src="<?= htmlspecialchars($logo) ?>" alt="<?= htmlspecialchars($store['store_name']) ?>" 
                             class="w-24 h-24 rounded-full object-cover border-4 border-white shadow-sm relative z-10 group-hover:scale-105 transition-transform">
                    </div>
                    
                    <div class="p-6 text-center">
                        <h2 class="text-xl font-bold text-gray-800 mb-1">
                            <?= htmlspecialchars($store['store_name']) ?>
                        </h2>
                        
                        <div class="flex items-center justify-center gap-1 mb-4">
                            <span class="text-blue-500">
                                <svg class="w-4 h-4 fill-current" viewBox="0 0 20 20"><path d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/></svg>
                            </span>
                            <span class="text-xs text-gray-500 font-medium uppercase tracking-wide">Verified Vendor</span>
                        </div>

                        <a href="<?= $storeLink ?>" class="inline-block w-full py-2 px-4 bg-gray-800 text-white font-bold rounded hover:bg-blue-600 transition-colors">
                            Visit Store
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-span-full text-center py-12">
                <p class="text-gray-500 text-lg">No stores are currently active. Check back soon!</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'footer.php'; ?>