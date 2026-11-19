<?php
/**
 * EduConnect — Configuration Paiements
 *
 * ⚠️  NE PAS committer ce fichier sur Git
 * ⚠️  Ajouter Config/paiement_config.php dans .gitignore
 *
 * Pour XAMPP local (démo) : laisser les valeurs vides,
 * les callbacks ne fonctionneront pas mais l'UI s'affiche.
 *
 * Pour production : remplir avec les vraies clés
 * obtenues depuis les dashboards Wave et Orange Money Business.
 */

// ── Wave Business ────────────────────────────────────────────
// Obtenir depuis : https://wave.com/fr/business/
define('WAVE_API_KEY',        $_ENV['WAVE_API_KEY']        ?? '');
define('WAVE_WEBHOOK_SECRET', $_ENV['WAVE_WEBHOOK_SECRET'] ?? '');

// ── Orange Money Business (Sénégal) ─────────────────────────
// Obtenir depuis : https://developer.orange.com/
define('OM_API_KEY',          $_ENV['OM_API_KEY']           ?? '');
define('OM_MERCHANT_KEY',     $_ENV['OM_MERCHANT_KEY']      ?? '');
define('OM_WEBHOOK_SECRET',   $_ENV['OM_WEBHOOK_SECRET']    ?? '');

// ── Paramètres généraux ──────────────────────────────────────
define('CURRENCY',            'XOF');
define('FRAIS_ANNUELS',       500000);  // 500 000 FCFA / an
define('FRAIS_EMISSION_RECU', 5000);    // Frais réémission bulletin en dehors EduConnect

// ── URLs de retour après paiement ────────────────────────────
$BASE_URL = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/educonnect';
define('WAVE_SUCCESS_URL', $BASE_URL . '/Views/Etudiant/etudiant_paiements.php?success=1');
define('WAVE_ERROR_URL',   $BASE_URL . '/Views/Etudiant/etudiant_paiements.php?error=1');
define('OM_RETURN_URL',    $BASE_URL . '/Views/Etudiant/etudiant_paiements.php?success=1');
define('OM_CANCEL_URL',    $BASE_URL . '/Views/Etudiant/etudiant_paiements.php?cancel=1');
define('OM_NOTIF_URL',     $BASE_URL . '/Api/Paiement/om_callback.php');
