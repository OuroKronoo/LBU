<?php
session_start();
include('includes/conn.php');
include('logcheck.php');

function profile_escape($value)
{
	return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$student = null;
$accountID = isset($_SESSION['userID']) ? (int)$_SESSION['userID'] : 0;
$profileQuery = mysqli_prepare($conn, 'SELECT studnum, fname, minitial, lname, course, yearlvl, emailaddress, contact, address FROM studenttbl WHERE accountID = ? LIMIT 1');
if ($profileQuery) {
	mysqli_stmt_bind_param($profileQuery, 'i', $accountID);
	mysqli_stmt_execute($profileQuery);
	$profileResult = mysqli_stmt_get_result($profileQuery);
	$student = $profileResult ? mysqli_fetch_assoc($profileResult) : null;
	mysqli_stmt_close($profileQuery);
}
$emailColumn = mysqli_query($conn, "SHOW COLUMNS FROM studenttbl LIKE 'emailaddress'");
if ($emailColumn && ($emailInfo = mysqli_fetch_assoc($emailColumn)) && strpos(strtolower($emailInfo['Type']), 'varchar(14)') === 0) {
	mysqli_query($conn, 'ALTER TABLE studenttbl MODIFY emailaddress varchar(254) NOT NULL');
}
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title>Student Profile | Long Brown University</title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Lora:wght@500;600&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
	<link href="style.css?v=20261127" rel="stylesheet" type="text/css" />
</head>
<body class="lbu">
	<header class="lbu-hero">
		<div class="lbu-bar">
			<a class="lbu-logo" href="studentindex.php" aria-label="Long Brown University home"><img src="../logo.png" alt="Long Brown University crest"><span>Long Brown University</span></a>
			<nav class="lbu-nav" aria-label="Student navigation"><?php include('includes/menu.php'); ?></nav>
		</div>
		<div class="lbu-pagehead">
			<p class="lbu-eyebrow">Student portal</p>
			<h1>Student profile</h1>
			<p>Review the details on your account.</p>
			
		</div>
	</header>
	<main class="lbu-panel">
		<?php if (!$student) { ?><p class="lbu-empty">We couldn’t find a student profile for this account. Please contact the university office for help.</p>
		<?php } else { ?>
			<div class="lbu-profile-head">
				<div class="lbu-avatar" aria-hidden="true"><?php echo profile_escape(strtoupper(substr($student['fname'], 0, 1) . substr($student['lname'], 0, 1))); ?></div>
				<div class="lbu-profile-name"><h2><?php echo profile_escape(trim($student['fname'].' '.$student['minitial'].' '.$student['lname'])); ?></h2><span class="lbu-muted">Student no. <?php echo profile_escape($student['studnum']); ?> · <?php echo profile_escape(strtoupper($student['course'])); ?> · <?php echo profile_escape($student['yearlvl']); ?></span></div>
				<a class="lbu-btn lbu-btn--gold" href="enroll.php">Continue to enrollment →</a>
			</div>
			<div class="lbu-fields">
				<div class="lbu-field"><span class="lbu-label">First name</span><div><?php echo profile_escape($student['fname']); ?></div></div>
				<div class="lbu-field"><span class="lbu-label">Middle initial</span><div><?php echo profile_escape($student['minitial']); ?></div></div>
				<div class="lbu-field"><span class="lbu-label">Last name</span><div><?php echo profile_escape($student['lname']); ?></div></div>
				<div class="lbu-field"><span class="lbu-label">Student number</span><div><?php echo profile_escape($student['studnum']); ?></div></div>
				<div class="lbu-field"><span class="lbu-label">Course</span><div><?php echo profile_escape(strtoupper($student['course'])); ?></div></div>
				<div class="lbu-field"><span class="lbu-label">Year level</span><div><?php echo profile_escape($student['yearlvl']); ?></div></div>
				<div class="lbu-field"><span class="lbu-label">Email</span><div><?php echo profile_escape($student['emailaddress']); ?></div></div>
				<div class="lbu-field"><span class="lbu-label">Contact</span><div><?php echo profile_escape($student['contact']); ?></div></div>
				<div class="lbu-field lbu-field--wide"><span class="lbu-label">Address</span><div><?php echo profile_escape($student['address']); ?></div></div>
			</div>
		<?php } ?>
	</main>
	<footer class="lbu-footer"><p>Long Brown University · Student Enrollment</p></footer>
</body>
</html>
