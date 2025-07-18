<?php
// save_consultation.php - Handles final form submission and generates Word document

require_once __DIR__ . '/vendor/autoload.php';

use App\Database;
use App\Validator;
use App\Sanitizer;
use App\Logger;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

// Security headers
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

if (!$consultationId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de consultation manquant']);
    exit;
}

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    // Fetch all consultation data
    $consultationData = [];
    
    // Get main consultation data
    $stmt = $pdo->prepare("SELECT * FROM consultations WHERE id = ?");
    $stmt->execute([$consultationId]);
    $consultationData['consultation'] = $stmt->fetch();
    
    if (!$consultationData['consultation']) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Consultation non trouvée']);
        exit;
    }
    
    // Get treatments data
    $stmt = $pdo->prepare("SELECT * FROM consultation_treatments WHERE consultation_id = ?");
    $stmt->execute([$consultationId]);
    $consultationData['treatments'] = $stmt->fetch() ?: [];
    
    // Get exams data
    $stmt = $pdo->prepare("SELECT * FROM consultation_exams WHERE consultation_id = ?");
    $stmt->execute([$consultationId]);
    $consultationData['exams'] = $stmt->fetch() ?: [];
    
    // Get observations data
    $stmt = $pdo->prepare("SELECT * FROM consultation_observations WHERE consultation_id = ?");
    $stmt->execute([$consultationId]);
    $consultationData['observations'] = $stmt->fetch() ?: [];
    
    // Get vaccinations data
    $stmt = $pdo->prepare("SELECT * FROM consultation_vaccinations WHERE consultation_id = ?");
    $stmt->execute([$consultationId]);
    $vaccinationRow = $stmt->fetch();
    $consultationData['vaccinations'] = $vaccinationRow ? json_decode($vaccinationRow['vaccination_data'], true) : [];
    
    // Generate Word document
    $phpWord = new PhpWord();
    $section = $phpWord->addSection();
    
    // Add title
    $titleStyle = ['name' => 'Arial', 'size' => 16, 'bold' => true, 'color' => 'D32F2F'];
    $section->addText('Rapport de Consultation Drepadata', $titleStyle, ['alignment' => 'center']);
    $section->addTextBreak(1);
    
    // Add patient information
    $headerStyle = ['name' => 'Arial', 'size' => 12, 'bold' => true];
    $textStyle = ['name' => 'Arial', 'size' => 11];
    
    $section->addText('INFORMATIONS PATIENT', $headerStyle);
    $section->addText('Nom et Prénom: ' . ($consultationData['consultation']['full_name'] ?? 'N/A'), $textStyle);
    $section->addText('Date de génération: ' . date('d/m/Y H:i'), $textStyle);
    $section->addTextBreak(1);
    
    // Administrative Information
    $section->addText('1. INFORMATIONS ADMINISTRATIVES', $headerStyle);
    $adminFields = [
        'FOSA' => 'fosa',
        'Région' => 'region',
        'District' => 'district',
        'Date du diagnostic' => 'diagnostic_date',
        'IPP' => 'ipp',
        'Personnel' => 'personnel',
        'Référé' => 'referred',
        'Référé de' => 'referred_from',
        'Référé pour' => 'referred_for',
        'Evolution' => 'evolution'
    ];
    
    foreach ($adminFields as $label => $field) {
        $value = $consultationData['consultation'][$field] ?? 'N/A';
        if ($value && $value !== 'N/A') {
            $section->addText("$label: $value", $textStyle);
        }
    }
    $section->addTextBreak(1);
    
    // Demographics
    $section->addText('2. DONNÉES DÉMOGRAPHIQUES', $headerStyle);
    $demoFields = [
        'Age' => 'age',
        'Date de naissance' => 'birth_date',
        'Sexe' => 'sex',
        'Adresse' => 'address',
        'Contact d\'urgence' => 'emergency_contact_name',
        'Relation' => 'emergency_contact_relation',
        'Téléphone' => 'emergency_contact_phone',
        'Vit avec' => 'lives_with',
        'Assurance' => 'insurance',
        'Groupe de soutien' => 'support_group',
        'Nom du groupe' => 'group_name',
        'Parents biologiques' => 'parents',
        'Rang fratrie' => 'sibling_rank'
    ];
    
    foreach ($demoFields as $label => $field) {
        $value = $consultationData['consultation'][$field] ?? 'N/A';
        if ($value && $value !== 'N/A') {
            if ($field === 'emergency_contact_phone' && $value) {
                $value = '+237' . $value;
            }
            $section->addText("$label: $value", $textStyle);
        }
    }
    $section->addTextBreak(1);
    
    // Treatments
    if (!empty($consultationData['treatments'])) {
        $section->addText('3. TRAITEMENTS EN COURS', $headerStyle);
        $treatmentFields = [
            'Hydroxyurée' => 'hydroxyurea',
            'Tolérance' => 'tolerance',
            'Raisons non-utilisation' => 'hydroxyurea_reasons',
            'Posologie hydroxyurée' => 'hydroxyurea_dosage',
            'Acide folique' => 'folic_acid',
            'Pénicilline' => 'penicillin',
            'Transfusions régulières' => 'regular_transfusion',
            'Type transfusion' => 'transfusion_type',
            'Fréquence transfusion' => 'transfusion_frequency',
            'Dernière transfusion' => 'last_transfusion_date',
            'Autres traitements' => 'other_treatments'
        ];
        
        foreach ($treatmentFields as $label => $field) {
            $value = $consultationData['treatments'][$field] ?? 'N/A';
            if ($value && $value !== 'N/A') {
                $section->addText("$label: $value", $textStyle);
            }
        }
        $section->addTextBreak(1);
    }
    
    // Medical History
    if (!empty($consultationData['exams'])) {
        $section->addText('4. ANTÉCÉDENTS MÉDICAUX', $headerStyle);
        $examFields = [
            'Type drépanocytose' => 'sickle_type',
            'Age au diagnostic' => 'diagnosis_age',
            'Crises vaso-occlusives' => 'vocs',
            'Hospitalisations' => 'hospitalizations'
        ];
        
        foreach ($examFields as $label => $field) {
            $value = $consultationData['exams'][$field] ?? 'N/A';
            if ($value && $value !== 'N/A') {
                $section->addText("$label: $value", $textStyle);
            }
        }
        $section->addTextBreak(1);
    }
    
    // Observations
    if (!empty($consultationData['observations'])) {
        $section->addText('5. SUIVI PSYCHOSOCIAL', $headerStyle);
        $obsFields = [
            'Impact scolaire' => 'impact_scolaire',
            'Accompagnement psychologique' => 'accompagnement_psychologique',
            'Soutien social' => 'soutien_social',
            'Famille informée' => 'famille_informee',
            'Plan de suivi' => 'plan_suivi_personnalise',
            'Prochaine consultation' => 'date_prochaine_consultation',
            'Éducation thérapeutique' => 'education_therapeutique'
        ];
        
        foreach ($obsFields as $label => $field) {
            $value = $consultationData['observations'][$field] ?? 'N/A';
            if ($value && $value !== 'N/A') {
                $section->addText("$label: $value", $textStyle);
            }
        }
        $section->addTextBreak(1);
    }
    
    // Vaccinations
    if (!empty($consultationData['vaccinations'])) {
        $section->addText('6. VACCINATIONS', $headerStyle);
        if (is_array($consultationData['vaccinations'])) {
            foreach ($consultationData['vaccinations'] as $vaccine => $data) {
                if (is_array($data)) {
                    $section->addText("Vaccin: $vaccine", $textStyle);
                    if (isset($data['received'])) {
                        $section->addText("Reçu: " . $data['received'], $textStyle);
                    }
                    if (isset($data['date'])) {
                        $section->addText("Date: " . $data['date'], $textStyle);
                    }
                    $section->addTextBreak(0.5);
                }
            }
        }
        $section->addTextBreak(1);
    }
    
    // Examinations
    if (!empty($consultationData['consultation']['examens_avant_consultation'])) {
        $section->addText('7. EXAMENS À RÉALISER', $headerStyle);
        $examens = json_decode($consultationData['consultation']['examens_avant_consultation'], true);
        if (is_array($examens)) {
            foreach ($examens as $examen) {
                if ($examen) {
                    $section->addText("• $examen", $textStyle);
                }
            }
        }
        $section->addTextBreak(1);
    }
    
    // Comments
    if (!empty($consultationData['consultation']['commentaires'])) {
        $section->addText('8. COMMENTAIRES', $headerStyle);
        $section->addText($consultationData['consultation']['commentaires'], $textStyle);
        $section->addTextBreak(1);
    }
    
    // Add footer
    $section->addText('Document généré par Drepadata Consultation App', ['name' => 'Arial', 'size' => 9, 'italic' => true], ['alignment' => 'center']);
    
    // Generate filename
    $patientName = $consultationData['consultation']['full_name'] ?? 'Patient';
    $safePatientName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $patientName);
    $filename = "consultation_report_{$safePatientName}_" . date('Y-m-d_H-i-s') . '.docx';
    
    // Save to temporary file
    $tempFile = tempnam(sys_get_temp_dir(), 'consultation_');
    $writer = IOFactory::createWriter($phpWord, 'Word2007');
    $writer->save($tempFile);
    
    // Set headers for download
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($tempFile));
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    
    // Output file
    readfile($tempFile);
    
    // Clean up
    unlink($tempFile);
    
    // Clear session after successful download
    unset($_SESSION['consultation_form_data']);
    unset($_SESSION['consultation_id']);
    unset($_SESSION['current_step']);
    
    Logger::info('Consultation report generated successfully', [
        'consultation_id' => $consultationId,
        'patient_name' => $patientName,
        'filename' => $filename
    ]);
    
    exit;
    
} catch (Exception $e) {
    Logger::error('Error generating consultation report', [
        'consultation_id' => $consultationId,
        'error' => $e->getMessage()
    ]);
    
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la génération du rapport']);
}