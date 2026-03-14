<?php
session_start();
include "../connection.php";
if (!isset($con) && isset($conn)) { $con = $conn; }

if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php"); exit();
}

/* ── Actions POST ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action'] ?? '';
    $section = $_POST['section'] ?? 'overview';

    if ($action === 'toggle_doctor') {
        $id  = (int)$_POST['docid'];
        $new = $_POST['new_status'];
        if (in_array($new, ['active','pending'])) {
            $stmt = $con->prepare("UPDATE doctor SET status=? WHERE docid=?");
            $stmt->bind_param("si", $new, $id);
            $stmt->execute(); $stmt->close();
        }
    } elseif ($action === 'delete_doctor') {
        $id = (int)$_POST['rid'];
        $stmt = $con->prepare("DELETE FROM doctor WHERE docid=?");
        $stmt->bind_param("i",$id); $stmt->execute(); $stmt->close();
    } elseif ($action === 'delete_appointment') {
        $id = (int)$_POST['rid'];
        $stmt = $con->prepare("DELETE FROM appointments WHERE id=?");
        $stmt->bind_param("i",$id); $stmt->execute(); $stmt->close();
    } elseif ($action === 'delete_consultation') {
        $id = (int)$_POST['rid'];
        $stmt = $con->prepare("DELETE FROM consultations WHERE id=?");
        $stmt->bind_param("i",$id); $stmt->execute(); $stmt->close();
    } elseif ($action === 'delete_patient') {
        $id = (int)$_POST['rid'];
        $stmt = $con->prepare("DELETE FROM patients WHERE id=?");
        $stmt->bind_param("i",$id); $stmt->execute(); $stmt->close();
    }
    header("Location: dashboard.php?section=".$section); exit();
}

$section = $_GET['section'] ?? 'overview';

$stat_doctors       = (int)$con->query("SELECT COUNT(*) c FROM doctor")->fetch_assoc()['c'];
$stat_active        = (int)$con->query("SELECT COUNT(*) c FROM doctor WHERE status='active'")->fetch_assoc()['c'];
$stat_pending       = $stat_doctors - $stat_active;
$stat_consultations = (int)$con->query("SELECT COUNT(*) c FROM consultations")->fetch_assoc()['c'];
$stat_appointments  = (int)$con->query("SELECT COUNT(*) c FROM appointments")->fetch_assoc()['c'];
$stat_patients      = (int)$con->query("SELECT COUNT(*) c FROM patients")->fetch_assoc()['c'];

$doctors       = $section==='doctors'       ? $con->query("SELECT * FROM doctor ORDER BY docid DESC") : null;
$appointments  = $section==='appointments'  ? $con->query("SELECT a.*, d.docname FROM appointments a LEFT JOIN doctor d ON a.doctor_id=d.docid ORDER BY a.app_date DESC LIMIT 100") : null;
$consultations = $section==='consultations' ? $con->query("SELECT c.*, d.docname, a.patient_name FROM consultations c LEFT JOIN doctor d ON c.doctor_id=d.docid LEFT JOIN appointments a ON c.appointment_id=a.id ORDER BY c.date_consultation DESC LIMIT 100") : null;
$patients      = $section==='patients'      ? $con->query("SELECT * FROM patients ORDER BY created_at DESC") : null;

$admin_name    = htmlspecialchars($_SESSION['admin_name'] ?? 'Admin');
$admin_initial = strtoupper(substr($_SESSION['admin_name'] ?? 'A', 0, 1));
$section_labels = ['overview'=>"Vue d'ensemble",'doctors'=>'Médecins','patients'=>'Patients','appointments'=>'Rendez-vous','consultations'=>'Consultations'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin · PsySpace</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{
  --bg:#f4f5f7;--surface:#fff;--border:#e5e7eb;--border2:#f0f1f3;
  --tx:#111827;--tx2:#6b7280;--tx3:#9ca3af;
  --ok:#059669;--ok-l:#ecfdf5;--wa:#d97706;--wa-l:#fffbeb;
  --er:#dc2626;--er-l:#fef2f2;--in:#2563eb;--in-l:#eff6ff;
  --sb:240px;--r:10px;
  --sh:0 1px 3px rgba(0,0,0,.07),0 1px 2px rgba(0,0,0,.04);
  --sh2:0 4px 12px rgba(0,0,0,.08),0 2px 4px rgba(0,0,0,.04);
}
html,body{height:100%;font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--tx);font-size:13.5px;-webkit-font-smoothing:antialiased;}
a{text-decoration:none;color:inherit;}
::-webkit-scrollbar{width:5px;height:5px;}
::-webkit-scrollbar-thumb{background:var(--border);border-radius:4px;}

.layout{display:flex;min-height:100vh;}
.main{margin-left:var(--sb);flex:1;display:flex;flex-direction:column;min-width:0;}

/* SIDEBAR */
.sidebar{width:var(--sb);flex-shrink:0;background:var(--surface);border-right:1px solid var(--border);display:flex;flex-direction:column;position:fixed;top:0;left:0;height:100vh;z-index:100;}
.sb-brand{padding:18px 16px 14px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
.sb-logo{font-size:15px;font-weight:700;letter-spacing:-.02em;}
.sb-logo span{color:var(--in);}
.sb-tag{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--er);background:var(--er-l);padding:2px 7px;border-radius:4px;border:1px solid rgba(220,38,38,.15);}
.sb-nav{flex:1;padding:10px 8px;overflow-y:auto;display:flex;flex-direction:column;gap:1px;}
.sb-grp{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.15em;color:var(--tx3);padding:10px 10px 4px;}
.sb-item{display:flex;align-items:center;gap:8px;padding:8px 10px;border-radius:var(--r);font-size:12.5px;font-weight:500;color:var(--tx2);transition:all .12s;border:1px solid transparent;}
.sb-item:hover{background:var(--bg);color:var(--tx);}
.sb-item.on{background:var(--in-l);color:var(--in);border-color:rgba(37,99,235,.12);font-weight:600;}
.sb-item svg{width:15px;height:15px;flex-shrink:0;opacity:.8;}
.sb-cnt{margin-left:auto;font-size:10px;font-weight:700;background:var(--bg);color:var(--tx3);padding:1px 7px;border-radius:99px;border:1px solid var(--border);}
.sb-item.on .sb-cnt{background:rgba(37,99,235,.1);color:var(--in);border-color:rgba(37,99,235,.2);}
.sb-foot{padding:10px 8px;border-top:1px solid var(--border);}
.sb-user{display:flex;align-items:center;gap:8px;padding:10px;background:var(--bg);border-radius:var(--r);margin-bottom:6px;border:1px solid var(--border);}
.sb-av{width:30px;height:30px;border-radius:8px;background:var(--in);color:#fff;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0;}
.sb-uname{font-size:12px;font-weight:600;}
.sb-urole{font-size:10px;color:var(--tx3);}
.sb-out{display:flex;align-items:center;gap:7px;padding:8px 10px;border-radius:var(--r);font-size:12px;font-weight:500;color:var(--tx2);border:1px solid var(--border);transition:all .12s;}
.sb-out:hover{background:var(--er-l);color:var(--er);border-color:rgba(220,38,38,.2);}

/* TOPBAR */
.topbar{height:54px;display:flex;align-items:center;justify-content:space-between;padding:0 22px;background:var(--surface);border-bottom:1px solid var(--border);position:sticky;top:0;z-index:50;}
.tb-title{font-size:14px;font-weight:700;}
.tb-meta{font-size:10.5px;color:var(--tx3);margin-top:1px;}
.tb-right{display:flex;align-items:center;gap:10px;}
.tb-clock{font-family:'DM Mono',monospace;font-size:12px;font-weight:500;color:var(--tx2);background:var(--bg);padding:5px 12px;border-radius:8px;border:1px solid var(--border);}
.tb-alert{font-size:10px;font-weight:700;padding:4px 10px;border-radius:6px;background:var(--er-l);color:var(--er);border:1px solid rgba(220,38,38,.2);}

/* CONTENT */
.content{padding:20px 22px;flex:1;}

/* STATS */
.stats{display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin-bottom:20px;}
.sc{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);padding:14px 16px;box-shadow:var(--sh);transition:box-shadow .15s,transform .15s;}
.sc:hover{box-shadow:var(--sh2);transform:translateY(-1px);}
.sc-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;}
.sc-icon{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:14px;}
.sc-badge{font-size:9.5px;font-weight:700;padding:2px 7px;border-radius:5px;}
.sc-val{font-size:24px;font-weight:700;letter-spacing:-.02em;line-height:1;}
.sc-lbl{font-size:11px;color:var(--tx3);font-weight:500;margin-top:4px;}

/* CARD */
.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);box-shadow:var(--sh);overflow:hidden;margin-bottom:16px;}
.card:last-child{margin-bottom:0;}
.card-head{display:flex;align-items:center;justify-content:space-between;padding:13px 16px;border-bottom:1px solid var(--border);}
.ch-left{display:flex;align-items:center;gap:9px;}
.ch-dot{width:7px;height:7px;border-radius:50%;flex-shrink:0;}
.ch-title{font-size:13px;font-weight:700;}
.ch-cnt{font-size:10px;font-weight:700;background:var(--bg);color:var(--tx3);padding:2px 8px;border-radius:99px;border:1px solid var(--border);}
.ch-link{font-size:11px;font-weight:600;color:var(--in);}
.ch-link:hover{text-decoration:underline;}

/* TABLE */
.tbl-wrap{overflow-x:auto;}
table{width:100%;border-collapse:collapse;}
thead th{padding:9px 14px;text-align:left;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--tx3);background:var(--bg);white-space:nowrap;border-bottom:1px solid var(--border);}
tbody tr{border-bottom:1px solid var(--border2);transition:background .1s;}
tbody tr:last-child{border-bottom:none;}
tbody tr:hover{background:#fafbfc;}
td{padding:10px 14px;font-size:12.5px;color:var(--tx2);vertical-align:middle;}
td.name{color:var(--tx);font-weight:600;}
td.mono{font-family:'DM Mono',monospace;font-size:11.5px;}

/* BADGE */
.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:99px;font-size:10px;font-weight:700;white-space:nowrap;}
.b-ok{background:var(--ok-l);color:var(--ok);border:1px solid rgba(5,150,105,.2);}
.b-wa{background:var(--wa-l);color:var(--wa);border:1px solid rgba(217,119,6,.2);}
.b-er{background:var(--er-l);color:var(--er);border:1px solid rgba(220,38,38,.2);}
.b-in{background:var(--in-l);color:var(--in);border:1px solid rgba(37,99,235,.2);}
.b-n{background:var(--bg);color:var(--tx3);border:1px solid var(--border);}

/* BUTTONS */
.btn{display:inline-flex;align-items:center;gap:5px;padding:5px 11px;border-radius:7px;border:1px solid;font-size:11px;font-weight:600;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;transition:all .12s;}
.btn-ok{color:var(--ok);border-color:rgba(5,150,105,.25);background:var(--ok-l);}
.btn-ok:hover{background:#d1fae5;}
.btn-wa{color:var(--wa);border-color:rgba(217,119,6,.25);background:var(--wa-l);}
.btn-wa:hover{background:#fde68a50;}
.btn-er{color:var(--er);border-color:rgba(220,38,38,.25);background:var(--er-l);}
.btn-er:hover{background:#fee2e2;}
.btn-ghost{color:var(--tx2);border-color:var(--border);background:var(--surface);}
.btn-ghost:hover{background:var(--bg);color:var(--tx);}

/* OV GRID */
.ov-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.ov-row{display:flex;align-items:center;justify-content:space-between;padding:9px 14px;border-bottom:1px solid var(--border2);transition:background .1s;}
.ov-row:last-child{border-bottom:none;}
.ov-row:hover{background:var(--bg);}
.ov-av{width:28px;height:28px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0;}

/* EMPTY */
.empty{padding:48px 20px;text-align:center;color:var(--tx3);}
.empty-icon{font-size:24px;margin-bottom:8px;opacity:.4;}
.empty p{font-size:12px;}

/* MODAL */
.overlay{position:fixed;inset:0;background:rgba(0,0,0,.35);backdrop-filter:blur(2px);display:flex;align-items:center;justify-content:center;z-index:999;opacity:0;pointer-events:none;transition:opacity .2s;}
.overlay.show{opacity:1;pointer-events:all;}
.modal{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:22px 24px;max-width:370px;width:90%;box-shadow:0 20px 50px rgba(0,0,0,.14);transform:translateY(8px) scale(.98);transition:transform .2s;}
.overlay.show .modal{transform:translateY(0) scale(1);}
.modal-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:17px;margin-bottom:12px;}
.modal h3{font-size:14px;font-weight:700;margin-bottom:5px;}
.modal p{font-size:12.5px;color:var(--tx2);line-height:1.6;margin-bottom:18px;}
.modal-btns{display:flex;gap:8px;justify-content:flex-end;}

@media(max-width:900px){.stats{grid-template-columns:repeat(2,1fr);}.ov-grid{grid-template-columns:1fr;}}
</style>
</head>
<body>
<div class="layout">

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sb-brand">
    <div class="sb-logo">Psy<span>Space</span></div>
    <div class="sb-tag">Admin</div>
  </div>
  <nav class="sb-nav">
    <div class="sb-grp">Dashboard</div>
    <a href="?section=overview" class="sb-item <?= $section==='overview'?'on':'' ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
      Vue d'ensemble
    </a>
    <div class="sb-grp">Gestion</div>
    <a href="?section=doctors" class="sb-item <?= $section==='doctors'?'on':'' ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
      Médecins <span class="sb-cnt"><?= $stat_doctors ?></span>
    </a>
    <a href="?section=patients" class="sb-item <?= $section==='patients'?'on':'' ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
      Patients <span class="sb-cnt"><?= $stat_patients ?></span>
    </a>
    <a href="?section=appointments" class="sb-item <?= $section==='appointments'?'on':'' ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
      Rendez-vous <span class="sb-cnt"><?= $stat_appointments ?></span>
    </a>
    <a href="?section=consultations" class="sb-item <?= $section==='consultations'?'on':'' ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
      Consultations <span class="sb-cnt"><?= $stat_consultations ?></span>
    </a>
  </nav>
  <div class="sb-foot">
    <div class="sb-user">
      <div class="sb-av"><?= $admin_initial ?></div>
      <div><div class="sb-uname"><?= $admin_name ?></div><div class="sb-urole">Administrateur</div></div>
    </div>
    <a href="logout.php" class="sb-out">
      <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
      Déconnexion
    </a>
  </div>
</aside>

<!-- MAIN -->
<div class="main">
  <div class="topbar">
    <div>
      <div class="tb-title"><?= $section_labels[$section] ?? 'Dashboard' ?></div>
      <div class="tb-meta">PsySpace · Panneau d'administration</div>
    </div>
    <div class="tb-right">
      <?php if($stat_pending>0): ?><div class="tb-alert">⚠ <?= $stat_pending ?> en attente</div><?php endif; ?>
      <div class="tb-clock" id="clock">--:--:--</div>
    </div>
  </div>

  <div class="content">

    <!-- Stats -->
    <div class="stats">
      <div class="sc">
        <div class="sc-top">
          <div class="sc-icon" style="background:var(--in-l);">👨‍⚕️</div>
          <span class="sc-badge" style="background:var(--in-l);color:var(--in);"><?= $stat_active ?> actifs</span>
        </div>
        <div class="sc-val"><?= $stat_doctors ?></div>
        <div class="sc-lbl">Médecins</div>
      </div>
      <div class="sc">
        <div class="sc-top"><div class="sc-icon" style="background:var(--ok-l);">🧑</div></div>
        <div class="sc-val"><?= $stat_patients ?></div>
        <div class="sc-lbl">Patients</div>
      </div>
      <div class="sc">
        <div class="sc-top"><div class="sc-icon" style="background:var(--wa-l);">📅</div></div>
        <div class="sc-val"><?= $stat_appointments ?></div>
        <div class="sc-lbl">Rendez-vous</div>
      </div>
      <div class="sc">
        <div class="sc-top"><div class="sc-icon" style="background:var(--in-l);">📋</div></div>
        <div class="sc-val"><?= $stat_consultations ?></div>
        <div class="sc-lbl">Consultations</div>
      </div>
      <div class="sc">
        <div class="sc-top">
          <div class="sc-icon" style="background:var(--er-l);">⏳</div>
          <?php if($stat_pending>0): ?><span class="sc-badge" style="background:var(--er-l);color:var(--er);">À activer</span><?php endif; ?>
        </div>
        <div class="sc-val" style="<?= $stat_pending>0?'color:var(--er)':'' ?>"><?= $stat_pending ?></div>
        <div class="sc-lbl">En attente</div>
      </div>
    </div>

<?php if($section==='overview'): ?>
    <div class="ov-grid">
      <div class="card">
        <div class="card-head">
          <div class="ch-left"><div class="ch-dot" style="background:var(--in);"></div><div class="ch-title">Médecins récents</div></div>
          <a href="?section=doctors" class="ch-link">Voir tout →</a>
        </div>
        <?php $ld=$con->query("SELECT * FROM doctor ORDER BY docid DESC LIMIT 6");
        if($ld && $ld->num_rows): while($d=$ld->fetch_assoc()): ?>
        <div class="ov-row">
          <div style="display:flex;align-items:center;gap:8px;">
            <?php if(!empty($d['photo']) && file_exists('../'.$d['photo'])): ?>
            <img src="../<?= htmlspecialchars($d['photo']) ?>" style="width:28px;height:28px;border-radius:7px;object-fit:cover;" alt="">
            <?php else: ?>
            <div class="ov-av" style="background:var(--in-l);color:var(--in);"><?= strtoupper(substr($d['docname'],0,1)) ?></div>
            <?php endif; ?>
            <div>
              <div style="font-size:12.5px;font-weight:600;color:var(--tx);"><?= htmlspecialchars($d['docname']) ?></div>
              <div style="font-size:10.5px;color:var(--tx3);"><?= htmlspecialchars($d['specialty']??$d['docemail']) ?></div>
            </div>
          </div>
          <span class="badge <?= $d['status']==='active'?'b-ok':'b-wa' ?>"><?= $d['status']==='active'?'● Actif':'○ Attente' ?></span>
        </div>
        <?php endwhile; else: ?><div class="empty"><div class="empty-icon">👨‍⚕️</div><p>Aucun médecin</p></div><?php endif; ?>
      </div>

      <div class="card">
        <div class="card-head">
          <div class="ch-left"><div class="ch-dot" style="background:var(--ok);"></div><div class="ch-title">Dernières consultations</div></div>
          <a href="?section=consultations" class="ch-link">Voir tout →</a>
        </div>
        <?php $lc=$con->query("SELECT c.*,d.docname,a.patient_name FROM consultations c LEFT JOIN doctor d ON c.doctor_id=d.docid LEFT JOIN appointments a ON c.appointment_id=a.id ORDER BY c.date_consultation DESC LIMIT 6");
        if($lc && $lc->num_rows): while($c=$lc->fetch_assoc()): ?>
        <div class="ov-row">
          <div>
            <div style="font-size:12.5px;font-weight:600;color:var(--tx);"><?= htmlspecialchars($c['patient_name']??'Patient') ?></div>
            <div style="font-size:10.5px;color:var(--tx3);">Dr. <?= htmlspecialchars($c['docname']??'—') ?> · <?= date('d/m/Y',strtotime($c['date_consultation'])) ?></div>
          </div>
          <span class="badge b-n"><?= $c['duree_minutes']>0?$c['duree_minutes'].' min':'—' ?></span>
        </div>
        <?php endwhile; else: ?><div class="empty"><div class="empty-icon">📋</div><p>Aucune consultation</p></div><?php endif; ?>
      </div>

      <div class="card" style="grid-column:1/-1;">
        <div class="card-head">
          <div class="ch-left"><div class="ch-dot" style="background:var(--wa);"></div><div class="ch-title">Rendez-vous récents</div></div>
          <a href="?section=appointments" class="ch-link">Voir tout →</a>
        </div>
        <?php $la=$con->query("SELECT a.*,d.docname FROM appointments a LEFT JOIN doctor d ON a.doctor_id=d.docid ORDER BY a.app_date DESC LIMIT 5");
        if($la && $la->num_rows): ?>
        <div class="tbl-wrap"><table>
          <thead><tr><th>Patient</th><th>Médecin</th><th>Date</th><th>Type</th></tr></thead>
          <tbody>
          <?php while($a=$la->fetch_assoc()): ?>
          <tr>
            <td class="name"><?= htmlspecialchars($a['patient_name']) ?></td>
            <td>Dr. <?= htmlspecialchars($a['docname']??'—') ?></td>
            <td class="mono"><?= date('d/m/Y H:i',strtotime($a['app_date'])) ?></td>
            <td><span class="badge b-n"><?= htmlspecialchars($a['app_type']??'Consultation') ?></span></td>
          </tr>
          <?php endwhile; ?>
          </tbody>
        </table></div>
        <?php else: ?><div class="empty"><div class="empty-icon">📅</div><p>Aucun rendez-vous</p></div><?php endif; ?>
      </div>
    </div>

<?php elseif($section==='doctors'): ?>
    <div class="card">
      <div class="card-head">
        <div class="ch-left"><div class="ch-dot" style="background:var(--in);"></div><div class="ch-title">Médecins inscrits</div><span class="ch-cnt"><?= $stat_doctors ?></span></div>
      </div>
      <div class="tbl-wrap"><table>
        <thead><tr><th>#</th><th>Médecin</th><th>Email</th><th>Statut</th><th>Actions</th></tr></thead>
        <tbody>
        <?php if($doctors && $doctors->num_rows): while($d=$doctors->fetch_assoc()): ?>
        <tr>
          <td class="mono" style="color:var(--tx3);"><?= $d['docid'] ?></td>
          <td class="name">
            <div style="display:flex;align-items:center;gap:8px;">
              <div class="ov-av" style="background:var(--in-l);color:var(--in);"><?= strtoupper(substr($d['docname'],0,1)) ?></div>
              <?= htmlspecialchars($d['docname']) ?>
            </div>
          </td>
          <td><?= htmlspecialchars($d['docemail']) ?></td>
          <td><span class="badge <?= $d['status']==='active'?'b-ok':'b-wa' ?>"><?= $d['status']==='active'?'● Actif':'○ Attente' ?></span></td>
          <td>
            <div style="display:flex;gap:6px;">
              <?php if($d['status']==='active'): ?>
              <button class="btn btn-wa" onclick="openModal('Désactiver ?','Dr. <?= addslashes(htmlspecialchars($d['docname'])) ?> ne pourra plus se connecter.','toggle_doctor',<?= $d['docid'] ?>,'pending','doctors')">Désactiver</button>
              <?php else: ?>
              <button class="btn btn-ok" onclick="openModal('Activer ?','Dr. <?= addslashes(htmlspecialchars($d['docname'])) ?> pourra se connecter.','toggle_doctor',<?= $d['docid'] ?>,'active','doctors')">Activer</button>
              <?php endif; ?>
              <button class="btn btn-er" onclick="openModal('Supprimer ce médecin ?','Action irréversible. Toutes les données liées seront perdues.','delete_doctor',<?= $d['docid'] ?>,null,'doctors')">Supprimer</button>
            </div>
          </td>
        </tr>
        <?php endwhile; else: ?><tr><td colspan="5"><div class="empty"><div class="empty-icon">👨‍⚕️</div><p>Aucun médecin</p></div></td></tr><?php endif; ?>
        </tbody>
      </table></div>
    </div>

<?php elseif($section==='patients'): ?>
    <div class="card">
      <div class="card-head">
        <div class="ch-left"><div class="ch-dot" style="background:var(--ok);"></div><div class="ch-title">Patients</div><span class="ch-cnt"><?= $stat_patients ?></span></div>
      </div>
      <div class="tbl-wrap"><table>
        <thead><tr><th>#</th><th>Nom</th><th>Téléphone</th><th>Naissance</th><th>Inscrit le</th><th>Action</th></tr></thead>
        <tbody>
        <?php if($patients && $patients->num_rows): while($p=$patients->fetch_assoc()): ?>
        <tr>
          <td class="mono" style="color:var(--tx3);"><?= $p['id'] ?></td>
          <td class="name"><?= htmlspecialchars($p['pname']) ?></td>
          <td><?= htmlspecialchars($p['pphone']??'—') ?></td>
          <td class="mono"><?= $p['pdob']?date('d/m/Y',strtotime($p['pdob'])):'—' ?></td>
          <td class="mono" style="color:var(--tx3);"><?= date('d/m/Y',strtotime($p['created_at'])) ?></td>
          <td><button class="btn btn-er" onclick="openModal('Supprimer ce patient ?','<?= addslashes(htmlspecialchars($p['pname'])) ?> sera supprimé définitivement.','delete_patient',<?= $p['id'] ?>,null,'patients')">Supprimer</button></td>
        </tr>
        <?php endwhile; else: ?><tr><td colspan="6"><div class="empty"><div class="empty-icon">🧑</div><p>Aucun patient</p></div></td></tr><?php endif; ?>
        </tbody>
      </table></div>
    </div>

<?php elseif($section==='appointments'): ?>
    <div class="card">
      <div class="card-head">
        <div class="ch-left"><div class="ch-dot" style="background:var(--wa);"></div><div class="ch-title">Rendez-vous</div><span class="ch-cnt"><?= $stat_appointments ?></span></div>
      </div>
      <div class="tbl-wrap"><table>
        <thead><tr><th>#</th><th>Patient</th><th>Téléphone</th><th>Médecin</th><th>Date</th><th>Type</th><th>Action</th></tr></thead>
        <tbody>
        <?php if($appointments && $appointments->num_rows): while($a=$appointments->fetch_assoc()): ?>
        <tr>
          <td class="mono" style="color:var(--tx3);"><?= $a['id'] ?></td>
          <td class="name"><?= htmlspecialchars($a['patient_name']) ?></td>
          <td><?= htmlspecialchars($a['patient_phone']??'—') ?></td>
          <td>Dr. <?= htmlspecialchars($a['docname']??'—') ?></td>
          <td class="mono"><?= date('d/m/Y H:i',strtotime($a['app_date'])) ?></td>
          <td><span class="badge b-n"><?= htmlspecialchars($a['app_type']??'Consultation') ?></span></td>
          <td><button class="btn btn-er" onclick="openModal('Supprimer ce RDV ?','Le rendez-vous du <?= date('d/m/Y',strtotime($a['app_date'])) ?> sera supprimé.','delete_appointment',<?= $a['id'] ?>,null,'appointments')">Supprimer</button></td>
        </tr>
        <?php endwhile; else: ?><tr><td colspan="7"><div class="empty"><div class="empty-icon">📅</div><p>Aucun rendez-vous</p></div></td></tr><?php endif; ?>
        </tbody>
      </table></div>
    </div>

<?php elseif($section==='consultations'): ?>
    <div class="card">
      <div class="card-head">
        <div class="ch-left"><div class="ch-dot" style="background:#7c3aed;"></div><div class="ch-title">Consultations archivées</div><span class="ch-cnt"><?= $stat_consultations ?></span></div>
      </div>
      <div class="tbl-wrap"><table>
        <thead><tr><th>#</th><th>Patient</th><th>Médecin</th><th>Date</th><th>Durée</th><th>Résumé IA</th><th>Action</th></tr></thead>
        <tbody>
        <?php if($consultations && $consultations->num_rows): while($c=$consultations->fetch_assoc()): ?>
        <tr>
          <td class="mono" style="color:var(--tx3);"><?= $c['id'] ?></td>
          <td class="name"><?= htmlspecialchars($c['patient_name']??'Non lié') ?></td>
          <td>Dr. <?= htmlspecialchars($c['docname']??'—') ?></td>
          <td class="mono"><?= date('d/m/Y H:i',strtotime($c['date_consultation'])) ?></td>
          <td><span class="badge b-n"><?= $c['duree_minutes']>0?$c['duree_minutes'].' min':'—' ?></span></td>
          <td><?= !empty($c['resume_ia'])?'<span class="badge b-ok">✓ Généré</span>':'<span class="badge b-n">—</span>' ?></td>
          <td><button class="btn btn-er" onclick="openModal('Supprimer cette consultation ?','Transcription et résumé IA perdus définitivement.','delete_consultation',<?= $c['id'] ?>,null,'consultations')">Supprimer</button></td>
        </tr>
        <?php endwhile; else: ?><tr><td colspan="7"><div class="empty"><div class="empty-icon">📋</div><p>Aucune consultation</p></div></td></tr><?php endif; ?>
        </tbody>
      </table></div>
    </div>
<?php endif; ?>

  </div>
</div>
</div>

<!-- MODAL -->
<div class="overlay" id="ov" onclick="if(event.target===this)closeModal()">
  <div class="modal">
    <div class="modal-icon" id="m-icon" style="background:var(--er-l);">🗑️</div>
    <h3 id="m-title"></h3>
    <p  id="m-msg"></p>
    <div class="modal-btns">
      <button class="btn btn-ghost" onclick="closeModal()">Annuler</button>
      <form id="m-form" method="POST" style="display:inline;">
        <input type="hidden" name="action"     id="m-action">
        <input type="hidden" name="rid"        id="m-rid">
        <input type="hidden" name="docid"      id="m-docid">
        <input type="hidden" name="new_status" id="m-ns">
        <input type="hidden" name="section"    id="m-section">
        <button type="submit" class="btn btn-er" id="m-confirm">Confirmer</button>
      </form>
    </div>
  </div>
</div>

<script>
(function tick(){
  var n=new Date(),el=document.getElementById('clock');
  if(el)el.textContent=[n.getHours(),n.getMinutes(),n.getSeconds()].map(function(x){return(''+x).padStart(2,'0')}).join(':');
  setTimeout(tick,1000);
})();

function openModal(title,msg,action,rid,newStatus,section){
  document.getElementById('m-title').textContent=title;
  document.getElementById('m-msg').textContent=msg;
  document.getElementById('m-action').value=action;
  document.getElementById('m-rid').value=rid||'';
  document.getElementById('m-docid').value=action==='toggle_doctor'?rid:'';
  document.getElementById('m-ns').value=newStatus||'';
  document.getElementById('m-section').value=section;
  var btn=document.getElementById('m-confirm'),icon=document.getElementById('m-icon');
  if(action==='toggle_doctor'&&newStatus==='active'){
    btn.className='btn btn-ok';btn.textContent='Activer';
    icon.textContent='✓';icon.style.background='var(--ok-l)';
  }else if(action==='toggle_doctor'){
    btn.className='btn btn-wa';btn.textContent='Désactiver';
    icon.textContent='⚠';icon.style.background='var(--wa-l)';
  }else{
    btn.className='btn btn-er';btn.textContent='Supprimer';
    icon.textContent='🗑️';icon.style.background='var(--er-l)';
  }
  document.getElementById('ov').classList.add('show');
}
function closeModal(){document.getElementById('ov').classList.remove('show');}
</script>
</body>
</html>