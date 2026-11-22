<?php

/**
 * Constantes métier EduConnect.
 * L'année académique est centralisée ici : les pages ne doivent pas la coder en dur.
 */
return [
    'annee_acad' => env('EDUCONNECT_ANNEE_ACAD', '2025-2026'),

    // Frais annuels de référence (FCFA) — valeur appliquée par le seeder
    'frais_annuels' => (int) env('EDUCONNECT_FRAIS_ANNUELS', 500000),

    // Seuil de validation d'un module (sur 20)
    'seuil_validation' => 10,

    // Durée d'inactivité avant déconnexion (secondes) — reprend SESSION_TIMEOUT de l'ancienne app
    'session_timeout' => (int) env('EDUCONNECT_SESSION_TIMEOUT', 900),
];
