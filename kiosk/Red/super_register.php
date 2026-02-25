<?php
// dns/kiosk/Red/super_register.php

$allowed_ips = ['127.0.0.1', '::1']; // Add your current IP here if you need to use this file
if (!in_array($_SERVER['REMOTE_ADDR'], $allowed_ips)) {
    die("Access denied. Super Admin registration is locked for security. If you need to add an admin, update the IP whitelist in this file.");
}

session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    if (file_exists(__DIR__ . '/../config.php')) {
        require_once __DIR__ . '/../config.php';
        require_once __DIR__ . '/../functions.php';
    } else {
        die("System Error: Configuration file not found.");
    }
} catch (Exception $e) {
    die("System Error: " . $e->getMessage());
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize(trim($_POST['username'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = "Username and Password are required.";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $error = "Username already taken.";
        } else {
            $hash = secureHash($password);
            $stmt = $pdo->prepare("INSERT INTO admins (username, password_hash, role) VALUES (?, ?, 'superadmin')");
            
            if ($stmt->execute([$username, $hash])) {
                $success = "Super Admin created successfully! <a href='admin_auth.php' class='underline font-bold'>Login here</a>. <br><br><strong>⚠️ IMPORTANT: Delete this file or remove your IP from the whitelist!</strong>";
            } else {
                $error = "Database error. Could not register.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register Super Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-red-900 flex items-center justify-center min-h-screen px-4">
    <div class="bg-white w-full max-w-md p-8 rounded-lg shadow-2xl">
        <h2 class="text-3xl font-bold text-center text-red-900 mb-2">Super Admin Setup</h2>
        
        <div class="bg-yellow-50 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6 text-sm">
            <p class="font-bold">⚠️ Security Warning:</p>
            <p>This page allows unrestricted creation of Super Admin accounts. <strong>Delete this file</strong> immediately after use.</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-100 text-red-700 p-3 rounded mb-4 border border-red-200"><?= $error ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="bg-green-100 text-green-700 p-3 rounded mb-4 border border-green-200"><?= $success ?></div>
        <?php endif; ?>

        <form method="POST" class="space-y-6">
            <div>
                <label class="block text-gray-700 font-bold mb-2">Username</label>
                <input type="text" name="username" class="w-full border border-gray-300 p-3 rounded focus:outline-none focus:ring-2 focus:ring-red-500" placeholder="Create a username" required>
            </div>
            <div>
                <label class="block text-gray-700 font-bold mb-2">Password</label>
                <input type="password" name="password" class="w-full border border-gray-300 p-3 rounded focus:outline-none focus:ring-2 focus:ring-red-500" placeholder="Create a password" required>
            </div>
            <button type="submit" class="w-full bg-red-700 hover:bg-red-800 text-white font-bold py-3 rounded transition duration-200 shadow-md">
                Create Super Admin
            </button>
        </form>
        
        <div class="mt-6 text-center">
            <a href="admin_auth.php" class="text-sm text-gray-500 hover:text-gray-800">Back to Regular Login</a>
        </div>
    </div>
</body>
</html>