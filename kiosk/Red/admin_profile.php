<?php
// dns/kiosk/Red/admin_profile.php
include 'header.php';

// Handle Profile Updates
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Handle Password Update (Existing logic)
    if (!empty($_POST['new_password'])) {
        $new_pass = $_POST['new_password'];
        $confirm_pass = $_POST['confirm_password'];
        
        if ($new_pass !== $confirm_pass) {
            $error = "New passwords do not match.";
        } else {
            $hash = secureHash($new_pass);
            $stmt = $pdo->prepare("UPDATE admins SET password_hash = ? WHERE id = ?");
            if ($stmt->execute([$hash, $_SESSION['admin_id']])) {
                $success = "Password updated successfully.";
            } else {
                $error = "Failed to update password.";
            }
        }
    }

    // 2. Handle Business Details Update (NEW)
    if (isset($_POST['update_details'])) {
        $store_name = sanitize($_POST['store_name']);
        $phone = sanitize($_POST['phone']);
        $bank_details = sanitize($_POST['bank_details']);
        $biz_desc = sanitize($_POST['business_description']);

        $stmt = $pdo->prepare("UPDATE admins SET store_name = ?, phone = ?, bank_details = ?, business_description = ? WHERE id = ?");
        if ($stmt->execute([$store_name, $phone, $bank_details, $biz_desc, $_SESSION['admin_id']])) {
            $success = "Business profile updated successfully.";
        } else {
            $error = "Failed to update profile.";
        }
    }
}

// Fetch current admin details
$stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
$stmt->execute([$_SESSION['admin_id']]);
$admin = $stmt->fetch();
?>

<div class="max-w-4xl mx-auto mt-8">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">My Admin Profile</h1>

    <?php if ($success): ?><div class="bg-green-100 text-green-700 p-4 rounded mb-4"><?= $success ?></div><?php endif; ?>
    <?php if ($error): ?><div class="bg-red-100 text-red-700 p-4 rounded mb-4"><?= $error ?></div><?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        
        <div class="bg-white p-6 rounded-lg shadow-md h-fit">
            <h2 class="text-xl font-bold mb-4 border-b pb-2">Login Credentials</h2>
            <div class="mb-4">
                <label class="block text-gray-600 text-sm font-bold mb-1">Username</label>
                <input type="text" value="<?= htmlspecialchars($admin['username']) ?>" disabled class="w-full border bg-gray-100 p-2 rounded cursor-not-allowed">
            </div>
            
            <form method="POST">
                <h3 class="text-md font-semibold mt-6 mb-2 text-gray-500">Change Password</h3>
                <div class="mb-3">
                    <input type="password" name="new_password" placeholder="New Password" class="w-full border p-2 rounded focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="mb-4">
                    <input type="password" name="confirm_password" placeholder="Confirm New Password" class="w-full border p-2 rounded focus:ring-2 focus:ring-blue-500">
                </div>
                <button type="submit" class="w-full bg-gray-800 text-white font-bold py-2 rounded hover:bg-gray-900 transition">Update Password</button>
            </form>
        </div>

        <div class="bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-xl font-bold mb-4 border-b pb-2">Business / Vendor Details</h2>
            <p class="text-sm text-gray-500 mb-4">These details help the Super Admin identify your store and process your payouts.</p>
            
            <form method="POST">
                <input type="hidden" name="update_details" value="1">
                
                <div class="mb-4">
                    <label class="block text-gray-700 font-bold mb-1">Store Name</label>
                    <input type="text" name="store_name" value="<?= htmlspecialchars($admin['store_name'] ?? '') ?>" placeholder="e.g. Ade's Electronics" class="w-full border p-2 rounded">
                </div>

                <div class="mb-4">
                    <label class="block text-gray-700 font-bold mb-1">Phone Number</label>
                    <input type="text" name="phone" value="<?= htmlspecialchars($admin['phone'] ?? '') ?>" placeholder="e.g. 08012345678" class="w-full border p-2 rounded">
                </div>

                <div class="mb-4">
                    <label class="block text-gray-700 font-bold mb-1">Bank Account Details</label>
                    <textarea name="bank_details" rows="3" placeholder="Bank Name, Account Number, Account Name" class="w-full border p-2 rounded"><?= htmlspecialchars($admin['bank_details'] ?? '') ?></textarea>
                </div>

                <div class="mb-4">
                    <label class="block text-gray-700 font-bold mb-1">Business Description</label>
                    <textarea name="business_description" rows="3" placeholder="What do you sell?" class="w-full border p-2 rounded"><?= htmlspecialchars($admin['business_description'] ?? '') ?></textarea>
                </div>

                <button type="submit" class="w-full bg-blue-600 text-white font-bold py-2 rounded hover:bg-blue-700 transition">Save Business Details</button>
            </form>
        </div>

    </div>
</div>

<?php echo "</main></div></body></html>"; ?>