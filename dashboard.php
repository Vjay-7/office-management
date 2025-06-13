<?php
require_once 'config.php';
requireLogin();

try {
    $stmt = $pdo->prepare("SELECT a.*, u.full_name as author FROM announcements a JOIN users u ON a.created_by = u.id ORDER BY a.created_at DESC LIMIT 10");
    $stmt->execute();
    $announcements = $stmt->fetchAll();
} catch(PDOException $e) {
    $announcements = [];
    error_log("Database error: " . $e->getMessage());
}

// Get user statistics (for admin)
$totalUsers = 0;
$totalAnnouncements = 0;
if (isAdmin()) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'employee'");
        $totalUsers = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM announcements");
        $totalAnnouncements = $stmt->fetchColumn();
    } catch(PDOException $e) {
        error_log("Stats error: " . $e->getMessage());
    }
}

// Handle file upload for employees
$uploadMessage = '';
$uploadError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_file'])) {
    $description = isset($_POST['file_description']) ? sanitize($_POST['file_description']) : '';
    
    if (isset($_FILES['employee_file']) && $_FILES['employee_file']['error'] === 0) {
        $result = uploadEmployeeFile($_FILES['employee_file'], $_SESSION['user_id'], $description);
        
        if ($result['success']) {
            try {
                // Get user's department
                $stmt = $pdo->prepare("SELECT department FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch();
                
                // Save file info to database
                $stmt = $pdo->prepare("INSERT INTO employee_uploads (user_id, original_filename, stored_filename, file_size, file_type, department, description, upload_date) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([
                    $_SESSION['user_id'],
                    $result['original_name'],
                    $result['filename'],
                    $result['size'],
                    $result['type'],
                    $user['department'] ?? 'Unknown',
                    $description
                ]);
                
                $uploadMessage = "File uploaded successfully!";
            } catch(PDOException $e) {
                $uploadError = "Database error occurred while saving file information.";
                error_log("Upload database error: " . $e->getMessage());
                // Remove the uploaded file if database insert failed
                if (file_exists('uploads/employee_files/' . $result['filename'])) {
                    unlink('uploads/employee_files/' . $result['filename']);
                }
            }
        } else {
            $uploadError = $result['message'];
        }
    } else {
        $uploadError = "Please select a file to upload.";
        if (isset($_FILES['employee_file']['error']) && $_FILES['employee_file']['error'] !== 0) {
            switch ($_FILES['employee_file']['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $uploadError = "File is too large.";
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $uploadError = "File was only partially uploaded.";
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $uploadError = "No file was uploaded.";
                    break;
                default:
                    $uploadError = "An error occurred during file upload.";
            }
        }
    }
}

// Get user's uploaded files
$userFiles = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM employee_uploads WHERE user_id = ? ORDER BY upload_date DESC LIMIT 5");
    $stmt->execute([$_SESSION['user_id']]);
    $userFiles = $stmt->fetchAll();
} catch(PDOException $e) {
    error_log("User files error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Office Management</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            line-height: 1.6;
        }

        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .nav-brand {
            font-size: 1.5rem;
            font-weight: bold;
        }

        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            transition: background 0.3s ease;
        }

        .nav-links a:hover {
            background: rgba(255,255,255,0.2);
        }

        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .welcome-section {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .welcome-section h1 {
            color: #333;
            margin-bottom: 0.5rem;
        }

        .welcome-section p {
            color: #666;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 4px solid #667eea;
        }

        .stat-card h3 {
            font-size: 2rem;
            color: #667eea;
            margin-bottom: 0.5rem;
        }

        .stat-card p {
            color: #666;
            font-weight: 500;
        }

        .announcements-section {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .section-header h2 {
            color: #333;
        }

        .announcement-card {
            border: 1px solid #e1e5e9;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .announcement-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .announcement-card h3 {
            color: #333;
            margin-bottom: 0.5rem;
        }

        .announcement-card p {
            color: #666;
            margin-bottom: 1rem;
        }

        .announcement-meta {
            font-size: 0.9rem;
            color: #999;
            display: flex;
            justify-content: space-between;
        }

        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.6rem 1.2rem;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            font-weight: 500;
            transition: transform 0.2s ease;
            cursor: pointer;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .no-announcements {
            text-align: center;
            color: #666;
            padding: 2rem;
            font-style: italic;
        }

        @media (max-width: 768px) {
            .nav-container {
                flex-direction: column;
                gap: 1rem;
            }

            .container {
                padding: 0 1rem;
            }

            .section-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .profile-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(255,255,255,0.3);
        }

        .profile-icon-default {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: rgba(255,255,255,0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
        }

        .announcement-image {
            margin: 1rem 0;
        }

        .announcement-image img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            cursor: pointer;
            transition: transform 0.2s ease;
        }

        .announcement-image img:hover {
            transform: scale(1.02);
        }

        /* Image Modal */
        .image-modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.8);
        }

        .image-modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            max-width: 90%;
            max-height: 90%;
        }

        .image-modal img {
            width: 100%;
            height: auto;
            border-radius: 8px;
        }

        .image-close {
            position: absolute;
            top: 15px;
            right: 35px;
            color: white;
            font-size: 40px;
            font-weight: bold;
            cursor: pointer;
        }

        .upload-section {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .upload-form {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
        }

        .form-group input, .form-group textarea {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #e1e5e9;
            border-radius: 5px;
            font-size: 1rem;
        }

        .form-group textarea {
            min-height: 80px;
            resize: vertical;
        }

        .form-group small {
            color: #666;
            font-size: 0.9rem;
        }

        .user-files h3 {
            color: #333;
            margin-bottom: 1rem;
        }

        .file-item {
            border: 1px solid #e1e5e9;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 0.5rem;
        }

        .file-item strong {
            color: #333;
        }

        .file-meta {
            color: #666;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }

        .alert {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .notification-bell {
            position: relative;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 50%;
            transition: background 0.3s ease;
        }

        .notification-bell:hover {
            background: rgba(255,255,255,0.2);
        }

        .notification-badge {
            position: absolute;
            top: 0;
            right: 0;
            background: #ff4757;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .notification-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            width: 300px;
            max-height: 400px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }

        .notification-item {
            padding: 1rem;
            border-bottom: 1px solid #eee;
            cursor: pointer;
        }

        .notification-item:hover {
            background: #f8f9fa;
        }

        .notification-item.unread {
            background: #e3f2fd;
        }

        .notification-title {
            font-weight: bold;
            color: #333;
            margin-bottom: 0.25rem;
        }

        .notification-message {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }

        .notification-time {
            color: #999;
            font-size: 0.8rem;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-brand">Office Portal</div>
            <div class="nav-links">
                <a href="dashboard.php">Dashboard</a>
            <?php if (isAdmin()): ?>
                <a href="employees.php">Employees</a>
                <a href="announcements.php">Manage Announcements</a>
                <a href="employee_files.php">Employee Files</a>
            <?php endif; ?>
            <div class="notification-bell" onclick="toggleNotifications()">
                🔔
                <?php 
                $unreadCount = getUnreadNotificationCount($_SESSION['user_id']);
                if ($unreadCount > 0): 
                ?>
                    <span class="notification-badge"><?php echo $unreadCount; ?></span>
                <?php endif; ?>
                
                <div id="notificationDropdown" class="notification-dropdown">
                    <!-- Notifications will be loaded here via AJAX -->
                </div>
            </div>
                <div class="user-info">
                    <?php
                    // Get user profile image
                    try {
                        $stmt = $pdo->prepare("SELECT profile_image FROM users WHERE id = ?");
                        $stmt->execute([$_SESSION['user_id']]);
                        $user = $stmt->fetch();
                        $profileImage = $user ? $user['profile_image'] : null;
                    } catch(PDOException $e) {
                        $profileImage = null;
                        error_log("Profile image error: " . $e->getMessage());
                    }
                    ?>
                    <?php if ($profileImage && file_exists('uploads/profiles/' . $profileImage)): ?>
                        <img src="uploads/profiles/<?php echo htmlspecialchars($profileImage); ?>" alt="Profile" class="profile-icon">
                    <?php else: ?>
                        <div class="profile-icon-default"><?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?></div>
                    <?php endif; ?>
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                </div>
                <a href="profile.php">Profile</a>
                <a href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="welcome-section">
            <h1>Welcome to your Dashboard</h1>
            <p>Hello <?php echo htmlspecialchars($_SESSION['full_name']); ?>! Here's what's happening in your office today.</p>
        </div>

        <?php if (isAdmin()): ?>
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo $totalUsers; ?></h3>
                <p>Total Employees</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $totalAnnouncements; ?></h3>
                <p>Total Announcements</p>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!isAdmin()): ?>
            <div class="upload-section">
                <div class="section-header">
                    <h2>Upload Files</h2>
                </div>
                
                <?php if ($uploadMessage): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($uploadMessage); ?></div>
                <?php endif; ?>
                
                <?php if ($uploadError): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($uploadError); ?></div>
                <?php endif; ?>
                
                <form method="POST" enctype="multipart/form-data" class="upload-form">
                    <div class="form-group">
                        <label for="employee_file">Select File</label>
                        <input type="file" id="employee_file" name="employee_file" required>
                        <small>Max size: 10MB. Allowed: PDF, DOC, DOCX, TXT, JPG, PNG, GIF, ZIP, RAR</small>
                    </div>
                    <div class="form-group">
                        <label for="file_description">Description (Optional)</label>
                        <textarea id="file_description" name="file_description" placeholder="Brief description of the file..."></textarea>
                    </div>
                    <button type="submit" name="upload_file" class="btn">Upload File</button>
                </form>
                
                <div class="user-files">
                    <h3>Your Recent Files</h3>
                    <?php if (empty($userFiles)): ?>
                        <p>No files uploaded yet.</p>
                    <?php else: ?>
                        <?php foreach ($userFiles as $file): ?>
                            <div class="file-item">
                                <strong><?php echo htmlspecialchars($file['original_filename']); ?></strong>
                                <div class="file-meta">
                                    Uploaded: <?php echo date('M d, Y H:i', strtotime($file['upload_date'])); ?> | 
                                    Size: <?php echo number_format($file['file_size'] / 1024, 1); ?> KB
                                </div>
                                <?php if ($file['description']): ?>
                                    <p><?php echo htmlspecialchars($file['description']); ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="announcements-section">
            <div class="section-header">
                <h2>Recent Announcements</h2>
                <?php if (isAdmin()): ?>
                    <a href="announcements.php" class="btn">Manage Announcements</a>
                <?php endif; ?>
            </div>

            <?php if (empty($announcements)): ?>
                <div class="no-announcements">
                    No announcements available at the moment.
                </div>
            <?php else: ?>
                <?php foreach ($announcements as $announcement): ?>
                    <div class="announcement-card">
                        <h3><?php echo htmlspecialchars($announcement['title']); ?></h3>
                        <p><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p>
                        
                        <?php if ($announcement['image_attachment'] && file_exists('uploads/announcements/' . $announcement['image_attachment'])): ?>
                            <div class="announcement-image">
                                <img src="uploads/announcements/<?php echo htmlspecialchars($announcement['image_attachment']); ?>" 
                                    alt="Announcement attachment" 
                                    onclick="openImageModal('uploads/announcements/<?php echo htmlspecialchars($announcement['image_attachment']); ?>')">
                            </div>
                        <?php endif; ?>
                        
                        <div class="announcement-meta">
                            <span>By: <?php echo htmlspecialchars($announcement['author']); ?></span>
                            <span><?php echo date('M d, Y H:i', strtotime($announcement['created_at'])); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Image Modal -->
    <div id="imageModal" class="image-modal">
        <span class="image-close">&times;</span>
        <div class="image-modal-content">
            <img id="modalImage" src="" alt="Full size image">
        </div>
    </div>

    <script>
    function openImageModal(imageSrc) {
        document.getElementById('modalImage').src = imageSrc;
        document.getElementById('imageModal').style.display = 'block';
    }

    // Close image modal
    document.querySelector('.image-close').onclick = function() {
        document.getElementById('imageModal').style.display = 'none';
    }

    // Close modal when clicking outside
    document.getElementById('imageModal').onclick = function(event) {
        if (event.target == this) {
            this.style.display = 'none';
        }
    }

    // Close modal with Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            document.getElementById('imageModal').style.display = 'none';
        }
    });

    function toggleNotifications() {
    const dropdown = document.getElementById('notificationDropdown');
    if (dropdown.style.display === 'block') {
        dropdown.style.display = 'none';
    } else {
        loadNotifications();
        dropdown.style.display = 'block';
    }
}

    function loadNotifications() {
        fetch('get_notifications.php')
            .then(response => response.json())
            .then(data => {
                const dropdown = document.getElementById('notificationDropdown');
                if (data.length === 0) {
                    dropdown.innerHTML = '<div style="padding: 1rem; text-align: center; color: #666;">No notifications</div>';
                } else {
                    dropdown.innerHTML = data.map(notification => `
                        <div class="notification-item ${notification.is_read == 0 ? 'unread' : ''}" onclick="markAsRead(${notification.id})">
                            <div class="notification-title">${notification.title}</div>
                            <div class="notification-message">${notification.message}</div>
                            <div class="notification-time">${notification.created_at}</div>
                        </div>
                    `).join('');
                }
            });
    }

    function markAsRead(notificationId) {
        fetch('mark_notification_read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({id: notificationId})
        }).then(() => {
            location.reload(); // Refresh to update badge count
        });
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
        const bell = document.querySelector('.notification-bell');
        const dropdown = document.getElementById('notificationDropdown');
        if (!bell.contains(event.target)) {
            dropdown.style.display = 'none';
        }
    });
    </script>
</body>
</html>