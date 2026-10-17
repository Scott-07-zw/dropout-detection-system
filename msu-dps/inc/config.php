<?php
// XAMPP defaults: MySQL user "root" with an empty password.
return [
  'db_host' => '127.0.0.1',
  'db_name' => 'msu_dps',
  'db_user' => 'root',
  'db_pass' => '',
  // Accounts can only be created with these email domains.
  // Sign users out after this many seconds without activity.
  'session_timeout' => 3600,
  'allowed_email_domains' => ['staff.msu.ac.zw'],
];
