<?php
/**
 * EduConnect — enseignant_notes.php — Version dynamique
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/Config/session_check.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/Config/db_connect.php';
session_role_check('enseignant');
$user = current_user();
$u_nom    = h($user['nom']);
$u_prenom = h($user['prenom']);
$u_full   = h($user['prenom'] . ' ' . $user['nom']);
$u_init   = h($user['initiales']);
$u_role   = 'Enseignant';
$current_page = basename(__FILE__);

// ═══════════════════════════════════════════════════════════
// DONNÉES DYNAMIQUES — Saisie Notes Enseignant
// ═══════════════════════════════════════════════════════════

// Modules enseignés par ce prof
$mes_modules = db_query($conn,
    "SELECT m.id, m.code, m.nom, m.type_eval, m.semestre, m.annee_etude, c.code AS classe_code
     FROM modules m
     JOIN module_enseignants me ON me.module_id = m.id
     JOIN module_classes mc ON mc.module_id = m.id
     JOIN classes c ON c.id = mc.classe_id
     WHERE me.enseignant_id = ?
     ORDER BY m.annee_etude, m.semestre, m.nom",
    "i", [(int)$user["id"]]
);

// Module + classe sélectionnés (via GET ou premier par défaut)
$sel_module_id = (int)($_GET["module"] ?? ($mes_modules[0]["id"] ?? 0));
$sel_classe_id = (int)($_GET["classe"] ?? 0);
$sel_semestre  = (int)($_GET["sem"]    ?? 1);
$annee_acad    = "2025-2026";

// Récupérer les classes liées au module sélectionné
$classes_module = db_query($conn,
    "SELECT c.id, c.code, c.nom FROM classes c
     JOIN module_classes mc ON mc.classe_id = c.id
     WHERE mc.module_id = ? AND mc.annee_acad = ?",
    "is", [$sel_module_id, $annee_acad]
);
if (!$sel_classe_id && count($classes_module) > 0) {
    $sel_classe_id = (int)$classes_module[0]["id"];
}

// Type eval du module sélectionné
$module_info = db_row($conn,
    "SELECT m.nom, m.type_eval, m.code FROM modules m WHERE m.id = ?",
    "i", [$sel_module_id]
);

// Étudiants de la classe + leurs notes
$etudiants_notes = db_query($conn,
    "SELECT u.id AS user_id, u.nom, u.prenom, u.numero_ucao, e.id AS etu_id,
            n.id AS note_id, n.note_devoir, n.note_examen, n.note_finale, n.publie
     FROM etudiants e
     JOIN users u ON u.id = e.user_id
     LEFT JOIN notes n ON n.etudiant_id = e.id
         AND n.module_id = ? AND n.semestre = ? AND n.annee_acad = ?
     WHERE e.classe_id = ?
     ORDER BY u.nom, u.prenom",
    "isis", [$sel_module_id, $sel_semestre, $annee_acad, $sel_classe_id]
);

// Statistiques rapides
$notes_saisies   = count(array_filter($etudiants_notes, fn($e) => $e["note_finale"] !== null));
$notes_en_attente = count($etudiants_notes) - $notes_saisies;
$avg_classe = $notes_saisies > 0
    ? round(array_sum(array_column(array_filter($etudiants_notes, fn($e) => $e["note_finale"] !== null), "note_finale")) / $notes_saisies, 2)
    : 0;

// ── Traitement POST : sauvegarder / publier ────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    if ($action === "sauvegarder" || $action === "publier") {
        $publier = ($action === "publier");
        foreach ($_POST["notes"] ?? [] as $etu_id_post => $note_data) {
            $etu_id_post = (int)$etu_id_post;
            $nd  = isset($note_data["devoir"])  ? (float)$note_data["devoir"]  : null;
            $ne  = isset($note_data["examen"])  ? (float)$note_data["examen"]  : null;
            $note_id = (int)($note_data["note_id"] ?? 0);

            if ($ne === null) continue; // Ignorer si pas de note examen

            // Vérifier limites
            if ($nd !== null) $nd = max(0, min(20, $nd));
            $ne = max(0, min(20, $ne));

            if ($note_id) {
                // UPDATE
                db_exec($conn,
                    "UPDATE notes SET note_devoir=?, note_examen=?, publie=?,
                     publie_le=IF(?,NOW(),publie_le), saisie_par=?, modifiee_le=NOW()
                     WHERE id=? AND etudiant_id=?",
                    "ddiiiiii",
                    [$nd, $ne, $publier ? 1 : 0, $publier, (int)$user["id"], $note_id, $etu_id_post]
                );
            } else {
                // INSERT (trigger calcule note_finale automatiquement)
                db_exec($conn,
                    "INSERT INTO notes (etudiant_id, module_id, semestre, annee_acad,
                     note_devoir, note_examen, publie, publie_le, saisie_par)
                     VALUES (?,?,?,?,?,?,?,IF(?,NOW(),NULL),?)",
                    "iiisddiii",
                    [$etu_id_post, $sel_module_id, $sel_semestre, $annee_acad,
                     $nd, $ne, $publier ? 1 : 0, $publier, (int)$user["id"]]
                );
            }
            if ($publier) {
                // Notifier l'étudiant
                $uid_etu = db_row($conn,"SELECT user_id FROM etudiants WHERE id=?","i",[$etu_id_post])["user_id"] ?? 0;
                if ($uid_etu) {
                    db_exec($conn,
                        "INSERT INTO notifications (user_id,type,titre,corps,canal)
                         VALUES (?,'note',?,?,'')",
                        "iss", [$uid_etu,
                            "Note publiée — " . h($module_info["nom"] ?? ""),
                            "Votre note a été publiée par votre enseignant. Consultez votre relevé."
                        ]
                    );
                }
            }
        }
        db_exec($conn,
            "INSERT INTO audit_log (user_id,action,detail,ip_address) VALUES (?,?,?,?)",
            "isss", [(int)$user["id"],
                $publier ? "NOTES_PUBLIEES" : "NOTES_SAUVEGARDEES",
                json_encode(["module_id"=>$sel_module_id,"classe_id"=>$sel_classe_id]),
                $_SERVER["REMOTE_ADDR"] ?? ""
            ]
        );
        // Recharger
        header("Location: enseignant_notes.php?module=$sel_module_id&classe=$sel_classe_id&sem=$sel_semestre&ok=" . ($publier?"published":"saved"));
        exit;
    }
}
$save_ok    = isset($_GET["ok"]) && $_GET["ok"] === "saved";
$publish_ok = isset($_GET["ok"]) && $_GET["ok"] === "published";

// Notifications non lues
$nb_notifs = (int)(db_row($conn,"SELECT COUNT(*) AS nb FROM notifications WHERE user_id = ? AND lu = 0","i",[(int)$user["id"]])["nb"] ?? 0);

$u_numero = h($user['numero_ucao']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EduConnect — Saisie des Notes</title>
<link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@300;400;500;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
<style>
:root{--blue:#326ea9;--blue-dk:#1f4a7a;--blue-lt:#4a90c4;--blue-xlt:#e8f0f8;--blue-dim:rgba(50,110,169,.12);--red:#C41E3A;--red-lt:#e85b72;--red-dim:rgba(196,30,58,.12);--green:#27ae60;--green-lt:#2ecc71;--green-dim:rgba(39,174,96,.12);--orange:#e67e22;--orange-dim:rgba(230,126,34,.12);--gold:#D4A843;--gold-dim:rgba(212,168,67,.15);--purple:#7C3AED;--purple-dim:rgba(124,58,237,.12);--teal:#0891b2;--teal-dk:#065f7e;--teal-lt:#06b6d4;--teal-dim:rgba(8,145,178,.12);--bg:#f0f2f5;--surface:#fff;--surface2:#f8f9fb;--border:#e0e3e8;--border2:#d1d5db;--txt:#1a1a2e;--txt2:#4b5563;--txt3:#9ca3af;--sidebar-w:240px;--topbar-h:56px;--r:8px;--r-sm:5px;--sh:0 1px 4px rgba(0,0,0,.08);--sh-md:0 4px 16px rgba(0,0,0,.12);--sh-lg:0 8px 32px rgba(0,0,0,.15);--tr:.2s ease}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}html{font-size:14px}body{font-family:'Ubuntu',sans-serif;background:var(--bg);color:var(--txt);min-height:100vh;display:flex}button{font-family:'Ubuntu',sans-serif;cursor:pointer;border:none;background:none}a{text-decoration:none;color:inherit}::-webkit-scrollbar{width:4px;height:4px}::-webkit-scrollbar-thumb{background:var(--border2);border-radius:10px}@keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
.sidebar{width:var(--sidebar-w);background:var(--blue-dk);position:fixed;height:100vh;z-index:400;display:flex;flex-direction:column;transition:width var(--tr);overflow:hidden}.sidebar.collapsed{width:54px}.logo-zone{height:var(--topbar-h);padding:0 14px;display:flex;align-items:center;gap:10px;border-bottom:1px solid rgba(255,255,255,.1);flex-shrink:0}.logo-mark{width:32px;height:32px;border-radius:7px;background:linear-gradient(135deg,var(--teal),var(--teal-lt));display:flex;align-items:center;justify-content:center;font-size:17px;color:#fff;flex-shrink:0;box-shadow:0 2px 8px rgba(8,145,178,.5)}.logo-text{font-size:15px;font-weight:700;color:#fff;white-space:nowrap;overflow:hidden}.logo-text span{color:#a8c8e8}.sidebar-toggle{margin-left:auto;width:24px;height:24px;border-radius:5px;display:flex;align-items:center;justify-content:center;color:rgba(255,255,255,.5);font-size:14px;transition:all var(--tr);flex-shrink:0;cursor:pointer}.sidebar-toggle:hover{background:rgba(255,255,255,.1);color:#fff}.sidebar-search{padding:10px 10px 6px;flex-shrink:0;overflow:hidden;transition:all var(--tr)}.sidebar.collapsed .sidebar-search{opacity:0;height:0;padding:0}.search-field{display:flex;align-items:center;gap:6px;padding:6px 10px;border-radius:var(--r-sm);background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12)}.search-field i{color:rgba(255,255,255,.5);font-size:13px;flex-shrink:0}.search-field input{background:none;border:none;outline:none;color:#fff;font-family:'Ubuntu',sans-serif;font-size:12px;width:100%}.search-field input::placeholder{color:rgba(255,255,255,.4)}.nav-body{flex:1;padding:4px 6px;overflow-y:auto;scrollbar-width:none}.nav-body::-webkit-scrollbar{display:none}.nav-section{font-size:9px;font-weight:700;letter-spacing:1.8px;text-transform:uppercase;color:rgba(255,255,255,.3);padding:10px 8px 4px;white-space:nowrap;overflow:hidden;transition:opacity var(--tr)}.sidebar.collapsed .nav-section{opacity:0}.nav-item{display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);color:rgba(255,255,255,.6);font-size:12.5px;font-weight:500;transition:all var(--tr);margin-bottom:1px;width:100%;text-align:left;cursor:pointer;white-space:nowrap;position:relative;overflow:hidden}.nav-item:hover{background:rgba(255,255,255,.08);color:#fff}.nav-item.active{background:rgba(255,255,255,.15);color:#fff;font-weight:600}.nav-item.active::before{content:'';position:absolute;left:0;top:20%;bottom:20%;width:3px;background:var(--teal-lt);border-radius:0 2px 2px 0}.nav-item i{font-size:16px;flex-shrink:0;opacity:.8;min-width:16px}.nav-item.active i{opacity:1;color:var(--teal-lt)}.nav-item-txt{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;transition:opacity var(--tr)}.sidebar.collapsed .nav-item-txt{opacity:0;width:0}.nav-badge{background:var(--red);font-size:9px;font-weight:700;padding:1px 6px;border-radius:20px;color:#fff;flex-shrink:0}.sidebar-foot{padding:8px;border-top:1px solid rgba(255,255,255,.1);flex-shrink:0}.user-chip{display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);background:rgba(255,255,255,.07);cursor:pointer;transition:background var(--tr);overflow:hidden}.user-chip:hover{background:rgba(255,255,255,.12)}.user-ava{width:30px;height:30px;border-radius:7px;background:linear-gradient(135deg,var(--teal-dk),var(--blue-dk));display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;color:#fff;flex-shrink:0}.user-info{overflow:hidden;transition:opacity var(--tr)}.sidebar.collapsed .user-info{opacity:0;width:0}.user-name{font-size:12px;font-weight:600;color:#fff;white-space:nowrap}.user-role{font-size:10px;color:rgba(255,255,255,.4);white-space:nowrap}
.main{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh;transition:margin-left var(--tr)}.main.expanded{margin-left:54px}
.topbar{height:var(--topbar-h);background:var(--surface);border-bottom:1px solid var(--border);display:flex;align-items:center;padding:0 20px;gap:12px;position:sticky;top:0;z-index:200;box-shadow:var(--sh)}.topbar-brand{font-size:13px;font-weight:600;color:var(--teal-dk);display:flex;align-items:center;gap:6px}.topbar-brand i{font-size:16px}.tab-nav{display:flex;align-items:center;gap:1px;background:var(--surface2);border:1px solid var(--border);border-radius:var(--r-sm);padding:3px;overflow-x:auto;scrollbar-width:none}.tab-nav::-webkit-scrollbar{display:none}.tab-btn{display:flex;align-items:center;gap:5px;padding:5px 12px;border-radius:4px;font-size:12px;font-weight:500;color:var(--txt2);white-space:nowrap;transition:all var(--tr);cursor:pointer}.tab-btn:hover{background:var(--surface);color:var(--txt)}.tab-btn.active{background:var(--teal);color:#fff;box-shadow:0 1px 4px rgba(8,145,178,.35)}.tab-btn i{font-size:13px}.topbar-right{margin-left:auto;display:flex;align-items:center;gap:8px}.tb-search{display:flex;align-items:center;gap:7px;padding:6px 12px;border-radius:var(--r-sm);background:var(--surface2);border:1px solid var(--border);min-width:180px}.tb-search:focus-within{border-color:var(--teal);background:#fff}.tb-search i{color:var(--txt3);font-size:13px;flex-shrink:0}.tb-search input{background:none;border:none;outline:none;color:var(--txt);font-family:'Ubuntu',sans-serif;font-size:12.5px;width:100%}.tb-icon{width:32px;height:32px;border-radius:var(--r-sm);background:var(--surface2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;color:var(--txt2);font-size:15px;transition:all var(--tr);cursor:pointer;position:relative}.tb-icon:hover{background:var(--teal-dim);border-color:var(--teal);color:var(--teal-dk)}.tb-notif{position:absolute;top:5px;right:5px;width:6px;height:6px;background:var(--red);border-radius:50%;border:1.5px solid var(--surface)}.tb-user{display:flex;align-items:center;gap:7px;padding:4px 10px 4px 4px;border-radius:var(--r-sm);background:var(--surface2);border:1px solid var(--border);cursor:pointer;transition:all var(--tr)}.tb-user:hover{border-color:var(--teal);background:var(--teal-dim)}.tb-ava{width:28px;height:28px;border-radius:6px;background:linear-gradient(135deg,var(--teal),var(--teal-dk));display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;color:#fff;flex-shrink:0}.tb-uname{font-size:12px;font-weight:600;color:var(--txt)}.tb-uentity{font-size:10px;color:var(--txt3)}
.content{padding:18px 20px;flex:1}
.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;animation:fadeUp .3s ease;flex-wrap:wrap;gap:10px}.page-title{display:flex;align-items:center;gap:10px}.page-title h1{font-size:24px;font-weight:700;color:var(--txt)}.page-title i{font-size:28px;color:var(--teal-dk)}.page-title p{font-size:12px;color:var(--txt3);margin-top:2px}.page-actions{display:flex;gap:8px}
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:var(--r-sm);font-size:12px;font-weight:600;cursor:pointer;transition:all var(--tr);border:1px solid transparent;font-family:'Ubuntu',sans-serif}.btn-primary{background:var(--teal);color:#fff;border-color:var(--teal)}.btn-primary:hover{background:var(--teal-dk);box-shadow:var(--sh-md);transform:translateY(-2px)}.btn-secondary{background:var(--surface2);color:var(--txt2);border-color:var(--border)}.btn-secondary:hover{border-color:var(--teal);color:var(--teal-dk)}.btn-success{background:var(--green);color:#fff;border-color:var(--green)}.btn-success:hover{filter:brightness(.9)}.btn-sm{padding:5px 10px;font-size:11px}.btn i{font-size:13px}
/* selector bar */
.selector-bar{display:flex;gap:10px;background:var(--surface);border:1px solid var(--border);border-radius:var(--r);padding:14px 16px;margin-bottom:16px;box-shadow:var(--sh);animation:fadeUp .3s ease;flex-wrap:wrap;align-items:flex-end}
.form-group-inline{display:flex;flex-direction:column;gap:4px;flex:1;min-width:140px}
.form-label-sm{font-size:11px;font-weight:600;color:var(--txt3);text-transform:uppercase;letter-spacing:.5px}
.form-select{width:100%;padding:8px 10px;border-radius:var(--r-sm);border:1px solid var(--border);font-family:'Ubuntu',sans-serif;font-size:12.5px;color:var(--txt);background:var(--surface);cursor:pointer;transition:border-color var(--tr)}.form-select:focus{outline:none;border-color:var(--teal)}
/* stats */
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:16px}
.stat-card{background:var(--surface);border-radius:var(--r);border:1px solid var(--border);box-shadow:var(--sh);padding:12px 16px;display:flex;align-items:center;justify-content:space-between;animation:fadeUp .3s ease}.stat-label{font-size:11px;color:var(--txt3);font-weight:500;margin-bottom:2px}.stat-value{font-size:20px;font-weight:700;color:var(--txt)}.stat-icon{width:36px;height:36px;border-radius:var(--r-sm);display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0}
.stat-icon.teal{background:var(--teal-dim);color:var(--teal-dk)}.stat-icon.green{background:var(--green-dim);color:var(--green)}.stat-icon.orange{background:var(--orange-dim);color:var(--orange)}.stat-icon.red{background:var(--red-dim);color:var(--red)}
/* grade entry table — key feature */
.grade-table-wrap{background:var(--surface);border-radius:var(--r);border:1px solid var(--border);box-shadow:var(--sh);overflow:hidden;animation:fadeUp .3s ease;margin-bottom:16px}
.grade-table-head{padding:12px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
.grade-table-title{font-size:13px;font-weight:600;color:var(--txt);display:flex;align-items:center;gap:6px}
.grade-table-title i{color:var(--teal-dk)}
.table{width:100%;border-collapse:collapse}
.table th{padding:10px 12px;text-align:left;font-size:11px;font-weight:700;color:var(--txt3);text-transform:uppercase;letter-spacing:.5px;border-bottom:2px solid var(--border);background:var(--surface2)}
.table td{padding:8px 12px;border-bottom:1px solid var(--border);font-size:12px;color:var(--txt2);vertical-align:middle}
.table tr:hover{background:var(--surface2)}
.table tr:last-child td{border:none}
/* grade input cell */
.grade-input{width:64px;padding:6px 8px;border-radius:var(--r-sm);border:1px solid var(--border);font-family:'Ubuntu',sans-serif;font-size:13px;font-weight:600;text-align:center;color:var(--txt);background:var(--surface2);transition:all var(--tr)}
.grade-input:focus{outline:none;border-color:var(--teal);background:#fff;box-shadow:0 0 0 3px var(--teal-dim)}
.grade-input.valid{border-color:var(--green);color:var(--green)}
.grade-input.invalid{border-color:var(--red);color:var(--red)}
.grade-input.unsaved{border-color:var(--orange);background:rgba(230,126,34,.05)}
/* mention auto-computed */
.mention-cell{font-size:11px;font-weight:700;min-width:36px}
.note-bar{height:5px;background:var(--border);border-radius:10px;overflow:hidden;width:60px;margin-top:3px}
.note-fill{height:100%;border-radius:10px}
.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:4px;font-size:10px;font-weight:600}
.badge-blue{background:var(--blue-dim);color:var(--blue)}.badge-green{background:var(--green-dim);color:var(--green)}.badge-orange{background:var(--orange-dim);color:var(--orange)}.badge-red{background:var(--red-dim);color:var(--red)}.badge-teal{background:var(--teal-dim);color:var(--teal-dk)}
/* chart */
.two-col{display:grid;grid-template-columns:1.5fr 1fr;gap:16px;margin-bottom:16px}
.card{background:var(--surface);border-radius:var(--r);border:1px solid var(--border);box-shadow:var(--sh);padding:16px;animation:fadeUp .3s ease}
.card-title{font-size:13px;font-weight:600;color:var(--txt);margin-bottom:12px;display:flex;align-items:center;gap:6px}
.card-title i{color:var(--teal-dk);font-size:14px}
.chart-container{height:220px}
/* publish alert */
.publish-bar{background:linear-gradient(135deg,var(--teal),var(--teal-lt));border-radius:var(--r-sm);padding:12px 16px;color:#fff;display:flex;align-items:center;gap:12px;margin-bottom:16px;animation:fadeUp .3s ease}
.publish-bar i{font-size:20px;flex-shrink:0}
.publish-bar p{font-size:12px;opacity:.9;margin-top:2px}
.toast{position:fixed;bottom:20px;right:20px;background:var(--txt);color:#fff;padding:12px 16px;border-radius:var(--r-sm);font-size:12.5px;font-weight:500;box-shadow:var(--sh-lg);transform:translateY(100px);opacity:0;transition:all .3s cubic-bezier(.34,1.56,.64,1);z-index:9999;border-left:3px solid var(--teal)}.toast.show{transform:translateY(0);opacity:1}
/* modal */
.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;align-items:center;justify-content:center}.modal.show{display:flex;animation:fadeUp .2s ease}
.modal-content{background:var(--surface);border-radius:var(--r);padding:22px;width:440px;max-width:92%;box-shadow:var(--sh-lg)}
.modal-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;padding-bottom:10px;border-bottom:1px solid var(--border)}.modal-title{font-size:15px;font-weight:700;color:var(--txt)}.modal-close{width:26px;height:26px;border-radius:4px;background:var(--surface2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;color:var(--txt2);cursor:pointer;font-size:14px;transition:all var(--tr)}.modal-close:hover{background:var(--red-dim);color:var(--red)}
.modal-footer{display:flex;gap:8px;justify-content:flex-end;margin-top:14px;padding-top:12px;border-top:1px solid var(--border)}
@media(max-width:1200px){.two-col{grid-template-columns:1fr}}
@media(max-width:768px){.sidebar{transform:translateX(-100%)}.main{margin-left:0}.content{padding:12px 14px}.page-header{flex-direction:column;align-items:flex-start}.selector-bar{flex-direction:column}.stats-grid{grid-template-columns:repeat(2,1fr)}}
</style>
</head>
<body>

<aside class="sidebar" id="sidebar">
  <div class="logo-zone">
    <div class="logo-mark"><i class="bi bi-person-video3"></i></div>
    <div><div class="logo-text">Edu<span>Connect</span></div><div style="font-size:9px;color:rgba(255,255,255,.4)">Enseignant</div></div>
    <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-chevron-left"></i></button>
  </div>
  <div class="sidebar-search"><div class="search-field"><i class="bi bi-search"></i><input type="text" placeholder="Rechercher…"></div></div>
  <div class="nav-body">
    <div class="nav-section">Enseignement</div>
    <button class="nav-item <?= $current_page === 'enseignant_cours.php' ? 'active' : '' ?>" onclick="location.href='enseignant_cours.php'"><i class="bi bi-book"></i><span class="nav-item-txt">Mes Cours</span></button>
    <button class="nav-item active"><i class="bi bi-pencil-square"></i><span class="nav-item-txt">Saisie des Notes</span></button>
    <button class="nav-item <?= $current_page === 'enseignant_ressources.php' ? 'active' : '' ?>" onclick="location.href='enseignant_ressources.php'"><i class="bi bi-folder2-open"></i><span class="nav-item-txt">Ressources</span></button>
    <div class="nav-section">Communication</div>
    <button class="nav-item"><i class="bi bi-calendar3"></i><span class="nav-item-txt">Mon Planning</span></button>
    <button class="nav-item"><i class="bi bi-chat-dots"></i><span class="nav-item-txt">Messages</span><span class="nav-badge"><?= $nb_notifs > 0 ? $nb_notifs : "" ?></span></button>
    <button class="nav-item"><i class="bi bi-bell"></i><span class="nav-item-txt">Notifications</span></button>
    <div class="nav-section">Compte</div>
    <button class="nav-item"><i class="bi bi-person"></i><span class="nav-item-txt">Mon Profil</span></button>
    <button class="nav-item"><i class="bi bi-gear"></i><span class="nav-item-txt">Paramètres</span></button>
  </div>
  <div class="sidebar-foot"><div class="user-chip" onclick="location.href='/Views/Enseignant/enseignant_profil.php'" style="cursor:pointer"><div class="user-ava"><?= $u_init ?></div><div class="user-info"><div class="user-name"><?= $u_full ?></div><div class="user-role"><?= $u_role ?></div></div></div>
  <a href="/Views/Enseignant/enseignant_parametres.php" style="display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);color:rgba(255,255,255,.5);font-size:12px;text-decoration:none;transition:all .2s;overflow:hidden" onmouseover="this.style.background='rgba(255,255,255,.08)';this.style.color='#fff'" onmouseout="this.style.background='';this.style.color='rgba(255,255,255,.5)'"><i class="bi bi-gear" style="font-size:16px;flex-shrink:0;min-width:16px"></i><span class="nav-item-txt">Paramètres</span></a>
  <a href="/Views/Auth/logout.php" style="display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);color:rgba(255,255,255,.5);font-size:12px;font-weight:500;text-decoration:none;transition:all .2s;margin-top:4px;overflow:hidden" onmouseover="this.style.background='rgba(196,30,58,.2)';this.style.color='#e85b72'" onmouseout="this.style.background='';this.style.color='rgba(255,255,255,.5)'">
    <i class="bi bi-box-arrow-left" style="font-size:16px;flex-shrink:0;min-width:16px"></i>
    <span class="nav-item-txt">Déconnexion</span>
  </a></div>
</aside>

<main class="main" id="main">
  <div class="topbar">
    <div class="topbar-brand"><i class="bi bi-pencil-square"></i>Saisie des Notes</div>
    <div class="tab-nav">
      <button class="tab-btn active" onclick="setTab('saisie',this)"><i class="bi bi-pencil-square"></i>Saisie</button>
      <button class="tab-btn" onclick="setTab('recapitulatif',this)"><i class="bi bi-bar-chart"></i>Récapitulatif</button>
      <button class="tab-btn" onclick="setTab('historique',this)"><i class="bi bi-archive"></i>Historique</button>
    </div>
    <div class="topbar-right">
      <div class="tb-search"><i class="bi bi-search"></i><input type="text" placeholder="Rechercher un étudiant…" onkeyup="filterStudents(this.value)"></div>
      <button class="tb-icon" title="Notifications"><i class="bi bi-bell-fill"></i><span class="tb-notif"></span></button>
      <button class="tb-icon" onclick="toast('📥 Export notes CSV')" title="Exporter"><i class="bi bi-download"></i></button>
      <div class="tb-user"><div class="tb-ava"><?= $u_init ?></div><div><div class="tb-uname"><?= $u_full ?></div><div class="tb-uentity"><?= $u_role ?></div></div></div>
    </div>
  </div>

  <div class="content">
    <div class="page-header">
      <div class="page-title"><i class="bi bi-pencil-square"></i><div><h1>Saisie des Notes</h1><p>Entrez, modifiez et publiez les notes de vos étudiants</p></div></div>
      <div class="page-actions">
        <button class="btn btn-secondary" onclick="toast('📥 Export CSV lancé')"><i class="bi bi-download"></i>Exporter</button>
        <button class="btn btn-primary" onclick="openPublish()"><i class="bi bi-send-fill"></i>Publier les notes</button>
      </div>
    </div>

    <!-- Course + evaluation selector -->
    <div class="selector-bar">
      <div class="form-group-inline">
        <span class="form-label-sm">Cours</span>
        <select class="form-select" onchange="changeCourse(this.value)">
          <option value="DRT201">DRT201 — Droit Civil (L2-A)</option>
          <option value="DRT201B">DRT201 — Droit Civil (L2-B)</option>
          <option value="DRT301">DRT301 — Droit Civil Approfondi (L3)</option>
          <option value="DRT101">DRT101 — Introduction au Droit (L1)</option>
          <option value="DRT205">DRT205 — Droit des Affaires (L2-D)</option>
        </select>
      </div>
      <div class="form-group-inline">
        <span class="form-label-sm">Type d'évaluation</span>
        <select class="form-select" onchange="toast('📋 Évaluation : '+this.options[this.selectedIndex].text)">
          <option>Contrôle continu — Semestre 2</option>
          <option>Examen final — Semestre 2</option>
          <option>Contrôle continu — Semestre 1</option>
          <option>Examen final — Semestre 1</option>
          <option>Rattrapage</option>
        </select>
      </div>
      <div class="form-group-inline">
        <span class="form-label-sm">Barème</span>
        <select class="form-select">
          <option>Sur 20</option>
          <option>Sur 100</option>
        </select>
      </div>
      <button class="btn btn-secondary" onclick="loadGrades()" style="align-self:flex-end"><i class="bi bi-arrow-clockwise"></i>Charger</button>
    </div>

    <!-- Publish alert -->
    <div class="publish-bar" id="publishBar">
      <i class="bi bi-exclamation-circle-fill"></i>
      <div style="flex:1">
        <div style="font-size:13px;font-weight:700">3 notes non publiées — Les étudiants ne les voient pas encore</div>
        <p>Entrez toutes les notes puis cliquez sur "Publier" pour les rendre visibles. Les parents et étudiants seront notifiés automatiquement.</p>
      </div>
      <button class="btn" style="background:rgba(255,255,255,.2);color:#fff;border-color:rgba(255,255,255,.4);flex-shrink:0" onclick="openPublish()"><i class="bi bi-send-fill"></i>Publier</button>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
      <div class="stat-card"><div><div class="stat-label">Étudiants</div><div class="stat-value"><?= count($etudiants_notes) ?></div></div><div class="stat-icon teal"><i class="bi bi-people"></i></div></div>
      <div class="stat-card"><div><div class="stat-label">Notes saisies</div><div class="stat-value" id="savedCount"><?= $notes_saisies ?></div></div><div class="stat-icon green"><i class="bi bi-check-circle"></i></div></div>
      <div class="stat-card"><div><div class="stat-label">En attente</div><div class="stat-value" id="pendingCount">3</div></div><div class="stat-icon orange"><i class="bi bi-hourglass-split"></i></div></div>
      <div class="stat-card"><div><div class="stat-label">Moyenne classe</div><div class="stat-value" id="avgDisplay">15.2</div></div><div class="stat-icon teal"><i class="bi bi-graph-up"></i></div></div>
    </div>

    <!-- Two col: grade table + chart -->
    <div class="two-col">
      <!-- Grade entry table — the core feature -->
      <div class="grade-table-wrap">
        <div class="grade-table-head">
          <div class="grade-table-title"><i class="bi bi-pencil-square"></i>DRT201 — Droit Civil L2-A · Contrôle continu S2</div>
          <div style="display:flex;gap:6px">
            <button class="btn btn-secondary btn-sm" onclick="autoSave()"><i class="bi bi-floppy"></i>Sauvegarder</button>
            <button class="btn btn-primary btn-sm" onclick="openPublish()"><i class="bi bi-send-fill"></i>Publier</button>
          </div>
        </div>
        <table class="table">
          <thead>
            <tr>
              <th>#</th>
              <th>Étudiant</th>
              <th>Matricule</th>
              <th style="text-align:center">Note /20</th>
              <th>Progression</th>
              <th>Mention</th>
              <th>Statut</th>
            </tr>
          </thead>
          <tbody id="gradeTableBody">
            <!-- Rows injected by JS -->
          </tbody>
        </table>
      </div>

      <!-- Distribution chart -->
      <div>
        <div class="card" style="margin-bottom:16px">
          <div class="card-title"><i class="bi bi-bar-chart"></i>Distribution des notes</div>
          <div class="chart-container"><canvas id="distChart"></canvas></div>
        </div>
        <div class="card">
          <div class="card-title"><i class="bi bi-info-circle"></i>Récapitulatif</div>
          <div style="display:flex;flex-direction:column;gap:8px">
            <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--border);font-size:12px"><span style="color:var(--txt3)">Moyenne générale</span><span style="font-weight:700;color:var(--teal-dk)" id="recapAvg">15.2 / 20</span></div>
            <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--border);font-size:12px"><span style="color:var(--txt3)">Note la plus haute</span><span style="font-weight:700;color:var(--green)">19 / 20</span></div>
            <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--border);font-size:12px"><span style="color:var(--txt3)">Note la plus basse</span><span style="font-weight:700;color:var(--orange)">8.5 / 20</span></div>
            <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--border);font-size:12px"><span style="color:var(--txt3)">Taux de réussite (≥10)</span><span style="font-weight:700;color:var(--green)">90.5%</span></div>
            <div style="display:flex;justify-content:space-between;padding:7px 0;font-size:12px"><span style="color:var(--txt3)">Mention TB (≥16)</span><span style="font-weight:700;color:var(--teal-dk)">8 étudiants</span></div>
          </div>
        </div>
      </div>
    </div>

  </div>
</main>

<!-- Publish modal -->
<div class="modal" id="publishModal">
  <div class="modal-content">
    <div class="modal-header">
      <div class="modal-title" style="display:flex;align-items:center;gap:7px"><i class="bi bi-send-fill" style="color:var(--teal-dk)"></i>Publier les notes</div>
      <button class="modal-close" onclick="closeModal()"><i class="bi bi-x"></i></button>
    </div>
    <div style="font-size:13px;color:var(--txt2);line-height:1.6;margin-bottom:14px">
      Vous êtes sur le point de publier les notes de <strong>DRT201 — Droit Civil L2-A</strong> (Contrôle continu S2).<br><br>
      Une fois publiées, les <strong>21 étudiants</strong> et leurs parents seront notifiés automatiquement par email, SMS et notification push.
    </div>
    <div style="background:var(--teal-dim);border-radius:var(--r-sm);padding:10px 12px;font-size:12px;color:var(--teal-dk);display:flex;gap:8px;align-items:flex-start">
      <i class="bi bi-info-circle-fill" style="flex-shrink:0;margin-top:1px"></i>
      <span>Cette action est définitive. Toute modification ultérieure nécessitera une correction officielle via l'administration.</span>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal()">Annuler</button>
      <button class="btn btn-primary" onclick="publishGrades()"><i class="bi bi-send-fill"></i>Confirmer la publication</button>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>
<script>
const STUDENTS = <?= json_encode(array_map(fn($en)=>[
  'id'       => (int)$en['etu_id'],
  'num'      => $en['numero_ucao'] ?? '—',
  'name'     => $en['prenom'].' '.$en['nom'],
  'devoir'   => $en['note_devoir'] !== null ? (float)$en['note_devoir'] : null,
  'examen'   => $en['note_examen'] !== null ? (float)$en['note_examen'] : null,
  'final'    => $en['note_finale'] !== null ? (float)$en['note_finale'] : null,
  'publie'   => (bool)$en['publie'],
  'note_id'  => (int)($en['note_id']??0),
], $etudiants_notes)) ?>;

function getMention(n){
  if(n===null)return{txt:'—',cls:'color:var(--txt3)'};
  if(n>=16)return{txt:'TB',cls:'color:var(--green)'};
  if(n>=14)return{txt:'B',cls:'color:var(--blue)'};
  if(n>=12)return{txt:'AB',cls:'color:var(--orange)'};
  if(n>=10)return{txt:'P',cls:'color:var(--orange)'};
  return{txt:'F',cls:'color:var(--red)'};
}

function renderTable(data){
  const tbody=document.getElementById('gradeTableBody');
  tbody.innerHTML='';
  data.forEach((s,i)=>{
    const m=getMention(s.note);
    const pct=s.note?((s.note/20)*100).toFixed(0):0;
    const fillColor=s.note>=16?'var(--green)':s.note>=12?'var(--teal)':s.note>=10?'var(--orange)':'var(--red)';
    const statusBadge=s.saved?'<span class="badge badge-green"><i class="bi bi-check-circle"></i>Sauvegardé</span>':'<span class="badge badge-orange"><i class="bi bi-pencil"></i>À saisir</span>';
    const inputCls=s.note===null?'grade-input unsaved':s.note<10?'grade-input invalid':'grade-input valid';
    tbody.innerHTML+=`<tr id="row${s.id}">
      <td style="color:var(--txt3);font-size:11px">${i+1}</td>
      <td style="font-weight:600">${s.name}</td>
      <td style="font-family:monospace;font-size:11px;color:var(--txt3)">${s.mat}</td>
      <td style="text-align:center">
        <input type="number" class="${inputCls}" id="note${s.id}" min="0" max="20" step="0.5"
          value="${s.note??''}" placeholder="—"
          oninput="updateNote(${s.id},this.value)"
          onblur="saveNote(${s.id},this.value)">
      </td>
      <td>
        <div style="font-size:11px;font-weight:600;color:var(--txt2)">${s.note??'—'}</div>
        <div class="note-bar"><div class="note-fill" style="width:${pct}%;background:${fillColor}"></div></div>
      </td>
      <td class="mention-cell" id="mention${s.id}" style="${m.cls}">${m.txt}</td>
      <td id="status${s.id}">${statusBadge}</td>
    </tr>`;
  });
}

function updateNote(id,val){
  const inp=document.getElementById('note'+id);
  const v=parseFloat(val);
  if(val===''){ inp.className='grade-input unsaved'; document.getElementById('mention'+id).textContent='—'; return; }
  if(isNaN(v)||v<0||v>20){ inp.className='grade-input invalid'; return; }
  inp.className='grade-input valid';
  const m=getMention(v);
  document.getElementById('mention'+id).textContent=m.txt;
  document.getElementById('mention'+id).style.cssText=m.cls;
  // mark as unsaved
  document.getElementById('status'+id).innerHTML='<span class="badge badge-orange"><i class="bi bi-pencil"></i>Non sauvegardé</span>';
}

function saveNote(id,val){
  const v=parseFloat(val);
  if(isNaN(v)||v<0||v>20) return;
  const s=STUDENTS.find(x=>x.id===id);
  if(s){ s.note=v; s.saved=true; }
  document.getElementById('status'+id).innerHTML='<span class="badge badge-green"><i class="bi bi-check-circle"></i>Sauvegardé</span>';
  updateStats();
}

function autoSave(){
  STUDENTS.forEach(s=>{
    const inp=document.getElementById('note'+s.id);
    if(inp){ const v=parseFloat(inp.value); if(!isNaN(v)&&v>=0&&v<=20){ s.note=v; s.saved=true; document.getElementById('status'+s.id).innerHTML='<span class="badge badge-green"><i class="bi bi-check-circle"></i>Sauvegardé</span>'; } }
  });
  updateStats();
  toast('✅ Notes sauvegardées automatiquement');
}

function updateStats(){
  const notes=STUDENTS.map(s=>s.note).filter(n=>n!==null);
  const avg=notes.length?( notes.reduce((a,b)=>a+b,0)/notes.length ).toFixed(1):0;
  const pending=STUDENTS.filter(s=>s.note===null).length;
  document.getElementById('savedCount').textContent=STUDENTS.length-pending;
  document.getElementById('pendingCount').textContent=pending;
  document.getElementById('avgDisplay').textContent=avg;
  document.getElementById('recapAvg').textContent=avg+' / 20';
}

function filterStudents(val){
  document.querySelectorAll('#gradeTableBody tr').forEach(row=>{
    row.style.display=row.textContent.toLowerCase().includes(val.toLowerCase())?'':'none';
  });
}

function openPublish(){ document.getElementById('publishModal').classList.add('show'); }
function closeModal(){ document.getElementById('publishModal').classList.remove('show'); }
function publishGrades(){
  closeModal();
  document.getElementById('publishBar').style.display='none';
  toast('🚀 Notes publiées ! 21 étudiants et leurs parents ont été notifiés.');
}

function changeCourse(c){ toast('📋 Cours chargé : '+c); }
function loadGrades(){ renderTable(STUDENTS); toast('✓ Notes chargées'); }
function setTab(tab,btn){ document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active')); btn.classList.add('active'); }
function toggleSidebar(){ document.getElementById('sidebar').classList.toggle('collapsed'); document.getElementById('main').classList.toggle('expanded'); }
let toastTimer;function toast(msg){const el=document.getElementById('toast');el.textContent=msg;el.classList.add('show');clearTimeout(toastTimer);toastTimer=setTimeout(()=>el.classList.remove('show'),3200)}

document.addEventListener('DOMContentLoaded',()=>{
  renderTable(STUDENTS);
  updateStats();
  // Distribution chart
  new Chart(document.getElementById('distChart').getContext('2d'),{
    type:'bar',
    data:{
      labels:['0–5','5–8','8–10','10–12','12–14','14–16','16–18','18–20'],
      datasets:[{label:'Étudiants',data: <?= json_encode((function($notes){
  $bins=array_fill(0,8,0);
  foreach($notes as $n){ if($n['note_finale']!==null){ $i=min(7,(int)floor((float)$n['note_finale']/2.5)); $bins[$i]++; } }
  return $bins;
})($etudiants_notes)) ?>,backgroundColor:['var(--red)','var(--red)','var(--orange)','var(--orange)','var(--blue)','var(--blue)','var(--teal)','var(--green)'],borderRadius:4}]
    },
    options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{stepSize:1,font:{family:'Ubuntu',size:11}}},x:{ticks:{font:{family:'Ubuntu',size:10}}}}}
  });
});
</script>
</body>
</html>
