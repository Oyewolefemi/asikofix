<?php
// kiosk/Red/orders.php
include 'header.php';

$is_super = ($_SESSION['admin_role'] ?? '') === 'superadmin';
$admin_id = $_SESSION['admin_id'];

// --- QUERY BUILDER ---
// We build dynamic queries based on role
function getOrderQuery($status, $is_super, $admin_id) {
    if ($is_super) {
        // Super Admin sees standard Order Totals
        return "SELECT o.id, u.email, o.order_date, o.total_amount as display_total, o.status, o.payment_method
                FROM orders o
                JOIN users u ON u.id = o.user_id
                WHERE o.status = '$status'
                ORDER BY o.order_date DESC";
    } else {
        // Vendor sees ONLY their share of the revenue
        return "SELECT o.id, u.email, o.order_date, o.status, o.payment_method,
                       SUM(od.price_at_purchase * od.quantity) as display_total
                FROM orders o
                JOIN users u ON u.id = o.user_id
                JOIN order_details od ON o.id = od.order_id
                JOIN products p ON od.product_id = p.id
                WHERE o.status = '$status' 
                AND p.admin_id = $admin_id
                GROUP BY o.id
                ORDER BY o.order_date DESC";
    }
}

// Fetch Lists
$pendingApprovalOrders = $pdo->query(getOrderQuery('payment_submitted', $is_super, $admin_id))->fetchAll();
$activeOrders = $pdo->query(getOrderQuery('active', $is_super, $admin_id))->fetchAll();

// Get "All" list (slightly different logic to handle non-filtered status)
if ($is_super) {
    $allOrders = $pdo->query("
        SELECT o.id, u.email, o.order_date, o.total_amount as display_total, o.status, o.payment_method
        FROM orders o JOIN users u ON u.id = o.user_id ORDER BY o.order_date DESC
    ")->fetchAll();
} else {
    $allOrders = $pdo->query("
        SELECT o.id, u.email, o.order_date, o.status, o.payment_method,
               SUM(od.price_at_purchase * od.quantity) as display_total
        FROM orders o
        JOIN users u ON u.id = o.user_id
        JOIN order_details od ON o.id = od.order_id
        JOIN products p ON od.product_id = p.id
        WHERE p.admin_id = $admin_id
        GROUP BY o.id
        ORDER BY o.order_date DESC
    ")->fetchAll();
}

// Calculate Stats for Top Bar
if ($is_super) {
    $stats = $pdo->query("SELECT COUNT(*) as total, SUM(total_amount) as rev FROM orders WHERE status='active'")->fetch();
} else {
    $stats = $pdo->query("
        SELECT COUNT(DISTINCT o.id) as total, SUM(od.price_at_purchase * od.quantity) as rev 
        FROM orders o 
        JOIN order_details od ON o.id = od.order_id 
        JOIN products p ON od.product_id = p.id 
        WHERE o.status='active' AND p.admin_id = $admin_id
    ")->fetch();
}
?>

    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800"><?= $is_super ? 'All Mall Orders' : 'My Shop Orders' ?></h1>
        <p class="text-gray-600 mt-2">
            <?= $is_super ? 'Manage all incoming orders.' : 'Orders containing your products.' ?>
        </p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white p-6 rounded-lg shadow">
            <h3 class="text-sm font-medium text-gray-500">Active Orders</h3>
            <p class="text-3xl font-bold text-green-600"><?= count($activeOrders) ?></p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow">
            <h3 class="text-sm font-medium text-gray-500">Pending Approval</h3>
            <p class="text-3xl font-bold text-yellow-600"><?= count($pendingApprovalOrders) ?></p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow">
            <h3 class="text-sm font-medium text-gray-500">Recognized Revenue</h3>
            <p class="text-3xl font-bold text-blue-600">₦<?= number_format($stats['rev'] ?? 0, 2) ?></p>
        </div>
    </div>

    <div x-data="{ activeTab: 'pending' }" class="bg-white rounded-lg shadow">
        <div class="border-b border-gray-200">
            <nav class="flex space-x-8 px-6">
                <button @click="activeTab = 'pending'" :class="activeTab === 'pending' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500'" class="py-4 px-1 border-b-2 font-medium text-sm transition">Pending (<?= count($pendingApprovalOrders) ?>)</button>
                <button @click="activeTab = 'active'" :class="activeTab === 'active' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500'" class="py-4 px-1 border-b-2 font-medium text-sm transition">Active (<?= count($activeOrders) ?>)</button>
                <button @click="activeTab = 'all'" :class="activeTab === 'all' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500'" class="py-4 px-1 border-b-2 font-medium text-sm transition">All History</button>
            </nav>
        </div>
        
        <?php 
        // Helper function to render table rows
        function renderOrderRows($orders, $is_super) {
            if (empty($orders)) {
                echo '<tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">No orders found.</td></tr>';
                return;
            }
            foreach ($orders as $order) {
                $statusClass = match($order['status']) {
                    'active' => 'bg-green-100 text-green-800',
                    'payment_submitted' => 'bg-yellow-100 text-yellow-800',
                    default => 'bg-gray-100 text-gray-800',
                };
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 font-bold text-gray-900">#<?= $order['id'] ?></td>
                    <td class="px-6 py-4 text-sm text-gray-500"><?= htmlspecialchars($order['email']) ?></td>
                    <td class="px-6 py-4 text-sm text-gray-500"><?= date('M d, Y', strtotime($order['order_date'])) ?></td>
                    <td class="px-6 py-4 font-bold text-gray-800">
                        ₦<?= number_format($order['display_total'], 2) ?>
                        <?php if(!$is_super): ?><span class="text-xs font-normal text-gray-400 block">(Your Share)</span><?php endif; ?>
                    </td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 text-xs rounded-full font-bold <?= $statusClass ?>">
                            <?= strtoupper(str_replace('_', ' ', $order['status'])) ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <?php if($is_super && $order['status'] === 'payment_submitted'): ?>
                             <a href="approve_order.php?order_id=<?= $order['id'] ?>" class="text-green-600 hover:underline font-bold text-sm">Approve</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php
            }
        }
        ?>

        <div x-show="activeTab === 'pending'" class="p-0"><table class="w-full text-left"><thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-6 py-3">Order ID</th><th class="px-6 py-3">Customer</th><th class="px-6 py-3">Date</th><th class="px-6 py-3">Total</th><th class="px-6 py-3">Status</th><th class="px-6 py-3"></th></tr></thead><tbody class="divide-y divide-gray-100"><?php renderOrderRows($pendingApprovalOrders, $is_super); ?></tbody></table></div>
        
        <div x-show="activeTab === 'active'" class="p-0"><table class="w-full text-left"><thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-6 py-3">Order ID</th><th class="px-6 py-3">Customer</th><th class="px-6 py-3">Date</th><th class="px-6 py-3">Total</th><th class="px-6 py-3">Status</th><th class="px-6 py-3"></th></tr></thead><tbody class="divide-y divide-gray-100"><?php renderOrderRows($activeOrders, $is_super); ?></tbody></table></div>
        
        <div x-show="activeTab === 'all'" class="p-0"><table class="w-full text-left"><thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-6 py-3">Order ID</th><th class="px-6 py-3">Customer</th><th class="px-6 py-3">Date</th><th class="px-6 py-3">Total</th><th class="px-6 py-3">Status</th><th class="px-6 py-3"></th></tr></thead><tbody class="divide-y divide-gray-100"><?php renderOrderRows($allOrders, $is_super); ?></tbody></table></div>

    </div>

<?php echo "</main></div><script src='https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js' defer></script></body></html>"; ?>