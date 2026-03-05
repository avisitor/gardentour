<?php
/**
 * API Endpoint: Delete a pin
 * 
 * Handles deleting a submission. Requires matching email cookie.
 */

require_once __DIR__ . '/../envloader.php';
require_once __DIR__ . '/../includes/db.php';

// Set JSON response header
header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Start session for CSRF validation
session_start();

/**
 * Send JSON response and exit
 */
function jsonResponse(bool $success, string $message, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

/**
 * Validate CSRF token
 */
function validateCsrfToken(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    
    return $token && $sessionToken && hash_equals($sessionToken, $token);
}

// ==================== Main Logic ====================

try {
    // Validate CSRF token
    if (!validateCsrfToken()) {
        jsonResponse(false, 'Invalid security token. Please refresh the page and try again.', 403);
    }
    
    // Get submission ID
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) {
        jsonResponse(false, 'Invalid submission ID', 400);
    }
    
    // Get the user's email from cookie
    $userEmail = $_COOKIE['garden_tour_email'] ?? '';
    if (empty($userEmail)) {
        jsonResponse(false, 'You must be the owner of this pin to delete it.', 403);
    }
    
    // Fetch the existing submission
    $submission = dbQueryOne("SELECT * FROM submissions WHERE id = ?", [$id]);
    if (!$submission) {
        jsonResponse(false, 'Submission not found', 404);
    }
    
    // Verify ownership by email
    if (strtolower($submission['email']) !== strtolower($userEmail)) {
        jsonResponse(false, 'You can only delete your own pins.', 403);
    }
    
    // Delete associated image file if exists
    if ($submission['image_path']) {
        $imagePath = __DIR__ . '/../' . $submission['image_path'];
        if (file_exists($imagePath)) {
            unlink($imagePath);
        }
    }
    
    // Delete the submission
    dbExecute("DELETE FROM submissions WHERE id = ?", [$id]);
    
    jsonResponse(true, 'Pin deleted successfully.');
    
} catch (PDOException $e) {
    error_log("Database error in delete.php: " . $e->getMessage());
    jsonResponse(false, 'A database error occurred. Please try again later.', 500);
} catch (Exception $e) {
    error_log("Error in delete.php: " . $e->getMessage());
    jsonResponse(false, 'An error occurred. Please try again later.', 500);
}
