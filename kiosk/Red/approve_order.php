<?php
// kiosk/Red/approve_order.php
session_start();
include '../config.php';
include '../functions.php';

// 1. Security Check
if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'superadmin') {
    die("Access Denied. Only Super Admin can approve payments.");
}

// 2. CSRF & Method Protection
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Method not allowed');
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    die('Invalid or expired security token. Please go back and try again.');
}

// 3. Get Order ID
$order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;

if ($order_id > 0) {
    try {
        $stmt = $pdo->prepare("UPDATE orders SET status = 'active', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$order_id]);

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