<?php
// save_vaccination.php - Handles saving vaccination data

require_once __DIR__ . '/vendor/autoload.php';

use App\Database;
use App\Sanitizer;
use App\Logger;

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

// Validate CSRF token
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF invalide']);
    exit;
}

// Get consultation ID
$consultationId = (int)($_POST['consultation_id'] ?? 0);

// Get vaccination data
$vaccines = $_POST['vaccines'] ?? [];

if (empty($vaccines)) {
    echo json_encode(['success' => false, 'message' => 'Aucune donnée de vaccination fournie']);
    exit;
}

// Sanitize vaccination data
$sanitizedVaccines = [];
foreach ($vaccines as $vaccineKey => $vaccineData) {
    $sanitizedVaccines[$vaccineKey] = [];
    
    if (isset($vaccineData['selected'])) {
        $sanitizedVaccines[$vaccineKey]['selected'] = (bool)$vaccineData['selected'];
    }
    
    if (isset($vaccineData['received'])) {
        $sanitizedVaccines[$vaccineKey]['received'] = Sanitizer::sanitize($vaccineData['received']);
    }
    
    if (isset($vaccineData['date'])) {
        $sanitizedVaccines[$vaccineKey]['date'] = Sanitizer::sanitize($vaccineData['date']);
    }
    
    if (isset($vaccineData['observations'])) {
        $sanitizedVaccines[$vaccineKey]['observations'] = Sanitizer::sanitize($vaccineData['observations']);
    }
    
    if (isset($vaccineData['administration']) && is_array($vaccineData['administration'])) {
        $sanitizedVaccines[$vaccineKey]['administration'] = array_map([Sanitizer::class, 'sanitize'], $vaccineData['administration']);
    }
}

try {
    // Save to session
    $_SESSION['vaccination_calendar'] = $sanitizedVaccines;
    
    // Save to database if consultation ID is provided
    if ($consultationId > 0) {
        $db = Database::getInstance();
        $pdo = $db->getConnection();
        
        $db->beginTransaction();
        
        // Check if consultation exists
        $stmt = $pdo->prepare("SELECT id FROM consultations WHERE id = ?");
        $stmt->execute([$consultationId]);
        
        if (!$stmt->fetch()) {
            $db->rollback();
            echo json_encode(['success' => false, 'message' => 'Consultation non trouvée']);
            exit;
        }
        
        // Save vaccination data
        $vaccinationJson = json_encode($sanitizedVaccines);
        $stmt = $pdo->prepare("
            INSERT INTO consultation_vaccinations (consultation_id, vaccination_data) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE vaccination_data = VALUES(vaccination_data), updated_at = NOW()
        ");
        $stmt->execute([$consultationId, $vaccinationJson]);
        
        $db->commit();
        
        Logger::info('Vaccination data saved successfully', [
            'consultation_id' => $consultationId,
            'vaccines_count' => count($sanitizedVaccines)
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Données de vaccination sauvegardées avec succès',
            'consultation_id' => $consultationId,
            'vaccines_count' => count($sanitizedVaccines)
        ]);
    } else {
        // Save to session only
        echo json_encode([
            'success' => true,
            'message' => 'Données de vaccination sauvegardées en session',
            'vaccines_count' => count($sanitizedVaccines)
        ]);
    }
    
} catch (Exception $e) {
    if (isset($db)) {
        $db->rollback();
    }
    
    Logger::error('Error saving vaccination data', [
        'consultation_id' => $consultationId,
        'error' => $e->getMessage()
    ]);
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la sauvegarde des données de vaccination'
    ]);
}