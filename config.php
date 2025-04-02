<?php
// Database configuration
$db_host = "localhost";
$db_name = "ripsgvse_ripstore_admin";
$db_user = "ripsgvse_admin";
$db_pass = "0ogiUNpqAiH8P4w";

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Email configuration for verification
$smtp_host = "smtp.gmail.com";
$smtp_username = "frucosecos@gmail.com";
$smtp_password = "awgp hhxk fbni chwk"; // Gmail App Password
$smtp_port = 587;

// reCAPTCHA configuration
$recaptcha_site_key = "6Lf5jgcrAAAAAGUjILX9MqzNdznT0dISQ1RsPxcT";
$recaptcha_secret_key = "6Lf5jgcrAAAAAEUFYdnDBxtpHZKmYBE3dzvl3d9W";

// WhatsApp configuration for payment notifications
$whatsapp_number = "your_whatsapp_number"; // Add your WhatsApp number here

// Admin email
$admin_email = "frucosecos@gmail.com";
?>