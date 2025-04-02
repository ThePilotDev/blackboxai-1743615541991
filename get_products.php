<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

try {
    // Get featured products (with discounts)
    $stmt = $pdo->query("
        SELECT id, name, price, image_url, description, category, discount, stock 
        FROM products 
        WHERE stock > 0 
        ORDER BY 
            CASE 
                WHEN discount > 0 THEN 1 
                ELSE 2 
            END,
            created_at DESC 
        LIMIT 8
    ");
    
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate discounted prices and format response
    $formattedProducts = array_map(function($product) {
        $product['original_price'] = $product['price'];
        $product['price'] = $product['discount'] > 0 
            ? $product['price'] * (1 - $product['discount'] / 100) 
            : $product['price'];
        
        // Format prices to 2 decimal places
        $product['price'] = number_format($product['price'], 2, '.', '');
        $product['original_price'] = number_format($product['original_price'], 2, '.', '');
        
        return $product;
    }, $products);

    echo json_encode([
        'success' => true,
        'products' => $formattedProducts
    ]);

} catch (PDOException $e) {
    error_log($e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al cargar los productos',
        'error' => $e->getMessage()
    ]);
}
?>