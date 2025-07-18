-- Drepadata Consultation App Database Schema
-- Creates tables for storing consultation data

-- Main consultations table
CREATE TABLE IF NOT EXISTS consultations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Administrative Information (Step 1)
    fosa VARCHAR(255) DEFAULT NULL,
    region VARCHAR(255) DEFAULT NULL,
    district VARCHAR(255) DEFAULT NULL,
    diagnostic_date DATE DEFAULT NULL,
    ipp VARCHAR(255) DEFAULT NULL,
    personnel VARCHAR(255) DEFAULT NULL,
    referred VARCHAR(10) DEFAULT NULL,
    referred_from VARCHAR(255) DEFAULT NULL,
    referred_for VARCHAR(255) DEFAULT NULL,
    evolution VARCHAR(255) DEFAULT NULL,
    
    -- Demographics (Step 2)
    full_name VARCHAR(255) DEFAULT NULL,
    age INT DEFAULT NULL,
    birth_date DATE DEFAULT NULL,
    sex VARCHAR(10) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    emergency_contact_name VARCHAR(255) DEFAULT NULL,
    emergency_contact_relation VARCHAR(255) DEFAULT NULL,
    emergency_contact_phone VARCHAR(20) DEFAULT NULL,
    lives_with VARCHAR(10) DEFAULT NULL,
    insurance VARCHAR(10) DEFAULT NULL,
    support_group VARCHAR(10) DEFAULT NULL,
    group_name VARCHAR(255) DEFAULT NULL,
    parents VARCHAR(10) DEFAULT NULL,
    sibling_rank INT DEFAULT NULL,
    
    -- Plan de suivi (Step 8)
    examens_avant_consultation TEXT DEFAULT NULL,
    
    -- Commentaires (Step 9)
    commentaires TEXT DEFAULT NULL,
    
    INDEX idx_created_at (created_at),
    INDEX idx_full_name (full_name),
    INDEX idx_region (region)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Consultation treatments table (Step 3)
CREATE TABLE IF NOT EXISTS consultation_treatments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    consultation_id INT NOT NULL UNIQUE,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Treatment information
    hydroxyurea VARCHAR(10) DEFAULT NULL,
    tolerance VARCHAR(50) DEFAULT NULL,
    hydroxyurea_reasons VARCHAR(255) DEFAULT NULL,
    hydroxyurea_dosage VARCHAR(100) DEFAULT NULL,
    folic_acid VARCHAR(10) DEFAULT NULL,
    penicillin VARCHAR(10) DEFAULT NULL,
    regular_transfusion VARCHAR(10) DEFAULT NULL,
    transfusion_type VARCHAR(50) DEFAULT NULL,
    transfusion_frequency VARCHAR(100) DEFAULT NULL,
    last_transfusion_date DATE DEFAULT NULL,
    other_treatments TEXT DEFAULT NULL,
    
    FOREIGN KEY (consultation_id) REFERENCES consultations(id) ON DELETE CASCADE,
    INDEX idx_consultation_id (consultation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Consultation exams table (Step 4)
CREATE TABLE IF NOT EXISTS consultation_exams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    consultation_id INT NOT NULL UNIQUE,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Medical history
    sickle_type VARCHAR(10) DEFAULT NULL,
    diagnosis_age VARCHAR(50) DEFAULT NULL,
    diagnosis_circumstance VARCHAR(100) DEFAULT NULL,
    family_history VARCHAR(100) DEFAULT NULL,
    vocs VARCHAR(50) DEFAULT NULL,
    hospitalizations VARCHAR(50) DEFAULT NULL,
    hospitalization_cause TEXT DEFAULT NULL,
    longest_hospitalization VARCHAR(50) DEFAULT NULL,
    
    -- Hemoglobin tracking
    hb_1 VARCHAR(20) DEFAULT NULL,
    hb_2 VARCHAR(20) DEFAULT NULL,
    hb_3 VARCHAR(20) DEFAULT NULL,
    recent_hb VARCHAR(20) DEFAULT NULL,
    hbf_1 VARCHAR(20) DEFAULT NULL,
    hbf_2 VARCHAR(20) DEFAULT NULL,
    hbf_3 VARCHAR(20) DEFAULT NULL,
    hbs_1 VARCHAR(20) DEFAULT NULL,
    hbs_2 VARCHAR(20) DEFAULT NULL,
    hbs_3 VARCHAR(20) DEFAULT NULL,
    
    -- Medical conditions
    transfusion_reaction VARCHAR(10) DEFAULT NULL,
    reaction_types TEXT DEFAULT NULL,
    reaction_type_other TEXT DEFAULT NULL,
    allo_immunization VARCHAR(10) DEFAULT NULL,
    hyperviscosity VARCHAR(10) DEFAULT NULL,
    acute_chest_syndrome VARCHAR(50) DEFAULT NULL,
    stroke VARCHAR(10) DEFAULT NULL,
    priapism VARCHAR(10) DEFAULT NULL,
    leg_ulcer VARCHAR(10) DEFAULT NULL,
    cholecystectomy VARCHAR(10) DEFAULT NULL,
    asplenia VARCHAR(10) DEFAULT NULL,
    
    -- Lab results
    nfs_gb VARCHAR(50) DEFAULT NULL,
    nfs_hb VARCHAR(50) DEFAULT NULL,
    nfs_pqts VARCHAR(50) DEFAULT NULL,
    reticulocytes VARCHAR(50) DEFAULT NULL,
    microalbuminuria VARCHAR(50) DEFAULT NULL,
    hemolysis VARCHAR(100) DEFAULT NULL,
    ophtalmologie VARCHAR(50) DEFAULT NULL,
    imagerie_medical VARCHAR(100) DEFAULT NULL,
    consultations_specialisees VARCHAR(100) DEFAULT NULL,
    
    -- Vaccines and side effects
    recommended_vaccines TEXT DEFAULT NULL,
    drug_side_effects TEXT DEFAULT NULL,
    
    FOREIGN KEY (consultation_id) REFERENCES consultations(id) ON DELETE CASCADE,
    INDEX idx_consultation_id (consultation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Consultation observations table (Steps 5, 7)
CREATE TABLE IF NOT EXISTS consultation_observations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    consultation_id INT NOT NULL UNIQUE,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Psychological and social support
    impact_scolaire VARCHAR(10) DEFAULT NULL,
    accompagnement_psychologique VARCHAR(10) DEFAULT NULL,
    soutien_social VARCHAR(10) DEFAULT NULL,
    famille_informee VARCHAR(10) DEFAULT NULL,
    education_therapeutique VARCHAR(10) DEFAULT NULL,
    plan_suivi_personnalise VARCHAR(100) DEFAULT NULL,
    date_prochaine_consultation DATE DEFAULT NULL,
    date_prochaine_consultation_plan DATE DEFAULT NULL,
    
    FOREIGN KEY (consultation_id) REFERENCES consultations(id) ON DELETE CASCADE,
    INDEX idx_consultation_id (consultation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Consultation vaccinations table (Step 6)
CREATE TABLE IF NOT EXISTS consultation_vaccinations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    consultation_id INT NOT NULL UNIQUE,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Vaccination data stored as JSON
    vaccination_data TEXT DEFAULT NULL,
    
    FOREIGN KEY (consultation_id) REFERENCES consultations(id) ON DELETE CASCADE,
    INDEX idx_consultation_id (consultation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;