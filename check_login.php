<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'logged_in' => false
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT first_name, last_name, email, is_admin FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        echo json_encode([
            'logged_in' => true,
            'user_name' => $user['first_name'],
            'is_admin' => $user['is_admin'] || $user['email'] === 'thepilotdev@gmail.com'
        ]);
    } else {
        // User not found in database, clear session
        session_destroy();
        echo json_encode([
            'logged_in' => false
        ]);
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    echo json_encode([
        'logged_in' => false,
        'error' => 'Error del servidor'
    ]);
}
?>