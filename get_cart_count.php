<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => true,
        'count' => 0
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(quantity), 0) as count 
        FROM cart 
        WHERE user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'count' => (int)$result['count']
    ]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    echo json_encode([
        'success' => false,
        'count' => 0,
        'message' => 'Error al obtener cantidad del carrito'
    ]);
}
?>