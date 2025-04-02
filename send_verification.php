<?php
session_start();
require_once 'config.php';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (empty($data['recaptcha'])) {
    echo json_encode(['success' => false, 'message' => 'Por favor complete el captcha']);
    exit;
}

// Verify reCAPTCHA
$recaptcha_response = $data['recaptcha'];
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

error_log("reCAPTCHA Response: " . print_r($captcha_success, true));

error_log("reCAPTCHA score: " . ($captcha_success->score ?? 'no score') . ", action: " . ($captcha_success->action ?? 'no action'));

if (!$captcha_success->success || 
    !isset($captcha_success->score) || 
    $captcha_success->score < 0.5 || 
    !isset($captcha_success->action) || 
    !in_array($captcha_success->action, ['send_code', 'resend_code'])) {
    
    echo json_encode([
        'success' => false,
        'message' => 'Verificación de seguridad fallida. Por favor intente nuevamente.',
        'debug' => [
            'success' => $captcha_success->success ?? false,
            'score' => $captcha_success->score ?? 'no score',
            'action' => $captcha_success->action ?? 'no action',
            'response' => $verify_response
        ]
    ]);
    exit;
}

$email = filter_var($data['email'], FILTER_SANITIZE_EMAIL);

if (empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Correo electrónico requerido']);
    exit;
}

try {
    // Generate verification code
    $verificationCode = sprintf('%06d', random_int(0, 999999));
    
    // Store verification code in session
    $_SESSION['verification_code'] = $verificationCode;
    $_SESSION['verification_email'] = $email;
    $_SESSION['verification_expires'] = time() + (15 * 60); // 15 minutes expiry

    // Create PHPMailer instance
    $mail = new PHPMailer(true);

    // Server settings
    $mail->isSMTP();
    $mail->Host = $smtp_host;
    $mail->SMTPAuth = true;
    $mail->Username = $smtp_username;
    $mail->Password = $smtp_password;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = $smtp_port;
    $mail->CharSet = 'UTF-8';
    
    // Enable debug output
    $mail->SMTPDebug = 2;
    $mail->Debugoutput = function($str, $level) {
        error_log("PHPMailer Debug: $str");
    };

    // Recipients
    $mail->setFrom($smtp_username, 'RipStoreSc');
    $mail->addAddress($email);

    // Content
    $mail->isHTML(true);
    $mail->Subject = 'Código de Verificación - RipStoreSc';
    $mail->Body = "
    <html>
    <head>
        <title>Verificación de Correo Electrónico</title>
        <style>
            body { font-family: Arial, sans-serif; }
            .code { font-size: 24px; font-weight: bold; color: #000; }
        </style>
    </head>
    <body>
        <h2>Verificación de Correo Electrónico</h2>
        <p>Tu código de verificación es: <span class='code'>{$verificationCode}</span></p>
        <p>Este código expirará en 15 minutos.</p>
        <br>
        <p>Si no solicitaste este código, puedes ignorar este correo.</p>
    </body>
    </html>";

    $mail->send();
    error_log("Email sent successfully to: " . $email);
    
    echo json_encode([
        'success' => true,
        'message' => 'Código de verificación enviado',
        'debug' => [
            'email' => $email,
            'code_length' => strlen($verificationCode),
            'session_set' => isset($_SESSION['verification_code'])
        ]
    ]);

} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al enviar el código de verificación. Por favor intenta más tarde.',
        'error' => $e->getMessage()
    ]);
}
?>