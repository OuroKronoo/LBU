<?php
session_start();
include('logcheck.php');
require('includes/conn.php');

// Deleting changes data, so only accept a POST for a numeric student record ID.
$num = ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accid'])) ? (int)$_POST['accid'] : 0;
if ($num > 0) {
	$find = mysqli_prepare($conn, 'SELECT accountID, studnum FROM studenttbl WHERE ID = ?');
	mysqli_stmt_bind_param($find, 'i', $num);
	mysqli_stmt_execute($find);
	mysqli_stmt_bind_result($find, $accountID, $studnum);
	$found = mysqli_stmt_fetch($find);
	mysqli_stmt_close($find);
	if ($found && (int)$accountID !== (int)($_SESSION['userID'] ?? 0)) {
		$statements = array(
			array('DELETE FROM student_subject WHERE studentID = ?', 's', $studnum),
			array('DELETE FROM studenttbl WHERE ID = ?', 'i', $num),
			array('DELETE FROM usertbl WHERE ID = ?', 'i', $accountID),
		);
		foreach ($statements as $s) {
			$stmt = mysqli_prepare($conn, $s[0]);
			if ($stmt) {
				mysqli_stmt_bind_param($stmt, $s[1], $s[2]);
				mysqli_stmt_execute($stmt);
				mysqli_stmt_close($stmt);
			}
		}
	}
}
header('Location: userreg.php');
exit;
