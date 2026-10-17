<?php
// Rule-based risk engine. All thresholds come from the `settings` table (admin-editable).

// Unmarked students become ABSENT 4 hours after the register was opened.
function finalize_attendance(): void {
  $rows = all("SELECT DISTINCT a.student_id, s.module_id FROM attendance a JOIN attendance_sessions s ON s.id=a.session_id
               WHERE a.status='pending' AND s.created_at <= NOW() - INTERVAL 4 HOUR");
  if (!$rows) return;
  q("UPDATE attendance a JOIN attendance_sessions s ON s.id=a.session_id SET a.status='absent', a.marked_at=NOW()
     WHERE a.status='pending' AND s.created_at <= NOW() - INTERVAL 4 HOUR");
  foreach ($rows as $r) risk_save((int)$r['student_id'], (int)$r['module_id']);
}

// Coursework scale for a module: its department's own scale, else the global defaults.
function dept_scale(int $mid): array {
  $S = settings();
  $d = one('SELECT d.test_max,d.a1_max,d.a2_max,d.cw_min FROM modules m JOIN departments d ON d.name=m.department WHERE m.id=?', [$mid]);
  return $d ? array_map('floatval', $d)
            : ['test_max' => (float)$S['test_max'], 'a1_max' => (float)$S['a1_max'], 'a2_max' => (float)$S['a2_max'], 'cw_min' => (float)$S['cw_min']];
}

function risk_compute(int $sid, int $mid): array {
  $S = settings();
  $st = one('SELECT registered FROM students WHERE id=?', [$sid]);
  $reasons = []; $m = [];

  // Financial: not registered => always RED.
  $registered = $st && (int)$st['registered'] === 1;
  $fin = $registered ? 'green' : 'red';
  $reasons[] = $registered ? 'Green (financial): student is registered for the current semester.'
                           : 'Red (financial): student is NOT registered for the current semester.';

  // Behavioural: trailing run of consecutive missed lectures.
  $att = all("SELECT a.status FROM attendance a JOIN attendance_sessions s ON s.id=a.session_id
              WHERE a.student_id=? AND s.module_id=? AND a.status IN ('present','absent') ORDER BY s.session_date, s.id", [$sid, $mid]);
  $streak = 0; $present = 0; $absent = 0;
  foreach ($att as $r) {
    if ($r['status'] === 'absent') { $absent++; $streak++; } else { $present++; $streak = 0; }
  }
  $m['sessions'] = count($att); $m['present'] = $present; $m['absent'] = $absent; $m['consecutive_missed'] = $streak;
  $m['attendance_pct'] = $att ? round($present / count($att) * 100) : null;
  $amb = (int)$S['att_amber']; $red = (int)$S['att_red'];
  if ($streak >= $red) { $a = 'red'; $reasons[] = "Red (attendance): $streak consecutive lectures missed (limit $red)."; }
  elseif ($streak >= $amb) { $a = 'amber'; $reasons[] = "Amber (attendance): $streak consecutive lectures missed (amber from $amb)."; }
  else { $a = 'green'; $reasons[] = "Green (attendance): $streak consecutive lectures missed."; }

  // Academic: coursework = test + assignment 1 + assignment 2.
  $mk = one('SELECT test,a1,a2 FROM marks WHERE student_id=? AND module_id=?', [$sid, $mid]);
  $ac = 'green';
  $sc = dept_scale($mid);
  $max = ['test' => $sc['test_max'], 'a1' => $sc['a1_max'], 'a2' => $sc['a2_max']];
  $total = array_sum($max); $m['coursework_total'] = $total; $m['coursework'] = null;
  $m['marks'] = $mk; $m['coursework_min'] = $sc['cw_min'];
  if (!$mk || ($mk['test'] === null && $mk['a1'] === null && $mk['a2'] === null)) {
    $reasons[] = 'Green (academic): no coursework marks recorded yet.';
  } else {
    $cw = (float)$mk['test'] + (float)$mk['a1'] + (float)$mk['a2'];
    $complete = $mk['test'] !== null && $mk['a1'] !== null && $mk['a2'] !== null;
    $m['coursework'] = $cw; $m['coursework_complete'] = $complete;
    $low = [];
    foreach (['test' => 'Test', 'a1' => 'Assignment 1', 'a2' => 'Assignment 2'] as $k => $lbl)
      if ($mk[$k] !== null && $max[$k] > 0 && $mk[$k] / $max[$k] * 100 < (float)$S['amber_pct']) $low[] = "$lbl (" . round($mk[$k] / $max[$k] * 100) . '%)';
    if ($complete && $cw < $sc['cw_min']) {
      $ac = 'red'; $reasons[] = "Red (academic): coursework is $cw/$total, below the minimum of {$sc['cw_min']}.";
    } elseif ($low) {
      $ac = 'amber'; $reasons[] = 'Amber (academic): ' . implode(', ', $low) . " below {$S['amber_pct']}% of the available mark.";
    } else {
      $reasons[] = "Green (academic): coursework is $cw/$total.";
    }
  }

  // Combine: any red => red; 2+ ambers => red (if escalation on); any amber => amber.
  $subs = [$fin, $a, $ac];
  $nr = count(array_keys($subs, 'red')); $na = count(array_keys($subs, 'amber'));
  if (!$registered) { $final = 'red'; $reasons[] = 'Final: RED, because an unregistered student is always high risk.'; }
  elseif ($nr) { $final = 'red'; $reasons[] = 'Final: RED, because at least one factor is red.'; }
  elseif ($na >= 2 && (int)$S['escalation'] === 1) { $final = 'red'; $reasons[] = 'Final: RED, because two or more factors are amber (compounding risk).'; }
  elseif ($na) { $final = 'amber'; $reasons[] = 'Final: AMBER, because one factor is amber.'; }
  else { $final = 'green'; $reasons[] = 'Final: GREEN, no risk factors detected.'; }

  return ['financial' => $fin, 'attendance' => $a, 'academic' => $ac, 'final' => $final, 'reds' => $nr,
          'trace' => ['reasons' => $reasons, 'metrics' => $m]];
}

function risk_save(int $sid, int $mid): void {
  $r = risk_compute($sid, $mid);
  $old = one('SELECT final FROM risk_state WHERE student_id=? AND module_id=?', [$sid, $mid]);
  q('REPLACE INTO risk_state(student_id,module_id,financial,attendance,academic,final,trace,computed_at) VALUES(?,?,?,?,?,?,?,NOW())',
    [$sid, $mid, $r['financial'], $r['attendance'], $r['academic'], $r['final'], json_encode($r['trace'])]);
  $rank = ['green' => 0, 'amber' => 1, 'red' => 2];
  if ($r['final'] !== 'green' && $rank[$r['final']] > ($old ? $rank[$old['final']] : 0)) {
    $sev = $r['final'] === 'red' ? ($r['reds'] >= 2 ? 'extreme' : 'red') : 'amber';
    $who = one('SELECT full_name,reg_number FROM students WHERE id=?', [$sid]);
    $mod = one('SELECT code FROM modules WHERE id=?', [$mid]);
    $why = $r['trace']['reasons'][0];
    foreach ($r['trace']['reasons'] as $x) if (!str_starts_with($x, 'Green') && !str_starts_with($x, 'Final')) { $why = $x; break; }
    q('INSERT INTO alerts(student_id,module_id,severity,message) VALUES(?,?,?,?)',
      [$sid, $mid, $sev, "{$who['full_name']} ({$who['reg_number']}) is now " . strtoupper($r['final']) . " in {$mod['code']}. $why"]);
  }
}
function risk_student(int $sid): void { foreach (all('SELECT module_id FROM enrolments WHERE student_id=?', [$sid]) as $e) risk_save($sid, (int)$e['module_id']); }
function risk_module(int $mid): void { foreach (all('SELECT student_id FROM enrolments WHERE module_id=?', [$mid]) as $e) risk_save((int)$e['student_id'], $mid); }
function risk_all(): void { foreach (all('SELECT student_id,module_id FROM enrolments') as $e) risk_save((int)$e['student_id'], (int)$e['module_id']); }
