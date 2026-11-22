<?php
/**
 * EduConnect — Tableau de bord mentor
 * Sessions, demandes et activité — données réelles.
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/Config/session_check.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/Config/db_connect.php';
session_role_check('mentor');

$user = current_user();
$uid  = (int)$user['id'];

$u_full = h($user['prenom'] . ' ' . $user['nom']);
$u_init = h($user['initiales']);
$current_page = basename(__FILE__);

$mentor = db_row($conn,
    "SELECT id, note_moyenne, nb_sessions, nb_etudiants, taux_satisfaction, actif
     FROM mentors WHERE user_id = ? LIMIT 1",
    "i", [$uid]
);
$mentor_id = (int)($mentor['id'] ?? 0);

// Accepter / refuser une demande (POST)
$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sm_id = (int)($_POST['session_id'] ?? 0);
    $act   = $_POST['action'] ?? '';
    if ($sm_id > 0 && in_array($act, ['accepter', 'refuser'], true) && $mentor_id > 0) {
        $statut = $act === 'accepter' ? 'confirmee' : 'annulee';
        $done = db_exec($conn,
            "UPDATE sessions_mentorat SET statut = ? WHERE id = ? AND mentor_id = ? AND statut = 'demandee'",
            "sii", [$statut, $sm_id, $mentor_id]
        );
        header('Location: mentor_dashboard.php?maj=' . ($done ? '1' : '0'));
        exit;
    }
}
if (isset($_GET['maj'])) {
    $flash = $_GET['maj'] === '1'
        ? ['Session mise à jour.', 'success']
        : ['La session n\'a pas pu être mise à jour.', 'danger'];
}

// Demandes en attente
$demandes = db_query($conn,
    "SELECT sm.id, sm.date_session, sm.heure_debut, sm.type_session,
            m.nom AS module_nom, CONCAT(u.prenom, ' ', u.nom) AS etudiant_nom
     FROM sessions_mentorat sm
     JOIN modules m ON m.id = sm.module_id
     JOIN etudiants e ON e.id = sm.etudiant_id
     JOIN users u ON u.id = e.user_id
     WHERE sm.mentor_id = ? AND sm.statut = 'demandee'
     ORDER BY sm.created_at ASC LIMIT 6",
    "i", [$mentor_id]
);

// Sessions à venir
$sessions = db_query($conn,
    "SELECT sm.date_session, sm.heure_debut, sm.heure_fin, sm.type_session,
            m.nom AS module_nom, CONCAT(u.prenom, ' ', u.nom) AS etudiant_nom
     FROM sessions_mentorat sm
     JOIN modules m ON m.id = sm.module_id
     JOIN etudiants e ON e.id = sm.etudiant_id
     JOIN users u ON u.id = e.user_id
     WHERE sm.mentor_id = ? AND sm.statut = 'confirmee' AND sm.date_session >= CURDATE()
     ORDER BY sm.date_session ASC LIMIT 5",
    "i", [$mentor_id]
);

// Répartition des statuts (toutes sessions)
$repartition = db_row($conn,
    "SELECT COALESCE(SUM(statut = 'demandee'), 0)  AS demandee,
            COALESCE(SUM(statut = 'confirmee'), 0) AS confirmee,
            COALESCE(SUM(statut = 'terminee'), 0)  AS terminee,
            COALESCE(SUM(statut = 'annulee'), 0)   AS annulee,
            COUNT(*) AS total
     FROM sessions_mentorat WHERE mentor_id = ?",
    "i", [$mentor_id]
);
$nb_demandees = (int)($repartition['demandee'] ?? 0);
$nb_confirmees = (int)($repartition['confirmee'] ?? 0);
$nb_terminees = (int)($repartition['terminee'] ?? 0);
$nb_annulees = (int)($repartition['annulee'] ?? 0);
$nb_total = (int)($repartition['total'] ?? 0);

// Activité des 6 derniers mois
$activite = db_query($conn,
    "SELECT DATE_FORMAT(date_session, '%Y-%m') AS mois, COUNT(*) AS total
     FROM sessions_mentorat
     WHERE mentor_id = ? AND date_session >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
     GROUP BY mois ORDER BY mois ASC",
    "i", [$mentor_id]
);

// Modules de spécialité
$modules = db_query($conn,
    "SELECT m.nom, mm.note_obtenue
     FROM mentor_modules mm
     JOIN modules m ON m.id = mm.module_id
     WHERE mm.mentor_id = ?
     ORDER BY m.nom",
    "i", [$mentor_id]
);

$nb_notifs = (int)(db_row($conn,
    "SELECT COUNT(*) AS nb FROM notifications WHERE user_id = ? AND lu = 0",
    "i", [$uid]
)['nb'] ?? 0);

$mois_fr = ['01'=>'Jan','02'=>'Fév','03'=>'Mar','04'=>'Avr','05'=>'Mai','06'=>'Juin','07'=>'Juil','08'=>'Août','09'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Déc'];
$labels_mois = [];
$values_mois = [];
foreach ($activite as $a) {
    $labels_mois[] = ($mois_fr[substr($a['mois'], 5, 2)] ?? $a['mois']);
    $values_mois[] = (int)$a['total'];
}
$satisfaction = (float)($mentor['taux_satisfaction'] ?? 0);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tableau de bord mentor — EduConnect</title>
<link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@300;400;500;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link rel="stylesheet" href="/Assets/css/educonnect-ui.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
</head>
<body class="role-mentor">

<aside class="sidebar" id="sidebar">
  <div class="sidebar__brand">
    <div class="sidebar__mark"><i class="bi bi-person-badge" aria-hidden="true"></i></div>
    <div>
      <div class="sidebar__name">Edu<span>Connect</span></div>
      <div class="sidebar__role">Mentor</div>
    </div>
  </div>

  <nav class="sidebar__nav" aria-label="Navigation principale">
    <a class="nav-link is-active" href="mentor_dashboard.php"><i class="bi bi-grid-1x2"></i>Tableau de bord</a>
    <div class="sidebar__label">Accompagnement</div>
    <a class="nav-link" href="mentor_sessions.php"><i class="bi bi-calendar3"></i>Sessions</a>
    <a class="nav-link" href="mentor_etudiants.php"><i class="bi bi-people"></i>Mes étudiants</a>
    <a class="nav-link" href="mentor_performances.php"><i class="bi bi-graph-up"></i>Performances</a>
    <a class="nav-link" href="mentor_ressources.php"><i class="bi bi-folder2-open"></i>Ressources</a>
    <div class="sidebar__label">Échanges</div>
    <a class="nav-link" href="mentor_messages.php"><i class="bi bi-chat-dots"></i>Messages</a>
  </nav>

  <div class="sidebar__foot">
    <a class="sidebar__user" href="mentor_profil.php">
      <span class="avatar avatar--sm"><?= $u_init ?></span>
      <span class="sidebar__user-meta">
        <span class="sidebar__user-name"><?= $u_full ?></span>
        <span class="sidebar__user-role">Mentor</span>
      </span>
    </a>
    <div class="flex" style="padding:8px 4px 0">
      <a class="nav-link" href="mentor_parametres.php" style="padding:6px 8px;font-size:12px"><i class="bi bi-gear"></i>Paramètres</a>
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
    <div class="topbar__title">Espace mentor <span>· <?= $mentor && (int)$mentor['actif'] === 1 ? 'Compte actif' : 'En attente de validation' ?></span></div>
    <div class="topbar__actions">
      <a class="icon-btn icon-btn--bordered bell" href="mentor_messages.php" aria-label="Messages non lus">
        <i class="bi bi-chat-dots" aria-hidden="true"></i>
        <?php if ($nb_notifs > 0): ?><span class="bell__dot"></span><?php endif; ?>
      </a>
      <a class="icon-btn icon-btn--bordered" href="mentor_profil.php" aria-label="Mon profil">
        <span class="avatar avatar--sm"><?= $u_init ?></span>
      </a>
    </div>
  </header>

  <div class="page">
    <div class="page-head">
      <div>
        <h1 class="page-title">Bonjour, <?= h($user['prenom']) ?></h1>
        <p class="page-sub"><?= $nb_total ?> session<?= $nb_total > 1 ? 's' : '' ?> au total · <?= (int)($mentor['nb_etudiants'] ?? 0) ?> étudiant<?= (int)($mentor['nb_etudiants'] ?? 0) > 1 ? 's' : '' ?> accompagné<?= (int)($mentor['nb_etudiants'] ?? 0) > 1 ? 's' : '' ?></p>
      </div>
      <a class="btn btn--primary" href="mentor_sessions.php">
        <i class="bi bi-plus-lg" aria-hidden="true"></i>Proposer un créneau
      </a>
    </div>

    <?php if ($flash): ?>
      <div class="alert alert--<?= $flash[1] ?>" style="margin-bottom:16px">
        <i class="bi bi-info-circle" aria-hidden="true"></i><span><?= h($flash[0]) ?></span>
      </div>
    <?php endif; ?>

    <section class="stat-grid" aria-label="Indicateurs clés">
      <article class="stat stat--accent">
        <div class="stat__top">
          <span class="stat__label">Sessions à venir</span>
          <span class="stat__icon"><i class="bi bi-calendar-check" aria-hidden="true"></i></span>
        </div>
        <div class="stat__value"><?= count($sessions) ?></div>
        <div class="stat__meta">Confirmées</div>
      </article>

      <article class="stat">
        <div class="stat__top">
          <span class="stat__label">Demandes en attente</span>
          <span class="stat__icon"><i class="bi bi-inbox" aria-hidden="true"></i></span>
        </div>
        <div class="stat__value"><?= $nb_demandees ?></div>
        <div class="stat__meta"><?= $nb_demandees > 0 ? 'À traiter' : 'Aucune demande' ?></div>
      </article>

      <article class="stat">
        <div class="stat__top">
          <span class="stat__label">Sessions terminées</span>
          <span class="stat__icon"><i class="bi bi-check2-circle" aria-hidden="true"></i></span>
        </div>
        <div class="stat__value"><?= $nb_terminees ?></div>
        <div class="stat__meta">Sur <?= $nb_total ?> session<?= $nb_total > 1 ? 's' : '' ?></div>
      </article>

      <article class="stat">
        <div class="stat__top">
          <span class="stat__label">Satisfaction</span>
          <span class="stat__icon"><i class="bi bi-star" aria-hidden="true"></i></span>
        </div>
        <div class="stat__value"><?= number_format($satisfaction, 1, ',', '') ?><small>/5</small></div>
        <div class="stat__meta">Note moyenne <?= number_format((float)($mentor['note_moyenne'] ?? 0), 2, ',', '') ?>/20</div>
      </article>
    </section>

    <div class="grid grid--main" style="margin-bottom:16px">
      <section class="card">
        <div class="card__head">
          <div>
            <h2 class="card__title"><i class="bi bi-activity" aria-hidden="true"></i>Activité des 6 derniers mois</h2>
            <p class="card__hint">Nombre de sessions planifiées ou tenues par mois</p>
          </div>
        </div>
        <div class="card__body">
          <div class="chart-wrap chart-wrap--lg">
            <canvas id="chartActivite"></canvas>
            <div class="chart-empty"><i class="bi bi-bar-chart" aria-hidden="true"></i><span>Aucune session sur la période</span></div>
          </div>
        </div>
      </section>

      <section class="card">
        <div class="card__head">
          <h2 class="card__title"><i class="bi bi-pie-chart" aria-hidden="true"></i>Statut des sessions</h2>
        </div>
        <div class="card__body">
          <div class="donut-wrap">
            <canvas id="chartStatuts"></canvas>
            <div class="donut-center"><div><strong><?= $nb_terminees ?></strong><span>terminées</span></div></div>
            <div class="chart-empty"><i class="bi bi-pie-chart" aria-hidden="true"></i><span>Aucune session enregistrée</span></div>
          </div>
          <div class="legend">
            <span class="legend__item"><span class="legend__dot" style="background:#15803d"></span>Confirmées · <?= $nb_confirmees ?></span>
            <span class="legend__item"><span class="legend__dot" style="background:#b45309"></span>Demandées · <?= $nb_demandees ?></span>
            <span class="legend__item"><span class="legend__dot" style="background:#326ea9"></span>Terminées · <?= $nb_terminees ?></span>
            <span class="legend__item"><span class="legend__dot" style="background:#c0272d"></span>Annulées · <?= $nb_annulees ?></span>
          </div>
        </div>
      </section>
    </div>

    <div class="grid grid--2">
      <section class="card">
        <div class="card__head">
          <h2 class="card__title"><i class="bi bi-calendar3" aria-hidden="true"></i>Prochaines sessions</h2>
          <a class="btn btn--ghost btn--sm" href="mentor_sessions.php">Toutes les sessions</a>
        </div>
        <div class="card__body card__body--flush">
          <?php if ($sessions): ?>
            <div class="list">
            <?php foreach ($sessions as $s): ?>
              <div class="list__item">
                <span class="list__icon list__icon--success"><i class="bi bi-camera-video" aria-hidden="true"></i></span>
                <div class="list__body">
                  <div class="list__title"><?= h($s['etudiant_nom']) ?> <span class="muted" style="font-weight:400">· <?= h($s['module_nom']) ?></span></div>
                  <div class="list__meta"><?= h(date('d/m/Y', strtotime($s['date_session']))) ?> · <?= h(substr($s['heure_debut'], 0, 5)) ?> – <?= h(substr($s['heure_fin'], 0, 5)) ?></div>
                </div>
                <span class="badge badge--info badge--plain"><?= $s['type_session'] === 'en_ligne' ? 'En ligne' : 'Présentiel' ?></span>
              </div>
            <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="empty"><i class="bi bi-calendar-x" aria-hidden="true"></i><strong>Aucune session à venir</strong><span>Vos prochaines sessions confirmées apparaîtront ici.</span></div>
          <?php endif; ?>
        </div>
      </section>

      <section class="card">
        <div class="card__head">
          <h2 class="card__title"><i class="bi bi-inbox" aria-hidden="true"></i>Demandes à traiter</h2>
          <span class="badge badge--<?= $nb_demandees > 0 ? 'warning' : 'success' ?>"><?= $nb_demandees ?> en attente</span>
        </div>
        <div class="card__body card__body--flush">
          <?php if ($demandes): ?>
            <div class="list">
            <?php foreach ($demandes as $d): ?>
              <div class="list__item">
                <span class="list__icon list__icon--warning"><i class="bi bi-hourglass-split" aria-hidden="true"></i></span>
                <div class="list__body">
                  <div class="list__title"><?= h($d['etudiant_nom']) ?></div>
                  <div class="list__meta"><?= h($d['module_nom']) ?> · <?= h(date('d/m/Y', strtotime($d['date_session']))) ?> à <?= h(substr($d['heure_debut'], 0, 5)) ?></div>
                </div>
                <form method="POST" class="flex gap-8" style="flex-shrink:0">
                  <input type="hidden" name="session_id" value="<?= (int)$d['id'] ?>">
                  <button class="btn btn--sm btn--primary" type="submit" name="action" value="accepter">
                    <i class="bi bi-check-lg" aria-hidden="true"></i>Accepter
                  </button>
                  <button class="btn btn--sm btn--outline" type="submit" name="action" value="refuser">Refuser</button>
                </form>
              </div>
            <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="empty"><i class="bi bi-inbox" aria-hidden="true"></i><strong>Aucune demande en attente</strong><span>Les nouvelles demandes d'étudiants arriveront ici.</span></div>
          <?php endif; ?>
        </div>
      </section>
    </div>

    <?php if ($modules): ?>
    <section class="card" style="margin-top:16px">
      <div class="card__head">
        <h2 class="card__title"><i class="bi bi-journal-bookmark" aria-hidden="true"></i>Modules de spécialité</h2>
      </div>
      <div class="card__body">
        <div class="flex" style="flex-wrap:wrap;gap:8px">
          <?php foreach ($modules as $m): ?>
            <span class="badge badge--info badge--plain">
              <?= h($m['nom']) ?><?= $m['note_obtenue'] !== null ? ' · ' . number_format((float)$m['note_obtenue'], 1, ',', '') . '/20' : '' ?>
            </span>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
    <?php endif; ?>
  </div>
</main>

<div class="toast" id="toast" role="status" aria-live="polite"></div>
<script src="/Assets/js/educonnect-charts.js"></script>
<script src="/Assets/js/educonnect-ui.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  EduCharts.line('chartActivite', {
    labels: <?= json_encode($labels_mois, JSON_UNESCAPED_UNICODE) ?>,
    values: <?= json_encode($values_mois) ?>,
    label: 'Sessions',
    format: 'count',
    color: '#6d28d9',
    aria: 'Sessions de mentorat par mois'
  });

  EduCharts.doughnut('chartStatuts', {
    labels: ['Confirmées', 'Demandées', 'Terminées', 'Annulées'],
    values: [<?= $nb_confirmees ?>, <?= $nb_demandees ?>, <?= $nb_terminees ?>, <?= $nb_annulees ?>],
    colors: ['#15803d', '#b45309', '#326ea9', '#c0272d'],
    format: 'count',
    aria: 'Répartition des sessions par statut',
    empty: 'Aucune session enregistrée'
  });
});
</script>
</body>
</html>
