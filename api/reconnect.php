<?php

require_once __DIR__ . '/../envloader.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

session_start();

function reconnectJsonResponse(bool $success, string $message, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

function reconnectCsrfTokenIsValid(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';

    return $token && $sessionToken && hash_equals($sessionToken, $token);
}

try {
    if (!reconnectCsrfTokenIsValid()) {
        reconnectJsonResponse(false, 'Invalid security token. Please refresh the page and try again.', 403);
    }

    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        reconnectJsonResponse(false, 'Please enter a valid email address.', 400);
    }

    $pinCount = dbQueryOne(
        'SELECT COUNT(*) AS count FROM submissions WHERE LOWER(email) = LOWER(?)',
        [$email]
    );

    if (!$pinCount || (int) $pinCount['count'] === 0) {
        reconnectJsonResponse(false, 'No confirmed sites were found for that email.', 404);
    }

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

    reconnectJsonResponse(true, 'Reconnected. Your pins are now editable on this device.');
} catch (PDOException $e) {
    error_log('Database error in reconnect.php: ' . $e->getMessage());
    reconnectJsonResponse(false, 'A database error occurred. Please try again later.', 500);
}
