<?php
/**
 * EduConnect — Tableau de bord étudiant
 * Toutes les valeurs affichées proviennent de la base ; aucun exemple codé en dur.
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/Config/session_check.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/Config/db_connect.php';
session_role_check('etudiant');

$user = current_user();
$uid  = (int)$user['id'];
$annee = '2025-2026';

$u_full = h($user['prenom'] . ' ' . $user['nom']);
$u_init = h($user['initiales']);
$current_page = basename(__FILE__);

// Profil académique
$etudiant = db_row($conn,
    "SELECT e.id AS etu_id, e.credits_obtenus, e.credits_requis, e.statut_academique,
            c.code AS classe_code, c.nom AS classe_nom, c.annee_etude
     FROM etudiants e
     JOIN classes c ON c.id = e.classe_id
     WHERE e.user_id = ? LIMIT 1",
    "i", [$uid]
);
$etu_id = (int)($etudiant['etu_id'] ?? 0);
$classe_label = h($etudiant['classe_code'] ?? '—');
$credits_obtenus = (int)($etudiant['credits_obtenus'] ?? 0);
$credits_requis  = max(1, (int)($etudiant['credits_requis'] ?? 30));
$credits_pct = min(100, (int)round($credits_obtenus / $credits_requis * 100));

// Frais de scolarité
$frais = db_row($conn,
    "SELECT f.montant_total, f.plan, f.statut, f.date_echeance,
            COALESCE(SUM(CASE WHEN p.statut = 'confirme' THEN p.montant ELSE 0 END), 0) AS paye
     FROM frais_scolarite f
     LEFT JOIN paiements p ON p.frais_id = f.id
     WHERE f.etudiant_id = ? AND f.annee_acad = ?
     GROUP BY f.id LIMIT 1",
    "is", [$etu_id, $annee]
);
$montant_total = (float)($frais['montant_total'] ?? 0);
$montant_paye  = (float)($frais['paye'] ?? 0);
$solde         = max(0, $montant_total - $montant_paye);
$pct_paye      = $montant_total > 0 ? (int)round($montant_paye / $montant_total * 100) : 0;

// Notes publiées
$notes_modules = db_query($conn,
    "SELECT m.nom AS module_nom, m.code, n.note_finale, n.semestre
     FROM notes n
     JOIN modules m ON m.id = n.module_id
     WHERE n.etudiant_id = ? AND n.publie = 1 AND n.annee_acad = ?
     ORDER BY n.note_finale DESC",
    "is", [$etu_id, $annee]
);
$nb_notes = count($notes_modules);
$moyenne = $nb_notes > 0
    ? round(array_sum(array_map(fn($n) => (float)$n['note_finale'], $notes_modules)) / $nb_notes, 2)
    : null;
$modules_valides = count(array_filter($notes_modules, fn($n) => (float)$n['note_finale'] >= 10));

// Évolution des notes (ordre de saisie)
$evolution = db_query($conn,
    "SELECT DATE_FORMAT(n.saisie_le, '%d/%m') AS jour, n.note_finale
     FROM notes n
     WHERE n.etudiant_id = ? AND n.publie = 1 AND n.note_finale IS NOT NULL
     ORDER BY n.saisie_le ASC LIMIT 12",
    "i", [$etu_id]
);

// Prochains cours de la classe
$prochains_cours = db_query($conn,
    "SELECT et.date_debut, et.type_creneau, m.nom AS module_nom, s.nom AS salle_nom
     FROM emplois_du_temps et
     JOIN modules m ON m.id = et.module_id
     JOIN etudiants e ON e.classe_id = et.classe_id
     LEFT JOIN salles s ON s.id = et.salle_id
     WHERE e.user_id = ? AND et.date_debut >= NOW()
     ORDER BY et.date_debut ASC LIMIT 5",
    "i", [$uid]
);

// Sessions de mentorat à venir
$sessions = db_query($conn,
    "SELECT sm.date_session, sm.heure_debut, sm.type_session, m.nom AS module_nom,
            CONCAT(mu.prenom, ' ', mu.nom) AS mentor_nom
     FROM sessions_mentorat sm
     JOIN modules m ON m.id = sm.module_id
     JOIN mentors mt ON mt.id = sm.mentor_id
     JOIN users mu ON mu.id = mt.user_id
     WHERE sm.etudiant_id = ? AND sm.statut = 'confirmee' AND sm.date_session >= CURDATE()
     ORDER BY sm.date_session ASC LIMIT 3",
    "i", [$etu_id]
);

$nb_notifs = (int)(db_row($conn,
    "SELECT COUNT(*) AS nb FROM notifications WHERE user_id = ? AND lu = 0",
    "i", [$uid]
)['nb'] ?? 0);

$fmt = fn($n) => number_format((float)$n, 0, ',', ' ') . ' FCFA';
$jours = ['lundi','mardi','mercredi','jeudi','vendredi','samedi','dimanche'];
$creneaux = ['cours1'=>'08:00 – 11:00','cours2'=>'11:15 – 13:00','cours3'=>'14:00 – 16:45','revision'=>'Révisions','examen'=>'Examen','rattrapage'=>'Rattrapage'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tableau de bord — EduConnect</title>
<link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@300;400;500;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link rel="stylesheet" href="/Assets/css/educonnect-ui.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
</head>
<body class="role-etudiant">

<aside class="sidebar" id="sidebar">
  <div class="sidebar__brand">
    <div class="sidebar__mark"><i class="bi bi-mortarboard-fill" aria-hidden="true"></i></div>
    <div>
      <div class="sidebar__name">Edu<span>Connect</span></div>
      <div class="sidebar__role">Étudiant</div>
    </div>
  </div>

  <nav class="sidebar__nav" aria-label="Navigation principale">
    <a class="nav-link is-active" href="etudiant_dashboard.php"><i class="bi bi-grid-1x2"></i>Tableau de bord</a>
    <div class="sidebar__label">Scolarité</div>
    <a class="nav-link" href="etudiant_cours.php"><i class="bi bi-journal-bookmark"></i>Mes cours</a>
    <a class="nav-link" href="etudiants_notes.php"><i class="bi bi-clipboard-data"></i>Mes notes</a>
    <a class="nav-link" href="etudiant_emploi_du_temps.php"><i class="bi bi-calendar3"></i>Emploi du temps</a>
    <a class="nav-link" href="etudiant_reservations.php"><i class="bi bi-door-open"></i>Réservations</a>
    <div class="sidebar__label">Accompagnement</div>
    <a class="nav-link" href="etudiant_mentor.php"><i class="bi bi-people"></i>Mentorat</a>
    <a class="nav-link" href="etudiant_paiements.php"><i class="bi bi-credit-card"></i>Paiements</a>
    <a class="nav-link" href="etudiant_messages.php"><i class="bi bi-chat-dots"></i>Messages</a>
    <a class="nav-link" href="etudiant_notifications.php">
      <i class="bi bi-bell"></i>Notifications
      <?php if ($nb_notifs > 0): ?><span class="nav-link__count"><?= $nb_notifs ?></span><?php endif; ?>
    </a>
  </nav>

  <div class="sidebar__foot">
    <a class="sidebar__user" href="etudiant_profil.php">
      <span class="avatar avatar--sm"><?= $u_init ?></span>
      <span class="sidebar__user-meta">
        <span class="sidebar__user-name"><?= $u_full ?></span>
        <span class="sidebar__user-role"><?= h($user['numero_ucao'] ?: 'Étudiant') ?></span>
      </span>
    </a>
    <div class="flex" style="padding:8px 4px 0">
      <a class="nav-link" href="etudiant_parametres.php" style="padding:6px 8px;font-size:12px"><i class="bi bi-gear"></i>Paramètres</a>
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
    <div class="topbar__title">Tableau de bord <span>· <?= $classe_label ?></span></div>
    <div class="topbar__actions">
      <a class="icon-btn icon-btn--bordered bell" href="etudiant_notifications.php" aria-label="Notifications">
        <i class="bi bi-bell" aria-hidden="true"></i>
        <?php if ($nb_notifs > 0): ?><span class="bell__dot"></span><?php endif; ?>
      </a>
      <a class="icon-btn icon-btn--bordered" href="etudiant_profil.php" aria-label="Mon profil">
        <span class="avatar avatar--sm" style="background:var(--accent)"><?= $u_init ?></span>
      </a>
    </div>
  </header>

  <div class="page">
    <div class="page-head">
      <div>
        <h1 class="page-title">Bonjour, <?= h($user['prenom']) ?></h1>
        <p class="page-sub">Année académique <?= h($annee) ?> · <?= h($etudiant['classe_nom'] ?? 'Classe non assignée') ?></p>
      </div>
      <div class="segmented" role="group" aria-label="Période affichée">
        <button type="button" class="is-active" aria-pressed="true" onclick="EduUI.setPeriod(this,{text:'Cette semaine'})">Semaine</button>
        <button type="button" aria-pressed="false" onclick="EduUI.setPeriod(this,{text:'Ce mois'})">Mois</button>
        <button type="button" aria-pressed="false" onclick="EduUI.setPeriod(this,{text:'Cette année'})">Année</button>
      </div>
    </div>

    <?php if ($etu_id === 0): ?>
      <div class="alert alert--warning" style="margin-bottom:16px">
        <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
        <div>Votre dossier étudiant n'est pas encore rattaché à une classe. Contactez l'administration pour activer vos notes et votre scolarité.</div>
      </div>
    <?php endif; ?>

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
          <span class="stat__label">Crédits validés</span>
          <span class="stat__icon"><i class="bi bi-award" aria-hidden="true"></i></span>
        </div>
        <div class="stat__value"><?= $credits_obtenus ?><small>/<?= $credits_requis ?></small></div>
        <div class="progress" style="margin-top:2px">
          <div class="progress__bar" style="width:<?= $credits_pct ?>%"></div>
        </div>
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
          <span class="stat__label">Modules validés</span>
          <span class="stat__icon"><i class="bi bi-check2-circle" aria-hidden="true"></i></span>
        </div>
        <div class="stat__value"><?= $modules_valides ?><small>/<?= $nb_notes ?></small></div>
        <div class="stat__meta">Seuil de validation : 10/20</div>
      </article>
    </section>

    <div class="grid grid--main" style="margin-bottom:16px">
      <section class="card">
        <div class="card__head">
          <div>
            <h2 class="card__title"><i class="bi bi-activity" aria-hidden="true"></i>Évolution de mes notes</h2>
            <p class="card__hint">Notes publiées, dans l'ordre de saisie par les enseignants</p>
          </div>
        </div>
        <div class="card__body">
          <div class="chart-wrap">
            <canvas id="chartEvolution"></canvas>
            <div class="chart-empty"><i class="bi bi-bar-chart" aria-hidden="true"></i><span>Aucune note publiée pour le moment</span></div>
          </div>
        </div>
      </section>

      <section class="card">
        <div class="card__head">
          <h2 class="card__title"><i class="bi bi-pie-chart" aria-hidden="true"></i>Ma scolarité</h2>
        </div>
        <div class="card__body">
          <div class="donut-wrap">
            <canvas id="chartScolarite"></canvas>
            <div class="donut-center" id="chartScolariteCenter">
              <div><strong><?= $pct_paye ?>%</strong><span>réglé</span></div>
            </div>
            <div class="chart-empty"><i class="bi bi-pie-chart" aria-hidden="true"></i><span>Aucun frais enregistré</span></div>
          </div>
          <div class="legend">
            <span class="legend__item"><span class="legend__dot" style="background:#15803d"></span>Payé · <?= $fmt($montant_paye) ?></span>
            <span class="legend__item"><span class="legend__dot" style="background:#e3e8ef"></span>Reste · <?= $fmt($solde) ?></span>
          </div>
          <?php if ($solde > 0): ?>
            <a class="btn btn--primary" href="etudiant_paiements.php" style="width:100%;margin-top:14px">
              <i class="bi bi-credit-card" aria-hidden="true"></i>Régler <?= $fmt($solde) ?>
            </a>
          <?php else: ?>
            <div class="alert alert--success" style="margin-top:14px">
              <i class="bi bi-check-circle" aria-hidden="true"></i><span>Scolarité entièrement réglée</span>
            </div>
          <?php endif; ?>
        </div>
      </section>
    </div>

    <section class="card">
        <div class="card__head">
          <h2 class="card__title"><i class="bi bi-clipboard-data" aria-hidden="true"></i>Mes notes par module</h2>
          <a class="btn btn--ghost btn--sm" href="etudiants_notes.php">Tout voir</a>
        </div>
        <div class="card__body card__body--flush">
          <?php if ($notes_modules): ?>
          <div class="table-wrap">
            <table class="table">
              <thead><tr><th>Module</th><th>Semestre</th><th class="num">Note</th><th class="hide-sm">Mention</th></tr></thead>
              <tbody>
              <?php foreach (array_slice($notes_modules, 0, 6) as $n):
                $note = (float)$n['note_finale'];
                $mention = $note >= 16 ? ['Très bien', 'success'] : ($note >= 14 ? ['Bien', 'success'] : ($note >= 12 ? ['Assez bien', 'info'] : ($note >= 10 ? ['Passable', 'warning'] : ['Insuffisant', 'danger'])));
              ?>
                <tr>
                  <td><strong><?= h($n['module_nom']) ?></strong><br><span class="muted" style="font-size:11px"><?= h($n['code']) ?></span></td>
                  <td>S<?= (int)$n['semestre'] ?></td>
                  <td class="num"><?= number_format($note, 2, ',', '') ?>/20</td>
                  <td class="hide-sm"><span class="badge badge--<?= $mention[1] ?>"><?= $mention[0] ?></span></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php else: ?>
            <div class="empty"><i class="bi bi-clipboard" aria-hidden="true"></i><strong>Aucune note publiée</strong><span>Vos notes apparaîtront dès leur publication par vos enseignants.</span></div>
          <?php endif; ?>
        </div>
    </section>

    <div class="grid grid--2" style="margin-top:16px">
        <section class="card">
          <div class="card__head">
            <h2 class="card__title"><i class="bi bi-calendar3" aria-hidden="true"></i>Prochains cours</h2>
            <a class="btn btn--ghost btn--sm" href="etudiant_emploi_du_temps.php">Emploi du temps</a>
          </div>
          <div class="card__body card__body--flush">
            <?php if ($prochains_cours): ?>
              <div class="list">
              <?php foreach ($prochains_cours as $c):
                $ts = strtotime($c['date_debut']);
              ?>
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
              <div class="empty"><i class="bi bi-calendar-x" aria-hidden="true"></i><strong>Aucun cours planifié</strong><span>Les prochains créneaux seront publiés par l'administration.</span></div>
            <?php endif; ?>
          </div>
        </section>

        <section class="card">
          <div class="card__head">
            <h2 class="card__title"><i class="bi bi-people" aria-hidden="true"></i>Mentorat à venir</h2>
            <a class="btn btn--ghost btn--sm" href="etudiant_mentor.php">Gérer</a>
          </div>
          <div class="card__body card__body--flush">
            <?php if ($sessions): ?>
              <div class="list">
              <?php foreach ($sessions as $s): ?>
                <div class="list__item">
                  <span class="list__icon list__icon--success"><i class="bi bi-camera-video" aria-hidden="true"></i></span>
                  <div class="list__body">
                    <div class="list__title"><?= h($s['module_nom']) ?></div>
                    <div class="list__meta"><?= h($s['mentor_nom']) ?> · <?= h(date('d/m/Y', strtotime($s['date_session']))) ?> à <?= h(substr($s['heure_debut'], 0, 5)) ?></div>
                  </div>
                  <span class="badge badge--info badge--plain"><?= $s['type_session'] === 'en_ligne' ? 'En ligne' : 'Présentiel' ?></span>
                </div>
              <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="empty"><i class="bi bi-calendar-check" aria-hidden="true"></i><strong>Aucune session confirmée</strong><span>Réservez un créneau avec un mentor depuis l'espace Mentorat.</span></div>
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
  EduCharts.line('chartEvolution', {
    labels: <?= json_encode(array_column($evolution, 'jour'), JSON_UNESCAPED_UNICODE) ?>,
    values: <?= json_encode(array_map(fn($e) => (float)$e['note_finale'], $evolution)) ?>,
    label: 'Note',
    format: 'score',
    max: 20,
    color: '#326ea9',
    aria: 'Évolution de vos notes publiées sur 20'
  });

  EduCharts.doughnut('chartScolarite', {
    labels: ['Payé', 'Reste à payer'],
    values: [<?= (float)$montant_paye ?>, <?= (float)$solde ?>],
    colors: ['#15803d', '#e3e8ef'],
    format: 'money',
    aria: 'Part de la scolarité déjà réglée',
    empty: 'Aucun frais de scolarité enregistré',
    center: { value: '<?= $pct_paye ?>%', label: 'réglé' }
  });
});
</script>
</body>
</html>
