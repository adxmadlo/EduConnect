<?php
/**
 * EduConnect — Tableau de bord parent
 * Suivi de l'enfant sélectionné, données réelles uniquement.
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/Config/session_check.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/Config/db_connect.php';
session_role_check('parent');

$user  = current_user();
$uid   = (int)$user['id'];
$annee = '2025-2026';

$u_full = h($user['prenom'] . ' ' . $user['nom']);
$u_init = h($user['initiales']);
$current_page = basename(__FILE__);

// Enfants rattachés au compte
$enfants = db_query($conn,
    "SELECT e.id AS etu_id, e.credits_obtenus, e.credits_requis,
            c.code AS classe_code, c.nom AS classe_nom,
            u.prenom, u.nom, u.numero_ucao
     FROM etudiants e
     JOIN users u ON u.id = e.user_id
     JOIN classes c ON c.id = e.classe_id
     WHERE e.parent_id = ?
     ORDER BY u.prenom, u.nom",
    "i", [$uid]
);

$ids = array_map(fn($e) => (int)$e['etu_id'], $enfants);
$selected_id = (int)($_GET['enfant'] ?? 0);
if (!in_array($selected_id, $ids, true)) {
    $selected_id = $ids[0] ?? 0;
}
$enfant = null;
foreach ($enfants as $e) {
    if ((int)$e['etu_id'] === $selected_id) { $enfant = $e; break; }
}

$enfant_nom   = $enfant ? h($enfant['prenom'] . ' ' . $enfant['nom']) : 'Aucun enfant rattaché';
$enfant_classe = $enfant ? h($enfant['classe_code']) : '—';
$enfant_classe_nom = $enfant ? h($enfant['classe_nom']) : '';
$enfant_init = $enfant ? h(substr($enfant['prenom'], 0, 1) . substr($enfant['nom'], 0, 1)) : '?';
$credits_obtenus = (int)($enfant['credits_obtenus'] ?? 0);
$credits_requis  = max(1, (int)($enfant['credits_requis'] ?? 30));

// Scolarité
$frais = db_row($conn,
    "SELECT f.montant_total, f.plan, f.statut, f.date_echeance,
            COALESCE(SUM(CASE WHEN p.statut = 'confirme' THEN p.montant ELSE 0 END), 0) AS paye
     FROM frais_scolarite f
     LEFT JOIN paiements p ON p.frais_id = f.id
     WHERE f.etudiant_id = ? AND f.annee_acad = ?
     GROUP BY f.id LIMIT 1",
    "is", [$selected_id, $annee]
);
$montant_total = (float)($frais['montant_total'] ?? 0);
$montant_paye  = (float)($frais['paye'] ?? 0);
$solde         = max(0, $montant_total - $montant_paye);
$pct_paye      = $montant_total > 0 ? (int)round($montant_paye / $montant_total * 100) : 0;

// Résultats
$notes = db_query($conn,
    "SELECT m.nom AS module_nom, m.code, n.note_finale, n.semestre
     FROM notes n
     JOIN modules m ON m.id = n.module_id
     WHERE n.etudiant_id = ? AND n.publie = 1 AND n.annee_acad = ?
     ORDER BY m.nom",
    "is", [$selected_id, $annee]
);
$nb_notes = count($notes);
$moyenne = $nb_notes > 0
    ? round(array_sum(array_map(fn($n) => (float)$n['note_finale'], $notes)) / $nb_notes, 2)
    : null;
$nb_sous_moyenne = count(array_filter($notes, fn($n) => (float)$n['note_finale'] < 10));

// Historique des paiements
$paiements = db_query($conn,
    "SELECT p.montant, p.moyen, p.statut, p.recu_numero, COALESCE(p.confirmed_at, p.created_at) AS date_op
     FROM frais_scolarite f
     JOIN paiements p ON p.frais_id = f.id
     WHERE f.etudiant_id = ?
     ORDER BY date_op DESC LIMIT 5",
    "i", [$selected_id]
);

// Prochains cours
$prochains_cours = db_query($conn,
    "SELECT et.date_debut, et.type_creneau, m.nom AS module_nom, s.nom AS salle_nom
     FROM emplois_du_temps et
     JOIN modules m ON m.id = et.module_id
     JOIN etudiants e ON e.classe_id = et.classe_id
     LEFT JOIN salles s ON s.id = et.salle_id
     WHERE e.id = ? AND et.date_debut >= NOW()
     ORDER BY et.date_debut ASC LIMIT 4",
    "i", [$selected_id]
);

$nb_notifs = (int)(db_row($conn,
    "SELECT COUNT(*) AS nb FROM notifications WHERE user_id = ? AND lu = 0",
    "i", [$uid]
)['nb'] ?? 0);

$fmt = fn($n) => number_format((float)$n, 0, ',', ' ') . ' FCFA';
$jours = ['lundi','mardi','mercredi','jeudi','vendredi','samedi','dimanche'];
$moyens = ['wave' => 'Wave', 'orange_money' => 'Orange Money', 'carte' => 'Carte bancaire', 'virement' => 'Virement', 'especes' => 'Espèces'];
$creneaux = ['cours1'=>'08:00 – 11:00','cours2'=>'11:15 – 13:00','cours3'=>'14:00 – 16:45','revision'=>'Révisions','examen'=>'Examen','rattrapage'=>'Rattrapage'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tableau de bord parent — EduConnect</title>
<link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@300;400;500;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link rel="stylesheet" href="/Assets/css/educonnect-ui.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
</head>
<body class="role-parent">

<aside class="sidebar" id="sidebar">
  <div class="sidebar__brand">
    <div class="sidebar__mark"><i class="bi bi-person-hearts" aria-hidden="true"></i></div>
    <div>
      <div class="sidebar__name">Edu<span>Connect</span></div>
      <div class="sidebar__role">Parent / Tuteur</div>
    </div>
  </div>

  <nav class="sidebar__nav" aria-label="Navigation principale">
    <a class="nav-link is-active" href="parent_dashboard.php"><i class="bi bi-grid-1x2"></i>Tableau de bord</a>
    <div class="sidebar__label">Suivi</div>
    <a class="nav-link" href="parent_etudiants.php"><i class="bi bi-person"></i>Mon enfant</a>
    <a class="nav-link" href="parent_notes.php"><i class="bi bi-clipboard-data"></i>Notes &amp; bulletins</a>
    <a class="nav-link" href="parent_emploi_du_temps.php"><i class="bi bi-calendar3"></i>Emploi du temps</a>
    <a class="nav-link" href="parent_paiements.php"><i class="bi bi-credit-card"></i>Paiements</a>
    <div class="sidebar__label">Communication</div>
    <a class="nav-link" href="parent_messages.php"><i class="bi bi-chat-dots"></i>Messages</a>
    <a class="nav-link" href="parent_notifications.php">
      <i class="bi bi-bell"></i>Notifications
      <?php if ($nb_notifs > 0): ?><span class="nav-link__count"><?= $nb_notifs ?></span><?php endif; ?>
    </a>
  </nav>

  <div class="sidebar__foot">
    <a class="sidebar__user" href="parent_profil.php">
      <span class="avatar avatar--sm"><?= $u_init ?></span>
      <span class="sidebar__user-meta">
        <span class="sidebar__user-name"><?= $u_full ?></span>
        <span class="sidebar__user-role">Parent / Tuteur</span>
      </span>
    </a>
    <div class="flex" style="padding:8px 4px 0">
      <a class="nav-link" href="parent_parametres.php" style="padding:6px 8px;font-size:12px"><i class="bi bi-gear"></i>Paramètres</a>
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
    <div class="topbar__title">Suivi de <?= $enfant_nom ?> <span>· <?= $enfant_classe ?></span></div>
    <div class="topbar__actions">
      <a class="icon-btn icon-btn--bordered bell" href="parent_notifications.php" aria-label="Notifications">
        <i class="bi bi-bell" aria-hidden="true"></i>
        <?php if ($nb_notifs > 0): ?><span class="bell__dot"></span><?php endif; ?>
      </a>
      <a class="icon-btn icon-btn--bordered" href="parent_profil.php" aria-label="Mon profil">
        <span class="avatar avatar--sm"><?= $u_init ?></span>
      </a>
    </div>
  </header>

  <div class="page">
    <div class="page-head">
      <div>
        <h1 class="page-title">Bonjour, <?= h($user['prenom']) ?></h1>
        <p class="page-sub"><?= $enfant ? h($enfant['prenom'] . ' ' . $enfant['nom']) . ' · ' . $enfant_classe_nom : 'Aucun enfant rattaché à votre compte' ?></p>
      </div>
      <?php if (count($enfants) > 1): ?>
        <div class="segmented" role="group" aria-label="Choisir un enfant">
          <?php foreach ($enfants as $e): ?>
            <a class="btn btn--sm <?= (int)$e['etu_id'] === $selected_id ? 'btn--primary' : 'btn--ghost' ?>"
               href="?enfant=<?= (int)$e['etu_id'] ?>"><?= h($e['prenom']) ?></a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <?php if (!$enfant): ?>
      <div class="alert alert--warning">
        <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
        <div>Aucun dossier étudiant n'est rattaché à votre compte. Contactez l'administration pour lier votre enfant.</div>
      </div>
    <?php else: ?>

    <section class="stat-grid" aria-label="Indicateurs clés">
      <article class="stat stat--accent">
        <div class="stat__top">
          <span class="stat__label">Moyenne générale</span>
          <span class="stat__icon"><i class="bi bi-graph-up" aria-hidden="true"></i></span>
        </div>
        <div class="stat__value"><?= $moyenne !== null ? number_format($moyenne, 2, ',', '') : '—' ?><small>/20</small></div>
        <div class="stat__meta"><?= $nb_notes ?> note<?= $nb_notes > 1 ? 's' : '' ?> publiée<?= $nb_notes > 1 ? 's' : '' ?></div>
      </article>

      <article class="stat">
        <div class="stat__top">
          <span class="stat__label">Scolarité réglée</span>
          <span class="stat__icon"><i class="bi bi-wallet2" aria-hidden="true"></i></span>
        </div>
        <div class="stat__value"><?= $pct_paye ?><small>%</small></div>
        <div class="stat__meta"><?= $fmt($montant_paye) ?> sur <?= $fmt($montant_total) ?></div>
      </article>

      <article class="stat">
        <div class="stat__top">
          <span class="stat__label">Reste à payer</span>
          <span class="stat__icon"><i class="bi bi-hourglass-split" aria-hidden="true"></i></span>
        </div>
        <div class="stat__value" style="font-size:20px"><?= $fmt($solde) ?></div>
        <div class="stat__meta"><?= $frais && $frais['date_echeance'] ? 'Échéance : ' . h(date('d/m/Y', strtotime($frais['date_echeance']))) : 'Aucune échéance définie' ?></div>
      </article>

      <article class="stat">
        <div class="stat__top">
          <span class="stat__label">Modules en difficulté</span>
          <span class="stat__icon"><i class="bi bi-exclamation-circle" aria-hidden="true"></i></span>
        </div>
        <div class="stat__value"><?= $nb_sous_moyenne ?></div>
        <div class="stat__meta">Note inférieure à 10/20</div>
      </article>
    </section>

    <div class="grid grid--main" style="margin-bottom:16px">
      <section class="card">
        <div class="card__head">
          <div>
            <h2 class="card__title"><i class="bi bi-bar-chart" aria-hidden="true"></i>Résultats par module</h2>
            <p class="card__hint">Notes publiées de <?= $enfant_nom ?> · seuil de validation à 10/20</p>
          </div>
        </div>
        <div class="card__body">
          <div class="chart-wrap chart-wrap--lg">
            <canvas id="chartNotes"></canvas>
            <div class="chart-empty"><i class="bi bi-bar-chart" aria-hidden="true"></i><span>Aucune note publiée pour le moment</span></div>
          </div>
        </div>
      </section>

      <section class="card">
        <div class="card__head">
          <h2 class="card__title"><i class="bi bi-pie-chart" aria-hidden="true"></i>Scolarité</h2>
        </div>
        <div class="card__body">
          <div class="donut-wrap">
            <canvas id="chartScolarite"></canvas>
            <div class="donut-center"><div><strong><?= $pct_paye ?>%</strong><span>réglé</span></div></div>
            <div class="chart-empty"><i class="bi bi-pie-chart" aria-hidden="true"></i><span>Aucun frais enregistré</span></div>
          </div>
          <div class="legend">
            <span class="legend__item"><span class="legend__dot" style="background:#15803d"></span>Payé</span>
            <span class="legend__item"><span class="legend__dot" style="background:#e3e8ef"></span>Reste</span>
          </div>
          <a class="btn btn--primary" href="parent_paiements.php" style="width:100%;margin-top:14px">
            <i class="bi bi-credit-card" aria-hidden="true"></i>Voir les paiements
          </a>
        </div>
      </section>
    </div>

    <div class="grid grid--2">
      <section class="card">
        <div class="card__head">
          <h2 class="card__title"><i class="bi bi-receipt" aria-hidden="true"></i>Derniers paiements</h2>
          <a class="btn btn--ghost btn--sm" href="parent_paiements.php">Historique</a>
        </div>
        <div class="card__body card__body--flush">
          <?php if ($paiements): ?>
          <div class="table-wrap">
            <table class="table">
              <thead><tr><th>Date</th><th>Moyen</th><th class="num">Montant</th><th>Statut</th></tr></thead>
              <tbody>
              <?php foreach ($paiements as $p):
                $st = $p['statut'] === 'confirme' ? ['Payé', 'success'] : ($p['statut'] === 'en_attente' ? ['En attente', 'warning'] : ($p['statut'] === 'rembourse' ? ['Remboursé', 'info'] : ['Échec', 'danger']));
              ?>
                <tr>
                  <td><?= h(date('d/m/Y', strtotime($p['date_op']))) ?></td>
                  <td><?= h($moyens[$p['moyen']] ?? '—') ?></td>
                  <td class="num"><?= $fmt($p['montant']) ?></td>
                  <td><span class="badge badge--<?= $st[1] ?>"><?= $st[0] ?></span></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php else: ?>
            <div class="empty"><i class="bi bi-receipt" aria-hidden="true"></i><strong>Aucun paiement enregistré</strong><span>Les règlements apparaîtront ici dès le premier versement.</span></div>
          <?php endif; ?>
        </div>
      </section>

      <section class="card">
        <div class="card__head">
          <h2 class="card__title"><i class="bi bi-calendar3" aria-hidden="true"></i>Prochains cours</h2>
          <a class="btn btn--ghost btn--sm" href="parent_emploi_du_temps.php">Emploi du temps</a>
        </div>
        <div class="card__body card__body--flush">
          <?php if ($prochains_cours): ?>
            <div class="list">
            <?php foreach ($prochains_cours as $c): $ts = strtotime($c['date_debut']); ?>
              <div class="list__item">
                <span class="list__icon list__icon--accent"><i class="bi bi-book" aria-hidden="true"></i></span>
                <div class="list__body">
                  <div class="list__title"><?= h($c['module_nom']) ?></div>
                  <div class="list__meta"><?= h(ucfirst($jours[(int)date('N', $ts) - 1])) ?> <?= date('d/m', $ts) ?> · <?= h($creneaux[$c['type_creneau']] ?? '') ?></div>
                </div>
                <div class="list__aside muted" style="font-size:11px"><?= h($c['salle_nom'] ?? '—') ?></div>
              </div>
            <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="empty"><i class="bi bi-calendar-x" aria-hidden="true"></i><strong>Aucun cours planifié</strong><span>L'emploi du temps sera publié par l'administration.</span></div>
          <?php endif; ?>
        </div>
      </section>
    </div>
    <?php endif; ?>
  </div>
</main>

<div class="toast" id="toast" role="status" aria-live="polite"></div>
<script src="/Assets/js/educonnect-charts.js"></script>
<script src="/Assets/js/educonnect-ui.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var notes = <?= json_encode(array_map(fn($n) => ['m' => $n['module_nom'], 'v' => (float)$n['note_finale']], $notes), JSON_UNESCAPED_UNICODE) ?>;
  EduCharts.bar('chartNotes', {
    labels: notes.map(function (n) { return n.m; }),
    values: notes.map(function (n) { return n.v; }),
    colors: notes.map(function (n) { return n.v >= 10 ? '#15803d' : '#c0272d'; }),
    label: 'Note',
    format: 'score',
    max: 20,
    aria: 'Notes par module sur 20'
  });

  EduCharts.doughnut('chartScolarite', {
    labels: ['Payé', 'Reste à payer'],
    values: [<?= (float)$montant_paye ?>, <?= (float)$solde ?>],
    colors: ['#15803d', '#e3e8ef'],
    format: 'money',
    aria: 'Part de la scolarité réglée',
    empty: 'Aucun frais de scolarité enregistré'
  });
});
</script>
</body>
</html>
