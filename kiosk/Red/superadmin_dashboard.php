<?php
// dns/kiosk/Red/superadmin_dashboard.php
include 'header.php'; 

// Security Check: Double check if user is actually a superadmin
if ($_SESSION['admin_role'] !== 'superadmin') {
    header("Location: admin_dashboard.php");
    exit();
}

// Fetch Global Statistics
try {
    $stats_stmt = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM users) as total_users,
            (SELECT COUNT(*) FROM products) as total_products,
            (SELECT COUNT(*) FROM orders) as total_orders,
            (SELECT SUM(total_amount + delivery_fee) FROM orders WHERE status = 'active') as total_revenue,
            (SELECT COUNT(*) FROM admins) as total_admins
    ");
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    printError("Error fetching stats: " . $e->getMessage());
}

// Fetch GLOBAL Activities (All Admins)
try {
    $act_sql = "SELECT a.*, ad.username, ad.role 
                FROM admin_activities a 
                JOIN admins ad ON a.admin_id = ad.id 
                ORDER BY a.created_at DESC LIMIT 20";
    $recent_activities = $pdo->query($act_sql)->fetchAll();
} catch (Exception $e) {
    $recent_activities = [];
}
?>

<div class="flex justify-between items-center mb-8">
    <div>
        <h1 class="text-3xl font-bold text-gray-800">Super Admin Overview</h1>
        <p class="text-gray-500 text-sm">Welcome back, Boss.</p>
    </div>
    <span class="bg-purple-600 text-white px-4 py-2 rounded-full text-sm font-bold shadow">
        SUPERADMIN MODE
    </span>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-6 mb-8">
    <div class="bg-white p-6 rounded-lg shadow border-t-4 border-purple-600">
        <h3 class="text-xs font-bold text-gray-400 uppercase">Total Revenue</h3>
        <p class="text-2xl font-bold text-gray-800">₦<?= number_format($stats['total_revenue'] ?? 0, 2) ?></p>
    </div>
    <div class="bg-white p-6 rounded-lg shadow border-t-4 border-purple-600">
        <h3 class="text-xs font-bold text-gray-400 uppercase">Total Orders</h3>
        <p class="text-2xl font-bold text-gray-800"><?= $stats['total_orders'] ?? 0 ?></p>
    </div>
    <div class="bg-white p-6 rounded-lg shadow border-t-4 border-purple-600">
        <h3 class="text-xs font-bold text-gray-400 uppercase">Products</h3>
        <p class="text-2xl font-bold text-gray-800"><?= $stats['total_products'] ?? 0 ?></p>
    </div>
    <div class="bg-white p-6 rounded-lg shadow border-t-4 border-purple-600">
        <h3 class="text-xs font-bold text-gray-400 uppercase">Customers</h3>
        <p class="text-2xl font-bold text-gray-800"><?= $stats['total_users'] ?? 0 ?></p>
    </div>
    <div class="bg-white p-6 rounded-lg shadow border-t-4 border-pink-500 bg-pink-50">
        <h3 class="text-xs font-bold text-pink-700 uppercase">Admin Staff</h3>
        <p class="text-2xl font-bold text-pink-700"><?= $stats['total_admins'] ?? 0 ?></p>
    </div>
</div>

<div class="bg-white rounded-xl shadow-lg overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50">
        <h3 class="font-bold text-gray-800">🌍 Global System Activity Log</h3>
        <span class="text-xs text-gray-500">Real-time tracking of all staff</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-gray-600">
            <thead class="bg-gray-100 text-gray-600 uppercase font-bold text-xs">
                <tr>
                    <th class="px-6 py-3">Staff Member</th>
                    <th class="px-6 py-3">Role</th>
                    <th class="px-6 py-3">Action</th>
                    <th class="px-6 py-3">Details</th>
                    <th class="px-6 py-3">Time</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if (count($recent_activities) > 0): ?>
                    <?php foreach ($recent_activities as $activity): ?>
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-6 py-3 font-medium text-gray-900">
                            <?= htmlspecialchars($activity['username']) ?>
                        </td>
                        <td class="px-6 py-3">
                            <span class="px-2 py-0.5 rounded text-[10px] uppercase font-bold 
                                <?= $activity['role'] === 'superadmin' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700' ?>">
                                <?= htmlspecialchars($activity['role']) ?>
                            </span>
                        </td>
                        <td class="px-6 py-3">
                            <span class="px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                                <?= htmlspecialchars($activity['action']) ?>
                            </span>
                        </td>
                        <td class="px-6 py-3 text-gray-500"><?= htmlspecialchars($activity['details']) ?></td>
                        <td class="px-6 py-3 text-gray-400 text-xs font-mono">
                            <?= date('M j, H:i', strtotime($activity['created_at'])) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="px-6 py-8 text-center text-gray-400">No activities recorded yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php echo "</main></div></body></html>"; ?>