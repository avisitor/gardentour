<?php
/**
 * Maui Garden Tour Map - Main Page
 */
require_once __DIR__ . '/envloader.php';

$googleMapsApiKey = env('GOOGLE_MAPS_API_KEY', '');
$googleMapsMapId = env('GOOGLE_MAPS_MAP_ID', 'DEMO_MAP_ID');
$adminEmail = env('ADMIN_EMAIL', '');
$siteName = 'Maui Native Garden Tour Map';

// Generate CSRF token
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($siteName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <!-- Favicons -->
    <link rel="icon" type="image/svg+xml" href="images/favicon.svg">
    <link rel="icon" type="image/x-icon" href="images/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="images/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="images/favicon-16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="images/apple-touch-icon.png">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <a href="/" class="logo">
                <img src="images/logo.svg" alt="<?= htmlspecialchars($siteName) ?>" class="logo-image">
            </a>
            <nav class="nav">
                <a href="#reconnect" class="nav-link nav-link-primary" id="reconnectBtn">Reconnect</a>
                <a href="#about" class="nav-link" id="aboutBtn">About</a>
                <a href="admin/" class="nav-link">Admin</a>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main">
        <!-- Map Container -->
        <div id="map" class="map"></div>

        <!-- Instructions Banner -->
        <div class="instructions" id="instructions">
            <span class="instructions-icon">📌</span>
            <span>Click anywhere on the map to place your pin</span>
            <button class="instructions-close" id="instructionsClose">&times;</button>
        </div>

        <!-- Map Legend -->
        <div class="map-legend" id="mapLegend">
            <div class="legend-item">
                <span class="legend-pin legend-pin-yours"></span>
                <span>Your pins</span>
            </div>
            <div class="legend-item">
                <span class="legend-pin legend-pin-others"></span>
                <span>Other pins</span>
            </div>
        </div>

        <!-- Form Panel (hidden by default) -->
        <div class="form-panel" id="formPanel">
            <div class="form-header">
                <h2>Add Your Location</h2>
                <button class="form-close" id="formClose">&times;</button>
            </div>
            
            <form id="submissionForm" class="submission-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="latitude" id="latitude">
                <input type="hidden" name="longitude" id="longitude">

                <div class="form-group">
                    <label for="name">Name</label>
                    <input type="text" id="name" name="name" placeholder="Your name or location name">
                </div>

                <div class="form-group">
                    <label for="address">Address</label>
                    <input type="text" id="address" name="address" placeholder="Street address">
                </div>

                <div class="form-group">
                    <label for="email">Email <span class="required">*</span></label>
                    <input type="email" id="email" name="email" placeholder="your@email.com" required>
                    <small class="form-hint">Required for confirmation</small>
                </div>

                <div class="form-group">
                    <label for="picture">Picture</label>
                    <div class="file-upload" id="fileUpload">
                        <input type="file" id="picture" name="picture" accept="image/*">
                        <div class="file-upload-content">
                            <span class="file-upload-icon">📷</span>
                            <span class="file-upload-text">Click or drag to upload</span>
                        </div>
                        <div class="file-preview" id="filePreview"></div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="3" placeholder="Tell us about this location..."></textarea>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" id="cancelBtn">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn" disabled>Submit</button>
                </div>
            </form>
        </div>

        <!-- Info Window Template (used by JS) -->
        <template id="infoWindowTemplate">
            <div class="info-window">
                <div class="info-window-image" data-field="image"></div>
                <h3 class="info-window-title" data-field="name"></h3>
                <p class="info-window-address" data-field="address"></p>
                <p class="info-window-description" data-field="description"></p>
                <p class="info-window-email" data-field="email"></p>
            </div>
        </template>
    </main>

    <!-- About Modal -->
    <div class="modal" id="aboutModal">
        <div class="modal-backdrop" id="aboutBackdrop"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h2>About Maui Native Garden Tour</h2>
                <button class="modal-close" id="aboutClose">&times;</button>
            </div>
            <div class="modal-body">
                <p>Welcome to the Maui Native Garden Tour Map! This interactive map allows you to register a site with a Hawaiian native garden to participate in the tour.</p>
                <p><strong>How to participate:</strong></p>
                <ol>
                    <li>Click anywhere on the map to place a pin</li>
                    <li>Fill out the form with your location details</li>
                    <li>Submit and check your email for a confirmation link</li>
                    <li>Once confirmed, your pin will appear on the map for everyone to see!</li>
                </ol>
                <p>Click on any existing pin to learn more about that location.</p>
            </div>
            <?php if ($adminEmail): ?>
            <div class="modal-footer">
                <p>Questions or suggestions? <a href="mailto:<?= htmlspecialchars($adminEmail) ?>">Contact us</a></p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="modal" id="successModal">
        <div class="modal-backdrop" id="successBackdrop"></div>
        <div class="modal-content modal-success">
            <div class="modal-icon">✉️</div>
            <h2>Check Your Email!</h2>
            <p>We've sent a confirmation link to <strong id="successEmail"></strong></p>
            <p class="modal-hint">Your pin will appear on the map after you confirm.</p>
            <button class="btn btn-primary" id="successOk">Got it!</button>
        </div>
    </div>

    <div class="modal" id="reconnectModal">
        <div class="modal-backdrop" id="reconnectBackdrop"></div>
        <div class="modal-content modal-confirm">
            <div class="modal-icon">🔗</div>
            <h2>Reconnect Your Pin</h2>
            <p>If you already placed a pin, enter the same email address. Matching pins will become editable on this device.</p>
            <div class="reconnect-box reconnect-box-modal">
                <div class="reconnect-inline-form" id="reconnectForm">
                    <input type="email" id="reconnectEmail" placeholder="your@email.com">
                    <button type="button" class="btn btn-primary" id="reconnectSubmit">Reconnect</button>
                </div>
                <p class="reconnect-status" id="reconnectStatus" aria-live="polite"></p>
            </div>
        </div>
    </div>

    <!-- Add Another Pin Confirmation Modal -->
    <div class="modal" id="addPinModal">
        <div class="modal-backdrop" id="addPinBackdrop"></div>
        <div class="modal-content modal-confirm">
            <div class="modal-icon">📍</div>
            <h2>Add Another Pin?</h2>
            <p>You already have a pin on the map. Are you sure you want to add another location?</p>
            <div class="modal-actions">
                <button class="btn btn-secondary" id="addPinCancel">Cancel</button>
                <button class="btn btn-primary" id="addPinConfirm">Yes, Add Pin</button>
            </div>
        </div>
    </div>

    <!-- Delete Pin Confirmation Modal -->
    <div class="modal" id="deletePinModal">
        <div class="modal-backdrop" id="deletePinBackdrop"></div>
        <div class="modal-content modal-confirm modal-danger">
            <div class="modal-icon">🗑️</div>
            <h2>Delete This Pin?</h2>
            <p>Are you sure you want to delete this pin? This action cannot be undone.</p>
            <div class="modal-actions">
                <button class="btn btn-secondary" id="deletePinCancel">Cancel</button>
                <button class="btn btn-danger" id="deletePinConfirm">Yes, Delete</button>
            </div>
        </div>
    </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <span>&copy; <?= date('Y') ?> <?= htmlspecialchars($siteName) ?></span>
            <?php if ($adminEmail): ?>
            <span class="footer-divider">|</span>
            <a href="mailto:<?= htmlspecialchars($adminEmail) ?>">Contact Admin</a>
            <?php endif; ?>
        </div>
    </footer>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
        <p>Submitting...</p>
    </div>

    <script>
        // Pass config to JavaScript
        window.APP_CONFIG = {
            csrfToken: <?= json_encode($csrfToken) ?>,
            mauiCenter: { lat: 20.7984, lng: -156.3319 },
            defaultZoom: 10,
            mapId: <?= json_encode($googleMapsMapId) ?>,
            userEmail: <?= json_encode($_COOKIE['garden_tour_email'] ?? '') ?>
        };
        // Debug: log user email status
        console.log('Garden Tour: User email from cookie:', window.APP_CONFIG.userEmail || '(not set)');
    </script>
    <script>
        (g=>{var h,a,k,p="The Google Maps JavaScript API",c="google",l="importLibrary",q="__ib__",m=document,b=window;b=b[c]||(b[c]={});var d=b.maps||(b.maps={}),r=new Set,e=new URLSearchParams,u=()=>h||(h=new Promise(async(f,n)=>{await (a=m.createElement("script"));e.set("libraries",[...r]+"");for(k in g)e.set(k.replace(/[A-Z]/g,t=>"_"+t[0].toLowerCase()),g[k]);e.set("callback",c+".maps."+q);a.src=`https://maps.googleapis.com/maps/api/js?`+e;d[q]=f;a.onerror=()=>h=n(Error(p+" could not load."));a.nonce=m.querySelector("script[nonce]")?.nonce||"";m.head.append(a)}));d[l]?console.warn(p+" only loads once. Ignoring:",g):d[l]=(f,...n)=>r.add(f)&&u().then(()=>d[l](f,...n))})({
            key: <?= json_encode($googleMapsApiKey) ?>,
            v: "weekly"
        });
    </script>
    <script src="js/map.js"></script>
</body>
</html>
