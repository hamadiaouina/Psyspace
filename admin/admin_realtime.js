/**
 * PsySpace Admin — Realtime polling
 * À inclure APRÈS le </body> de admin.php
 * Dépendance : window.PSYADMIN_CSRF doit être défini dans admin.php
 * Exemple : <script>window.PSYADMIN_CSRF = "<?= $csrf ?>";</script>
 */
(function () {
  'use strict';

  var INTERVAL   = 12000; // 12 secondes
  var POLL_URL   = 'admin_poll.php';
  var csrf       = window.PSYADMIN_CSRF || '';
  var timer      = null;
  var lastCritId = 0;   // dernier ID critique connu
  var lastApptId = 0;   // dernier RDV connu
  var lastConsId = 0;   // dernière consultation connue
  var firstRun   = true;

  // ── Indicateur pulsant dans la topbar ──────────────────────────────────────
  var dot = document.createElement('div');
  dot.id  = 'rt-dot';
  dot.style.cssText = [
    'width:6px','height:6px','border-radius:50%',
    'background:var(--ok)','flex-shrink:0',
    'transition:background .3s',
    'animation:rt-pulse 2.4s cubic-bezier(.4,0,.6,1) infinite'
  ].join(';');
  var styleEl = document.createElement('style');
  styleEl.textContent = [
    '@keyframes rt-pulse{0%,100%{opacity:1}50%{opacity:.25}}',
    '#rt-toast{position:fixed;bottom:16px;right:16px;z-index:9999;',
      'display:flex;flex-direction:column;gap:6px;pointer-events:none;}',
    '.rt-t{background:var(--surface);border:1px solid var(--line);border-radius:var(--r2);',
      'padding:9px 13px;font-size:12px;color:var(--tx);',
      'box-shadow:var(--sh2);pointer-events:all;',
      'display:flex;align-items:center;gap:8px;',
      'opacity:0;transform:translateY(8px);',
      'transition:opacity .22s,transform .22s;}',
    '.rt-t.show{opacity:1;transform:none;}',
    '.rt-t.er{border-color:var(--er-b);background:var(--er-l);color:var(--er);}',
    '.rt-t.wa{border-color:var(--wa-b);background:var(--wa-l);color:var(--wa);}',
    '.rt-t.ok{border-color:var(--ok-b);background:var(--ok-l);color:var(--ok);}'
  ].join('');
  document.head.appendChild(styleEl);

  // Insérer le dot dans la topbar à droite
  var tbr = document.querySelector('.tb-r');
  if (tbr) {
    var wrap = document.createElement('div');
    wrap.style.cssText = 'display:flex;align-items:center;gap:5px;';
    var lbl  = document.createElement('span');
    lbl.style.cssText = 'font-size:10px;color:var(--tx3);font-family:\'IBM Plex Mono\',monospace;';
    lbl.id = 'rt-lbl';
    lbl.textContent = 'Live';
    wrap.appendChild(dot);
    wrap.appendChild(lbl);
    tbr.insertBefore(wrap, tbr.firstChild);
  }

  // Toast container
  var toastWrap = document.createElement('div');
  toastWrap.id  = 'rt-toast';
  document.body.appendChild(toastWrap);

  function toast(msg, type, duration) {
    type     = type     || 'ok';
    duration = duration || 4000;
    var t    = document.createElement('div');
    t.className = 'rt-t ' + type;
    t.textContent = msg;
    toastWrap.appendChild(t);
    requestAnimationFrame(function () {
      requestAnimationFrame(function () { t.classList.add('show'); });
    });
    setTimeout(function () {
      t.classList.remove('show');
      setTimeout(function () { t.remove(); }, 300);
    }, duration);
  }

  // ── Helpers DOM ────────────────────────────────────────────────────────────
  function setText(sel, val) {
    var el = document.querySelector(sel);
    if (el && el.textContent !== String(val)) el.textContent = val;
  }

  function setClass(sel, cls, on) {
    var el = document.querySelector(sel);
    if (!el) return;
    if (on) el.classList.add(cls);
    else     el.classList.remove(cls);
  }

  // ── Mise à jour du DOM ─────────────────────────────────────────────────────
  function applyUpdate(d) {
    var s = d.stats;

    // Stat strip
    updateStatCell(0, s.doctors,       s.active + ' actifs');
    updateStatCell(1, s.patients,      null);
    updateStatCell(2, s.appointments,  null);
    updateStatCell(3, s.consultations, null);
    updateStatCell(4, s.pending,       s.pending > 0 ? 'Activation requise' : null, s.pending > 0 ? 'c-er' : '');
    updateStatCell(5, s.critical,      s.critical > 0 ? 'Risque critique' : null, s.critical > 0 ? 'c-er' : '');

    // Topbar pills
    updateTopbarPills(s);

    // Sidebar count médecins en attente
    updateSidebarCounts(s);

    // ── Détection de nouveautés ──
    if (!firstRun) {

      // Nouvelle alerte critique
      if (d.last_crit_id && d.last_crit_id > lastCritId) {
        toast('Nouvelle alerte critique détectée — vérifiez la section Alertes', 'er', 7000);
        flashStatCell(5); // cell critique
        // Mettre à jour le banner si on n'est pas sur la page alertes
        showCritBanner(s.critical);
      }

      // Nouvelle consultation archivée
      if (d.last_consult && d.last_consult.id > lastConsId && lastConsId > 0) {
        var cn = d.last_consult;
        toast('Consultation archivée — ' + (cn.patient_name || '?') + ' · Dr. ' + (cn.docname || '?'), 'ok', 5000);
      }

      // Nouveau rendez-vous
      if (d.last_appt && d.last_appt.id > lastApptId && lastApptId > 0) {
        var an = d.last_appt;
        toast('Nouveau rendez-vous — ' + (an.patient_name || '?') + ' · Dr. ' + (an.docname || '?'), 'ok', 5000);
      }

      // Nouveau médecin en attente
      if (s.pending > 0 && d.pending_doctors && d.pending_doctors.length) {
        // On laisse juste le badge parler, pas de toast intrusif sauf si +1
      }
    }

    // Mémoriser les IDs connus
    if (d.last_crit_id)              lastCritId = d.last_crit_id;
    if (d.last_consult && d.last_consult.id) lastConsId = d.last_consult.id;
    if (d.last_appt    && d.last_appt.id)    lastApptId = d.last_appt.id;

    firstRun = false;
  }

  // ── Stat cells ─────────────────────────────────────────────────────────────
  function updateStatCell(idx, val, hint, colorClass) {
    var cells = document.querySelectorAll('.stat-cell');
    if (!cells[idx]) return;
    var valEl  = cells[idx].querySelector('.stat-val');
    var hintEl = cells[idx].querySelector('.stat-hint');
    if (valEl) {
      if (valEl.textContent !== String(val)) {
        valEl.textContent = val;
        // Flash léger
        valEl.style.transition = 'color .12s';
        valEl.style.color = 'var(--ac)';
        setTimeout(function () { valEl.style.color = ''; }, 600);
      }
      // Appliquer la classe couleur si spécifiée
      if (colorClass !== undefined) {
        valEl.className = 'stat-val' + (colorClass ? ' ' + colorClass : '');
      }
    }
    if (hintEl && hint !== null && hint !== undefined) {
      hintEl.textContent = hint;
      hintEl.style.color = colorClass ? 'var(--er)' : '';
    }
  }

  function flashStatCell(idx) {
    var cells = document.querySelectorAll('.stat-cell');
    if (!cells[idx]) return;
    cells[idx].style.background = 'var(--er-l)';
    setTimeout(function () { cells[idx].style.background = ''; }, 1800);
  }

  // ── Topbar pills ───────────────────────────────────────────────────────────
  function updateTopbarPills(s) {
    var tbr = document.querySelector('.tb-r');
    if (!tbr) return;

    // Pill alertes critiques
    var critPill = document.getElementById('rt-crit-pill');
    if (s.critical > 0) {
      if (!critPill) {
        critPill    = document.createElement('a');
        critPill.id = 'rt-crit-pill';
        critPill.href      = '?section=alerts';
        critPill.className = 'tb-pill tb-pill-er';
        tbr.insertBefore(critPill, tbr.children[1]);
      }
      critPill.textContent = '⚠ ' + s.critical + ' critique' + (s.critical > 1 ? 's' : '');
    } else if (critPill) {
      critPill.remove();
    }

    // Pill en attente
    var pendPill = document.getElementById('rt-pend-pill');
    if (s.pending > 0) {
      if (!pendPill) {
        pendPill    = document.createElement('div');
        pendPill.id = 'rt-pend-pill';
        pendPill.className = 'tb-pill tb-pill-er';
        tbr.insertBefore(pendPill, tbr.children[1]);
      }
      pendPill.textContent = s.pending + ' en attente';
    } else if (pendPill) {
      pendPill.remove();
    }
  }

  // ── Sidebar counts ─────────────────────────────────────────────────────────
  function updateSidebarCounts(s) {
    var map = {
      'doctors':       s.doctors,
      'patients':      s.patients,
      'appointments':  s.appointments,
      'consultations': s.consultations,
      'alerts':        s.critical,
    };
    document.querySelectorAll('.sb-lnk').forEach(function (link) {
      var href    = link.getAttribute('href') || '';
      var section = href.replace('?section=', '');
      if (map[section] !== undefined) {
        var cnt = link.querySelector('.sb-cnt');
        if (cnt) {
          cnt.textContent = map[section];
          cnt.classList.toggle('alert', section === 'alerts' && s.critical > 0);
        }
      }
    });
  }

  // ── Banner critique ────────────────────────────────────────────────────────
  function showCritBanner(count) {
    var existing = document.querySelector('.alert-banner');
    if (existing) {
      var strong = existing.querySelector('strong');
      if (strong) strong.textContent = count + ' séance' + (count > 1 ? 's' : '') + ' à risque critique';
      return;
    }
    var content  = document.querySelector('.content');
    if (!content) return;
    var banner   = document.createElement('div');
    banner.className = 'alert-banner';
    banner.innerHTML = [
      '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2">',
        '<path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>',
        '<line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
      '</svg>',
      '<strong>' + count + ' séance' + (count > 1 ? 's' : '') + ' à risque critique</strong> —',
      '<a href="?section=alerts" style="font-weight:600;text-decoration:underline;color:var(--er);">Voir les alertes →</a>'
    ].join(' ');
    content.insertBefore(banner, content.firstChild);
  }

  // ── Dot status ─────────────────────────────────────────────────────────────
  function setDotStatus(ok) {
    if (!dot) return;
    dot.style.background = ok ? 'var(--ok)' : 'var(--er)';
    var lbl = document.getElementById('rt-lbl');
    if (lbl) lbl.textContent = ok ? 'Live' : 'Hors ligne';
  }

  // ── Fetch ──────────────────────────────────────────────────────────────────
  function poll() {
    // Pause si onglet caché (économie de requêtes)
    if (document.hidden) return;

    fetch(POLL_URL, {
      method:  'GET',
      headers: { 'X-CSRF-Token': csrf, 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
    .then(function (r) {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    })
    .then(function (data) {
      if (data.error) throw new Error(data.error);
      setDotStatus(true);
      applyUpdate(data);
    })
    .catch(function (err) {
      setDotStatus(false);
      console.warn('[PsySpace RT]', err.message);
    });
  }

  // ── Start ──────────────────────────────────────────────────────────────────
  function start() {
    poll(); // premier appel immédiat
    timer = setInterval(poll, INTERVAL);
  }

  function stop() {
    clearInterval(timer);
    timer = null;
  }

  // Pause quand onglet perdu, reprise au focus
  document.addEventListener('visibilitychange', function () {
    if (document.hidden) stop();
    else start();
  });

  window.addEventListener('focus', function () {
    if (!timer) start();
  });

  // Lancement
  start();

})();