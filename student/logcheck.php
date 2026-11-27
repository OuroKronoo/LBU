<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
	session_start();
}

if (!isset($_SESSION['logcheck']) || $_SESSION['logcheck'] != 1 || !isset($_SESSION['type']) || $_SESSION['type'] !== 'student') {
	header('Location: ../index.php');
	exit;
}
?>
