<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Verify reCAPTCHA v3
$recaptcha_response = $_POST['recaptcha_response'];
$verify_url = "https://www.google.com/recaptcha/api/siteverify";
$recaptcha_data = [
    'secret' => $recaptcha_secret_key,
    'response' => $recaptcha_response
];

$options = [
    'http' => [
        'header' => "Content-type: application/x-www-form-urlencoded\r\n",
        'method' => 'POST',
        'content' => http_build_query($recaptcha_data)
    ]
];

$context = stream_context_create($options);
$verify_response = file_get_contents($verify_url, false, $context);
$captcha_success = json_decode($verify_response);

error_log("Register reCAPTCHA score: " . ($captcha_success->score ?? 'no score') . ", action: " . ($captcha_success->action ?? 'no action'));

if (!$captcha_success->success || 
    !isset($captcha_success->score) || 
    $captcha_success->score < 0.5 || 
    !isset($captcha_success->action) || 
    $captcha_success->action !== 'register') {
    
    echo json_encode([
        'success' => false,
        'message' => 'Verificación de seguridad fallida. Por favor intente nuevamente.',
        'debug' => [
            'success' => $captcha_success->success ?? false,
            'score' => $captcha_success->score ?? 'no score',
            'action' => $captcha_success->action ?? 'no action'
        ]
    ]);
    exit;
}

$email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
$password = $_POST['password'];
$nombre = filter_var($_POST['nombre'], FILTER_SANITIZE_STRING);
$apellido = filter_var($_POST['apellido'], FILTER_SANITIZE_STRING);
$telefono = filter_var($_POST['telefono'], FILTER_SANITIZE_STRING);
$linea1 = filter_var($_POST['linea1'], FILTER_SANITIZE_STRING);
$linea2 = filter_var($_POST['linea2'], FILTER_SANITIZE_STRING);
$ciudad = filter_var($_POST['ciudad'], FILTER_SANITIZE_STRING);

if (empty($email) || empty($password) || empty($nombre) || empty($apellido) || empty($telefono) || empty($linea1) || empty($ciudad)) {
    echo json_encode(['success' => false, 'message' => 'Por favor complete todos los campos requeridos']);
    exit;
}

try {
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert user
    $stmt = $pdo->prepare("
        INSERT INTO users (
            email, 
            password, 
            first_name, 
            last_name, 
            phone, 
            address_line1, 
            address_line2, 
            city,
            email_verified
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $email,
        $hashedPassword,
        $nombre,
        $apellido,
        $telefono,
        $linea1,
        $linea2,
        $ciudad,
        1  // Set email_verified to true since we verified via code
    ]);

    $userId = $pdo->lastInsertId();

    // Set admin if email matches
    if ($email === 'frucosecos@gmail.com') {
        $stmt = $pdo->prepare("UPDATE users SET is_admin = TRUE WHERE id = ?");
        $stmt->execute([$userId]);
    }

    echo json_encode([
        'success' => true,
        'message' => '¡Registro exitoso! Bienvenido a RipStoreSc.'
    ]);

} catch (PDOException $e) {
    error_log($e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error del servidor',
        'debug' => $e->getMessage()
    ]);
    exit;
}
?>