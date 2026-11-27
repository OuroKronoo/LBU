<?php
session_start();
include('logcheck.php');
require('includes/conn.php');

// Deleting changes data, so only accept a POST for a numeric subject ID.
$num = ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accid'])) ? (int)$_POST['accid'] : 0;
if ($num > 0) {
	$del = mysqli_prepare($conn, 'DELETE FROM subjecttbl WHERE ID = ?');
	mysqli_stmt_bind_param($del, 'i', $num);
	mysqli_stmt_execute($del);
	$deleted = mysqli_stmt_affected_rows($del) > 0;
	mysqli_stmt_close($del);
}
header('Location: subjectreg.php?delete=' . (!empty($deleted) ? 'done' : 'missing'));
exit;
