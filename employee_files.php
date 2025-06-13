<?php
require_once 'config.php';
requireAdmin();

// Get all departments
$stmt = $pdo->query("SELECT DISTINCT department FROM users WHERE department IS NOT NULL AND department != '' ORDER BY department");
$departments = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get selected department filter
$selectedDept = isset($_GET['dept']) ? $_GET['dept'] : '';

// Build query based on filter
if ($selectedDept) {
    $stmt = $pdo->prepare("
        SELECT ef.*, u.full_name, u.department 
        FROM employee_uploads ef 
        JOIN users u ON ef.user_id = u.id 
        WHERE u.department = ? 
        ORDER BY ef.upload_date DESC
    ");
    $stmt->execute([$selectedDept]);
} else {
    $stmt = $pdo->query("
        SELECT ef.*, u.full_name, u.department 
        FROM employee_uploads ef 
        JOIN users u ON ef.user_id = u.id 
        ORDER BY ef.upload_date DESC
    ");
}
$files = $stmt->fetchAll();

// Group files by department
$filesByDept = [];
foreach ($files as $file) {
    $dept = $file['department'] ?: 'No Department';
    if (!isset($filesByDept[$dept])) {
        $filesByDept[$dept] = [];
    }
    $filesByDept[$dept][] = $file;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Files - Office Portal</title>
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

        .filters {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .filter-group {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .filter-group select {
            padding: 0.5rem;
            border: 2px solid #e1e5e9;
            border-radius: 5px;
        }

        .department-section {
            background: white;
            margin-bottom: 2rem;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .dept-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem;
            font-size: 1.2rem;
            font-weight: bold;
        }

        .file-grid {
            padding: 1.5rem;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
        }

        .file-card {
            border: 1px solid #e1e5e9;
            border-radius: 8px;
            padding: 1rem;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .file-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .file-name {
            color: #333;
            font-weight: bold;
            margin-bottom: 0.5rem;
            word-break: break-word;
        }

        .file-meta {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .file-employee {
            color: #667eea;
            font-weight: 500;
        }

        .file-description {
            color: #555;
            font-size: 0.9rem;
            margin-top: 0.5rem;
            font-style: italic;
        }

        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            font-size: 0.9rem;
            transition: transform 0.2s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .stats-bar {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            text-align: center;
            color: #666;
        }

        @media (max-width: 768px) {
            .nav-container {
                flex-direction: column;
                gap: 1rem;
            }

            .container {
                padding: 0 1rem;
            }

            .file-grid {
                grid-template-columns: 1fr;
            }

            .filter-group {
                flex-direction: column;
                align-items: flex-start;
            }
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
                <a href="employee_files.php">Employee Files</a>
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                <a href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1>Employee Files Management</h1>
            <p>View and manage files uploaded by employees, organized by department.</p>
        </div>

        <div class="filters">
            <form method="GET">
                <div class="filter-group">
                    <label for="dept">Filter by Department:</label>
                    <select name="dept" id="dept" onchange="this.form.submit()">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo htmlspecialchars($dept); ?>" <?php echo $selectedDept === $dept ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>

        <?php if (empty($files)): ?>
            <div class="department-section">
                <div class="stats-bar">No files found.</div>
            </div>
        <?php else: ?>
            <?php foreach ($filesByDept as $deptName => $deptFiles): ?>
                <div class="department-section">
                    <div class="dept-header">
                        <?php echo htmlspecialchars($deptName); ?> (<?php echo count($deptFiles); ?> files)
                    </div>
                    <div class="file-grid">
                        <?php foreach ($deptFiles as $file): ?>
                            <div class="file-card">
                                <div class="file-name"><?php echo htmlspecialchars($file['original_filename']); ?></div>
                                <div class="file-meta">
                                    <strong>Size:</strong> <?php echo number_format($file['file_size'] / 1024, 1); ?> KB<br>
                                    <strong>Uploaded:</strong> <?php echo date('M d, Y H:i', strtotime($file['upload_date'])); ?>
                                </div>
                                <div class="file-employee">
                                    By: <?php echo htmlspecialchars($file['full_name']); ?>
                                </div>
                                <?php if ($file['description']): ?>
                                    <div class="file-description">
                                        "<?php echo htmlspecialchars($file['description']); ?>"
                                    </div>
                                <?php endif; ?>
                                <div style="margin-top: 1rem;">
                                    <a href="uploads/employee_files/<?php echo htmlspecialchars($file['stored_filename']); ?>" 
                                       class="btn" target="_blank">Download</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>