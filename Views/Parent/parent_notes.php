<?php
/**
 * EduConnect — parent_notes.php — Version dynamique
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/Config/session_check.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/Config/db_connect.php';
session_role_check('parent');
$user = current_user();
$u_nom    = h($user['nom']);
$u_prenom = h($user['prenom']);
$u_full   = h($user['prenom'] . ' ' . $user['nom']);
$u_init   = h($user['initiales']);
$u_role   = 'Parent / Tuteur';
$current_page = basename(__FILE__);

// ═══════════════════════════════════════════════════════════
// DONNÉES DYNAMIQUES — Notes Enfant (Parent)
// ═══════════════════════════════════════════════════════════
$uid = (int)$user["id"];
// Enfants rattachés au parent, puis enfant sélectionné (?enfant=ID, validé contre la liste)
$enfants_n = db_query($conn,
    "SELECT e.id AS etu_id, e.credits_obtenus, e.credits_requis,
            u.prenom, u.nom, u.numero_ucao, c.code AS classe_code
     FROM etudiants e JOIN users u ON u.id=e.user_id JOIN classes c ON c.id=e.classe_id
     WHERE e.parent_id=? ORDER BY u.prenom, u.nom",
    "i",[$uid]
);
$enfant_sel_id = (int)($_GET["enfant"] ?? 0);
if (!in_array($enfant_sel_id, array_map(fn($e) => (int)$e["etu_id"], $enfants_n), true)) {
    $enfant_sel_id = $enfants_n ? (int)$enfants_n[0]["etu_id"] : 0;
}
$enfant_n = null;
foreach ($enfants_n as $e_n) {
    if ((int)$e_n["etu_id"] === $enfant_sel_id) { $enfant_n = $e_n; break; }
}
$enfant_nom   = $enfant_n ? h($enfant_n["prenom"]." ".$enfant_n["nom"]) : "Enfant non lié";
$enfant_init  = $enfant_n ? h(substr($enfant_n["prenom"],0,1).substr($enfant_n["nom"],0,1)) : "?";
$enfant_class = $enfant_n ? h($enfant_n["classe_code"]) : "";
$etu_id_n     = (int)($enfant_n["etu_id"] ?? 0);

$semestre_sel = (int)($_GET["sem"] ?? 1);
$notes_enfant_n = db_query($conn,
    "SELECT m.nom AS module_nom, m.type_eval, m.credits, m.coefficient,
            n.note_devoir, n.note_examen, n.note_finale, n.semestre,
            CASE WHEN n.note_finale>=16 THEN 'TB' WHEN n.note_finale>=14 THEN 'B'
                 WHEN n.note_finale>=12 THEN 'AB' WHEN n.note_finale>=10 THEN 'P'
                 WHEN n.note_finale IS NOT NULL THEN 'F' ELSE '—' END AS mention,
            COALESCE(CONCAT(u.prenom,' ',u.nom),'—') AS prof_nom
     FROM notes n
     JOIN modules m ON m.id=n.module_id
     LEFT JOIN module_enseignants me ON me.module_id=m.id AND me.est_principal=1
     LEFT JOIN users u ON u.id=me.enseignant_id
     WHERE n.etudiant_id=? AND n.publie=1 AND n.annee_acad='2025-2026'
     ORDER BY m.nom",
    "i",[$etu_id_n]
);

$sp=0;$sc=0;
foreach($notes_enfant_n as $r){ if($r["note_finale"]!==null){ $sp+=(float)$r["note_finale"]*(int)$r["coefficient"]; $sc+=(int)$r["coefficient"]; } }
$moy_sem_n = $sc>0?round($sp/$sc,2):null;

// Bulletins disponibles
$bulletins_enfant = db_query($conn,
    "SELECT semestre, annee_acad, moyenne_sem, rang, mention, pdf_url, genere_le
     FROM bulletins WHERE etudiant_id=? ORDER BY annee_acad DESC, semestre DESC",
    "i",[$etu_id_n]
);

// Rang enfant dans sa classe
$rang_n = (int)(db_row($conn,
    "SELECT COUNT(*)+1 AS rang FROM (
        SELECT e2.id, AVG(n2.note_finale) AS moy
        FROM etudiants e2 JOIN notes n2 ON n2.etudiant_id=e2.id
        WHERE e2.classe_id=(SELECT classe_id FROM etudiants WHERE id=?) AND n2.publie=1
        GROUP BY e2.id
        HAVING moy > COALESCE((SELECT AVG(n3.note_finale) FROM notes n3 WHERE n3.etudiant_id=? AND n3.publie=1),0)
    ) t",
    "ii",[$etu_id_n,$etu_id_n])["rang"] ?? 0);



// Moyenne de la classe par module (même classe, même semestre, notes publiées)
$moy_classes_n = db_query($conn,
    "SELECT m.nom AS module_nom, n.semestre, ROUND(AVG(n.note_finale),2) AS moy
     FROM notes n
     JOIN modules m ON m.id=n.module_id
     JOIN etudiants e2 ON e2.id=n.etudiant_id
     WHERE e2.classe_id=(SELECT classe_id FROM etudiants WHERE id=?)
       AND n.publie=1 AND n.note_finale IS NOT NULL
     GROUP BY m.id, m.nom, n.semestre",
    "i",[$etu_id_n]
);
$moy_classe_map = [];
foreach($moy_classes_n as $mc){
    $moy_classe_map[$mc["module_nom"] . "|" . (int)$mc["semestre"]] = round((float)$mc["moy"],2);
}

// Données exposées au JS — uniquement des valeurs lues en base
$grades_js = [1=>[], 2=>[]];
foreach ($notes_enfant_n as $ng) {
    if ($ng["note_finale"] === null) continue;
    $grades_js[(int)$ng["semestre"]][] = [
        "matiere"  => (string)$ng["module_nom"],
        "prof"     => (string)$ng["prof_nom"],
        "coeff"    => (int)$ng["coefficient"],
        "note"     => round((float)$ng["note_finale"],2),
        "classAvg" => $moy_classe_map[$ng["module_nom"] . "|" . (int)$ng["semestre"]] ?? null,
    ];
}
$enfant_classe_code_n = $enfant_n ? (string)$enfant_n["classe_code"] : "—";
$children_js = [
    "enfant" => [
        "name"    => $enfant_n ? $enfant_n["prenom"]." ".$enfant_n["nom"] : "Étudiant",
        "numero"  => $enfant_n ? (string)$enfant_n["numero_ucao"] : "—",
        "classe"  => $enfant_classe_code_n,
        "rang"    => $rang_n > 0 ? "#".$rang_n : "—",
        "credits" => (int)($enfant_n["credits_obtenus"] ?? 0)."/".(int)($enfant_n["credits_requis"] ?? 30),
        "grades"  => $grades_js,
    ],
];
$bulletins_js = array_map(fn($b) => [
    "sem"     => (int)$b["semestre"],
    "annee"   => (string)$b["annee_acad"],
    "moy"     => $b["moyenne_sem"] !== null ? round((float)$b["moyenne_sem"],2) : null,
    "rang"    => $b["rang"] !== null ? (int)$b["rang"] : null,
    "mention" => $b["mention"] !== null ? (string)$b["mention"] : null,
    "date"    => $b["genere_le"] ? date("d/m/Y", strtotime($b["genere_le"])) : null,
    "pdf"     => $b["pdf_url"] ? (string)$b["pdf_url"] : null,
], $bulletins_enfant);

// Enseignants du semestre, pour le sélecteur du formulaire de message
$profs_n = array_values(array_unique(array_filter(
    array_map(fn($n) => (string)$n["prof_nom"], $notes_enfant_n),
    fn($p) => $p !== "" && $p !== "—"
)));
sort($profs_n);

$u_numero = h($user['numero_ucao']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EduConnect — Notes & Bulletins Parent</title>
<link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@300;400;500;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
<style>
:root{
  --blue:#326ea9;--blue-dk:#1f4a7a;--blue-lt:#4a90c4;--blue-xlt:#e8f0f8;--blue-dim:rgba(50,110,169,.12);
  --red:#C41E3A;--red-lt:#e85b72;--red-dim:rgba(196,30,58,.12);
  --green:#27ae60;--green-lt:#2ecc71;--green-dim:rgba(39,174,96,.12);
  --orange:#e67e22;--orange-dim:rgba(230,126,34,.12);
  --gold:#D4A843;--gold-dim:rgba(212,168,67,.12);
  --purple:#7C3AED;--purple-dim:rgba(124,58,237,.12);
  --teal:#0891b2;--teal-dim:rgba(8,145,178,.12);
  --accent:#e67e22;--accent-lt:#f39c12;--accent-dim:rgba(230,126,34,.12);
  --bg:#f0f2f5;--surface:#fff;--surface2:#f8f9fb;
  --border:#e0e3e8;--border2:#d1d5db;
  --txt:#1a1a2e;--txt2:#4b5563;--txt3:#9ca3af;
  --sidebar-w:240px;--topbar-h:56px;
  --r:8px;--r-sm:5px;
  --sh:0 1px 4px rgba(0,0,0,.08);--sh-md:0 4px 16px rgba(0,0,0,.12);--sh-lg:0 8px 32px rgba(0,0,0,.15);
  --tr:.2s ease;
}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
html{font-size:14px}
body{font-family:'Ubuntu',sans-serif;background:var(--bg);color:var(--txt);min-height:100vh;display:flex}
button{font-family:'Ubuntu',sans-serif;cursor:pointer;border:none;background:none}
a{text-decoration:none;color:inherit}
::-webkit-scrollbar{width:4px;height:4px}
::-webkit-scrollbar-thumb{background:var(--border2);border-radius:10px}
@keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}

/* ═══ SIDEBAR ═══ */
.sidebar{width:var(--sidebar-w);background:var(--blue-dk);position:fixed;height:100vh;z-index:400;display:flex;flex-direction:column;transition:width var(--tr);overflow:hidden}
.sidebar.collapsed{width:54px}
.logo-zone{height:var(--topbar-h);padding:0 14px;display:flex;align-items:center;gap:10px;border-bottom:1px solid rgba(255,255,255,.1);flex-shrink:0}
.logo-mark{width:32px;height:32px;border-radius:7px;background:linear-gradient(135deg,var(--accent),var(--accent-lt));display:flex;align-items:center;justify-content:center;font-size:17px;color:#fff;flex-shrink:0;box-shadow:0 2px 8px rgba(230,126,34,.5)}
.logo-text{font-size:15px;font-weight:700;color:#fff;white-space:nowrap;overflow:hidden}
.logo-text span{color:#fbd38d}
.sidebar-toggle{margin-left:auto;width:24px;height:24px;border-radius:5px;display:flex;align-items:center;justify-content:center;color:rgba(255,255,255,.5);font-size:14px;transition:all var(--tr);flex-shrink:0;cursor:pointer}
.sidebar-toggle:hover{background:rgba(255,255,255,.1);color:#fff}
.sidebar-search{padding:10px 10px 6px;flex-shrink:0;overflow:hidden;transition:all var(--tr)}
.sidebar.collapsed .sidebar-search{opacity:0;height:0;padding:0}
.search-field{display:flex;align-items:center;gap:6px;padding:6px 10px;border-radius:var(--r-sm);background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12)}
.search-field i{color:rgba(255,255,255,.5);font-size:13px;flex-shrink:0}
.search-field input{background:none;border:none;outline:none;color:#fff;font-family:'Ubuntu',sans-serif;font-size:12px;width:100%}
.search-field input::placeholder{color:rgba(255,255,255,.4)}
.nav-body{flex:1;padding:4px 6px;overflow-y:auto;scrollbar-width:none}
.nav-body::-webkit-scrollbar{display:none}
.nav-section{font-size:9px;font-weight:700;letter-spacing:1.8px;text-transform:uppercase;color:rgba(255,255,255,.3);padding:10px 8px 4px;white-space:nowrap;overflow:hidden;transition:opacity var(--tr)}
.sidebar.collapsed .nav-section{opacity:0}
.nav-item{display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);color:rgba(255,255,255,.6);font-size:12.5px;font-weight:500;transition:all var(--tr);margin-bottom:1px;width:100%;text-align:left;cursor:pointer;white-space:nowrap;position:relative;overflow:hidden}
.nav-item:hover{background:rgba(255,255,255,.08);color:#fff}
.nav-item.active{background:rgba(255,255,255,.15);color:#fff;font-weight:600}
.nav-item.active::before{content:'';position:absolute;left:0;top:20%;bottom:20%;width:3px;background:var(--accent-lt);border-radius:0 2px 2px 0}
.nav-item i{font-size:16px;flex-shrink:0;opacity:.8;min-width:16px}
.nav-item.active i{opacity:1;color:var(--accent-lt)}
.nav-item-txt{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;transition:opacity var(--tr)}
.sidebar.collapsed .nav-item-txt{opacity:0;width:0}
.nav-badge{background:var(--red);font-size:9px;font-weight:700;padding:1px 6px;border-radius:20px;color:#fff;flex-shrink:0}
.sidebar-foot{padding:8px;border-top:1px solid rgba(255,255,255,.1);flex-shrink:0}
.user-chip{display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);background:rgba(255,255,255,.07);cursor:pointer;transition:background var(--tr);overflow:hidden}
.user-chip:hover{background:rgba(255,255,255,.12)}
.user-ava{width:30px;height:30px;border-radius:7px;background:linear-gradient(135deg,var(--accent),var(--blue-dk));display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;color:#fff;flex-shrink:0}
.user-info{overflow:hidden;transition:opacity var(--tr)}
.sidebar.collapsed .user-info{opacity:0;width:0}
.user-name{font-size:12px;font-weight:600;color:#fff;white-space:nowrap}
.user-role{font-size:10px;color:rgba(255,255,255,.4);white-space:nowrap}

/* ═══ MAIN / TOPBAR ═══ */
.main{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh;transition:margin-left var(--tr)}
.main.expanded{margin-left:54px}
.topbar{height:var(--topbar-h);background:var(--surface);border-bottom:1px solid var(--border);display:flex;align-items:center;padding:0 20px;gap:12px;position:sticky;top:0;z-index:200;box-shadow:var(--sh)}
.topbar-brand{font-size:13px;font-weight:600;color:var(--blue);display:flex;align-items:center;gap:6px}
.topbar-brand i{font-size:16px}
.tab-nav{display:flex;align-items:center;gap:1px;background:var(--surface2);border:1px solid var(--border);border-radius:var(--r-sm);padding:3px;overflow-x:auto;scrollbar-width:none}
.tab-nav::-webkit-scrollbar{display:none}
.tab-btn{display:flex;align-items:center;gap:5px;padding:5px 12px;border-radius:4px;font-size:12px;font-weight:500;color:var(--txt2);white-space:nowrap;transition:all var(--tr);cursor:pointer}
.tab-btn:hover{background:var(--surface);color:var(--txt)}
.tab-btn.active{background:var(--blue);color:#fff;box-shadow:0 1px 4px rgba(50,110,169,.3)}
.tab-btn i{font-size:13px}
.topbar-right{margin-left:auto;display:flex;align-items:center;gap:8px}
.tb-icon{width:32px;height:32px;border-radius:var(--r-sm);background:var(--surface2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;color:var(--txt2);font-size:15px;transition:all var(--tr);cursor:pointer;position:relative}
.tb-icon:hover{background:var(--blue-xlt);border-color:var(--blue);color:var(--blue)}
.tb-notif{position:absolute;top:5px;right:5px;width:6px;height:6px;background:var(--red);border-radius:50%;border:1.5px solid var(--surface)}
.tb-user{display:flex;align-items:center;gap:7px;padding:4px 10px 4px 4px;border-radius:var(--r-sm);background:var(--surface2);border:1px solid var(--border);cursor:pointer;transition:all var(--tr)}
.tb-user:hover{border-color:var(--blue);background:var(--blue-xlt)}
.tb-ava{width:28px;height:28px;border-radius:6px;background:linear-gradient(135deg,var(--accent),var(--blue-dk));display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;color:#fff;flex-shrink:0}
.tb-uname{font-size:12px;font-weight:600;color:var(--txt)}
.tb-uentity{font-size:10px;color:var(--txt3)}

/* ═══ CONTENT ═══ */
.content{padding:18px 20px;flex:1;overflow-y:auto}
.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;animation:fadeUp .3s ease}
.page-title{display:flex;align-items:center;gap:10px}
.page-title h1{font-size:22px;font-weight:700;color:var(--txt)}
.page-title i{font-size:26px;color:var(--blue)}
.page-title p{font-size:12px;color:var(--txt3);margin-top:2px}
.page-actions{display:flex;gap:8px}

/* ═══ CHILD SELECTOR ═══ */
.child-bar{display:flex;align-items:center;gap:10px;background:var(--surface);border:1px solid var(--border);border-radius:var(--r);padding:12px 16px;margin-bottom:16px;box-shadow:var(--sh);animation:fadeUp .3s ease;flex-wrap:wrap}
.child-bar-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--txt3);flex-shrink:0}
.child-chips{display:flex;gap:6px;flex-wrap:wrap;flex:1}
.child-chip{display:flex;align-items:center;gap:7px;padding:6px 14px;border-radius:20px;border:1.5px solid var(--border2);background:var(--surface2);cursor:pointer;transition:all var(--tr);font-size:12px;font-weight:500;color:var(--txt2)}
.child-chip:hover{border-color:var(--accent);color:var(--accent)}
.child-chip.active{border-color:var(--accent);background:var(--accent-dim);color:var(--accent);font-weight:700}
.child-chip-ava{width:22px;height:22px;border-radius:5px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:9px;color:#fff;flex-shrink:0}

/* ═══ STATS ═══ */
.stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:16px;animation:fadeUp .3s ease}
.stat-box{border-radius:var(--r);padding:16px 18px;color:#fff;position:relative;overflow:hidden}
.stat-box::after{content:'';position:absolute;bottom:-20px;right:-20px;width:80px;height:80px;border-radius:50%;background:rgba(255,255,255,.08)}
.stat-val{font-size:26px;font-weight:700;line-height:1;margin-bottom:4px}
.stat-lbl{font-size:11px;opacity:.88}
.stat-icon{position:absolute;top:14px;right:16px;font-size:22px;opacity:.35}
.sb-blue  {background:linear-gradient(135deg,var(--blue),var(--blue-lt))}
.sb-green {background:linear-gradient(135deg,var(--green),var(--green-lt))}
.sb-orange{background:linear-gradient(135deg,var(--accent),var(--accent-lt))}
.sb-purple{background:linear-gradient(135deg,var(--purple),#9b59b6)}

/* ═══ SEMESTER TABS ═══ */
.sem-tabs{display:flex;gap:6px;margin-bottom:14px}
.sem-tab{padding:6px 16px;border-radius:20px;border:1.5px solid var(--border2);background:var(--surface2);font-size:12px;font-weight:600;color:var(--txt3);cursor:pointer;transition:all var(--tr)}
.sem-tab:hover{border-color:var(--blue);color:var(--blue)}
.sem-tab.active{border-color:var(--blue);background:var(--blue);color:#fff}

/* ═══ GRADE TABLE ═══ */
.table-container{background:var(--surface);border-radius:var(--r);border:1px solid var(--border);box-shadow:var(--sh);overflow:hidden;animation:fadeUp .3s ease;margin-bottom:16px}
.table-head{padding:14px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
.table-head-title{font-size:13px;font-weight:600;color:var(--txt);display:flex;align-items:center;gap:7px}
.table-head-title i{color:var(--blue);font-size:14px}
.table{width:100%;border-collapse:collapse}
.table th{padding:11px 14px;text-align:left;font-size:11px;font-weight:700;color:var(--txt3);text-transform:uppercase;letter-spacing:.5px;border-bottom:2px solid var(--border);background:var(--surface2)}
.table td{padding:11px 14px;border-bottom:1px solid var(--border);font-size:12px;color:var(--txt2)}
.table tr:hover{background:var(--surface2)}
.table tr:last-child td{border:none}

/* Grade bar inside table */
.grade-bar-wrap{display:flex;align-items:center;gap:8px}
.grade-bar{width:80px;height:6px;background:var(--border);border-radius:10px;overflow:hidden;flex-shrink:0}
.grade-bar-fill{height:100%;border-radius:10px}

/* ═══ CHART CARDS ═══ */
.two-col{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px}
.card{background:var(--surface);border-radius:var(--r);border:1px solid var(--border);box-shadow:var(--sh);padding:16px;animation:fadeUp .3s ease}
.card-title{font-size:12px;font-weight:600;color:var(--txt3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:14px;display:flex;align-items:center;gap:6px}
.card-title i{color:var(--blue);font-size:14px}
.chart-container{position:relative;height:220px}

/* ═══ MENTION BADGE ═══ */
.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:4px;font-size:10px;font-weight:600}
.badge-blue{background:var(--blue-dim);color:var(--blue)}
.badge-green{background:var(--green-dim);color:var(--green)}
.badge-orange{background:var(--orange-dim);color:var(--accent)}
.badge-red{background:var(--red-dim);color:var(--red)}
.badge-purple{background:var(--purple-dim);color:var(--purple)}

/* ═══ BULLETIN CARD ═══ */
.bulletin-card{
  background:var(--surface);border:1px solid var(--border);border-radius:var(--r);
  box-shadow:var(--sh);padding:20px;margin-bottom:16px;
  display:flex;align-items:center;gap:20px;animation:fadeUp .3s ease;
  border-left:5px solid var(--blue);
}
.bc-icon{font-size:38px;flex-shrink:0}
.bc-body{flex:1}
.bc-title{font-size:15px;font-weight:700;color:var(--txt);margin-bottom:3px}
.bc-sub{font-size:11px;color:var(--txt3)}
.bc-stats{display:flex;gap:16px;margin-top:10px}
.bc-stat{text-align:center;padding:8px 14px;background:var(--surface2);border-radius:var(--r-sm);border:1px solid var(--border)}
.bc-stat-val{font-size:18px;font-weight:700;color:var(--blue)}
.bc-stat-lbl{font-size:9px;color:var(--txt3);text-transform:uppercase;letter-spacing:.4px}
.bc-actions{display:flex;flex-direction:column;gap:6px;flex-shrink:0}

/* ═══ ALERT ═══ */
.alert-card{
  border-radius:var(--r-sm);padding:12px 14px;margin-bottom:14px;
  display:flex;align-items:flex-start;gap:10px;font-size:12px;line-height:1.6;
}
.alert-info{background:var(--blue-xlt);border-left:3px solid var(--blue);color:var(--blue-dk)}
.alert-warn{background:var(--orange-dim);border-left:3px solid var(--accent);color:#7a3f00}
.alert-success{background:var(--green-dim);border-left:3px solid var(--green);color:#1a5e34}

/* ═══ BUTTONS ═══ */
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:var(--r-sm);font-size:12px;font-weight:600;cursor:pointer;transition:all var(--tr);border:1px solid transparent}
.btn-primary{background:var(--blue);color:#fff;border-color:var(--blue)}
.btn-primary:hover{background:var(--blue-lt);box-shadow:var(--sh-md);transform:translateY(-1px)}
.btn-secondary{background:var(--surface2);color:var(--txt2);border-color:var(--border)}
.btn-secondary:hover{background:var(--surface);border-color:var(--blue);color:var(--blue)}
.btn-sm{padding:5px 10px;font-size:11px}
.btn i{font-size:13px}

/* ═══ MODAL ═══ */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:500;display:none;align-items:center;justify-content:center;padding:16px}
.modal-overlay.open{display:flex}
.modal-box{background:var(--surface);border-radius:12px;padding:24px;width:500px;max-width:100%;box-shadow:var(--sh-lg);animation:fadeUp .25s ease;max-height:90vh;overflow-y:auto}
.modal-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;padding-bottom:12px;border-bottom:1px solid var(--border)}
.modal-title{font-size:15px;font-weight:700;color:var(--txt);display:flex;align-items:center;gap:8px}
.modal-close{width:28px;height:28px;border-radius:5px;background:var(--surface2);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--txt3);font-size:16px;transition:all var(--tr)}
.modal-close:hover{background:var(--red-dim);color:var(--red)}

/* ═══ TOAST ═══ */
.toast{position:fixed;bottom:20px;right:20px;background:var(--txt);color:#fff;padding:12px 16px;border-radius:var(--r-sm);font-size:12.5px;font-weight:500;box-shadow:var(--sh-lg);transform:translateY(100px);opacity:0;transition:all .3s cubic-bezier(.34,1.56,.64,1);z-index:9999;border-left:3px solid var(--accent);max-width:300px}
.toast.show{transform:translateY(0);opacity:1}

@media(max-width:1100px){.stats-row{grid-template-columns:1fr 1fr}.two-col{grid-template-columns:1fr}}
@media(max-width:768px){.sidebar{transform:translateX(-100%)}.main{margin-left:0}.content{padding:12px 14px}.page-header{flex-direction:column;align-items:flex-start;gap:10px}.bulletin-card{flex-direction:column}.bc-actions{flex-direction:row}}
</style>
<!-- EduConnect UI upgrade (see Assets/css/educonnect-legacy.css) -->
<link rel="stylesheet" href="../../Assets/css/educonnect-legacy.css">
<script>document.documentElement.classList.add('legacy-ui');(function(){var m=location.pathname.match(/Views\/(Etudiant|Parent|Enseignant|Mentor|Admin)\//);if(m){var r={Etudiant:'etudiant',Parent:'parent',Enseignant:'enseignant',Mentor:'mentor',Admin:'admin'}[m[1]];document.documentElement.classList.add('role-'+r);}})();</script>
</head>
<body>

<!-- ════════════════ SIDEBAR ════════════════ -->
<aside class="sidebar" id="sidebar">
  <div class="logo-zone">
    <div class="logo-mark"><i class="bi bi-people-fill"></i></div>
    <div>
      <div class="logo-text">Edu<span>Connect</span></div>
      <div style="font-size:9px;color:rgba(255,255,255,.4)">Parent / Tuteur</div>
    </div>
    <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-chevron-left"></i></button>
  </div>

  <div class="sidebar-search">
    <div class="search-field">
      <i class="bi bi-search"></i>
      <input type="text" placeholder="Rechercher…">
    </div>
  </div>

  <div class="nav-body">
    <div class="nav-section">Tableau de bord</div>
    <a href="parent_dashboard.php" class="nav-item">
      <i class="bi bi-speedometer2"></i>
      <span class="nav-item-txt">Vue d'ensemble</span>
    </a>

    <div class="nav-section">Mon enfant</div>
    <a href="parent_notes.php" class="nav-item active">
      <i class="bi bi-graph-up"></i>
      <span class="nav-item-txt">Notes & Bulletins</span>
    </a>
    <a href="parent_paiements.php" class="nav-item">
      <i class="bi bi-credit-card"></i>
      <span class="nav-item-txt">Paiements</span>
      <span class="nav-badge"><?= $nb_notifs > 0 ? $nb_notifs : "" ?></span>
    </a>
    <a href="parent_emploi_du_temps.php" class="nav-item">
      <i class="bi bi-calendar3"></i>
      <span class="nav-item-txt">Emploi du temps</span>
    </a>
    <a href="parent_messages.php" class="nav-item">
      <i class="bi bi-chat-dots"></i>
      <span class="nav-item-txt">Messages</span>
      <span class="nav-badge"><?= $nb_notifs > 0 ? $nb_notifs : "" ?></span>
    </a>
    <a href="parent_notifications.php" class="nav-item">
      <i class="bi bi-bell"></i>
      <span class="nav-item-txt">Notifications</span>
      <span class="nav-badge"><?= $nb_notifs > 0 ? $nb_notifs : "" ?></span>
    </a>

    <div class="nav-section">Compte</div>
    <a href="parent_profil.php" class="nav-item">
      <i class="bi bi-person-circle"></i>
      <span class="nav-item-txt">Mon Profil</span>
    </a>
  </div>

  <div class="sidebar-foot">
    <div class="user-chip" onclick="location.href='/Views/Parent/parent_profil.php'" style="cursor:pointer">
      <div class="user-ava"><?= $u_init ?></div>
      <div class="user-info">
        <div class="user-name"><?= $u_full ?></div>
        <div class="user-role">Parent · 2 enfants</div>
      </div>
    </div>
  
  <a href="/Views/Parent/parent_parametres.php" style="display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);color:rgba(255,255,255,.5);font-size:12px;text-decoration:none;transition:all .2s;overflow:hidden" onmouseover="this.style.background='rgba(255,255,255,.08)';this.style.color='#fff'" onmouseout="this.style.background='';this.style.color='rgba(255,255,255,.5)'"><i class="bi bi-gear" style="font-size:16px;flex-shrink:0;min-width:16px"></i><span class="nav-item-txt">Paramètres</span></a>
  <a href="/Views/Auth/logout.php" style="display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);color:rgba(255,255,255,.5);font-size:12px;font-weight:500;text-decoration:none;transition:all .2s;margin-top:4px;overflow:hidden" onmouseover="this.style.background='rgba(196,30,58,.2)';this.style.color='#e85b72'" onmouseout="this.style.background='';this.style.color='rgba(255,255,255,.5)'">
    <i class="bi bi-box-arrow-left" style="font-size:16px;flex-shrink:0;min-width:16px"></i>
    <span class="nav-item-txt">Déconnexion</span>
  </a></div>
</aside>

<!-- ════════════════ MAIN ════════════════ -->
<main class="main" id="main">

  <!-- TOPBAR -->
  <div class="topbar">
    <div class="topbar-brand">
      <i class="bi bi-graph-up"></i>
      Notes & Bulletins
    </div>

    <div class="tab-nav">
      <button class="tab-btn active" onclick="setView('current',this)">
        <i class="bi bi-calendar-check"></i>Semestre actuel
      </button>
      <button class="tab-btn" onclick="setView('compare',this)">
        <i class="bi bi-bar-chart-line"></i>Comparatif
      </button>
      <button class="tab-btn" onclick="setView('bulletins',this)">
        <i class="bi bi-file-earmark-text"></i>Bulletins
      </button>
    </div>

    <div class="topbar-right">
      <button class="tb-icon" title="Télécharger le bulletin" onclick="toast('📥 Bulletin téléchargé')">
        <i class="bi bi-download"></i>
      </button>
      <button class="tb-icon" title="Notifications">
        <i class="bi bi-bell-fill"></i>
        <span class="tb-notif"></span>
      </button>
      <div class="tb-user">
        <div class="tb-ava"><?= $u_init ?></div>
        <div>
          <div class="tb-uname"><?= $u_full ?></div>
          <div class="tb-uentity"><?= $u_role ?></div>
        </div>
      </div>
    </div>
  </div>

  <!-- CONTENT -->
  <div class="content">

    <!-- Page header -->
    <div class="page-header">
      <div class="page-title">
        <i class="bi bi-graph-up"></i>
        <div>
          <h1>Notes & Bulletins</h1>
          <p>Résultats académiques — Année 2025–2026</p>
        </div>
      </div>
      <div class="page-actions">
        <button class="btn btn-secondary" onclick="openMessageModal()">
          <i class="bi bi-chat-dots"></i>Contacter un prof
        </button>
        <button class="btn btn-primary" onclick="toast('📥 Bulletin PDF téléchargé')">
          <i class="bi bi-download"></i>Télécharger bulletin
        </button>
      </div>
    </div>

    <!-- Child selector -->
    <div class="child-bar">
      <span class="child-bar-label"><i class="bi bi-people-fill" style="color:var(--accent)"></i> Enfant :</span>
      <div class="child-chips">
        <?php if (!$enfants_n): ?>
        <span style="font-size:12px;color:var(--txt2)">Aucun enfant rattaché à votre compte.</span>
        <?php endif; foreach ($enfants_n as $e_n):
            $e_nom = $e_n["prenom"] . " " . $e_n["nom"];
            $e_ini = strtoupper(substr($e_n["prenom"], 0, 1) . substr($e_n["nom"], 0, 1));
        ?>
        <a class="child-chip <?= (int)$e_n["etu_id"] === $enfant_sel_id ? 'active' : '' ?>"
           href="?enfant=<?= (int)$e_n["etu_id"] ?>">
          <div class="child-chip-ava" style="background:var(--blue)"><?= h($e_ini) ?></div>
          <?= h($e_nom) ?>
          <span class="badge badge-blue" style="font-size:9px"><?= h($e_n["classe_code"]) ?></span>
        </a>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- ── CURRENT SEMESTER VIEW ── -->
    <div id="view-current">

      <!-- Alert for low grade -->
      <div class="alert-card alert-warn" id="alertLow" style="display:none">
        <i class="bi bi-exclamation-triangle-fill" style="font-size:16px;flex-shrink:0;margin-top:1px"></i>
        <div id="alertText"></div>
      </div>
      <div class="alert-card alert-success" id="alertGood" style="display:none">
        <i class="bi bi-trophy-fill" style="font-size:16px;flex-shrink:0;margin-top:1px"></i>
        <div id="alertGoodText"></div>
      </div>

      <!-- Stats -->
      <div class="stats-row">
        <div class="stat-box sb-blue">
          <i class="bi bi-graph-up-arrow stat-icon"></i>
          <div class="stat-val" id="statMoy">–</div>
          <div class="stat-lbl">Moyenne générale /20</div>
        </div>
        <div class="stat-box sb-green">
          <i class="bi bi-award-fill stat-icon"></i>
          <div class="stat-val" id="statMention">–</div>
          <div class="stat-lbl">Mention</div>
        </div>
        <div class="stat-box sb-orange">
          <i class="bi bi-journals stat-icon"></i>
          <div class="stat-val" id="statCredits">–</div>
          <div class="stat-lbl">Crédits validés</div>
        </div>
        <div class="stat-box sb-purple">
          <i class="bi bi-people stat-icon"></i>
          <div class="stat-val" id="statRang">–</div>
          <div class="stat-lbl">Rang dans la classe</div>
        </div>
      </div>

      <!-- Semester selector -->
      <div class="sem-tabs">
        <button class="sem-tab active" onclick="setSemester(1,this)">Semestre 1</button>
        <button class="sem-tab" onclick="setSemester(2,this)">Semestre 2</button>
      </div>

      <!-- Grade table -->
      <div class="table-container">
        <div class="table-head">
          <div class="table-head-title"><i class="bi bi-list-ol"></i>Notes par matière — <span id="tableChildName"><?= $enfant_nom ?? "—" ?></span> · S<span id="tableSemNum">1</span></div>
          <div style="display:flex;gap:6px;align-items:center">
            <span style="font-size:11px;color:var(--txt3)">Trier par :</span>
            <select style="padding:4px 8px;border:1px solid var(--border);border-radius:var(--r-sm);font-family:'Ubuntu',sans-serif;font-size:11px" onchange="sortGrades(this.value)">
              <option value="note-desc">Note ↓</option>
              <option value="note-asc">Note ↑</option>
              <option value="coeff">Coefficient</option>
            </select>
          </div>
        </div>
        <table class="table">
          <thead>
            <tr><th>Matière</th><th>Enseignant</th><th>Coeff.</th><th>Note /20</th><th>Performance</th><th>Mention</th></tr>
          </thead>
          <tbody id="gradesBody"></tbody>
          <tfoot id="gradesFoot"></tfoot>
        </table>
      </div>

      <!-- Charts -->
      <div class="two-col">
        <div class="card">
          <div class="card-title"><i class="bi bi-radar"></i>Radar de performance</div>
          <div class="chart-container"><canvas id="radarChart"></canvas></div>
        </div>
        <div class="card">
          <div class="card-title"><i class="bi bi-bar-chart-fill"></i>Notes par matière</div>
          <div class="chart-container"><canvas id="barChart"></canvas></div>
        </div>
      </div>

    </div><!-- /current -->

    <!-- ── COMPARE VIEW ── -->
    <div id="view-compare" style="display:none">
      <div class="alert-card alert-info">
        <i class="bi bi-info-circle-fill" style="font-size:16px;flex-shrink:0;margin-top:1px"></i>
        <div>Ce graphique compare la progression de <strong id="compareChildName"><?= $enfant_nom ?? "—" ?></strong> sur les deux semestres de l'année et par rapport à la moyenne de la classe.</div>
      </div>
      <div class="card" style="margin-bottom:16px">
        <div class="card-title"><i class="bi bi-graph-up"></i>Progression — S1 vs S2</div>
        <div class="chart-container" style="height:280px"><canvas id="compareChart"></canvas></div>
      </div>
      <div class="two-col">
        <div class="card">
          <div class="card-title"><i class="bi bi-trophy"></i>Points forts</div>
          <div id="strengths" style="display:flex;flex-direction:column;gap:8px"></div>
        </div>
        <div class="card">
          <div class="card-title"><i class="bi bi-arrow-up-circle"></i>Points à améliorer</div>
          <div id="weaknesses" style="display:flex;flex-direction:column;gap:8px"></div>
        </div>
      </div>
    </div><!-- /compare -->

    <!-- ── BULLETINS VIEW ── -->
    <div id="view-bulletins" style="display:none">
      <div id="bulletinsContainer"></div>
    </div><!-- /bulletins -->

  </div><!-- /content -->
</main>

<!-- ══ MESSAGE MODAL ══ -->
<div class="modal-overlay" id="msgModal" onclick="if(event.target===this)closeMsgModal()">
  <div class="modal-box">
    <div class="modal-head">
      <div class="modal-title"><i class="bi bi-chat-dots-fill" style="color:var(--blue)"></i>Contacter un enseignant</div>
      <button class="modal-close" onclick="closeMsgModal()">✕</button>
    </div>
    <div style="margin-bottom:14px">
      <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--txt3);margin-bottom:5px">Enseignant</div>
      <select style="width:100%;padding:9px 12px;border:1.5px solid var(--border2);border-radius:var(--r-sm);font-family:'Ubuntu',sans-serif;font-size:13px" id="msgProf">
        <?php if (!$profs_n): ?>
        <option value="">Aucun enseignant référencé</option>
        <?php else: foreach ($profs_n as $p): ?>
        <option><?= h($p) ?></option>
        <?php endforeach; endif; ?>
      </select>
    </div>
    <div style="margin-bottom:14px">
      <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--txt3);margin-bottom:5px">Objet</div>
      <input style="width:100%;padding:9px 12px;border:1.5px solid var(--border2);border-radius:var(--r-sm);font-family:'Ubuntu',sans-serif;font-size:13px" type="text" placeholder="ex : Question sur les résultats du semestre 1" id="msgSubject">
    </div>
    <div style="margin-bottom:16px">
      <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--txt3);margin-bottom:5px">Message</div>
      <textarea style="width:100%;padding:9px 12px;border:1.5px solid var(--border2);border-radius:var(--r-sm);font-family:'Ubuntu',sans-serif;font-size:13px;min-height:100px;resize:vertical" id="msgBody" placeholder="Écrivez votre message ici…"></textarea>
    </div>
    <div style="display:flex;gap:10px">
      <button class="btn btn-secondary" style="flex:1;justify-content:center" onclick="closeMsgModal()">Annuler</button>
      <button class="btn btn-primary" style="flex:2;justify-content:center" onclick="sendMessage()">
        <i class="bi bi-send-fill"></i>Envoyer le message
      </button>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
/* ════════════════════════════════
   DATA
════════════════════════════════ */
const CHILDREN = <?= json_encode($children_js, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const BULLETINS = <?= json_encode($bulletins_js, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const CURRENT_CHILD = 'enfant';

let currentChild = CURRENT_CHILD;
let currentSem   = <?= (int)$semestre_sel ?>;
let currentSort  = 'note-desc';
let radarRef = null, barRef = null, compareRef = null;

/* ════ INIT ════ */
document.addEventListener('DOMContentLoaded',()=>{
  renderAll();
});

/* ════ SEMESTER ════ */
function setSemester(sem, btn){
  currentSem = sem;
  document.querySelectorAll('.sem-tab').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
  renderGrades();
  updateCharts();
}

/* ════ SORT ════ */
function sortGrades(val){ currentSort = val; renderGrades(); }

/* ════ RENDER ALL ════ */
function renderAll(){
  renderStats();
  renderGrades();
  renderCharts();
  renderCompare();
  renderBulletins();
}

/* ════ STATS ════ */
function renderStats(){
  const c = CHILDREN[currentChild];
  const g = c.grades[currentSem];
  const moy = calcMoy(g);
  document.getElementById('statMoy').textContent     = moy === null ? '—' : moy.toFixed(2);
  document.getElementById('statMention').textContent  = moy === null ? '—' : getMention(moy).label;
  document.getElementById('statCredits').textContent  = c.credits;
  document.getElementById('statRang').textContent     = c.rang;
  document.getElementById('tableChildName').textContent = c.name;
  document.getElementById('compareChildName').textContent = c.name;

  // Alerts
  const lowGrade = g.find(x=>x.note < 10);
  const topGrade = g.find(x=>x.note >= 17);
  document.getElementById('alertLow').style.display  = lowGrade ? '' : 'none';
  document.getElementById('alertGood').style.display = topGrade ? '' : 'none';
  if(lowGrade) document.getElementById('alertText').textContent = `⚠️ ${c.name} a une note insuffisante en ${lowGrade.matiere} (${lowGrade.note}/20). Il est recommandé de prendre contact avec l'enseignant.`;
  if(topGrade) document.getElementById('alertGoodText').textContent = `🏆 Excellent résultat ! ${c.name} obtient ${topGrade.note}/20 en ${topGrade.matiere}. Une candidature au mentorat est possible.`;
}

/* ════ GRADES TABLE ════ */
function renderGrades(){
  const c = CHILDREN[currentChild];
  const g = [...c.grades[currentSem]];
  document.getElementById('tableSemNum').textContent = currentSem;

  if(currentSort === 'note-desc') g.sort((a,b)=>b.note-a.note);
  else if(currentSort === 'note-asc') g.sort((a,b)=>a.note-b.note);
  else g.sort((a,b)=>b.coeff-a.coeff);

  document.getElementById('gradesBody').innerHTML = g.map(r => {
    const color = gradeColor(r.note);
    const pct   = (r.note/20*100).toFixed(0);
    const men   = getMention(r.note);
    return `<tr>
      <td style="font-weight:600;color:var(--txt)">${esc(r.matiere)}</td>
      <td>${esc(r.prof)}</td>
      <td><span class="badge badge-blue">${r.coeff}</span></td>
      <td style="font-size:14px;font-weight:700;color:${color}">${r.note}/20</td>
      <td>
        <div class="grade-bar-wrap">
          <div class="grade-bar"><div class="grade-bar-fill" style="width:${pct}%;background:${color}"></div></div>
          <span style="font-size:10px;color:var(--txt3)">${pct}%</span>
        </div>
      </td>
      <td><span class="badge" style="background:${men.bg};color:${men.col}">${men.label}</span></td>
    </tr>`;
  }).join('');

  // Footer: weighted average
  const moy = calcMoy(c.grades[currentSem]);
  const totalCoeff = c.grades[currentSem].reduce((a,r)=>a+r.coeff,0);
  const men = moy === null ? null : getMention(moy);
  document.getElementById('gradesFoot').innerHTML = `
    <tr style="background:var(--surface2);font-weight:700">
      <td colspan="2" style="padding:10px 14px;font-size:12px;color:var(--txt)">Moyenne pondérée (total coeff. ${totalCoeff})</td>
      <td></td>
      <td style="font-size:15px;font-weight:700;color:var(--blue);padding:10px 14px">${moy === null ? '—' : moy.toFixed(2)}/20</td>
      <td colspan="2" style="padding:10px 14px">${men ? `<span class="badge" style="background:${men.bg};color:${men.col}">${men.label}</span>` : '<span class="badge">Aucune note publiée</span>'}</td>
    </tr>`;
}

/* ════ CHARTS ════ */
function renderCharts(){
  const c = CHILDREN[currentChild];
  const g = c.grades[currentSem];
  const labels  = g.map(r=>r.matiere.split(' ')[0]);
  const data    = g.map(r=>r.note);
  const colors  = data.map(n=>gradeColor(n));

  // Radar
  if(radarRef) radarRef.destroy();
  radarRef = new Chart(document.getElementById('radarChart').getContext('2d'),{
    type:'radar',
    data:{labels,datasets:[
      {label:'Notes',data,backgroundColor:'rgba(50,110,169,.12)',borderColor:'#326ea9',borderWidth:2,pointBackgroundColor:colors,pointRadius:4},
      {label:'Objectif (12)',data:Array(g.length).fill(12),backgroundColor:'transparent',borderColor:'rgba(230,126,34,.4)',borderWidth:1.5,borderDash:[4,4],pointRadius:0}
    ]},
    options:{responsive:true,maintainAspectRatio:false,scales:{r:{min:0,max:20,ticks:{stepSize:5,font:{family:'Ubuntu',size:10}},pointLabels:{font:{family:'Ubuntu',size:10}},grid:{color:'rgba(0,0,0,.06)'},angleLines:{color:'rgba(0,0,0,.06)'}}},plugins:{legend:{display:false}}}
  });

  // Bar
  if(barRef) barRef.destroy();
  barRef = new Chart(document.getElementById('barChart').getContext('2d'),{
    type:'bar',
    data:{labels,datasets:[
      {label:'Note',data,backgroundColor:  colors.map(col=>col.replace(')',',0.7)').replace('rgb','rgba')),borderRadius:5},
      {label:'Moyenne classe',data:g.map(r=>r.classAvg),type:'line',borderColor:'rgba(230,126,34,.7)',backgroundColor:'transparent',borderWidth:2,borderDash:[4,4],pointRadius:0}
    ]},
    options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{font:{family:'Ubuntu',size:10},boxWidth:10}}},scales:{x:{grid:{display:false},ticks:{font:{family:'Ubuntu',size:9}}},y:{min:0,max:20,grid:{color:'rgba(0,0,0,.04)'},ticks:{font:{family:'Ubuntu',size:10},callback:v=>v+'/20'}}}}
  });
}

function updateCharts(){ renderCharts(); }

/* ════ COMPARE ════ */
function renderCompare(){
  const c = CHILDREN[currentChild];
  const g1 = c.grades[1], g2 = c.grades[2];
  const labels = g1.map(r=>r.matiere.split(' ')[0]);

  if(compareRef) compareRef.destroy();
  compareRef = new Chart(document.getElementById('compareChart').getContext('2d'),{
    type:'bar',
    data:{labels,datasets:[
      {label:'Semestre 1',data:g1.map(r=>r.note),backgroundColor:'rgba(50,110,169,.65)',borderRadius:4},
      {label:'Semestre 2',data:g2.map(r=>r.note),backgroundColor:'rgba(39,174,96,.65)',borderRadius:4},
      {label:'Moy. classe',data:g1.map(r=>r.classAvg),type:'line',borderColor:'rgba(230,126,34,.8)',backgroundColor:'transparent',borderWidth:2,borderDash:[4,4],pointRadius:0},
    ]},
    options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{font:{family:'Ubuntu',size:11},boxWidth:10}}},scales:{x:{grid:{display:false},ticks:{font:{family:'Ubuntu',size:9}}},y:{min:0,max:20,grid:{color:'rgba(0,0,0,.04)'},ticks:{font:{family:'Ubuntu',size:10},callback:v=>v+'/20'}}}}
  });

  // Strengths & weaknesses (based on S2)
  const sorted = [...g2].sort((a,b)=>b.note-a.note);
  document.getElementById('strengths').innerHTML = sorted.slice(0,3).map(r=>`
    <div style="display:flex;align-items:center;gap:10px;padding:8px 10px;border:1px solid var(--green-dim);background:var(--green-dim);border-radius:var(--r-sm)">
      <i class="bi bi-check-circle-fill" style="color:var(--green);flex-shrink:0"></i>
      <div><div style="font-size:12px;font-weight:600">${esc(r.matiere)}</div><div style="font-size:10px;color:var(--txt3)">Note : ${r.note}/20</div></div>
    </div>`).join('');
  document.getElementById('weaknesses').innerHTML = sorted.slice(-3).reverse().map(r=>`
    <div style="display:flex;align-items:center;gap:10px;padding:8px 10px;border:1px solid var(--orange-dim);background:var(--orange-dim);border-radius:var(--r-sm)">
      <i class="bi bi-arrow-up-circle-fill" style="color:var(--accent);flex-shrink:0"></i>
      <div><div style="font-size:12px;font-weight:600">${esc(r.matiere)}</div><div style="font-size:10px;color:var(--txt3)">Note : ${r.note}/20 — À renforcer</div></div>
    </div>`).join('');
}

/* ════ BULLETINS ════ */
function renderBulletins(){
  const c = CHILDREN[currentChild];
  const container = document.getElementById('bulletinsContainer');
  if(!BULLETINS.length){
    container.innerHTML = `<div class="bulletin-card" style="opacity:.75;border-left-color:var(--txt3)">
      <div class="bc-icon">📄</div>
      <div class="bc-body">
        <div class="bc-title">Aucun bulletin généré — ${esc(c.classe)}</div>
        <div class="bc-sub">Un bulletin apparaît ici dès qu'il est généré et publié par l'administration.</div>
      </div>
    </div>`;
    return;
  }
  container.innerHTML = BULLETINS.map(b=>{
    const hasPdf = b.pdf && /^(https?:\/\/|\/)/i.test(b.pdf);
    return `
    <div class="bulletin-card">
      <div class="bc-icon">📋</div>
      <div class="bc-body">
        <div class="bc-title">
          Bulletin Semestre ${b.sem} — ${esc(c.name)}
          <span class="badge badge-green"><i class="bi bi-check-circle"></i>Disponible</span>
        </div>
        <div class="bc-sub">${esc(c.classe)} · Année ${esc(b.annee)}${b.date ? ' · Généré le ' + esc(b.date) : ''}</div>
        <div class="bc-stats">
          <div class="bc-stat"><div class="bc-stat-val">${b.moy === null ? '–' : b.moy.toFixed(2)}</div><div class="bc-stat-lbl">Moyenne /20</div></div>
          <div class="bc-stat"><div class="bc-stat-val">${b.mention ? esc(b.mention) : '–'}</div><div class="bc-stat-lbl">Mention</div></div>
          <div class="bc-stat"><div class="bc-stat-val">${b.rang === null ? '–' : '#' + b.rang}</div><div class="bc-stat-lbl">Rang</div></div>
          <div class="bc-stat"><div class="bc-stat-val" style="font-size:11px">${esc(b.pdf ? 'PDF' : '—')}</div><div class="bc-stat-lbl">Document</div></div>
        </div>
      </div>
      <div class="bc-actions">
        ${hasPdf
          ? `<a class="btn btn-primary" href="${esc(b.pdf)}"><i class="bi bi-download"></i>Télécharger PDF</a>`
          : `<button class="btn btn-secondary" disabled style="opacity:.5;cursor:not-allowed"><i class="bi bi-hourglass-split"></i>PDF indisponible</button>`}
      </div>
    </div>`;
  }).join('');
}

/* ════ HELPERS ════ */
function calcMoy(grades){
  if(!grades || !grades.length) return null;
  const totalPoints = grades.reduce((a,r)=>a + r.note * r.coeff, 0);
  const totalCoeff  = grades.reduce((a,r)=>a + r.coeff, 0);
  return totalCoeff > 0 ? totalPoints / totalCoeff : null;
}

function esc(value){
  return String(value ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
}

function gradeColor(n){
  if(n >= 16) return 'var(--green)';
  if(n >= 14) return 'var(--blue)';
  if(n >= 10) return 'var(--accent)';
  return 'var(--red)';
}

function getMention(n){
  if(n >= 16) return { label:'Très Bien', bg:'var(--green-dim)', col:'var(--green)' };
  if(n >= 14) return { label:'Bien',      bg:'var(--blue-dim)',  col:'var(--blue)'  };
  if(n >= 12) return { label:'Assez Bien',bg:'var(--gold-dim)',  col:'var(--gold)'  };
  if(n >= 10) return { label:'Passable',  bg:'var(--orange-dim)',col:'var(--accent)'};
  return              { label:'Insuffisant',bg:'var(--red-dim)', col:'var(--red)'   };
}

/* ════ VIEW SWITCH ════ */
function setView(v, btn){
  document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
  ['current','compare','bulletins'].forEach(id=>{
    document.getElementById('view-'+id).style.display = id===v ? '' : 'none';
  });
  if(v === 'compare') renderCompare();
  if(v === 'bulletins') renderBulletins();
}

/* ════ MESSAGE MODAL ════ */
function openMessageModal(){ document.getElementById('msgModal').classList.add('open'); }
function closeMsgModal(){ document.getElementById('msgModal').classList.remove('open'); }
function sendMessage(){
  const prof    = document.getElementById('msgProf').value;
  const subject = document.getElementById('msgSubject').value.trim();
  if(!subject){ toast('⚠️ Veuillez saisir un objet'); return; }
  closeMsgModal();
  toast('✅ Message envoyé à ' + prof.split('—')[0].trim());
}

/* ════ SIDEBAR / TOAST ════ */
function toggleSidebar(){
  document.getElementById('sidebar').classList.toggle('collapsed');
  document.getElementById('main').classList.toggle('expanded');
}
let toastT;
function toast(msg){
  const el=document.getElementById('toast');
  el.textContent=msg;el.classList.add('show');
  clearTimeout(toastT);toastT=setTimeout(()=>el.classList.remove('show'),3500);
}
</script>
<script src="../../Assets/js/educonnect-legacy.js"></script>
</body>
</html><?php /** EduConnect — Notes & Bulletins Parent/Tuteur */ ?>
