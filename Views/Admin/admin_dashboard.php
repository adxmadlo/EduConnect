<?php
/**
 * EduConnect — Tableau de bord administration
 * Pilotage de la plateforme : effectifs, recouvrement, file d'attente.
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/Config/session_check.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/Config/db_connect.php';
session_role_check(['admin', 'chef_admin']);

$user = current_user();
$uid  = (int)$user['id'];

$u_full = h($user['prenom'] . ' ' . $user['nom']);
$u_init = h($user['initiales']);
$current_page = basename(__FILE__);

// Indicateurs globaux
$stats = db_row($conn,
    "SELECT
        (SELECT COUNT(*) FROM users WHERE role = 'etudiant'   AND statut = 'actif') AS nb_etudiants,
        (SELECT COUNT(*) FROM users WHERE role = 'enseignant' AND statut = 'actif') AS nb_enseignants,
        (SELECT COUNT(*) FROM users WHERE role = 'mentor'     AND statut = 'actif') AS nb_mentors,
        (SELECT COUNT(*) FROM users WHERE role = 'parent'     AND statut = 'actif') AS nb_parents,
        (SELECT COUNT(*) FROM paiements WHERE statut = 'confirme')                  AS nb_paiements,
        (SELECT COALESCE(SUM(montant), 0) FROM paiements WHERE statut = 'confirme') AS total_recouvrement,
        (SELECT COUNT(*) FROM paiements WHERE statut = 'en_attente')                AS paiements_attente,
        (SELECT COUNT(*) FROM sessions_mentorat WHERE statut = 'confirmee' AND date_session >= CURDATE()) AS sessions_a_venir,
        (SELECT COUNT(*) FROM reservations WHERE statut = 'en_attente')             AS reservations_attente,
        (SELECT COUNT(*) FROM notes WHERE publie = 0)                               AS notes_attente"
);
$nb_etudiants    = (int)($stats['nb_etudiants'] ?? 0);
$nb_enseignants  = (int)($stats['nb_enseignants'] ?? 0);
$nb_mentors      = (int)($stats['nb_mentors'] ?? 0);
$nb_parents      = (int)($stats['nb_parents'] ?? 0);
$total_recouvre  = (float)($stats['total_recouvrement'] ?? 0);
$nb_paiements    = (int)($stats['nb_paiements'] ?? 0);
$paiements_attente = (int)($stats['paiements_attente'] ?? 0);
$reservations_attente = (int)($stats['reservations_attente'] ?? 0);
$notes_attente   = (int)($stats['notes_attente'] ?? 0);

// Taux de recouvrement
$total_scolarite = (float)(db_row($conn,
    "SELECT COALESCE(SUM(montant_total), 0) AS total FROM frais_scolarite"
)['total'] ?? 0);
$taux_recouvrement = $total_scolarite > 0
    ? round($total_recouvre / $total_scolarite * 100, 1)
    : 0.0;

// Recouvrement des 6 derniers mois
$recouvrement = db_query($conn,
    "SELECT DATE_FORMAT(COALESCE(confirmed_at, created_at), '%Y-%m') AS mois,
            SUM(montant) AS total
     FROM paiements
     WHERE statut = 'confirme'
       AND COALESCE(confirmed_at, created_at) >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
     GROUP BY mois
     ORDER BY mois ASC"
);

// Répartition des comptes
$roles = db_query($conn,
    "SELECT role, COUNT(*) AS nb FROM users GROUP BY role ORDER BY nb DESC"
);

// Moyens de paiement confirmés
$moyens = db_query($conn,
    "SELECT moyen, COUNT(*) AS nb, COALESCE(SUM(montant), 0) AS total
     FROM paiements
     WHERE statut = 'confirme'
     GROUP BY moyen
     ORDER BY total DESC"
);

// Derniers paiements
$derniers_paiements = db_query($conn,
    "SELECT p.montant, p.moyen, p.statut, p.recu_numero,
            COALESCE(p.confirmed_at, p.created_at) AS date_op,
            CONCAT(u.prenom, ' ', u.nom) AS payeur_nom
     FROM paiements p
     JOIN users u ON u.id = p.payeur_id
     ORDER BY date_op DESC LIMIT 8"
);

// Dernières inscriptions
$derniers_users = db_query($conn,
    "SELECT CONCAT(prenom, ' ', nom) AS nom_complet, email, role, statut, created_at
     FROM users
     ORDER BY created_at DESC LIMIT 8"
);

$nb_notifs = (int)(db_row($conn,
    "SELECT COUNT(*) AS nb FROM notifications WHERE lu = 0"
)['nb'] ?? 0);

$fmt = fn($n) => number_format((float)$n, 0, ',', ' ') . ' FCFA';
$mois_fr = ['01'=>'Jan','02'=>'Fév','03'=>'Mar','04'=>'Avr','05'=>'Mai','06'=>'Juin','07'=>'Juil','08'=>'Août','09'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Déc'];
$role_labels = ['etudiant'=>'Étudiants','enseignant'=>'Enseignants','mentor'=>'Mentors','parent'=>'Parents','admin'=>'Administrateurs','chef_admin'=>'Chef admin'];
$moyen_labels = ['wave'=>'Wave','orange_money'=>'Orange Money','carte'=>'Carte bancaire','virement'=>'Virement','especes'=>'Espèces'];
$statut_labels = ['actif'=>['Actif', 'success'], 'suspendu'=>['Suspendu', 'danger'], 'en_attente'=>['En attente', 'warning']];

$labels_recouvrement = [];
$values_recouvrement = [];
foreach ($recouvrement as $r) {
    $labels_recouvrement[] = $mois_fr[substr($r['mois'], 5, 2)] ?? $r['mois'];
    $values_recouvrement[] = (float)$r['total'];
}

$role_values = [];
$role_labels_chart = [];
foreach ($roles as $r) {
    $role_labels_chart[] = $role_labels[$r['role']] ?? $r['role'];
    $role_values[] = (int)$r['nb'];
}
$role_colors = ['#326ea9', '#0e7490', '#6d28d9', '#a16207', '#b91c1c', '#15803d'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Administration — EduConnect</title>
<link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@300;400;500;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link rel="stylesheet" href="/Assets/css/educonnect-ui.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
</head>
<body class="role-admin">

<aside class="sidebar" id="sidebar">
  <div class="sidebar__brand">
    <div class="sidebar__mark"><i class="bi bi-shield-check" aria-hidden="true"></i></div>
    <div>
      <div class="sidebar__name">Edu<span>Connect</span></div>
      <div class="sidebar__role">Administration</div>
    </div>
  </div>

  <nav class="sidebar__nav" aria-label="Navigation principale">
    <a class="nav-link is-active" href="admin_dashboard.php"><i class="bi bi-grid-1x2"></i>Tableau de bord</a>
    <div class="sidebar__label">Gestion</div>
    <a class="nav-link" href="admin_utilisateurs.php"><i class="bi bi-people"></i>Utilisateurs</a>
    <a class="nav-link" href="admin_etudiants.php"><i class="bi bi-mortarboard"></i>Étudiants</a>
    <a class="nav-link" href="admin_mentors.php"><i class="bi bi-person-badge"></i>Mentors</a>
    <a class="nav-link" href="admin_notes.php"><i class="bi bi-clipboard-check"></i>Notes</a>
    <a class="nav-link" href="admin_paiements.php">
      <i class="bi bi-credit-card"></i>Paiements
      <?php if ($paiements_attente > 0): ?><span class="nav-link__count"><?= $paiements_attente ?></span><?php endif; ?>
    </a>
    <div class="sidebar__label">Organisation</div>
    <a class="nav-link" href="admin_emploi_du_temps.php"><i class="bi bi-calendar3"></i>Emploi du temps</a>
    <a class="nav-link" href="admin_salles.php"><i class="bi bi-door-open"></i>Salles</a>
    <a class="nav-link" href="admin_rapports.php"><i class="bi bi-file-earmark-bar-graph"></i>Rapports</a>
    <a class="nav-link" href="admin_notifications.php">
      <i class="bi bi-bell"></i>Notifications
      <?php if ($nb_notifs > 0): ?><span class="nav-link__count"><?= $nb_notifs ?></span><?php endif; ?>
    </a>
  </nav>

  <div class="sidebar__foot">
    <a class="sidebar__user" href="admin_profil.php">
      <span class="avatar avatar--sm"><?= $u_init ?></span>
      <span class="sidebar__user-meta">
        <span class="sidebar__user-name"><?= $u_full ?></span>
        <span class="sidebar__user-role">Administrateur</span>
      </span>
    </a>
    <div class="flex" style="padding:8px 4px 0">
      <a class="nav-link" href="admin_parametres.php" style="padding:6px 8px;font-size:12px"><i class="bi bi-gear"></i>Paramètres</a>
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
    <div class="topbar__title">Administration <span>· Année 2025-2026</span></div>
    <div class="topbar__actions">
      <a class="icon-btn icon-btn--bordered bell" href="admin_notifications.php" aria-label="Notifications">
        <i class="bi bi-bell" aria-hidden="true"></i>
        <?php if ($nb_notifs > 0): ?><span class="bell__dot"></span><?php endif; ?>
      </a>
      <a class="icon-btn icon-btn--bordered" href="admin_profil.php" aria-label="Mon profil">
        <span class="avatar avatar--sm"><?= $u_init ?></span>
      </a>
    </div>
  </header>

  <div class="page">
    <div class="page-head">
      <div>
        <h1 class="page-title">Pilotage de la plateforme</h1>
        <p class="page-sub"><?= $nb_etudiants ?> étudiants · <?= $nb_enseignants ?> enseignants · <?= $nb_parents ?> parents</p>
      </div>
      <div class="flex gap-8">
        <a class="btn btn--outline btn--sm" href="admin_rapports.php"><i class="bi bi-download" aria-hidden="true"></i>Rapports</a>
        <a class="btn btn--primary btn--sm" href="admin_utilisateurs.php"><i class="bi bi-plus-lg" aria-hidden="true"></i>Nouvel utilisateur</a>
      </div>
    </div>

    <section class="stat-grid" aria-label="Indicateurs clés">
      <article class="stat stat--accent">
        <div class="stat__top">
          <span class="stat__label">Étudiants actifs</span>
          <span class="stat__icon"><i class="bi bi-mortarboard" aria-hidden="true"></i></span>
        </div>
        <div class="stat__value"><?= $nb_etudiants ?></div>
        <div class="stat__meta"><?= $nb_parents ?> parents rattachés</div>
      </article>

      <article class="stat">
        <div class="stat__top">
          <span class="stat__label">Enseignants actifs</span>
          <span class="stat__icon"><i class="bi bi-easel" aria-hidden="true"></i></span>
        </div>
        <div class="stat__value"><?= $nb_enseignants ?></div>
        <div class="stat__meta"><?= $nb_mentors ?> mentors</div>
      </article>

      <article class="stat">
        <div class="stat__top">
          <span class="stat__label">Recouvrement</span>
          <span class="stat__icon"><i class="bi bi-cash-stack" aria-hidden="true"></i></span>
        </div>
        <div class="stat__value" style="font-size:19px"><?= $fmt($total_recouvre) ?></div>
        <div class="stat__meta"><?= number_format($taux_recouvrement, 1, ',', '') ?>% de <?= $fmt($total_scolarite) ?></div>
      </article>

      <article class="stat">
        <div class="stat__top">
          <span class="stat__label">Paiements en attente</span>
          <span class="stat__icon"><i class="bi bi-hourglass-split" aria-hidden="true"></i></span>
        </div>
        <div class="stat__value"><?= $paiements_attente ?></div>
        <div class="stat__meta"><a href="admin_paiements.php" style="color:var(--accent);font-weight:600">Rapprocher</a></div>
      </article>
    </section>

    <div class="grid grid--main" style="margin-bottom:16px">
      <section class="card">
        <div class="card__head">
          <div>
            <h2 class="card__title"><i class="bi bi-graph-up-arrow" aria-hidden="true"></i>Recouvrement mensuel</h2>
            <p class="card__hint">Paiements confirmés des 6 derniers mois</p>
          </div>
          <a class="btn btn--ghost btn--sm" href="admin_paiements.php">Détail</a>
        </div>
        <div class="card__body">
          <div class="chart-wrap chart-wrap--lg">
            <canvas id="chartRecouvrement"></canvas>
            <div class="chart-empty"><i class="bi bi-bar-chart" aria-hidden="true"></i><span>Aucun paiement confirmé sur la période</span></div>
          </div>
        </div>
      </section>

      <section class="card">
        <div class="card__head">
          <h2 class="card__title"><i class="bi bi-pie-chart" aria-hidden="true"></i>Comptes par rôle</h2>
        </div>
        <div class="card__body">
          <div class="donut-wrap">
            <canvas id="chartRoles"></canvas>
            <div class="donut-center"><div><strong><?= array_sum($role_values) ?></strong><span>comptes</span></div></div>
            <div class="chart-empty"><i class="bi bi-pie-chart" aria-hidden="true"></i><span>Aucun compte enregistré</span></div>
          </div>
          <div class="legend">
            <?php foreach ($roles as $i => $r): ?>
              <span class="legend__item">
                <span class="legend__dot" style="background:<?= $role_colors[$i % count($role_colors)] ?>"></span>
                <?= h($role_labels[$r['role']] ?? $r['role']) ?> · <?= (int)$r['nb'] ?>
              </span>
            <?php endforeach; ?>
          </div>
        </div>
      </section>
    </div>

    <div class="grid grid--2" style="margin-bottom:16px">
      <section class="card">
        <div class="card__head">
          <div>
            <h2 class="card__title"><i class="bi bi-credit-card-2-front" aria-hidden="true"></i>Paiements par moyen</h2>
            <p class="card__hint">Montants confirmés, tous canaux</p>
          </div>
        </div>
        <div class="card__body">
          <div class="chart-wrap chart-wrap--sm">
            <canvas id="chartMoyens"></canvas>
            <div class="chart-empty"><i class="bi bi-bar-chart" aria-hidden="true"></i><span>Aucun paiement confirmé</span></div>
          </div>
        </div>
      </section>

      <section class="card">
        <div class="card__head">
          <h2 class="card__title"><i class="bi bi-list-check" aria-hidden="true"></i>File d'attente</h2>
        </div>
        <div class="card__body card__body--flush">
          <div class="list">
            <a class="list__item" href="admin_notes.php">
              <span class="list__icon <?= $notes_attente > 0 ? 'list__icon--warning' : 'list__icon--success' ?>"><i class="bi bi-clipboard-check" aria-hidden="true"></i></span>
              <div class="list__body">
                <div class="list__title">Notes à publier</div>
                <div class="list__meta">Saisies par les enseignants, non visibles par les étudiants</div>
              </div>
              <span class="badge badge--<?= $notes_attente > 0 ? 'warning' : 'success' ?>"><?= $notes_attente ?></span>
            </a>
            <a class="list__item" href="admin_paiements.php">
              <span class="list__icon <?= $paiements_attente > 0 ? 'list__icon--warning' : 'list__icon--success' ?>"><i class="bi bi-cash-coin" aria-hidden="true"></i></span>
              <div class="list__body">
                <div class="list__title">Paiements à rapprocher</div>
                <div class="list__meta">Tentatives en attente de confirmation prestataire</div>
              </div>
              <span class="badge badge--<?= $paiements_attente > 0 ? 'warning' : 'success' ?>"><?= $paiements_attente ?></span>
            </a>
            <a class="list__item" href="admin_salles.php">
              <span class="list__icon <?= $reservations_attente > 0 ? 'list__icon--warning' : 'list__icon--success' ?>"><i class="bi bi-door-open" aria-hidden="true"></i></span>
              <div class="list__body">
                <div class="list__title">Réservations de salles</div>
                <div class="list__meta">Demandes en attente de validation</div>
              </div>
              <span class="badge badge--<?= $reservations_attente > 0 ? 'warning' : 'success' ?>"><?= $reservations_attente ?></span>
            </a>
          </div>
        </div>
      </section>
    </div>

    <div class="grid grid--2">
      <section class="card">
        <div class="card__head">
          <h2 class="card__title"><i class="bi bi-receipt" aria-hidden="true"></i>Derniers paiements</h2>
          <a class="btn btn--ghost btn--sm" href="admin_paiements.php">Tout voir</a>
        </div>
        <div class="card__body card__body--flush">
          <?php if ($derniers_paiements): ?>
          <div class="table-wrap">
            <table class="table">
              <thead><tr><th>Payeur</th><th>Moyen</th><th class="num">Montant</th><th>Statut</th></tr></thead>
              <tbody>
              <?php foreach ($derniers_paiements as $p):
                $st = $p['statut'] === 'confirme' ? ['Payé', 'success'] : ($p['statut'] === 'en_attente' ? ['En attente', 'warning'] : ($p['statut'] === 'rembourse' ? ['Remboursé', 'info'] : ['Échec', 'danger']));
              ?>
                <tr>
                  <td><strong><?= h($p['payeur_nom']) ?></strong><br><span class="muted" style="font-size:11px"><?= h(date('d/m/Y', strtotime($p['date_op']))) ?></span></td>
                  <td><?= h($moyen_labels[$p['moyen']] ?? '—') ?></td>
                  <td class="num"><?= $fmt($p['montant']) ?></td>
                  <td><span class="badge badge--<?= $st[1] ?>"><?= $st[0] ?></span></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php else: ?>
            <div class="empty"><i class="bi bi-receipt" aria-hidden="true"></i><strong>Aucun paiement</strong><span>Les paiements apparaîtront ici.</span></div>
          <?php endif; ?>
        </div>
      </section>

      <section class="card">
        <div class="card__head">
          <h2 class="card__title"><i class="bi bi-person-plus" aria-hidden="true"></i>Dernières inscriptions</h2>
          <a class="btn btn--ghost btn--sm" href="admin_utilisateurs.php">Gérer</a>
        </div>
        <div class="card__body card__body--flush">
          <?php if ($derniers_users): ?>
          <div class="table-wrap">
            <table class="table">
              <thead><tr><th>Utilisateur</th><th>Rôle</th><th>Statut</th></tr></thead>
              <tbody>
              <?php foreach ($derniers_users as $u):
                $st = $statut_labels[$u['statut']] ?? [$u['statut'], 'info'];
              ?>
                <tr>
                  <td><strong><?= h($u['nom_complet']) ?></strong><br><span class="muted" style="font-size:11px"><?= h($u['email']) ?></span></td>
                  <td><?= h($role_labels[$u['role']] ?? $u['role']) ?></td>
                  <td><span class="badge badge--<?= $st[1] ?>"><?= $st[0] ?></span></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php else: ?>
            <div class="empty"><i class="bi bi-people" aria-hidden="true"></i><strong>Aucun compte</strong><span>Les inscriptions apparaîtront ici.</span></div>
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
  EduCharts.line('chartRecouvrement', {
    labels: <?= json_encode($labels_recouvrement, JSON_UNESCAPED_UNICODE) ?>,
    values: <?= json_encode($values_recouvrement) ?>,
    label: 'Recouvrement',
    format: 'money',
    color: '#b91c1c',
    aria: 'Montant des paiements confirmés par mois'
  });

  EduCharts.doughnut('chartRoles', {
    labels: <?= json_encode($role_labels_chart, JSON_UNESCAPED_UNICODE) ?>,
    values: <?= json_encode($role_values) ?>,
    colors: <?= json_encode(array_slice($role_colors, 0, max(1, count($role_values)))) ?>,
    format: 'count',
    aria: 'Répartition des comptes par rôle',
    empty: 'Aucun compte enregistré'
  });

  var moyens = <?= json_encode(array_map(fn($m) => ['l' => $moyen_labels[$m['moyen']] ?? $m['moyen'], 'v' => (float)$m['total']], $moyens), JSON_UNESCAPED_UNICODE) ?>;
  EduCharts.bar('chartMoyens', {
    labels: moyens.map(function (m) { return m.l; }),
    values: moyens.map(function (m) { return m.v; }),
    colors: ['#326ea9', '#0e7490', '#6d28d9', '#a16207', '#15803d'],
    label: 'Montant',
    format: 'money',
    horizontal: true,
    aria: 'Montant confirmé par moyen de paiement'
  });
});
</script>
</body>
</html>
