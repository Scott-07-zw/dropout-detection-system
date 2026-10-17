<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/risk.php';
$a = $_GET['a'] ?? '';
$in = json_decode(file_get_contents('php://input'), true) ?: [];
$post = $_SERVER['REQUEST_METHOD'] === 'POST';
const MAX_ADMINS = 5;

function csv_out(string $name, array $head, array $rows): void {
  header('Content-Type: text/csv; charset=utf-8'); header("Content-Disposition: attachment; filename=\"$name\"");
  $o = fopen('php://output', 'w'); fwrite($o, "\xEF\xBB\xBF"); fputcsv($o, $head); foreach ($rows as $r) fputcsv($o, $r); exit;
}
function num($v) { return ($v === '' || $v === null) ? null : (is_numeric($v) ? (float)$v : false); }
function save_marks(int $mid, int $sid, $t, $a1, $a2): bool {
  $sc = dept_scale($mid); $v = [$t, $a1, $a2]; $mx = [$sc['test_max'], $sc['a1_max'], $sc['a2_max']];
  foreach ($v as $i => $x) if ($x === false || ($x !== null && ($x < 0 || $x > $mx[$i]))) return false;
  $old = one('SELECT test,a1,a2 FROM marks WHERE student_id=? AND module_id=?', [$sid, $mid]);
  $nz = fn($x) => $x === null ? null : (float)$x;
  if ($old && $nz($old['test']) === $t && $nz($old['a1']) === $a1 && $nz($old['a2']) === $a2) return true;
  q('REPLACE INTO marks(student_id,module_id,test,a1,a2) VALUES(?,?,?,?,?)', [$sid, $mid, $t, $a1, $a2]);
  audit('marks_update', 'marks', "$sid/$mid", $old, ['test' => $t, 'a1' => $a1, 'a2' => $a2]);
  return true;
}
function check_email_domain(string $email): void {
  $allowed = (require __DIR__ . '/inc/config.php')['allowed_email_domains'] ?? ['staff.msu.ac.zw'];
  if (!in_array(substr(strrchr($email, '@'), 1), $allowed, true)) jerr('Only university email addresses are accepted (@' . implode(' or @', $allowed) . ').');
}
function read_departments(array $in, string $role): array {
  if ($role === 'admin') return [];
  $list = isset($in['departments']) && is_array($in['departments']) ? $in['departments'] : (isset($in['department']) && $in['department'] !== '' ? [$in['department']] : []);
  $list = array_values(array_unique(array_filter(array_map(fn($x) => trim((string)$x), $list))));
  if (!$list) jerr($role === 'hod' ? 'Choose the HOD\'s department' : 'Choose at least one department');
  if ($role === 'hod' && count($list) > 1) jerr('An HOD / Dean heads exactly one department');
  foreach ($list as $d) if (!one('SELECT 1 FROM departments WHERE name=?', [$d])) jerr('Unknown department: ' . $d);
  return $list;
}
function set_user_departments(int $id, array $deps): void {
  q('DELETE FROM user_departments WHERE user_id=?', [$id]);
  foreach ($deps as $d) q('INSERT IGNORE INTO user_departments(user_id,department) VALUES(?,?)', [$id, $d]);
}
function online_sql(): string { return "(last_seen >= NOW() - INTERVAL 5 MINUTE AND (last_logout IS NULL OR last_logout < last_seen))"; }

try {
  if ($a === 'login') {
    $email = strtolower(trim($in['email'] ?? ''));
    $u = one('SELECT *, (locked_until IS NOT NULL AND locked_until > NOW()) AS locked FROM users WHERE email=?', [$email]);
    if ($u && $u['locked']) jerr('Account temporarily locked after repeated failed attempts. Try again in 15 minutes.', 423);
    if (!$u || !$u['active'] || !password_verify($in['password'] ?? '', $u['password_hash'])) {
      if ($u) { $f = (int)$u['failed_attempts'] + 1; q('UPDATE users SET failed_attempts=?, locked_until=' . ($f >= 5 ? 'NOW()+INTERVAL 15 MINUTE' : 'locked_until') . ' WHERE id=?', [$f >= 5 ? 0 : $f, $u['id']]); }
      jerr($u && !$u['active'] ? 'This account has been deactivated.' : 'Invalid email or password.', 401);
    }
    session_regenerate_id(true);
    $_SESSION['last_activity'] = time();
    $_SESSION['uid'] = (int)$u['id']; $_SESSION['csrf'] = bin2hex(random_bytes(16));
    q('UPDATE users SET failed_attempts=0, locked_until=NULL, last_login=NOW(), last_seen=NOW(), last_logout=NULL WHERE id=?', [$u['id']]);
    q('INSERT INTO login_log(user_id) VALUES(?)', [$u['id']]);
    audit('login', 'user', $u['id']);
    jout(['ok' => true]);
  }
  if (check_timeout()) { http_response_code(401); header('Content-Type: application/json'); echo json_encode(['error' => 'You were signed out after ' . round(SESSION_TIMEOUT / 60) . ' minutes of inactivity. Please sign in again.', 'expired' => true]); exit; }
  $u = need(['admin', 'lecturer', 'hod']);
  if ($post && !hash_equals($_SESSION['csrf'] ?? '', $_SERVER['HTTP_X_CSRF'] ?? '')) jerr('Session expired, please sign in again', 403);
  // Only real use keeps the session alive: any request except a heartbeat from an idle page.
  if ($a !== 'ping' || !empty($in['active'])) { $_SESSION['last_activity'] = time(); q('UPDATE users SET last_seen=NOW() WHERE id=?', [$u['id']]); }
  finalize_attendance();
  $role = $u['role'];

  switch ($a) {
    case 'ping': jout(['ok' => true]);
    case 'change_password':   // any signed-in user, for their own account
      $row = one('SELECT password_hash FROM users WHERE id=?', [$u['id']]); $cur = $in['current'] ?? ''; $new = $in['new'] ?? '';
      if (!password_verify($cur, $row['password_hash'])) jerr('Your current password is incorrect');
      if (strlen($new) < 8) jerr('The new password must be at least 8 characters');
      if ($new === $cur) jerr('The new password must be different from the current one');
      q('UPDATE users SET password_hash=?, must_change=0 WHERE id=?', [password_hash($new, PASSWORD_DEFAULT), $u['id']]);
      audit('password_change', 'user', $u['id']); jout(['ok' => true]);
    case 'logout': end_session(); jout(['ok' => true]);

    case 'dashboard': {
      if ($role === 'admin') {
        $users = all('SELECT id,name,role,last_login,last_seen,' . online_sql() . ' AS online FROM users WHERE active=1 ORDER BY last_seen DESC');
        jout(['admin' => true, 'total' => count($users), 'online' => count(array_filter($users, fn($x) => $x['online'])),
              'admins' => (int)one("SELECT COUNT(*) n FROM users WHERE role='admin' AND active=1")['n'], 'max_admins' => MAX_ADMINS, 'users' => $users,
              'students' => (int)one('SELECT COUNT(*) n FROM students')['n'], 'unregistered' => (int)one('SELECT COUNT(*) n FROM students WHERE registered=0')['n']]);
      }
      $mods = my_modules(); $ids = array_map(fn($m) => (int)$m['id'], $mods);
      if (!$ids) jout(['counts' => ['green' => 0, 'amber' => 0, 'red' => 0], 'modules' => [], 'trend' => [], 'atrisk' => [], 'alerts' => []]);
      $in_ = implode(',', $ids);
      $rows = all("SELECT r.*, s.full_name, s.reg_number, s.registered, m.code FROM risk_state r JOIN students s ON s.id=r.student_id JOIN modules m ON m.id=r.module_id WHERE r.module_id IN ($in_)");
      $rank = ['green' => 0, 'amber' => 1, 'red' => 2]; $worst = []; $perMod = [];
      foreach ($rows as $r) {
        if (!isset($worst[$r['student_id']]) || $rank[$r['final']] > $rank[$worst[$r['student_id']]]) $worst[$r['student_id']] = $r['final'];
        $perMod[$r['module_id']][$r['final']] = ($perMod[$r['module_id']][$r['final']] ?? 0) + 1;
      }
      $counts = ['green' => 0, 'amber' => 0, 'red' => 0]; foreach ($worst as $w) $counts[$w]++;
      $ms = [];
      foreach ($mods as $m) { $c = $perMod[$m['id']] ?? []; $g = $c['green'] ?? 0; $am = $c['amber'] ?? 0; $rd = $c['red'] ?? 0;
        $ms[] = ['id' => $m['id'], 'code' => $m['code'], 'name' => $m['name'], 'lecturer' => $m['lecturer'], 'green' => $g, 'amber' => $am, 'red' => $rd];
        q('REPLACE INTO risk_trend VALUES(?,?,?,?,?)', [date('Y-m-d', strtotime('monday this week')), $m['id'], $g, $am, $rd]); }
      $atrisk = array_values(array_filter($rows, fn($r) => $r['final'] !== 'green'));
      usort($atrisk, fn($x, $y) => $rank[$y['final']] <=> $rank[$x['final']]);
      $atrisk = array_map(function ($r) { $t = json_decode($r['trace'], true)['reasons']; $why = '';
        foreach ($t as $x) if (!str_starts_with($x, 'Green') && !str_starts_with($x, 'Final')) { $why = $x; break; }
        return ['student_id' => $r['student_id'], 'module_id' => $r['module_id'], 'name' => $r['full_name'], 'reg' => $r['reg_number'], 'code' => $r['code'], 'final' => $r['final'], 'why' => $why]; }, array_slice($atrisk, 0, 12));
      $trend = all("SELECT week_start, SUM(green) green, SUM(amber) amber, SUM(red) red FROM risk_trend WHERE module_id IN ($in_) GROUP BY week_start ORDER BY week_start DESC LIMIT 7");
      $sevf = $role === 'hod' ? "AND a.severity='extreme'" : '';
      $alerts = all("SELECT a.id,a.severity,a.message,a.created_at,a.student_id,a.module_id FROM alerts a WHERE a.acknowledged=0 AND a.module_id IN ($in_) $sevf ORDER BY a.created_at DESC, a.id DESC LIMIT 30");
      jout(['counts' => $counts, 'modules' => $ms, 'trend' => array_reverse($trend), 'atrisk' => $atrisk, 'alerts' => $alerts]);
    }

    case 'dept_report': $hu = need(['hod']); $mods = my_modules(); $ids = array_map(fn($m) => (int)$m['id'], $mods);
      $fac = one('SELECT f.name FROM departments d JOIN faculties f ON f.id=d.faculty_id WHERE d.name=?', [$hu['department']]);
      $rank = ['green' => 0, 'amber' => 1, 'red' => 2]; $worst = []; $unreg = []; $per = []; $atrisk = [];
      $rows = $ids ? all('SELECT r.student_id,r.module_id,r.final,r.trace,s.full_name,s.reg_number,s.registered FROM risk_state r JOIN students s ON s.id=r.student_id WHERE r.module_id IN (' . implode(',', $ids) . ')') : [];
      $code = array_column($mods, 'code', 'id');
      foreach ($rows as $r) {
        if (!isset($worst[$r['student_id']]) || $rank[$r['final']] > $rank[$worst[$r['student_id']]]) $worst[$r['student_id']] = $r['final'];
        if (!$r['registered']) $unreg[$r['student_id']] = 1;
        $per[$r['module_id']][$r['final']] = ($per[$r['module_id']][$r['final']] ?? 0) + 1;
        if ($r['final'] !== 'green') {
          $why = ''; foreach (json_decode($r['trace'], true)['reasons'] as $x) if (!str_starts_with($x, 'Green') && !str_starts_with($x, 'Final')) { $why = $x; break; }
          $atrisk[] = ['name' => $r['full_name'], 'reg' => $r['reg_number'], 'module' => $code[$r['module_id']], 'status' => $r['final'], 'why' => $why];
        }
      }
      usort($atrisk, fn($x, $y) => [$rank[$y['status']], $x['name']] <=> [$rank[$x['status']], $y['name']]);
      $counts = ['green' => 0, 'amber' => 0, 'red' => 0]; foreach ($worst as $w) $counts[$w]++;
      $ms = []; $lecs = [];
      foreach ($mods as $m) {
        $c = $per[$m['id']] ?? []; $g = $c['green'] ?? 0; $am = $c['amber'] ?? 0; $rd = $c['red'] ?? 0; $ln = $m['lecturer'] ?: 'Unassigned';
        $ms[] = ['code' => $m['code'], 'name' => $m['name'], 'lecturer' => $ln, 'students' => $g + $am + $rd, 'green' => $g, 'amber' => $am, 'red' => $rd];
        $lecs[$ln] = $lecs[$ln] ?? ['lecturer' => $ln, 'modules' => 0, 'students' => 0, 'amber' => 0, 'red' => 0];
        $lecs[$ln]['modules']++; $lecs[$ln]['students'] += $g + $am + $rd; $lecs[$ln]['amber'] += $am; $lecs[$ln]['red'] += $rd;
      }
      audit('dept_report', 'department', $hu['department']);
      jout(['department' => $hu['department'], 'faculty' => $fac['name'] ?? '', 'generated_by' => $hu['name'], 'generated_at' => date('Y-m-d H:i'),
            'summary' => ['students' => count($worst), 'green' => $counts['green'], 'amber' => $counts['amber'], 'red' => $counts['red'], 'unregistered' => count($unreg), 'modules' => count($mods), 'lecturers' => count(array_filter($lecs, fn($l) => $l['lecturer'] !== 'Unassigned'))],
            'modules' => $ms, 'lecturers' => array_values($lecs), 'atrisk' => $atrisk]);

    case 'alerts_ack': need(['lecturer', 'hod']);
      $ids = array_map(fn($m) => (int)$m['id'], my_modules());
      if ($ids) q('UPDATE alerts SET acknowledged=1 WHERE module_id IN (' . implode(',', $ids) . ')');
      jout(['ok' => true]);

    case 'modules': need(['lecturer', 'hod']);
      $qs = strtolower(trim($_GET['q'] ?? '')); $out = [];
      foreach (my_modules() as $m) {
        if ($qs !== '' && !str_contains(strtolower($m['code'] . ' ' . $m['name']), $qs)) continue;
        $c = one("SELECT COUNT(*) n, SUM(final='red') r, SUM(final='amber') a FROM risk_state WHERE module_id=?", [$m['id']]);
        $out[] = $m + ['students' => (int)$c['n'], 'red' => (int)$c['r'], 'amber' => (int)$c['a']];
      }
      jout($out);

    case 'roster': need(['lecturer', 'hod']); $mid = (int)($_GET['module_id'] ?? 0); $m = can_module($mid);
      jout(['module' => $m, 'students' => all('SELECT s.id,s.reg_number,s.full_name,s.programme,s.registered,r.final,r.financial,r.attendance,r.academic FROM enrolments e
        JOIN students s ON s.id=e.student_id LEFT JOIN risk_state r ON r.student_id=s.id AND r.module_id=e.module_id WHERE e.module_id=? ORDER BY s.full_name', [$mid])]);

    case 'student': need(['lecturer', 'hod']);
      $sid = (int)($_GET['student_id'] ?? 0); $mid = (int)($_GET['module_id'] ?? 0); $m = can_module($mid);
      $st = one('SELECT s.* FROM students s JOIN enrolments e ON e.student_id=s.id WHERE s.id=? AND e.module_id=?', [$sid, $mid]);
      if (!$st) jerr('Student is not enrolled in this module', 404);
      $r = one('SELECT * FROM risk_state WHERE student_id=? AND module_id=?', [$sid, $mid]);
      jout(['student' => $st, 'module' => $m, 'risk' => array_merge($r, ['trace' => json_decode($r['trace'], true)]),
        'attendance' => all("SELECT s.session_date d, a.status FROM attendance a JOIN attendance_sessions s ON s.id=a.session_id WHERE a.student_id=? AND s.module_id=? ORDER BY s.session_date DESC LIMIT 20", [$sid, $mid]),
        'notes' => all('SELECT n.note_text,n.created_at,u.name lecturer FROM notes n JOIN users u ON u.id=n.lecturer_id WHERE n.student_id=? ORDER BY n.created_at DESC LIMIT 10', [$sid]),
        'generated_by' => $u['name'], 'generated_at' => date('Y-m-d H:i'), 'can_note' => $role === 'lecturer']);

    case 'note_add': need(['lecturer']); $mid = (int)($in['module_id'] ?? 0); can_module($mid);
      $t = trim($in['text'] ?? ''); if ($t === '') jerr('Write a note first');
      if (!one('SELECT 1 FROM enrolments WHERE student_id=? AND module_id=?', [(int)$in['student_id'], $mid])) jerr('Student not in module', 404);
      q('INSERT INTO notes(student_id,module_id,lecturer_id,note_text) VALUES(?,?,?,?)', [(int)$in['student_id'], $mid, $u['id'], substr($t, 0, 1000)]);
      audit('note_add', 'student', $in['student_id']); jout(['ok' => true]);

    case 'att_get': need(['lecturer']); $mid = (int)($_GET['module_id'] ?? 0); can_module($mid);
      $d = $_GET['date'] ?? date('Y-m-d'); if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) jerr('Invalid date');
      $s = one('SELECT id, created_at, created_at + INTERVAL 3 HOUR edit_until, created_at + INTERVAL 4 HOUR absent_at, NOW() < created_at + INTERVAL 3 HOUR editable, NOW() now_ts
               FROM attendance_sessions WHERE module_id=? AND session_date=?', [$mid, $d]);
      $rows = all('SELECT s.id,s.reg_number,s.full_name, a.status FROM enrolments e JOIN students s ON s.id=e.student_id
                   LEFT JOIN attendance a ON a.student_id=s.id AND a.session_id=? WHERE e.module_id=? ORDER BY s.full_name', [$s['id'] ?? 0, $mid]);
      jout(['session' => $s, 'editable' => !$s || (bool)$s['editable'], 'students' => $rows]);

    case 'att_save': need(['lecturer']); $mid = (int)($in['module_id'] ?? 0); can_module($mid);
      $d = $in['date'] ?? ''; if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) || $d > date('Y-m-d')) jerr('Choose a valid lecture date (not in the future)');
      $s = one('SELECT id, NOW() < created_at + INTERVAL 3 HOUR editable FROM attendance_sessions WHERE module_id=? AND session_date=?', [$mid, $d]);
      if ($s && !$s['editable']) jerr('This register is locked: attendance can only be edited within 3 hours of being opened.', 423);
      if (!$s) { q('INSERT INTO attendance_sessions(module_id,session_date,created_by) VALUES(?,?,?)', [$mid, $d, $u['id']]); $sid = (int)db()->lastInsertId(); } else $sid = (int)$s['id'];
      $marks = $in['marks'] ?? []; $n = 0;
      foreach (all('SELECT student_id FROM enrolments WHERE module_id=?', [$mid]) as $e) {
        $st = $marks[$e['student_id']] ?? null;
        if ($st === 'present' || $st === 'absent') { q("INSERT INTO attendance(session_id,student_id,status,marked_at) VALUES(?,?,?,NOW()) ON DUPLICATE KEY UPDATE status=VALUES(status), marked_at=NOW()", [$sid, $e['student_id'], $st]); $n++; }
        else q("INSERT IGNORE INTO attendance(session_id,student_id,status) VALUES(?,?,'pending')", [$sid, $e['student_id']]);
      }
      audit('attendance_save', 'module', "$mid@$d", null, ['marked' => $n]);
      risk_module($mid); jout(['ok' => true, 'marked' => $n]);

    case 'marks_get': need(['lecturer']); $mid = (int)($_GET['module_id'] ?? 0); can_module($mid); $sc = dept_scale($mid);
      jout(['max' => ['test' => $sc['test_max'], 'a1' => $sc['a1_max'], 'a2' => $sc['a2_max']], 'cw_min' => $sc['cw_min'],
        'students' => all('SELECT s.id,s.reg_number,s.full_name,k.test,k.a1,k.a2 FROM enrolments e JOIN students s ON s.id=e.student_id
          LEFT JOIN marks k ON k.student_id=s.id AND k.module_id=e.module_id WHERE e.module_id=? ORDER BY s.full_name', [$mid])]);

    case 'marks_save': need(['lecturer']); $mid = (int)($in['module_id'] ?? 0); can_module($mid); $bad = [];
      foreach ($in['rows'] ?? [] as $r) {
        if (!one('SELECT 1 FROM enrolments WHERE student_id=? AND module_id=?', [(int)$r['student_id'], $mid])) continue;
        if (!save_marks($mid, (int)$r['student_id'], num($r['test'] ?? null), num($r['a1'] ?? null), num($r['a2'] ?? null))) $bad[] = $r['student_id'];
      }
      if ($bad) { risk_module($mid); jerr('Some marks were out of range and were not saved (check each mark against its maximum).'); }
      risk_module($mid); jout(['ok' => true]);

    case 'marks_import': need(['lecturer']); $mid = (int)($in['module_id'] ?? 0); can_module($mid); $ok = 0; $skipped = [];
      foreach ($in['rows'] ?? [] as $i => $r) {
        $reg = trim((string)($r['reg'] ?? '')); $st = one('SELECT s.id FROM students s JOIN enrolments e ON e.student_id=s.id WHERE s.reg_number=? AND e.module_id=?', [$reg, $mid]);
        if (!$st) { $skipped[] = "Row " . ($i + 2) . ": $reg not enrolled in this module"; continue; }
        if (save_marks($mid, (int)$st['id'], num($r['test'] ?? null), num($r['assignment1'] ?? null), num($r['assignment2'] ?? null))) $ok++; else $skipped[] = "Row " . ($i + 2) . ": $reg has a mark outside its allowed range";
      }
      audit('marks_import', 'module', $mid, null, ['imported' => $ok, 'skipped' => count($skipped)]);
      risk_module($mid); jout(['imported' => $ok, 'skipped' => $skipped]);

    // ---------------- Admin ----------------
    case 'users_list': need(['admin']);
      $UD = []; foreach (all('SELECT user_id,department FROM user_departments ORDER BY department') as $x) $UD[$x['user_id']][] = $x['department'];
      jout(['admins' => (int)one("SELECT COUNT(*) n FROM users WHERE role='admin' AND active=1")['n'], 'max_admins' => MAX_ADMINS, 'domains' => (require __DIR__ . '/inc/config.php')['allowed_email_domains'] ?? ['staff.msu.ac.zw'],
        'departments' => all('SELECT name FROM departments ORDER BY name'),
        'users' => array_map(fn($x) => $x + ['departments' => $UD[$x['id']] ?? []], all('SELECT id,name,email,role,department,active,must_change,(locked_until IS NOT NULL AND locked_until > NOW()) AS locked,last_login,last_seen,created_at,' . online_sql() . ' AS online FROM users ORDER BY role,name'))]);

    case 'user_create': need(['admin']);
      $name = trim($in['name'] ?? ''); $email = strtolower(trim($in['email'] ?? '')); $r = $in['role'] ?? ''; $pw = $in['password'] ?? '';
      if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($r, ['admin', 'lecturer', 'hod'], true)) jerr('Enter a name, a valid email and a role');
      if (strlen($pw) < 8) jerr('Password must be at least 8 characters');
      $allowed = (require __DIR__ . '/inc/config.php')['allowed_email_domains'] ?? ['staff.msu.ac.zw'];
      if (!in_array(substr(strrchr($email, '@'), 1), $allowed, true)) jerr('Only university email addresses are accepted (@' . implode(' or @', $allowed) . ').');
      $deps = read_departments($in, $r); $dep = $deps[0] ?? '';
      if ($r === 'admin' && (int)one("SELECT COUNT(*) n FROM users WHERE role='admin' AND active=1")['n'] >= MAX_ADMINS) jerr('The maximum of ' . MAX_ADMINS . ' administrators has been reached.');
      if (one('SELECT 1 FROM users WHERE email=?', [$email])) jerr('That email is already in use');
      q('INSERT INTO users(name,email,password_hash,role,department) VALUES(?,?,?,?,?)', [$name, $email, password_hash($pw, PASSWORD_DEFAULT), $r, $dep]); $newId = (int)db()->lastInsertId();
      set_user_departments($newId, $deps);
      audit('user_create', 'user', $newId, null, ['email' => $email, 'role' => $r, 'departments' => $deps]); jout(['ok' => true]);

    case 'user_update': need(['admin']); $id = (int)($in['id'] ?? 0); $t = one('SELECT * FROM users WHERE id=?', [$id]);
      if (!$t) jerr('User not found', 404);
      $name = trim($in['name'] ?? ''); $email = strtolower(trim($in['email'] ?? '')); $r = $in['role'] ?? ''; $dep = trim($in['department'] ?? '');
      if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($r, ['admin', 'lecturer', 'hod'], true)) jerr('Enter a name, a valid email and a role');
      check_email_domain($email);
      if ($id === (int)$u['id'] && $r !== $t['role']) jerr('You cannot change your own role');
      if ($r === 'admin' && $t['role'] !== 'admin' && $t['active'] && (int)one("SELECT COUNT(*) n FROM users WHERE role='admin' AND active=1")['n'] >= MAX_ADMINS) jerr('The maximum of ' . MAX_ADMINS . ' administrators has been reached.');
      $deps = read_departments($in, $r); 
      if (one('SELECT 1 FROM users WHERE email=? AND id<>?', [$email, $id])) jerr('That email is already in use');
      if ($r === 'lecturer') $deps = array_values(array_unique(array_merge($deps, array_column(all('SELECT DISTINCT department FROM modules WHERE lecturer_id=? AND department<>""', [$id]), 'department'))));   // a lecturer stays attached to every department where they still teach
      $dep = $deps[0] ?? '';
      q('UPDATE users SET name=?, email=?, role=?, department=? WHERE id=?', [$name, $email, $r, $dep, $id]);
      $oldDeps = array_column(all('SELECT department FROM user_departments WHERE user_id=? ORDER BY department', [$id]), 'department'); set_user_departments($id, $deps);   // same row: notes, modules, history all stay
      audit('user_update', 'user', $id, ['name' => $t['name'], 'email' => $t['email'], 'role' => $t['role'], 'departments' => $oldDeps], ['name' => $name, 'email' => $email, 'role' => $r, 'departments' => $deps]);
      jout(['ok' => true]);

    case 'user_reset_password': need(['admin']); $id = (int)($in['id'] ?? 0); $t = one('SELECT id FROM users WHERE id=?', [$id]); $pw = $in['password'] ?? '';
      if (!$t) jerr('User not found', 404);
      if ($id === (int)$u['id']) jerr('Use "Change password" for your own account');
      if (strlen($pw) < 8) jerr('The temporary password must be at least 8 characters');
      q('UPDATE users SET password_hash=?, must_change=1, failed_attempts=0, locked_until=NULL WHERE id=?', [password_hash($pw, PASSWORD_DEFAULT), $id]);
      audit('password_reset', 'user', $id); jout(['ok' => true]);   // the password itself is never logged

    case 'user_toggle': need(['admin']); $id = (int)($in['id'] ?? 0); $t = one('SELECT * FROM users WHERE id=?', [$id]);
      if (!$t) jerr('User not found', 404); if ($id === (int)$u['id']) jerr('You cannot deactivate your own account');
      if (!$t['active'] && $t['role'] === 'admin' && (int)one("SELECT COUNT(*) n FROM users WHERE role='admin' AND active=1")['n'] >= MAX_ADMINS) jerr('The maximum of ' . MAX_ADMINS . ' administrators has been reached.');
      q('UPDATE users SET active=1-active WHERE id=?', [$id]); audit('user_toggle', 'user', $id, $t['active'], 1 - $t['active']); jout(['ok' => true]);

    case 'students_list': need(['admin']);
      jout(all('SELECT s.id,s.reg_number,s.full_name,s.programme,s.registered,f.name faculty FROM students s LEFT JOIN programmes p ON p.id=s.programme_id LEFT JOIN faculties f ON f.id=p.faculty_id ORDER BY s.full_name'));

    case 'registration_set': need(['admin']);
      foreach ($in['rows'] ?? [] as $r) {
        $st = isset($r['id']) ? one('SELECT * FROM students WHERE id=?', [(int)$r['id']]) : one('SELECT * FROM students WHERE reg_number=?', [trim((string)($r['reg'] ?? ''))]);
        if (!$st) { $skip[] = $r['reg'] ?? $r['id']; continue; }
        $v = filter_var($r['registered'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        if ((int)$st['registered'] !== $v) { q('UPDATE students SET registered=? WHERE id=?', [$v, $st['id']]); audit('registration_change', 'student', $st['id'], $st['registered'], $v); risk_student((int)$st['id']); }
      }
      jout(['ok' => true, 'skipped' => $skip ?? []]);

    case 'faculties_list': need(['admin']);
      jout(all('SELECT f.*, (SELECT COUNT(*) FROM departments d WHERE d.faculty_id=f.id) depts, (SELECT COUNT(*) FROM programmes p WHERE p.faculty_id=f.id) programmes,
        (SELECT COUNT(*) FROM students s JOIN programmes p ON p.id=s.programme_id WHERE p.faculty_id=f.id) students FROM faculties f ORDER BY f.name'));
    case 'faculty_save': need(['admin']); $id = (int)($in['id'] ?? 0); $f = one('SELECT * FROM faculties WHERE id=?', [$id]); $v = [];
      if (!$f) jerr('Faculty not found', 404);
      foreach (['test_max', 'a1_max', 'a2_max', 'cw_min'] as $k) { if (!isset($in[$k]) || !is_numeric($in[$k]) || $in[$k] < 0) jerr('Enter a valid number for every mark field'); $v[$k] = (float)$in[$k]; }
      $tot = $v['test_max'] + $v['a1_max'] + $v['a2_max'];
      if ($tot <= 0) jerr('The coursework total must be above zero');
      if ($v['cw_min'] > $tot) jerr('The minimum coursework mark cannot be more than the coursework total (' . $tot . ')');
      q('UPDATE faculties SET test_max=?,a1_max=?,a2_max=?,cw_min=? WHERE id=?', [$v['test_max'], $v['a1_max'], $v['a2_max'], $v['cw_min'], $id]);
      q('UPDATE departments SET test_max=?,a1_max=?,a2_max=?,cw_min=? WHERE faculty_id=?', [$v['test_max'], $v['a1_max'], $v['a2_max'], $v['cw_min'], $id]);   // departments follow their faculty
      audit('faculty_scale_update', 'faculty', $f['name'], ['test_max' => $f['test_max'], 'a1_max' => $f['a1_max'], 'a2_max' => $f['a2_max'], 'cw_min' => $f['cw_min']], $v);
      risk_all(); jout(['ok' => true]);
    case 'departments_list': need(['admin']);
      jout(all('SELECT d.id,d.name,f.name faculty,d.test_max,d.a1_max,d.a2_max,d.cw_min FROM departments d LEFT JOIN faculties f ON f.id=d.faculty_id ORDER BY f.name,d.name'));
    case 'department_save': need(['admin']); $name = trim($in['name'] ?? ''); $f = one('SELECT * FROM faculties WHERE id=?', [(int)($in['faculty_id'] ?? 0)]);
      if ($name === '' || strlen($name) > 120) jerr('Enter a department name');
      if (!$f) jerr('Choose a faculty');
      $d = one('SELECT id FROM departments WHERE name=?', [$name]);
      if ($d) { q('UPDATE departments SET faculty_id=?,test_max=?,a1_max=?,a2_max=?,cw_min=? WHERE id=?', [$f['id'], $f['test_max'], $f['a1_max'], $f['a2_max'], $f['cw_min'], $d['id']]); audit('department_move', 'department', $name, null, $f['name']); risk_all(); }
      else { q('INSERT INTO departments(name,faculty_id,test_max,a1_max,a2_max,cw_min) VALUES(?,?,?,?,?,?)', [$name, $f['id'], $f['test_max'], $f['a1_max'], $f['a2_max'], $f['cw_min']]); audit('department_create', 'department', $name, null, $f['name']); }
      jout(['ok' => true]);

    case 'modules_admin': need(['admin']);
      jout(['modules' => all('SELECT m.id,m.code,m.name,m.department,m.lecturer_id,(SELECT COUNT(*) FROM enrolments e WHERE e.module_id=m.id) students FROM modules m ORDER BY m.code'),
            'lecturers' => all("SELECT id,name,department FROM users WHERE role='lecturer' AND active=1 ORDER BY name"),
            'departments' => all('SELECT name FROM departments ORDER BY name'),
            'programmes' => all('SELECT id,name FROM programmes ORDER BY name')]);
    case 'module_save': need(['admin']); $id = (int)($in['id'] ?? 0); $lec = ($in['lecturer_id'] ?? '') === '' ? null : (int)$in['lecturer_id'];
      if ($lec !== null && !one("SELECT 1 FROM users WHERE id=? AND role='lecturer' AND active=1", [$lec])) jerr('Choose an active lecturer');
      if ($id) {
        $m = one('SELECT * FROM modules WHERE id=?', [$id]); if (!$m) jerr('Module not found', 404);
        q('UPDATE modules SET lecturer_id=? WHERE id=?', [$lec, $id]);
        if ($lec !== null && $m['department']) q('INSERT IGNORE INTO user_departments(user_id,department) VALUES(?,?)', [$lec, $m['department']]);   // lecturer now also teaches in this department
        audit('module_lecturer', 'module', $m['code'], $m['lecturer_id'], $lec); jout(['ok' => true]);
      }
      $code = strtoupper(trim($in['code'] ?? '')); $name = trim($in['name'] ?? ''); $dep = trim($in['department'] ?? ''); $pid = (int)($in['programme_id'] ?? 0);
      if (!preg_match('/^[A-Z0-9]{3,20}$/', $code)) jerr('Module code must be 3-20 letters or digits, e.g. IS201');
      if ($name === '') jerr('Enter a module name');
      if (!one('SELECT 1 FROM departments WHERE name=?', [$dep])) jerr('Choose a department');
      if (one('SELECT 1 FROM modules WHERE code=?', [$code])) jerr('That module code already exists');
      q('INSERT INTO modules(code,name,lecturer_id,department,programme_id) VALUES(?,?,?,?,?)', [$code, $name, $lec, $dep, $pid ?: null]); $mid = (int)db()->lastInsertId();
      if ($pid) q('INSERT IGNORE INTO enrolments(student_id,module_id) SELECT id, ? FROM students WHERE programme_id=?', [$mid, $pid]);
      if ($lec !== null) q('INSERT IGNORE INTO user_departments(user_id,department) VALUES(?,?)', [$lec, $dep]);
      risk_module($mid); audit('module_create', 'module', $code, null, ['department' => $dep, 'programme_id' => $pid]); jout(['ok' => true]);

    case 'settings_get': need(['admin']); jout(settings());
    case 'settings_save': need(['admin']); $S = settings(); $new = [];
      foreach (['att_amber', 'att_red', 'cw_min', 'amber_pct', 'test_max', 'a1_max', 'a2_max', 'escalation'] as $k) {
        if (!isset($in[$k]) || !is_numeric($in[$k]) || $in[$k] < 0) jerr("Invalid value for $k");
        $new[$k] = $in[$k];
      }
      if ($new['att_amber'] >= $new['att_red']) jerr('Attendance amber threshold must be lower than the red threshold');
      foreach ($new as $k => $v) if ((string)$S[$k] !== (string)$v) { q('UPDATE settings SET v=? WHERE k=?', [$v, $k]); audit('threshold_change', 'settings', $k, $S[$k], $v); }
      settings(true); risk_all(); // apply new thresholds to every student immediately
      jout(['ok' => true]);

    case 'audit_list': need(['admin']);
      jout(all('SELECT id,created_at,user_name,action,entity,entity_id,old_value,new_value FROM audit_log ORDER BY id DESC LIMIT 300'));
    case 'audit_csv': need(['admin']); audit('audit_download', 'audit_log');
      csv_out('audit-log-' . date('Ymd-His') . '.csv', ['ID', 'Time', 'User', 'Action', 'Entity', 'Entity ID', 'Old value', 'New value', 'IP'],
        array_map(fn($r) => [$r['id'], $r['created_at'], $r['user_name'], $r['action'], $r['entity'], $r['entity_id'], $r['old_value'], $r['new_value'], $r['ip']],
                  all('SELECT * FROM audit_log ORDER BY id')));

    case 'usage_periods': need(['admin']);
      $start = strtotime(settings()['system_start']); $out = []; $now = time();
      for ($p = 1; $start + ($p - 1) * 49 * 86400 <= $now; $p++) {
        $s = $start + ($p - 1) * 49 * 86400; $e = $s + 49 * 86400;
        $out[] = ['period' => $p, 'from' => date('Y-m-d', $s), 'to' => date('Y-m-d', $e - 86400), 'complete' => $now >= $e];
      }
      jout(array_reverse($out));
    case 'usage_csv': need(['admin']); $p = max(1, (int)($_GET['p'] ?? 1));
      $s = strtotime(settings()['system_start']) + ($p - 1) * 49 * 86400; $e = $s + 49 * 86400;
      if ($s > time()) jerr('That period has not started yet', 404);
      $partial = time() < $e;   // downloadable any time: an unfinished period is reported up to now
      $f = date('Y-m-d H:i:s', $s); $t = $partial ? date('Y-m-d H:i:s') : date('Y-m-d H:i:s', $e);
      $label = date('Y-m-d', $s) . ' to ' . ($partial ? date('Y-m-d') . ' (to date)' : date('Y-m-d', $e - 86400));
      $rows = all('SELECT u.name,u.email,u.role,u.last_login,
          (SELECT COUNT(*) FROM login_log l WHERE l.user_id=u.id AND l.created_at>=? AND l.created_at<=?) logins,
          (SELECT COUNT(*) FROM audit_log a WHERE a.user_id=u.id AND a.action NOT IN ("login","logout") AND a.created_at>=? AND a.created_at<=?) actions
          FROM users u ORDER BY u.role,u.name', [$f, $t, $f, $t]);
      audit('usage_report_download', 'usage', $p . ($partial ? ' (to date)' : ''));
      csv_out("usage-report-period-$p" . ($partial ? '-to-date' : '') . '.csv', ['Period', 'Name', 'Email', 'Role', 'Logins in period', 'Actions in period', 'Last login'],
        array_map(fn($r) => [$label, $r['name'], $r['email'], $r['role'], $r['logins'], $r['actions'], $r['last_login']], $rows));

    default: jerr('Unknown action', 404);
  }
} catch (Throwable $e) { jerr('Server error: ' . $e->getMessage(), 500); }
