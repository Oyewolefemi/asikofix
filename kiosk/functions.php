<?php
// /kiosk/functions.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!function_exists('sanitize')) {
    function sanitize($data) {
        return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('printError')) {
    function printError($message) {
        echo "<div class='container mx-auto my-4'><div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded' role='alert'><strong>Error:</strong> " . htmlspecialchars($message) . "</div></div>";
    }
}

if (!function_exists('printSuccess')) {
    function printSuccess($message) {
        echo "<div class='container mx-auto my-4'><div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded' role='alert'>" . htmlspecialchars($message) . "</div></div>";
    }
}

if (!function_exists('formatCurrency')) {
    function formatCurrency($amount) {
        return '₦' . number_format($amount, 2);
    }
}

if (!function_exists('getApplicablePrice')) {
    function getApplicablePrice($item, $bulk_discounts_array) {
        $base_price = (!empty($item['sale_price']) && $item['sale_price'] > 0) ? (float)$item['sale_price'] : (float)$item['price'];
        $discount_percent = 0;
        if (isset($bulk_discounts_array[$item['product_id']])) {
            foreach ($bulk_discounts_array[$item['product_id']] as $tier) {
                if ($item['quantity'] >= $tier['min_quantity']) {
                    $discount_percent = (float)$tier['discount_percentage'];
                    break; 
                }
            }
        }
        $discount_amount = ($base_price * $discount_percent) / 100;
        return $base_price - $discount_amount;
    }
}

// --- NEW LUHN ALGORITHM FUNCTION ---
if (!function_exists('generateLuhnOrderNumber')) {
    /**
     * Generates a random order number that satisfies the Luhn algorithm.
     * @param int $length Total length of the order number (default 8).
     * @return string The generated order number.
     */
    function generateLuhnOrderNumber($length = 8) {
        // 1. Generate N-1 random digits
        $body = '';
        for ($i = 0; $i < $length - 1; $i++) {
            $body .= mt_rand(0, 9);
        }

        // 2. Calculate Luhn Check Digit
        $sum = 0;
        $alt = true;
        // Process digits from right to left
        for ($i = strlen($body) - 1; $i >= 0; $i--) {
            $n = intval($body[$i]);
            if ($alt) {
                $n *= 2;
                if ($n > 9) $n -= 9;
            }
            $sum += $n;
            $alt = !$alt; // Toggle flag
        }

        $checkDigit = (10 - ($sum % 10)) % 10;
        
        return $body . $checkDigit;
    }
}

if (!function_exists('getProductImage')) {
    function getProductImage($imagePath) {
        if (empty($imagePath)) return 'https://placehold.co/100x100/f8f9fa/ccc?text=No+Image';
        $fixedPath = str_replace('/kios/', '/kiosk/', $imagePath);
        if (strpos($fixedPath, '/') !== 0) $fixedPath = '/kiosk/Red/uploads/' . $fixedPath;
        return $fixedPath; 
    }
}

if (!function_exists('secureHash')) {
    function secureHash($password) { return password_hash($password, PASSWORD_DEFAULT); }
}

if (!function_exists('verifyPassword')) {
    function verifyPassword($password, $hash) { return password_verify($password, $hash); }
}

if (!function_exists('generateCsrfToken')) {
    function generateCsrfToken() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf_token'];
    }
}
    
if (!function_exists('verifyCsrfToken')) {
    function verifyCsrfToken($token) {
        if (isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token)) {
            unset($_SESSION['csrf_token']); 
            return true;
        }
        return false;
    }
}

if (!function_exists('logActivity')) {
    function logActivity($admin_id, $action, $details = '') {
        global $pdo; 
        if (!$pdo) return;
        try {
            $stmt = $pdo->prepare("INSERT INTO admin_activities (admin_id, action, details) VALUES (?, ?, ?)");
            $stmt->execute([$admin_id, $action, $details]);
        } catch (Exception $e) {}
    }
}

/**
 * NEW: Darkens a hex color by a given percentage.
 */
if (!function_exists('darken_color')) {
    function darken_color($hex, $percent) {
        $hex = str_replace('#', '', $hex);
        if (strlen($hex) == 3) {
            $hex = str_repeat(substr($hex, 0, 1), 2) . str_repeat(substr($hex, 1, 1), 2) . str_repeat(substr($hex, 2, 1), 2);
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $factor = 1 - ($percent / 100);
        $r = max(0, min(255, round($r * $factor)));
        $g = max(0, min(255, round($g * $factor)));
        $b = max(0, min(255, round($b * $factor)));
        return sprintf("#%02x%02x%02x", $r, $g, $b);
    }
}
?>