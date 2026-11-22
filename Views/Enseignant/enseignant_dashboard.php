<?php
/**
 * EduConnect — Tableau de bord enseignant
 * Modules, publication des notes et prochains cours — données réelles.
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/Config/session_check.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/Config/db_connect.php';
session_role_check('enseignant');

$user = current_user();
$uid  = (int)$user['id'];
$annee = '2025-2026';

$u_full = h($user['prenom'] . ' ' . $user['nom']);
$u_init = h($user['initiales']);
$current_page = basename(__FILE__);

// Modules de l'enseignant, avec performance et état de publication
$modules = db_query($conn,
    "SELECT m.id, m.nom, m.code,
            COUNT(n.id) AS nb_notes,
            ROUND(AVG(n.note_finale), 2) AS moyenne,
            COALESCE(SUM(CASE WHEN n.publie = 0 THEN 1 ELSE 0 END), 0) AS en_attente
     FROM module_enseignants me
     JOIN modules m ON m.id = me.module_id
     LEFT JOIN notes n ON n.module_id = m.id AND n.annee_acad = ?
     WHERE me.enseignant_id = ?
     GROUP BY m.id, m.nom, m.code
     ORDER BY m.nom",
    "si", [$annee, $uid]
);
$nb_modules = count($modules);

// Étudiants suivis
$nb_etudiants = (int)(db_row($conn,
    "SELECT COUNT(DISTINCT e.id) AS nb
     FROM etudiants e
     JOIN module_classes mc ON mc.classe_id = e.classe_id
     JOIN module_enseignants me ON me.module_id = mc.module_id
     WHERE me.enseignant_id = ?",
    "i", [$uid]
)['nb'] ?? 0);

// Publication des notes
$publication = db_row($conn,
    "SELECT COALESCE(SUM(CASE WHEN n.publie = 1 THEN 1 ELSE 0 END), 0) AS publiees,
            COALESCE(SUM(CASE WHEN n.publie = 0 THEN 1 ELSE 0 END), 0) AS attente
     FROM notes n
     JOIN module_enseignants me ON me.module_id = n.module_id
     WHERE me.enseignant_id = ?",
    "i", [$uid]
);
$notes_publiees = (int)($publication['publiees'] ?? 0);
$notes_attente  = (int)($publication['attente'] ?? 0);
$total_notes    = $notes_publiees + $notes_attente;

// Prochains cours
$prochains_cours = db_query($conn,
    "SELECT et.date_debut, et.type_creneau,
            m.nom AS module_nom, c.code AS classe_code, s.nom AS salle_nom
     FROM emplois_du_temps et
     JOIN modules m ON m.id = et.module_id
     LEFT JOIN classes c ON c.id = et.classe_id
     LEFT JOIN salles s ON s.id = et.salle_id
     WHERE et.enseignant_id = ? AND et.date_debut >= NOW()
     ORDER BY et.date_debut ASC LIMIT 5",
    "i", [$uid]
);

// Dernières saisies
$dernieres_saisies = db_query($conn,
    "SELECT m.nom AS module_nom, c.code AS classe_code,
            COUNT(*) AS nb_notes, MAX(n.saisie_le) AS derniere_saisie,
            SUM(CASE WHEN n.publie = 1 THEN 1 ELSE 0 END) AS publiees
     FROM notes n
     JOIN modules m ON m.id = n.module_id
     JOIN module_enseignants me ON me.module_id = n.module_id
     LEFT JOIN etudiants e ON e.id = n.etudiant_id
     LEFT JOIN classes c ON c.id = e.classe_id
     WHERE me.enseignant_id = ?
     GROUP BY n.module_id, e.classe_id, m.nom, c.code
     ORDER BY derniere_saisie DESC LIMIT 5",
    "i", [$uid]
);

$nb_notifs = (int)(db_row($conn,
    "SELECT COUNT(*) AS nb FROM notifications WHERE user_id = ? AND lu = 0",
    "i", [$uid]
)['nb'] ?? 0);

$jours = ['lundi','mardi','mercredi','jeudi','vendredi','samedi','dimanche'];
$creneaux = ['cours1'=>'08:00 – 11:00','cours2'=>'11:15 – 13:00','cours3'=>'14:00 – 16:45','revision'=>'Révisions','examen'=>'Examen','rattrapage'=>'Rattrapage'];

$avec_moyenne = array_values(array_filter($modules, fn($m) => $m['moyenne'] !== null));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tableau de bord enseignant — EduConnect</title>
<link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@300;400;500;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link rel="stylesheet" href="/Assets/css/educonnect-ui.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
</head>
<body class="role-enseignant">

<aside class="sidebar" id="sidebar">
  <div class="sidebar__brand">
    <div class="sidebar__mark"><i class="bi bi-easel" aria-hidden="true"></i></div>
    <div>
      <div class="sidebar__name">Edu<span>Connect</span></div>
      <div class="sidebar__role">Enseignant</div>
    </div>
  </div>

  <nav class="sidebar__nav" aria-label="Navigation principale">
    <a class="nav-link is-active" href="enseignant_dashboard.php"><i class="bi bi-grid-1x2"></i>Tableau de bord</a>
    <div class="sidebar__label">Enseignement</div>
    <a class="nav-link" href="enseignant_cours.php"><i class="bi bi-journal-bookmark"></i>Mes cours</a>
    <a class="nav-link" href="enseignant_notes.php"><i class="bi bi-clipboard-check"></i>Saisie des notes</a>
    <a class="nav-link" href="enseignant_emploi_du_temps.php"><i class="bi bi-calendar3"></i>Emploi du temps</a>
    <a class="nav-link" href="enseignant_ressources.php"><i class="bi bi-folder2-open"></i>Ressources</a>
    <div class="sidebar__label">Échanges</div>
    <a class="nav-link" href="enseignant_messages.php"><i class="bi bi-chat-dots"></i>Messages</a>
    <a class="nav-link" href="enseignant_notifications.php">
      <i class="bi bi-bell"></i>Notifications
      <?php if ($nb_notifs > 0): ?><span class="nav-link__count"><?= $nb_notifs ?></span><?php endif; ?>
    </a>
  </nav>

  <div class="sidebar__foot">
    <a class="sidebar__user" href="enseignant_profil.php">
      <span class="avatar avatar--sm"><?= $u_init ?></span>
      <span class="sidebar__user-meta">
        <span class="sidebar__user-name"><?= $u_full ?></span>
        <span class="sidebar__user-role"><?= h($user['numero_ucao'] ?: 'Enseignant') ?></span>
      </span>
    </a>
    <div class="flex" style="padding:8px 4px 0">
      <a class="nav-link" href="enseignant_parametres.php" style="padding:6px 8px;font-size:12px"><i class="bi bi-gear"></i>Paramètres</a>
      <a class="nav-link" href="/Views/Auth/logout.php" style="padding:6px 8px;font-size:12px;margin-left:auto"><i class="bi bi-box-arrow-left"></i>Quitter</a>
    </div>
  </div>
</aside>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<main class="main">
  <header class="topbar">
    <button class="icon-btn nav-toggle" type="button" onclick="EduUI.toggleNav()" aria-label="Ouvrir la navigation">
      <i class="bi bi-list" aria-hidden="true"></i>
    </button>
    <div class="topbar__title">Espace enseignant <span>· <?= h($annee) ?></span></div>
    <div class="topbar__actions">
      <a class="icon-btn icon-btn--bordered bell" href="enseignant_notifications.php" aria-label="Notifications">
        <i class="bi bi-bell" aria-hidden="true"></i>
        <?php if ($nb_notifs > 0): ?><span class="bell__dot"></span><?php endif; ?>
      </a>
      <a class="icon-btn icon-btn--bordered" href="enseignant_profil.php" aria-label="Mon profil">
        <span class="avatar avatar--sm"><?= $u_init ?></span>
      </a>
    </div>
  </header>

  <div class="page">
    <div class="page-head">
      <div>
        <h1 class="page-title">Bonjour, <?= h($user['prenom']) ?></h1>
        <p class="page-sub"><?= $nb_modules ?> module<?= $nb_modules > 1 ? 's' : '' ?> · <?= $nb_etudiants ?> étudiant<?= $nb_etudiants > 1 ? 's' : '' ?> suivi<?= $nb_etudiants > 1 ? 's' : '' ?></p>
      </div>
      <a class="btn btn--primary" href="enseignant_notes.php">
        <i class="bi bi-pencil-square" aria-hidden="true"></i>Saisir des notes
      </a>
    </div>

    <?php if ($notes_attente > 0): ?>
      <div class="alert alert--warning" style="margin-bottom:16px">
        <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
        <div><strong><?= $notes_attente ?> note<?= $notes_attente > 1 ? 's' : '' ?></strong> saisie<?= $notes_attente > 1 ? 's' : '' ?> mais non publiée<?= $notes_attente > 1 ? 's' : '' ?>. Les étudiants ne les voient pas encore.</div>
        <a class="btn btn--sm btn--outline ml-auto" href="enseignant_notes.php">Publier</a>
      </div>
    <?php endif; ?>

    <section class="stat-grid" aria-label="Indicateurs clés">
      <article class="stat stat--accent">
        <div class="stat__top">
          <span class="stat__label">Modules enseignés</span>
          <span class="stat__icon"><i class="bi bi-journal-bookmark" aria-hidden="true"></i></span>
        </div>
        <div class="stat__value"><?= $nb_modules ?></div>
        <div class="stat__meta">Année <?= h($annee) ?></div>
      </article>

      <article class="stat">
        <div class="stat__top">
          <span class="stat__label">Étudiants suivis</span>
          <span class="stat__icon"><i class="bi bi-people" aria-hidden="true"></i></span>
        </div>
        <div class="stat__value"><?= $nb_etudiants ?></div>
        <div class="stat__meta">Toutes classes confondues</div>
      </article>

      <article class="stat">
        <div class="stat__top">
          <span class="stat__label">Notes publiées</span>
          <span class="stat__icon"><i class="bi bi-check2-circle" aria-hidden="true"></i></span>
        </div>
        <div class="stat__value"><?= $notes_publiees ?><small>/<?= $total_notes ?></small></div>
        <div class="progress" style="margin-top:2px">
          <div class="progress__bar progress__bar--success" style="width:<?= $total_notes > 0 ? (int)round($notes_publiees / $total_notes * 100) : 0 ?>%"></div>
        </div>
      </article>

      <article class="stat">
        <div class="stat__top">
          <span class="stat__label">Cours à venir</span>
          <span class="stat__icon"><i class="bi bi-calendar-check" aria-hidden="true"></i></span>
        </div>
        <div class="stat__value"><?= count($prochains_cours) ?></div>
        <div class="stat__meta">Créneaux planifiés</div>
      </article>
    </section>

    <div class="grid grid--main" style="margin-bottom:16px">
      <section class="card">
        <div class="card__head">
          <div>
            <h2 class="card__title"><i class="bi bi-bar-chart" aria-hidden="true"></i>Moyenne par module</h2>
            <p class="card__hint">Moyenne des notes saisies, sur 20</p>
          </div>
          <a class="btn btn--ghost btn--sm" href="enseignant_notes.php">Détail</a>
        </div>
        <div class="card__body">
          <div class="chart-wrap chart-wrap--lg">
            <canvas id="chartModules"></canvas>
            <div class="chart-empty"><i class="bi bi-bar-chart" aria-hidden="true"></i><span>Aucune note saisie pour l'instant</span></div>
          </div>
        </div>
      </section>

      <section class="card">
        <div class="card__head">
          <h2 class="card__title"><i class="bi bi-pie-chart" aria-hidden="true"></i>Publication</h2>
        </div>
        <div class="card__body">
          <div class="donut-wrap">
            <canvas id="chartPublication"></canvas>
            <div class="donut-center"><div><strong><?= $notes_publiees ?></strong><span>publiées</span></div></div>
            <div class="chart-empty"><i class="bi bi-pie-chart" aria-hidden="true"></i><span>Aucune note enregistrée</span></div>
          </div>
          <div class="legend">
            <span class="legend__item"><span class="legend__dot" style="background:#15803d"></span>Publiées · <?= $notes_publiees ?></span>
            <span class="legend__item"><span class="legend__dot" style="background:#b45309"></span>En attente · <?= $notes_attente ?></span>
          </div>
          <a class="btn btn--outline" href="enseignant_notes.php" style="width:100%;margin-top:14px">
            <i class="bi bi-upload" aria-hidden="true"></i>Gérer les publications
          </a>
        </div>
      </section>
    </div>

    <div class="grid grid--2">
      <section class="card">
        <div class="card__head">
          <h2 class="card__title"><i class="bi bi-calendar3" aria-hidden="true"></i>Prochains cours</h2>
          <a class="btn btn--ghost btn--sm" href="enseignant_emploi_du_temps.php">Emploi du temps</a>
        </div>
        <div class="card__body card__body--flush">
          <?php if ($prochains_cours): ?>
            <div class="table-wrap">
              <table class="table">
                <thead><tr><th>Date</th><th>Module</th><th>Classe</th><th>Salle</th></tr></thead>
                <tbody>
                <?php foreach ($prochains_cours as $c): $ts = strtotime($c['date_debut']); ?>
                  <tr>
                    <td>
                      <strong><?= h(ucfirst($jours[(int)date('N', $ts) - 1])) ?> <?= date('d/m', $ts) ?></strong><br>
                      <span class="muted" style="font-size:11px"><?= h($creneaux[$c['type_creneau']] ?? '') ?></span>
                    </td>
                    <td><?= h($c['module_nom']) ?></td>
                    <td><?= h($c['classe_code'] ?? '—') ?></td>
                    <td><?= h($c['salle_nom'] ?? '—') ?></td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <div class="empty"><i class="bi bi-calendar-x" aria-hidden="true"></i><strong>Aucun cours planifié</strong><span>Vos créneaux apparaîtront ici dès publication par l'administration.</span></div>
          <?php endif; ?>
        </div>
      </section>

      <section class="card">
        <div class="card__head">
          <h2 class="card__title"><i class="bi bi-clock-history" aria-hidden="true"></i>Dernières saisies</h2>
          <a class="btn btn--ghost btn--sm" href="enseignant_notes.php">Tout voir</a>
        </div>
        <div class="card__body card__body--flush">
          <?php if ($dernieres_saisies): ?>
            <div class="list">
            <?php foreach ($dernieres_saisies as $s):
              $toutes_publiees = (int)$s['publiees'] === (int)$s['nb_notes'];
            ?>
              <div class="list__item">
                <span class="list__icon <?= $toutes_publiees ? 'list__icon--success' : 'list__icon--warning' ?>">
                  <i class="bi <?= $toutes_publiees ? 'bi-check2-all' : 'bi-hourglass' ?>" aria-hidden="true"></i>
                </span>
                <div class="list__body">
                  <div class="list__title"><?= h($s['module_nom']) ?> <span class="muted" style="font-weight:400">· <?= h($s['classe_code'] ?? '—') ?></span></div>
                  <div class="list__meta"><?= (int)$s['nb_notes'] ?> note<?= (int)$s['nb_notes'] > 1 ? 's' : '' ?> · <?= (int)$s['publiees'] ?> publiée<?= (int)$s['publiees'] > 1 ? 's' : '' ?> · <?= h(date('d/m/Y', strtotime($s['derniere_saisie']))) ?></div>
                </div>
                <span class="badge badge--<?= $toutes_publiees ? 'success' : 'warning' ?>"><?= $toutes_publiees ? 'Publié' : 'Brouillon' ?></span>
              </div>
            <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="empty"><i class="bi bi-clipboard" aria-hidden="true"></i><strong>Aucune note saisie</strong><span>Commencez par saisir les notes d'un module.</span></div>
          <?php endif; ?>
        </div>
      </section>
    </div>
  </div>
</main>

<div class="toast" id="toast" role="status" aria-live="polite"></div>
<script src="/Assets/js/educonnect-charts.js"></script>
<script src="/Assets/js/educonnect-ui.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var modules = <?= json_encode(array_map(fn($m) => ['n' => $m['nom'], 'v' => (float)$m['moyenne']], $avec_moyenne), JSON_UNESCAPED_UNICODE) ?>;
  EduCharts.bar('chartModules', {
    labels: modules.map(function (m) { return m.n; }),
    values: modules.map(function (m) { return m.v; }),
    colors: modules.map(function (m) { return m.v >= 10 ? '#0e7490' : '#c0272d'; }),
    label: 'Moyenne',
    format: 'score',
    max: 20,
    aria: 'Moyenne des notes par module sur 20'
  });

  EduCharts.doughnut('chartPublication', {
    labels: ['Publiées', 'En attente'],
    values: [<?= $notes_publiees ?>, <?= $notes_attente ?>],
    colors: ['#15803d', '#b45309'],
    format: 'count',
    aria: 'Répartition des notes publiées et en attente',
    empty: 'Aucune note enregistrée'
  });
});
</script>
</body>
</html>
