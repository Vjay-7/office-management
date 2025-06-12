<?php
require_once 'config.php';
requireAdmin();

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $title = sanitize($_POST['title']);
                $content = sanitize($_POST['content']);
                
                try {
                    // First create the announcement
                    $stmt = $pdo->prepare("INSERT INTO announcements (title, content, created_by) VALUES (?, ?, ?)");
                    $stmt->execute([$title, $content, $_SESSION['user_id']]);
                    $newAnnouncementId = $pdo->lastInsertId();
                    
                    // Handle image upload if provided
                    if (isset($_FILES['announcement_image']) && $_FILES['announcement_image']['error'] === 0) {
                        $result = uploadAnnouncementImage($_FILES['announcement_image'], $newAnnouncementId);
                        if ($result['success']) {
                            // Update the announcement record with the image
                            $stmt = $pdo->prepare("UPDATE announcements SET image_attachment = ? WHERE id = ?");
                            $stmt->execute([$result['filename'], $newAnnouncementId]);
                        }
                    }
                    
                    $message = "Announcement created successfully!";
                } catch (PDOException $e) {
                    $error = "Error creating announcement: " . $e->getMessage();
                }
                break;                
            case 'delete':
                $announcement_id = (int)$_POST['announcement_id'];
                try {
                    // Delete image file if exists
                    $stmt = $pdo->prepare("SELECT image_attachment FROM announcements WHERE id = ?");
                    $stmt->execute([$announcement_id]);
                    $announcement = $stmt->fetch();
                    
                    if ($announcement && $announcement['image_attachment']) {
                        $imagePath = 'uploads/announcements/' . $announcement['image_attachment'];
                        if (file_exists($imagePath)) {
                            unlink($imagePath);
                        }
                    }
                    
                    $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = ?");
                    $stmt->execute([$announcement_id]);
                    $message = "Announcement deleted successfully!";
                } catch (PDOException $e) {
                    $error = "Error deleting announcement: " . $e->getMessage();
                }
                break;
        }
    }
}

// Fetch all announcements
$stmt = $pdo->prepare("SELECT a.*, u.full_name as author FROM announcements a JOIN users u ON a.created_by = u.id ORDER BY a.created_at DESC LIMIT 10");$stmt->execute();
$announcements = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements Management - Office Portal</title>
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

        .page-header {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .page-header h1 {
            color: #333;
            margin-bottom: 0.5rem;
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

        .form-section {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .form-section h2 {
            color: #333;
            margin-bottom: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
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
            font-family: inherit;
            transition: border-color 0.3s ease;
        }

        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }

        .form-group input:focus, .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: transform 0.2s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        }

        .announcements-section {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .announcement-card {
            border: 1px solid #e1e5e9;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .announcement-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .announcement-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .announcement-card h3 {
            color: #333;
            margin-bottom: 0.5rem;
        }

        .announcement-content {
            color: #666;
            margin-bottom: 1rem;
            line-height: 1.6;
        }

        .announcement-meta {
            font-size: 0.9rem;
            color: #999;
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .announcement-actions {
            display: flex;
            gap: 0.5rem;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 2rem;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: #000;
        }

        @media (max-width: 768px) {
            .nav-container {
                flex-direction: column;
                gap: 1rem;
            }

            .container {
                padding: 0 1rem;
            }

            .announcement-header {
                flex-direction: column;
                gap: 1rem;
            }

            .announcement-meta {
                flex-direction: column;
                gap: 0.5rem;
            }
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

        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.9rem;
        }

        .announcement-actions {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e1e5e9;
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
                <a href="employees.php">Employees</a>
                <a href="announcements.php">Announcements</a>
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                <a href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1>Announcements Management</h1>
            <p>Create and manage office announcements for all employees.</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="form-section">
            <h2>Create New Announcement</h2>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="announcement_image">Attachment Image (Optional)</label>
                    <input type="file" id="announcement_image" name="announcement_image" accept="image/*">
                    <small>Max size: 5MB. Allowed formats: JPG, PNG, GIF</small>
                </div>
                <input type="hidden" name="action" value="create">
                <div class="form-group">
                    <label for="title">Announcement Title</label>
                    <input type="text" id="title" name="title" required maxlength="200">
                </div>
                <div class="form-group">
                    <label for="content">Announcement Content</label>
                    <textarea id="content" name="content" required placeholder="Enter your announcement details here..."></textarea>
                </div>
                <button type="submit" class="btn">Create Announcement</button>
            </form>
        </div>

        <div class="announcements-section">
            <h2>All Announcements (<?php echo count($announcements); ?>)</h2>
            
            <?php if (empty($announcements)): ?>
                <p>No announcements found. Create your first announcement above.</p>
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
                        
                        <?php if (isAdmin()): ?>
                            <div class="announcement-actions">
                                <button class="btn btn-danger btn-sm" onclick="confirmDelete(<?php echo $announcement['id']; ?>, '<?php echo htmlspecialchars($announcement['title']); ?>')">Delete</button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Confirm Delete</h3>
                <span class="close">&times;</span>
            </div>
            <p>Are you sure you want to delete the announcement "<span id="announcementTitle"></span>"? This action cannot be undone.</p>
            <form method="POST" style="margin-top: 1rem;">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="announcement_id" id="deleteAnnouncementId">
                <button type="submit" class="btn btn-danger">Yes, Delete</button>
                <button type="button" class="btn" onclick="closeModal()">Cancel</button>
            </form>
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
        function confirmDelete(announcementId, announcementTitle) {
            document.getElementById('deleteAnnouncementId').value = announcementId;
            document.getElementById('announcementTitle').textContent = announcementTitle;
            document.getElementById('deleteModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        // Close modal when clicking outside of it
        window.onclick = function(event) {
            const modal = document.getElementById('deleteModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }

        // Close modal with close button
        document.querySelector('.close').onclick = function() {
            closeModal();
        }
    </script>
</body>
</html>