# CLAUDE.md

Les instructions de ce dépôt sont centralisées dans **`AGENTS.md`** (racine du projet) : stack PHP procédural,
conventions BDD/échappement, design system de la refonte UI, procédure de vérification et dettes de sécurité connues.

À lire avant toute modification. Points non négociables en résumé :

- PHP 8.1+ procédural, `mysqli`, pas de Composer/Laravel, pas d'étape de build.
- Toute requête via `db_query` / `db_row` / `db_exec` (requêtes préparées) ; tout affichage via `h()`.
- `session_check.php` puis `session_role_check()` en tête de chaque page protégée.
- Interface et commentaires en **français**. Aucune donnée inventée dans les pages : tout vient de la BDD.
- Ne pas casser la refonte UI : `educonnect-ui.css` (pages reconstruites), `educonnect-legacy.css` scopé `html.legacy-ui`,
  `educonnect-public.css` (pages publiques).
- Vérifier avec `php -l` sur les fichiers touchés, puis la page sous XAMPP ; annoncer ce qui n'a pas été testé.
- Pas de commit, push ou déploiement sans demande explicite.
