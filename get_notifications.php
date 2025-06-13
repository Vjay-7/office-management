<?php
require_once 'config.php';
requireLogin();

$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$stmt->execute([$_SESSION['user_id']]);
$notifications = $stmt->fetchAll();

header('Content-Type: application/json');
echo json_encode($notifications);
?>