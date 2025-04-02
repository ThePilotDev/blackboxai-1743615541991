<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$userId = $_SESSION['user_id'];
$paymentMethod = $_POST['payment_method'];

try {
    // Start transaction
    $pdo->beginTransaction();

    // Get cart items
    $stmt = $pdo->prepare("
        SELECT c.*, p.name, p.price 
        FROM cart c 
        JOIN products p ON c.product_id = p.id 
        WHERE c.user_id = ?
    ");
    $stmt->execute([$userId]);
    $cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($cartItems)) {
        echo json_encode(['success' => false, 'message' => 'Carrito vacío']);
        exit;
    }

    // Calculate total
    $total = array_reduce($cartItems, function($sum, $item) {
        return $sum + ($item['price'] * $item['quantity']);
    }, 0);

    // Create order
    $stmt = $pdo->prepare("
        INSERT INTO orders (user_id, total_amount, payment_method, payment_status)
        VALUES (?, ?, ?, 'pending')
    ");
    $stmt->execute([$userId, $total, $paymentMethod]);
    $orderId = $pdo->lastInsertId();

    // Add order items
    $stmt = $pdo->prepare("
        INSERT INTO order_items (order_id, product_id, quantity, price)
        VALUES (?, ?, ?, ?)
    ");
    foreach ($cartItems as $item) {
        $stmt->execute([
            $orderId,
            $item['product_id'],
            $item['quantity'],
            $item['price']
        ]);
    }

    // Handle payment proof upload for PagoMovil
    if ($paymentMethod === 'pagomovil' && isset($_FILES['payment_proof'])) {
        $uploadDir = 'uploads/payment_proofs/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $uploadedFiles = [];
        foreach ($_FILES['payment_proof']['tmp_name'] as $key => $tmpName) {
            $fileName = uniqid() . '_' . $_FILES['payment_proof']['name'][$key];
            $filePath = $uploadDir . $fileName;
            
            if (move_uploaded_file($tmpName, $filePath)) {
                $uploadedFiles[] = $filePath;
            }
        }

        if (!empty($uploadedFiles)) {
            // Update order with payment proof
            $stmt = $pdo->prepare("
                UPDATE orders 
                SET payment_proof_url = ? 
                WHERE id = ?
            ");
            $stmt->execute([json_encode($uploadedFiles), $orderId]);

            // Send WhatsApp notification with payment proof
            $user = getUserDetails($userId, $pdo);
            sendWhatsAppNotification($orderId, $user, $uploadedFiles);
        }
    }

    // Clear cart
    $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
    $stmt->execute([$userId]);

    // Commit transaction
    $pdo->commit();

    echo json_encode(['success' => true, 'order_id' => $orderId]);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al procesar el pedido']);
}

function getUserDetails($userId, $pdo) {
    $stmt = $pdo->prepare("
        SELECT first_name, last_name, email, phone 
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function sendWhatsAppNotification($orderId, $user, $proofFiles) {
    global $whatsapp_number;
    
    // Format message
    $message = "¡Nuevo pago recibido!\n\n";
    $message .= "Orden #: {$orderId}\n";
    $message .= "Cliente: {$user['first_name']} {$user['last_name']}\n";
    $message .= "Email: {$user['email']}\n";
    $message .= "Teléfono: {$user['phone']}\n";
    $message .= "\nComprobantes de pago adjuntos.";

    // Here you would implement the actual WhatsApp API integration
    // For example, using the WhatsApp Business API or a third-party service
    
    // For demonstration purposes, we'll just log the message
    error_log("WhatsApp notification would be sent to: {$whatsapp_number}");
    error_log("Message: {$message}");
    error_log("Attachments: " . implode(", ", $proofFiles));
}
?>