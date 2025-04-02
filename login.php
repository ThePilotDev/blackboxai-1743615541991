<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
$password = $_POST['password'];

if (empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Por favor complete todos los campos']);
    exit;
}

try {
    // Check if user exists and is verified
    $stmt = $pdo->prepare("SELECT id, email, password, is_admin, email_verified FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Correo electrónico o contraseña incorrectos']);
        exit;
    }

    if (!$user['email_verified']) {
        echo json_encode(['success' => false, 'message' => 'Por favor verifique su correo electrónico antes de iniciar sesión']);
        exit;
    }

    // Verify password
    if (!password_verify($password, $user['password'])) {
        echo json_encode(['success' => false, 'message' => 'Correo electrónico o contraseña incorrectos']);
        exit;
    }

    // Set session variables
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['is_admin'] = $user['is_admin'];

    // Check if user is admin
    $isAdmin = $user['email'] === 'thepilotdev@gmail.com';
    if ($isAdmin) {
        $_SESSION['is_admin'] = true;
    }

    echo json_encode([
        'success' => true, 
        'message' => 'Inicio de sesión exitoso',
        'is_admin' => $isAdmin
    ]);

} catch (PDOException $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error del servidor']);
    exit;
}
?>