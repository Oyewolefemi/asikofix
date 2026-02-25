<?php
// kiosk/Red/approve_order.php
session_start();
include '../config.php';
include '../functions.php'; // For sanitize() or logs if needed

// 1. Security Check
if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'superadmin') {
    die("Access Denied. Only Super Admin can approve payments.");
}

// 2. Get Order ID
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

if ($order_id > 0) {
    try {
        // 3. Update Status
        // We change 'payment_submitted' -> 'active' (or 'processing' if you prefer)
        $stmt = $pdo->prepare("UPDATE orders SET status = 'active', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$order_id]);

        // Optional: Log admin activity
        // logAdminActivity($_SESSION['admin_id'], 'Approved Order #' . $order_id);

        // 4. Redirect Back
        header("Location: orders.php?msg=approved");
        exit;
    } catch (PDOException $e) {
        die("Error approving order: " . $e->getMessage());
    }
} else {
    header("Location: orders.php?error=invalid_id");
    exit;
}
?>