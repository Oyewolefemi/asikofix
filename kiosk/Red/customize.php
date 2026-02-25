<?php
include 'header.php'; 
require_once __DIR__ . '/../EnvLoader.php';

// --- SECURITY: SUPER ADMIN ONLY ---
if (($_SESSION['admin_role'] ?? '') !== 'superadmin') {
    // Redirect to standard dashboard if not authorized
    echo "<script>window.location.href = 'admin_dashboard.php';</script>";
    exit;
}

// Use the correct path to the .env file
$envFilePath = __DIR__ . '/../.env';
EnvLoader::load($envFilePath);

$error = '';
$success = '';

if (!is_writable($envFilePath)) {
    $error = "CRITICAL ERROR: The configuration file ($envFilePath) is not writable. Please check file permissions.";
} 

// Helper function to update the .env file safely
function updateEnvFile($filePath, $key, $value) {
    $value = str_replace('"', '\"', $value);
    
    $content = file_get_contents($filePath);
    if ($content === false) return false;

    $key = strtoupper($key);
    $pattern = "/^{$key}=.*/m";
    $replacement = "{$key}=\"{$value}\"";
    
    if (preg_match($pattern, $content)) {
        $content = preg_replace($pattern, $replacement, $content);
    } else {
        if (substr($content, -1) !== "\n" && !empty($content)) {
            $content .= "\n";
        }
        $content .= $replacement . "\n";
    }
    return file_put_contents($filePath, $content) !== false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_writable($envFilePath)) {
    try {
        // Save text inputs
        $settings = [
            'STORE_NAME', 'THEME_COLOR', 'BACKGROUND_COLOR', 'TEXT_COLOR',
            'FONT_FAMILY', 'FONT_SIZE', 'HERO_TITLE', 'HERO_SUBTITLE'
        ];
        
        foreach($settings as $setting) {
            if(isset($_POST[strtolower($setting)])) {
                updateEnvFile($envFilePath, $setting, sanitize($_POST[strtolower($setting)]));
            }
        }

        // Handle Logo Upload
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $old_logo_path = EnvLoader::get('LOGO_PATH');
            
            // --- FIX: Dynamic Path (No more /kiosk/ hardcoding) ---
            $upload_subdir = 'Red/uploads/logos/';
            $upload_dir_absolute = __DIR__ . '/uploads/logos/';

            if (!is_dir($upload_dir_absolute)) {
                mkdir($upload_dir_absolute, 0755, true);
            }
            
            $file_ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            $new_filename = 'logo_' . time() . '.' . $file_ext;
            $destination_absolute = $upload_dir_absolute . $new_filename;
            
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $destination_absolute)) {
                $logo_path_for_env = $upload_subdir . $new_filename;
                updateEnvFile($envFilePath, 'LOGO_PATH', $logo_path_for_env);
            } else { 
                $error = 'Failed to move uploaded logo. Check folder permissions.'; 
            }
        }
        
        if (empty($error)) {
            $success = "Store settings updated! Please hard-refresh (Ctrl+F5) to see changes.";
            EnvLoader::load($envFilePath);
        }
    } catch (Exception $e) { $error = "Error updating settings file: " . $e->getMessage(); }
}

$current_logo_db = EnvLoader::get('LOGO_PATH');
$admin_logo_preview = '';
if ($current_logo_db) {
    if (strpos($current_logo_db, 'Red/') === 0) {
        $admin_logo_preview = substr($current_logo_db, 4); 
    } else {
        $admin_logo_preview = '../' . ltrim($current_logo_db, '/');
    }
}
?>
<style>
    .preview-frame { transform: scale(0.9); transform-origin: top center; transition: all 0.3s ease; }
    .control-group { border: 1px solid #e5e7eb; border-radius: 8px; padding: 1rem; margin-bottom: 1.5rem; }
    .control-group h3 { font-weight: 600; color: #374151; margin-bottom: 1rem; border-bottom: 1px solid #e5e7eb; padding-bottom: 0.5rem; }
</style>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8 max-w-7xl mx-auto">
    <div class="lg:col-span-1 bg-white p-6 rounded-lg shadow-lg">
        <h1 class="text-2xl font-bold text-gray-800 mb-6">Store Customizer</h1>

        <?php if ($error) echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4' role='alert'>$error</div>"; ?>
        <?php if ($success) echo "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4' role='alert'>$success</div>"; ?>

        <form action="customize.php" method="POST" enctype="multipart/form-data">
            <div class="control-group">
                <h3>Branding</h3>
                <label for="store_name" class="block text-sm font-medium text-gray-700 mb-2">Store Name</label>
                <input type="text" name="store_name" id="store_name" value="<?= htmlspecialchars(EnvLoader::get('STORE_NAME')) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md mb-4" required>
                <label class="block text-sm font-medium text-gray-700 mb-2">Store Logo</label>
                <input type="file" name="logo" id="logo-input" class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"/>
            </div>

            <div class="control-group">
                <h3>Hero Section</h3>
                <label for="hero_title" class="block text-sm font-medium text-gray-700 mb-2">Hero Title</label>
                <input type="text" name="hero_title" id="hero_title" value="<?= htmlspecialchars(EnvLoader::get('HERO_TITLE')) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md mb-2">
                <label for="hero_subtitle" class="block text-sm font-medium text-gray-700 mb-2">Hero Subtitle</label>
                <input type="text" name="hero_subtitle" id="hero_subtitle" value="<?= htmlspecialchars(EnvLoader::get('HERO_SUBTITLE')) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md">
            </div>

            <div class="control-group">
                <h3>Colors</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="theme_color" class="block text-sm font-medium text-gray-700 mb-2">Primary</label>
                        <input type="color" name="theme_color" id="theme_color" value="<?= htmlspecialchars(EnvLoader::get('THEME_COLOR')) ?>" class="h-10 w-full p-1 border rounded-md">
                    </div>
                    <div>
                        <label for="background_color" class="block text-sm font-medium text-gray-700 mb-2">Background</label>
                        <input type="color" name="background_color" id="background_color" value="<?= htmlspecialchars(EnvLoader::get('BACKGROUND_COLOR')) ?>" class="h-10 w-full p-1 border rounded-md">
                    </div>
                    <div>
                        <label for="text_color" class="block text-sm font-medium text-gray-700 mb-2">Text</label>
                        <input type="color" name="text_color" id="text_color" value="<?= htmlspecialchars(EnvLoader::get('TEXT_COLOR')) ?>" class="h-10 w-full p-1 border rounded-md">
                    </div>
                </div>
            </div>

            <div class="control-group">
                <h3>Typography</h3>
                <label for="font_family" class="block text-sm font-medium text-gray-700 mb-2">Font Family</label>
                <select name="font_family" id="font_family" class="w-full px-3 py-2 border border-gray-300 rounded-md mb-4">
                    <?php $current_font = EnvLoader::get('FONT_FAMILY'); ?>
                    <option value="Inter" <?= $current_font === 'Inter' ? 'selected' : '' ?>>Inter</option>
                    <option value="Lato" <?= $current_font === 'Lato' ? 'selected' : '' ?>>Lato</option>
                    <option value="Montserrat" <?= $current_font === 'Montserrat' ? 'selected' : '' ?>>Montserrat</option>
                    <option value="Roboto" <?= $current_font === 'Roboto' ? 'selected' : '' ?>>Roboto</option>
                </select>
                <label for="font_size" class="block text-sm font-medium text-gray-700 mb-2">Base Font Size: <span id="font-size-label"></span>px</label>
                <input type="range" name="font_size" id="font_size" min="14" max="18" step="1" value="<?= htmlspecialchars(EnvLoader::get('FONT_SIZE')) ?>" class="w-full">
            </div>

            <div class="mt-8">
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-md" <?= !is_writable($envFilePath) ? 'disabled' : '' ?>>
                    Save & Publish Changes
                </button>
            </div>
        </form>
    </div>

    <div class="lg:col-span-2 bg-gray-200 p-4 rounded-lg overflow-hidden">
        <div id="preview-container" class="preview-frame w-full h-full rounded-md shadow-inner overflow-y-auto" style="background-color: <?= htmlspecialchars(EnvLoader::get('BACKGROUND_COLOR')) ?>;">
            <header id="preview-header" class="p-4 flex justify-between items-center border-b">
                <div class="flex items-center">
                    <img id="logo-preview" src="<?= htmlspecialchars($admin_logo_preview) ?>?v=<?= time() ?>" alt="Logo" class="h-10 object-contain">
                    <span id="preview-store-name" class="ml-3 font-bold text-lg"><?= htmlspecialchars(EnvLoader::get('STORE_NAME')) ?></span>
                </div>
                <nav class="flex items-center space-x-4">
                    <span class="text-sm">Home</span>
                    <span class="text-sm">Products</span>
                    <span id="preview-header-btn" class="text-sm text-white font-semibold py-2 px-4 rounded-md">Sign In</span>
                </nav>
            </header>
            <main id="preview-main" class="p-8">
                <div id="preview-hero" class="text-white text-center p-12 rounded-lg mb-8">
                    <h1 id="preview-hero-title" class="text-4xl font-bold"></h1>
                    <p id="preview-hero-subtitle" class="text-xl opacity-90 mt-2"></p>
                </div>
                <h2 id="preview-product-title" class="text-2xl font-bold mb-4">Featured Product</h2>
                <p id="preview-product-text" class="mb-6">This is how a product description would look.</p>
                <button id="preview-button" class="px-6 py-3 text-white font-semibold rounded-lg shadow-md">Add to Cart</button>
            </main>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    function getHoverColor(hexColor) {
        if (!hexColor || hexColor.length !== 7) return '#000000';
        try {
            let r = parseInt(hexColor.slice(1, 3), 16);
            let g = parseInt(hexColor.slice(3, 5), 16);
            let b = parseInt(hexColor.slice(5, 7), 16);
            r = Math.floor(r * 0.9);
            g = Math.floor(g * 0.9);
            b = Math.floor(b * 0.9);
            return `#${r.toString(16).padStart(2, '0')}${g.toString(16).padStart(2, '0')}${b.toString(16).padStart(2, '0')}`;
        } catch (e) {
            return '#000000';
        }
    }

    const inputs = {
        name: document.getElementById('store_name'),
        heroTitle: document.getElementById('hero_title'),
        heroSubtitle: document.getElementById('hero_subtitle'),
        themeColor: document.getElementById('theme_color'),
        bgColor: document.getElementById('background_color'),
        textColor: document.getElementById('text_color'),
        fontFamily: document.getElementById('font_family'),
        fontSize: document.getElementById('font_size'),
        logo: document.getElementById('logo-input')
    };

    const previews = {
        container: document.getElementById('preview-container'),
        storeName: document.getElementById('preview-store-name'),
        headerBtn: document.getElementById('preview-header-btn'),
        main: document.getElementById('preview-main'),
        hero: document.getElementById('preview-hero'),
        heroTitle: document.getElementById('preview-hero-title'),
        heroSubtitle: document.getElementById('preview-hero-subtitle'),
        productTitle: document.getElementById('preview-product-title'),
        productText: document.getElementById('preview-product-text'),
        button: document.getElementById('preview-button'),
        logo: document.getElementById('logo-preview'),
        fontSizeLabel: document.getElementById('font-size-label')
    };
    
    function updatePreview() {
        const values = {
            name: inputs.name.value,
            heroTitle: inputs.heroTitle.value,
            heroSubtitle: inputs.heroSubtitle.value,
            theme: inputs.themeColor.value,
            bg: inputs.bgColor.value,
            text: inputs.textColor.value,
            font: inputs.fontFamily.value,
            size: inputs.fontSize.value
        };

        const themeHover = getHoverColor(values.theme);

        previews.container.style.backgroundColor = values.bg;
        previews.container.style.color = values.text;
        previews.container.style.fontFamily = `'${values.font}', sans-serif`;
        previews.container.style.fontSize = `${values.size}px`;
        if(previews.storeName) previews.storeName.textContent = values.name;
        previews.headerBtn.style.backgroundColor = values.theme;
        previews.button.style.backgroundColor = values.theme;
        previews.hero.style.background = `linear-gradient(135deg, ${values.theme} 0%, ${themeHover} 100%)`;
        previews.heroTitle.textContent = values.heroTitle || "Your Store Title";
        previews.heroSubtitle.textContent = values.heroSubtitle || "A catchy tagline about your products";
        previews.fontSizeLabel.textContent = values.size;
    }

    inputs.logo.addEventListener('change', e => {
        if (e.target.files && e.target.files[0]) {
            const reader = new FileReader();
            reader.onload = function(event) {
                previews.logo.src = event.target.result;
            }
            reader.readAsDataURL(e.target.files[0]);
        }
    });

    Object.values(inputs).forEach(el => {
        if (el) {
            if (el.type === 'color' || el.type === 'range' || el.tagName === 'SELECT') {
                el.addEventListener('input', updatePreview);
            } else {
                el.addEventListener('keyup', updatePreview);
            }
        }
    });

    updatePreview();
});
</script>

<?php 
echo "</main></div></body></html>";
?>