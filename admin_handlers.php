<?php
session_start();
require_once 'config.php';

// Check if user is admin
function checkAdmin() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
        exit;
    }
}

// Handle file upload
function handleFileUpload($file) {
    $uploadDir = 'uploads/products/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $fileName = uniqid() . '_' . basename($file['name']);
    $targetPath = $uploadDir . $fileName;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return $targetPath;
    }
    return false;
}

// Get all products
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_products') {
    checkAdmin();
    
    try {
        $stmt = $pdo->query("SELECT * FROM products ORDER BY created_at DESC");
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'products' => $products]);
    } catch (PDOException $e) {
        error_log($e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error al obtener productos']);
    }
}

// Add new product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_product') {
    checkAdmin();
    
    try {
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Error al subir la imagen');
        }

        $imagePath = handleFileUpload($_FILES['image']);
        if (!$imagePath) {
            throw new Exception('Error al guardar la imagen');
        }

        $stmt = $pdo->prepare("
            INSERT INTO products (
                name, price, image_url, description, category,
                discount, stock, estimated_shipping_date
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $_POST['name'],
            $_POST['price'],
            $imagePath,
            $_POST['description'],
            $_POST['category'],
            $_POST['discount'] ?? 0,
            $_POST['stock'],
            $_POST['shipping_date']
        ]);

        echo json_encode(['success' => true, 'message' => 'Producto agregado exitosamente']);
    } catch (Exception $e) {
        error_log($e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error al agregar producto']);
    }
}

// Edit product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_product') {
    checkAdmin();
    
    try {
        $imagePath = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $imagePath = handleFileUpload($_FILES['image']);
            if (!$imagePath) {
                throw new Exception('Error al guardar la imagen');
            }
        }

        $sql = "
            UPDATE products 
            SET name = ?, price = ?, description = ?, category = ?,
                discount = ?, stock = ?, estimated_shipping_date = ?
        ";
        $params = [
            $_POST['name'],
            $_POST['price'],
            $_POST['description'],
            $_POST['category'],
            $_POST['discount'] ?? 0,
            $_POST['stock'],
            $_POST['shipping_date']
        ];

        if ($imagePath) {
            $sql .= ", image_url = ?";
            $params[] = $imagePath;
        }

        $sql .= " WHERE id = ?";
        $params[] = $_POST['product_id'];

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        echo json_encode(['success' => true, 'message' => 'Producto actualizado exitosamente']);
    } catch (Exception $e) {
        error_log($e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error al actualizar producto']);
    }
}

// Delete product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_product') {
    checkAdmin();
    
    try {
        // Get product image path
        $stmt = $pdo->prepare("SELECT image_url FROM products WHERE id = ?");
        $stmt->execute([$_POST['product_id']]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        // Delete product
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$_POST['product_id']]);

        // Delete image file
        if ($product && $product['image_url'] && file_exists($product['image_url'])) {
            unlink($product['image_url']);
        }

        echo json_encode(['success' => true, 'message' => 'Producto eliminado exitosamente']);
    } catch (Exception $e) {
        error_log($e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error al eliminar producto']);
    }
}

// Add admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_admin') {
    checkAdmin();
    
    try {
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        
        $stmt = $pdo->prepare("UPDATE users SET is_admin = TRUE WHERE email = ?");
        $stmt->execute([$email]);

        if ($stmt->rowCount() === 0) {
            throw new Exception('Usuario no encontrado');
        }

        echo json_encode(['success' => true, 'message' => 'Administrador agregado exitosamente']);
    } catch (Exception $e) {
        error_log($e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error al agregar administrador']);
    }
}
?>