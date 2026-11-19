-- ============================================================
--  EduConnect — UCAO Saint-Michel
--  Schéma FINAL v3.0 — Informatique de Gestion L1/L2
--  Hackathon 2026 | Adama Diallo & équipe
-- ============================================================
--  Stack : MySQL 8+ / MariaDB 10.5+
--  Charset : utf8mb4 (emoji + caractères africains)
--  Moteur : InnoDB (FK, transactions, contraintes)
--  Notes LMD : 30% devoir + 70% examen OU 100% examen
--  Sessions : timeout 900s (15 min inactivité)
--  Messagerie : Firebase Firestore (pas de table SQL messages)
--  Paiements : Wave + Orange Money + Carte
-- ============================================================

DROP DATABASE IF EXISTS educonnect;
CREATE DATABASE educonnect CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE educonnect;

-- ============================================================
-- TABLE 1 : USERS
-- ============================================================
CREATE TABLE users (
    id            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    numero_ucao   VARCHAR(20)     UNIQUE,
    nom           VARCHAR(100)    NOT NULL,
    prenom        VARCHAR(100)    NOT NULL,
    email         VARCHAR(180)    NOT NULL UNIQUE,
    telephone     VARCHAR(20),
    password_hash VARCHAR(255)    NOT NULL,
    role          ENUM('etudiant','parent','enseignant','mentor','admin','chef_admin') NOT NULL DEFAULT 'etudiant',
    statut        ENUM('actif','suspendu','en_attente','archive') NOT NULL DEFAULT 'en_attente',
    photo_url     VARCHAR(500),
    dernier_login TIMESTAMP       NULL,
    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_role   (role),
    INDEX idx_statut (statut),
    INDEX idx_email  (email)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE 2 : CLASSES
-- ============================================================
CREATE TABLE classes (
    id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    code        VARCHAR(20)   NOT NULL UNIQUE,  -- ex: LIG1-1
    nom         VARCHAR(100)  NOT NULL,          -- ex: Licence Informatique de Gestion 1 - Groupe 1
    filiere     VARCHAR(100)  NOT NULL,          -- ex: Informatique de Gestion
    annee_etude TINYINT UNSIGNED NOT NULL,       -- 1, 2, 3
    capacite    TINYINT UNSIGNED NOT NULL DEFAULT 40,
    annee_acad  VARCHAR(10)   NOT NULL,          -- ex: 2025-2026
    PRIMARY KEY (id)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE 3 : ETUDIANTS
-- ============================================================
CREATE TABLE etudiants (
    id                  INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    user_id             INT UNSIGNED    NOT NULL UNIQUE,
    classe_id           INT UNSIGNED    NOT NULL,
    date_naissance      DATE,
    adresse             VARCHAR(300),
    parent_id           INT UNSIGNED,
    credits_obtenus     INT UNSIGNED    DEFAULT 0,
    credits_requis      INT UNSIGNED    DEFAULT 30,
    statut_academique   ENUM('regulier','probation','exclusion') DEFAULT 'regulier',
    PRIMARY KEY (id),
    FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE,
    FOREIGN KEY (classe_id) REFERENCES classes(id) ON DELETE RESTRICT,
    FOREIGN KEY (parent_id) REFERENCES users(id)   ON DELETE SET NULL,
    INDEX idx_classe (classe_id)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE 4 : ENSEIGNANTS (profil étendu)
-- ============================================================
CREATE TABLE enseignants (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED NOT NULL UNIQUE,
    grade       VARCHAR(100),  -- ex: Maître de Conférences
    departement VARCHAR(100),
    bureau      VARCHAR(50),
    PRIMARY KEY (id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- TABLE 5 : MODULES (LMD complet)
-- ============================================================
CREATE TABLE modules (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    code            VARCHAR(30)     NOT NULL UNIQUE,
    nom             VARCHAR(200)    NOT NULL,
    filiere         VARCHAR(100)    NOT NULL,
    annee_etude     TINYINT UNSIGNED NOT NULL,
    semestre        TINYINT UNSIGNED NOT NULL,   -- 1 ou 2
    type_eval       ENUM('100','30_70') NOT NULL DEFAULT '30_70',
    -- 100 = examen seul sur 100%
    -- 30_70 = devoir 30% + examen 70%
    credits         TINYINT UNSIGNED NOT NULL DEFAULT 3,
    coefficient     TINYINT UNSIGNED NOT NULL DEFAULT 1,
    description     TEXT,
    PRIMARY KEY (id),
    INDEX idx_filiere  (filiere),
    INDEX idx_semestre (semestre, annee_etude)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE 6 : MODULE_ENSEIGNANTS (1 ou 2 profs par module)
-- ============================================================
CREATE TABLE module_enseignants (
    module_id       INT UNSIGNED NOT NULL,
    enseignant_id   INT UNSIGNED NOT NULL,  -- users.id (role=enseignant)
    est_principal   BOOLEAN NOT NULL DEFAULT TRUE,
    PRIMARY KEY (module_id, enseignant_id),
    FOREIGN KEY (module_id)     REFERENCES modules(id) ON DELETE CASCADE,
    FOREIGN KEY (enseignant_id) REFERENCES users(id)   ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- TABLE 7 : MODULE_CLASSES (quels modules pour quelle classe)
-- ============================================================
CREATE TABLE module_classes (
    module_id   INT UNSIGNED NOT NULL,
    classe_id   INT UNSIGNED NOT NULL,
    annee_acad  VARCHAR(10)  NOT NULL,
    PRIMARY KEY (module_id, classe_id, annee_acad),
    FOREIGN KEY (module_id) REFERENCES modules(id)  ON DELETE CASCADE,
    FOREIGN KEY (classe_id) REFERENCES classes(id)  ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- TABLE 8 : NOTES (LMD — devoir 30% + examen 70%)
-- ============================================================
CREATE TABLE notes (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    etudiant_id     INT UNSIGNED    NOT NULL,
    module_id       INT UNSIGNED    NOT NULL,
    semestre        TINYINT UNSIGNED NOT NULL,
    annee_acad      VARCHAR(10)     NOT NULL,
    -- Pour module type 30_70 :
    note_devoir     DECIMAL(4,2)    DEFAULT NULL,  -- /20, compte pour 30%
    note_examen     DECIMAL(4,2)    DEFAULT NULL,  -- /20, compte pour 70%
    -- Pour module type 100 :
    -- note_devoir = NULL, note_examen = note finale
    note_finale     DECIMAL(4,2)    DEFAULT NULL,  -- calculée et stockée
    -- note_finale = ROUND(note_devoir*0.30 + note_examen*0.70, 2) si 30_70
    -- note_finale = note_examen si 100
    publie          BOOLEAN         NOT NULL DEFAULT FALSE,
    publie_le       TIMESTAMP       NULL,
    saisie_par      INT UNSIGNED    NOT NULL,
    saisie_le       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modifiee_le     TIMESTAMP       NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (etudiant_id) REFERENCES etudiants(id) ON DELETE CASCADE,
    FOREIGN KEY (module_id)   REFERENCES modules(id)   ON DELETE RESTRICT,
    FOREIGN KEY (saisie_par)  REFERENCES users(id)     ON DELETE RESTRICT,
    UNIQUE KEY uq_note (etudiant_id, module_id, semestre, annee_acad),
    INDEX idx_semestre  (semestre, annee_acad),
    INDEX idx_publie    (publie),
    INDEX idx_etudiant  (etudiant_id)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE 9 : BULLETINS
-- ============================================================
CREATE TABLE bulletins (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    etudiant_id     INT UNSIGNED    NOT NULL,
    semestre        TINYINT UNSIGNED NOT NULL,
    annee_acad      VARCHAR(10)     NOT NULL,
    moyenne_sem     DECIMAL(4,2),
    rang            SMALLINT UNSIGNED,
    mention         ENUM('TB','B','AB','P','R'),
    credits_obtenus TINYINT UNSIGNED,
    pdf_url         VARCHAR(500),
    cachet_hash     VARCHAR(128),
    genere_le       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    genere_par      INT UNSIGNED    NOT NULL,
    PRIMARY KEY (id),
    FOREIGN KEY (etudiant_id) REFERENCES etudiants(id) ON DELETE CASCADE,
    FOREIGN KEY (genere_par)  REFERENCES users(id)     ON DELETE RESTRICT,
    UNIQUE KEY uq_bulletin (etudiant_id, semestre, annee_acad)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE 10 : FRAIS DE SCOLARITE
-- ============================================================
CREATE TABLE frais_scolarite (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    etudiant_id     INT UNSIGNED    NOT NULL,
    annee_acad      VARCHAR(10)     NOT NULL,
    montant_total   DECIMAL(12,2)   NOT NULL,
    plan            ENUM('mensuel','trimestriel','semestriel','annuel') NOT NULL DEFAULT 'trimestriel',
    statut          ENUM('en_attente','en_cours','solde','retard','exonere') NOT NULL DEFAULT 'en_attente',
    date_echeance   DATE,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (etudiant_id) REFERENCES etudiants(id) ON DELETE CASCADE,
    UNIQUE KEY uq_frais (etudiant_id, annee_acad),
    INDEX idx_statut (statut)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE 11 : PAIEMENTS (Wave + Orange Money + Carte)
-- ============================================================
CREATE TABLE paiements (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    frais_id            INT UNSIGNED    NOT NULL,
    payeur_id           INT UNSIGNED    NOT NULL,
    montant             DECIMAL(12,2)   NOT NULL,
    moyen               ENUM('wave','orange_money','carte','virement','especes') NOT NULL,
    -- Wave
    wave_checkout_id    VARCHAR(200)    NULL,
    wave_fee            DECIMAL(10,2)   NULL,
    -- Orange Money
    om_order_id         VARCHAR(200)    NULL,
    om_token            VARCHAR(500)    NULL,
    -- Commun
    reference_ext       VARCHAR(200)    NULL,
    recu_numero         VARCHAR(50)     NOT NULL UNIQUE,
    recu_url            VARCHAR(500),
    qr_code             VARCHAR(500),
    callback_payload    JSON            NULL,
    statut              ENUM('en_attente','confirme','echec','rembourse') NOT NULL DEFAULT 'en_attente',
    note                TEXT,
    created_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    confirmed_at        TIMESTAMP       NULL,
    PRIMARY KEY (id),
    FOREIGN KEY (frais_id)  REFERENCES frais_scolarite(id) ON DELETE RESTRICT,
    FOREIGN KEY (payeur_id) REFERENCES users(id)           ON DELETE RESTRICT,
    INDEX idx_statut    (statut),
    INDEX idx_recu      (recu_numero),
    INDEX idx_reference (reference_ext)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE 12 : SALLES
-- ============================================================
CREATE TABLE salles (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    nom         VARCHAR(50)     NOT NULL UNIQUE,
    batiment    ENUM('UCAO','Claire_Afrique') NOT NULL,
    etage       TINYINT UNSIGNED NOT NULL,
    type        ENUM('cours','reunion','informatique','amphi','labo') NOT NULL DEFAULT 'cours',
    capacite    SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    equipements JSON,
    actif       BOOLEAN         NOT NULL DEFAULT TRUE,
    PRIMARY KEY (id),
    INDEX idx_batiment (batiment),
    INDEX idx_etage    (etage)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE 13 : EMPLOIS DU TEMPS
-- ============================================================
CREATE TABLE emplois_du_temps (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    classe_id       INT UNSIGNED,
    module_id       INT UNSIGNED,
    enseignant_id   INT UNSIGNED,
    salle_id        INT UNSIGNED    NOT NULL,
    titre           VARCHAR(200),
    date_debut      DATETIME        NOT NULL,
    date_fin        DATETIME        NOT NULL,
    -- Créneaux UCAO : 08:00-11:00 | 11:15-13:00 | 14:00-16:45 | révisions jusqu'à 20:00
    type_creneau    ENUM('cours1','cours2','cours3','revision','examen','rattrapage') NOT NULL DEFAULT 'cours1',
    recurrence      ENUM('aucune','hebdo') NOT NULL DEFAULT 'aucune',
    cree_par        INT UNSIGNED    NOT NULL,
    PRIMARY KEY (id),
    FOREIGN KEY (classe_id)     REFERENCES classes(id)  ON DELETE SET NULL,
    FOREIGN KEY (module_id)     REFERENCES modules(id)  ON DELETE SET NULL,
    FOREIGN KEY (enseignant_id) REFERENCES users(id)    ON DELETE SET NULL,
    FOREIGN KEY (salle_id)      REFERENCES salles(id)   ON DELETE RESTRICT,
    FOREIGN KEY (cree_par)      REFERENCES users(id)    ON DELETE RESTRICT,
    INDEX idx_date    (date_debut, date_fin),
    INDEX idx_classe  (classe_id),
    INDEX idx_salle   (salle_id)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE 14 : RESERVATIONS
-- ============================================================
CREATE TABLE reservations (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    salle_id        INT UNSIGNED    NOT NULL,
    demandeur_id    INT UNSIGNED    NOT NULL,
    objet           VARCHAR(300)    NOT NULL,
    participants    SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    date_debut      DATETIME        NOT NULL,
    date_fin        DATETIME        NOT NULL,
    statut          ENUM('en_attente','confirmee','refusee','annulee') NOT NULL DEFAULT 'en_attente',
    code_resa       VARCHAR(20)     NOT NULL UNIQUE,
    notes_admin     TEXT,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (salle_id)     REFERENCES salles(id) ON DELETE RESTRICT,
    FOREIGN KEY (demandeur_id) REFERENCES users(id)  ON DELETE RESTRICT,
    INDEX idx_date    (date_debut, date_fin),
    INDEX idx_statut  (statut)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE 15 : MENTORS
-- ============================================================
CREATE TABLE mentors (
    id                  INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    user_id             INT UNSIGNED    NOT NULL UNIQUE,
    bio                 TEXT,
    tarif               ENUM('gratuit','symbolique') NOT NULL DEFAULT 'gratuit',
    note_moyenne        DECIMAL(3,2)    DEFAULT 0.00,
    nb_sessions         INT UNSIGNED    DEFAULT 0,
    nb_etudiants        INT UNSIGNED    DEFAULT 0,
    taux_satisfaction   DECIMAL(3,2)    DEFAULT 0.00,
    -- Processus de validation 4 étapes
    etape_temoignage    BOOLEAN         DEFAULT FALSE,  -- Étape 1: enseignant valide
    etape_notes         BOOLEAN         DEFAULT FALSE,  -- Étape 2: moyenne >= 15/20
    etape_entretien     BOOLEAN         DEFAULT FALSE,  -- Étape 3: entretien Mme Bilong
    etape_quiz          BOOLEAN         DEFAULT FALSE,  -- Étape 4: quiz >= score min
    score_quiz          DECIMAL(5,2)    DEFAULT NULL,   -- Score /100
    quiz_passe_le       TIMESTAMP       NULL,
    quiz_retentable_le  TIMESTAMP       NULL,           -- Peut repasser après 2 semaines
    valide_par          INT UNSIGNED,
    valide_le           TIMESTAMP       NULL,
    actif               BOOLEAN         NOT NULL DEFAULT FALSE,
    PRIMARY KEY (id),
    FOREIGN KEY (user_id)    REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (valide_par) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- TABLE 16 : MENTOR_MODULES
-- ============================================================
CREATE TABLE mentor_modules (
    mentor_id       INT UNSIGNED    NOT NULL,
    module_id       INT UNSIGNED    NOT NULL,
    note_obtenue    DECIMAL(4,2)    NOT NULL,  -- >= 15.00 requis
    temoignage_txt  TEXT,                      -- Témoignage enseignant
    PRIMARY KEY (mentor_id, module_id),
    FOREIGN KEY (mentor_id) REFERENCES mentors(id)  ON DELETE CASCADE,
    FOREIGN KEY (module_id) REFERENCES modules(id)  ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- TABLE 17 : SESSIONS_MENTORAT
-- ============================================================
CREATE TABLE sessions_mentorat (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    mentor_id       INT UNSIGNED    NOT NULL,
    etudiant_id     INT UNSIGNED    NOT NULL,
    module_id       INT UNSIGNED    NOT NULL,
    type_session    ENUM('en_ligne','presentiel') NOT NULL DEFAULT 'en_ligne',
    date_session    DATE            NOT NULL,
    heure_debut     TIME            NOT NULL,
    heure_fin       TIME            NOT NULL,
    salle_id        INT UNSIGNED,
    lien_visio      VARCHAR(500),
    statut          ENUM('demandee','confirmee','annulee','terminee') NOT NULL DEFAULT 'demandee',
    note_etudiant   TINYINT UNSIGNED,   -- 1 à 5 étoiles
    commentaire     TEXT,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (mentor_id)   REFERENCES mentors(id)   ON DELETE RESTRICT,
    FOREIGN KEY (etudiant_id) REFERENCES etudiants(id) ON DELETE RESTRICT,
    FOREIGN KEY (module_id)   REFERENCES modules(id)   ON DELETE RESTRICT,
    FOREIGN KEY (salle_id)    REFERENCES salles(id)    ON DELETE SET NULL,
    INDEX idx_date     (date_session),
    INDEX idx_statut   (statut),
    INDEX idx_mentor   (mentor_id),
    INDEX idx_etudiant (etudiant_id)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE 18 : STAGES & RECOMMANDATIONS
-- ============================================================
CREATE TABLE stages (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    etudiant_id     INT UNSIGNED    NOT NULL,
    entreprise      VARCHAR(200)    NOT NULL,
    poste           VARCHAR(200),
    date_debut      DATE,
    date_fin        DATE,
    statut          ENUM('candidat','recommande','en_cours','termine','refuse') NOT NULL DEFAULT 'candidat',
    recommande_par  INT UNSIGNED,   -- user_id enseignant ou admin
    score_profil    DECIMAL(5,2),   -- Score auto calculé (notes + présence + mentorat)
    admin_valide    BOOLEAN         DEFAULT FALSE,
    contact_entreprise VARCHAR(200),
    notes           TEXT,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (etudiant_id)    REFERENCES etudiants(id) ON DELETE CASCADE,
    FOREIGN KEY (recommande_par) REFERENCES users(id)     ON DELETE SET NULL,
    INDEX idx_statut    (statut),
    INDEX idx_etudiant  (etudiant_id)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE 19 : NOTIFICATIONS
-- ============================================================
CREATE TABLE notifications (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED    NOT NULL,
    type        ENUM('paiement','note','bulletin','examen','mentorat','reservation','systeme','alerte') NOT NULL,
    titre       VARCHAR(200)    NOT NULL,
    corps       TEXT,
    lien        VARCHAR(500),
    lu          BOOLEAN         NOT NULL DEFAULT FALSE,
    canal       SET('in_app','email','sms') NOT NULL DEFAULT 'in_app',
    priorite    ENUM('basse','normale','haute','critique') DEFAULT 'normale',
    envoye_le   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    lu_le       TIMESTAMP       NULL,
    PRIMARY KEY (id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_lu  (user_id, lu),
    INDEX idx_type     (type),
    INDEX idx_priorite (priorite)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE 20 : AUDIT LOG
-- ============================================================
CREATE TABLE audit_log (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED,
    action      VARCHAR(100)    NOT NULL,
    entite      VARCHAR(50),
    entite_id   BIGINT UNSIGNED,
    detail      JSON,
    ip_address  VARCHAR(45),
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_action  (action),
    INDEX idx_user    (user_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE 21 : USER_PREFERENCES
-- ============================================================
CREATE TABLE user_preferences (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id             INT UNSIGNED NOT NULL UNIQUE,
    notif_email         BOOLEAN DEFAULT TRUE,
    notif_sms           BOOLEAN DEFAULT FALSE,
    notif_inapp         BOOLEAN DEFAULT TRUE,
    langue              VARCHAR(5) DEFAULT 'fr',
    theme               ENUM('light','dark','auto') DEFAULT 'auto',
    timezone            VARCHAR(50) DEFAULT 'Africa/Dakar',
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- VUES
-- ============================================================

-- Calcul de note finale selon le type d'évaluation
CREATE OR REPLACE VIEW v_notes_calculees AS
SELECT
    n.id, n.etudiant_id, n.module_id, n.semestre, n.annee_acad,
    m.type_eval,
    n.note_devoir, n.note_examen,
    CASE
        WHEN m.type_eval = '100'   THEN n.note_examen
        WHEN m.type_eval = '30_70' THEN ROUND(COALESCE(n.note_devoir,0)*0.30 + COALESCE(n.note_examen,0)*0.70, 2)
        ELSE NULL
    END AS note_finale_calculee,
    CASE
        WHEN (CASE WHEN m.type_eval='100' THEN n.note_examen ELSE ROUND(COALESCE(n.note_devoir,0)*0.30+COALESCE(n.note_examen,0)*0.70,2) END) >= 16 THEN 'TB'
        WHEN (CASE WHEN m.type_eval='100' THEN n.note_examen ELSE ROUND(COALESCE(n.note_devoir,0)*0.30+COALESCE(n.note_examen,0)*0.70,2) END) >= 14 THEN 'B'
        WHEN (CASE WHEN m.type_eval='100' THEN n.note_examen ELSE ROUND(COALESCE(n.note_devoir,0)*0.30+COALESCE(n.note_examen,0)*0.70,2) END) >= 12 THEN 'AB'
        WHEN (CASE WHEN m.type_eval='100' THEN n.note_examen ELSE ROUND(COALESCE(n.note_devoir,0)*0.30+COALESCE(n.note_examen,0)*0.70,2) END) >= 10 THEN 'P'
        ELSE 'F'
    END AS mention,
    n.publie, n.saisie_par
FROM notes n
JOIN modules m ON m.id = n.module_id;

-- État paiement par étudiant
CREATE OR REPLACE VIEW v_paiements_etudiants AS
SELECT
    u.id AS user_id, u.nom, u.prenom, u.email,
    c.code AS classe, c.annee_etude,
    f.annee_acad, f.montant_total, f.plan, f.statut AS statut_frais,
    COALESCE(SUM(CASE WHEN p.statut='confirme' THEN p.montant ELSE 0 END),0) AS montant_paye,
    f.montant_total - COALESCE(SUM(CASE WHEN p.statut='confirme' THEN p.montant ELSE 0 END),0) AS solde_restant
FROM users u
JOIN etudiants e ON e.user_id = u.id
JOIN classes c ON c.id = e.classe_id
JOIN frais_scolarite f ON f.etudiant_id = e.id
LEFT JOIN paiements p ON p.frais_id = f.id
GROUP BY u.id, f.id;

-- Moyenne par étudiant et par semestre
CREATE OR REPLACE VIEW v_moyennes_semestre AS
SELECT
    e.id AS etudiant_id, u.nom, u.prenom, c.code AS classe,
    n.semestre, n.annee_acad,
    ROUND(AVG(nc.note_finale_calculee), 2) AS moyenne,
    COUNT(DISTINCT n.module_id) AS nb_modules,
    SUM(CASE WHEN nc.note_finale_calculee >= 10 THEN 1 ELSE 0 END) AS modules_valides
FROM etudiants e
JOIN users u ON u.id = e.user_id
JOIN classes c ON c.id = e.classe_id
JOIN notes n ON n.etudiant_id = e.id
JOIN v_notes_calculees nc ON nc.id = n.id
WHERE n.publie = TRUE
GROUP BY e.id, n.semestre, n.annee_acad;

-- ============================================================
-- TRIGGER : Calculer note_finale avant INSERT/UPDATE
-- ============================================================
DELIMITER //
CREATE TRIGGER trg_note_finale_insert
BEFORE INSERT ON notes
FOR EACH ROW
BEGIN
    DECLARE v_type_eval VARCHAR(10);
    SELECT type_eval INTO v_type_eval FROM modules WHERE id = NEW.module_id;
    IF v_type_eval = '100' THEN
        SET NEW.note_finale = NEW.note_examen;
    ELSEIF v_type_eval = '30_70' AND NEW.note_devoir IS NOT NULL AND NEW.note_examen IS NOT NULL THEN
        SET NEW.note_finale = ROUND(NEW.note_devoir * 0.30 + NEW.note_examen * 0.70, 2);
    ELSE
        SET NEW.note_finale = NULL;
    END IF;
END//

CREATE TRIGGER trg_note_finale_update
BEFORE UPDATE ON notes
FOR EACH ROW
BEGIN
    DECLARE v_type_eval VARCHAR(10);
    SELECT type_eval INTO v_type_eval FROM modules WHERE id = NEW.module_id;
    IF v_type_eval = '100' THEN
        SET NEW.note_finale = NEW.note_examen;
    ELSEIF v_type_eval = '30_70' AND NEW.note_devoir IS NOT NULL AND NEW.note_examen IS NOT NULL THEN
        SET NEW.note_finale = ROUND(NEW.note_devoir * 0.30 + NEW.note_examen * 0.70, 2);
    ELSE
        SET NEW.note_finale = NULL;
    END IF;
END//
DELIMITER ;

-- ============================================================
-- ============================================================
-- DONNÉES DE TEST
-- ============================================================
-- ============================================================

-- ============================================================
-- CLASSES
-- ============================================================
INSERT INTO classes (code, nom, filiere, annee_etude, capacite, annee_acad) VALUES
('LIG1-1', 'Licence Informatique de Gestion 1 — Groupe 1', 'Informatique de Gestion', 1, 40, '2025-2026'),
('LIG1-2', 'Licence Informatique de Gestion 1 — Groupe 2', 'Informatique de Gestion', 1, 40, '2025-2026'),
('LIG2-1', 'Licence Informatique de Gestion 2 — Groupe 1', 'Informatique de Gestion', 2, 40, '2025-2026');

-- ============================================================
-- SALLES — UCAO (23 salles) + Claire Afrique (à compléter)
-- ============================================================
INSERT INTO salles (nom, batiment, etage, type, capacite, equipements) VALUES
-- UCAO 1er étage (3 salles)
('UCAO-1-1', 'UCAO', 1, 'cours', 35, '["Tableau","Projecteur","Wi-Fi"]'),
('UCAO-1-2', 'UCAO', 1, 'cours', 35, '["Tableau","Projecteur","Wi-Fi"]'),
('UCAO-1-3', 'UCAO', 1, 'cours', 30, '["Tableau","Wi-Fi"]'),
-- UCAO 2e étage (5 salles)
('UCAO-2-1', 'UCAO', 2, 'cours', 35, '["Tableau","Projecteur","Wi-Fi"]'),
('UCAO-2-2', 'UCAO', 2, 'cours', 35, '["Tableau","Projecteur","Wi-Fi"]'),
('UCAO-2-3', 'UCAO', 2, 'cours', 30, '["Tableau","Wi-Fi"]'),
('UCAO-2-4', 'UCAO', 2, 'informatique', 25, '["PC×25","Projecteur","Wi-Fi"]'),
('UCAO-2-5', 'UCAO', 2, 'reunion', 15, '["Écran TV","Wi-Fi","Table ronde"]'),
-- UCAO 3e étage (3 salles)
('UCAO-3-1', 'UCAO', 3, 'cours', 40, '["Tableau","Projecteur","Wi-Fi","Climatisation"]'),
('UCAO-3-2', 'UCAO', 3, 'cours', 40, '["Tableau","Projecteur","Wi-Fi","Climatisation"]'),
('UCAO-3-3', 'UCAO', 3, 'informatique', 30, '["PC×30","Projecteur","Wi-Fi"]'),
-- UCAO 4e étage (5 salles)
('UCAO-4-1', 'UCAO', 4, 'cours', 45, '["Tableau","Projecteur","Wi-Fi","Micro"]'),
('UCAO-4-2', 'UCAO', 4, 'cours', 45, '["Tableau","Projecteur","Wi-Fi","Micro"]'),
('UCAO-4-3', 'UCAO', 4, 'cours', 35, '["Tableau","Projecteur","Wi-Fi"]'),
('UCAO-4-4', 'UCAO', 4, 'informatique', 28, '["PC×28","Projecteur","Wi-Fi"]'),
('UCAO-4-5', 'UCAO', 4, 'reunion', 12, '["Écran TV","Wi-Fi"]'),
-- UCAO 5e étage (5 salles)
('UCAO-5-1', 'UCAO', 5, 'cours', 50, '["Tableau","Projecteur","Wi-Fi","Micro","Climatisation"]'),
('UCAO-5-2', 'UCAO', 5, 'cours', 50, '["Tableau","Projecteur","Wi-Fi","Micro","Climatisation"]'),
('UCAO-5-3', 'UCAO', 5, 'cours', 35, '["Tableau","Projecteur","Wi-Fi"]'),
('UCAO-5-4', 'UCAO', 5, 'informatique', 30, '["PC×30","Projecteur","Wi-Fi"]'),
('UCAO-5-5', 'UCAO', 5, 'labo', 20, '["PC×20","Matériel réseau","Wi-Fi"]'),
-- UCAO 6e étage (2 salles)
('UCAO-6-1', 'UCAO', 6, 'amphi', 120, '["Micro","Projecteur","Climatisation","Gradins"]'),
('UCAO-6-2', 'UCAO', 6, 'amphi', 100, '["Micro","Projecteur","Climatisation","Gradins"]');
-- NOTE: Salles Claire Afrique à ajouter quand les détails seront disponibles

-- ============================================================
-- MODULES — L1 Semestre 1 (11 modules)
-- ============================================================
INSERT INTO modules (code, nom, filiere, annee_etude, semestre, type_eval, credits, coefficient) VALUES
('IG-L1S1-01', 'Architecture des Ordinateurs',              'Informatique de Gestion', 1, 1, '30_70', 3, 2),
('IG-L1S1-02', 'Anglais Technique',                         'Informatique de Gestion', 1, 1, '100',   2, 1),
('IG-L1S1-03', 'Algorithme 1',                              'Informatique de Gestion', 1, 1, '30_70', 3, 3),
('IG-L1S1-04', 'Système d\'Exploitation',                   'Informatique de Gestion', 1, 1, '30_70', 3, 2),
('IG-L1S1-05', 'Programmation 1 (C#)',                      'Informatique de Gestion', 1, 1, '30_70', 4, 3),
('IG-L1S1-06', 'Mathématiques Générales',                   'Informatique de Gestion', 1, 1, '100',   3, 2),
('IG-L1S1-07', 'Mathématiques Financières',                 'Informatique de Gestion', 1, 1, '100',   2, 2),
('IG-L1S1-08', 'Comptabilité Générale',                     'Informatique de Gestion', 1, 1, '30_70', 3, 2),
('IG-L1S1-09', 'Économie',                                  'Informatique de Gestion', 1, 1, '30_70', 2, 1),
('IG-L1S1-10', 'Technique d\'Expression en Langue Française','Informatique de Gestion', 1, 1, '100',   2, 1),
('IG-L1S1-11', 'Atelier Maintenance',                       'Informatique de Gestion', 1, 1, '30_70', 2, 2);

-- ============================================================
-- MODULES — L1 Semestre 2 (10 modules)
-- ============================================================
INSERT INTO modules (code, nom, filiere, annee_etude, semestre, type_eval, credits, coefficient) VALUES
('IG-L1S2-01', 'Probabilités',                              'Informatique de Gestion', 1, 2, '100',   2, 2),
('IG-L1S2-02', 'Statistiques',                              'Informatique de Gestion', 1, 2, '100',   2, 2),
('IG-L1S2-03', 'Algorithme 2',                              'Informatique de Gestion', 1, 2, '30_70', 3, 3),
('IG-L1S2-04', 'Programmation 2 (C#)',                      'Informatique de Gestion', 1, 2, '30_70', 4, 3),
('IG-L1S2-05', 'Économie d\'Entreprise',                    'Informatique de Gestion', 1, 2, '30_70', 2, 1),
('IG-L1S2-06', 'Initiation au Management',                  'Informatique de Gestion', 1, 2, '30_70', 2, 1),
('IG-L1S2-07', 'Site Web Statiques (HTML/CSS/JS)',          'Informatique de Gestion', 1, 2, '30_70', 3, 2),
('IG-L1S2-08', 'Framework Frontend (Bootstrap/jQuery)',     'Informatique de Gestion', 1, 2, '30_70', 3, 2),
('IG-L1S2-09', 'SGBDR',                                     'Informatique de Gestion', 1, 2, '30_70', 3, 3),
('IG-L1S2-10', 'MERISE',                                    'Informatique de Gestion', 1, 2, '30_70', 2, 2);

-- ============================================================
-- MODULE_CLASSES : lier modules L1 aux classes LIG1-1 et LIG1-2
-- ============================================================
INSERT INTO module_classes (module_id, classe_id, annee_acad)
SELECT m.id, c.id, '2025-2026'
FROM modules m, classes c
WHERE m.annee_etude = 1 AND c.code IN ('LIG1-1','LIG1-2');

-- ============================================================
-- USERS — Admin & Chef Admin
-- ============================================================
-- MDP: EduConnect2026! => $2y$12$... (bcrypt — remplacer par hash réel)
INSERT INTO users (numero_ucao, nom, prenom, email, telephone, password_hash, role, statut) VALUES
('UCAO-ADM-001', 'Bilong',   'Marie',     'bilong@ucao.edu.sn',         '+221771000001', '$2y$12$HASH_BILONG',        'chef_admin',  'actif'),
('UCAO-ADM-002', 'Traoré',   'Moussa',    'traore@ucao.edu.sn',         '+221771000002', '$2y$12$HASH_TRAORE',        'admin',       'actif');

-- ============================================================
-- USERS — Enseignants (13 profs pour 21 modules)
-- ============================================================
INSERT INTO users (numero_ucao, nom, prenom, email, telephone, password_hash, role, statut) VALUES
('UCAO-PROF-01', 'Ndiaye',   'Ibrahima',  'i.ndiaye@ucao.edu.sn',       '+221771000011', '$2y$12$HASH_PROF01', 'enseignant', 'actif'),
('UCAO-PROF-02', 'Sall',     'Aminata',   'a.sall@ucao.edu.sn',          '+221771000012', '$2y$12$HASH_PROF02', 'enseignant', 'actif'),
('UCAO-PROF-03', 'Diop',     'Mamadou',   'm.diop@ucao.edu.sn',          '+221771000013', '$2y$12$HASH_PROF03', 'enseignant', 'actif'),
('UCAO-PROF-04', 'Fall',     'Rokhaya',   'r.fall@ucao.edu.sn',          '+221771000014', '$2y$12$HASH_PROF04', 'enseignant', 'actif'),
('UCAO-PROF-05', 'Touré',    'Seydou',    's.toure@ucao.edu.sn',         '+221771000015', '$2y$12$HASH_PROF05', 'enseignant', 'actif'),
('UCAO-PROF-06', 'Kane',     'Fatou',     'f.kane@ucao.edu.sn',          '+221771000016', '$2y$12$HASH_PROF06', 'enseignant', 'actif'),
('UCAO-PROF-07', 'Sarr',     'Cheikh',    'c.sarr@ucao.edu.sn',          '+221771000017', '$2y$12$HASH_PROF07', 'enseignant', 'actif'),
('UCAO-PROF-08', 'Mbaye',    'Aissatou',  'a.mbaye@ucao.edu.sn',         '+221771000018', '$2y$12$HASH_PROF08', 'enseignant', 'actif'),
('UCAO-PROF-09', 'Gaye',     'Lamine',    'l.gaye@ucao.edu.sn',          '+221771000019', '$2y$12$HASH_PROF09', 'enseignant', 'actif'),
('UCAO-PROF-10', 'Diouf',    'Ndéye',     'n.diouf@ucao.edu.sn',         '+221771000020', '$2y$12$HASH_PROF10', 'enseignant', 'actif'),
('UCAO-PROF-11', 'Cissé',    'Babacar',   'b.cisse@ucao.edu.sn',         '+221771000021', '$2y$12$HASH_PROF11', 'enseignant', 'actif'),
('UCAO-PROF-12', 'Ba',       'Mariama',   'm.ba@ucao.edu.sn',             '+221771000022', '$2y$12$HASH_PROF12', 'enseignant', 'actif'),
('UCAO-PROF-13', 'Faye',     'Omar',      'o.faye@ucao.edu.sn',           '+221771000023', '$2y$12$HASH_PROF13', 'enseignant', 'actif');

-- Enseignants étendus
INSERT INTO enseignants (user_id, grade, departement) VALUES
(3,'Maître de Conférences','Informatique'),
(4,'Assistant','Langues'),
(5,'Maître de Conférences','Informatique'),
(6,'Assistant','Informatique'),
(7,'Maître de Conférences','Informatique'),
(8,'Assistant','Mathématiques'),
(9,'Maître de Conférences','Mathématiques'),
(10,'Assistant','Comptabilité'),
(11,'Maître de Conférences','Économie'),
(12,'Assistant','Langues'),
(13,'Assistant','Informatique'),
(14,'Maître de Conférences','Gestion'),
(15,'Assistant','Informatique');

-- ============================================================
-- MODULE_ENSEIGNANTS — attribution 1-2 profs par module
-- ============================================================
INSERT INTO module_enseignants (module_id, enseignant_id, est_principal) VALUES
-- S1
(1,  3,  TRUE),  -- Archi ordis → Ndiaye (principal)
(1,  5,  FALSE), -- Archi ordis → Diop (secondaire)
(2,  4,  TRUE),  -- Anglais → Sall
(3,  5,  TRUE),  -- Algo 1 → Diop
(3,  7,  FALSE), -- Algo 1 → Touré (TD)
(4,  3,  TRUE),  -- Sys exploit → Ndiaye
(5,  7,  TRUE),  -- Prog 1 C# → Touré
(5,  11, FALSE), -- Prog 1 → Cissé (TP)
(6,  8,  TRUE),  -- Maths Géné → Kane
(7,  9,  TRUE),  -- Maths Fin → Sarr
(8,  10, TRUE),  -- Compta → Mbaye
(9,  11, TRUE),  -- Économie → Gaye
(10, 12, TRUE),  -- TELF → Diouf
(11, 13, TRUE),  -- Atelier → Cissé
(11, 3,  FALSE), -- Atelier → Ndiaye (encadrement)
-- S2
(12, 9,  TRUE),  -- Probas → Sarr
(13, 8,  TRUE),  -- Stats → Kane
(14, 7,  TRUE),  -- Algo 2 → Touré
(14, 5,  FALSE), -- Algo 2 → Diop (TD)
(15, 7,  TRUE),  -- Prog 2 C# → Touré
(15, 13, FALSE), -- Prog 2 → Faye (TP)
(16, 11, TRUE),  -- Éco Entreprise → Gaye
(17, 14, TRUE),  -- Management → Ba
(18, 6,  TRUE),  -- Site web → Fall
(18, 13, FALSE), -- Site web → Faye (TP)
(19, 6,  TRUE),  -- Framework → Fall
(19, 13, FALSE), -- Framework → Faye (TP)
(20, 5,  TRUE),  -- SGBDR → Diop
(20, 15, FALSE), -- SGBDR → Faye (TP)
(21, 3,  TRUE),  -- MERISE → Ndiaye
(21, 15, FALSE); -- MERISE → Faye (TD)

-- ============================================================
-- USERS — Étudiants LIG 1-1 (40 étudiants, IDs 16–55)
-- ============================================================
INSERT INTO users (numero_ucao, nom, prenom, email, telephone, password_hash, role, statut) VALUES
('UCAO-2026-001','Diallo',   'Adama',     'adama.diallo@etud.ucao.sn',   '+221770100001','$2y$12$HASH_ETU001','etudiant','actif'),
('UCAO-2026-002','Sow',      'Marie',     'marie.sow@etud.ucao.sn',      '+221770100002','$2y$12$HASH_ETU002','etudiant','actif'),
('UCAO-2026-003','Ndiaye',   'Ibou',      'ibou.ndiaye@etud.ucao.sn',    '+221770100003','$2y$12$HASH_ETU003','etudiant','actif'),
('UCAO-2026-004','Fall',     'Aïssa',     'aissa.fall@etud.ucao.sn',     '+221770100004','$2y$12$HASH_ETU004','etudiant','actif'),
('UCAO-2026-005','Diouf',    'Mamadou',   'mamadou.diouf@etud.ucao.sn',  '+221770100005','$2y$12$HASH_ETU005','etudiant','actif'),
('UCAO-2026-006','Sarr',     'Fatou',     'fatou.sarr@etud.ucao.sn',     '+221770100006','$2y$12$HASH_ETU006','etudiant','actif'),
('UCAO-2026-007','Ba',       'Cheikh',    'cheikh.ba@etud.ucao.sn',      '+221770100007','$2y$12$HASH_ETU007','etudiant','actif'),
('UCAO-2026-008','Gaye',     'Ndéye',     'ndeye.gaye@etud.ucao.sn',     '+221770100008','$2y$12$HASH_ETU008','etudiant','actif'),
('UCAO-2026-009','Touré',    'Seydou',    'seydou.toure@etud.ucao.sn',   '+221770100009','$2y$12$HASH_ETU009','etudiant','actif'),
('UCAO-2026-010','Cissé',    'Aminata',   'aminata.cisse@etud.ucao.sn',  '+221770100010','$2y$12$HASH_ETU010','etudiant','actif'),
('UCAO-2026-011','Kane',     'Lamine',    'lamine.kane@etud.ucao.sn',    '+221770100011','$2y$12$HASH_ETU011','etudiant','actif'),
('UCAO-2026-012','Mbaye',    'Rokhaya',   'rokhaya.mbaye@etud.ucao.sn',  '+221770100012','$2y$12$HASH_ETU012','etudiant','actif'),
('UCAO-2026-013','Diop',     'Omar',      'omar.diop@etud.ucao.sn',      '+221770100013','$2y$12$HASH_ETU013','etudiant','actif'),
('UCAO-2026-014','Faye',     'Bineta',    'bineta.faye@etud.ucao.sn',    '+221770100014','$2y$12$HASH_ETU014','etudiant','actif'),
('UCAO-2026-015','Ly',       'Pape',      'pape.ly@etud.ucao.sn',        '+221770100015','$2y$12$HASH_ETU015','etudiant','actif'),
('UCAO-2026-016','Niang',    'Mariama',   'mariama.niang@etud.ucao.sn',  '+221770100016','$2y$12$HASH_ETU016','etudiant','actif'),
('UCAO-2026-017','Dieng',    'Ibrahima',  'ibrahima.dieng@etud.ucao.sn', '+221770100017','$2y$12$HASH_ETU017','etudiant','actif'),
('UCAO-2026-018','Seck',     'Khadija',   'khadija.seck@etud.ucao.sn',   '+221770100018','$2y$12$HASH_ETU018','etudiant','actif'),
('UCAO-2026-019','Konaté',   'Moussa',    'moussa.konate@etud.ucao.sn',  '+221770100019','$2y$12$HASH_ETU019','etudiant','actif'),
('UCAO-2026-020','Traoré',   'Kadiatou',  'kadiatou.traore@etud.ucao.sn','+221770100020','$2y$12$HASH_ETU020','etudiant','actif'),
('UCAO-2026-021','Balde',    'Aliou',     'aliou.balde@etud.ucao.sn',    '+221770100021','$2y$12$HASH_ETU021','etudiant','actif'),
('UCAO-2026-022','Camara',   'Aïchatou',  'aichatou.camara@etud.ucao.sn','+221770100022','$2y$12$HASH_ETU022','etudiant','actif'),
('UCAO-2026-023','Barry',    'Souleymane','souleymane.barry@etud.ucao.sn','+221770100023','$2y$12$HASH_ETU023','etudiant','actif'),
('UCAO-2026-024','Dramé',    'Fatoumata', 'fatoumata.drame@etud.ucao.sn','+221770100024','$2y$12$HASH_ETU024','etudiant','actif'),
('UCAO-2026-025','Sylla',    'Mamadou',   'mamadou.sylla@etud.ucao.sn',  '+221770100025','$2y$12$HASH_ETU025','etudiant','actif'),
('UCAO-2026-026','Bah',      'Aïssata',   'aissata.bah@etud.ucao.sn',    '+221770100026','$2y$12$HASH_ETU026','etudiant','actif'),
('UCAO-2026-027','Coulibaly','Lassana',   'lassana.coulibaly@etud.ucao.sn','+221770100027','$2y$12$HASH_ETU027','etudiant','actif'),
('UCAO-2026-028','Keïta',    'Mariam',    'mariam.keita@etud.ucao.sn',   '+221770100028','$2y$12$HASH_ETU028','etudiant','actif'),
('UCAO-2026-029','Doumbia',  'Bakary',    'bakary.doumbia@etud.ucao.sn', '+221770100029','$2y$12$HASH_ETU029','etudiant','actif'),
('UCAO-2026-030','Sanogo',   'Tenin',     'tenin.sanogo@etud.ucao.sn',   '+221770100030','$2y$12$HASH_ETU030','etudiant','actif'),
('UCAO-2026-031','Ouattara', 'Drissa',    'drissa.ouattara@etud.ucao.sn','+221770100031','$2y$12$HASH_ETU031','etudiant','actif'),
('UCAO-2026-032','Kourouma', 'Hawa',      'hawa.kourouma@etud.ucao.sn',  '+221770100032','$2y$12$HASH_ETU032','etudiant','actif'),
('UCAO-2026-033','Diallo',   'Mamadou',   'mamadou2.diallo@etud.ucao.sn','+221770100033','$2y$12$HASH_ETU033','etudiant','actif'),
('UCAO-2026-034','Diabaté',  'Safiatou',  'safiatou.diabate@etud.ucao.sn','+221770100034','$2y$12$HASH_ETU034','etudiant','actif'),
('UCAO-2026-035','Koné',     'Moussa',    'moussa.kone@etud.ucao.sn',    '+221770100035','$2y$12$HASH_ETU035','etudiant','actif'),
('UCAO-2026-036','Diallo',   'Oumou',     'oumou.diallo@etud.ucao.sn',   '+221770100036','$2y$12$HASH_ETU036','etudiant','actif'),
('UCAO-2026-037','Bamba',    'Cheick',    'cheick.bamba@etud.ucao.sn',   '+221770100037','$2y$12$HASH_ETU037','etudiant','actif'),
('UCAO-2026-038','Sangaré',  'Maïmouna',  'maimouna.sangare@etud.ucao.sn','+221770100038','$2y$12$HASH_ETU038','etudiant','actif'),
('UCAO-2026-039','Touré',    'Youssouf',  'youssouf.toure@etud.ucao.sn', '+221770100039','$2y$12$HASH_ETU039','etudiant','actif'),
('UCAO-2026-040','Diallo',   'Aïcha',     'aicha.diallo@etud.ucao.sn',   '+221770100040','$2y$12$HASH_ETU040','etudiant','actif');

-- ============================================================
-- USERS — Étudiants LIG 1-2 (40 étudiants, IDs 56–95)
-- ============================================================
INSERT INTO users (numero_ucao, nom, prenom, email, telephone, password_hash, role, statut) VALUES
('UCAO-2026-041','Sow',      'Boubacar',  'boubacar.sow@etud.ucao.sn',   '+221770200001','$2y$12$HASH_ETU041','etudiant','actif'),
('UCAO-2026-042','Ndiaye',   'Mame',      'mame.ndiaye@etud.ucao.sn',    '+221770200002','$2y$12$HASH_ETU042','etudiant','actif'),
('UCAO-2026-043','Diouf',    'Penda',     'penda.diouf@etud.ucao.sn',    '+221770200003','$2y$12$HASH_ETU043','etudiant','actif'),
('UCAO-2026-044','Fall',     'Aliou',     'aliou.fall@etud.ucao.sn',     '+221770200004','$2y$12$HASH_ETU044','etudiant','actif'),
('UCAO-2026-045','Sarr',     'Khadija',   'khadija.sarr@etud.ucao.sn',   '+221770200005','$2y$12$HASH_ETU045','etudiant','actif'),
('UCAO-2026-046','Ba',       'Aminatou',  'aminatou.ba@etud.ucao.sn',    '+221770200006','$2y$12$HASH_ETU046','etudiant','actif'),
('UCAO-2026-047','Gaye',     'Babacar',   'babacar.gaye@etud.ucao.sn',   '+221770200007','$2y$12$HASH_ETU047','etudiant','actif'),
('UCAO-2026-048','Touré',    'Maimouna',  'maimouna.toure@etud.ucao.sn', '+221770200008','$2y$12$HASH_ETU048','etudiant','actif'),
('UCAO-2026-049','Cissé',    'Samba',     'samba.cisse@etud.ucao.sn',    '+221770200009','$2y$12$HASH_ETU049','etudiant','actif'),
('UCAO-2026-050','Kane',     'Astou',     'astou.kane@etud.ucao.sn',     '+221770200010','$2y$12$HASH_ETU050','etudiant','actif'),
('UCAO-2026-051','Mbaye',    'Cheikh',    'cheikh.mbaye@etud.ucao.sn',   '+221770200011','$2y$12$HASH_ETU051','etudiant','actif'),
('UCAO-2026-052','Diop',     'Fatou',     'fatou.diop@etud.ucao.sn',     '+221770200012','$2y$12$HASH_ETU052','etudiant','actif'),
('UCAO-2026-053','Faye',     'Ibou',      'ibou.faye@etud.ucao.sn',      '+221770200013','$2y$12$HASH_ETU053','etudiant','actif'),
('UCAO-2026-054','Ly',       'Ndeye',     'ndeye.ly@etud.ucao.sn',       '+221770200014','$2y$12$HASH_ETU054','etudiant','actif'),
('UCAO-2026-055','Niang',    'Youssou',   'youssou.niang@etud.ucao.sn',  '+221770200015','$2y$12$HASH_ETU055','etudiant','actif'),
('UCAO-2026-056','Dieng',    'Aïda',      'aida.dieng@etud.ucao.sn',     '+221770200016','$2y$12$HASH_ETU056','etudiant','actif'),
('UCAO-2026-057','Seck',     'Abdoulaye', 'abdoulaye.seck@etud.ucao.sn', '+221770200017','$2y$12$HASH_ETU057','etudiant','actif'),
('UCAO-2026-058','Konaté',   'Assétou',   'assetou.konate@etud.ucao.sn', '+221770200018','$2y$12$HASH_ETU058','etudiant','actif'),
('UCAO-2026-059','Traoré',   'Seydou',    'seydou.traore@etud.ucao.sn',  '+221770200019','$2y$12$HASH_ETU059','etudiant','actif'),
('UCAO-2026-060','Balde',    'Hawa',      'hawa.balde@etud.ucao.sn',     '+221770200020','$2y$12$HASH_ETU060','etudiant','actif'),
('UCAO-2026-061','Camara',   'Mohamed',   'mohamed.camara@etud.ucao.sn', '+221770200021','$2y$12$HASH_ETU061','etudiant','actif'),
('UCAO-2026-062','Barry',    'Kadiatou',  'kadiatou.barry@etud.ucao.sn', '+221770200022','$2y$12$HASH_ETU062','etudiant','actif'),
('UCAO-2026-063','Dramé',    'Lamine',    'lamine.drame@etud.ucao.sn',   '+221770200023','$2y$12$HASH_ETU063','etudiant','actif'),
('UCAO-2026-064','Sylla',    'Rougui',    'rougui.sylla@etud.ucao.sn',   '+221770200024','$2y$12$HASH_ETU064','etudiant','actif'),
('UCAO-2026-065','Bah',      'Thierno',   'thierno.bah@etud.ucao.sn',    '+221770200025','$2y$12$HASH_ETU065','etudiant','actif'),
('UCAO-2026-066','Coulibaly','Oumou',     'oumou.coulibaly@etud.ucao.sn','+221770200026','$2y$12$HASH_ETU066','etudiant','actif'),
('UCAO-2026-067','Keïta',    'Ibrahima',  'ibrahima.keita@etud.ucao.sn', '+221770200027','$2y$12$HASH_ETU067','etudiant','actif'),
('UCAO-2026-068','Doumbia',  'Tenin',     'tenin.doumbia@etud.ucao.sn',  '+221770200028','$2y$12$HASH_ETU068','etudiant','actif'),
('UCAO-2026-069','Sanogo',   'Bintou',    'bintou.sanogo@etud.ucao.sn',  '+221770200029','$2y$12$HASH_ETU069','etudiant','actif'),
('UCAO-2026-070','Ouattara', 'Maïmouna',  'maimouna.ouattara@etud.ucao.sn','+221770200030','$2y$12$HASH_ETU070','etudiant','actif'),
('UCAO-2026-071','Kourouma', 'Aboubacar', 'aboubacar.kourouma@etud.ucao.sn','+221770200031','$2y$12$HASH_ETU071','etudiant','actif'),
('UCAO-2026-072','Diallo',   'Fatoumata', 'fatoumata.diallo@etud.ucao.sn','+221770200032','$2y$12$HASH_ETU072','etudiant','actif'),
('UCAO-2026-073','Diabaté',  'Issiaka',   'issiaka.diabate@etud.ucao.sn','+221770200033','$2y$12$HASH_ETU073','etudiant','actif'),
('UCAO-2026-074','Koné',     'Awa',       'awa.kone@etud.ucao.sn',       '+221770200034','$2y$12$HASH_ETU074','etudiant','actif'),
('UCAO-2026-075','Diallo',   'Moussa',    'moussa.diallo@etud.ucao.sn',  '+221770200035','$2y$12$HASH_ETU075','etudiant','actif'),
('UCAO-2026-076','Bamba',    'Kali',      'kali.bamba@etud.ucao.sn',     '+221770200036','$2y$12$HASH_ETU076','etudiant','actif'),
('UCAO-2026-077','Sangaré',  'Rokia',     'rokia.sangare@etud.ucao.sn',  '+221770200037','$2y$12$HASH_ETU077','etudiant','actif'),
('UCAO-2026-078','Touré',    'Nana',      'nana.toure@etud.ucao.sn',     '+221770200038','$2y$12$HASH_ETU078','etudiant','actif'),
('UCAO-2026-079','Diallo',   'Souleymane','souleymane.diallo@etud.ucao.sn','+221770200039','$2y$12$HASH_ETU079','etudiant','actif'),
('UCAO-2026-080','Sow',      'Kadiatou',  'kadiatou.sow@etud.ucao.sn',   '+221770200040','$2y$12$HASH_ETU080','etudiant','actif');

-- ============================================================
-- USERS — Étudiants LIG 2-1 (40 étudiants, IDs 96–135)
-- ============================================================
INSERT INTO users (numero_ucao, nom, prenom, email, telephone, password_hash, role, statut) VALUES
('UCAO-2025-001','Ndiaye',   'Souleymane','souleymane.ndiaye@etud.ucao.sn','+221770300001','$2y$12$HASH_ETU081','etudiant','actif'),
('UCAO-2025-002','Sarr',     'Mariama',   'mariama.sarr@etud.ucao.sn',   '+221770300002','$2y$12$HASH_ETU082','etudiant','actif'),
('UCAO-2025-003','Diallo',   'Ibrahima',  'ibrahima2.diallo@etud.ucao.sn','+221770300003','$2y$12$HASH_ETU083','etudiant','actif'),
('UCAO-2025-004','Fall',     'Mame',      'mame.fall@etud.ucao.sn',      '+221770300004','$2y$12$HASH_ETU084','etudiant','actif'),
('UCAO-2025-005','Diouf',    'Alioune',   'alioune.diouf@etud.ucao.sn',  '+221770300005','$2y$12$HASH_ETU085','etudiant','actif'),
('UCAO-2025-006','Gaye',     'Oumou',     'oumou.gaye@etud.ucao.sn',     '+221770300006','$2y$12$HASH_ETU086','etudiant','actif'),
('UCAO-2025-007','Ba',       'Thierno',   'thierno.ba@etud.ucao.sn',     '+221770300007','$2y$12$HASH_ETU087','etudiant','actif'),
('UCAO-2025-008','Touré',    'Mariam',    'mariam.toure@etud.ucao.sn',   '+221770300008','$2y$12$HASH_ETU088','etudiant','actif'),
('UCAO-2025-009','Cissé',    'Mamadou',   'mamadou2.cisse@etud.ucao.sn', '+221770300009','$2y$12$HASH_ETU089','etudiant','actif'),
('UCAO-2025-010','Kane',     'Ndéye',     'ndeye.kane@etud.ucao.sn',     '+221770300010','$2y$12$HASH_ETU090','etudiant','actif'),
('UCAO-2025-011','Mbaye',    'Ousmane',   'ousmane.mbaye@etud.ucao.sn',  '+221770300011','$2y$12$HASH_ETU091','etudiant','actif'),
('UCAO-2025-012','Diop',     'Aïcha',     'aicha.diop@etud.ucao.sn',     '+221770300012','$2y$12$HASH_ETU092','etudiant','actif'),
('UCAO-2025-013','Faye',     'Seydou',    'seydou.faye@etud.ucao.sn',    '+221770300013','$2y$12$HASH_ETU093','etudiant','actif'),
('UCAO-2025-014','Ly',       'Aminata',   'aminata.ly@etud.ucao.sn',     '+221770300014','$2y$12$HASH_ETU094','etudiant','actif'),
('UCAO-2025-015','Niang',    'Cheikh',    'cheikh.niang@etud.ucao.sn',   '+221770300015','$2y$12$HASH_ETU095','etudiant','actif'),
('UCAO-2025-016','Dieng',    'Rokhaya',   'rokhaya.dieng@etud.ucao.sn',  '+221770300016','$2y$12$HASH_ETU096','etudiant','actif'),
('UCAO-2025-017','Seck',     'Lamine',    'lamine.seck@etud.ucao.sn',    '+221770300017','$2y$12$HASH_ETU097','etudiant','actif'),
('UCAO-2025-018','Konaté',   'Fatou',     'fatou.konate@etud.ucao.sn',   '+221770300018','$2y$12$HASH_ETU098','etudiant','actif'),
('UCAO-2025-019','Traoré',   'Bakary',    'bakary.traore@etud.ucao.sn',  '+221770300019','$2y$12$HASH_ETU099','etudiant','actif'),
('UCAO-2025-020','Balde',    'Oumou',     'oumou.balde@etud.ucao.sn',    '+221770300020','$2y$12$HASH_ETU100','etudiant','actif'),
('UCAO-2025-021','Camara',   'Samba',     'samba.camara@etud.ucao.sn',   '+221770300021','$2y$12$HASH_ETU101','etudiant','actif'),
('UCAO-2025-022','Barry',    'Aïssata',   'aissata.barry@etud.ucao.sn',  '+221770300022','$2y$12$HASH_ETU102','etudiant','actif'),
('UCAO-2025-023','Dramé',    'Moussa',    'moussa.drame@etud.ucao.sn',   '+221770300023','$2y$12$HASH_ETU103','etudiant','actif'),
('UCAO-2025-024','Sylla',    'Kadiatou',  'kadiatou.sylla@etud.ucao.sn', '+221770300024','$2y$12$HASH_ETU104','etudiant','actif'),
('UCAO-2025-025','Bah',      'Ibrahim',   'ibrahim.bah@etud.ucao.sn',    '+221770300025','$2y$12$HASH_ETU105','etudiant','actif'),
('UCAO-2025-026','Coulibaly','Fatoumata', 'fatoumata.coulibaly@etud.ucao.sn','+221770300026','$2y$12$HASH_ETU106','etudiant','actif'),
('UCAO-2025-027','Keïta',    'Boubacar',  'boubacar.keita@etud.ucao.sn', '+221770300027','$2y$12$HASH_ETU107','etudiant','actif'),
('UCAO-2025-028','Doumbia',  'Aïcha',     'aicha.doumbia@etud.ucao.sn',  '+221770300028','$2y$12$HASH_ETU108','etudiant','actif'),
('UCAO-2025-029','Sanogo',   'Lassana',   'lassana.sanogo@etud.ucao.sn', '+221770300029','$2y$12$HASH_ETU109','etudiant','actif'),
('UCAO-2025-030','Ouattara', 'Aminata',   'aminata.ouattara@etud.ucao.sn','+221770300030','$2y$12$HASH_ETU110','etudiant','actif'),
('UCAO-2025-031','Kourouma', 'Yaya',      'yaya.kourouma@etud.ucao.sn',  '+221770300031','$2y$12$HASH_ETU111','etudiant','actif'),
('UCAO-2025-032','Diallo',   'Ramata',    'ramata.diallo@etud.ucao.sn',  '+221770300032','$2y$12$HASH_ETU112','etudiant','actif'),
('UCAO-2025-033','Diabaté',  'Mariam',    'mariam.diabate@etud.ucao.sn', '+221770300033','$2y$12$HASH_ETU113','etudiant','actif'),
('UCAO-2025-034','Koné',     'Lancine',   'lancine.kone@etud.ucao.sn',   '+221770300034','$2y$12$HASH_ETU114','etudiant','actif'),
('UCAO-2025-035','Diallo',   'Hawa',      'hawa.diallo@etud.ucao.sn',    '+221770300035','$2y$12$HASH_ETU115','etudiant','actif'),
('UCAO-2025-036','Bamba',    'Awa',       'awa.bamba@etud.ucao.sn',      '+221770300036','$2y$12$HASH_ETU116','etudiant','actif'),
('UCAO-2025-037','Sangaré',  'Fousseyni', 'fousseyni.sangare@etud.ucao.sn','+221770300037','$2y$12$HASH_ETU117','etudiant','actif'),
('UCAO-2025-038','Touré',    'Safiatou',  'safiatou.toure@etud.ucao.sn', '+221770300038','$2y$12$HASH_ETU118','etudiant','actif'),
('UCAO-2025-039','Diallo',   'Aminata',   'aminata2.diallo@etud.ucao.sn','+221770300039','$2y$12$HASH_ETU119','etudiant','actif'),
('UCAO-2025-040','Sow',      'Lamine',    'lamine.sow@etud.ucao.sn',     '+221770300040','$2y$12$HASH_ETU120','etudiant','actif');

-- ============================================================
-- ETUDIANTS (lier users aux classes)
-- ============================================================
-- LIG 1-1 : users IDs 16-55 → classe_id=1
INSERT INTO etudiants (user_id, classe_id, credits_requis) VALUES
(16,1,30),(17,1,30),(18,1,30),(19,1,30),(20,1,30),
(21,1,30),(22,1,30),(23,1,30),(24,1,30),(25,1,30),
(26,1,30),(27,1,30),(28,1,30),(29,1,30),(30,1,30),
(31,1,30),(32,1,30),(33,1,30),(34,1,30),(35,1,30),
(36,1,30),(37,1,30),(38,1,30),(39,1,30),(40,1,30),
(41,1,30),(42,1,30),(43,1,30),(44,1,30),(45,1,30),
(46,1,30),(47,1,30),(48,1,30),(49,1,30),(50,1,30),
(51,1,30),(52,1,30),(53,1,30),(54,1,30),(55,1,30);

-- LIG 1-2 : users IDs 56-95 → classe_id=2
INSERT INTO etudiants (user_id, classe_id, credits_requis) VALUES
(56,2,30),(57,2,30),(58,2,30),(59,2,30),(60,2,30),
(61,2,30),(62,2,30),(63,2,30),(64,2,30),(65,2,30),
(66,2,30),(67,2,30),(68,2,30),(69,2,30),(70,2,30),
(71,2,30),(72,2,30),(73,2,30),(74,2,30),(75,2,30),
(76,2,30),(77,2,30),(78,2,30),(79,2,30),(80,2,30),
(81,2,30),(82,2,30),(83,2,30),(84,2,30),(85,2,30),
(86,2,30),(87,2,30),(88,2,30),(89,2,30),(90,2,30),
(91,2,30),(92,2,30),(93,2,30),(94,2,30),(95,2,30);

-- LIG 2-1 : users IDs 96-135 → classe_id=3
INSERT INTO etudiants (user_id, classe_id, credits_requis) VALUES
(96,3,30),(97,3,30),(98,3,30),(99,3,30),(100,3,30),
(101,3,30),(102,3,30),(103,3,30),(104,3,30),(105,3,30),
(106,3,30),(107,3,30),(108,3,30),(109,3,30),(110,3,30),
(111,3,30),(112,3,30),(113,3,30),(114,3,30),(115,3,30),
(116,3,30),(117,3,30),(118,3,30),(119,3,30),(120,3,30),
(121,3,30),(122,3,30),(123,3,30),(124,3,30),(125,3,30),
(126,3,30),(127,3,30),(128,3,30),(129,3,30),(130,3,30),
(131,3,30),(132,3,30),(133,3,30),(134,3,30),(135,3,30);

-- ============================================================
-- COMPTES PARENTS (3 de test — liés aux 3 premiers étudiants)
-- ============================================================
INSERT INTO users (numero_ucao, nom, prenom, email, telephone, password_hash, role, statut) VALUES
('UCAO-PAR-001','Diallo',  'Oumar',     'oumar.diallo@gmail.com',     '+221771200001','$2y$12$HASH_PAR01','parent','actif'),
('UCAO-PAR-002','Sow',     'Mamadou',   'mamadou.sow@gmail.com',      '+221771200002','$2y$12$HASH_PAR02','parent','actif'),
('UCAO-PAR-003','Ndiaye',  'Rokhaya',   'rokhaya.ndiaye@gmail.com',   '+221771200003','$2y$12$HASH_PAR03','parent','actif');

-- Mise à jour des liens parent → étudiant 1, 2, 3
UPDATE etudiants SET parent_id=136 WHERE user_id=16;
UPDATE etudiants SET parent_id=137 WHERE user_id=17;
UPDATE etudiants SET parent_id=138 WHERE user_id=18;

-- ============================================================
-- COMPTES MENTORS (3 de test — pris parmi LIG 2-1)
-- ============================================================
INSERT INTO users (numero_ucao, nom, prenom, email, telephone, password_hash, role, statut) VALUES
('UCAO-MENT-01','Ndiaye',  'Souleymane','souleymane.ndiaye.mentor@ucao.sn','+221771300001','$2y$12$HASH_MENT01','mentor','actif'),
('UCAO-MENT-02','Sarr',    'Mariama',   'mariama.sarr.mentor@ucao.sn',     '+221771300002','$2y$12$HASH_MENT02','mentor','actif'),
('UCAO-MENT-03','Diallo',  'Ibrahima',  'ibrahima.diallo.mentor@ucao.sn',  '+221771300003','$2y$12$HASH_MENT03','mentor','actif');

-- Profils mentors
INSERT INTO mentors (user_id, bio, tarif, note_moyenne, nb_sessions, nb_etudiants, taux_satisfaction,
    etape_temoignage, etape_notes, etape_entretien, etape_quiz, score_quiz,
    quiz_passe_le, valide_par, valide_le, actif) VALUES
(139,'Expert en Algorithmes et Programmation C#. Toujours disponible.','gratuit',4.80,24,8,0.96,TRUE,TRUE,TRUE,TRUE,87.50,'2026-02-15 10:00:00',1,'2026-02-20 09:00:00',TRUE),
(140,'Passionnée par les maths et la compta. Pédagogie claire.','gratuit',4.60,18,6,0.93,TRUE,TRUE,TRUE,TRUE,82.00,'2026-02-20 10:00:00',1,'2026-02-25 09:00:00',TRUE),
(141,'Spécialiste web et SGBDR. Cours pratiques orientés projets.','gratuit',4.90,31,10,0.97,TRUE,TRUE,TRUE,TRUE,91.00,'2026-03-01 10:00:00',1,'2026-03-05 09:00:00',TRUE);

-- Modules des mentors
INSERT INTO mentor_modules (mentor_id, module_id, note_obtenue, temoignage_txt) VALUES
(1, 3, 17.50, 'Souleymane a une maîtrise exceptionnelle des algorithmes. Je le recommande vivement. — Prof. Touré'),
(1, 5, 16.00, 'Très bon niveau en C#. Autonome et rigoureux. — Prof. Touré'),
(2, 6, 16.50, 'Excellente compréhension des concepts mathématiques. — Prof. Kane'),
(2, 8, 17.00, 'Très bonne maîtrise de la comptabilité générale. — Prof. Mbaye'),
(3, 18,17.50, 'Excellent en développement web. Maîtrise HTML/CSS/JS parfaitement. — Prof. Fall'),
(3, 20,16.00, 'Bonne maîtrise du SGBDR et des requêtes SQL. — Prof. Diop');

-- ============================================================
-- FRAIS DE SCOLARITE — 120 étudiants (500 000 FCFA / an, plan trimestriel)
-- ============================================================
INSERT INTO frais_scolarite (etudiant_id, annee_acad, montant_total, plan, statut, date_echeance)
SELECT e.id, '2025-2026', 500000.00, 'trimestriel',
    CASE WHEN e.id % 5 = 0 THEN 'retard'
         WHEN e.id % 3 = 0 THEN 'en_cours'
         ELSE 'en_cours' END,
    '2026-04-15'
FROM etudiants e;

-- ============================================================
-- QUELQUES PAIEMENTS DE TEST (pour les 3 premiers étudiants)
-- ============================================================
INSERT INTO paiements (frais_id, payeur_id, montant, moyen, recu_numero, statut, confirmed_at) VALUES
(1, 136, 125000.00, 'wave',         'REC-2026-00001', 'confirme', '2026-01-15 10:30:00'),
(1, 136, 125000.00, 'orange_money', 'REC-2026-00002', 'confirme', '2026-02-15 09:00:00'),
(2, 137, 125000.00, 'wave',         'REC-2026-00003', 'confirme', '2026-01-16 11:00:00'),
(3, 138, 125000.00, 'carte',        'REC-2026-00004', 'confirme', '2026-01-17 14:00:00'),
(3, 138, 125000.00, 'wave',         'REC-2026-00005', 'en_attente', NULL);

-- ============================================================
-- USER PREFERENCES (pour tous les comptes de test)
-- ============================================================
INSERT INTO user_preferences (user_id, notif_email, notif_sms, notif_inapp)
SELECT id, TRUE, (id % 2 = 0), TRUE FROM users WHERE statut = 'actif';

-- ============================================================
-- FIN DU FICHIER
-- ============================================================
