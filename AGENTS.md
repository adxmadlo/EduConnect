# AGENTS.md — EduConnect

Instructions pour tout agent de code (Freebuff, Claude Code, Cursor, Copilot…) travaillant dans ce dépôt.
Elles décrivent ce qui existe réellement ici : les respecter plutôt que d'introduire de nouveaux outils.

## Le projet

Application web de gestion scolaire pour l'UCAO Saint-Michel (Dakar) — projet hackathon, v3.0.
Interface **entièrement en français** : libellés, messages d'erreur, commentaires de code, noms de variables métier
(`$etudiants`, `$notes`, `$moyenne`…). Garder cette langue, y compris dans les nouveaux textes.

## Stack et contraintes dures

- **PHP 8.1+ procédural**, `mysqli`, aucune dépendance gérée par Composer. Pas de Laravel, Symfony, ni framework maison.
- **Pas d'étape de build** : CSS et JS sont servis tels quels depuis `Assets/`. Pas de npm, pas de bundler, pas de Sass.
- Bootstrap Icons + Chart.js sont chargés par CDN dans les pages qui en ont besoin.
- Base **MySQL/MariaDB via XAMPP**, port toujours `3306`, base `educonnect`.
- Messagerie temps réel : Firebase Firestore (`Assets/js/firebase_init.js`).
- Paiements : Wave et Orange Money (`Config/paiement_config.php` lit `$_ENV`, webhooks dans `Api/Paiement/`).
- Cible d'hébergement : Apache + PHP (XAMPP en local, `/htdocs/educonnect/`). Rien ne doit dépendre de la CLI PHP en production.

## Installation locale

1. Copier le dossier dans `C:/xampp/htdocs/educonnect/`.
2. Démarrer Apache + MySQL, puis importer `educonnect_final.sql` (à la racine) dans une base `educonnect` via phpMyAdmin.
3. Générer un vrai hash bcrypt avec `hash_helper.php` ou
   `php -r "echo password_hash('EduConnect2026!', PASSWORD_BCRYPT);"` et remplacer les `$2y$12$HASH_*` du SQL.
4. Copier `.env.example` en `.env` et remplir les clés (jamais l'inverse : `.env` est ignoré par Git).
5. Ouvrir `http://localhost/educonnect/Views/Auth/login.php`. Comptes de test et mot de passe universel : voir `README_DEMO.txt`.

Aucun test automatisé, aucun lint configuré : la vérification est manuelle (voir plus bas).

## Structure

```
Config/     db_connect.php (connexion + helpers), session_check.php, paiement_config.php
Views/      Admin/ (12) Enseignant/ (9) Etudiant/ (12) Mentor/ (8) Parent/ (9) Auth/ (4)  → 54 pages
Api/        Paiement/ (wave_*, om_*), Recus/recu_generer.php
Assets/     css/ js/
Erreur/     403, 404, 500, 503
educonnect_final.sql   schéma complet (21 tables, données de démo)
```

Chaque page est un fichier autonome : `session_check.php` → `db_connect.php` → HTML complet. Pas de routeur, pas de layout partagé.

## Conventions PHP

- **Première ligne de chaque page protégée** :
  ```php
  require_once $_SERVER['DOCUMENT_ROOT'] . '/Config/session_check.php';
  session_role_check(['admin', 'chef_admin']);
  require_once $_SERVER['DOCUMENT_ROOT'] . '/Config/db_connect.php';
  ```
  `session_check.php` gère déjà le timeout de 900 s et la redirection vers `login.php`.
- **Toute requête SQL passe par les helpers préparés** de `Config/db_connect.php` :
  `db_query($conn, $sql, $types, $params)`, `db_row(...)`, `db_exec(...)`.
  Jamais de concaténation de variable dans une requête, jamais de `mysqli_query` direct.
  Attention : `db_exec()` retourne l'ID inséré **ou** le nombre de lignes affectées.
- **Toute valeur affichée issue de la BDD ou d'une URL passe par `h()`** (`htmlspecialchars`, `ENT_QUOTES | ENT_HTML5`, UTF-8).
  Dans un contexte JS, utiliser `json_encode(..., JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)` —
  jamais `addslashes()` ni d'interpolation de chaîne dans un `<script>`.
- Mots de passe : `password_hash()` / `password_verify()` uniquement. Ne jamais journaliser un mot de passe ou un code de réinitialisation (le log de `mdp_oublie.php` est conditionné par `EDUCONNECT_DEBUG_RESET`).
- Valider les entrées avant usage : `in_array($v, ['light', 'dark'], true)` — attention aux littéraux avec espace (`" light"`), source de bugs déjà rencontrés.
- Les redirections et liens dynamiques doivent être filtrés (`^(https?://|/)`), sinon risque de `javascript:`.
- Garder le style existant : commentaires en français, séparateurs `// ── … ──`, noms de fichiers `role_page.php`.

## Design system (refonte UI)

La refonte de l'UI est livrée et ne doit pas être cassée. Répartition vérifiée sur les balises `<link>` réelles
(54 pages dans `Views/` + 4 pages publiques) :

- `Assets/css/educonnect-ui.css` + `Assets/js/educonnect-ui.js` : **uniquement les 5 dashboards reconstruits** —
  `admin_dashboard.php`, `enseignant_dashboard.php`, `etudiant_dashboard.php`, `mentor_dashboard.php`, `parent_dashboard.php`.
  `Assets/js/educonnect-charts.js` est chargé sur ces mêmes 5 pages (graphiques Chart.js alimentés par les données réelles).
- `Assets/css/educonnect-legacy.css` + `Assets/js/educonnect-legacy.js` : pont des **48 pages legacy**, c'est-à-dire toutes les pages
  de `Views/` sauf ces 5 dashboards et `logout.php`. Juste après le `<link>` du pont, un script inline dans le `<head>` ajoute
  `legacy-ui` puis `role-<role>` (`etudiant`, `parent`, `enseignant`, `mentor`, `admin`) sur `<html>` ; le JS est chargé avant `</body>`.
  Le CSS du pont est scopé sous `html.legacy-ui` : ne pas renommer ces classes ni changer l'ordre d'inclusion, et ne pas y toucher
  pour modifier une page reconstruite.
- `Assets/css/educonnect-public.css` : les 4 pages publiques `index.php`, `apropos.php`, `contact.php`, `services.php`,
  chargée en plus de `stylemanus.css`.
- Les pages legacy conservent leur CSS d'origine : `login.css` sur les 3 pages Auth (`login.php`, `mdp_oublie.php`, `register.php`)
  et un bloc `<style>` inline sur 46 d'entre elles. Ne pas remplacer ce CSS par celui des dashboards.
- `Assets/css/style.css` existe encore sur le disque mais **n'est plus lié par aucune page** : ne pas le réintroduire,
  ne pas le supprimer sans demande. `animations.js` et `script.js` restent utilisés par les pages legacy.

Règles de contenu :

- **Aucune donnée inventée dans les pages applicatives.** Pas de chiffres, moyennes, noms, dates ou codes de bulletin en dur :
  tout vient de la BDD via une requête préparée. Si une donnée manque, afficher `—`.
- Pas de lorem ipsum, pas de faux comptes de démo ajoutés pour « remplir » un écran.
- Réutiliser les composants et la palette existantes (variables CSS de `educonnect-ui.css`) plutôt que d'inventer un nouveau style.
- Accessibilité : contraste suffisant, `aria-*` sur les contrôles, `prefers-reduced-motion` respecté (les animations `.sr` masquent le contenu par défaut).

## Vérification

Il n'y a pas de suite de tests. Avant de livrer :

```bash
php -l <chaque fichier .php modifié>            # syntaxe (0 échec attendu)
```

- Vérifier la page dans un navigateur via XAMPP (`http://localhost/educonnect/...`) : statut 200, zéro `Warning:` / `Notice:` / `Fatal error` dans la sortie, console JS propre.
- Si un script inline est modifié, vérifier qu'il parse (`new Function(bloc)` ou la console).
- Toute modification de données doit être testée avec `educonnect_final.sql` importé ; sans MySQL, le dire explicitement — ne pas prétendre avoir testé une requête qui n'a pas tourné.
- Ne pas lancer de déploiement, de migration ou de commande destructive sans demande explicite.

## Sécurité — état connu

Déjà en place : requêtes préparées, échappement systématique, `session_role_check`, cookies `httponly` + `use_strict_mode`,
`Config/` bloqué par `.htaccess`, fichiers `.sql`/`.env`/`.log` bloqués.

Dettes identifiées, à traiter si la tâche le demande (ne pas les corriger au passage sans raison) :

- Aucun jeton CSRF sur les formulaires POST.
- Pas de limitation de tentatives sur `login.php` ni sur la saisie du code de réinitialisation.
- L'envoi réel de l'e-mail de réinitialisation (`mdp_oublie.php`) est encore un TODO.
- `$nb_notifs` / `$sessions_mentorat_enfant` non définis sur plusieurs pages Parent.

Secrets : uniquement dans `.env` (ignoré par Git). Ne jamais écrire une clé Wave/OM/Firebase/Mail en dur dans le code,
la committer, l'afficher dans un chat ou la journaliser.

## Git et référence ECC

- Ne committer ou pousser que si l'utilisateur le demande. Les changements en cours peuvent être nombreux et non revus.
- Sont ignorés volontairement par Git (même s'ils peuvent être absents du poste) : `.preview/` (harnais de prévisualisation avec BDD simulée),
  `artifacts/` (captures d'écran), `reference/` (dépôts de référence clonés localement, dont `reference/ECC` et son `reference/ECC-PLAYBOOK.md`).
- ECC (Everything Claude Code, MIT) est une référence de méthode, pas une dépendance : ne pas l'importer dans le code applicatif.
  La boucle `ecc-workflow` (compétence de compte) s'applique en plan → test → implémentation → revue → vérification.
- Revue de code : appliquer les grilles `php-reviewer` et `security-reviewer` d'ECC (OWASP Top 10, requêtes préparées,
  hachage, échappement, CSRF) au périmètre modifié, sans refactoriser le reste de l'application.

## Checklist avant de rendre

1. La demande est couverte entièrement, rien de plus (pas de fonctionnalité, page ou dépendance non demandée).
2. Aucune donnée factice ajoutée ; les libellés sont en français.
3. `php -l` passe sur tous les fichiers touchés.
4. La page ou la route modifiée a été ouverte et ne produit aucun diagnostic PHP.
5. Ce qui n'a pas pu être vérifié (MySQL, navigateur, appareil) est annoncé clairement.
