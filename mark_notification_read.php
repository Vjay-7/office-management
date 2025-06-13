<?php
require_once 'config.php';
requireLogin();

$input = json_decode(file_get_contents('php://input'), true);
$notificationId = $input['id'];

$stmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE id = ? AND user_id = ?");
$stmt->execute([$notificationId, $_SESSION['user_id']]);

echo json_encode(['success' => true]);
?>