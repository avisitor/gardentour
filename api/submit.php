<?php
/**
 * API Endpoint: Submit new pin
 * 
 * Handles form submission, validates data, saves to pending_submissions,
 * and sends confirmation email.
 */

require_once __DIR__ . '/../envloader.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mailer.php';
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
 * Validate email format
 */
function validateEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
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
    
    // Basic range check
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
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = bin2hex(random_bytes(16)) . '.' . strtolower($extension);
    
    // Ensure uploads directory exists
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
    
    // Move uploaded file
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
    
    // Get and sanitize input
    $email = trim($_POST['email'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $latitude = $_POST['latitude'] ?? '';
    $longitude = $_POST['longitude'] ?? '';
    
    // Validate required fields
    if (empty($email)) {
        jsonResponse(false, 'Email is required', 400);
    }
    
    if (!validateEmail($email)) {
        jsonResponse(false, 'Please enter a valid email address', 400);
    }
    
    if (!validateCoordinates($latitude, $longitude)) {
        jsonResponse(false, 'Invalid coordinates. Please place a pin on the map.', 400);
    }
    
    // Handle image upload
    $uploadResult = handleImageUpload();
    
    // Check for upload error
    if ($uploadResult['error'] !== null) {
        jsonResponse(false, $uploadResult['error'], 400);
    }
    
    $imagePath = $uploadResult['path'];
    
    // Generate confirmation token
    $token = generateToken(64);
    
    // Calculate expiration time
    $expiryHours = (int) env('TOKEN_EXPIRY_HOURS', 24);
    $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiryHours} hours"));
    
    // Clean up any expired pending submissions for this email
    dbExecute(
        "DELETE FROM pending_submissions WHERE email = ? OR expires_at < NOW()",
        [$email]
    );
    
    // Insert into pending_submissions
    dbExecute(
        "INSERT INTO pending_submissions 
         (token, name, address, email, description, image_path, latitude, longitude, expires_at) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [
            $token,
            $name ?: null,
            $address ?: null,
            $email,
            $description ?: null,
            $imagePath,
            (float) $latitude,
            (float) $longitude,
            $expiresAt
        ]
    );
    
    // Send confirmation email
    $submissionData = [
        'latitude' => $latitude,
        'longitude' => $longitude
    ];
    
    sendConfirmationEmail($email, $token, $submissionData);

    $cookieExpiry = time() + (365 * 24 * 60 * 60);
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
                || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);

    setcookie('garden_tour_email', $email, [
        'expires' => $cookieExpiry,
        'path' => '/',
        'secure' => $isSecure,
        'httponly' => false,
        'samesite' => 'Lax'
    ]);
    
    jsonResponse(true, 'Confirmation email sent! Please check your inbox.', 200, ['email' => $email]);
    
} catch (PDOException $e) {
    error_log("Database error in submit.php: " . $e->getMessage());
    jsonResponse(false, 'A database error occurred. Please try again later.', 500);
} catch (Exception $e) {
    error_log("Error in submit.php: " . $e->getMessage());
    jsonResponse(false, 'Failed to send confirmation email. Please try again later.', 500);
}
