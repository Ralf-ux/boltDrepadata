<?php
// save_consultation_step.php - Handles incremental saving of consultation steps

require_once __DIR__ . '/vendor/autoload.php';

use App\Database;
use App\Validator;
use App\Sanitizer;
use App\Logger;

// Security headers
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Start session with secure settings
session_set_cookie_params([
    'lifetime' => 1800, // 30 minutes
    'path' => '/',
    'domain' => '',
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    'httponly' => true,
    'samesite' => 'Strict'
]);
session_start();

// Session timeout check
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 1800) {
    session_unset();
    session_destroy();
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Session expirée']);
    exit;
}
$_SESSION['last_activity'] = time();

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

// Get request data
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($contentType, 'application/json') !== false) {
    $input = json_decode(file_get_contents('php://input'), true);
} else {
    $input = $_POST;
}

// Validate CSRF token
if (!isset($input['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $input['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF invalide']);
    exit;
}

// Handle session save action
if (isset($input['action']) && $input['action'] === 'save_session') {
    $_SESSION['consultation_form_data'] = $input;
    echo json_encode(['success' => true]);
    exit;
}

// Get current step
$currentStep = (int)($input['current_step'] ?? 1);
$consultationId = (int)($input['consultation_id'] ?? 0);

// Step to table mapping
$stepTableMap = [
    1 => 'consultations',
    2 => 'consultations',
    3 => 'consultation_treatments',
    4 => 'consultation_exams',
    5 => 'consultation_observations',
    6 => 'consultation_vaccinations',
    7 => 'consultation_observations',
    8 => 'consultations',
    9 => 'consultations'
];

$targetTable = $stepTableMap[$currentStep] ?? 'consultations';

// Validation rules by step
$validationRules = [
    1 => [
        'fosa' => ['required', 'string', 'max:255'],
        'region' => ['required', 'string', 'max:255'],
        'district' => ['string', 'max:255'],
        'diagnostic_date' => ['date'],
        'ipp' => ['string', 'max:255'],
        'personnel' => ['string', 'max:255'],
        'referred' => ['in:Oui,Non'],
        'referred_from' => ['string', 'max:255'],
        'referred_for' => ['string', 'max:255'],
        'evolution' => ['string', 'max:255']
    ],
    2 => [
        'full_name' => ['required', 'string', 'max:255'],
        'age' => ['integer', 'min:0'],
        'birth_date' => ['date'],
        'sex' => ['in:M,F'],
        'address' => ['string'],
        'emergency_contact_name' => ['string', 'max:255'],
        'emergency_contact_relation' => ['string', 'max:255'],
        'emergency_contact_phone' => ['nullable', 'regex:/^[0-9]{9}$/'],
        'lives_with' => ['in:Oui,Non'],
        'insurance' => ['in:Oui,Non'],
        'support_group' => ['in:Oui,Non'],
        'group_name' => ['string', 'max:255'],
        'parents' => ['in:Oui,Non'],
        'sibling_rank' => ['integer', 'min:1']
    ],
    3 => [
        'hydroxyurea' => ['in:Oui,Non'],
        'tolerance' => ['string', 'max:50'],
        'hydroxyurea_reasons' => ['string', 'max:255'],
        'hydroxyurea_dosage' => ['string', 'max:100'],
        'folic_acid' => ['in:Oui,Non'],
        'penicillin' => ['in:Oui,Non'],
        'regular_transfusion' => ['in:Oui,Non'],
        'transfusion_type' => ['string', 'max:50'],
        'transfusion_frequency' => ['string', 'max:100'],
        'last_transfusion_date' => ['date'],
        'other_treatments' => ['string']
    ],
    4 => [
        'sickle_type' => ['in:SS,SC,Sβ⁰,Sβ⁺,Autre'],
        'diagnosis_age' => ['string', 'max:50'],
        'diagnosis_circumstance' => ['string', 'max:50'],
        'family_history' => ['string', 'max:50'],
        'other_medical_history' => ['in:Oui,Non'],
        'previous_surgeries' => ['in:Oui,Non'],
        'allergies' => ['in:Oui,Non'],
        'vocs' => ['string', 'max:50'],
        'hospitalizations' => ['string', 'max:50']
    ],
    5 => [
        'impact_scolaire' => ['in:Oui,Non'],
        'accompagnement_psychologique' => ['in:Oui,Non'],
        'soutien_social' => ['in:Oui,Non'],
        'famille_informee' => ['in:Oui,Non'],
        'date_prochaine_consultation' => ['date']
    ],
    6 => [
        'vaccination_data' => ['string']
    ],
    7 => [
        'plan_suivi_personnalise' => ['string', 'max:100']
    ],
    8 => [
        'examens_avant_consultation' => ['array'],
        'education_therapeutique' => ['in:Oui,Non']
    ],
    9 => [
        'commentaires' => ['string']
    ]
];

// Get rules for current step
$rules = $validationRules[$currentStep] ?? [];

// Validate input
$validator = new Validator();
if (!$validator->validate($input, $rules)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Données invalides',
        'errors' => $validator->getErrors()
    ]);
    exit;
}

// Sanitize input
$sanitizedData = Sanitizer::sanitize($input);

// Special handling for phone numbers
if (isset($sanitizedData['emergency_contact_phone'])) {
    $sanitizedData['emergency_contact_phone'] = Sanitizer::sanitizePhone($sanitizedData['emergency_contact_phone']);
}

// Special handling for arrays
if (isset($sanitizedData['examens_avant_consultation']) && is_array($sanitizedData['examens_avant_consultation'])) {
    $sanitizedData['examens_avant_consultation'] = json_encode(array_filter($sanitizedData['examens_avant_consultation']));
}

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    $db->beginTransaction();
    
    // If no consultation ID, create new consultation record
    if (!$consultationId) {
        $stmt = $pdo->prepare("INSERT INTO consultations (created_at, updated_at) VALUES (NOW(), NOW())");
        $stmt->execute();
        $consultationId = $pdo->lastInsertId();
        $_SESSION['consultation_id'] = $consultationId;
    }
    
    // Set default value "RAS" for empty or missing fields
    foreach ($sanitizedData as $key => $value) {
        if ($value === null || $value === '' || (is_array($value) && count($value) === 0)) {
            $sanitizedData[$key] = 'RAS';
        }
    }
    
    // Save data to appropriate table
    switch ($targetTable) {
        case 'consultations':
            $allowedFields = ['fosa', 'region', 'district', 'diagnostic_date', 'ipp', 'personnel', 
                             'referred', 'referred_from', 'referred_for', 'evolution',
                             'full_name', 'age', 'birth_date', 'sex', 'address', 'emergency_contact_name',
                             'emergency_contact_relation', 'emergency_contact_phone', 'lives_with',
                             'insurance', 'support_group', 'group_name', 'parents', 'sibling_rank',
                             'examens_avant_consultation', 'commentaires'];
            
            $updateFields = [];
            $updateValues = [];
            
            foreach ($allowedFields as $field) {
                if (isset($sanitizedData[$field])) {
                    $updateFields[] = "$field = ?";
                    $updateValues[] = $sanitizedData[$field];
                }
            }
            
            if (!empty($updateFields)) {
                $updateValues[] = $consultationId;
                $sql = "UPDATE consultations SET " . implode(', ', $updateFields) . ", updated_at = NOW() WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($updateValues);
            }
            break;
            
        case 'consultation_treatments':
            $allowedFields = ['hydroxyurea', 'tolerance', 'hydroxyurea_reasons', 'hydroxyurea_dosage',
                             'folic_acid', 'penicillin', 'regular_transfusion', 'transfusion_type',
                             'transfusion_frequency', 'last_transfusion_date', 'other_treatments'];
            
            $insertFields = ['consultation_id'];
            $insertValues = [$consultationId];
            $updateFields = [];
            $updateValues = [];
            
            foreach ($allowedFields as $field) {
                if (isset($sanitizedData[$field])) {
                    $insertFields[] = $field;
                    $insertValues[] = $sanitizedData[$field];
                    $updateFields[] = "$field = VALUES($field)";
                }
            }
            
            if (count($insertFields) > 1) {
                $placeholders = str_repeat('?,', count($insertValues) - 1) . '?';
                $sql = "INSERT INTO consultation_treatments (" . implode(', ', $insertFields) . ") 
                       VALUES ($placeholders) 
                       ON DUPLICATE KEY UPDATE " . implode(', ', $updateFields) . ", updated_at = NOW()";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($insertValues);
            }
            break;
            
        case 'consultation_exams':
            $allowedFields = ['sickle_type', 'diagnosis_age', 'diagnosis_circumstance', 'family_history', 'other_medical_history', 'previous_surgeries', 'allergies', 'vocs', 'hospitalizations'];
            
            $insertFields = ['consultation_id'];
            $insertValues = [$consultationId];
            $updateFields = [];
            
            foreach ($allowedFields as $field) {
                if (isset($sanitizedData[$field])) {
                    $insertFields[] = $field;
                    $insertValues[] = $sanitizedData[$field];
                    $updateFields[] = "$field = VALUES($field)";
                }
            }
            
            if (count($insertFields) > 1) {
                $placeholders = str_repeat('?,', count($insertValues) - 1) . '?';
                $sql = "INSERT INTO consultation_exams (" . implode(', ', $insertFields) . ") 
                       VALUES ($placeholders) 
                       ON DUPLICATE KEY UPDATE " . implode(', ', $updateFields) . ", updated_at = NOW()";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($insertValues);
            }
            break;
            
        case 'consultation_observations':
            $allowedFields = ['impact_scolaire', 'accompagnement_psychologique', 'soutien_social',
                             'famille_informee', 'date_prochaine_consultation', 'plan_suivi_personnalise',
                             'education_therapeutique'];
            
            $insertFields = ['consultation_id'];
            $insertValues = [$consultationId];
            $updateFields = [];
            
            foreach ($allowedFields as $field) {
                if (isset($sanitizedData[$field])) {
                    $insertFields[] = $field;
                    $insertValues[] = $sanitizedData[$field];
                    $updateFields[] = "$field = VALUES($field)";
                }
            }
            
            if (count($insertFields) > 1) {
                $placeholders = str_repeat('?,', count($insertValues) - 1) . '?';
                $sql = "INSERT INTO consultation_observations (" . implode(', ', $insertFields) . ") 
                       VALUES ($placeholders) 
                       ON DUPLICATE KEY UPDATE " . implode(', ', $updateFields) . ", updated_at = NOW()";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($insertValues);
            }
            break;
            
        case 'consultation_vaccinations':
            if (isset($sanitizedData['vaccination_data'])) {
                $sql = "INSERT INTO consultation_vaccinations (consultation_id, vaccination_data) 
                       VALUES (?, ?) 
                       ON DUPLICATE KEY UPDATE vaccination_data = VALUES(vaccination_data), updated_at = NOW()";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$consultationId, $sanitizedData['vaccination_data']]);
            }
            break;
    }
    
    $db->commit();
    
    // Save form data in session for resuming
    $_SESSION['consultation_form_data'][$currentStep] = $sanitizedData;
    $_SESSION['current_step'] = $currentStep;
    
    echo json_encode([
        'success' => true,
        'consultation_id' => $consultationId,
        'step' => $currentStep,
        'message' => 'Données sauvegardées avec succès'
    ]);
    
} catch (Exception $e) {
    $db->rollback();
    Logger::error('Erreur lors de la sauvegarde de l\'étape', [
        'step' => $currentStep,
        'consultation_id' => $consultationId,
        'error' => $e->getMessage()
    ]);
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la sauvegarde des données'
    ]);
}
