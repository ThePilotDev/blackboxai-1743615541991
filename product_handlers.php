<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

// Get all products
if ($_SERVER['REQUEST_METHOD'] === 'GET' && (!isset($_GET['action']) || $_GET['action'] === 'get_all')) {
    try {
        $stmt = $pdo->query("
            SELECT id, name, price, image_url, description, category, discount, stock 
            FROM products 
            WHERE stock > 0 
            ORDER BY created_at DESC
        ");
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'products' => array_map(function($product) {
                $product['price_with_discount'] = $product['discount'] > 0 
                    ? $product['price'] * (1 - $product['discount'] / 100) 
                    : $product['price'];
                return $product;
            }, $products)
        ]);
    } catch (PDOException $e) {
        error_log($e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error al obtener productos']);
    }
}

// Get products by category
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_category') {
    $category = filter_var($_GET['category'], FILTER_SANITIZE_STRING);

    try {
        // Get products with discounts (offers)
        $stmt = $pdo->prepare("
            SELECT id, name, price, image_url, description, category, discount, stock 
            FROM products 
            WHERE category = ? AND discount > 0 AND stock > 0 
            ORDER BY discount DESC 
            LIMIT 4
        ");
        $stmt->execute([$category]);
        $offers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get recommended products (no discount)
        $stmt = $pdo->prepare("
            SELECT id, name, price, image_url, description, category, discount, stock 
            FROM products 
            WHERE category = ? AND discount = 0 AND stock > 0 
            ORDER BY RAND() 
            LIMIT 4
        ");
        $stmt->execute([$category]);
        $recommendations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate prices with discounts
        $processProducts = function($products) {
            return array_map(function($product) {
                $product['price_with_discount'] = $product['discount'] > 0 
                    ? $product['price'] * (1 - $product['discount'] / 100) 
                    : $product['price'];
                return $product;
            }, $products);
        };

        echo json_encode([
            'success' => true,
            'offers' => $processProducts($offers),
            'recommendations' => $processProducts($recommendations)
        ]);
    } catch (PDOException $e) {
        error_log($e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error al obtener productos']);
    }
}

// Get single product
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_product') {
    $productId = filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT);

    try {
        $stmt = $pdo->prepare("
            SELECT id, name, price, image_url, description, category, discount, stock, estimated_shipping_date 
            FROM products 
            WHERE id = ?
        ");
        $stmt->execute([$productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            echo json_encode(['success' => false, 'message' => 'Producto no encontrado']);
            exit;
        }

        // Get related products from same category
        $stmt = $pdo->prepare("
            SELECT id, name, price, image_url, discount 
            FROM products 
            WHERE category = ? AND id != ? AND stock > 0 
            ORDER BY RAND() 
            LIMIT 4
        ");
        $stmt->execute([$product['category'], $productId]);
        $relatedProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate prices with discounts
        $product['price_with_discount'] = $product['discount'] > 0 
            ? $product['price'] * (1 - $product['discount'] / 100) 
            : $product['price'];

        $relatedProducts = array_map(function($related) {
            $related['price_with_discount'] = $related['discount'] > 0 
                ? $related['price'] * (1 - $related['discount'] / 100) 
                : $related['price'];
            return $related;
        }, $relatedProducts);

        echo json_encode([
            'success' => true,
            'product' => $product,
            'related_products' => $relatedProducts
        ]);
    } catch (PDOException $e) {
        error_log($e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error al obtener producto']);
    }
}

// Search products
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'search') {
    $searchTerm = filter_var($_GET['term'], FILTER_SANITIZE_STRING);

    try {
        $stmt = $pdo->prepare("
            SELECT id, name, price, image_url, description, category, discount, stock 
            FROM products 
            WHERE (name LIKE ? OR description LIKE ?) AND stock > 0 
            ORDER BY 
                CASE 
                    WHEN name LIKE ? THEN 1 
                    WHEN name LIKE ? THEN 2 
                    ELSE 3 
                END, 
                name ASC
        ");
        
        $searchPattern = "%{$searchTerm}%";
        $startPattern = "{$searchTerm}%";
        $stmt->execute([$searchPattern, $searchPattern, $startPattern, $searchPattern]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'products' => array_map(function($product) {
                $product['price_with_discount'] = $product['discount'] > 0 
                    ? $product['price'] * (1 - $product['discount'] / 100) 
                    : $product['price'];
                return $product;
            }, $products)
        ]);
    } catch (PDOException $e) {
        error_log($e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error al buscar productos']);
    }
}
?>