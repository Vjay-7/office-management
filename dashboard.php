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
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'employee'");
    $totalUsers = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM announcements");
    $totalAnnouncements = $stmt->fetchColumn();
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
                <?php endif; ?>
                <div class="user-info">
                    <?php
                    // Get user profile image
                    $stmt = $pdo->prepare("SELECT profile_image FROM users WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $user = $stmt->fetch();
                    $profileImage = $user['profile_image'];
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
</body>
</html>

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
</script>