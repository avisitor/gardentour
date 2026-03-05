<?php
/**
 * Email Confirmation Handler
 * 
 * Validates confirmation token and moves pending submission to confirmed.
 */

require_once __DIR__ . '/envloader.php';
require_once __DIR__ . '/includes/db.php';

$siteName = 'Maui Garden Tour Map';
$adminEmail = env('ADMIN_EMAIL', '');

// Get token from URL
$token = $_GET['token'] ?? '';

$error = null;
$success = false;
$redirectUrl = null;

if (empty($token)) {
    $error = 'Invalid confirmation link. No token provided.';
} else {
    try {
        // Look up the pending submission
        $pending = dbQueryOne(
            "SELECT * FROM pending_submissions WHERE token = ?",
            [$token]
        );
        
        if (!$pending) {
            // Check if this token was already confirmed (by looking in submissions)
            // We can't directly check since we don't store the token, but we can inform the user
            $error = 'This confirmation link is invalid or has already been used.';
        } elseif (strtotime($pending['expires_at']) < time()) {
            // Token has expired
            $error = 'This confirmation link has expired. Please submit your pin again.';
            
            // Clean up expired record
            dbExecute("DELETE FROM pending_submissions WHERE id = ?", [$pending['id']]);
        } else {
            // Token is valid - move to confirmed submissions
            
            // Insert into submissions table
            dbExecute(
                "INSERT INTO submissions 
                 (name, address, email, description, image_path, latitude, longitude) 
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [
                    $pending['name'],
                    $pending['address'],
                    $pending['email'],
                    $pending['description'],
                    $pending['image_path'],
                    $pending['latitude'],
                    $pending['longitude']
                ]
            );
            
            // Delete from pending
            dbExecute("DELETE FROM pending_submissions WHERE id = ?", [$pending['id']]);
            
            // Set cookie to allow editing this user's pins (30 days)
            $cookieExpiry = time() + (30 * 24 * 60 * 60);
            $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
                        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
                        || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
            
            setcookie('garden_tour_email', $pending['email'], [
                'expires' => $cookieExpiry,
                'path' => '/',
                'secure' => $isSecure,
                'httponly' => false,  // Allow JS to read for debugging; PHP passes it anyway
                'samesite' => 'Lax'
            ]);
            
            $success = true;
            
            // Build redirect URL to show the pin on the map
            $siteUrl = rtrim(env('SITE_URL', ''), '/');
            $redirectUrl = $siteUrl . '/?confirmed=1&lat=' . $pending['latitude'] . '&lng=' . $pending['longitude'];
        }
    } catch (PDOException $e) {
        error_log("Database error in confirm.php: " . $e->getMessage());
        $error = 'A database error occurred. Please try again or contact support.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Your Pin - <?= htmlspecialchars($siteName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #f5f7f5 0%, #e8f5e9 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: #1a1a1a;
        }
        
        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            padding: 40px;
            max-width: 480px;
            width: 100%;
            text-align: center;
        }
        
        .icon {
            font-size: 4rem;
            margin-bottom: 20px;
        }
        
        .icon-success {
            color: #2e7d32;
        }
        
        .icon-error {
            color: #d32f2f;
        }
        
        h1 {
            font-size: 1.5rem;
            margin-bottom: 15px;
            color: #1a1a1a;
        }
        
        p {
            color: #666;
            margin-bottom: 15px;
            line-height: 1.6;
        }
        
        .btn {
            display: inline-block;
            background: #2e7d32;
            color: white;
            padding: 14px 28px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            margin-top: 20px;
            transition: background 0.3s ease;
        }
        
        .btn:hover {
            background: #1b5e20;
        }
        
        .btn-secondary {
            background: #f5f5f5;
            color: #666;
        }
        
        .btn-secondary:hover {
            background: #e0e0e0;
        }
        
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            font-size: 0.875rem;
            color: #999;
        }
        
        .footer a {
            color: #2e7d32;
            text-decoration: none;
        }
        
        .footer a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 480px) {
            .container {
                padding: 30px 20px;
            }
            
            h1 {
                font-size: 1.25rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($success): ?>
            <div class="icon icon-success">✓</div>
            <h1>Your Pin is Now Live!</h1>
            <p>Thank you for confirming your email. Your garden location has been added to the map and is now visible to everyone.</p>
            <a href="<?= htmlspecialchars($redirectUrl) ?>" class="btn">View on Map</a>
        <?php else: ?>
            <div class="icon icon-error">✕</div>
            <h1>Confirmation Failed</h1>
            <p><?= htmlspecialchars($error) ?></p>
            <a href="./" class="btn btn-secondary">Back to Map</a>
        <?php endif; ?>
        
        <?php if ($adminEmail): ?>
        <div class="footer">
            <p>Need help? <a href="mailto:<?= htmlspecialchars($adminEmail) ?>">Contact us</a></p>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
