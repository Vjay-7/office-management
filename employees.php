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
                $username = sanitize($_POST['username']);
                $email = sanitize($_POST['email']);
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $full_name = sanitize($_POST['full_name']);
                $department = sanitize($_POST['department']);
                $position = sanitize($_POST['position']);
                
                try {
                    // First create the user
                    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name, department, position) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$username, $email, $password, $full_name, $department, $position]);
                    $newUserId = $pdo->lastInsertId();
                    
                    // Handle image upload if provided
                    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === 0) {
                        $result = uploadProfileImage($_FILES['profile_image'], $newUserId);
                        if ($result['success']) {
                            // Update the user record with the image
                            $stmt = $pdo->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
                            $stmt->execute([$result['filename'], $newUserId]);
                        }
                    }
                    
                    $message = "Employee created successfully!";
                } catch (PDOException $e) {
                    $error = "Error creating employee: " . $e->getMessage();
                }
                break;
                
            case 'delete':
                $user_id = (int)$_POST['user_id'];
                try {
                    // Delete profile image if exists
                    $stmt = $pdo->prepare("SELECT profile_image FROM users WHERE id = ? AND role = 'employee'");
                    $stmt->execute([$user_id]);
                    $user = $stmt->fetch();
                    
                    if ($user && $user['profile_image']) {
                        $imagePath = 'uploads/profiles/' . $user['profile_image'];
                        if (file_exists($imagePath)) {
                            unlink($imagePath);
                        }
                    }
                    
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'employee'");
                    $stmt->execute([$user_id]);
                    $message = "Employee deleted successfully!";
                } catch (PDOException $e) {
                    $error = "Error deleting employee: " . $e->getMessage();
                }
                break;
        }
    }
}



// Fetch all employees
$stmt = $pdo->prepare("SELECT id, username, email, full_name, department, position, profile_image, created_at FROM users WHERE role = 'employee' ORDER BY created_at DESC");
$stmt->execute();
$employees = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Management - Office Portal</title>
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

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
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

        .form-group input, .form-group select {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #e1e5e9;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus, .form-group select:focus {
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

        .employees-section {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .employees-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .employee-card {
            border: 1px solid #e1e5e9;
            border-radius: 8px;
            padding: 1.5rem;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .employee-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .employee-card h3 {
            color: #333;
            margin-bottom: 0.5rem;
        }

        .employee-info {
            color: #666;
            margin-bottom: 1rem;
        }

        .employee-info p {
            margin-bottom: 0.3rem;
        }

        .employee-actions {
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

            .form-grid {
                grid-template-columns: 1fr;
            }
        }

        .employee-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .employee-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e1e5e9;
        }

        .employee-avatar-default {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 18px;
            border: 2px solid #e1e5e9;
        }

        .employee-card h3 {
            margin: 0;
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
            <h1>Employee Management</h1>
            <p>Manage your office employees and their information.</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="form-section">
            <h2>Add New Employee</h2>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="create">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="profile_image">Profile Image</label>
                        <input type="file" id="profile_image" name="profile_image" accept="image/*">
                    </div>
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <div class="form-group">
                        <label for="full_name">Full Name</label>
                        <input type="text" id="full_name" name="full_name" required>
                    </div>
                    <div class="form-group">
                        <label for="department">Department</label>
                        <input type="text" id="department" name="department">
                    </div>
                    <div class="form-group">
                        <label for="position">Position</label>
                        <input type="text" id="position" name="position">
                    </div>
                </div>
                <button type="submit" class="btn">Create Employee</button>
            </form>
        </div>

        <div class="employees-section">
            <h2>Current Employees (<?php echo count($employees); ?>)</h2>
            
            <?php if (empty($employees)): ?>
                <p>No employees found. Create your first employee above.</p>
            <?php else: ?>
                <div class="employees-grid">
                    <?php foreach ($employees as $employee): ?>
                        <div class="employee-card">
                            <div class="employee-header">
                                <?php if ($employee['profile_image'] && file_exists('uploads/profiles/' . $employee['profile_image'])): ?>
                                    <img src="uploads/profiles/<?php echo htmlspecialchars($employee['profile_image']); ?>" alt="Profile" class="employee-avatar">
                                <?php else: ?>
                                    <div class="employee-avatar-default"><?php echo strtoupper(substr($employee['full_name'], 0, 1)); ?></div>
                                <?php endif; ?>
                                <h3><?php echo htmlspecialchars($employee['full_name']); ?></h3>
                            </div>
                            <div class="employee-info">
                                <p><strong>Username:</strong> <?php echo htmlspecialchars($employee['username']); ?></p>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($employee['email']); ?></p>
                                <p><strong>Department:</strong> <?php echo htmlspecialchars($employee['department'] ?? 'N/A'); ?></p>
                                <p><strong>Position:</strong> <?php echo htmlspecialchars($employee['position'] ?? 'N/A'); ?></p>
                                <p><strong>Joined:</strong> <?php echo date('M d, Y', strtotime($employee['created_at'])); ?></p>
                            </div>
                            <div class="employee-actions">
                                <button class="btn btn-danger" onclick="confirmDelete(<?php echo $employee['id']; ?>, '<?php echo htmlspecialchars($employee['full_name']); ?>')">Delete</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
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
            <p>Are you sure you want to delete <span id="employeeName"></span>? This action cannot be undone.</p>
            <form method="POST" style="margin-top: 1rem;">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="user_id" id="deleteUserId">
                <button type="submit" class="btn btn-danger">Yes, Delete</button>
                <button type="button" class="btn" onclick="closeModal()">Cancel</button>
            </form>
        </div>
    </div>

    <script>
        function confirmDelete(userId, employeeName) {
            document.getElementById('deleteUserId').value = userId;
            document.getElementById('employeeName').textContent = employeeName;
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