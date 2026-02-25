<?php
header('Content-Type: application/json');

$location = $_GET['location'] ?? '';

// --- SIMULATED API CALL ---
// Replace this function with your actual API call to a logistics provider.
function getShippingFeeFromApi($destination) {
    // This is a dummy implementation. In a real scenario, you would use cURL
    // to call an external API endpoint like https://api.logistics.com/quote
    // with the destination and other order details (weight, size, etc.).
    
    $fees = [
        'Island' => 2500,
        'Mainland' => 2000,
        'Inter-state (park)' => 4500,
        'Inter-state (doorstep)' => 7500,
        'Pick-up' => 0
    ];
    
    // Simulate a network delay to mimic a real API call
    sleep(1); 
    
    return $fees[$destination] ?? 0;
}

$fee = getShippingFeeFromApi($location);

echo json_encode(['fee_amount' => $fee]);
?>