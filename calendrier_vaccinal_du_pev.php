<?php
// calendrier_vaccinal_du_pev.php - Vaccination calendar page

require_once __DIR__ . '/vendor/autoload.php';

use App\Database;
use App\Logger;

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

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get parameters
$patientName = $_GET['patient_name'] ?? '';
$consultationId = (int)($_GET['consultation_id'] ?? 0);

// Load existing vaccination data if available
$vaccinationData = $_SESSION['vaccination_calendar'] ?? [];

// Try to load from database if consultation_id exists
if ($consultationId && empty($vaccinationData)) {
    try {
        $db = Database::getInstance();
        $pdo = $db->getConnection();
        
        $stmt = $pdo->prepare("SELECT vaccination_data FROM consultation_vaccinations WHERE consultation_id = ?");
        $stmt->execute([$consultationId]);
        $result = $stmt->fetch();
        
        if ($result && $result['vaccination_data']) {
            $vaccinationData = json_decode($result['vaccination_data'], true) ?: [];
        }
    } catch (Exception $e) {
        Logger::error('Error loading vaccination data', [
            'consultation_id' => $consultationId,
            'error' => $e->getMessage()
        ]);
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendrier Vaccinal du PEV - Drepadata</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        .vaccination-table {
            border-collapse: collapse;
            width: 100%;
            font-size: 14px;
        }
        
        .vaccination-table th,
        .vaccination-table td {
            border: 1px solid #d1d5db;
            padding: 8px;
            text-align: left;
            vertical-align: top;
        }
        
        .vaccination-table th {
            background-color: #f3f4f6;
            font-weight: bold;
        }
        
        .period-cell {
            background-color: #fef2f2;
            font-weight: bold;
            text-align: center;
            vertical-align: middle;
        }
        
        .checkbox-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .checkbox-group label {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 12px;
        }
        
        .radio-group {
            display: flex;
            gap: 8px;
        }
        
        .radio-group label {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 12px;
        }
        
        .date-input {
            width: 100%;
            padding: 4px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 12px;
        }
        
        .save-button {
            background-color: #ef4444;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .save-button:hover {
            background-color: #dc2626;
        }
        
        .back-button {
            background-color: #6b7280;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .back-button:hover {
            background-color: #4b5563;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto p-8 max-w-7xl">
        <div class="bg-white rounded-lg shadow-md p-8">
            <h1 class="text-3xl font-bold text-center mb-6 text-red-600">Calendrier Vaccinal du PEV</h1>
            
            <?php if ($patientName): ?>
                <div class="mb-6 text-center">
                    <p class="text-lg font-medium text-gray-700">Patient: <?php echo htmlspecialchars($patientName, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            <?php endif; ?>
            
            <div id="message" class="hidden mb-6 p-4 rounded-md"></div>
            
            <form id="vaccination-form" method="POST" action="save_vaccination.php">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="consultation_id" value="<?php echo $consultationId; ?>">
                
                <div class="overflow-x-auto">
                    <table class="vaccination-table">
                        <thead>
                            <tr>
                                <th>Période</th>
                                <th>Vaccin</th>
                                <th>Voie d'administration</th>
                                <th>Reçu</th>
                                <th>Date</th>
                                <th>Observations</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Naissance -->
                            <tr>
                                <td rowspan="2" class="period-cell">Naissance</td>
                                <td>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="vaccines[bcg][selected]" value="1" class="mr-2" <?php echo isset($vaccinationData['bcg']['selected']) ? 'checked' : ''; ?>>
                                        BCG
                                    </label>
                                </td>
                                <td>
                                    <div class="checkbox-group">
                                        <label><input type="checkbox" name="vaccines[bcg][administration][]" value="Intra dermique" <?php echo (isset($vaccinationData['bcg']['administration']) && in_array('Intra dermique', $vaccinationData['bcg']['administration'])) ? 'checked' : ''; ?>> Intra dermique</label>
                                        <label><input type="checkbox" name="vaccines[bcg][administration][]" value="Orale" <?php echo (isset($vaccinationData['bcg']['administration']) && in_array('Orale', $vaccinationData['bcg']['administration'])) ? 'checked' : ''; ?>> Orale</label>
                                    </div>
                                </td>
                                <td>
                                    <div class="radio-group">
                                        <label><input type="radio" name="vaccines[bcg][received]" value="Oui" <?php echo (isset($vaccinationData['bcg']['received']) && $vaccinationData['bcg']['received'] === 'Oui') ? 'checked' : ''; ?>> Oui</label>
                                        <label><input type="radio" name="vaccines[bcg][received]" value="Non" <?php echo (isset($vaccinationData['bcg']['received']) && $vaccinationData['bcg']['received'] === 'Non') ? 'checked' : ''; ?>> Non</label>
                                    </div>
                                </td>
                                <td>
                                    <input type="date" name="vaccines[bcg][date]" class="date-input" value="<?php echo $vaccinationData['bcg']['date'] ?? ''; ?>">
                                </td>
                                <td>
                                    <input type="text" name="vaccines[bcg][observations]" class="date-input" value="<?php echo htmlspecialchars($vaccinationData['bcg']['observations'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                </td>
                            </tr>
                            
                            <tr>
                                <td>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="vaccines[vpo0][selected]" value="1" class="mr-2" <?php echo isset($vaccinationData['vpo0']['selected']) ? 'checked' : ''; ?>>
                                        VPO-0
                                    </label>
                                </td>
                                <td>
                                    <div class="checkbox-group">
                                        <label><input type="checkbox" name="vaccines[vpo0][administration][]" value="Orale" <?php echo (isset($vaccinationData['vpo0']['administration']) && in_array('Orale', $vaccinationData['vpo0']['administration'])) ? 'checked' : ''; ?>> Orale</label>
                                    </div>
                                </td>
                                <td>
                                    <div class="radio-group">
                                        <label><input type="radio" name="vaccines[vpo0][received]" value="Oui" <?php echo (isset($vaccinationData['vpo0']['received']) && $vaccinationData['vpo0']['received'] === 'Oui') ? 'checked' : ''; ?>> Oui</label>
                                        <label><input type="radio" name="vaccines[vpo0][received]" value="Non" <?php echo (isset($vaccinationData['vpo0']['received']) && $vaccinationData['vpo0']['received'] === 'Non') ? 'checked' : ''; ?>> Non</label>
                                    </div>
                                </td>
                                <td>
                                    <input type="date" name="vaccines[vpo0][date]" class="date-input" value="<?php echo $vaccinationData['vpo0']['date'] ?? ''; ?>">
                                </td>
                                <td>
                                    <input type="text" name="vaccines[vpo0][observations]" class="date-input" value="<?php echo htmlspecialchars($vaccinationData['vpo0']['observations'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                </td>
                            </tr>
                            
                            <!-- 6 Semaines -->
                            <tr>
                                <td rowspan="4" class="period-cell">6 Semaines</td>
                                <td>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="vaccines[dtc_hep_hib_1][selected]" value="1" class="mr-2" <?php echo isset($vaccinationData['dtc_hep_hib_1']['selected']) ? 'checked' : ''; ?>>
                                        DTC-HepB+Hib 1
                                    </label>
                                </td>
                                <td>
                                    <div class="checkbox-group">
                                        <label><input type="checkbox" name="vaccines[dtc_hep_hib_1][administration][]" value="Intra musculaire" <?php echo (isset($vaccinationData['dtc_hep_hib_1']['administration']) && in_array('Intra musculaire', $vaccinationData['dtc_hep_hib_1']['administration'])) ? 'checked' : ''; ?>> Intra musculaire</label>
                                    </div>
                                </td>
                                <td>
                                    <div class="radio-group">
                                        <label><input type="radio" name="vaccines[dtc_hep_hib_1][received]" value="Oui" <?php echo (isset($vaccinationData['dtc_hep_hib_1']['received']) && $vaccinationData['dtc_hep_hib_1']['received'] === 'Oui') ? 'checked' : ''; ?>> Oui</label>
                                        <label><input type="radio" name="vaccines[dtc_hep_hib_1][received]" value="Non" <?php echo (isset($vaccinationData['dtc_hep_hib_1']['received']) && $vaccinationData['dtc_hep_hib_1']['received'] === 'Non') ? 'checked' : ''; ?>> Non</label>
                                    </div>
                                </td>
                                <td>
                                    <input type="date" name="vaccines[dtc_hep_hib_1][date]" class="date-input" value="<?php echo $vaccinationData['dtc_hep_hib_1']['date'] ?? ''; ?>">
                                </td>
                                <td>
                                    <input type="text" name="vaccines[dtc_hep_hib_1][observations]" class="date-input" value="<?php echo htmlspecialchars($vaccinationData['dtc_hep_hib_1']['observations'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                </td>
                            </tr>
                            
                            <tr>
                                <td>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="vaccines[pneumo_13_1][selected]" value="1" class="mr-2" <?php echo isset($vaccinationData['pneumo_13_1']['selected']) ? 'checked' : ''; ?>>
                                        Pneumo 13-1
                                    </label>
                                </td>
                                <td>
                                    <div class="checkbox-group">
                                        <label><input type="checkbox" name="vaccines[pneumo_13_1][administration][]" value="Intra musculaire" <?php echo (isset($vaccinationData['pneumo_13_1']['administration']) && in_array('Intra musculaire', $vaccinationData['pneumo_13_1']['administration'])) ? 'checked' : ''; ?>> Intra musculaire</label>
                                    </div>
                                </td>
                                <td>
                                    <div class="radio-group">
                                        <label><input type="radio" name="vaccines[pneumo_13_1][received]" value="Oui" <?php echo (isset($vaccinationData['pneumo_13_1']['received']) && $vaccinationData['pneumo_13_1']['received'] === 'Oui') ? 'checked' : ''; ?>> Oui</label>
                                        <label><input type="radio" name="vaccines[pneumo_13_1][received]" value="Non" <?php echo (isset($vaccinationData['pneumo_13_1']['received']) && $vaccinationData['pneumo_13_1']['received'] === 'Non') ? 'checked' : ''; ?>> Non</label>
                                    </div>
                                </td>
                                <td>
                                    <input type="date" name="vaccines[pneumo_13_1][date]" class="date-input" value="<?php echo $vaccinationData['pneumo_13_1']['date'] ?? ''; ?>">
                                </td>
                                <td>
                                    <input type="text" name="vaccines[pneumo_13_1][observations]" class="date-input" value="<?php echo htmlspecialchars($vaccinationData['pneumo_13_1']['observations'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                </td>
                            </tr>
                            
                            <tr>
                                <td>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="vaccines[vpo_1][selected]" value="1" class="mr-2" <?php echo isset($vaccinationData['vpo_1']['selected']) ? 'checked' : ''; ?>>
                                        VPO-1
                                    </label>
                                </td>
                                <td>
                                    <div class="checkbox-group">
                                        <label><input type="checkbox" name="vaccines[vpo_1][administration][]" value="Orale" <?php echo (isset($vaccinationData['vpo_1']['administration']) && in_array('Orale', $vaccinationData['vpo_1']['administration'])) ? 'checked' : ''; ?>> Orale</label>
                                    </div>
                                </td>
                                <td>
                                    <div class="radio-group">
                                        <label><input type="radio" name="vaccines[vpo_1][received]" value="Oui" <?php echo (isset($vaccinationData['vpo_1']['received']) && $vaccinationData['vpo_1']['received'] === 'Oui') ? 'checked' : ''; ?>> Oui</label>
                                        <label><input type="radio" name="vaccines[vpo_1][received]" value="Non" <?php echo (isset($vaccinationData['vpo_1']['received']) && $vaccinationData['vpo_1']['received'] === 'Non') ? 'checked' : ''; ?>> Non</label>
                                    </div>
                                </td>
                                <td>
                                    <input type="date" name="vaccines[vpo_1][date]" class="date-input" value="<?php echo $vaccinationData['vpo_1']['date'] ?? ''; ?>">
                                </td>
                                <td>
                                    <input type="text" name="vaccines[vpo_1][observations]" class="date-input" value="<?php echo htmlspecialchars($vaccinationData['vpo_1']['observations'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                </td>
                            </tr>
                            
                            <tr>
                                <td>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="vaccines[rota_1][selected]" value="1" class="mr-2" <?php echo isset($vaccinationData['rota_1']['selected']) ? 'checked' : ''; ?>>
                                        ROTA-1
                                    </label>
                                </td>
                                <td>
                                    <div class="checkbox-group">
                                        <label><input type="checkbox" name="vaccines[rota_1][administration][]" value="Orale" <?php echo (isset($vaccinationData['rota_1']['administration']) && in_array('Orale', $vaccinationData['rota_1']['administration'])) ? 'checked' : ''; ?>> Orale</label>
                                    </div>
                                </td>
                                <td>
                                    <div class="radio-group">
                                        <label><input type="radio" name="vaccines[rota_1][received]" value="Oui" <?php echo (isset($vaccinationData['rota_1']['received']) && $vaccinationData['rota_1']['received'] === 'Oui') ? 'checked' : ''; ?>> Oui</label>
                                        <label><input type="radio" name="vaccines[rota_1][received]" value="Non" <?php echo (isset($vaccinationData['rota_1']['received']) && $vaccinationData['rota_1']['received'] === 'Non') ? 'checked' : ''; ?>> Non</label>
                                    </div>
                                </td>
                                <td>
                                    <input type="date" name="vaccines[rota_1][date]" class="date-input" value="<?php echo $vaccinationData['rota_1']['date'] ?? ''; ?>">
                                </td>
                                <td>
                                    <input type="text" name="vaccines[rota_1][observations]" class="date-input" value="<?php echo htmlspecialchars($vaccinationData['rota_1']['observations'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                </td>
                            </tr>
                            
                            <!-- 10 Semaines -->
                            <tr>
                                <td rowspan="4" class="period-cell">10 Semaines</td>
                                <td>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="vaccines[dtc_hep_hib_2][selected]" value="1" class="mr-2" <?php echo isset($vaccinationData['dtc_hep_hib_2']['selected']) ? 'checked' : ''; ?>>
                                        DTC-HepB+Hib 2
                                    </label>
                                </td>
                                <td>
                                    <div class="checkbox-group">
                                        <label><input type="checkbox" name="vaccines[dtc_hep_hib_2][administration][]" value="Intra musculaire" <?php echo (isset($vaccinationData['dtc_hep_hib_2']['administration']) && in_array('Intra musculaire', $vaccinationData['dtc_hep_hib_2']['administration'])) ? 'checked' : ''; ?>> Intra musculaire</label>
                                    </div>
                                </td>
                                <td>
                                    <div class="radio-group">
                                        <label><input type="radio" name="vaccines[dtc_hep_hib_2][received]" value="Oui" <?php echo (isset($vaccinationData['dtc_hep_hib_2']['received']) && $vaccinationData['dtc_hep_hib_2']['received'] === 'Oui') ? 'checked' : ''; ?>> Oui</label>
                                        <label><input type="radio" name="vaccines[dtc_hep_hib_2][received]" value="Non" <?php echo (isset($vaccinationData['dtc_hep_hib_2']['received']) && $vaccinationData['dtc_hep_hib_2']['received'] === 'Non') ? 'checked' : ''; ?>> Non</label>
                                    </div>
                                </td>
                                <td>
                                    <input type="date" name="vaccines[dtc_hep_hib_2][date]" class="date-input" value="<?php echo $vaccinationData['dtc_hep_hib_2']['date'] ?? ''; ?>">
                                </td>
                                <td>
                                    <input type="text" name="vaccines[dtc_hep_hib_2][observations]" class="date-input" value="<?php echo htmlspecialchars($vaccinationData['dtc_hep_hib_2']['observations'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                </td>
                            </tr>
                            
                            <tr>
                                <td>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="vaccines[pneumo_13_2][selected]" value="1" class="mr-2" <?php echo isset($vaccinationData['pneumo_13_2']['selected']) ? 'checked' : ''; ?>>
                                        Pneumo 13-2
                                    </label>
                                </td>
                                <td>
                                    <div class="checkbox-group">
                                        <label><input type="checkbox" name="vaccines[pneumo_13_2][administration][]" value="Intra musculaire" <?php echo (isset($vaccinationData['pneumo_13_2']['administration']) && in_array('Intra musculaire', $vaccinationData['pneumo_13_2']['administration'])) ? 'checked' : ''; ?>> Intra musculaire</label>
                                    </div>
                                </td>
                                <td>
                                    <div class="radio-group">
                                        <label><input type="radio" name="vaccines[pneumo_13_2][received]" value="Oui" <?php echo (isset($vaccinationData['pneumo_13_2']['received']) && $vaccinationData['pneumo_13_2']['received'] === 'Oui') ? 'checked' : ''; ?>> Oui</label>
                                        <label><input type="radio" name="vaccines[pneumo_13_2][received]" value="Non" <?php echo (isset($vaccinationData['pneumo_13_2']['received']) && $vaccinationData['pneumo_13_2']['received'] === 'Non') ? 'checked' : ''; ?>> Non</label>
                                    </div>
                                </td>
                                <td>
                                    <input type="date" name="vaccines[pneumo_13_2][date]" class="date-input" value="<?php echo $vaccinationData['pneumo_13_2']['date'] ?? ''; ?>">
                                </td>
                                <td>
                                    <input type="text" name="vaccines[pneumo_13_2][observations]" class="date-input" value="<?php echo htmlspecialchars($vaccinationData['pneumo_13_2']['observations'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                </td>
                            </tr>
                            
                            <tr>
                                <td>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="vaccines[vpo_2][selected]" value="1" class="mr-2" <?php echo isset($vaccinationData['vpo_2']['selected']) ? 'checked' : ''; ?>>
                                        VPO-2
                                    </label>
                                </td>
                                <td>
                                    <div class="checkbox-group">
                                        <label><input type="checkbox" name="vaccines[vpo_2][administration][]" value="Orale" <?php echo (isset($vaccinationData['vpo_2']['administration']) && in_array('Orale', $vaccinationData['vpo_2']['administration'])) ? 'checked' : ''; ?>> Orale</label>
                                    </div>
                                </td>
                                <td>
                                    <div class="radio-group">
                                        <label><input type="radio" name="vaccines[vpo_2][received]" value="Oui" <?php echo (isset($vaccinationData['vpo_2']['received']) && $vaccinationData['vpo_2']['received'] === 'Oui') ? 'checked' : ''; ?>> Oui</label>
                                        <label><input type="radio" name="vaccines[vpo_2][received]" value="Non" <?php echo (isset($vaccinationData['vpo_2']['received']) && $vaccinationData['vpo_2']['received'] === 'Non') ? 'checked' : ''; ?>> Non</label>
                                    </div>
                                </td>
                                <td>
                                    <input type="date" name="vaccines[vpo_2][date]" class="date-input" value="<?php echo $vaccinationData['vpo_2']['date'] ?? ''; ?>">
                                </td>
                                <td>
                                    <input type="text" name="vaccines[vpo_2][observations]" class="date-input" value="<?php echo htmlspecialchars($vaccinationData['vpo_2']['observations'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                </td>
                            </tr>
                            
                            <tr>
                                <td>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="vaccines[rota_2][selected]" value="1" class="mr-2" <?php echo isset($vaccinationData['rota_2']['selected']) ? 'checked' : ''; ?>>
                                        ROTA-2
                                    </label>
                                </td>
                                <td>
                                    <div class="checkbox-group">
                                        <label><input type="checkbox" name="vaccines[rota_2][administration][]" value="Orale" <?php echo (isset($vaccinationData['rota_2']['administration']) && in_array('Orale', $vaccinationData['rota_2']['administration'])) ? 'checked' : ''; ?>> Orale</label>
                                    </div>
                                </td>
                                <td>
                                    <div class="radio-group">
                                        <label><input type="radio" name="vaccines[rota_2][received]" value="Oui" <?php echo (isset($vaccinationData['rota_2']['received']) && $vaccinationData['rota_2']['received'] === 'Oui') ? 'checked' : ''; ?>> Oui</label>
                                        <label><input type="radio" name="vaccines[rota_2][received]" value="Non" <?php echo (isset($vaccinationData['rota_2']['received']) && $vaccinationData['rota_2']['received'] === 'Non') ? 'checked' : ''; ?>> Non</label>
                                    </div>
                                </td>
                                <td>
                                    <input type="date" name="vaccines[rota_2][date]" class="date-input" value="<?php echo $vaccinationData['rota_2']['date'] ?? ''; ?>">
                                </td>
                                <td>
                                    <input type="text" name="vaccines[rota_2][observations]" class="date-input" value="<?php echo htmlspecialchars($vaccinationData['rota_2']['observations'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                </td>
                            </tr>
                            
                            <!-- 14 Semaines -->
                            <tr>
                                <td rowspan="3" class="period-cell">14 Semaines</td>
                                <td>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="vaccines[dtc_hep_hib_3][selected]" value="1" class="mr-2" <?php echo isset($vaccinationData['dtc_hep_hib_3']['selected']) ? 'checked' : ''; ?>>
                                        DTC-HepB+Hib 3
                                    </label>
                                </td>
                                <td>
                                    <div class="checkbox-group">
                                        <label><input type="checkbox" name="vaccines[dtc_hep_hib_3][administration][]" value="Intra musculaire" <?php echo (isset($vaccinationData['dtc_hep_hib_3']['administration']) && in_array('Intra musculaire', $vaccinationData['dtc_hep_hib_3']['administration'])) ? 'checked' : ''; ?>> Intra musculaire</label>
                                    </div>
                                </td>
                                <td>
                                    <div class="radio-group">
                                        <label><input type="radio" name="vaccines[dtc_hep_hib_3][received]" value="Oui" <?php echo (isset($vaccinationData['dtc_hep_hib_3']['received']) && $vaccinationData['dtc_hep_hib_3']['received'] === 'Oui') ? 'checked' : ''; ?>> Oui</label>
                                        <label><input type="radio" name="vaccines[dtc_hep_hib_3][received]" value="Non" <?php echo (isset($vaccinationData['dtc_hep_hib_3']['received']) && $vaccinationData['dtc_hep_hib_3']['received'] === 'Non') ? 'checked' : ''; ?>> Non</label>
                                    </div>
                                </td>
                                <td>
                                    <input type="date" name="vaccines[dtc_hep_hib_3][date]" class="date-input" value="<?php echo $vaccinationData['dtc_hep_hib_3']['date'] ?? ''; ?>">
                                </td>
                                <td>
                                    <input type="text" name="vaccines[dtc_hep_hib_3][observations]" class="date-input" value="<?php echo htmlspecialchars($vaccinationData['dtc_hep_hib_3']['observations'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                </td>
                            </tr>
                            
                            <tr>
                                <td>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="vaccines[pneumo_13_3][selected]" value="1" class="mr-2" <?php echo isset($vaccinationData['pneumo_13_3']['selected']) ? 'checked' : ''; ?>>
                                        Pneumo 13-3
                                    </label>
                                </td>
                                <td>
                                    <div class="checkbox-group">
                                        <label><input type="checkbox" name="vaccines[pneumo_13_3][administration][]" value="Intra musculaire" <?php echo (isset($vaccinationData['pneumo_13_3']['administration']) && in_array('Intra musculaire', $vaccinationData['pneumo_13_3']['administration'])) ? 'checked' : ''; ?>> Intra musculaire</label>
                                    </div>
                                </td>
                                <td>
                                    <div class="radio-group">
                                        <label><input type="radio" name="vaccines[pneumo_13_3][received]" value="Oui" <?php echo (isset($vaccinationData['pneumo_13_3']['received']) && $vaccinationData['pneumo_13_3']['received'] === 'Oui') ? 'checked' : ''; ?>> Oui</label>
                                        <label><input type="radio" name="vaccines[pneumo_13_3][received]" value="Non" <?php echo (isset($vaccinationData['pneumo_13_3']['received']) && $vaccinationData['pneumo_13_3']['received'] === 'Non') ? 'checked' : ''; ?>> Non</label>
                                    </div>
                                </td>
                                <td>
                                    <input type="date" name="vaccines[pneumo_13_3][date]" class="date-input" value="<?php echo $vaccinationData['pneumo_13_3']['date'] ?? ''; ?>">
                                </td>
                                <td>
                                    <input type="text" name="vaccines[pneumo_13_3][observations]" class="date-input" value="<?php echo htmlspecialchars($vaccinationData['pneumo_13_3']['observations'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                </td>
                            </tr>
                            
                            <tr>
                                <td>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="vaccines[vpo_3][selected]" value="1" class="mr-2" <?php echo isset($vaccinationData['vpo_3']['selected']) ? 'checked' : ''; ?>>
                                        VPO-3
                                    </label>
                                </td>
                                <td>
                                    <div class="checkbox-group">
                                        <label><input type="checkbox" name="vaccines[vpo_3][administration][]" value="Orale" <?php echo (isset($vaccinationData['vpo_3']['administration']) && in_array('Orale', $vaccinationData['vpo_3']['administration'])) ? 'checked' : ''; ?>> Orale</label>
                                    </div>
                                </td>
                                <td>
                                    <div class="radio-group">
                                        <label><input type="radio" name="vaccines[vpo_3][received]" value="Oui" <?php echo (isset($vaccinationData['vpo_3']['received']) && $vaccinationData['vpo_3']['received'] === 'Oui') ? 'checked' : ''; ?>> Oui</label>
                                        <label><input type="radio" name="vaccines[vpo_3][received]" value="Non" <?php echo (isset($vaccinationData['vpo_3']['received']) && $vaccinationData['vpo_3']['received'] === 'Non') ? 'checked' : ''; ?>> Non</label>
                                    </div>
                                </td>
                                <td>
                                    <input type="date" name="vaccines[vpo_3][date]" class="date-input" value="<?php echo $vaccinationData['vpo_3']['date'] ?? ''; ?>">
                                </td>
                                <td>
                                    <input type="text" name="vaccines[vpo_3][observations]" class="date-input" value="<?php echo htmlspecialchars($vaccinationData['vpo_3']['observations'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                </td>
                            </tr>
                            
                            <!-- 9 Mois -->
                            <tr>
                                <td rowspan="2" class="period-cell">9 Mois</td>
                                <td>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="vaccines[rougeole_1][selected]" value="1" class="mr-2" <?php echo isset($vaccinationData['rougeole_1']['selected']) ? 'checked' : ''; ?>>
                                        Rougeole 1
                                    </label>
                                </td>
                                <td>
                                    <div class="checkbox-group">
                                        <label><input type="checkbox" name="vaccines[rougeole_1][administration][]" value="Sous cutanée" <?php echo (isset($vaccinationData['rougeole_1']['administration']) && in_array('Sous cutanée', $vaccinationData['rougeole_1']['administration'])) ? 'checked' : ''; ?>> Sous cutanée</label>
                                    </div>
                                </td>
                                <td>
                                    <div class="radio-group">
                                        <label><input type="radio" name="vaccines[rougeole_1][received]" value="Oui" <?php echo (isset($vaccinationData['rougeole_1']['received']) && $vaccinationData['rougeole_1']['received'] === 'Oui') ? 'checked' : ''; ?>> Oui</label>
                                        <label><input type="radio" name="vaccines[rougeole_1][received]" value="Non" <?php echo (isset($vaccinationData['rougeole_1']['received']) && $vaccinationData['rougeole_1']['received'] === 'Non') ? 'checked' : ''; ?>> Non</label>
                                    </div>
                                </td>
                                <td>
                                    <input type="date" name="vaccines[rougeole_1][date]" class="date-input" value="<?php echo $vaccinationData['rougeole_1']['date'] ?? ''; ?>">
                                </td>
                                <td>
                                    <input type="text" name="vaccines[rougeole_1][observations]" class="date-input" value="<?php echo htmlspecialchars($vaccinationData['rougeole_1']['observations'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                </td>
                            </tr>
                            
                            <tr>
                                <td>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="vaccines[fievre_jaune][selected]" value="1" class="mr-2" <?php echo isset($vaccinationData['fievre_jaune']['selected']) ? 'checked' : ''; ?>>
                                        Fièvre Jaune
                                    </label>
                                </td>
                                <td>
                                    <div class="checkbox-group">
                                        <label><input type="checkbox" name="vaccines[fievre_jaune][administration][]" value="Sous cutanée" <?php echo (isset($vaccinationData['fievre_jaune']['administration']) && in_array('Sous cutanée', $vaccinationData['fievre_jaune']['administration'])) ? 'checked' : ''; ?>> Sous cutanée</label>
                                    </div>
                                </td>
                                <td>
                                    <div class="radio-group">
                                        <label><input type="radio" name="vaccines[fievre_jaune][received]" value="Oui" <?php echo (isset($vaccinationData['fievre_jaune']['received']) && $vaccinationData['fievre_jaune']['received'] === 'Oui') ? 'checked' : ''; ?>> Oui</label>
                                        <label><input type="radio" name="vaccines[fievre_jaune][received]" value="Non" <?php echo (isset($vaccinationData['fievre_jaune']['received']) && $vaccinationData['fievre_jaune']['received'] === 'Non') ? 'checked' : ''; ?>> Non</label>
                                    </div>
                                </td>
                                <td>
                                    <input type="date" name="vaccines[fievre_jaune][date]" class="date-input" value="<?php echo $vaccinationData['fievre_jaune']['date'] ?? ''; ?>">
                                </td>
                                <td>
                                    <input type="text" name="vaccines[fievre_jaune][observations]" class="date-input" value="<?php echo htmlspecialchars($vaccinationData['fievre_jaune']['observations'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                </td>
                            </tr>
                            
                            <!-- 15 Mois -->
                            <tr>
                                <td class="period-cell">15 Mois</td>
                                <td>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="vaccines[rougeole_2][selected]" value="1" class="mr-2" <?php echo isset($vaccinationData['rougeole_2']['selected']) ? 'checked' : ''; ?>>
                                        Rougeole 2
                                    </label>
                                </td>
                                <td>
                                    <div class="checkbox-group">
                                        <label><input type="checkbox" name="vaccines[rougeole_2][administration][]" value="Sous cutanée" <?php echo (isset($vaccinationData['rougeole_2']['administration']) && in_array('Sous cutanée', $vaccinationData['rougeole_2']['administration'])) ? 'checked' : ''; ?>> Sous cutanée</label>
                                    </div>
                                </td>
                                <td>
                                    <div class="radio-group">
                                        <label><input type="radio" name="vaccines[rougeole_2][received]" value="Oui" <?php echo (isset($vaccinationData['rougeole_2']['received']) && $vaccinationData['rougeole_2']['received'] === 'Oui') ? 'checked' : ''; ?>> Oui</label>
                                        <label><input type="radio" name="vaccines[rougeole_2][received]" value="Non" <?php echo (isset($vaccinationData['rougeole_2']['received']) && $vaccinationData['rougeole_2']['received'] === 'Non') ? 'checked' : ''; ?>> Non</label>
                                    </div>
                                </td>
                                <td>
                                    <input type="date" name="vaccines[rougeole_2][date]" class="date-input" value="<?php echo $vaccinationData['rougeole_2']['date'] ?? ''; ?>">
                                </td>
                                <td>
                                    <input type="text" name="vaccines[rougeole_2][observations]" class="date-input" value="<?php echo htmlspecialchars($vaccinationData['rougeole_2']['observations'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-8 flex justify-between">
                    <a href="consultation.php?resume=1" class="back-button">
                        ← Retour au formulaire
                    </a>
                    <button type="submit" class="save-button">
                        Sauvegarder les vaccinations
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        document.getElementById('vaccination-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const messageDiv = document.getElementById('message');
            const formData = new FormData(this);
            
            try {
                const response = await fetch('save_vaccination.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    messageDiv.className = 'mb-6 p-4 rounded-md bg-green-100 border border-green-400 text-green-700';
                    messageDiv.textContent = 'Données de vaccination sauvegardées avec succès !';
                    messageDiv.classList.remove('hidden');
                    
                    // Scroll to top to show message
                    window.scrollTo(0, 0);
                } else {
                    messageDiv.className = 'mb-6 p-4 rounded-md bg-red-100 border border-red-400 text-red-700';
                    messageDiv.textContent = 'Erreur lors de la sauvegarde : ' + (result.message || 'Erreur inconnue');
                    messageDiv.classList.remove('hidden');
                }
            } catch (error) {
                messageDiv.className = 'mb-6 p-4 rounded-md bg-red-100 border border-red-400 text-red-700';
                messageDiv.textContent = 'Erreur de connexion : ' + error.message;
                messageDiv.classList.remove('hidden');
            }
        });
    </script>
</body>
</html>