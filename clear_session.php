<?php
// clear_session.php - Clears session data after successful submission

// Security headers
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Start session
session_set_cookie_params([
    'lifetime' => 1800,
    'path' => '/',
    'domain' => '',
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    'httponly' => true,
    'samesite' => 'Strict'
]);
session_start();

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

try {
    // Clear consultation-related session data
    unset($_SESSION['consultation_form_data']);
    unset($_SESSION['consultation_id']);
    unset($_SESSION['current_step']);
    unset($_SESSION['vaccination_calendar']);
    unset($_SESSION['prefill_data']);
    
    echo json_encode([
        'success' => true,
        'message' => 'Session nettoyée avec succès'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors du nettoyage de la session'
    ]);
}