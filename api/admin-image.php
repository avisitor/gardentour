<?php
/**
 * API Endpoint: Admin Image Management
 * 
 * Handles image removal and replacement for admin users.
 */

require_once __DIR__ . '/../envloader.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/image.php';

// Start session for authentication
session_start();

// Set JSON response header
header('Content-Type: application/json');

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

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
            return 'Server configuration error.';
        default:
            return 'Failed to upload image.';
    }
}

/**
 * Handle image upload for admin
 */
function handleImageUpload(): array
{
    if (!isset($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) {
        return ['path' => null, 'error' => 'No image file provided.'];
    }
    
    $file = $_FILES['image'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['path' => null, 'error' => getUploadErrorMessage($file['error'])];
    }
    
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
            return ['path' => null, 'error' => 'Failed to create upload directory.'];
        }
    }
    
    if (!is_writable($uploadsDir)) {
        error_log("Upload directory not writable: $uploadsDir");
        return ['path' => null, 'error' => 'Upload directory is not writable.'];
    }
    
    $destination = $uploadsDir . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        error_log("Failed to move uploaded file to: $destination");
        return ['path' => null, 'error' => 'Failed to save uploaded image.'];
    }
    
    // Resize image if it exceeds max size
    $maxSize = (int) env('MAX_IMAGE_SIZE_MB', 5) * 1024 * 1024;
    $resizedPath = resizeImageIfNeeded($destination, $maxSize, $mimeType);
    
    if ($resizedPath === null) {
        if (file_exists($destination)) {
            unlink($destination);
        }
        return ['path' => null, 'error' => 'Failed to process image.'];
    }
    
    return ['path' => 'uploads/' . basename($resizedPath), 'error' => null];
}

/**
 * Delete an image file
 */
function deleteImageFile(?string $imagePath): void
{
    if ($imagePath) {
        $fullPath = __DIR__ . '/../' . $imagePath;
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
    }
}

// ==================== Main Logic ====================

try {
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    
    if ($id <= 0) {
        jsonResponse(false, 'Invalid submission ID', 400);
    }
    
    // Fetch the existing submission
    $submission = dbQueryOne("SELECT * FROM submissions WHERE id = ?", [$id]);
    if (!$submission) {
        jsonResponse(false, 'Submission not found', 404);
    }
    
    switch ($action) {
        case 'remove':
            // Remove the image
            deleteImageFile($submission['image_path']);
            
            dbExecute(
                "UPDATE submissions SET image_path = NULL, updated_at = NOW() WHERE id = ?",
                [$id]
            );
            
            jsonResponse(true, 'Image removed successfully');
            break;
            
        case 'replace':
            // Replace the image
            $uploadResult = handleImageUpload();
            
            if ($uploadResult['error'] !== null) {
                jsonResponse(false, $uploadResult['error'], 400);
            }
            
            // Delete old image
            deleteImageFile($submission['image_path']);
            
            // Update database with new image path
            dbExecute(
                "UPDATE submissions SET image_path = ?, updated_at = NOW() WHERE id = ?",
                [$uploadResult['path'], $id]
            );
            
            jsonResponse(true, 'Image replaced successfully', 200, ['image_path' => $uploadResult['path']]);
            break;
            
        default:
            jsonResponse(false, 'Invalid action', 400);
    }
    
} catch (PDOException $e) {
    error_log("Database error in admin-image.php: " . $e->getMessage());
    jsonResponse(false, 'A database error occurred.', 500);
} catch (Exception $e) {
    error_log("Error in admin-image.php: " . $e->getMessage());
    jsonResponse(false, 'An error occurred.', 500);
}
