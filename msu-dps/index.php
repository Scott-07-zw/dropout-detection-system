<?php require __DIR__ . '/inc/bootstrap.php'; header('Location: ' . (empty($_SESSION['uid']) ? 'login.php' : 'app.php')); exit;
