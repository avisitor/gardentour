<?php
/**
 * API Endpoint: Get confirmed pins
 * 
 * Returns all confirmed submissions as JSON for display on the map.
 */

require_once __DIR__ . '/../envloader.php';
require_once __DIR__ . '/../includes/db.php';

// Set JSON response header
header('Content-Type: application/json');

// Allow CORS for same-origin requests
header('Access-Control-Allow-Origin: ' . env('SITE_URL', '*'));

// Only accept GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Fetch all confirmed submissions
    $pins = dbQuery(
        "SELECT id, name, address, email, description, image_path, latitude, longitude, created_at 
         FROM submissions 
         ORDER BY created_at DESC"
    );
    
    // Return success response
    echo json_encode([
        'success' => true,
        'pins' => $pins,
        'count' => count($pins)
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in pins.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load pins'
    ]);
}
