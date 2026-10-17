const B = window.BOOT, ME = B.me, $ = (s, r = document) => r.querySelector(s);
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const BAD = { green: '✔ Green', amber: '▲ Amber', red: '✖ Red' };
const badge = s => s ? `<span class="badge ${s}">${BAD[s] || s}</span>` : '<span class="badge gray">—</span>';
const fmt = t => t ? t.replace('T', ' ').slice(0, 16) : '—';
const ROLE = { admin: 'Administrator', lecturer: 'Lecturer', hod: 'HOD / Dean' };

async function api(a, body, qs = '') {
  const o = body ? { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF': B.csrf }, body: JSON.stringify(body) } : {};
  const r = await fetch('api.php?a=' + a + qs, o);
  const j = await r.json().catch(() => ({ error: 'Unexpected server response' }));
  if (r.status === 401) location.replace('login.php' + (j.expired ? '?expired=1' : ''));
  if (!r.ok) throw new Error(j.error || 'Request failed');
  return j;
}
function toast(m, bad) { const t = $('#toast'); t.textContent = m; t.className = 'toast show' + (bad ? ' bad' : ''); clearTimeout(t._t); t._t = setTimeout(() => t.className = 'toast', 3500); }
const guard = fn => async (...a) => { try { await fn(...a); } catch (e) { toast(e.message, true); } };
function modal(html, foot = '') { const m = $('#modal'); m.innerHTML = `<div class="modal-card">${html}${foot ? `<div class="mf">${foot}</div>` : ''}</div>`; m.classList.remove('hidden'); m.onclick = e => { if (e.target === m) closeModal(); }; }
function closeModal() { $('#modal').classList.add('hidden'); }
const mhead = t => `<div class="mh"><h3>${t}</h3><button class="btn btn-secondary btn-sm" onclick="closeModal()">✕</button></div>`;

/* ---------- navigation ---------- */
const NAV = {
  lecturer: [['dashboard', 'Dashboard', '🏠'], ['modules', 'Modules & Students', '📚'], ['attendance', 'Attendance', '✅'], ['gradebook', 'Gradebook', '📝']],
  hod: [['dashboard', 'Dashboard', '🏠'], ['modules', 'Modules & Students', '📚'], ['report', 'Department Report', '📄']],
  admin: [['dashboard', 'Overview', '🏠'], ['users', 'Users', '👥'], ['students', 'Students & Registration', '🎓'], ['faculties', 'Faculties & Departments', '🏛️'], ['modmgmt', 'Modules', '📘'], ['settings', 'Rule Settings', '⚙️'], ['audit', 'Audit Log', '🧾'], ['usage', 'Usage Reports', '📈']]
};
let current = 'dashboard';
function buildNav() {
  $('#nav').innerHTML = NAV[ME.role].map(([k, l, i]) => `<button data-v="${k}"><span>${i}</span>${l}</button>`).join('');
  $('#nav').onclick = e => { const b = e.target.closest('button'); if (b) go(b.dataset.v); };
}
function menu(open) { $('#side').classList.toggle('open', open); $('#scrim').classList.toggle('open', open); $('#burger').classList.toggle('open', open); $('#burger').setAttribute('aria-expanded', open); }
$('#burger').onclick = () => menu(!$('#side').classList.contains('open'));
$('#scrim').onclick = () => menu(false);
function go(v) {
  current = v; menu(false);
  document.querySelectorAll('#nav button').forEach(b => b.classList.toggle('active', b.dataset.v === v));
  const item = NAV[ME.role].find(x => x[0] === v); $('#title').textContent = item[1];
  $('#view').innerHTML = '<div class="card muted">Loading…</div>';
  guard(V[v])();
}
$('#logout').onclick = async () => { try { await api('logout', {}); } catch (e) { } location.replace('login.php'); };

const V = {};
function countUp() {
  document.querySelectorAll('[data-count]').forEach(el => {
    const to = +el.dataset.count, t0 = performance.now();
    const step = t => { const p = Math.min(1, (t - t0) / 900); el.textContent = Math.round(to * (1 - Math.pow(1 - p, 3))); if (p < 1) requestAnimationFrame(step); };
    requestAnimationFrame(step);
  });
}
const kpi = (cls, ico, label, n, extra = '') => `<div class="card kpi k-${cls} fade"><span class="ico">${ico}</span><div>${label}</div><div class="num" data-count="${n}">0</div><div class="small">${extra}</div></div>`;
const greet = () => { const h = new Date().getHours(); return h < 12 ? 'Good morning' : h < 17 ? 'Good afternoon' : 'Good evening'; };

function donut(c) {
  const tot = c.green + c.amber + c.red || 1, R = 52, C = 2 * Math.PI * R; let off = 0;
  const seg = (v, col) => { const l = v / tot * C, s = `<circle r="${R}" cx="70" cy="70" fill="none" stroke="${col}" stroke-width="22" stroke-dasharray="${l} ${C - l}" stroke-dashoffset="${-off}" transform="rotate(-90 70 70)"><animate attributeName="stroke-dasharray" from="0 ${C}" to="${l} ${C - l}" dur=".9s" fill="freeze"/></circle>`; off += l; return s; };
  return `<svg width="160" height="160" viewBox="0 0 140 140"><circle r="${R}" cx="70" cy="70" fill="none" stroke="#eef1f5" stroke-width="22"/>${seg(c.green, '#2bb673')}${seg(c.amber, '#f2a900')}${seg(c.red, '#e5584a')}<text x="70" y="68" text-anchor="middle" font-size="26" font-weight="800" fill="#172033">${c.green + c.amber + c.red}</text><text x="70" y="86" text-anchor="middle" font-size="11" fill="#687386">students</text></svg>`;
}
function trendChart(t) {
  if (!t.length) return '<p class="muted">No trend data yet.</p>';
  const mx = Math.max(...t.map(x => +x.green + +x.amber + +x.red), 1);
  return `<div class="trend">${t.map(x => { const h = (+x.green + +x.amber + +x.red) / mx * 100; return `<div class="tcol" title="${x.week_start}: ${x.red} red, ${x.amber} amber, ${x.green} green"><div class="tstack" style="height:${h}%"><i style="height:${x.green / (+x.green + +x.amber + +x.red || 1) * 100}%;background:#2bb673"></i><i style="height:${x.amber / (+x.green + +x.amber + +x.red || 1) * 100}%;background:#f2a900"></i><i style="height:${x.red / (+x.green + +x.amber + +x.red || 1) * 100}%;background:#e5584a"></i></div><small>${x.week_start.slice(5)}</small></div>`; }).join('')}</div><div class="small muted" style="margin-top:8px">Weekly risk movement (green / amber / red)</div>`;
}

/* ---------- dashboards ---------- */
V.dashboard = async () => {
  const d = await api('dashboard');
  if (d.admin) {
    $('#view').innerHTML = `<div class="card hello fade"><div><h3>${greet()}, ${esc(ME.name.split(' ')[0])} 👋</h3><p>System overview and user activity.</p></div></div>
    <div class="grid kpis" style="margin:18px 0">${kpi('blue', '👥', 'Users in system', d.total)}${kpi('green', '🟢', 'Online now', d.online)}${kpi('navy', '🛡️', 'Administrators', d.admins, `maximum ${d.max_admins}`)}${kpi('red', '🎓', 'Unregistered students', d.unregistered, `of ${d.students} students`)}</div>
    <div class="card fade"><h3>User activity</h3><div class="table-wrap"><table class="table"><thead><tr><th>Name</th><th>Role</th><th>Status</th><th>Last login</th><th>Last active</th></tr></thead><tbody>
    ${d.users.map(u => `<tr><td><b>${esc(u.name)}</b></td><td>${ROLE[u.role]}</td><td class="${u.online == 1 ? 'on' : 'off'}"><span class="dotp"></span>${u.online == 1 ? 'Online' : 'Offline'}</td><td>${fmt(u.last_login)}</td><td>${fmt(u.last_seen)}</td></tr>`).join('')}</tbody></table></div></div>`;
    return countUp();
  }
  const c = d.counts, tot = c.green + c.amber + c.red;
  $('#view').innerHTML = `<div class="card hello fade"><div><h3>${greet()}, ${esc(ME.name.split(' ').slice(-1)[0])} 👋</h3><p>${ME.role === 'hod' ? 'Department-wide risk overview (read-only).' : 'Here is how your students are doing today.'}</p></div>
      ${c.red ? `<button class="btn btn-gold" id="seeAlerts">⚠ ${c.red} student${c.red > 1 ? 's' : ''} at high risk</button>` : '<span class="badge green">✔ No high-risk students</span>'}${ME.role === 'hod' ? '<button class="btn btn-secondary" id="dr">📄 Department report</button>' : ''}</div>
    <div class="grid kpis" style="margin:18px 0">${kpi('blue', '🎓', 'Students', tot)}${kpi('green', '✔', 'Green · no risk', c.green)}${kpi('amber', '▲', 'Amber · moderate', c.amber)}${kpi('red', '✖', 'Red · high risk', c.red)}</div>
    <div class="grid two"><div class="card fade"><h3>Risk distribution</h3><div class="donut-wrap">${donut(c)}<div class="legend"><div><span class="dot" style="background:#2bb673"></span>Green ${c.green}</div><div><span class="dot" style="background:#f2a900"></span>Amber ${c.amber}</div><div><span class="dot" style="background:#e5584a"></span>Red ${c.red}</div></div></div></div>
    <div class="card fade"><h3>Risk trend</h3>${trendChart(d.trend)}</div></div>
    <div class="grid two" style="margin-top:18px"><div class="card fade"><h3>🚨 Students needing attention</h3>${d.atrisk.length ? `<div class="table-wrap"><table class="table"><thead><tr><th>Student</th><th>Module</th><th>Status</th><th>Main reason</th><th></th></tr></thead><tbody>${d.atrisk.map(r => `<tr><td><b>${esc(r.name)}</b><div class="small muted">${esc(r.reg)}</div></td><td>${esc(r.code)}</td><td>${badge(r.final)}</td><td class="small">${esc(r.why)}</td><td><button class="btn btn-secondary btn-sm" onclick="report(${r.student_id},${r.module_id})">Report</button></td></tr>`).join('')}</tbody></table></div>` : '<p class="muted">Nobody is currently at risk 🎉</p>'}</div>
    <div class="card fade"><h3>${ME.role === 'hod' ? 'Modules & lecturers' : 'My modules'}</h3>${d.modules.map(m => `<div style="margin:12px 0"><div style="display:flex;justify-content:space-between"><b>${esc(m.code)}</b><span class="small muted">${esc(m.lecturer || '')}</span></div><div class="strip" style="margin-top:6px;height:10px;gap:0;border-radius:6px;overflow:hidden">${['green', 'amber', 'red'].map(k => `<i style="flex:${m[k]};height:10px;width:auto;border-radius:0;background:${{ green: '#2bb673', amber: '#f2a900', red: '#e5584a' }[k]}"></i>`).join('')}</div><div class="small muted">${m.green} green · ${m.amber} amber · ${m.red} red</div></div>`).join('')}</div></div>`;
  countUp();
  if ($('#seeAlerts')) $('#seeAlerts').onclick = () => showAlerts(d.alerts.length ? d.alerts : []);
  if ($('#dr')) $('#dr').onclick = () => deptReport();
  setBell(d.alerts.length);
  if (d.alerts.length) showAlerts(d.alerts);   // pop-up when the dashboard opens
};
function setBell(n) { $('#bell').classList.toggle('hidden', ME.role === 'admin'); $('#bellN').textContent = n; $('#bellN').classList.toggle('hidden', !n); }
$('#bell').onclick = guard(async () => { const d = await api('dashboard'); showAlerts(d.alerts); });
function showAlerts(list) {
  modal(`${mhead('🚨 Students at risk')}<div class="mb">${list.length ? list.map(a => `<div class="popup-alert ${a.severity === 'amber' ? 'amber' : ''}"><span class="em">${a.severity === 'amber' ? '▲' : '✖'}</span><div><b>${a.severity === 'extreme' ? 'Extreme risk' : a.severity === 'red' ? 'High risk' : 'Moderate risk'}</b><div class="small">${esc(a.message)}</div><div class="small muted">${fmt(a.created_at)}</div></div></div>`).join('') : '<p class="muted">No new alerts.</p>'}</div>`,
    `<button class="btn btn-secondary" onclick="closeModal()">Close</button>${list.length ? '<button class="btn btn-primary" id="ack">Mark all as seen</button>' : ''}`);
  if ($('#ack')) $('#ack').onclick = guard(async () => { await api('alerts_ack', {}); closeModal(); setBell(0); toast('Alerts marked as seen'); });
}

/* ---------- modules & roster ---------- */
V.modules = async () => {
  $('#view').innerHTML = `<div class="toolbar"><input id="mq" placeholder="🔍 Search by module code or name (e.g. IS201)" autofocus></div><div id="mlist" class="grid mcards"></div><div id="roster"></div>`;
  const load = guard(async () => {
    const ms = await api('modules', null, '&q=' + encodeURIComponent($('#mq').value));
    $('#mlist').innerHTML = ms.length ? ms.map(m => `<div class="card mcard fade" data-id="${m.id}"><div class="code">${esc(m.code)}</div><div>${esc(m.name)}</div><div class="small muted" style="margin:6px 0">${esc(m.lecturer || '')}</div><span class="badge gray">${m.students} students</span> ${m.red ? `<span class="badge red">${m.red} red</span>` : ''} ${m.amber ? `<span class="badge amber">${m.amber} amber</span>` : ''}</div>`).join('') : '<div class="card muted">No module matches your search.</div>';
  });
  $('#mq').oninput = load; $('#mlist').onclick = e => { const c = e.target.closest('.mcard'); if (c) roster(c.dataset.id); };
  await load();
  const only = $('#mlist .mcard'); if (only && $('#mlist').children.length === 1 && ME.role === 'lecturer' && false) only.click();
};
const roster = guard(async id => {
  const d = await api('roster', null, '&module_id=' + id);
  $('#roster').innerHTML = `<div class="card fade" style="margin-top:18px"><h3>${esc(d.module.code)} · ${esc(d.module.name)} <span class="muted small">(${d.students.length} students)</span></h3>
  <div class="toolbar"><input id="sq" placeholder="Filter students by name or reg number"></div><div class="table-wrap"><table class="table"><thead><tr><th>Reg number</th><th>Name</th><th>Registered</th><th>Attendance</th><th>Academic</th><th>Overall risk</th><th></th></tr></thead><tbody id="rb"></tbody></table></div></div>`;
  const draw = () => { const f = $('#sq').value.toLowerCase(); $('#rb').innerHTML = d.students.filter(s => (s.full_name + s.reg_number).toLowerCase().includes(f)).map(s => `<tr><td>${esc(s.reg_number)}</td><td><b>${esc(s.full_name)}</b></td><td>${s.registered == 1 ? '<span class="badge green">✔ Yes</span>' : '<span class="badge red">✖ No</span>'}</td><td>${badge(s.attendance)}</td><td>${badge(s.academic)}</td><td>${badge(s.final)}</td><td><button class="btn btn-secondary btn-sm" onclick="report(${s.id},${id})">View report</button></td></tr>`).join('') || '<tr><td colspan="7" class="muted">No students found.</td></tr>'; };
  $('#sq').oninput = draw; draw(); $('#roster').scrollIntoView({ behavior: 'smooth' });
});

/* ---------- report pop-up & PDF ---------- */
let REP = null;
const rcls = x => x.startsWith('Red') ? 'r-red' : x.startsWith('Amber') ? 'r-amber' : x.startsWith('Final') ? 'r-final' : 'r-green';
window.report = guard(async (sid, mid) => {
  REP = await api('student', null, `&student_id=${sid}&module_id=${mid}`);
  const r = REP.risk, m = r.trace.metrics, s = REP.student;
  const why = r.trace.reasons.filter(x => x.startsWith('Red') || x.startsWith('Amber')).map(x => x.replace(/^(Red|Amber) \((\w+)\): /, '')).join(' ');
  modal(`${mhead('Risk report preview')}<div class="mb">
    <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap"><div><h2 style="margin:0">${esc(s.full_name)}</h2><div class="muted">${esc(s.reg_number)} · ${esc(s.programme)} · ${esc(REP.module.code)} ${esc(REP.module.name)}</div></div><div>${badge(r.final)}</div></div>
    <div class="notice ${r.final === 'red' ? 'lock' : r.final === 'amber' ? 'warn' : ''}" style="margin-top:14px"><b>${r.final === 'green' ? 'No risk detected.' : 'Why this student is at risk:'}</b> ${r.final === 'green' ? 'All three factors are within the accepted limits.' : esc(why || 'Compounding moderate risk factors.')}</div>
    <h4>How the status was reached</h4><ul class="reasons">${r.trace.reasons.map(x => `<li class="${rcls(x)}">${esc(x)}</li>`).join('')}</ul>
    <div class="metrics"><div><span class="muted small">Registration</span><b>${s.registered == 1 ? 'Registered' : 'NOT registered'}</b></div><div><span class="muted small">Attendance</span><b>${m.attendance_pct ?? '—'}${m.attendance_pct !== null ? '%' : ''}</b><span class="small">${m.present ?? 0} present · ${m.absent ?? 0} absent</span></div><div><span class="muted small">Consecutive missed</span><b>${m.consecutive_missed ?? 0}</b></div><div><span class="muted small">Coursework</span><b>${m.coursework ?? '—'}/${m.coursework_total}</b><span class="small">minimum ${m.coursework_min}</span></div></div>
    ${m.marks ? `<p class="small"><b>Marks:</b> Test ${m.marks.test ?? '—'} · Assignment 1 ${m.marks.a1 ?? '—'} · Assignment 2 ${m.marks.a2 ?? '—'}</p>` : ''}
    <h4>Recent attendance <span class="small muted">(oldest → newest)</span></h4><div class="strip">${[...REP.attendance].reverse().map(a => `<i class="${a.status === 'present' ? 'p' : a.status === 'absent' ? 'a' : ''}" style="${a.status === 'pending' ? 'background:#cbd5e1' : ''}" title="${a.d}: ${a.status}"></i>`).join('') || '<span class="muted">No sessions yet</span>'}</div>
    <h4>Lecturer notes</h4>${REP.notes.length ? REP.notes.map(n => `<div class="small" style="margin:6px 0"><b>${esc(n.lecturer)}</b> · ${fmt(n.created_at)}<br>${esc(n.note_text)}</div>`).join('') : '<p class="muted small">No notes logged.</p>'}
    ${REP.can_note ? `<div class="toolbar" style="margin-top:8px"><input id="nt" placeholder="Log a concern about this student" style="max-width:none"><button class="btn btn-secondary" id="addNote">Add note</button></div>` : ''}
  </div>`, `<button class="btn btn-secondary" onclick="closeModal()">Close</button><button class="btn btn-primary" id="genPdf">📄 Generate PDF report</button>`);
  $('#genPdf').onclick = guard(makePdf);
  if ($('#addNote')) $('#addNote').onclick = guard(async () => { await api('note_add', { student_id: sid, module_id: mid, text: $('#nt').value }); toast('Note added'); report(sid, mid); });
});
function logoData() { return new Promise(res => { const i = new Image(); i.onload = () => { const c = document.createElement('canvas'); c.width = i.width; c.height = i.height; c.getContext('2d').drawImage(i, 0, 0); res(c.toDataURL('image/png')); }; i.onerror = () => res(null); i.src = 'assets/img/logo.png'; }); }
async function makePdf() {
  if (!window.jspdf) throw new Error('PDF library could not load. Check your internet connection.');
  const { jsPDF } = window.jspdf, d = new jsPDF(), r = REP.risk, m = r.trace.metrics, s = REP.student; let y = 18;
  const logo = await logoData(); if (logo) d.addImage(logo, 'PNG', 14, 10, 18, 19);
  d.setFont('helvetica', 'bold'); d.setFontSize(15); d.text('Midlands State University', 38, 17); d.setFontSize(11); d.text('Dropout Prediction System - Student Risk Report', 38, 24);
  y = 38; d.setFontSize(13); d.text(s.full_name, 14, y); d.setFont('helvetica', 'normal'); d.setFontSize(10);
  y += 6; d.text(`${s.reg_number}  |  ${s.programme}  |  ${REP.module.code} ${REP.module.name}`, 14, y);
  y += 10; d.setFont('helvetica', 'bold'); d.setFontSize(12); const col = { green: [22, 121, 76], amber: [161, 92, 0], red: [180, 35, 24] }[r.final]; d.setTextColor(...col); d.text('Overall risk status: ' + r.final.toUpperCase(), 14, y); d.setTextColor(0);
  y += 9; d.setFontSize(11); d.text('Why this status was assigned', 14, y); d.setFont('helvetica', 'normal'); d.setFontSize(10);
  r.trace.reasons.forEach(t => { const l = d.splitTextToSize('- ' + t, 180); if (y + l.length * 5 > 275) { d.addPage(); y = 20; } y += 6; d.text(l, 14, y); y += (l.length - 1) * 5; });
  y += 10; d.setFont('helvetica', 'bold'); d.text('Key data', 14, y); d.setFont('helvetica', 'normal');
  [`Registration: ${s.registered == 1 ? 'Registered' : 'NOT registered'}`, `Attendance: ${m.attendance_pct ?? 'n/a'}${m.attendance_pct !== null ? '%' : ''} (${m.present} present, ${m.absent} absent, ${m.consecutive_missed} consecutive missed)`,
    `Coursework: ${m.coursework ?? 'n/a'} / ${m.coursework_total} (minimum ${m.coursework_min})`, m.marks ? `Test ${m.marks.test ?? '-'}, Assignment 1 ${m.marks.a1 ?? '-'}, Assignment 2 ${m.marks.a2 ?? '-'}` : 'No marks recorded']
    .forEach(t => { y += 6; if (y > 275) { d.addPage(); y = 20; } d.text(t, 14, y); });
  if (REP.notes.length) { y += 10; d.setFont('helvetica', 'bold'); d.text('Lecturer notes', 14, y); d.setFont('helvetica', 'normal'); REP.notes.slice(0, 5).forEach(n => { const l = d.splitTextToSize(`${n.created_at.slice(0, 10)} ${n.lecturer}: ${n.note_text}`, 180); if (y + l.length * 5 > 275) { d.addPage(); y = 20; } y += 6; d.text(l, 14, y); y += (l.length - 1) * 5; }); }
  d.setFontSize(8); d.setTextColor(120); d.text(`Generated by ${REP.generated_by} on ${REP.generated_at}`, 14, 290);
  d.save(`risk-report-${s.reg_number}-${REP.module.code}.pdf`); toast('PDF report generated');
}

/* ---------- attendance ---------- */
async function moduleSelect(withLoad) {
  const ms = await api('modules');
  return `<div class="toolbar"><select id="msel">${ms.map(m => `<option value="${m.id}">${esc(m.code)} · ${esc(m.name)}</option>`).join('')}</select>${withLoad || ''}</div>`;
}
V.attendance = async () => {
  $('#view').innerHTML = (await moduleSelect(`<input type="date" id="dt" max="${new Date().toISOString().slice(0, 10)}" value="${new Date().toISOString().slice(0, 10)}" style="max-width:190px;min-width:150px"><button class="btn btn-primary" id="ld">Load register</button>`)) + '<div id="reg"></div>';
  const load = guard(async () => {
    const d = await api('att_get', null, `&module_id=${$('#msel').value}&date=${$('#dt').value}`), s = d.session;
    const locked = !d.editable;
    $('#reg').innerHTML = `<div class="notice ${locked ? 'lock' : 'warn'}">${s ? (locked ? `🔒 <b>Register locked.</b> It was opened at ${fmt(s.created_at)} and could only be edited until ${fmt(s.edit_until)}. Students left unmarked become <b>absent</b> at ${fmt(s.absent_at)}.` : `Register opened ${fmt(s.created_at)}. You can edit until <b>${fmt(s.edit_until)}</b>; unmarked students are automatically recorded <b>absent</b> at ${fmt(s.absent_at)}.`) : 'New register. Once saved, you have <b>3 hours</b> to edit it, and students you leave unmarked are recorded <b>absent after 4 hours</b>.'}</div>
    <div class="card"><div class="table-wrap"><table class="table"><thead><tr><th>Reg number</th><th>Student</th><th>Attendance</th></tr></thead><tbody>${d.students.map(x => `<tr><td>${esc(x.reg_number)}</td><td><b>${esc(x.full_name)}</b></td><td><div class="radios"><label><input type="radio" name="s${x.id}" value="present" ${x.status === 'present' ? 'checked' : ''} ${locked ? 'disabled' : ''}> Present</label><label class="ab"><input type="radio" name="s${x.id}" value="absent" ${x.status === 'absent' ? 'checked' : ''} ${locked ? 'disabled' : ''}> Absent</label>${x.status === 'pending' ? '<span class="badge gray">not marked</span>' : ''}</div></td></tr>`).join('')}</tbody></table></div>
    ${locked ? '' : '<div style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap"><button class="btn btn-secondary" id="allp">Mark all present</button><button class="btn btn-primary" id="sv">💾 Save attendance</button></div>'}</div>`;
    if ($('#allp')) $('#allp').onclick = () => document.querySelectorAll('input[value=present]').forEach(i => i.checked = true);
    if ($('#sv')) $('#sv').onclick = guard(async () => {
      const marks = {}; d.students.forEach(x => { const c = document.querySelector(`input[name=s${x.id}]:checked`); if (c) marks[x.id] = c.value; });
      const r = await api('att_save', { module_id: +$('#msel').value, date: $('#dt').value, marks });
      toast(`Attendance saved (${r.marked} marked)`); load();
    });
  });
  $('#ld').onclick = load; $('#msel').onchange = load; $('#dt').onchange = load; load();
};

/* ---------- gradebook ---------- */
V.gradebook = async () => {
  $('#view').innerHTML = (await moduleSelect(`<button class="btn btn-secondary" id="tpl">⬇ Excel template</button><label class="btn btn-gold" style="margin:0">📤 Upload Excel / CSV<input type="file" id="xf" accept=".xlsx,.xls,.csv" hidden></label>`)) + '<div id="gb"></div>';
  let G;
  const load = guard(async () => {
    G = await api('marks_get', null, '&module_id=' + $('#msel').value); const mx = G.max, tot = mx.test + mx.a1 + mx.a2;
    $('#gb').innerHTML = `<div class="notice">Coursework = Test (/${mx.test}) + Assignment 1 (/${mx.a1}) + Assignment 2 (/${mx.a2}) = <b>/${tot}</b>. Below <b>${G.cw_min}</b> is flagged red.</div><div class="card"><div class="table-wrap"><table class="table"><thead><tr><th>Reg number</th><th>Student</th><th>Test</th><th>Assignment 1</th><th>Assignment 2</th><th>Coursework</th></tr></thead><tbody>${G.students.map(s => `<tr data-id="${s.id}"><td>${esc(s.reg_number)}</td><td><b>${esc(s.full_name)}</b></td>${['test', 'a1', 'a2'].map(k => `<td style="width:120px"><input type="number" step="0.5" min="0" max="${mx[k]}" data-k="${k}" value="${s[k] ?? ''}"></td>`).join('')}<td class="cw"></td></tr>`).join('')}</tbody></table></div><div style="margin-top:14px"><button class="btn btn-primary" id="sm">💾 Save marks</button></div></div>`;
    const calc = tr => { const v = [...tr.querySelectorAll('input')].map(i => i.value === '' ? null : +i.value); const all = v.every(x => x !== null), s = v.reduce((a, b) => a + (b || 0), 0); tr.querySelector('.cw').innerHTML = v.every(x => x === null) ? '—' : `<b>${s}</b>/${tot} ${all ? badge(s < G.cw_min ? 'red' : 'green') : '<span class="badge gray">incomplete</span>'}`; };
    document.querySelectorAll('#gb tbody tr').forEach(tr => { calc(tr); tr.oninput = () => calc(tr); });
    $('#sm').onclick = guard(async () => {
      const rows = [...document.querySelectorAll('#gb tbody tr')].map(tr => ({ student_id: tr.dataset.id, test: tr.querySelector('[data-k=test]').value, a1: tr.querySelector('[data-k=a1]').value, a2: tr.querySelector('[data-k=a2]').value }));
      await api('marks_save', { module_id: +$('#msel').value, rows }); toast('Marks saved and risk recalculated'); load();
    });
  });
  $('#msel').onchange = load; load();
  $('#tpl').onclick = () => { if (!window.XLSX) return toast('Excel library could not load (internet needed)', true); const ws = XLSX.utils.aoa_to_sheet([['reg_number', 'test', 'assignment1', 'assignment2'], ...(G ? G.students.map(s => [s.reg_number, s.test, s.a1, s.a2]) : [])]); const wb = XLSX.utils.book_new(); XLSX.utils.book_append_sheet(wb, ws, 'Coursework'); XLSX.writeFile(wb, 'coursework-template.xlsx'); };
  $('#xf').onchange = guard(async e => {
    const f = e.target.files[0]; if (!f) return; if (!window.XLSX) throw new Error('Excel library could not load (internet needed)');
    const wb = XLSX.read(await f.arrayBuffer(), { type: 'array' }), rows = XLSX.utils.sheet_to_json(wb.Sheets[wb.SheetNames[0]], { defval: '' });
    const pick = (r, ...ks) => { for (const k of Object.keys(r)) if (ks.includes(k.toLowerCase().replace(/[\s_\-]/g, ''))) return r[k]; return ''; };
    const data = rows.map(r => ({ reg: String(pick(r, 'regnumber', 'reg', 'regno', 'registrationnumber', 'studentnumber')).trim(), test: pick(r, 'test', 'test1'), assignment1: pick(r, 'assignment1', 'a1', 'assign1'), assignment2: pick(r, 'assignment2', 'a2', 'assign2') }));
    if (!data.length) throw new Error('No rows found in the file');
    const r = await api('marks_import', { module_id: +$('#msel').value, rows: data }); e.target.value = '';
    toast(`${r.imported} student(s) imported` + (r.skipped.length ? `, ${r.skipped.length} skipped` : ''), !!r.skipped.length && !r.imported);
    if (r.skipped.length) modal(`${mhead('Some rows were skipped')}<div class="mb"><ul>${r.skipped.map(x => `<li>${esc(x)}</li>`).join('')}</ul></div>`, '<button class="btn btn-primary" onclick="closeModal()">OK</button>');
    load();
  });
};

/* ---------- admin ---------- */
const pwField = (id, ph) => `<div class="pwbox"><input id="${id}" type="password" placeholder="${ph}"><button type="button" class="eye" aria-label="Show password" title="Show / hide password"></button></div>`;
document.addEventListener('click', e => { const b = e.target.closest('.eye'); if (!b) return; const i = b.previousElementSibling, show = i.type === 'password'; i.type = show ? 'text' : 'password'; b.classList.toggle('on', show); });
const deptBoxes = (all, sel) => `<div style="max-height:170px;overflow:auto;border:1px solid var(--line);border-radius:10px;padding:8px 10px">${all.map(x => `<label style="display:flex;gap:8px;align-items:center;font-weight:500;margin:4px 0"><input type="checkbox" value="${esc(x.name)}" ${sel.includes(x.name) ? 'checked' : ''}> ${esc(x.name)}</label>`).join('')}</div><div class="small muted">Lecturers can be ticked in several departments (a department stays on the list while they still teach one of its modules). An HOD / Dean heads exactly one. Administrators need none.</div>`;
const ticked = id => [...document.querySelectorAll(id + ' input:checked')].map(i => i.value);
V.users = async () => {
  const d = await api('users_list'); window.USERS = d.users; window.DEPS = d.departments; const full = d.admins >= d.max_admins, doms = d.domains.map(x => '@' + x).join(' or ');
  $('#view').innerHTML = `<div class="grid two"><div class="card fade"><h3>Create account</h3><div class="form-group"><label>Full name</label><input id="un"></div><div class="form-group"><label>Email</label><input id="ue" type="email" placeholder="name@staff.msu.ac.zw"><div class="small muted">Only ${esc(doms)} addresses are accepted.</div></div>
  <div class="fgrid"><div class="form-group"><label>Role</label><select id="ur"><option value="lecturer">Lecturer</option><option value="hod">HOD / Dean</option><option value="admin" ${full ? 'disabled' : ''}>Administrator${full ? ' (limit reached)' : ''}</option></select></div><div class="form-group"><label>Department(s)</label><div id="ud">${deptBoxes(d.departments, [])}</div></div></div>
  <div class="form-group"><label>Temporary password (min 8 characters)</label>${pwField('up', 'Set a password')}</div><button class="btn btn-primary" id="cu">Create account</button>
  <p class="small muted">Administrators: <b>${d.admins}/${d.max_admins}</b></p></div>
  <div class="card fade"><h3>At a glance</h3><div class="metrics"><div><span class="muted small">Total users</span><b>${d.users.length}</b></div><div><span class="muted small">Online now</span><b>${d.users.filter(u => u.online == 1).length}</b></div></div></div></div>
  <div class="card fade" style="margin-top:18px"><h3>All users</h3><div class="table-wrap"><table class="table"><thead><tr><th>Name</th><th>Role</th><th>Status</th><th>Last login</th><th>Last active</th><th>Created</th><th></th></tr></thead><tbody>
  ${d.users.map(u => `<tr><td><b>${esc(u.name)}</b><div class="small muted">${esc(u.email)}</div></td><td>${ROLE[u.role]}${(u.departments || []).length ? `<div class="small muted">${esc(u.departments.join(', '))}</div>` : ''}</td><td class="${u.online == 1 ? 'on' : 'off'}"><span class="dotp"></span>${u.online == 1 ? 'Online' : 'Offline'}${u.active == 1 ? '' : ' <span class="badge red">Deactivated</span>'}${u.locked == 1 ? ' <span class="badge amber">Locked</span>' : ''}${u.must_change == 1 ? ' <span class="badge gray">Must set new password</span>' : ''}</td><td>${fmt(u.last_login)}</td><td>${fmt(u.last_seen)}</td><td>${fmt(u.created_at)}</td><td style="white-space:nowrap"><button class="btn btn-secondary btn-sm" onclick="editUser(${u.id})">Edit</button> ${u.id == ME.id ? '' : `<button class="btn btn-secondary btn-sm" onclick="resetPw(${u.id})">Reset password</button> <button class="btn btn-sm ${u.active == 1 ? 'btn-danger' : 'btn-secondary'}" onclick="toggleUser(${u.id})">${u.active == 1 ? 'Deactivate' : 'Activate'}</button>`}</td></tr>`).join('')}</tbody></table></div></div>`;
  $('#cu').onclick = guard(async () => { await api('user_create', { name: $('#un').value, email: $('#ue').value, role: $('#ur').value, departments: ticked('#ud'), password: $('#up').value }); toast('Account created'); V.users(); });
};
window.editUser = id => {
  const u = USERS.find(x => x.id == id), self = id == ME.id;
  modal(`${mhead('Edit user')}<div class="mb"><div class="notice">Editing keeps this person's account, history, notes and module assignments. Nothing is deleted.</div>
    <div class="form-group"><label>Full name</label><input id="en" value="${esc(u.name)}"></div><div class="form-group"><label>Email</label><input id="ee" type="email" value="${esc(u.email)}"></div>
    <div class="fgrid"><div class="form-group"><label>Role</label><select id="er" ${self ? 'disabled' : ''}><option value="lecturer" ${u.role === 'lecturer' ? 'selected' : ''}>Lecturer</option><option value="hod" ${u.role === 'hod' ? 'selected' : ''}>HOD / Dean</option><option value="admin" ${u.role === 'admin' ? 'selected' : ''}>Administrator</option></select>${self ? '<div class="small muted">You cannot change your own role.</div>' : ''}</div>
    <div class="form-group"><label>Department(s)</label><div id="ed">${deptBoxes(DEPS, u.departments || [])}</div></div></div></div>`,
    '<button class="btn btn-secondary" onclick="closeModal()">Cancel</button><button class="btn btn-primary" id="eu">Save changes</button>');
  $('#eu').onclick = guard(async () => { await api('user_update', { id, name: $('#en').value, email: $('#ee').value, role: self ? u.role : $('#er').value, departments: ticked('#ed') }); closeModal(); toast('User updated'); V.users(); });
};
window.resetPw = id => {
  const u = USERS.find(x => x.id == id);
  modal(`${mhead('Reset password')}<div class="mb"><p>Set a temporary password for <b>${esc(u.name)}</b>. They will be asked to choose their own at their next sign-in, and any lockout is cleared.</p><div class="form-group"><label>Temporary password (min 8 characters)</label>${pwField('rp', 'Temporary password')}</div></div>`,
    '<button class="btn btn-secondary" onclick="closeModal()">Cancel</button><button class="btn btn-primary" id="rpb">Reset password</button>');
  $('#rpb').onclick = guard(async () => { await api('user_reset_password', { id, password: $('#rp').value }); closeModal(); toast('Password reset. Give the person their temporary password.'); V.users(); });
};
window.toggleUser = guard(async id => { await api('user_toggle', { id }); toast('Account updated'); V.users(); });

V.students = async () => {
  const rows = await api('students_list'), facs = [...new Set(rows.map(r => r.faculty).filter(Boolean))].sort();
  $('#view').innerHTML = `<div class="toolbar"><input id="sq" placeholder="Search name, reg number or programme"><select id="sf"><option value="">All faculties</option>${facs.map(f => `<option>${esc(f)}</option>`).join('')}</select><label class="btn btn-gold" style="margin:0">📤 Import registration (Excel/CSV)<input type="file" id="rf" accept=".xlsx,.xls,.csv" hidden></label></div>
  <div class="notice">An <b>unregistered</b> student is always <b>Red</b>. Import columns: <code>reg_number</code>, <code>registered</code> (yes/no or 1/0).</div>
  <div class="card"><div class="muted small" id="scount" style="margin-bottom:8px"></div><div class="table-wrap"><table class="table"><thead><tr><th>Reg number</th><th>Name</th><th>Faculty</th><th>Programme</th><th>Registered</th></tr></thead><tbody id="sb"></tbody></table></div></div>`;
  const draw = () => { const f = $('#sq').value.toLowerCase(), fac = $('#sf').value, m = rows.filter(s => (!fac || s.faculty === fac) && (s.full_name + s.reg_number + (s.programme || '')).toLowerCase().includes(f));
    $('#scount').textContent = `${m.length} student${m.length === 1 ? '' : 's'}${m.length > 200 ? ' (showing first 200, use search or the faculty filter)' : ''}`;
    $('#sb').innerHTML = m.slice(0, 200).map(s => `<tr><td>${esc(s.reg_number)}</td><td><b>${esc(s.full_name)}</b></td><td class="small">${esc(s.faculty || '—')}</td><td class="small">${esc(s.programme || '')}</td><td><label><input type="checkbox" ${s.registered == 1 ? 'checked' : ''} onchange="setReg(${s.id},this)"> <span>${s.registered == 1 ? 'Registered' : '<b>Not registered</b>'}</span></label></td></tr>`).join(''); };
  $('#sq').oninput = draw; $('#sf').onchange = draw; draw();
  $('#rf').onchange = guard(async e => { const f = e.target.files[0]; if (!f) return; if (!window.XLSX) throw new Error('Excel library could not load (internet needed)');
    const wb = XLSX.read(await f.arrayBuffer(), { type: 'array' }), js = XLSX.utils.sheet_to_json(wb.Sheets[wb.SheetNames[0]], { defval: '' });
    const data = js.map(r => { const k = Object.keys(r).reduce((o, x) => (o[x.toLowerCase().replace(/[\s_\-]/g, '')] = r[x], o), {}); const v = String(k.registered ?? k.status ?? '').toLowerCase(); return { reg: String(k.regnumber ?? k.reg ?? '').trim(), registered: ['1', 'yes', 'true', 'y', 'registered'].includes(v) }; });
    const r = await api('registration_set', { rows: data }); toast(`Registration updated${r.skipped.length ? `, ${r.skipped.length} unknown reg numbers skipped` : ''}`); V.students(); });
};
window.setReg = guard(async (id, el) => { const v = el.checked; await api('registration_set', { rows: [{ id, registered: v }] }); el.nextElementSibling.innerHTML = v ? 'Registered' : '<b>Not registered</b>'; toast(v ? 'Marked as registered' : 'Marked NOT registered: student is now Red'); });

V.faculties = async () => {
  const [fs, ds] = await Promise.all([api('faculties_list'), api('departments_list')]);
  const inp = (k, v) => `<td style="width:92px"><input type="number" step="0.5" min="0" data-k="${k}" value="${+v}"></td>`;
  $('#view').innerHTML = `<div class="notice">Coursework (test + assignment 1 + assignment 2) is marked on <b>one scale per faculty</b>. Every department in the faculty follows it, lecturers' marks are checked against it, and the <b>minimum</b> is the mark below which a student is Red. Changing a scale recalculates every student's risk.</div>
  <div class="card fade" style="margin-bottom:18px"><h3>Faculties</h3><div class="table-wrap"><table class="table"><thead><tr><th>Faculty</th><th>Depts</th><th>Programmes</th><th>Students</th><th>Test</th><th>Assign. 1</th><th>Assign. 2</th><th>Total</th><th>Minimum</th><th></th></tr></thead><tbody id="fb">${fs.map(f => `<tr data-id="${f.id}"><td><b>${esc(f.name)}</b></td><td>${f.depts}</td><td>${f.programmes}</td><td>${f.students}</td>${inp('test_max', f.test_max)}${inp('a1_max', f.a1_max)}${inp('a2_max', f.a2_max)}<td class="tot"></td>${inp('cw_min', f.cw_min)}<td><button class="btn btn-secondary btn-sm">Save</button></td></tr>`).join('')}</tbody></table></div></div>
  <div class="grid two"><div class="card fade"><h3>Departments</h3><div class="toolbar"><input id="dq" placeholder="Filter departments"></div><div class="table-wrap" style="max-height:420px"><table class="table"><thead><tr><th>Department</th><th>Faculty</th><th>Scale</th></tr></thead><tbody id="db"></tbody></table></div></div>
  <div class="card fade"><h3>Add or move a department</h3><div class="form-group"><label>Department name</label><input id="dn"></div><div class="form-group"><label>Faculty</label><select id="df">${fs.map(f => `<option value="${f.id}">${esc(f.name)}</option>`).join('')}</select></div><button class="btn btn-primary" id="da">Save department</button><p class="small muted">Using an existing name moves that department to the chosen faculty and applies its scale.</p></div></div>`;
  const tot = tr => tr.querySelector('.tot').innerHTML = '<b>' + ['test_max', 'a1_max', 'a2_max'].reduce((a, k) => a + +tr.querySelector(`[data-k=${k}]`).value, 0) + '</b>';
  document.querySelectorAll('#fb tr').forEach(tr => { tot(tr); tr.oninput = () => tot(tr); });
  const drawD = () => { const q = $('#dq').value.toLowerCase(); $('#db').innerHTML = ds.filter(d => (d.name + (d.faculty || '')).toLowerCase().includes(q)).map(d => `<tr><td>${esc(d.name)}</td><td class="small">${esc(d.faculty || '—')}</td><td>${+d.test_max + +d.a1_max + +d.a2_max} (min ${+d.cw_min})</td></tr>`).join(''); };
  $('#dq').oninput = drawD; drawD();
  $('#fb').onclick = guard(async e => { const b = e.target.closest('button'); if (!b) return; const tr = b.closest('tr'), g = { id: tr.dataset.id }; tr.querySelectorAll('input').forEach(i => g[i.dataset.k] = i.value); b.disabled = true; await api('faculty_save', g); toast('Scale saved; risk recalculated'); V.faculties(); });
  $('#da').onclick = guard(async () => { await api('department_save', { name: $('#dn').value, faculty_id: $('#df').value }); toast('Department saved'); V.faculties(); });
};
V.modmgmt = async () => {
  const d = await api('modules_admin'), opt = (arr, f) => arr.map(f).join('');
  $('#view').innerHTML = `<div class="card fade" style="margin-bottom:18px"><h3>Create a module</h3><div class="fgrid"><div class="form-group"><label>Module code</label><input id="mc" placeholder="IS201"></div><div class="form-group"><label>Module name</label><input id="mn"></div>
    <div class="form-group"><label>Department</label><select id="md">${opt(d.departments, x => `<option>${esc(x.name)}</option>`)}</select></div>
    <div class="form-group"><label>Lecturer (optional)</label><select id="ml"><option value="">— none yet —</option>${opt(d.lecturers, l => `<option value="${l.id}">${esc(l.name)}</option>`)}</select></div>
    <div class="form-group"><label>Enrol every student of programme</label><select id="mp"><option value="">— nobody —</option>${opt(d.programmes, p => `<option value="${p.id}">${esc(p.name)}</option>`)}</select></div></div><button class="btn btn-primary" id="mcb">Create module</button></div>
  <div class="card fade"><h3>Modules and their lecturers</h3><div class="toolbar"><input id="mq" placeholder="Search by code, name or department"><span class="muted small" id="mcount"></span></div><div class="table-wrap"><table class="table"><thead><tr><th>Code</th><th>Module</th><th>Department</th><th>Students</th><th>Lecturer</th><th></th></tr></thead><tbody id="mb"></tbody></table></div></div>`;
  const draw = () => { const q = $('#mq').value.toLowerCase(), rows = d.modules.filter(m => (m.code + m.name + m.department).toLowerCase().includes(q)); $('#mcount').textContent = `${rows.length} module${rows.length === 1 ? '' : 's'}${rows.length > 100 ? ' (showing first 100)' : ''}`;
    $('#mb').innerHTML = rows.slice(0, 100).map(m => `<tr data-id="${m.id}"><td><b>${esc(m.code)}</b></td><td>${esc(m.name)}</td><td class="small">${esc(m.department)}</td><td>${m.students}</td><td><select>${'<option value="">— unassigned —</option>' + opt(d.lecturers, l => `<option value="${l.id}" ${l.id == m.lecturer_id ? 'selected' : ''}>${esc(l.name)}</option>`)}</select></td><td><button class="btn btn-secondary btn-sm">Save</button></td></tr>`).join(''); };
  $('#mq').oninput = draw; draw();
  $('#mb').onclick = guard(async e => { const b = e.target.closest('button'); if (!b) return; const tr = b.closest('tr'); await api('module_save', { id: tr.dataset.id, lecturer_id: tr.querySelector('select').value }); toast('Lecturer updated'); });
  $('#mcb').onclick = guard(async () => { await api('module_save', { code: $('#mc').value, name: $('#mn').value, department: $('#md').value, lecturer_id: $('#ml').value, programme_id: $('#mp').value }); toast('Module created'); V.modmgmt(); });
};
V.settings = async () => {
  const s = await api('settings_get');
  const f = (k, l, h) => `<div class="form-group"><label>${l}</label><input id="${k}" type="number" step="any" min="0" value="${esc(s[k])}"><div class="small muted">${h}</div></div>`;
  $('#view').innerHTML = `<div class="card fade" style="max-width:760px"><h3>Rule thresholds</h3><p class="muted">No rule is hard-coded: changes apply to every student as soon as you save. Coursework scales are set per department on the Departments page.</p><div class="fgrid">
  ${f('att_amber', 'Attendance AMBER from (consecutive missed lectures)', 'Default 5')}${f('att_red', 'Attendance RED from (consecutive missed lectures)', 'Default 10')}${f('cw_min', 'Default minimum coursework mark', 'Used only if a department has no scale')}${f('amber_pct', 'Amber when any assessment is below (%)', 'Default 30')}
  ${f('test_max', 'Default test maximum', 'Fallback scale')}${f('a1_max', 'Default assignment 1 maximum', 'Fallback scale')}${f('a2_max', 'Default assignment 2 maximum', 'Fallback scale')}
  <div class="form-group"><label>Escalation: two ambers = Red</label><select id="escalation"><option value="1" ${s.escalation == 1 ? 'selected' : ''}>On</option><option value="0" ${s.escalation == 0 ? 'selected' : ''}>Off (worst single factor wins)</option></select></div></div>
  <button class="btn btn-primary" id="ss">Save thresholds</button></div>`;
  $('#ss').onclick = guard(async () => { const b = {}; ['att_amber', 'att_red', 'cw_min', 'amber_pct', 'test_max', 'a1_max', 'a2_max', 'escalation'].forEach(k => b[k] = $('#' + k).value); await api('settings_save', b); toast('Thresholds saved; all risk scores recalculated'); });
};
V.audit = async () => {
  const rows = await api('audit_list');
  $('#view').innerHTML = `<div class="toolbar"><a class="btn btn-primary" href="api.php?a=audit_csv">⬇ Download full audit log (CSV)</a><span class="muted small">Showing the latest ${rows.length} entries</span></div><div class="card"><div class="table-wrap"><table class="table"><thead><tr><th>Time</th><th>User</th><th>Action</th><th>Target</th><th>Old</th><th>New</th></tr></thead><tbody>${rows.map(r => `<tr><td>${fmt(r.created_at)}</td><td>${esc(r.user_name)}</td><td>${esc(r.action)}</td><td>${esc(r.entity)} ${esc(r.entity_id || '')}</td><td class="small">${esc(r.old_value || '')}</td><td class="small">${esc(r.new_value || '')}</td></tr>`).join('')}</tbody></table></div></div>`;
};
V.usage = async () => {
  const p = await api('usage_periods');
  $('#view').innerHTML = `<div class="notice">Usage reports are grouped in <b>7-week periods</b> and can be downloaded <b>at any time</b>. A period that is still running is reported up to today.</div><div class="card"><table class="table"><thead><tr><th>Period</th><th>From</th><th>To</th><th>Status</th><th></th></tr></thead><tbody>${p.map(x => `<tr><td><b>Period ${x.period}</b></td><td>${x.from}</td><td>${x.to}</td><td>${x.complete ? '<span class="badge green">Complete</span>' : '<span class="badge amber">In progress</span>'}</td><td><a class="btn btn-primary btn-sm" href="api.php?a=usage_csv&p=${x.period}">⬇ Download${x.complete ? '' : ' (to date)'}</a></td></tr>`).join('')}</tbody></table></div>`;
};


/* ---------- HOD departmental report (preview, then PDF or CSV) ---------- */
V.report = async () => {
  $('#view').innerHTML = `<div class="card fade" style="max-width:640px"><h3>Departmental report</h3><p class="muted">A summary of every student and module in your department: risk totals, a breakdown by module and by lecturer, and the full list of students who are Amber or Red with the reason.</p><button class="btn btn-primary" id="dr2">📄 Preview and download</button></div>`;
  $('#dr2').onclick = () => deptReport();
};
const csvCell = v => { v = String(v ?? ''); return /[",\n]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v; };
function download(name, blob) { const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = name; document.body.appendChild(a); a.click(); a.remove(); setTimeout(() => URL.revokeObjectURL(a.href), 2000); }
window.deptReport = guard(async () => {
  const R = window.DR = await api('dept_report'), s = R.summary, st = x => ({ green: 'Green', amber: 'Amber', red: 'Red' }[x]);
  modal(`${mhead('Departmental report preview')}<div class="mb"><h2 style="margin:0">${esc(R.department)}</h2><div class="muted">${esc(R.faculty)} · generated ${esc(R.generated_at)} by ${esc(R.generated_by)}</div>
    <div class="metrics"><div><span class="muted small">Students</span><b>${s.students}</b></div><div><span class="muted small">Green</span><b style="color:var(--green)">${s.green}</b></div><div><span class="muted small">Amber</span><b style="color:var(--amber)">${s.amber}</b></div><div><span class="muted small">Red</span><b style="color:var(--red)">${s.red}</b></div><div><span class="muted small">Unregistered</span><b>${s.unregistered}</b></div><div><span class="muted small">Modules · lecturers</span><b>${s.modules} · ${s.lecturers}</b></div></div>
    <h4>By module</h4><div class="table-wrap" style="max-height:200px"><table class="table"><thead><tr><th>Module</th><th>Lecturer</th><th>Students</th><th>Amber</th><th>Red</th></tr></thead><tbody>${R.modules.map(m => `<tr><td><b>${esc(m.code)}</b> <span class="small muted">${esc(m.name)}</span></td><td>${esc(m.lecturer)}</td><td>${m.students}</td><td>${m.amber}</td><td>${m.red}</td></tr>`).join('') || '<tr><td colspan="5" class="muted">No modules in this department yet.</td></tr>'}</tbody></table></div>
    <h4>By lecturer</h4><div class="table-wrap"><table class="table"><thead><tr><th>Lecturer</th><th>Modules</th><th>Students</th><th>Amber</th><th>Red</th></tr></thead><tbody>${R.lecturers.map(l => `<tr><td>${esc(l.lecturer)}</td><td>${l.modules}</td><td>${l.students}</td><td>${l.amber}</td><td>${l.red}</td></tr>`).join('')}</tbody></table></div>
    <h4>Students at risk (${R.atrisk.length})</h4><div class="table-wrap" style="max-height:220px"><table class="table"><thead><tr><th>Student</th><th>Module</th><th>Status</th><th>Reason</th></tr></thead><tbody>${R.atrisk.slice(0, 25).map(a => `<tr><td>${esc(a.name)}<div class="small muted">${esc(a.reg)}</div></td><td>${esc(a.module)}</td><td>${badge(a.status)}</td><td class="small">${esc(a.why)}</td></tr>`).join('') || '<tr><td colspan="4" class="muted">Nobody is currently at risk.</td></tr>'}</tbody></table></div>
    ${R.atrisk.length > 25 ? `<p class="small muted">Showing 25 of ${R.atrisk.length}. The downloads contain the full list.</p>` : ''}</div>`,
    `<button class="btn btn-secondary" onclick="closeModal()">Close</button><button class="btn btn-secondary" id="dcsv">⬇ Download CSV (Excel)</button><button class="btn btn-primary" id="dpdf">📄 Generate PDF report</button>`);
  $('#dcsv').onclick = () => {
    const rows = [['Department report', R.department], ['Faculty', R.faculty], ['Generated', R.generated_at + ' by ' + R.generated_by], [],
      ['Students', 'Green', 'Amber', 'Red', 'Unregistered', 'Modules', 'Lecturers'], [s.students, s.green, s.amber, s.red, s.unregistered, s.modules, s.lecturers], [],
      ['MODULES'], ['Code', 'Module', 'Lecturer', 'Students', 'Green', 'Amber', 'Red'], ...R.modules.map(m => [m.code, m.name, m.lecturer, m.students, m.green, m.amber, m.red]), [],
      ['LECTURERS'], ['Lecturer', 'Modules', 'Students', 'Amber', 'Red'], ...R.lecturers.map(l => [l.lecturer, l.modules, l.students, l.amber, l.red]), [],
      ['STUDENTS AT RISK'], ['Name', 'Reg number', 'Module', 'Status', 'Reason'], ...R.atrisk.map(a => [a.name, a.reg, a.module, st(a.status), a.why])];
    download(`department-report-${R.department.replace(/\W+/g, '-').toLowerCase()}.csv`, new Blob(['\ufeff' + rows.map(r => r.map(csvCell).join(',')).join('\r\n')], { type: 'text/csv;charset=utf-8' }));
    toast('CSV downloaded');
  };
  $('#dpdf').onclick = guard(makeDeptPdf);
});
async function makeDeptPdf() {
  if (!window.jspdf) throw new Error('PDF library could not load. Check your internet connection.');
  const { jsPDF } = window.jspdf, R = DR, s = R.summary, d = new jsPDF(); let y = 18;
  const logo = await logoData(); if (logo) d.addImage(logo, 'PNG', 14, 10, 18, 19);
  d.setFont('helvetica', 'bold'); d.setFontSize(15); d.text('Midlands State University', 38, 17); d.setFontSize(11); d.text('Dropout Prediction System - Departmental Report', 38, 24);
  y = 38; d.setFontSize(13); d.text(R.department, 14, y); d.setFont('helvetica', 'normal'); d.setFontSize(10); y += 6; d.text(`${R.faculty}  |  Generated ${R.generated_at} by ${R.generated_by}`, 14, y);
  y += 10; d.setFont('helvetica', 'bold'); d.text('Summary', 14, y); d.setFont('helvetica', 'normal');
  y += 6; d.text(`Students: ${s.students}    Green: ${s.green}    Amber: ${s.amber}    Red: ${s.red}    Unregistered: ${s.unregistered}`, 14, y); y += 6; d.text(`Modules: ${s.modules}    Lecturers: ${s.lecturers}`, 14, y);
  const table = (title, cols, rows) => {
    y += 11; if (y > 270) { d.addPage(); y = 20; } d.setFont('helvetica', 'bold'); d.setFontSize(11); d.text(title, 14, y); d.setFontSize(9);
    const line = (vals, bold) => { if (y > 280) { d.addPage(); y = 20; } d.setFont('helvetica', bold ? 'bold' : 'normal'); vals.forEach((v, i) => d.text(d.splitTextToSize(String(v ?? ''), cols[i][1])[0] || '', cols[i][0], y)); y += 5.2; };
    y += 6; line(cols.map(c => c[2]), true); rows.forEach(r => line(r, false)); d.setFontSize(10);
  };
  table('By module', [[14, 22, 'Code'], [36, 62, 'Module'], [100, 44, 'Lecturer'], [146, 16, 'Students'], [166, 12, 'Amber'], [182, 10, 'Red']], R.modules.map(m => [m.code, m.name, m.lecturer, m.students, m.amber, m.red]));
  table('By lecturer', [[14, 80, 'Lecturer'], [100, 20, 'Modules'], [126, 20, 'Students'], [156, 16, 'Amber'], [178, 12, 'Red']], R.lecturers.map(l => [l.lecturer, l.modules, l.students, l.amber, l.red]));
  const cap = 150; table(`Students at risk (${R.atrisk.length}${R.atrisk.length > cap ? ', first ' + cap + ' shown; the CSV has all' : ''})`, [[14, 40, 'Student'], [56, 26, 'Reg number'], [84, 20, 'Module'], [106, 14, 'Status'], [122, 80, 'Reason']],
    R.atrisk.slice(0, cap).map(a => [a.name, a.reg, a.module, { green: 'Green', amber: 'Amber', red: 'Red' }[a.status], a.why.replace(/^(Red|Amber) \(\w+\): /, '')]));
  d.setFontSize(8); d.setTextColor(120); d.text(`Generated by ${R.generated_by} on ${R.generated_at}`, 14, 290);
  d.save(`department-report-${R.department.replace(/\W+/g, '-').toLowerCase()}.pdf`); toast('PDF report generated');
}

/* ---------- change password (voluntary, and forced after an admin reset) ---------- */
function pwModal(forced) {
  modal(`<div class="mh"><h3>${forced ? 'Choose a new password' : 'Change password'}</h3>${forced ? '' : '<button class="btn btn-secondary btn-sm" onclick="closeModal()">✕</button>'}</div><div class="mb">${forced ? '<div class="notice warn">An administrator reset your password. Please choose your own before continuing.</div>' : ''}
    <div class="form-group"><label>${forced ? 'Temporary password' : 'Current password'}</label>${pwField('cp0', '')}</div><div class="form-group"><label>New password (min 8 characters)</label>${pwField('cp1', '')}</div><div class="form-group"><label>Confirm new password</label>${pwField('cp2', '')}</div></div>`,
    `${forced ? '' : '<button class="btn btn-secondary" onclick="closeModal()">Cancel</button>'}<button class="btn btn-primary" id="cpb">Save password</button>`);
  if (forced) $('#modal').onclick = null;
  $('#cpb').onclick = guard(async () => {
    if ($('#cp1').value !== $('#cp2').value) throw new Error('The new passwords do not match');
    await api('change_password', { current: $('#cp0').value, new: $('#cp1').value }); ME.must_change = 0; closeModal(); toast('Password changed'); if (forced) go('dashboard');
  });
}
$('.side-foot').insertAdjacentHTML('afterbegin', '<button class="btn btn-secondary btn-sm" id="cpw" style="width:100%;margin-bottom:10px">🔑 Change password</button>');
$('#cpw').onclick = () => pwModal(false);

/* ---------- boot ---------- */
$('#uname').textContent = ME.name; $('#urole').textContent = ROLE[ME.role]; $('#av').textContent = ME.name.replace(/^(Dr|Prof|Ms|Mr|Mrs)\.?\s+/i, '').split(' ').map(x => x[0]).slice(0, 2).join('');
buildNav(); if (ME.must_change == 1) pwModal(true); else go('dashboard');
/* Back button -> login screen; sign out after an hour of inactivity (see session.js) */
const guard_ = startSessionGuard({
  timeout: B.timeout || 3600,
  logout: () => fetch('api.php?a=logout', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF': B.csrf }, body: '{}', keepalive: true }),
  toLogin: expired => location.replace('login.php' + (expired ? '?expired=1' : '')),
  ping: () => api('ping', { active: 1 }).catch(() => { }),
  warn: left => {
    if (!$('#wl')) { modal(`<div class="mh"><h3>Still there?</h3></div><div class="mb"><p>For your security you will be signed out in <b id="wl"></b> seconds because there has been no activity.</p></div>`, '<button class="btn btn-primary" id="stay">Stay signed in</button>'); $('#modal').onclick = null; $('#stay').onclick = () => guard_.stay(); }
    $('#wl').textContent = left;
  },
  unwarn: () => { if ($('#wl')) closeModal(); }
});
