<?php
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once 'config.php';
include_once 'functions.php';
require_once 'EnvLoader.php';

// FIX #18: Load environment variables only once per request using a constant guard
if (!defined('ENV_LOADED')) {
    EnvLoader::load(__DIR__ . '/.env');
    define('ENV_LOADED', true);
}

$store_name = htmlspecialchars(EnvLoader::get('STORE_NAME', 'ASIKO'));

$raw_logo_path = EnvLoader::get('LOGO_PATH', 'ASIKO.png');
if (strpos($raw_logo_path, 'uploads/logos/') !== false && strpos($raw_logo_path, 'Red/') === false) {
    $logo_path = 'Red/' . ltrim($raw_logo_path, '/');
} else {
    $logo_path = ltrim($raw_logo_path, '/'); 
}
$logo_path = htmlspecialchars($logo_path);

// FIX #17: Use filemtime instead of time() to allow browser caching of the logo
$absolute_logo_path = __DIR__ . '/' . $logo_path;
$logo_version = file_exists($absolute_logo_path) ? filemtime($absolute_logo_path) : time();

$theme_color = htmlspecialchars(EnvLoader::get('THEME_COLOR', '#dbcb36')); 
$background_color = htmlspecialchars(EnvLoader::get('BACKGROUND_COLOR', '#fefefe')); 
$text_color = htmlspecialchars(EnvLoader::get('TEXT_COLOR', '#1a1a1a'));
$font_family = htmlspecialchars(EnvLoader::get('FONT_FAMILY', 'Inter'));
$font_size = htmlspecialchars(EnvLoader::get('FONT_SIZE', '16'));

$theme_color_hover = darken_color($theme_color, 10); 

$cart_count = 0;
if (isset($_SESSION['user_id'])) {
    try {
        if (isset($_SESSION['cart_count'])) {
            $cart_count = $_SESSION['cart_count'];
        } else {
            $cart_stmt = $pdo->prepare("SELECT SUM(quantity) FROM cart WHERE user_id = ?");
            $cart_stmt->execute([$_SESSION['user_id']]);
            $cart_count = $cart_stmt->fetchColumn() ?: 0;
            $_SESSION['cart_count'] = $cart_count; 
        }
    } catch (Exception $e) {
        error_log("Error fetching cart count: " . $e->getMessage());
        $cart_count = 0; 
    }
}
$current_page = basename($_SERVER['PHP_SELF']);
$asset_version = file_exists(__DIR__ . '/assets/css/custom.css') ? filemtime(__DIR__ . '/assets/css/custom.css') : time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $store_name ?></title>
    
    <link rel="icon" type="image/png" href="icodo.png">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=<?= urlencode($font_family) ?>:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    
    <link rel="stylesheet" href="assets/css/main.css?v=<?= $asset_version ?>">
    <link rel="stylesheet" href="assets/css/custom.css?v=<?= $asset_version ?>">

    <style>
        :root {
            --primary-cyan: <?= $theme_color ?>;
            --primary-cyan-hover: <?= $theme_color_hover ?>;
            --luxury-black: <?= $text_color ?>;
            --luxury-gray: #666666;
            --luxury-light-gray: color-mix(in srgb, <?= $background_color ?> 95%, black);
            --luxury-border: color-mix(in srgb, <?= $background_color ?> 90%, black);
            --bg-color: <?= $background_color ?>;
            --bg-light: var(--luxury-light-gray); 
        }
        html { font-size: <?= $font_size ?>px; }
        body {
            font-family: '<?= $font_family ?>', sans-serif !important;
            background-color: var(--bg-color) !important;
            color: var(--luxury-black);
        }
        .btn-primary {
            background-color: var(--primary-cyan) !important;
            border-color: var(--primary-cyan) !important;
        }
        .btn-primary:hover {
            background-color: var(--primary-cyan-hover) !important;
        }
    </style>
    
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</head>
<body class="text-base">
    <div class="page-wrapper">
        <header class="luxury-header" id="header">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 flex items-center justify-between h-20">
                <a href="index.php" class="logo flex-shrink-0 flex items-center">
                    <img src="<?= $logo_path ?>?v=<?= $logo_version ?>" alt="<?= $store_name ?> Logo" class="h-12 w-auto object-contain">
                </a>

                <nav class="hidden md:flex items-center space-x-2">
                    <a href="index.php" class="nav-link <?= $current_page == 'index.php' ? 'active' : '' ?>">Home</a>
                    
                    <a href="kiosks.php" class="nav-link <?= $current_page == 'kiosks.php' ? 'active' : '' ?>">Stores</a>

                    <a href="products.php" class="nav-link <?= $current_page == 'products.php' ? 'active' : '' ?>">Products</a>
                    <a href="cart.php" class="nav-link relative <?= $current_page == 'cart.php' ? 'active' : '' ?>">
                        Cart
                        <?php if ($cart_count > 0): ?>
                            <span class="cart-badge" id="cart-count"><?= $cart_count ?></span>
                        <?php else: ?>
                            <span class="cart-badge hidden" id="cart-count">0</span>
                        <?php endif; ?>
                    </a>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="my-account.php" class="nav-link <?= $current_page == 'my-account.php' ? 'active' : '' ?>">Account</a>
                        <a href="logout.php" class="nav-account">Logout</a>
                    <?php else: ?>
                        <a href="auth.php" class="nav-account">Sign In</a>
                    <?php endif; ?>
                </nav>
                
                <div class="md:hidden">
                    <button id="mobile-menu-button" type="button" class="inline-flex items-center justify-center p-2 rounded-md text-gray-500 hover:text-black focus:outline-none">
                        <span class="sr-only">Open main menu</span>
                        <svg id="hamburger-icon" class="h-6 w-6 block" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                        <svg id="close-icon" class="h-6 w-6 hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        </header>

        <div id="mobile-menu" class="mobile-menu-panel hidden md:hidden">
            <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3">
                <a href="index.php" class="mobile-nav-link <?= $current_page == 'index.php' ? 'active' : '' ?>">Home</a>
                
                <a href="kiosks.php" class="mobile-nav-link <?= $current_page == 'kiosks.php' ? 'active' : '' ?>">Stores</a>
                
                <a href="products.php" class="mobile-nav-link <?= $current_page == 'products.php' ? 'active' : '' ?>">Products</a>
                <a href="cart.php" class="mobile-nav-link <?= $current_page == 'cart.php' ? 'active' : '' ?>">Cart</a>
                 <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="my-account.php" class="mobile-nav-link <?= $current_page == 'my-account.php' ? 'active' : '' ?>">My Account</a>
                    <a href="logout.php" class="mobile-nav-link">Logout</a>
                <?php else: ?>
                    <a href="auth.php" class="mobile-nav-link">Sign In / Register</a>
                <?php endif; ?>
            </div>
        </div>

        <script>
            const btn = document.getElementById('mobile-menu-button');
            const menu = document.getElementById('mobile-menu');
            const hamburgerIcon = document.getElementById('hamburger-icon');
            const closeIcon = document.getElementById('close-icon');

            if (btn && menu) {
                btn.addEventListener('click', () => {
                    menu.classList.toggle('hidden');
                    hamburgerIcon.classList.toggle('hidden');
                    closeIcon.classList.toggle('hidden');
                });
            }
        </script>