<?php
require_once 'config.php';
requireLogin();

$message = '';
$error = '';

// Handle profile image upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_image'])) {
    $result = uploadProfileImage($_FILES['profile_image'], $_SESSION['user_id']);
    
    if ($result['success']) {
        // Update database
        $stmt = $pdo->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
        $stmt->execute([$result['filename'], $_SESSION['user_id']]);
        $message = 'Profile image updated successfully!';
    } else {
        $error = $result['message'];
    }
}

// Get current user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$currentUser = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Office Management</title>
    <!-- Add the same CSS from dashboard.php here -->
</head>
<body>
    <!-- Add the same navbar from dashboard.php here -->
    
    <div class="container">
        <div class="profile-section">
            <h2>Profile Settings</h2>
            
            <?php if ($message): ?>
                <div class="success"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="current-image">
                <?php if ($currentUser['profile_image'] && file_exists('uploads/profiles/' . $currentUser['profile_image'])): ?>
                    <img src="uploads/profiles/<?php echo htmlspecialchars($currentUser['profile_image']); ?>" alt="Current Profile" style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover;">
                <?php else: ?>
                    <div style="width: 100px; height: 100px; border-radius: 50%; background: #ddd; display: flex; align-items: center; justify-content: center; font-size: 36px;">
                        <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <form method="POST" enctype="multipart/form-data" style="margin-top: 20px;">
                <div class="form-group">
                    <label for="profile_image">Upload Profile Image</label>
                    <input type="file" id="profile_image" name="profile_image" accept="image/*" required>
                    <small>Max size: 2MB. Allowed formats: JPG, PNG, GIF</small>
                </div>
                <button type="submit" class="btn">Update Profile Image</button>
            </form>
        </div>
    </div>
</body>
</html>