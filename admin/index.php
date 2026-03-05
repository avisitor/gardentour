<?php
/**
 * Admin Dashboard
 * 
 * Password-protected admin interface with:
 * - Sortable table of all submissions
 * - Pagination
 * - Delete functionality
 * - CSV export
 * - Record detail view
 */

require_once __DIR__ . '/../envloader.php';
require_once __DIR__ . '/../includes/db.php';

// Start session for authentication
session_start();

$siteName = 'Maui Garden Tour Map';

// ==================== Authentication ====================

$adminUser = env('ADMIN_USER', 'admin');
$adminPassHash = env('ADMIN_PASS_HASH', '');

$loginError = '';
$isLoggedIn = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($username === $adminUser && password_verify($password, $adminPassHash)) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_last_activity'] = time();
        $isLoggedIn = true;
    } else {
        $loginError = 'Invalid username or password';
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// Check session timeout (30 minutes)
if ($isLoggedIn && isset($_SESSION['admin_last_activity'])) {
    if (time() - $_SESSION['admin_last_activity'] > 1800) {
        session_destroy();
        header('Location: index.php?timeout=1');
        exit;
    }
    $_SESSION['admin_last_activity'] = time();
}

// ==================== Handle Actions (if logged in) ====================

$message = '';
$messageType = '';

if ($isLoggedIn) {
    // Handle delete
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            // Get image path before deleting
            $submission = dbQueryOne("SELECT image_path FROM submissions WHERE id = ?", [$id]);
            
            // Delete from database
            $deleted = dbExecute("DELETE FROM submissions WHERE id = ?", [$id]);
            
            if ($deleted > 0) {
                // Delete associated image if exists
                if ($submission && $submission['image_path']) {
                    $imagePath = __DIR__ . '/../' . $submission['image_path'];
                    if (file_exists($imagePath)) {
                        unlink($imagePath);
                    }
                }
                $message = 'Submission deleted successfully';
                $messageType = 'success';
            } else {
                $message = 'Failed to delete submission';
                $messageType = 'error';
            }
        }
    }
    
    // Handle CSV export
    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
        $submissions = dbQuery(
            "SELECT id, name, address, email, description, latitude, longitude, created_at 
             FROM submissions ORDER BY created_at DESC"
        );
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="garden-tour-submissions-' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Header row
        fputcsv($output, ['ID', 'Name', 'Address', 'Email', 'Description', 'Latitude', 'Longitude', 'Created At']);
        
        // Data rows
        foreach ($submissions as $row) {
            fputcsv($output, [
                $row['id'],
                $row['name'],
                $row['address'],
                $row['email'],
                $row['description'],
                $row['latitude'],
                $row['longitude'],
                $row['created_at']
            ]);
        }
        
        fclose($output);
        exit;
    }
}

// ==================== Fetch Data (if logged in) ====================

$submissions = [];
$totalCount = 0;
$currentPage = 1;
$perPage = 25;
$totalPages = 1;
$sortColumn = 'created_at';
$sortDirection = 'DESC';

if ($isLoggedIn) {
    // Get sort parameters
    $allowedColumns = ['id', 'name', 'email', 'address', 'created_at'];
    if (isset($_GET['sort']) && in_array($_GET['sort'], $allowedColumns)) {
        $sortColumn = $_GET['sort'];
    }
    if (isset($_GET['dir']) && in_array(strtoupper($_GET['dir']), ['ASC', 'DESC'])) {
        $sortDirection = strtoupper($_GET['dir']);
    }
    
    // Get pagination
    $currentPage = max(1, (int) ($_GET['page'] ?? 1));
    
    // Get total count
    $countResult = dbQueryOne("SELECT COUNT(*) as total FROM submissions");
    $totalCount = $countResult['total'] ?? 0;
    $totalPages = max(1, ceil($totalCount / $perPage));
    $currentPage = min($currentPage, $totalPages);
    $offset = ($currentPage - 1) * $perPage;
    
    // Fetch submissions
    $submissions = dbQuery(
        "SELECT * FROM submissions ORDER BY {$sortColumn} {$sortDirection} LIMIT {$perPage} OFFSET {$offset}"
    );
}

/**
 * Build sort URL
 */
function sortUrl($column, $currentSort, $currentDir) {
    $dir = ($column === $currentSort && $currentDir === 'ASC') ? 'DESC' : 'ASC';
    return "?sort={$column}&dir={$dir}";
}

/**
 * Get sort indicator
 */
function sortIndicator($column, $currentSort, $currentDir) {
    if ($column !== $currentSort) return '';
    return $currentDir === 'ASC' ? ' ▲' : ' ▼';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - <?= htmlspecialchars($siteName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Favicons -->
    <link rel="icon" type="image/svg+xml" href="../images/favicon.svg">
    <link rel="icon" type="image/x-icon" href="../images/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="../images/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../images/favicon-16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="../images/apple-touch-icon.png">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f7f5;
            min-height: 100vh;
            color: #1a1a1a;
            line-height: 1.5;
        }
        
        /* Header */
        .header {
            background: white;
            border-bottom: 1px solid #e0e0e0;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            font-size: 1.25rem;
            color: #2e7d32;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .header h1 img {
            height: 36px;
            width: auto;
        }
        
        .header-actions {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .header-actions a {
            color: #666;
            text-decoration: none;
            font-size: 0.875rem;
        }
        
        .header-actions a:hover { color: #2e7d32; }
        
        /* Main Content */
        .main {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* Login Form */
        .login-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: calc(100vh - 100px);
        }
        
        .login-box {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            padding: 40px;
            width: 100%;
            max-width: 400px;
        }
        
        .login-box h2 {
            text-align: center;
            margin-bottom: 30px;
            color: #2e7d32;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
            color: #666;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            font-family: inherit;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #2e7d32;
            box-shadow: 0 0 0 3px rgba(46, 125, 50, 0.15);
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 0.9375rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
            font-family: inherit;
        }
        
        .btn-primary {
            background: #2e7d32;
            color: white;
            width: 100%;
        }
        
        .btn-primary:hover { background: #1b5e20; }
        
        .btn-secondary {
            background: #f5f5f5;
            color: #666;
            border: 1px solid #e0e0e0;
        }
        
        .btn-secondary:hover { background: #e0e0e0; }
        
        .btn-danger {
            background: #d32f2f;
            color: white;
        }
        
        .btn-danger:hover { background: #b71c1c; }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8125rem;
        }
        
        .error-message {
            background: #ffebee;
            color: #d32f2f;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .success-message {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        /* Dashboard */
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .dashboard-header h2 {
            font-size: 1.125rem;
            color: #666;
        }
        
        .dashboard-actions {
            display: flex;
            gap: 10px;
        }
        
        /* Table */
        .table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        .table-wrapper {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
        }
        
        th {
            background: #f9f9f9;
            font-weight: 600;
            color: #666;
            white-space: nowrap;
        }
        
        th a {
            color: #666;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        th a:hover { color: #2e7d32; }
        
        tr:hover { background: #fafafa; }
        
        td {
            font-size: 0.875rem;
        }
        
        .truncate {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .thumbnail {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 6px;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .thumbnail:hover {
            transform: scale(1.1);
        }
        
        .no-image {
            width: 50px;
            height: 50px;
            background: #f0f0f0;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ccc;
            font-size: 1.25rem;
        }
        
        .actions {
            display: flex;
            gap: 8px;
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            padding: 20px;
            border-top: 1px solid #f0f0f0;
        }
        
        .pagination a, .pagination span {
            padding: 8px 12px;
            border-radius: 6px;
            text-decoration: none;
            color: #666;
        }
        
        .pagination a:hover {
            background: #f0f0f0;
        }
        
        .pagination .current {
            background: #2e7d32;
            color: white;
        }
        
        .pagination .disabled {
            color: #ccc;
        }
        
        /* Detail Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 100;
            justify-content: center;
            align-items: center;
        }
        
        .modal-overlay.visible { display: flex; }
        
        .modal {
            background: white;
            border-radius: 12px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 { font-size: 1.125rem; }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #999;
        }
        
        .modal-close:hover { color: #333; }
        
        .modal-body { padding: 20px; }
        
        .detail-row {
            margin-bottom: 15px;
        }
        
        .detail-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: #999;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        
        .detail-value {
            color: #333;
        }
        
        .detail-image {
            max-width: 100%;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        .image-management {
            background: #f9f9f9;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .image-management-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .image-management-header h4 {
            font-size: 0.875rem;
            font-weight: 600;
            color: #666;
            margin: 0;
        }
        
        .image-preview {
            text-align: center;
            margin-bottom: 15px;
        }
        
        .image-preview img {
            max-width: 100%;
            max-height: 200px;
            border-radius: 8px;
        }
        
        .image-placeholder {
            background: #e8e8e8;
            border-radius: 8px;
            padding: 40px;
            color: #999;
            text-align: center;
        }
        
        .image-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
        }
        
        .file-input-wrapper input[type=file] {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            cursor: pointer;
            width: 100%;
            height: 100%;
        }
        
        .detail-map {
            height: 200px;
            border-radius: 8px;
            margin-bottom: 15px;
            background: #e8e8e8;
        }
        
        .modal-footer {
            padding: 15px 20px;
            border-top: 1px solid #e0e0e0;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        
        .empty-state-icon {
            font-size: 3rem;
            margin-bottom: 15px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 10px;
            }
            
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            th, td {
                padding: 10px;
            }
        }
    </style>
    <!-- Google Maps JavaScript API -->
    <script>
        (g=>{var h,a,k,p="The Google Maps JavaScript API",c="google",l="importLibrary",q="__ib__",m=document,b=window;b=b[c]||(b[c]={});var d=b.maps||(b.maps={}),r=new Set,e=new URLSearchParams,u=()=>h||(h=new Promise(async(f,n)=>{await (a=m.createElement("script"));e.set("libraries",[...r]+"");for(k in g)e.set(k.replace(/[A-Z]/g,t=>"_"+t[0].toLowerCase()),g[k]);e.set("callback",c+".maps."+q);a.src=`https://maps.googleapis.com/maps/api/js?`+e;d[q]=f;a.onerror=()=>h=n(Error(p+" could not load."));a.nonce=m.querySelector("script[nonce]")?.nonce||"";m.head.append(a)}));d[l]?console.warn(p+" only loads once. Ignoring:",g):d[l]=(f,...n)=>r.add(f)&&u().then(()=>d[l](f,...n))})({
            key: <?= json_encode(env('GOOGLE_MAPS_API_KEY', '')) ?>,
            v: "weekly"
        });
    </script>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <h1><img src="../images/favicon.svg" alt=""> <?= htmlspecialchars($siteName) ?> - Admin</h1>
        <div class="header-actions">
            <a href="../">← Back to Map</a>
            <?php if ($isLoggedIn): ?>
            <a href="?logout=1">Logout</a>
            <?php endif; ?>
        </div>
    </header>

    <main class="main">
        <?php if (!$isLoggedIn): ?>
        <!-- Login Form -->
        <div class="login-container">
            <div class="login-box">
                <h2>Admin Login</h2>
                
                <?php if ($loginError): ?>
                <div class="error-message"><?= htmlspecialchars($loginError) ?></div>
                <?php endif; ?>
                
                <?php if (isset($_GET['timeout'])): ?>
                <div class="error-message">Your session has expired. Please log in again.</div>
                <?php endif; ?>
                
                <form method="POST">
                    <input type="hidden" name="action" value="login">
                    
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" required autofocus>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Log In</button>
                </form>
            </div>
        </div>
        
        <?php else: ?>
        <!-- Dashboard -->
        
        <?php if ($message): ?>
        <div class="<?= $messageType === 'success' ? 'success-message' : 'error-message' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>
        
        <div class="dashboard-header">
            <h2>📊 Submissions (<?= $totalCount ?> total)</h2>
            <div class="dashboard-actions">
                <a href="?export=csv" class="btn btn-secondary btn-sm">📥 Export CSV</a>
                <a href="?" class="btn btn-secondary btn-sm">🔄 Refresh</a>
            </div>
        </div>
        
        <div class="table-container">
            <?php if (empty($submissions)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">📍</div>
                <p>No submissions yet</p>
            </div>
            <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th><a href="<?= sortUrl('id', $sortColumn, $sortDirection) ?>">ID<?= sortIndicator('id', $sortColumn, $sortDirection) ?></a></th>
                            <th>Image</th>
                            <th><a href="<?= sortUrl('name', $sortColumn, $sortDirection) ?>">Name<?= sortIndicator('name', $sortColumn, $sortDirection) ?></a></th>
                            <th><a href="<?= sortUrl('email', $sortColumn, $sortDirection) ?>">Email<?= sortIndicator('email', $sortColumn, $sortDirection) ?></a></th>
                            <th><a href="<?= sortUrl('address', $sortColumn, $sortDirection) ?>">Address<?= sortIndicator('address', $sortColumn, $sortDirection) ?></a></th>
                            <th><a href="<?= sortUrl('created_at', $sortColumn, $sortDirection) ?>">Created<?= sortIndicator('created_at', $sortColumn, $sortDirection) ?></a></th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($submissions as $row): ?>
                        <tr data-submission='<?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>'>
                            <td><?= $row['id'] ?></td>
                            <td>
                                <?php if ($row['image_path']): ?>
                                <img src="../<?= htmlspecialchars($row['image_path']) ?>" 
                                     class="thumbnail" 
                                     alt="Submission image"
                                     onclick="showDetail(JSON.parse(this.closest('tr').dataset.submission))">
                                <?php else: ?>
                                <div class="no-image">-</div>
                                <?php endif; ?>
                            </td>
                            <td class="truncate"><?= htmlspecialchars($row['name'] ?: '(none)') ?></td>
                            <td class="truncate"><?= htmlspecialchars($row['email']) ?></td>
                            <td class="truncate"><?= htmlspecialchars($row['address'] ?: '(none)') ?></td>
                            <td><?= date('Y-m-d', strtotime($row['created_at'])) ?></td>
                            <td class="actions">
                                <button class="btn btn-secondary btn-sm" onclick="showDetail(JSON.parse(this.closest('tr').dataset.submission))">View</button>
                                <button class="btn btn-danger btn-sm" onclick="confirmDelete(<?= $row['id'] ?>)">Delete</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($currentPage > 1): ?>
                <a href="?page=1&sort=<?= $sortColumn ?>&dir=<?= $sortDirection ?>">« First</a>
                <a href="?page=<?= $currentPage - 1 ?>&sort=<?= $sortColumn ?>&dir=<?= $sortDirection ?>">‹ Prev</a>
                <?php else: ?>
                <span class="disabled">« First</span>
                <span class="disabled">‹ Prev</span>
                <?php endif; ?>
                
                <span>Page <?= $currentPage ?> of <?= $totalPages ?></span>
                
                <?php if ($currentPage < $totalPages): ?>
                <a href="?page=<?= $currentPage + 1 ?>&sort=<?= $sortColumn ?>&dir=<?= $sortDirection ?>">Next ›</a>
                <a href="?page=<?= $totalPages ?>&sort=<?= $sortColumn ?>&dir=<?= $sortDirection ?>">Last »</a>
                <?php else: ?>
                <span class="disabled">Next ›</span>
                <span class="disabled">Last »</span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <!-- Detail Modal -->
        <div class="modal-overlay" id="detailModal">
            <div class="modal">
                <div class="modal-header">
                    <h3>Submission Details</h3>
                    <button class="modal-close" onclick="closeModal()">&times;</button>
                </div>
                <div class="modal-body" id="modalBody">
                    <!-- Filled by JavaScript -->
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" onclick="closeModal()">Close</button>
                </div>
            </div>
        </div>
        
        <!-- Delete Form (hidden) -->
        <form id="deleteForm" method="POST" style="display: none;">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="deleteId">
        </form>
        
        <?php endif; ?>
    </main>

    <script>
        let currentSubmissionId = null;
        
        function showDetail(data) {
            currentSubmissionId = data.id;
            let html = '';
            
            // Image management section
            html += `<div class="image-management">
                <div class="image-management-header">
                    <h4>Image</h4>
                </div>
                <div class="image-preview" id="imagePreview">`;
            
            if (data.image_path) {
                html += `<img src="../${data.image_path}" alt="Submission image" id="currentImage">`;
            } else {
                html += `<div class="image-placeholder">No image</div>`;
            }
            
            html += `</div>
                <div class="image-actions">
                    <div class="file-input-wrapper">
                        <button class="btn btn-secondary btn-sm">${data.image_path ? 'Replace Image' : 'Add Image'}</button>
                        <input type="file" accept="image/*" onchange="replaceImage(this, ${data.id})">
                    </div>`;
            
            if (data.image_path) {
                html += `<button class="btn btn-danger btn-sm" onclick="removeImage(${data.id})">Remove Image</button>`;
            }
            
            html += `</div>
                <div id="imageStatus" style="margin-top: 10px; font-size: 0.875rem;"></div>
            </div>`;
            
            // Interactive map preview container
            html += `<div id="detailMap" class="detail-map"></div>`;
            
            html += `
                <div class="detail-row">
                    <div class="detail-label">ID</div>
                    <div class="detail-value">${data.id}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Name</div>
                    <div class="detail-value">${escapeHtml(data.name) || '(not provided)'}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Email</div>
                    <div class="detail-value">${escapeHtml(data.email)}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Address</div>
                    <div class="detail-value">${escapeHtml(data.address) || '(not provided)'}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Description</div>
                    <div class="detail-value">${escapeHtml(data.description) || '(not provided)'}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Coordinates</div>
                    <div class="detail-value">${data.latitude}, ${data.longitude}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Created At</div>
                    <div class="detail-value">${data.created_at}</div>
                </div>
            `;
            
            document.getElementById('modalBody').innerHTML = html;
            document.getElementById('detailModal').classList.add('visible');
            
            // Initialize the interactive map after modal is visible
            initDetailMap(parseFloat(data.latitude), parseFloat(data.longitude));
        }
        
        // Initialize the detail map with a marker
        async function initDetailMap(lat, lng) {
            try {
                const { Map } = await google.maps.importLibrary("maps");
                const { AdvancedMarkerElement } = await google.maps.importLibrary("marker");
                
                const position = { lat, lng };
                
                const map = new Map(document.getElementById('detailMap'), {
                    center: position,
                    zoom: 15,
                    mapId: '<?= htmlspecialchars(env('GOOGLE_MAPS_MAP_ID', 'DEMO_MAP_ID')) ?>',
                    mapTypeId: google.maps.MapTypeId.ROADMAP,
                    mapTypeControl: false,
                    zoomControl: true,
                    streetViewControl: false,
                    fullscreenControl: false,
                    gestureHandling: 'cooperative'
                });
                
                // Add marker at the location
                new AdvancedMarkerElement({
                    map: map,
                    position: position,
                    title: 'Submission Location'
                });
            } catch (error) {
                console.error('Failed to initialize detail map:', error);
                document.getElementById('detailMap').innerHTML = '<div style="padding: 20px; text-align: center; color: #999;">Map unavailable</div>';
            }
        }
        
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function closeModal() {
            document.getElementById('detailModal').classList.remove('visible');
            currentSubmissionId = null;
        }
        
        function showImageStatus(message, isError = false) {
            const status = document.getElementById('imageStatus');
            status.innerHTML = `<span style="color: ${isError ? '#d32f2f' : '#2e7d32'}">${escapeHtml(message)}</span>`;
        }
        
        async function removeImage(id) {
            if (!confirm('Are you sure you want to remove this image?')) {
                return;
            }
            
            showImageStatus('Removing image...');
            
            try {
                const formData = new FormData();
                formData.append('action', 'remove');
                formData.append('id', id);
                
                const response = await fetch('../api/admin-image.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showImageStatus('Image removed successfully');
                    // Update the preview
                    document.getElementById('imagePreview').innerHTML = '<div class="image-placeholder">No image</div>';
                    // Update the image actions
                    const actionsDiv = document.querySelector('.image-actions');
                    actionsDiv.innerHTML = `
                        <div class="file-input-wrapper">
                            <button class="btn btn-secondary btn-sm">Add Image</button>
                            <input type="file" accept="image/*" onchange="replaceImage(this, ${id})">
                        </div>
                    `;
                    // Update thumbnail in table
                    updateTableThumbnail(id, null);
                } else {
                    showImageStatus(result.message || 'Failed to remove image', true);
                }
            } catch (error) {
                showImageStatus('An error occurred. Please try again.', true);
                console.error('Remove image error:', error);
            }
        }
        
        async function replaceImage(input, id) {
            const file = input.files[0];
            if (!file) return;
            
            // Validate file type
            if (!file.type.startsWith('image/')) {
                showImageStatus('Please select an image file.', true);
                input.value = '';
                return;
            }
            
            showImageStatus('Uploading image...');
            
            try {
                const formData = new FormData();
                formData.append('action', 'replace');
                formData.append('id', id);
                formData.append('image', file);
                
                const response = await fetch('../api/admin-image.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showImageStatus('Image updated successfully');
                    // Update the preview
                    const newImagePath = result.image_path;
                    document.getElementById('imagePreview').innerHTML = `<img src="../${newImagePath}" alt="Submission image" id="currentImage">`;
                    // Update the image actions to show Remove button
                    const actionsDiv = document.querySelector('.image-actions');
                    actionsDiv.innerHTML = `
                        <div class="file-input-wrapper">
                            <button class="btn btn-secondary btn-sm">Replace Image</button>
                            <input type="file" accept="image/*" onchange="replaceImage(this, ${id})">
                        </div>
                        <button class="btn btn-danger btn-sm" onclick="removeImage(${id})">Remove Image</button>
                    `;
                    // Update thumbnail in table
                    updateTableThumbnail(id, newImagePath);
                } else {
                    showImageStatus(result.message || 'Failed to upload image', true);
                }
            } catch (error) {
                showImageStatus('An error occurred. Please try again.', true);
                console.error('Replace image error:', error);
            }
            
            input.value = '';
        }
        
        function updateTableThumbnail(id, imagePath) {
            // Find the row with this ID and update its thumbnail
            const rows = document.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const idCell = row.querySelector('td:first-child');
                if (idCell && idCell.textContent == id) {
                    // Update the data-submission attribute with the new image path
                    try {
                        const submission = JSON.parse(row.dataset.submission);
                        submission.image_path = imagePath;
                        row.dataset.submission = JSON.stringify(submission);
                    } catch (e) {
                        console.error('Failed to update submission data:', e);
                    }
                    
                    const imageCell = row.querySelector('td:nth-child(2)');
                    if (imageCell) {
                        if (imagePath) {
                            imageCell.innerHTML = `<img src="../${imagePath}" class="thumbnail" alt="Submission image" onclick="showDetail(JSON.parse(this.closest('tr').dataset.submission))">`;
                        } else {
                            imageCell.innerHTML = '<div class="no-image">-</div>';
                        }
                    }
                }
            });
        }
        
        function confirmDelete(id) {
            if (confirm('Are you sure you want to delete this submission? This cannot be undone.')) {
                document.getElementById('deleteId').value = id;
                document.getElementById('deleteForm').submit();
            }
        }
        
        // Close modal on backdrop click
        document.getElementById('detailModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
        
        // Close modal on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
    </script>
</body>
</html>
