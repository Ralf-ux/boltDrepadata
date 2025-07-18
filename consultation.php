<?php
// consultation.php - Multi-step consultation form page

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    'httponly' => true,
    'samesite' => 'Strict'
]);
session_start();

// Regenerate session ID on first load to prevent session fixation
if (!isset($_SESSION['initialized'])) {
    session_regenerate_id(true);
    $_SESSION['initialized'] = true;
}

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check for Composer dependencies
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    die('Missing dependencies. Please run composer install.');
}

// Load saved consultation and vaccination data if resume=1
if (isset($_GET['resume']) && $_GET['resume'] == '1') {
    // Load consultation form data from session or database (if implemented)
    $consultation_data = $_SESSION['consultation_form_data'] ?? [];

    // Load vaccination calendar data from session or database
    $vaccination_data = $_SESSION['vaccination_calendar'] ?? [];

    // Prefill form data in session for use in form fields
    $_SESSION['prefill_data'] = array_merge($consultation_data, ['vaccination_calendar' => $vaccination_data]);
}

// Display any error messages passed via query string
$error = $_GET['error'] ?? '';
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Formulaire de Consultation - Drepadata</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        .step {
            display: none;
        }

        form#consultation-form {
            position: relative;
            z-index: 1;
            background-color: #fef2f2;
        }

        .step.active {
            display: flex;
            height: auto;
            justify-content: flex-start;
            flex-direction: column;
            padding: 30px;
        }

        .step.active h2 {
            margin-bottom: 1rem;
        }

        .step.active > div.grid {
            width: 100%;
        }

        .progress-bar {
            width: 0%;
            transition: width 0.3s ease-in-out;
            background-color: #ef4444;
        }

        .required-asterisk {
            color: #ef4444;
        }

        input[type="text"],
        input[type="number"],
        input[type="date"],
        input[type="tel"],
        select,
        textarea {
            border: 2px solid #d1d5db;
            border-radius: 0.375rem;
            padding: 0.75rem;
            font-size: 1rem;
            outline: none;
            transition: border-color 0.3s ease;
        }

        input[type="text"]:focus,
        input[type="number"]:focus,
        input[type="date"]:focus,
        input[type="tel"]:focus,
        select:focus,
        textarea:focus {
            border-color: #ef4444;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.3);
        }

        .conditional-field {
            display: none;
        }

        .phone-input {
            display: flex;
            align-items: center;
        }

        .phone-prefix {
            background-color: #f3f4f6;
            padding: 0.75rem;
            border: 2px solid #ef4444;
            border-right: none;
            border-radius: 0.375rem 0 0 0.375rem;
            color: #374151;
        }

        .phone-number {
            border-radius: 0 0.375rem 0.375rem 0;
            flex-grow: 1;
            border: 2px solid #ef4444;
        }

        .checkbox-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .conditional-container {
            margin-top: 1rem;
            margin-left: 1rem;
        }

        button.bg-blue-600 {
            background-color: #ef4444;
        }

        button.bg-blue-600:hover {
            background-color: #dc2626;
        }

        button.bg-green-600 {
            background-color: #16a34a;
        }

        button.bg-green-600:hover {
            background-color: #15803d;
        }

        .animation-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 0;
            opacity: 0.25;
        }

        .dna-strand {
            stroke: #d32f2f;
            stroke-width: 3;
            fill: none;
            animation: dnaWave 10s ease-in-out infinite;
        }

        .blood-cell {
            fill: #b71c1c;
            opacity: 0.5;
            animation: float 15s ease-in-out infinite;
        }

        @keyframes dnaWave {
            0%, 100% {
                transform: translateY(0) scaleY(1);
            }
            50% {
                transform: translateY(-40px) scaleY(1.2);
            }
        }

        @keyframes float {
            0%, 100% {
                transform: translate(0, 0);
                opacity: 0.5;
            }
            25% {
                transform: translate(50px, -60px);
                opacity: 0.8;
            }
            50% {
                transform: translate(-30px, 80px);
                opacity: 0.4;
            }
            75% {
                transform: translate(60px, 40px);
                opacity: 0.7;
            }
        }

        .dna-strand:nth-child(2) {
            animation-delay: -2s;
        }

        .dna-strand:nth-child(3) {
            animation-delay: -4s;
        }

        .blood-cell:nth-child(4) {
            animation-delay: -3s;
        }

        .blood-cell:nth-child(5) {
            animation-delay: -6s;
        }

        .blood-cell:nth-child(6) {
            animation-delay: -9s;
        }

        .blood-cell:nth-child(7) {
            animation-delay: -12s;
        }
    </style>
</head>

<body class="bg-gray-100 font-sans">
    <div class="animation-container">
        <svg width="100%" height="100%" preserveAspectRatio="none">
            <path class="dna-strand" d="M0,200 C300,50 600,350 900,200 C1200,50 1500,350 1800,200" />
            <path class="dna-strand" d="M0,250 C300,400 600,100 900,250 C1200,400 1500,100 1800,250" />
            <path class="dna-strand" d="M0,300 C300,150 600,450 900,300 C1200,150 1500,450 1800,300" />
            <circle class="blood-cell" cx="200" cy="300" r="12" />
            <circle class="blood-cell" cx="500" cy="600" r="10" />
            <circle class="blood-cell" cx="800" cy="200" r="15" />
            <circle class="blood-cell" cx="1100" cy="400" r="8" />
            <circle class="blood-cell" cx="1400" cy="500" r="11" />
        </svg>
    </div>
    
    <div class="container mx-auto p-8 max-w-7xl">
        <h1 class="text-4xl font-bold text-center mb-8 text-red-600">Formulaire de Consultation Drepadata</h1>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-6 py-4 rounded mb-8">
                <p>Erreur: <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        <?php endif; ?>

        <div id="error-message" class="hidden bg-red-100 border border-red-400 text-red-700 px-6 py-4 rounded mb-8 text-base"></div>

        <div class="mb-8">
            <div class="w-full bg-gray-200 rounded-full h-3">
                <div class="bg-red-600 h-3 rounded-full progress-bar"></div>
            </div>
            <p class="text-center mt-3 text-base text-gray-600">Étape <span id="current-step">1</span> sur <span id="total-steps">9</span></p>
        </div>

        <form id="consultation-form" action="save_consultation.php" method="POST" class="bg-white p-8 rounded-lg shadow-md" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>" />
            <input type="hidden" id="current_step_input" name="current_step" value="1" />
            <input type="hidden" id="consultation_id_input" name="consultation_id" value="" />
            
            <div id="form-message" class="hidden text-red-600 mb-4"></div>

            <!-- Step 1: Informations administratives -->
            <div class="step active" data-step="1">
                <h2 class="text-3xl font-semibold mb-6">Informations administratives</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="fosa" class="block text-base font-medium text-gray-700">Nom du site (FOSA) <span class="required-asterisk">*</span></label>
                        <select id="fosa" name="fosa" class="mt-2 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 p-3 text-base" required>
                            <option value="">--Sélectionner--</option>
                            <option value="Hôpital Général de Douala">Hôpital Général de Douala</option>
                            <option value="Centre Médical de Yaoundé">Centre Médical de Yaoundé</option>
                            <option value="Hôpital Régional de Bamenda">Hôpital Régional de Bamenda</option>
                            <option value="Autres">Autres</option>
                        </select>
                        <div id="fosa_other_field" class="conditional-container" style="display:none;">
                            <label for="fosa_other" class="block text-base font-medium text-gray-700">Veuillez préciser le FOSA</label>
                            <input type="text" id="fosa_other" name="fosa_other" class="mt-2 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 p-3 text-base" />
                        </div>
                    </div>
                    <div>
                        <label for="region" class="block text-base font-medium text-gray-700">Région <span class="required-asterisk">*</span></label>
                        <select id="region" name="region" class="mt-2 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 p-3 text-base" required>
                            <option value="">--Sélectionner--</option>
                            <option value="Adamaoua">Adamaoua</option>
                            <option value="Centre">Centre</option>
                            <option value="Est">Est</option>
                            <option value="Extrême-Nord">Extrême-Nord</option>
                            <option value="Littoral">Littoral</option>
                            <option value="Nord">Nord</option>
                            <option value="Nord-Ouest">Nord-Ouest</option>
                            <option value="Ouest">Ouest</option>
                            <option value="Sud">Sud</option>
                            <option value="Sud-Ouest">Sud-Ouest</option>
                        </select>
                    </div>
                    <div>
                        <label for="district" class="block text-base font-medium text-gray-700">District de santé</label>
                        <input type="text" id="district" name="district" class="mt-2 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 p-3 text-base" />
                    </div>
                    <div>
                        <label for="diagnostic_date" class="block text-base font-medium text-gray-700">Date (période) du diagnostic</label>
                        <input type="date" id="diagnostic_date" name="diagnostic_date" class="mt-2 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 p-3 text-base" />
                    </div>
                    <div>
                        <label for="ipp" class="block text-base font-medium text-gray-700">Numéro de dossier / IPP</label>
                        <input type="text" id="ipp" name="ipp" class="mt-2 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 p-3 text-base" />
                    </div>
                    <div>
                        <label for="personnel" class="block text-base font-medium text-gray-700">Personnel remplissant le formulaire</label>
                        <select id="personnel" name="personnel" class="mt-2 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 p-3 text-base">
                            <option value="">--Sélectionner--</option>
                            <option value="medecin">Médecin</option>
                            <option value="infirmier">Infirmier</option>
                            <option value="APS">APS (Accompagnateur psychosocial)</option>
                            <option value="laborantin">Laborantin</option>
                            <option value="Autres">Autres</option>
                        </select>
                    </div>
                    <div>
                        <label for="referred" class="block text-base font-medium text-gray-700">Référé</label>
                        <select id="referred" name="referred" class="mt-2 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 p-3 text-base">
                            <option value="">--Sélectionner--</option>
                            <option value="Oui">Oui</option>
                            <option value="Non">Non</option>
                        </select>
                        <div id="referred_from_field" class="conditional-field conditional-container">
                            <label for="referred_from" class="block text-base font-medium text-gray-700">Référé de</label>
                            <input type="text" id="referred_from" name="referred_from" class="mt-2 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 p-3 text-base" />
                        </div>
                        <div id="referred_for_field" class="conditional-field conditional-container">
                            <label for="referred_for" class="block text-base font-medium text-gray-700">Raison</label>
                            <input type="text" id="referred_for" name="referred_for" class="mt-2 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 p-3 text-base" />
                        </div>
                    </div>
                    <div>
                        <label for="evolution" class="block text-base font-medium text-gray-700">Evolution</label>
                        <select id="evolution" name="evolution" class="mt-2 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 p-3 text-base">
                            <option value="">--Sélectionner--</option>
                            <option value="Suivi régulier">Suivi régulier</option>
                            <option value="Perdu de vue">Perdu de vue</option>
                            <option value="Décédé">Décédé</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Step 2: Données démographiques -->
            <div class="step" data-step="2">
                <h2 class="text-3xl font-semibold mb-6">Données démographiques</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="full_name" class="block text-base font-medium text-gray-700">Nom et Prénom <span class="required-asterisk">*</span></label>
                        <input type="text" id="full_name" name="full_name" class="mt-2 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 p-3 text-base" required />
                    </div>
                    <div>
                        <label for="age" class="block text-base font-medium text-gray-700">Age</label>
                        <input type="number" id="age" name="age" min="0" class="mt-2 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 p-3 text-base" />
                    </div>
                    <div>
                        <label for="birth_date" class="block text-base font-medium text-gray-700">Date de naissance</label>
                        <input type="date" id="birth_date" name="birth_date" class="mt-2 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 p-3 text-base" />
                    </div>
                    <div>
                        <label for="sex" class="block text-base font-medium text-gray-700">Sexe</label>
                        <select id="sex" name="sex" class="mt-2 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 p-3 text-base">
                            <option value="">--Sélectionner--</option>
                            <option value="M">M</option>
                            <option value="F">F</option>
                        </select>
                    </div>
                    <div>
                        <label for="address" class="block text-base font-medium text-gray-700">Adresse</label>
                        <input type="text" id="address" name="address" class="mt-2 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 p-3 text-base" />
                    </div>
                    <div>
                        <label for="emergency_contact_name" class="block text-base font-medium text-gray-700">Nom de la personne à contacter en cas d'urgence</label>
                        <input type="text" id="emergency_contact_name" name="emergency_contact_name" class="mt-2 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 p-3 text-base" />
                    </div>
                    <div>
                        <label for="emergency_contact_relation" class="block text-base font-medium text-gray-700">Lien avec le patient</label>
                        <select id="emergency_contact_relation" name="emergency_contact_relation" class="mt-2 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 p-3 text-base">
                            <option value="">--Sélectionner--</option>
                            <option value="Père">Père</option>
                            <option value="Mère">Mère</option>
                            <option value="Grand-mère">Grand-mère</option>
                            <option value="Grand-père">Grand-père</option>
                            <option value="Frère">Frère</option>
                            <option value="Sœur">Sœur</option>
                            <option value="Oncle">Oncle</option>
                            <option value="Tante">Tante</option>
                            <option value="Autre">Autre</option>
                        </select>
                    </div>
                    <div>
                        <label for="emergency_contact_phone" class="block text-base font-medium text-gray-700">Téléphone de la personne à contacter</label>
                        <div class="phone-input mt-2">
                            <span class="phone-prefix">+237</span>
                            <input type="tel" id="emergency_contact_phone" name="emergency_contact_phone" class="phone-number block border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 p-3 text-base" pattern="[0-9]{9}" placeholder="6XXXXXXXX" />
                        </div>
                    </div>
                    <div>
                        <label for="lives_with" class="block text-base font-medium text-gray-700">Vit avec le patient</label>
                        <select id="lives_with" name="lives_with" class="mt-2 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 p-3 text-base">
                            <option value="">--Sélectionner--</option>
                            <option value="Oui">Oui</option>
                            <option value="Non">Non</option>
                        </select>
                    </div>
                    <div>
                        <label for="insurance" class="block text-base font-medium text-gray-700">Assurance / Couverture sociale</label>
                        <select id="insurance" name="insurance" class="mt-2 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 p-3 text-base">
                            <option value="">--Sélectionner--</option>
                            <option value="Oui">Oui</option>
                            <option value="Non">Non</option>
                        </select>
                    </div>
                    <div>
                        <label for="support_group" class="block text-base font-medium text-gray-700">Appartient à un groupe/Association de patients drépanocytaires</label>
                        <select id="support_group" name="support_group" class="mt-2 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 p-3 text-base">
                            <option value="">--Sélectionner--</option>
                            <option value="Oui">Oui</option>
                            <option value="Non">Non</option>
                        </select>
                        <div id="group_name_field" class="conditional-field conditional-container">
                            <label for="group_name" class="block text-base font-medium text-gray-700">Nom du groupe/Association</label>
                            <input type="text" id="group_name" name="group_name" class="mt-2 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 p-3 text-base" />
                        </div>
                    </div>
                    <div>
                        <label for="parents" class="block text-base font-medium text-gray-700">Vit avec ses parents biologiques</label>
                        <select id="parents" name="parents" class="mt-2 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 p-3 text-base">
                            <option value="">--Sélectionner--</option>
                            <option value="Oui">Oui</option>
                            <option value="Non">Non</option>
                        </select>
                        <div id="sibling_rank_field" class="conditional-field conditional-container">
                            <label for="sibling_rank" class="block text-base font-medium text-gray-700">Rang dans la fratrie</label>
                            <input type="number" id="sibling_rank" name="sibling_rank" min="1" class="mt-2 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 p-3 text-base" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 3: Traitements en cours -->
            <div class="step" data-step="3">
                <h2 class="text-3xl font-semibold mb-6">Traitements en cours</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="hydroxyurea" class="block text-base font-medium text-gray-700">Hydroxyurée</label>
                        <select id="hydroxyurea" name="hydroxyurea" class="mt-2 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 p-3 text-base">
                            <option value="">--Sélectionner--</option>
                            <option value="Oui">Oui</option>
                            <option value="Non">Non</option>
                        </select>
                        <div id="tolerance_field" class="conditional-field conditional-container">
                            <label for="tolerance" class="block text-base font-medium text-gray-700">Tolérance</label>
                            <select id="tolerance" name="tolerance" class="mt-2 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 p-3 text-base">
                                <option value="">--Sélectionner--</option>
                                <option value="Bonne tolérance">Bonne tolérance</option>
                                <option value="Tolérance moyenne">Tolérance moyenne</option>
                                <option value="Mauvaise tolérance">Mauvaise tolérance</option>
                            </select>
                        </div>
                        <div id="hydroxyurea_reasons_field" class="conditional-field conditional-container">
                            <label for="hydroxyurea_reasons" class="block text-base font-medium text-gray-700">Raisons de non-utilisation de l'hydroxyurée</label>
                            <select id="hydroxyurea_reasons" name="hydroxyurea_reasons" class="mt-2 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 p-3 text-base">
                                <option value="">--Sélectionner--</option>
                                <option value="Non disponible en pharmacie">Non disponible en pharmacie</option>
                                <option value="Manque de moyens financiers">Manque de moyens financiers</option>
                                <option value="Crainte des effets secondaires">Crainte des effets secondaires</option>
                                <option value="Crainte de l'irrégularité dans l'approvisionnement du médicament">Crainte de l'irrégularité dans l'approvisionnement du médicament</option>
                                <option value="Manque de conviction sur l'intérêt du médicament">Manque de conviction sur l'intérêt du médicament</option>
                                <option value="Autre raison">Autre raison</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label for="hydroxyurea_dosage" class="block text-base font-medium text-gray-700">Posologie de l'hydroxyurée</label>
                        <input type="text" id="hydroxyurea_dosage" name="hydroxyurea_dosage" class="mt-2 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 p-3 text-base" />
                    </div>
                    <div>
                        <label for="folic_acid" class="block text-base font-medium text-gray-700">Acide folique</label>
                        <select id="folic_acid" name="folic_acid" class="mt-2 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 p-3 text-base">
                            <option value="">--Sélectionner--</option>
                            <option value="Oui">Oui</option>
                            <option value="Non">Non</option>
                        </select>
                    </div>
                    <div>
                        <label for="penicillin" class="block text-base font-medium text-gray-700">Antibioprophylaxie (Pénicilline)</label>
                        <select id="penicillin" name="penicillin" class="mt-2 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 p-3 text-base">
                            <option value="">--Sélectionner--</option>
                            <option value="Oui">Oui</option>
                            <option value="Non">Non</option>
                        </select>
                    </div>
                    <div>
                        <label for="regular_transfusion" class="block text-base font-medium text-gray-700">Transfusions régulières</label>
                        <select id="regular_transfusion" name="regular_transfusion" class="mt-2 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 p-3 text-base">
                            <option value="">--Sélectionner--</option>
                            <option value="Oui">Oui</option>
                            <option value="Non">Non</option>
                        </select>
                        <div id="transfusion_details_field" class="conditional-field conditional-container">
                            <label for="transfusion_type" class="block text-base font-medium text-gray-700">Type de transfusion</label>
                            <select id="transfusion_type" name="transfusion_type" class="mt-2 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 p-3 text-base">
                                <option value="">--Sélectionner--</option>
                                <option value="Sang Total">Sang Total</option>
                                <option value="Sang Fractionné">Sang Fractionné</option>
                                <option value="Autres">Autres</option>
                            </select>
                            <label for="transfusion_frequency" class="block text-base font-medium text-gray-700 mt-4">Fréquence des transfusions</label>
                            <input type="text" id="transfusion_frequency" name="transfusion_frequency" class="mt-2 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 p-3 text-base" />
                            <label for="last_transfusion_date" class="block text-base font-medium text-gray-700 mt-4">Date de la dernière transfusion</label>
                            <input type="date" id="last_transfusion_date" name="last_transfusion_date" class="mt-2 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 p-3 text-base" />
                        </div>
                    </div>
                    <div>
                        <label for="other_treatments" class="block text-base font-medium text-gray-700">Autres traitements spécifiques</label>
                        <textarea id="other_treatments" name="other_treatments" rows="5" class="mt-2 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 p-3 text-base"></textarea>
                    </div>
                </div>
            </div>

            <!-- Step 4: Antécédents médicaux -->
            <div class="step" data-step="4">
                <h2 class="text-3xl font-semibold mb-6">Antécédents médicaux</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="sickle_type" class="block text-base font-medium text-gray-700">Type de drépanocytose</label>
                        <select id="sickle_type" name="sickle_type" class="mt-2 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 p-3 text-base">
                            <option value="">--Sélectionner--</option>
                            <option value="SS">SS</option>
                            <option value="SC">SC</option>
                            <option value="Sβ⁰">Sβ⁰</option>
                            <option value="Sβ⁺">Sβ⁺</option>
                            <option value="Autre">Autre</option>
                        </select>
                    </div>
                    <div>
                        <label for="diagnosis_age" class="block text-base font-medium text-gray-700">Age au diagnostic</label>
                        <select id="diagnosis_age" name="diagnosis_age" class="mt-2 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 p-3 text-base">
                            <option value="">--Sélectionner--</option>
                            <option value="A la naissance">A la naissance</option>
                            <option value="0-3 mois">0-3 mois</option>
                            <option value="4-6 mois">4-6 mois</option>
                            <option value="7-12 mois">7-12 mois</option>
                            <option value="2-3 ans">2-3 ans</option>
                            <option value="4-5 ans">4-5 ans</option>
                        </select>
                    </div>
                    <div>
                        <label for="vocs" class="block text-base font-medium text-gray-700">Nombre total d'épisodes de crises vaso-occlusives (3 derniers mois)</label>
                        <select id="vocs" name="vocs" class="mt-2 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 p-3 text-base">
                            <option value="">--Sélectionner--</option>
                            <option value="Aucune">Aucune</option>
                            <option value="moins de 2">moins de 2</option>
                            <option value="3-5">3-5</option>
                            <option value="6-8">6-8</option>
                            <option value="9-10">9-10</option>
                            <option value="plus de 10">plus de 10</option>
                        </select>
                    </div>
                    <div>
                        <label for="hospitalizations" class="block text-base font-medium text-gray-700">Nombre total d'hospitalisations (3 derniers mois)</label>
                        <select id="hospitalizations" name="hospitalizations" class="mt-2 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 p-3 text-base">
                            <option value="">--Sélectionner--</option>
                            <option value="0">0</option>
                            <option value="moins de 2">moins de 2</option>
                            <option value="2-5">2-5</option>
                            <option value="6-8">6-8</option>
                            <option value="9-10">9-10</option>
                            <option value="plus de 10">plus de 10</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Step 5: Suivi psychologique et social -->
            <div class="step" data-step="5">
                <h2 class="text-3xl font-semibold mb-6">Suivi psychologique et social</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="impact_scolaire" class="block text-base font-medium text-gray-700">Impact scolaire / absentéisme</label>
                        <select id="impact_scolaire" name="impact_scolaire" class="mt-2 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 p-3 text-base">
                            <option value="">--Sélectionner--</option>
                            <option value="Oui">Oui</option>
                            <option value="Non">Non</option>
                        </select>
                    </div>
                    <div>
                        <label for="accompagnement_psychologique" class="block text-base font-medium text-gray-700">Accompagnement psychologique</label>
                        <select id="accompagnement_psychologique" name="accompagnement_psychologique" class="mt-2 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 p-3 text-base">
                            <option value="">--Sélectionner--</option>
                            <option value="Oui">Oui</option>
                            <option value="Non">Non</option>
                        </select>
                    </div>
                    <div>
                        <label for="soutien_social" class="block text-base font-medium text-gray-700">Soutien social / Prestations spécifiques</label>
                        <select id="soutien_social" name="soutien_social" class="mt-2 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 p-3 text-base">
                            <option value="">--Sélectionner--</option>
                            <option value="Oui">Oui</option>
                            <option value="Non">Non</option>
                        </select>
                    </div>
                    <div>
                        <label for="famille_informee" class="block text-base font-medium text-gray-700">Famille informée et éduquée sur la maladie</label>
                        <select id="famille_informee" name="famille_informee" class="mt-2 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 p-3 text-base">
                            <option value="">--Sélectionner--</option>
                            <option value="Oui">Oui</option>
                            <option value="Non">Non</option>
                        </select>
                    </div>
                    <div>
                        <label for="date_prochaine_consultation" class="block text-base font-medium text-gray-700">Date de prochaine consultation</label>
                        <input type="date" id="date_prochaine_consultation" name="date_prochaine_consultation" class="mt-2 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 p-3 text-base" />
                    </div>
                </div>
            </div>

            <!-- Step 6: Calendrier vaccinal -->
            <div class="step" data-step="6">
                <h2 class="text-3xl font-semibold mb-6">Calendrier vaccinal</h2>
                <div class="text-center">
                    <p class="text-lg mb-6">Cliquez sur le bouton ci-dessous pour accéder au calendrier vaccinal du PEV</p>
                    <button type="button" id="vaccination-calendar-btn" class="bg-yellow-500 text-white px-8 py-4 rounded-md text-lg hover:bg-yellow-600 focus:ring-2 focus:ring-yellow-400">
                        Ouvrir le Calendrier Vaccinal
                    </button>
                </div>
            </div>

            <!-- Step 7: Observations supplémentaires -->
            <div class="step" data-step="7">
                <h2 class="text-3xl font-semibold mb-6">Observations supplémentaires</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="education_therapeutique" class="block text-base font-medium text-gray-700">Éducation thérapeutique prévue</label>
                        <select id="education_therapeutique" name="education_therapeutique" class="mt-2 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 p-3 text-base">
                            <option value="">--Sélectionner--</option>
                            <option value="Oui">Oui</option>
                            <option value="Non">Non</option>
                        </select>
                    </div>
                    <div>
                        <label for="date_prochaine_consultation_plan" class="block text-base font-medium text-gray-700">Date de la prochaine consultation</label>
                        <input type="date" id="date_prochaine_consultation_plan" name="date_prochaine_consultation_plan" class="mt-2 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 p-3 text-base" />
                    </div>
                </div>
            </div>

            <!-- Step 8: Plan de suivi personnalisé -->
            <div class="step" data-step="8">
                <h2 class="text-3xl font-semibold mb-6">Plan de suivi personnalisé</h2>
                <div class="grid grid-cols-1 gap-6">
                    <div>
                        <label class="block text-base font-medium text-gray-700">Examens à réaliser avant la consultation</label>
                        <div id="examens-container" class="mt-2">
                            <div class="flex items-center mb-3">
                                <input type="text" name="examens_avant_consultation[]" class="block w-full bg-white border border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 p-3 text-base" placeholder="Entrez un examen" />
                                <button type="button" id="add-examen-btn" class="ml-2 bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700" title="Ajouter un autre examen">+</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 9: Commentaires / Observations libres -->
            <div class="step" data-step="9">
                <h2 class="text-3xl font-semibold mb-6">Commentaires / Observations libres</h2>
                <div>
                    <label for="commentaires" class="block text-base font-medium text-gray-700">Commentaires / Observations libres</label>
                    <textarea id="commentaires" name="commentaires" rows="8" class="mt-2 block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 p-3 text-base" placeholder="Entrez vos commentaires et observations..."></textarea>
                </div>
            </div>

            <div class="mt-8 flex justify-between items-center">
                <button type="button" id="prev-btn" class="bg-gray-600 text-white px-6 py-3 rounded-md text-base hover:bg-gray-700 focus:ring-2 focus:ring-red-500" disabled>Précédent</button>
                <button type="button" id="next-btn" class="bg-blue-600 text-white px-6 py-3 rounded-md text-base hover:bg-red-700 focus:ring-2 focus:ring-red-500">Suivant</button>
                <button type="submit" id="submit-btn" class="hidden bg-green-600 text-white px-6 py-3 rounded-md text-base hover:bg-green-700 focus:ring-2 focus:ring-green-500">Soumettre</button>
            </div>
        </form>
    </div>

    <script>
        const steps = document.querySelectorAll('.step');
        const prevBtn = document.getElementById('prev-btn');
        const nextBtn = document.getElementById('next-btn');
        const submitBtn = document.getElementById('submit-btn');
        const progressBar = document.querySelector('.progress-bar');
        const currentStepDisplay = document.getElementById('current-step');
        const totalStepsDisplay = document.getElementById('total-steps');
        const errorMessage = document.getElementById('error-message');
        const form = document.getElementById('consultation-form');
        let currentStep = 1;
        let consultationId = null;

        totalStepsDisplay.textContent = steps.length;

        function updateProgress() {
            const progress = ((currentStep - 1) / (steps.length - 1)) * 100;
            progressBar.style.width = `${progress}%`;
            currentStepDisplay.textContent = currentStep;
            prevBtn.disabled = currentStep === 1;
            nextBtn.classList.toggle('hidden', currentStep === steps.length);
            submitBtn.classList.toggle('hidden', currentStep !== steps.length);
        }

        function showStep(step) {
            steps.forEach((s, index) => {
                s.classList.toggle('active', index + 1 === step);
            });
            document.getElementById('current_step_input').value = step;
            updateProgress();
        }

        function validateStep(step) {
            const currentStepElement = document.querySelector(`.step[data-step="${step}"]`);
            const requiredFields = currentStepElement.querySelectorAll('[required]');
            let isValid = true;
            let errorMessages = [];

            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('border-red-500');
                    const label = currentStepElement.querySelector(`label[for="${field.id}"]`);
                    errorMessages.push(`Le champ "${label ? label.textContent.replace('*', '').trim() : field.id}" est requis.`);
                } else {
                    field.classList.remove('border-red-500');
                }
            });

            if (!isValid) {
                errorMessage.classList.remove('hidden');
                errorMessage.innerHTML = errorMessages.join('<br>');
            } else {
                errorMessage.classList.add('hidden');
            }

            return isValid;
        }

        function handleConditionalFields() {
            // FOSA: Show 'fosa_other' field if 'Autres' is selected
            const fosaSelect = document.getElementById('fosa');
            const fosaOtherField = document.getElementById('fosa_other_field');
            if (fosaSelect) {
                fosaSelect.addEventListener('change', () => {
                    fosaOtherField.style.display = fosaSelect.value === 'Autres' ? 'block' : 'none';
                });
            }

            // Referred: Show 'referred_from' and 'referred_for' fields if 'Oui' is selected
            const referredSelect = document.getElementById('referred');
            const referredFromField = document.getElementById('referred_from_field');
            const referredForField = document.getElementById('referred_for_field');
            if (referredSelect) {
                referredSelect.addEventListener('change', () => {
                    const show = referredSelect.value === 'Oui';
                    referredFromField.style.display = show ? 'block' : 'none';
                    referredForField.style.display = show ? 'block' : 'none';
                });
            }

            // Support Group: Show 'group_name' field if 'Oui' is selected
            const supportGroupSelect = document.getElementById('support_group');
            const groupNameField = document.getElementById('group_name_field');
            if (supportGroupSelect) {
                supportGroupSelect.addEventListener('change', () => {
                    groupNameField.style.display = supportGroupSelect.value === 'Oui' ? 'block' : 'none';
                });
            }

            // Parents: Show 'sibling_rank' field if 'Oui' is selected
            const parentsSelect = document.getElementById('parents');
            const siblingRankField = document.getElementById('sibling_rank_field');
            if (parentsSelect) {
                parentsSelect.addEventListener('change', () => {
                    siblingRankField.style.display = parentsSelect.value === 'Oui' ? 'block' : 'none';
                });
            }

            // Hydroxyurea: Show 'tolerance' and 'hydroxyurea_reasons' fields based on selection
            const hydroxyureaSelect = document.getElementById('hydroxyurea');
            const toleranceField = document.getElementById('tolerance_field');
            const hydroxyureaReasonsField = document.getElementById('hydroxyurea_reasons_field');
            if (hydroxyureaSelect) {
                hydroxyureaSelect.addEventListener('change', () => {
                    toleranceField.style.display = hydroxyureaSelect.value === 'Oui' ? 'block' : 'none';
                    hydroxyureaReasonsField.style.display = hydroxyureaSelect.value === 'Non' ? 'block' : 'none';
                });
            }

            // Regular Transfusion: Show 'transfusion_details' field if 'Oui' is selected
            const regularTransfusionSelect = document.getElementById('regular_transfusion');
            const transfusionDetailsField = document.getElementById('transfusion_details_field');
            if (regularTransfusionSelect) {
                regularTransfusionSelect.addEventListener('change', () => {
                    transfusionDetailsField.style.display = regularTransfusionSelect.value === 'Oui' ? 'block' : 'none';
                });
            }
        }

        function addExamenField() {
            const container = document.getElementById('examens-container');
            const newField = document.createElement('div');
            newField.className = 'flex items-center mb-3';
            newField.innerHTML = `
                <input type="text" name="examens_avant_consultation[]" class="block w-full bg-white border border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500 p-3 text-base" placeholder="Entrez un examen" />
                <button type="button" class="ml-2 bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700 remove-examen-btn" title="Supprimer cet examen">-</button>
            `;
            container.appendChild(newField);

            // Add event listener to remove button
            newField.querySelector('.remove-examen-btn').addEventListener('click', () => {
                if (container.children.length > 1) {
                    container.removeChild(newField);
                }
            });
        }

        // Save consultation session function
        async function saveConsultationSession() {
            const form = document.getElementById('consultation-form');
            const formData = new FormData(form);
            formData.append('action', 'save_session');
            try {
                const response = await fetch('save_consultation_step.php', {
                    method: 'POST',
                    body: formData
                });
                if (!response.ok) {
                    console.error('Failed to save consultation session');
                }
            } catch (error) {
                console.error('Error saving consultation session:', error);
            }
        }

        // Vaccination calendar button handler
        document.getElementById('vaccination-calendar-btn').addEventListener('click', async function(event) {
            event.preventDefault();
            try {
                await saveConsultationSession();
                const patientNameInput = document.getElementById('full_name');
                let patientName = '';
                if (patientNameInput) {
                    patientName = encodeURIComponent(patientNameInput.value.trim());
                }
                let url = 'calendrier_vaccinal_du_pev.php';
                if (patientName) {
                    url += '?patient_name=' + patientName;
                }
                if (consultationId) {
                    url += (patientName ? '&' : '?') + 'consultation_id=' + consultationId;
                }
                window.location.href = url;
            } catch (error) {
                console.error('Error during saveConsultationSession:', error);
                alert('Erreur lors de la sauvegarde de la session. Veuillez réessayer.');
            }
        });

        nextBtn.addEventListener('click', async () => {
            if (validateStep(currentStep)) {
                // Collect data for current step only
                const currentStepElement = document.querySelector(`.step[data-step="${currentStep}"]`);
                const inputs = currentStepElement.querySelectorAll('input, select, textarea');
                const data = {};
                
                inputs.forEach(input => {
                    if (input.name) {
                        if ((input.type === 'checkbox' || input.type === 'radio') && !input.checked) {
                            return;
                        }
                        if (input.name.includes('[]')) {
                            const baseName = input.name.replace('[]', '');
                            if (!data[baseName]) {
                                data[baseName] = [];
                            }
                            data[baseName].push(input.value);
                        } else {
                            data[input.name] = input.value;
                        }
                    }
                });

                // Add CSRF token and current step
                const csrfToken = document.querySelector('input[name="csrf_token"]').value;
                data['csrf_token'] = csrfToken;
                data['current_step'] = currentStep;
                if (consultationId) {
                    data['consultation_id'] = consultationId;
                }

                try {
                    const response = await fetch('save_consultation_step.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        credentials: 'include',
                        body: JSON.stringify(data)
                    });
                    
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    
                    const result = await response.json();
                    if (result.success) {
                        if (result.consultation_id) {
                            consultationId = result.consultation_id;
                            document.getElementById('consultation_id_input').value = consultationId;
                        }
                        if (currentStep < steps.length) {
                            currentStep++;
                            showStep(currentStep);
                        }
                    } else {
                        console.error('Erreur lors de la sauvegarde des données: ' + (result.message || 'Erreur inconnue'));
                        alert('Erreur lors de la sauvegarde: ' + (result.message || 'Erreur inconnue'));
                    }
                } catch (error) {
                    console.error('Erreur réseau lors de la sauvegarde des données: ' + error.message);
                    alert('Erreur de connexion. Veuillez réessayer.');
                }
            }
        });

        prevBtn.addEventListener('click', () => {
            if (currentStep > 1) {
                currentStep--;
                showStep(currentStep);
                errorMessage.classList.add('hidden');
            }
        });

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (!validateStep(currentStep)) {
                return;
            }

            const formMessage = document.getElementById('form-message');
            formMessage.classList.add('hidden');
            formMessage.textContent = '';

            const formData = new FormData(form);
            if (consultationId) {
                formData.set('consultation_id', consultationId);
            }

            try {
                const response = await fetch('save_consultation.php', {
                    method: 'POST',
                    body: formData,
                });
                
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                
                const blob = await response.blob();
                const disposition = response.headers.get('Content-Disposition');
                let filename = 'consultation_report.docx';
                if (disposition && disposition.indexOf('attachment') !== -1) {
                    const filenameRegex = /filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/;
                    const matches = filenameRegex.exec(disposition);
                    if (matches != null && matches[1]) {
                        filename = matches[1].replace(/['"]/g, '');
                    }
                }
                
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = filename;
                document.body.appendChild(a);
                a.click();
                a.remove();
                window.URL.revokeObjectURL(url);
                
                // Clear session after successful download
                await fetch('clear_session.php', { method: 'POST' });
                
            } catch (error) {
                console.error('Error during form submission:', error);
                formMessage.textContent = 'Erreur lors de la soumission du formulaire. Veuillez réessayer.';
                formMessage.classList.remove('hidden');
            }
        });

        document.getElementById('add-examen-btn').addEventListener('click', addExamenField);

        // Initialize conditional fields and progress
        handleConditionalFields();
        updateProgress();

        // Phone input validation
        const phoneInput = document.getElementById('emergency_contact_phone');
        if (phoneInput) {
            phoneInput.addEventListener('input', () => {
                phoneInput.value = phoneInput.value.replace(/[^0-9]/g, '');
                if (phoneInput.value.length > 9) {
                    phoneInput.value = phoneInput.value.slice(0, 9);
                }
            });
        }

        // Age and birth date synchronization
        const birthDateInput = document.getElementById('birth_date');
        const ageInput = document.getElementById('age');
        let isUpdating = false;

        function calculateAgeFromDOB(dob) {
            const today = new Date();
            const birthDate = new Date(dob);
            let age = today.getFullYear() - birthDate.getFullYear();
            const m = today.getMonth() - birthDate.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
                age--;
            }
            return age >= 0 ? age : '';
        }

        function calculateDOBFromAge(age) {
            const today = new Date();
            const birthYear = today.getFullYear() - age;
            return new Date(birthYear, 11, 31).toISOString().split('T')[0];
        }

        birthDateInput.addEventListener('change', () => {
            if (isUpdating) return;
            isUpdating = true;
            const dobValue = birthDateInput.value;
            if (dobValue) {
                const age = calculateAgeFromDOB(dobValue);
                ageInput.value = age;
            } else {
                ageInput.value = '';
            }
            isUpdating = false;
        });

        ageInput.addEventListener('input', () => {
            if (isUpdating) return;
            isUpdating = true;
            const ageValue = parseInt(ageInput.value, 10);
            if (!isNaN(ageValue) && ageValue >= 0) {
                const dob = calculateDOBFromAge(ageValue);
                birthDateInput.value = dob;
            } else {
                birthDateInput.value = '';
            }
            isUpdating = false;
        });
    </script>
</body>
</html>