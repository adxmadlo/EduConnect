═══════════════════════════════════════════════════════════════
             EduConnect — README DÉMO HACKATHON
             UCAO Saint-Michel — Informatique de Gestion
═══════════════════════════════════════════════════════════════

INSTALLATION RAPIDE (XAMPP)
─────────────────────────────────────────────────────────────
1. Copier tout le dossier dans : C:/xampp/htdocs/educonnect/

2. Démarrer Apache + MySQL dans XAMPP Control Panel

3. Ouvrir phpMyAdmin : http://localhost/phpmyadmin/
   → Créer une base de données "educonnect"
   → Importer le fichier : Config/educonnect_final.sql

4. Générer les vrais hash bcrypt (une seule fois) :
   → Ouvrir http://localhost/educonnect/hash_helper.php
   → Ou via CLI : php -r "echo password_hash('EduConnect2026!', PASSWORD_BCRYPT);"
   → Remplacer TOUS les '$2y$12$HASH_*' dans le SQL par ce hash
   → Réimporter le SQL modifié

5. Accéder à l'application :
   http://localhost/educonnect/Views/Auth/login.php

STRUCTURE DU PROJET
─────────────────────────────────────────────────────────────
educonnect/
├── Config/
│   ├── db_connect.php          Connexion mysqli + helpers db_row/db_query/db_exec/h()
│   ├── session_check.php       Session 900s timeout + session_role_check()
│   ├── educonnect_final.sql    Schéma BDD complet (21 tables, 120 étudiants)
│   └── paiement_config.php     Clés API Wave + Orange Money (à configurer)
├── Views/
│   ├── Auth/                   login, logout, register, mdp_oublie
│   ├── Admin/                  12 pages (dashboard, étudiants, paiements...)
│   ├── Enseignant/             9 pages (notes LMD, cours, EDT...)
│   ├── Etudiant/               12 pages (dashboard, notes, paiements...)
│   ├── Mentor/                 8 pages (sessions, étudiants...)
│   └── Parent/                 9 pages (suivi enfant, paiements...)
├── Api/
│   ├── Paiement/               wave_initier, wave_callback, om_initier, om_callback
│   └── Recus/recu_generer.php  Génération reçu HTML + QR code
├── Assets/
│   ├── css/                    style.css, login.css
│   └── js/                     animations.js, firebase_init.js
├── Erreur/                     403, 404, 500, 503
├── .env.example                Template variables d'environnement
└── .htaccess                   Sécurité Apache

COMPTES DE TEST (Mot de passe universel : EduConnect2026!)
─────────────────────────────────────────────────────────────
CHEF ADMIN  : bilong@ucao.edu.sn          (Mme Bilong)
ADMIN       : traore@ucao.edu.sn           (M. Traoré)

ÉTUDIANT 1  : adama.diallo@etud.ucao.sn   (LIG1-1)
ÉTUDIANT 2  : marie.sow@etud.ucao.sn      (LIG1-1)
ÉTUDIANT 3  : ibou.ndiaye@etud.ucao.sn    (LIG1-1)

PARENT 1    : oumar.diallo@gmail.com       (parent d'Adama)
PARENT 2    : mamadou.sow@gmail.com        (parent de Marie)
PARENT 3    : rokhaya.ndiaye@gmail.com     (parent d'Ibou)

ENSEIGNANT 1: i.ndiaye@ucao.edu.sn         (Algo, Archi, MERISE)
ENSEIGNANT 2: m.diop@ucao.edu.sn           (Algo 2, SGBDR)
ENSEIGNANT 3: s.toure@ucao.edu.sn          (Prog C# 1&2)

MENTOR 1    : souleymane.ndiaye.mentor@ucao.sn  (Algo + C#, note 4.8★)
MENTOR 2    : mariama.sarr.mentor@ucao.sn        (Maths + Compta, note 4.6★)
MENTOR 3    : ibrahima.diallo.mentor@ucao.sn     (Web + SGBDR, note 4.9★)

FONCTIONNALITÉS DYNAMIQUES CLÉS
─────────────────────────────────────────────────────────────
✅ Login/Logout + session 900s timeout
✅ Rôles : étudiant, parent, enseignant, mentor, admin, chef_admin
✅ Notes LMD : saisie enseignant → trigger MySQL calcul note_finale → publication → notif étudiant
✅ Paiements : Wave + Orange Money (API prêtes, sandbox à configurer)
✅ Reçu PDF avec QR code de vérification
✅ Mentorat : processus 4 étapes (temoignage → notes → entretien → quiz)
✅ Réservation salles : 23 salles UCAO avec anti-conflit
✅ EDT : Lundi–Samedi, créneaux 08h–20h, par classe et par enseignant
✅ Messagerie temps réel : Firebase Firestore (config dans Assets/js/firebase_init.js)
✅ Notifications : in-app, badge dynamique, mark-as-read
✅ Paramètres : thème clair/sombre, notifs email/SMS, changement mot de passe

SYSTÈME LMD
─────────────────────────────────────────────────────────────
- 30 crédits requis par semestre
- Type 30/70 : note_finale = CC*0.30 + Examen*0.70
- Type 100%  : note_finale = Examen
- Calcul automatique via trigger MySQL (trg_note_finale_insert/update)
- Mentions : TB≥16 | B≥14 | AB≥12 | P≥10 | F<10

CONFIGURATION PAIEMENTS (PRODUCTION)
─────────────────────────────────────────────────────────────
1. Copier .env.example en .env
2. Remplir WAVE_API_KEY (Wave Business dashboard)
3. Remplir OM_API_KEY + OM_MERCHANT_KEY (Orange Money Business)
4. Configurer les webhooks dans les dashboards respectifs :
   - Wave   → https://votre-domaine.com/educonnect/Api/Paiement/wave_callback.php
   - OM     → https://votre-domaine.com/educonnect/Api/Paiement/om_callback.php

CONFIGURATION FIREBASE (MESSAGERIE TEMPS RÉEL)
─────────────────────────────────────────────────────────────
1. Créer un projet sur https://console.firebase.google.com/
2. Activer Firestore Database (mode production)
3. Copier la config dans Assets/js/firebase_init.js
4. Ajouter les règles Firestore :
   allow read, write: if request.auth != null;

NOTES CONNUES
─────────────────────────────────────────────────────────────
- Modules L2 (S1+S2) : à intégrer quand reçus du membre de l'équipe
- Salles Bâtiment Claire Afrique : à ajouter dans la table `salles`
- Les hash '$2y$12$HASH_*' dans le SQL DOIVENT être remplacés avant démo
- PHP 8.1+ requis (match expression, str_starts_with, etc.)
- Port MySQL TOUJOURS 3306 (XAMPP default)

═══════════════════════════════════════════════════════════════
   EduConnect v3.0 — UCAO Saint-Michel — Hackathon 2026
   Adama Diallo & équipe — Informatique de Gestion L2
═══════════════════════════════════════════════════════════════
