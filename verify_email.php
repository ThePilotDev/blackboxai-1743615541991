<?php
session_start();
header('Content-Type: application/json');

error_log("Starting email verification process");

$data = json_decode(file_get_contents('php://input'), true);
error_log("Received data: " . print_r($data, true));

$submittedCode = filter_var($data['code'], FILTER_SANITIZE_STRING);
error_log("Submitted code: " . $submittedCode);

if (empty($submittedCode)) {
    echo json_encode(['success' => false, 'message' => 'Código de verificación requerido']);
    exit;
}

error_log("Session data: " . print_r($_SESSION, true));

// Check if verification data exists in session
if (!isset($_SESSION['verification_code']) || 
    !isset($_SESSION['verification_email']) || 
    !isset($_SESSION['verification_expires'])) {
    error_log("Missing session data for verification");
    echo json_encode([
        'success' => false, 
        'message' => 'Sesión de verificación expirada. Por favor solicite un nuevo código.',
        'debug' => [
            'has_code' => isset($_SESSION['verification_code']),
            'has_email' => isset($_SESSION['verification_email']),
            'has_expires' => isset($_SESSION['verification_expires'])
        ]
    ]);
    exit;
}

// Check if code has expired
if (time() > $_SESSION['verification_expires']) {
    error_log("Verification code has expired");
    // Clear verification data
    unset($_SESSION['verification_code']);
    unset($_SESSION['verification_email']);
    unset($_SESSION['verification_expires']);
    
    echo json_encode([
        'success' => false,
        'message' => 'El código ha expirado. Por favor solicite un nuevo código.'
    ]);
    exit;
}

// Verify the code
error_log("Comparing codes - Submitted: " . $submittedCode . ", Stored: " . $_SESSION['verification_code']);
if ($submittedCode === $_SESSION['verification_code']) {
    error_log("Code verification successful");
    // Store verification success in session
    $_SESSION['email_verified'] = true;
    $_SESSION['verified_email'] = $_SESSION['verification_email'];
    
    // Clear verification data
    unset($_SESSION['verification_code']);
    unset($_SESSION['verification_email']);
    unset($_SESSION['verification_expires']);
    
    echo json_encode([
        'success' => true,
        'message' => 'Correo electrónico verificado exitosamente'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Código de verificación incorrecto',
        'debug' => [
            'submitted' => $submittedCode,
            'expected' => $_SESSION['verification_code']
        ]
    ]);
}
?>