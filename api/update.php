<?php
/**
 * API Endpoint: Update existing pin
 * 
 * Handles updating a submission. Requires matching email cookie.
 */

require_once __DIR__ . '/../envloader.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/image.php';

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
function jsonResponse(bool $success, string $message, int $statusCode = 200, array $extra = []): void
{
    http_response_code($statusCode);
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
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

/**
 * Validate coordinates
 */
function validateCoordinates($lat, $lng): bool
{
    if (!is_numeric($lat) || !is_numeric($lng)) {
        return false;
    }
    
    $lat = (float) $lat;
    $lng = (float) $lng;
    
    return $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180;
}

/**
 * Get human-readable upload error message
 */
function getUploadErrorMessage(int $errorCode): string
{
    switch ($errorCode) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'The image file is too large.';
        case UPLOAD_ERR_PARTIAL:
            return 'The image was only partially uploaded. Please try again.';
        case UPLOAD_ERR_NO_TMP_DIR:
        case UPLOAD_ERR_CANT_WRITE:
        case UPLOAD_ERR_EXTENSION:
            return 'Server configuration error. Please contact support.';
        default:
            return 'Failed to upload image. Please try again.';
    }
}

/**
 * Handle image upload
 * 
 * @param bool $required Whether an image upload is required
 * @return array{path: string|null, error: string|null} Result with path or error
 */
function handleImageUpload(bool $required = false): array
{
    // No file provided
    if (!isset($_FILES['picture']) || $_FILES['picture']['error'] === UPLOAD_ERR_NO_FILE) {
        if ($required) {
            return ['path' => null, 'error' => 'Please select an image to upload.'];
        }
        return ['path' => null, 'error' => null];
    }
    
    $file = $_FILES['picture'];
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['path' => null, 'error' => getUploadErrorMessage($file['error'])];
    }
    
    // Validate MIME type
    $allowedTypes = [
        'image/jpeg',
        'image/png', 
        'image/gif',
        'image/webp',
        'image/heic',
        'image/heif',
        'image/tiff',
    ];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        return ['path' => null, 'error' => 'Invalid image type. Allowed: JPG, PNG, GIF, WebP, HEIC, TIFF'];
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = bin2hex(random_bytes(16)) . '.' . strtolower($extension);
    
    $uploadsDir = __DIR__ . '/../uploads';
    if (!is_dir($uploadsDir)) {
        if (!mkdir($uploadsDir, 0775, true)) {
            return ['path' => null, 'error' => 'Failed to create upload directory. Please contact support.'];
        }
    }
    
    // Check directory is writable
    if (!is_writable($uploadsDir)) {
        error_log("Upload directory not writable: $uploadsDir");
        return ['path' => null, 'error' => 'Upload directory is not writable. Please contact support.'];
    }
    
    $destination = $uploadsDir . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        error_log("Failed to move uploaded file to: $destination");
        return ['path' => null, 'error' => 'Failed to save uploaded image. Please try again.'];
    }
    
    // Resize image if it exceeds max size
    $maxSize = (int) env('MAX_IMAGE_SIZE_MB', 5) * 1024 * 1024;
    $resizedPath = resizeImageIfNeeded($destination, $maxSize, $mimeType);
    
    if ($resizedPath === null) {
        // Cleanup on failure
        if (file_exists($destination)) {
            unlink($destination);
        }
        return ['path' => null, 'error' => 'Failed to process image. Please try a different image.'];
    }
    
    // Return path relative to project root (may have changed extension)
    return ['path' => 'uploads/' . basename($resizedPath), 'error' => null];
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
        jsonResponse(false, 'You must be the owner of this pin to edit it.', 403);
    }
    
    // Fetch the existing submission
    $submission = dbQueryOne("SELECT * FROM submissions WHERE id = ?", [$id]);
    if (!$submission) {
        jsonResponse(false, 'Submission not found', 404);
    }
    
    // Verify ownership by email
    if (strtolower($submission['email']) !== strtolower($userEmail)) {
        jsonResponse(false, 'You can only edit your own pins.', 403);
    }
    
    // Get and sanitize input
    $name = trim($_POST['name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $latitude = $_POST['latitude'] ?? $submission['latitude'];
    $longitude = $_POST['longitude'] ?? $submission['longitude'];
    
    if (!validateCoordinates($latitude, $longitude)) {
        jsonResponse(false, 'Invalid coordinates.', 400);
    }
    
    // Handle image upload
    $uploadResult = handleImageUpload();
    
    // Check for upload error
    if ($uploadResult['error'] !== null) {
        jsonResponse(false, $uploadResult['error'], 400);
    }
    
    $imagePath = $uploadResult['path'];
    
    // If new image uploaded, delete old one
    if ($imagePath && $submission['image_path']) {
        $oldImagePath = __DIR__ . '/../' . $submission['image_path'];
        if (file_exists($oldImagePath)) {
            unlink($oldImagePath);
        }
    }
    
    // Use existing image if no new one uploaded
    if (!$imagePath) {
        $imagePath = $submission['image_path'];
    }
    
    // Update the submission
    dbExecute(
        "UPDATE submissions SET 
            name = ?, 
            address = ?, 
            description = ?, 
            image_path = ?, 
            latitude = ?, 
            longitude = ?,
            updated_at = NOW()
         WHERE id = ?",
        [
            $name ?: null,
            $address ?: null,
            $description ?: null,
            $imagePath,
            (float) $latitude,
            (float) $longitude,
            $id
        ]
    );
    
    // Fetch updated data to return
    $updated = dbQueryOne("SELECT * FROM submissions WHERE id = ?", [$id]);
    
    jsonResponse(true, 'Pin updated successfully!', 200, ['pin' => $updated]);
    
} catch (PDOException $e) {
    error_log("Database error in update.php: " . $e->getMessage());
    jsonResponse(false, 'A database error occurred. Please try again later.', 500);
} catch (Exception $e) {
    error_log("Error in update.php: " . $e->getMessage());
    jsonResponse(false, 'An error occurred. Please try again later.', 500);
}
