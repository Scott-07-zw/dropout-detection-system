<?php
// Run once: http://localhost/msu-dps/setup.php   (add ?reset=1 to wipe and re-seed)
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/risk.php';
$c = require __DIR__ . '/inc/config.php';
header('Content-Type: text/html; charset=utf-8');
set_time_limit(0); ignore_user_abort(true); // seeding can be slow on some disks
try {
  $root = new PDO("mysql:host={$c['db_host']};charset=utf8mb4", $c['db_user'], $c['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
  $root->exec("CREATE DATABASE IF NOT EXISTS `{$c['db_name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
} catch (Throwable $e) { die('<h2>Cannot reach MySQL</h2><p>Start MySQL in the XAMPP Control Panel and check inc/config.php.<br><code>' . htmlspecialchars($e->getMessage()) . '</code></p>'); }

$db = db();
if (isset($_GET['reset'])) {
  $db->exec('SET FOREIGN_KEY_CHECKS=0');
  foreach (all('SHOW TABLES') as $t) $db->exec('DROP TABLE `' . array_values($t)[0] . '`');
  $db->exec('SET FOREIGN_KEY_CHECKS=1');
}

$STRUCT = [
 'Business Sciences' => [30, [
   'Accounting Sciences' => ['Accounting', 'Applied Accounting', 'Audit and Risk Management'],
   'Management Sciences' => ['Business Management', 'Human Resource Management'],
   'Information and Marketing Sciences' => ['Consumer and Marketing Intelligence', 'Information Systems'],
   'Supply Chain, Insurance and Risk Sciences' => ['Logistics and Supply Chain Management', 'Risk Management and Insurance'],
   'Economic Sciences' => ['Economics', 'Financial Economics and Investment'],
   'Tourism, Hospitality and Leisure Sciences' => ['Tourism and Hospitality Management', 'Sustainable Tourism Management and Planning'],
   'Centre for Entrepreneurship and Innovation' => ['Entrepreneurship and Business Development']]],
 'Science and Technology' => [30, ['Science and Technology' => ['Computer Science and Emerging Technologies', 'Software Engineering', 'Artificial Intelligence and Machine Learning', 'Cybersecurity', 'Information Security', 'Cloud Computing and Internet of Things (IoT)', 'Data Science and Informatics', 'Applied Sciences and Nanotechnology', 'Industrial Chemistry', 'Medicinal and Pharmaceutical Chemistry', 'Mathematics and Applied Statistics', 'Biotechnology and Bioinformatics', 'Forensic Science']]],
 'Engineering and Geosciences' => [40, ['Engineering and Geosciences' => ['Telecommunications Engineering', 'Fuels and Energy Engineering', 'Applied Geology', 'Biosystems Engineering']]],
 'Agriculture, Environment and Natural Resources Management' => [40, ['Agriculture, Environment and Natural Resources Management' => ['Agricultural Economics and Development', 'Agricultural Value Chain and Marketing', 'Agricultural Water Management', 'Agriculture and Environment Management', 'Applied Agricultural Sciences and Business', 'Animal and Wildlife Sciences', 'Crop Production and Horticulture', 'Crop Protection', 'Crop Improvement and Seed Systems', 'Dairy Sciences', 'Poultry Science', 'Fisheries and Aquaculture Sciences', 'Wildlife Ecology and Management', 'Environmental Disaster Risk Management and Resilience Building', 'Geography and Environmental Sustainability', 'Information Technology for Agricultural Systems']]],
 'Arts and Humanities' => [40, ['Arts and Humanities' => ['Development Practice', 'Development Planning and Management', 'Applied Linguistics', 'Language and Cultural Studies', 'Translation and Intercultural Communication', 'History and International Studies', 'Records and Archives Management', 'Archival and Historical Information Management', 'Radio, Television and Multimedia Production', 'Multimedia, Graphic Designing and Editing', 'Media, Animation and Artificial Intelligence', 'Religious Studies and Human Development', 'Monitoring and Evaluation']]],
 'Education' => [40, ['Education' => ['Primary Education and Management Practices', 'Inclusive Education and Assistive Technologies', 'Computer Science and Emerging Technologies Education', 'Biology and Applied Technologies Education', 'Chemistry and Applied Technologies Education', 'Physics and Applied Technologies Education', 'Mathematics and Applied Statistics Education', 'Food Technology and Design Education', 'Textile Technology and Design Education', 'Business Studies and Enterprise Development Education', 'Economics and Development Policy Education', 'Indigenous Languages, Knowledge Systems and Multiculturalism Education', 'Philosophy and Critical Thinking Education', 'Physical Education, Sports Science and Management Education', 'Psychology and Intervention Designs Education', 'Sociology, Heritage and Civic Studies Education', 'Educational Leadership, Management and Development']]],
 'Social Sciences' => [30, ['Social Sciences' => ['Psychology', 'Behavioural Psychology', 'Health Psychology', 'Organisational and Industrial Psychology', 'Industrial Sociology', 'Sociology and Social Policy', 'Social Work', 'Peace, Human Rights and Social Justice', 'Peace, Leadership and Governance', 'Human Rights and Social Justice']]],
 'Law' => [30, ['Law' => ['Law', 'Legal Studies']]],
 'Medicine and Health Sciences' => [30, ['Medicine and Health Sciences' => ['Medicine and Health-Related Degrees', 'Health Care Management']]],
 'Built Environment, Art and Design' => [40, ['Built Environment, Art and Design' => ['Surveying and Geomatics', 'Geomatics (Geographical Information Science)', 'Geomatics (Land Surveying)']]],
];
const FIRST = ['Tendai','Tatenda','Farai','Rudo','Chipo','Tafara','Nyasha','Anesu','Kudzai','Tinashe','Ruvimbo','Vimbai','Shamiso','Rutendo','Tapiwa','Panashe','Munyaradzi','Blessing','Memory','Precious','Prudence','Ngonidzashe','Takudzwa','Simbarashe','Chiedza','Tsitsi','Nokuthula','Thandiwe','Sipho','Themba','Lindiwe','Bongani','Nomusa','Mthulisi','Siphiwe','Thabo','Zodwa','Busisiwe','Sibusiso','Nkosana','Nomalanga','Gift','Joy','Kuda','Brenda','Tracy','Brian','Lloyd','Natasha','Tonderai','Trevor','Patience','Mercy','Fadzai','Kelvin','Tanaka','Tariro','Tawanda','Wellington','Kundai','Rumbidzai','Yeukai','Chenai','Danai','Fungai','Nyaradzo','Ropafadzo','Anotida','Tadiwa','Tanyaradzwa','Ashleigh','Kimberly','Ethel','Hazvinei','Joyline','Loveness','Shingirai','Talent','Tawana','Vongai','Wadzanai','Albert','Cephas','Elvis','Garikai','Innocent','Lovemore','Martin','Nelson','Pardon','Raymond','Taurai','Victor','Webster','Admire','Austin','Batsirai','Dumisani','Edmore'];
const LAST = ['Moyo','Ncube','Sibanda','Dube','Ndlovu','Nkomo','Mpofu','Mlambo','Khumalo','Mhlanga','Chikwanda','Dzingai','Mhuri','Sithole','Manyika','Zvobgo','Chuma','Mapfumo','Gumbo','Marufu','Madziva','Chirwa','Hove','Banda','Mutasa','Chitiyo','Zhou','Gwasira','Chigumba','Mutsvairo','Mudzingwa','Murombo','Mushonga','Mutendi','Nyathi','Makoni','Mangwende','Matimba','Chidzero','Charamba','Chivasa','Shumba','Tembo','Zimuto','Zinyama','Mavhunga','Mazarura','Mukucha','Munetsi','Mupfumi','Mutizwa','Nhamo','Nyoni','Ruzvidzo','Chimbira','Gondo','Gwata','Hlongwane','Jongwe','Kanengoni','Kativu','Machingura','Madondo','Maphosa','Marange','Masuku','Mavuso','Mhike','Moyo-Ndlovu','Mukwena','Nyamukapa','Pfumojena','Rusike','Sakupwanya','Tshuma','Zhuwao'];

function link_modules(): void { q("UPDATE modules m JOIN programmes p ON m.name = CONCAT('Introduction to ', p.name) SET m.programme_id = p.id WHERE m.programme_id IS NULL"); }

// Each degree programme does about 4 modules a semester: make sure every programme has 4, with all its students enrolled.
function ensure_modules(int $per = 4): void {
  $db = db(); $used = [];
  foreach (all('SELECT code FROM modules') as $m) $used[$m['code']] = 1;
  foreach (all('SELECT p.id,p.name,d.name dept FROM programmes p JOIN departments d ON d.id=p.department_id ORDER BY p.id') as $p) {
    $have = (int)one('SELECT COUNT(*) n FROM modules WHERE programme_id=?', [$p['id']])['n'];
    if ($have >= $per) continue;
    $ini = ''; foreach (preg_split('/[^A-Za-z]+/', $p['name'], -1, PREG_SPLIT_NO_EMPTY) as $w) if (strlen($w) > 3) $ini .= strtoupper($w[0]);
    if (strlen($ini) < 3) $ini = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $p['name']), 0, 3));
    $ini = substr($ini, 0, 4); $n = 101;
    for ($i = $have + 1; $i <= $per; $i++) {
      while (isset($used[$ini . $n])) $n++;
      $code = $ini . $n; $used[$code] = 1;
      q('INSERT INTO modules(code,name,lecturer_id,department,programme_id) VALUES(?,?,NULL,?,?)', [$code, $i === 1 ? 'Introduction to ' . $p['name'] : $p['name'] . ' Module ' . $i, $p['dept'], $p['id']]);
      q('INSERT IGNORE INTO enrolments(student_id,module_id) SELECT id, ? FROM students WHERE programme_id=?', [$db->lastInsertId(), $p['id']]);
    }
  }
}

// A lecturer can teach in several departments: keep user_departments in step with home department + assigned modules.
function backfill_user_departments(): void {
  q("INSERT IGNORE INTO user_departments(user_id,department) SELECT id,department FROM users WHERE role<>'admin' AND department<>''");
  q("INSERT IGNORE INTO user_departments(user_id,department) SELECT DISTINCT lecturer_id,department FROM modules WHERE lecturer_id IS NOT NULL AND department IS NOT NULL AND department<>''");
}
function link_programmes(): void { q('UPDATE students s JOIN programmes p ON p.name = s.programme SET s.programme_id = p.id WHERE s.programme_id IS NULL'); }

// Faculties -> departments -> programmes, plus 16-20 generated students per programme (modules: see ensure_modules).
function seed_structure(): void {
  global $STRUCT; $db = db(); $seq = 0; $probe = 0; $seen = []; $pi = 0; $used = [];
  foreach (all('SELECT code FROM modules') as $m) $used[$m['code']] = 1;
  foreach (all('SELECT full_name FROM students') as $r) $seen[$r['full_name']] = 1;   // never repeat an existing student's name
  foreach ($STRUCT as $fname => [$total, $depts]) {
    $sc = $total === 40 ? [20, 10, 10, 24] : [15, 7.5, 7.5, 18];   // test, assignment 1, assignment 2, minimum (60%)
    q('INSERT INTO faculties(name,test_max,a1_max,a2_max,cw_min) VALUES(?,?,?,?,?)', array_merge([$fname], $sc)); $fid = (int)$db->lastInsertId();
    foreach ($depts as $dname => $progs) {
      $d = one('SELECT id FROM departments WHERE name=?', [$dname]);
      if ($d) { q('UPDATE departments SET faculty_id=?,test_max=?,a1_max=?,a2_max=?,cw_min=? WHERE id=?', array_merge([$fid], $sc, [$d['id']])); $did = (int)$d['id']; }
      else { q('INSERT INTO departments(name,faculty_id,test_max,a1_max,a2_max,cw_min) VALUES(?,?,?,?,?,?)', array_merge([$dname, $fid], $sc)); $did = (int)$db->lastInsertId(); }
      foreach ($progs as $pname) {
        q('INSERT INTO programmes(name,department_id,faculty_id) VALUES(?,?,?)', [$pname, $did, $fid]); $pid = (int)$db->lastInsertId();
        for ($i = 0, $n = 16 + $pi % 5; $i < $n; $i++) {
          do { $nm = FIRST[($probe * 7 + 3) % count(FIRST)] . ' ' . LAST[(intdiv($probe, count(FIRST)) * 11 + $probe * 13) % count(LAST)]; $probe++; } while (isset($seen[$nm]));
          $seen[$nm] = 1; $seq++;
          q('INSERT INTO students(reg_number,full_name,programme,programme_id,year_of_study,registered) VALUES(?,?,?,?,?,?)',
            [sprintf('R%02d%05d%s', 22 + $seq % 4, 10000 + $seq, chr(65 + $seq % 26)), $nm, $pname, $pid, 'Level ' . (1 + $seq % 4) . ' Semester 1', $seq % 23 === 11 ? 0 : 1]);
        }
        $pi++;
      }
    }
  }
}

$T = [
"users(id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120) NOT NULL, email VARCHAR(160) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','lecturer','hod') NOT NULL, department VARCHAR(120) DEFAULT '', active TINYINT DEFAULT 1, must_change TINYINT NOT NULL DEFAULT 0, failed_attempts INT DEFAULT 0,
  locked_until DATETIME NULL, last_login DATETIME NULL, last_seen DATETIME NULL, last_logout DATETIME NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)",
"students(id INT AUTO_INCREMENT PRIMARY KEY, reg_number VARCHAR(20) NOT NULL UNIQUE, full_name VARCHAR(120) NOT NULL, programme VARCHAR(120),
  year_of_study VARCHAR(40), contact VARCHAR(120), programme_id INT NULL, registered TINYINT NOT NULL DEFAULT 1)",
"modules(id INT AUTO_INCREMENT PRIMARY KEY, code VARCHAR(20) NOT NULL UNIQUE, name VARCHAR(160) NOT NULL, lecturer_id INT NULL, department VARCHAR(120), programme_id INT NULL,
  FOREIGN KEY(lecturer_id) REFERENCES users(id) ON DELETE SET NULL)",
"enrolments(student_id INT, module_id INT, semester VARCHAR(20) DEFAULT 'Current', PRIMARY KEY(student_id,module_id),
  FOREIGN KEY(student_id) REFERENCES students(id) ON DELETE CASCADE, FOREIGN KEY(module_id) REFERENCES modules(id) ON DELETE CASCADE)",
"attendance_sessions(id INT AUTO_INCREMENT PRIMARY KEY, module_id INT NOT NULL, session_date DATE NOT NULL, created_by INT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE(module_id,session_date), FOREIGN KEY(module_id) REFERENCES modules(id) ON DELETE CASCADE)",
"attendance(session_id INT, student_id INT, status ENUM('present','absent','pending') NOT NULL DEFAULT 'pending', marked_at DATETIME NULL,
  PRIMARY KEY(session_id,student_id), FOREIGN KEY(session_id) REFERENCES attendance_sessions(id) ON DELETE CASCADE,
  FOREIGN KEY(student_id) REFERENCES students(id) ON DELETE CASCADE)",
"marks(student_id INT, module_id INT, test DECIMAL(5,2) NULL, a1 DECIMAL(5,2) NULL, a2 DECIMAL(5,2) NULL, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY(student_id,module_id), FOREIGN KEY(student_id) REFERENCES students(id) ON DELETE CASCADE, FOREIGN KEY(module_id) REFERENCES modules(id) ON DELETE CASCADE)",
"risk_state(student_id INT, module_id INT, financial VARCHAR(8), attendance VARCHAR(8), academic VARCHAR(8), final VARCHAR(8), trace JSON NULL, computed_at DATETIME,
  PRIMARY KEY(student_id,module_id))",
"risk_trend(week_start DATE, module_id INT, green INT, amber INT, red INT, PRIMARY KEY(week_start,module_id))",
"notes(id INT AUTO_INCREMENT PRIMARY KEY, student_id INT NOT NULL, module_id INT NULL, lecturer_id INT NOT NULL, note_text TEXT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)",
"alerts(id INT AUTO_INCREMENT PRIMARY KEY, student_id INT NOT NULL, module_id INT NOT NULL, severity ENUM('amber','red','extreme') NOT NULL, message VARCHAR(400),
  acknowledged TINYINT DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)",
"settings(k VARCHAR(40) PRIMARY KEY, v VARCHAR(100) NOT NULL)",
"departments(id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120) NOT NULL UNIQUE, faculty_id INT NULL, test_max DECIMAL(5,2) NOT NULL, a1_max DECIMAL(5,2) NOT NULL, a2_max DECIMAL(5,2) NOT NULL, cw_min DECIMAL(5,2) NOT NULL)",
"user_departments(user_id INT NOT NULL, department VARCHAR(120) NOT NULL, PRIMARY KEY(user_id,department))",
"faculties(id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(160) NOT NULL UNIQUE, test_max DECIMAL(5,2) NOT NULL, a1_max DECIMAL(5,2) NOT NULL, a2_max DECIMAL(5,2) NOT NULL, cw_min DECIMAL(5,2) NOT NULL)",
"programmes(id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(160) NOT NULL UNIQUE, department_id INT NOT NULL, faculty_id INT NOT NULL)",
"audit_log(id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NULL, user_name VARCHAR(120), action VARCHAR(60), entity VARCHAR(60), entity_id VARCHAR(40), old_value TEXT NULL,
  new_value TEXT NULL, ip VARCHAR(45), created_at DATETIME DEFAULT CURRENT_TIMESTAMP)",
"login_log(id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)",
];
foreach ($T as $t) $db->exec('CREATE TABLE IF NOT EXISTS ' . $t . ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

// Upgrade older installs: users.must_change (forces a new password after an admin reset).
if (!one("SHOW COLUMNS FROM users LIKE 'must_change'")) $db->exec('ALTER TABLE users ADD COLUMN must_change TINYINT NOT NULL DEFAULT 0');
foreach ([['students', 'programme_id', 'INT NULL'], ['departments', 'faculty_id', 'INT NULL'], ['modules', 'programme_id', 'INT NULL']] as [$t_, $c_, $def_])
  if (!one("SHOW COLUMNS FROM $t_ LIKE '$c_'")) $db->exec("ALTER TABLE $t_ ADD COLUMN $c_ $def_");
if ((int)one('SELECT COUNT(*) n FROM users')['n'] > 0) {
  backfill_user_departments();
  $msg = 'Database exists and is up to date.';
  if ((int)one('SELECT COUNT(*) n FROM faculties')['n'] === 0) {   // upgrade an older install: keep all existing data
    $db->beginTransaction();
    q('DELETE FROM departments WHERE faculty_id IS NULL AND name <> ?', ['Information and Marketing Sciences']);
    seed_structure(); link_programmes(); ensure_modules(); risk_all();
    $db->commit();
    $msg = 'Upgraded: faculties, programmes, students and 4 modules per programme were added. Your existing data was kept.';
  } else {
    link_modules();
    if ((int)one('SELECT COUNT(*) n FROM programmes p WHERE (SELECT COUNT(*) FROM modules m WHERE m.programme_id=p.id) < 4')['n'] > 0) {
      $db->beginTransaction(); ensure_modules(); risk_all(); $db->commit();
      $msg = 'Upgraded: every programme now has 4 modules. Your existing data was kept.';
    }
  }
  echo '<h2>Already set up</h2><p>' . $msg . ' <a href="login.php">Go to login</a> or <a href="setup.php?reset=1">reset and re-seed demo data</a> (deletes everything).</p>'; exit;
}

$db->beginTransaction(); // one commit instead of ~3500: much faster on XAMPP, and a failure leaves nothing half-seeded
$defaults = ['att_amber' => 5, 'att_red' => 10, 'cw_min' => 12, 'amber_pct' => 30, 'test_max' => 10, 'a1_max' => 5, 'a2_max' => 5, 'escalation' => 1];
foreach ($defaults as $k => $v) q('INSERT INTO settings VALUES(?,?)', [$k, $v]);
q('INSERT INTO settings VALUES("system_start", ?)', ['2026-08-03']); // start of the 7-week usage-report periods
$GLOBALS['__s'] = null;

$dept = 'Information and Marketing Sciences'; $pw = password_hash('Password@123', PASSWORD_DEFAULT);
$U = [['System Administrator', 'admin@staff.msu.ac.zw', 'admin', ''], ['Dr. Tendai Muzorewa', 'lecturer@staff.msu.ac.zw', 'lecturer', $dept],
      ['Ms. Rutendo Makoni', 'makoni@staff.msu.ac.zw', 'lecturer', $dept], ['Prof. Nomsa Dube', 'hod@staff.msu.ac.zw', 'hod', $dept]];
foreach ($U as $u) q('INSERT INTO users(name,email,password_hash,role,department) VALUES(?,?,?,?,?)', [$u[0], $u[1], $pw, $u[2], $u[3]]);
$lec = [2, 2, 3, 3];   // demo lecturers: user ids 2 and 3
$M = [['IS201', 'Systems Analysis and Design'], ['MKT201', 'Consumer Behaviour'], ['IS301', 'Database Systems'], ['STAT201', 'Business Statistics']];
foreach ($M as $i => $m) q('INSERT INTO modules(code,name,lecturer_id,department) VALUES(?,?,?,?)', [$m[0], $m[1], $lec[$i], $dept]);

$names = ['Takudzwa Moyo','Simbai Chikwanda','Nyasha Dzingai','Tatenda Mhuri','Ruvimbo Sithole','Kudakwashe Ncube','Farai Manyika','Blessing Zvobgo','Tinotenda Chuma','Rudo Mapfumo',
  'Tafara Gumbo','Chipo Marufu','Anesu Madziva','Vimbai Chirwa','Tawanda Nkomo','Memory Dube','Panashe Hove','Lloyd Banda','Natasha Mlambo','Tendai Sibanda','Shamiso Mutasa','Brian Chitiyo','Ropafadzo Zhou','Munyaradzi Gwasira'];
$prog = ['Information Systems', 'Consumer and Marketing Intelligence', 'Information Systems', 'Consumer and Marketing Intelligence'];
foreach ($names as $i => $n)
  q('INSERT INTO students(reg_number,full_name,programme,year_of_study,registered) VALUES(?,?,?,?,?)',
    [sprintf('R21%04d%s', 3400 + $i * 37, chr(65 + $i % 26)), $n, $prog[$i % 4], 'Level 2 Semester 1', in_array($i, [2, 17]) ? 0 : 1]);

$marks = [[8, 4, 4], [7, 3, 3.5], [9, 4, 5], [6, 4, 4], [10, 5, 5], [7, 3, 4], [3, 3, 3], [2, 5, 5], [7, 4, 1], [8, 5, 4]]; // 1 red, 2 amber, rest good (written out of 20)
foreach ($marks as &$mk0) foreach ($mk0 as &$x0) $x0 = round($x0 * 3) / 2; unset($mk0, $x0); // rescale to the 30-mark Business Sciences scale
$sess = []; // 12 weekly sessions per module
foreach ($M as $mi => $m) {
  $mid = $mi + 1;
  for ($k = 12; $k >= 1; $k--) {
    $d = date('Y-m-d', strtotime("-" . ($k * 7) . " days"));
    q('INSERT INTO attendance_sessions(module_id,session_date,created_by,created_at) VALUES(?,?,?,?)', [$mid, $d, $lec[$mi], "$d 09:00:00"]);
    $sess[$mid][] = (int)db()->lastInsertId();
  }
  foreach ($names as $i => $_) {
    $sid = $i + 1;
    if ($mi !== $i % 4 && (($mi + 3) % 4) !== $i % 4) continue; // each student takes two modules
    q('INSERT INTO enrolments(student_id,module_id) VALUES(?,?)', [$sid, $mid]);
    $p = ($i * 5 + $mi * 3) % 9; // 1 = amber streak, 2 = red streak, 3 = scattered absences
    foreach ($sess[$mid] as $k => $sessId) {
      $n = $k + 1; // 1..12
      $abs = match ($p) { 1 => $n > 6, 2 => $n > 2, 3 => in_array($n, [2, 5, 8]), default => false };
      q('INSERT INTO attendance(session_id,student_id,status,marked_at) VALUES(?,?,?,NOW())', [$sessId, $sid, $abs ? 'absent' : 'present']);
    }
    if (!($i % 9 === 8 && $mi === 3)) { $mk = $marks[($i * 3 + $mi * 2) % 10]; q('INSERT INTO marks(student_id,module_id,test,a1,a2) VALUES(?,?,?,?,?)', [$sid, $mid, $mk[0], $mk[1], $mk[2]]); }
  }
}
q('INSERT INTO notes(student_id,module_id,lecturer_id,note_text) VALUES(2,1,2,"Spoke to student after class; reports transport problems.")');
seed_structure(); link_programmes(); ensure_modules();
q("UPDATE modules SET lecturer_id=2 WHERE code='ACC101'");   // demo: the first lecturer also teaches in Accounting Sciences
backfill_user_departments(); risk_all();
// Seed 6 earlier weeks of trend data from the current counts.
foreach ($M as $mi => $m) {
  $cur = one("SELECT SUM(final='red') r, SUM(final='amber') a, COUNT(*) n FROM risk_state WHERE module_id=?", [$mi + 1]);
  for ($w = 6; $w >= 1; $w--) {
    $r = max(0, (int)$cur['r'] + ($w % 2) - 1); $a = (int)$cur['a'] + ($w % 3);
    q('INSERT INTO risk_trend VALUES(?,?,?,?,?)', [date('Y-m-d', strtotime("monday this week -$w weeks")), $mi + 1, max(0, (int)$cur['n'] - $r - $a), $a, $r]);
  }
}
foreach ([2, 3, 4] as $uid) for ($w = 0; $w < 40; $w += 3) q('INSERT INTO login_log(user_id,created_at) VALUES(?,NOW() - INTERVAL ? DAY)', [$uid, $w + $uid]);
q('UPDATE alerts SET acknowledged=0');
$db->commit();
?>
<h2>Setup complete</h2>
<p>Database <b><?= htmlspecialchars($c['db_name']) ?></b> created with demo data. <a href="login.php">Go to login</a></p>
<p>Demo accounts (password <code>Password@123</code>): admin@staff.msu.ac.zw, lecturer@staff.msu.ac.zw, makoni@staff.msu.ac.zw, hod@staff.msu.ac.zw.<br>Change these passwords and delete setup.php before real use.</p>
