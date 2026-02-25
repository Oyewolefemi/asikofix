<?php
// kiosk/Red/manage_admins.php
include 'header.php'; 

// Only Super Admin can access this
if (($_SESSION['admin_role'] ?? '') !== 'superadmin') {
    echo "<div class='p-6'><div class='bg-red-100 text-red-700 p-4 rounded'>Access Denied. Super Admin only.</div></div>";
    echo "</main></div></body></html>";
    exit();
}

$success = '';
$error = '';
$csrf_token = generateCsrfToken();

// Helper to handle logo upload
function uploadStoreLogo($file) {
    $target_dir = __DIR__ . "/../uploads/logos/";
    if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
    
    $ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    
    if (!in_array($ext, $allowed)) throw new Exception("Invalid logo format. Only JPG, PNG, WEBP allowed.");
    
    $filename = "store_" . uniqid() . "." . $ext;
    $target_file = $target_dir . $filename;
    
    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        return "kiosk/uploads/logos/" . $filename; // Return relative path for DB
    }
    throw new Exception("Failed to upload logo.");
}

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Session expired. Please refresh.";
    } else {
        // --- 1. TOGGLE ERP ACCESS ---
        if (isset($_POST['toggle_erp_id'])) {
            $erp_id = intval($_POST['toggle_erp_id']);
            $current_status = intval($_POST['current_status']);
            $new_status = $current_status ? 0 : 1; 

            if ($erp_id === $_SESSION['admin_id']) {
                $error = "You cannot revoke your own access.";
            } else {
                $stmt = $pdo->prepare("UPDATE admins SET erp_access = ? WHERE id = ?");
                $stmt->execute([$new_status, $erp_id]);
                $success = "Access permissions updated successfully.";
            }
        }
        // --- 2. DELETE ADMIN ---
        elseif (isset($_POST['delete_id'])) {
            $del_id = intval($_POST['delete_id']);
            if ($del_id === $_SESSION['admin_id']) {
                $error = "You cannot delete yourself!";
            } else {
                try {
                    $stmt = $pdo->prepare("DELETE FROM admins WHERE id = ?");
                    $stmt->execute([$del_id]);
                    $success = "Admin access removed successfully.";
                } catch (PDOException $e) {
                    $error = "Database error: " . $e->getMessage();
                }
            }
        }
        // --- 3. CREATE ADMIN (VENDOR) ---
        elseif (isset($_POST['create_admin'])) {
            $new_user  = sanitize(trim($_POST['new_username']));
            $new_email = sanitize(trim($_POST['new_email']));
            $new_pass  = $_POST['new_password'];
            
            $store_name = sanitize(trim($_POST['store_name']));
            $store_slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $store_name))); 
            $store_logo = 'default_logo.png';

            if (empty($new_user) || empty($new_email) || empty($new_pass) || empty($store_name)) {
                $error = "Username, Email, Password, and Store Name are required.";
            } else {
                $checkAdmin = $pdo->prepare("SELECT id FROM admins WHERE username = ? OR store_slug = ?");
                $checkAdmin->execute([$new_user, $store_slug]);
                $checkUser = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $checkUser->execute([$new_email]);

                if ($checkAdmin->rowCount() > 0) {
                    $error = "Username or Store Name (slug) is already taken.";
                } elseif ($checkUser->rowCount() > 0) {
                    $error = "Email '{$new_email}' is already in use.";
                } else {
                    try {
                        if (isset($_FILES['store_logo']) && $_FILES['store_logo']['error'] == 0) {
                            $store_logo = uploadStoreLogo($_FILES['store_logo']);
                        }

                        $pdo->beginTransaction();
                        $hash = secureHash($new_pass);
                        
                        $stmtUser = $pdo->prepare("INSERT INTO users (name, email, password, role, created_at) VALUES (?, ?, ?, 'admin', NOW())");
                        $stmtUser->execute([$new_user, $new_email, $hash]);
                        $user_id = $pdo->lastInsertId();

                        $stmtAdmin = $pdo->prepare("INSERT INTO admins (user_id, username, password_hash, role, erp_access, store_name, store_slug, store_logo, created_at) VALUES (?, ?, ?, 'admin', 0, ?, ?, ?, NOW())");
                        $stmtAdmin->execute([$user_id, $new_user, $hash, $store_name, $store_slug, $store_logo]);

                        $pdo->commit();
                        $success = "Vendor Account created! <strong>$store_name</strong> is now active.";
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $error = "System Error: " . $e->getMessage();
                    }
                }
            }
        }
        // --- 4. UPDATE VENDOR (EDIT) ---
        elseif (isset($_POST['update_vendor'])) {
            $edit_id = intval($_POST['edit_id']);
            $store_name = sanitize(trim($_POST['edit_store_name']));
            $store_slug = sanitize(trim($_POST['edit_store_slug']));
            
            // Auto-slug if empty
            if (empty($store_slug)) {
                $store_slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $store_name)));
            }

            try {
                // Check if slug is taken by another vendor
                $chk = $pdo->prepare("SELECT id FROM admins WHERE store_slug = ? AND id != ?");
                $chk->execute([$store_slug, $edit_id]);
                
                if ($chk->rowCount() > 0) {
                    $error = "Store Slug '$store_slug' is already taken by another vendor.";
                } else {
                    // Handle Logo Update
                    $logo_sql = "";
                    $params = [$store_name, $store_slug];
                    
                    if (isset($_FILES['edit_store_logo']) && $_FILES['edit_store_logo']['error'] == 0) {
                        $new_logo = uploadStoreLogo($_FILES['edit_store_logo']);
                        $logo_sql = ", store_logo = ?";
                        $params[] = $new_logo;
                    }
                    
                    $params[] = $edit_id; // For WHERE clause

                    $stmt = $pdo->prepare("UPDATE admins SET store_name = ?, store_slug = ? $logo_sql WHERE id = ?");
                    $stmt->execute($params);
                    $success = "Store details updated successfully.";
                }
            } catch (Exception $e) {
                $error = "Update failed: " . $e->getMessage();
            }
        }
    }
    $csrf_token = generateCsrfToken();
}

$admins = $pdo->query("SELECT * FROM admins ORDER BY role DESC, created_at DESC")->fetchAll();
?>

<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Manage Vendors & Staff</h1>
    <a href="superadmin_dashboard.php" class="text-blue-600 hover:underline">← Back to Dashboard</a>
</div>

<?php if ($error): ?><div class="bg-red-100 text-red-700 p-3 rounded mb-4"><?= $error ?></div><?php endif; ?>
<?php if ($success): ?><div class="bg-green-100 text-green-700 p-3 rounded mb-4"><?= $success ?></div><?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <div class="bg-white p-6 rounded-lg shadow-md h-fit">
        <h2 class="text-lg font-bold text-gray-800 mb-4">Add New Vendor</h2>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <input type="hidden" name="create_admin" value="1">
            
            <h3 class="text-xs font-bold text-gray-400 uppercase mb-2">Login Details</h3>
            <label class="block text-sm font-bold text-gray-700 mb-1">Username</label>
            <input type="text" name="new_username" class="w-full border p-2 rounded mb-2" placeholder="e.g. nike_store" required>

            <label class="block text-sm font-bold text-gray-700 mb-1">Email Address</label>
            <input type="email" name="new_email" class="w-full border p-2 rounded mb-2" placeholder="e.g. contact@nike.com" required>

            <label class="block text-sm font-bold text-gray-700 mb-1">Password</label>
            <input type="password" name="new_password" class="w-full border p-2 rounded mb-4" placeholder="Assign a password" required>

            <h3 class="text-xs font-bold text-gray-400 uppercase mb-2 pt-2 border-t">Store Details</h3>
            <label class="block text-sm font-bold text-gray-700 mb-1">Store Name</label>
            <input type="text" name="store_name" class="w-full border p-2 rounded mb-2" placeholder="e.g. Nike Factory" required>
            
            <label class="block text-sm font-bold text-gray-700 mb-1">Store Logo</label>
            <input type="file" name="store_logo" class="w-full border p-2 rounded mb-4" accept="image/*">

            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 rounded transition">
                Create Vendor
            </button>
        </form>
    </div>

    <div class="lg:col-span-2 bg-white rounded-lg shadow-md overflow-hidden">
        <div class="bg-gray-50 px-6 py-4 border-b">
            <h3 class="font-bold text-gray-700">Existing Accounts</h3>
        </div>
        <table class="w-full text-left">
            <thead class="bg-gray-100 text-sm text-gray-500 uppercase">
                <tr>
                    <th class="px-6 py-3">Store / User</th>
                    <th class="px-6 py-3">Role</th>
                    <th class="px-6 py-3 text-center">Status</th>
                    <th class="px-6 py-3 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($admins as $a): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <div class="flex items-center">
                            <?php if(!empty($a['store_logo']) && $a['store_logo'] != 'default_logo.png'): ?>
                                <img src="/<?= htmlspecialchars($a['store_logo']) ?>" class="w-8 h-8 rounded-full mr-3 object-cover border">
                            <?php endif; ?>
                            <div>
                                <div class="font-bold text-gray-800">
                                    <?= htmlspecialchars($a['store_name'] ?: $a['username']) ?>
                                </div>
                                <div class="text-xs text-gray-500"><?= htmlspecialchars($a['username']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <?php if ($a['role'] === 'superadmin'): ?>
                            <span class="bg-purple-100 text-purple-700 px-2 py-1 rounded text-xs font-bold">SUPER</span>
                        <?php else: ?>
                            <span class="bg-blue-100 text-blue-700 px-2 py-1 rounded text-xs font-bold">VENDOR</span>
                        <?php endif; ?>
                    </td>
                    
                    <td class="px-6 py-4 text-center">
                        <?php if ($a['role'] === 'superadmin'): ?>
                            <span class="text-green-600 font-bold text-xs">ACTIVE</span>
                        <?php else: ?>
                            <form method="POST" class="inline">
                                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                <input type="hidden" name="toggle_erp_id" value="<?= $a['id'] ?>">
                                <input type="hidden" name="current_status" value="<?= $a['erp_access'] ?>">
                                
                                <?php if ($a['erp_access'] == 1): ?>
                                    <button type="submit" class="text-green-600 font-bold text-xs hover:underline">✅ Active</button>
                                <?php else: ?>
                                    <button type="submit" class="text-gray-400 font-bold text-xs hover:underline">❌ Suspended</button>
                                <?php endif; ?>
                            </form>
                        <?php endif; ?>
                    </td>

                    <td class="px-6 py-4 text-right flex justify-end gap-2">
                        <?php if ($a['id'] !== $_SESSION['admin_id']): ?>
                            <button onclick="openEditModal(
                                <?= $a['id'] ?>, 
                                '<?= addslashes($a['store_name'] ?? '') ?>', 
                                '<?= addslashes($a['store_slug'] ?? '') ?>'
                            )" class="text-blue-500 hover:text-blue-700 font-bold text-sm">Edit</button>

                            <form method="POST" onsubmit="return confirm('Are you sure? This will delete the vendor and their products.');" class="inline">
                                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                <input type="hidden" name="delete_id" value="<?= $a['id'] ?>">
                                <button type="submit" class="text-red-500 hover:text-red-700 font-bold text-sm">Delete</button>
                            </form>
                        <?php else: ?>
                            <span class="text-gray-300 text-sm">--</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
        <h2 class="text-xl font-bold mb-4">Edit Vendor Details</h2>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <input type="hidden" name="update_vendor" value="1">
            <input type="hidden" name="edit_id" id="modal_edit_id">
            
            <label class="block text-sm font-bold text-gray-700 mb-1">Store Name</label>
            <input type="text" name="edit_store_name" id="modal_store_name" class="w-full border p-2 rounded mb-3" required>
            
            <label class="block text-sm font-bold text-gray-700 mb-1">Store Slug (URL)</label>
            <input type="text" name="edit_store_slug" id="modal_store_slug" class="w-full border p-2 rounded mb-3" placeholder="e.g. my-store">
            
            <label class="block text-sm font-bold text-gray-700 mb-1">Update Logo (Optional)</label>
            <input type="file" name="edit_store_logo" class="w-full border p-2 rounded mb-6" accept="image/*">
            
            <div class="flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('editModal').classList.add('hidden')" class="bg-gray-300 text-gray-700 px-4 py-2 rounded font-bold hover:bg-gray-400">Cancel</button>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded font-bold hover:bg-blue-700">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(id, name, slug) {
    document.getElementById('modal_edit_id').value = id;
    document.getElementById('modal_store_name').value = name;
    document.getElementById('modal_store_slug').value = slug;
    document.getElementById('editModal').classList.remove('hidden');
}
</script>

<?php echo "</main></div></body></html>"; ?>