<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET' || !isset($_GET['token'])) {
    header('Location: login.html');
    exit;
}

$token = filter_var($_GET['token'], FILTER_SANITIZE_STRING);

try {
    // Find user with this verification token
    $stmt = $pdo->prepare("SELECT id, email_verified FROM users WHERE verification_token = ?");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        die('Token de verificación inválido');
    }

    if ($user['email_verified']) {
        die('Este correo electrónico ya ha sido verificado');
    }

    // Update user as verified
    $stmt = $pdo->prepare("UPDATE users SET email_verified = TRUE, verification_token = NULL WHERE id = ?");
    $stmt->execute([$user['id']]);

    // Redirect to success page
    header('Location: verification_success.html');
    exit;

} catch (PDOException $e) {
    error_log($e->getMessage());
    die('Error del servidor');
}
?>