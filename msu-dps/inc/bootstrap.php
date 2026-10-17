<?php
date_default_timezone_set('Africa/Harare');
// Never let the browser cache a page or API reply: Back/Forward must not show an old signed-in view.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0'); header('Pragma: no-cache');
define('SESSION_TIMEOUT', (int)((require __DIR__ . '/config.php')['session_timeout'] ?? 3600));
// Keep sessions in our own folder and for longer than the timeout (PHP's default cleanup would drop idle sessions after ~24 minutes).
$__sp = __DIR__ . '/../sessions'; if (!is_dir($__sp)) @mkdir($__sp, 0700, true); if (is_dir($__sp) && is_writable($__sp)) session_save_path($__sp);
ini_set('session.gc_maxlifetime', (string)(SESSION_TIMEOUT * 2)); ini_set('session.use_strict_mode', '1');
session_name('msu_dps');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();

function db(): PDO {
  static $p;
  if (!$p) {
    $c = require __DIR__ . '/config.php';
    $p = new PDO("mysql:host={$c['db_host']};dbname={$c['db_name']};charset=utf8mb4", $c['db_user'], $c['db_pass'],
      [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $p->exec("SET time_zone='+02:00'");
  }
  return $p;
}
function q(string $sql, array $a = []): PDOStatement { $s = db()->prepare($sql); $s->execute($a); return $s; }
function one(string $sql, array $a = []) { return q($sql, $a)->fetch() ?: null; }
function all(string $sql, array $a = []): array { return q($sql, $a)->fetchAll(); }
function jerr(string $m, int $code = 400) { http_response_code($code); header('Content-Type: application/json'); echo json_encode(['error' => $m]); exit; }
function jout($d) { header('Content-Type: application/json'); echo json_encode($d); exit; }

function settings(bool $fresh = false): array {
  static $s;
  if ($s === null || $fresh) { $s = []; foreach (all('SELECT k,v FROM settings') as $r) $s[$r['k']] = $r['v']; }
  return $s;
}
// Ends the session and records it (reason: 'logout' or 'timeout').
function end_session(string $why = 'logout'): void {
  if (!empty($_SESSION['uid'])) {
    $uid = (int)$_SESSION['uid'];
    q('UPDATE users SET last_logout=NOW(), last_seen=NOW() WHERE id=?', [$uid]);
    $u = one('SELECT name FROM users WHERE id=?', [$uid]);
    q('INSERT INTO audit_log(user_id,user_name,action,entity,entity_id,ip) VALUES(?,?,?,?,?,?)', [$uid, $u['name'] ?? 'user', $why === 'timeout' ? 'session_timeout' : 'logout', 'user', $uid, $_SERVER['REMOTE_ADDR'] ?? '']);
  }
  $_SESSION = [];
  if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
  setcookie(session_name(), '', time() - 3600, '/');
}
// True (and the session is ended) when the signed-in user has been idle longer than the timeout.
function check_timeout(): bool {
  if (empty($_SESSION['uid'])) return false;
  if (time() - ($_SESSION['last_activity'] ?? time()) > SESSION_TIMEOUT) { end_session('timeout'); return true; }
  return false;
}
function me(): ?array {
  static $u = false;
  if ($u === false) {
    $u = empty($_SESSION['uid']) ? null : one('SELECT id,name,email,role,department,active,must_change FROM users WHERE id=?', [$_SESSION['uid']]);
    if ($u && !$u['active']) $u = null;
  }
  return $u;
}
function need(array $roles): array {
  $u = me();
  if (!$u) jerr('Please sign in', 401);
  if (!in_array($u['role'], $roles, true)) jerr('You do not have permission for this action', 403);
  return $u;
}
function audit(string $action, string $entity = '', $id = null, $old = null, $new = null): void {
  $u = me();
  q('INSERT INTO audit_log(user_id,user_name,action,entity,entity_id,old_value,new_value,ip) VALUES(?,?,?,?,?,?,?,?)',
    [$u['id'] ?? null, $u['name'] ?? 'system', $action, $entity, $id,
     $old === null ? null : (is_scalar($old) ? (string)$old : json_encode($old)),
     $new === null ? null : (is_scalar($new) ? (string)$new : json_encode($new)), $_SERVER['REMOTE_ADDR'] ?? '']);
}
// Modules the signed-in user may see: lecturer = own modules, HOD = department modules, admin = none.
function my_modules(): array {
  $u = me();
  if (!$u) return [];
  if ($u['role'] === 'lecturer') return all('SELECT m.*,u.name lecturer FROM modules m LEFT JOIN users u ON u.id=m.lecturer_id WHERE m.lecturer_id=? ORDER BY m.code', [$u['id']]);
  if ($u['role'] === 'hod') return all('SELECT m.*,u.name lecturer FROM modules m LEFT JOIN users u ON u.id=m.lecturer_id WHERE m.department=? ORDER BY m.code', [$u['department']]);
  return [];
}
function can_module(int $mid): array {
  foreach (my_modules() as $m) if ((int)$m['id'] === $mid) return $m;
  jerr('You are not allowed to access this module', 403);
}
