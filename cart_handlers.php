<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

// Get cart count
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_count') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => true, 'count' => 0]);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT SUM(quantity) as count FROM cart WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'count' => $result['count'] ?? 0
        ]);
    } catch (PDOException $e) {
        error_log($e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error al obtener carrito']);
    }
}

// Get cart items
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_items') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT c.*, p.name, p.price, p.image_url 
            FROM cart c 
            JOIN products p ON c.product_id = p.id 
            WHERE c.user_id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total = array_reduce($items, function($sum, $item) {
            return $sum + ($item['price'] * $item['quantity']);
        }, 0);

        echo json_encode([
            'success' => true,
            'items' => $items,
            'total' => $total
        ]);
    } catch (PDOException $e) {
        error_log($e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error al obtener carrito']);
    }
}

// Add to cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
        exit;
    }

    $productId = filter_var($_POST['product_id'], FILTER_SANITIZE_NUMBER_INT);
    $quantity = filter_var($_POST['quantity'], FILTER_SANITIZE_NUMBER_INT);

    try {
        // Check if product exists and has enough stock
        $stmt = $pdo->prepare("SELECT stock FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            throw new Exception('Producto no encontrado');
        }

        if ($product['stock'] < $quantity) {
            throw new Exception('Stock insuficiente');
        }

        // Check if product already in cart
        $stmt = $pdo->prepare("SELECT quantity FROM cart WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$_SESSION['user_id'], $productId]);
        $cartItem = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($cartItem) {
            // Update quantity
            $newQuantity = $cartItem['quantity'] + $quantity;
            if ($newQuantity > $product['stock']) {
                throw new Exception('Stock insuficiente');
            }

            $stmt = $pdo->prepare("
                UPDATE cart 
                SET quantity = ? 
                WHERE user_id = ? AND product_id = ?
            ");
            $stmt->execute([$newQuantity, $_SESSION['user_id'], $productId]);
        } else {
            // Add new item
            $stmt = $pdo->prepare("
                INSERT INTO cart (user_id, product_id, quantity)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$_SESSION['user_id'], $productId, $quantity]);
        }

        echo json_encode(['success' => true, 'message' => 'Producto agregado al carrito']);
    } catch (Exception $e) {
        error_log($e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Update cart item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
        exit;
    }

    $productId = filter_var($_POST['product_id'], FILTER_SANITIZE_NUMBER_INT);
    $quantity = filter_var($_POST['quantity'], FILTER_SANITIZE_NUMBER_INT);

    try {
        if ($quantity <= 0) {
            // Remove item if quantity is 0 or less
            $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$_SESSION['user_id'], $productId]);
        } else {
            // Check stock
            $stmt = $pdo->prepare("SELECT stock FROM products WHERE id = ?");
            $stmt->execute([$productId]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($quantity > $product['stock']) {
                throw new Exception('Stock insuficiente');
            }

            // Update quantity
            $stmt = $pdo->prepare("
                UPDATE cart 
                SET quantity = ? 
                WHERE user_id = ? AND product_id = ?
            ");
            $stmt->execute([$quantity, $_SESSION['user_id'], $productId]);
        }

        echo json_encode(['success' => true, 'message' => 'Carrito actualizado']);
    } catch (Exception $e) {
        error_log($e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Remove from cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
        exit;
    }

    $productId = filter_var($_POST['product_id'], FILTER_SANITIZE_NUMBER_INT);

    try {
        $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$_SESSION['user_id'], $productId]);

        echo json_encode(['success' => true, 'message' => 'Producto eliminado del carrito']);
    } catch (PDOException $e) {
        error_log($e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error al eliminar producto']);
    }
}
?>