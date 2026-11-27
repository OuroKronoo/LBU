<?php
session_start();
include('logcheck.php');
include('includes/conn.php');
function userreg_escape($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
$programs = array('bsit' => 'BSIT', 'bsce' => 'BSCE', 'bsed' => 'BSED');

// Edit an account (POST), then show the edit form for ?edit=ID (GET).
$editError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
	$fullname = trim($_POST['fullname'] ?? ''); $username = trim($_POST['username'] ?? ''); $password = (string)($_POST['password'] ?? '');
	$role = in_array($_POST['user_type'] ?? '', array('student', 'admin'), true) ? $_POST['user_type'] : '';
	if ($fullname === '' || $username === '' || $password === '' || $role === '') { $editError = 'Name, username, password and role are required.'; }
	elseif (mb_strlen($fullname) > 20 || mb_strlen($username) > 11 || mb_strlen($password) > 11) { $editError = 'Name is limited to 20 characters; username and password to 11.'; }
	elseif ($role === 'student' && (trim($_POST['studnum'] ?? '') === '' || !isset($programs[strtolower(trim($_POST['course'] ?? ''))]) || !in_array($_POST['yearlvl'] ?? '', array('1', '2', '3', '4'), true))) { $editError = 'Choose a defined program and year level (1-4) for student accounts.'; }
	else {
		$dup = mysqli_prepare($conn, 'SELECT 1 FROM usertbl WHERE username = ? LIMIT 1'); mysqli_stmt_bind_param($dup, 's', $username); mysqli_stmt_execute($dup); mysqli_stmt_store_result($dup); $taken = mysqli_stmt_num_rows($dup) > 0; mysqli_stmt_close($dup);
		if ($taken) { $editError = 'That username is already taken.'; }
		else {
			$add = mysqli_prepare($conn, 'INSERT INTO usertbl (username, password, fullname, user_type) VALUES (?, ?, ?, ?)'); mysqli_stmt_bind_param($add, 'ssss', $username, $password, $fullname, $role);
			if (mysqli_stmt_execute($add)) {
				$newAccountID = mysqli_insert_id($conn); mysqli_stmt_close($add);
				if ($role === 'student') {
					$studnum = trim($_POST['studnum']); $course = strtolower(trim($_POST['course'])); $yearlvl = trim($_POST['yearlvl']);
					$profile = mysqli_prepare($conn, "INSERT INTO studenttbl (accountID, studnum, fname, minitial, lname, address, contact, emailaddress, birthdate, course, yearlvl, gender, fathername, mothername, guardianname, birthplace, civilstatus, elemschool, dategradelem, hsschool, datehsgrad, lastgrad, datelastgrad, addresslastgrad) VALUES (?, ?, ?, '', '', '', '', '', '', ?, ?, '', '', '', '', '', '', '', '', '', '', '', '', '')");
					mysqli_stmt_bind_param($profile, 'issss', $newAccountID, $studnum, $fullname, $course, $yearlvl);
					if (!mysqli_stmt_execute($profile)) { mysqli_stmt_close($profile); mysqli_query($conn, 'DELETE FROM usertbl WHERE ID = ' . (int)$newAccountID); $editError = 'Student profile could not be created; account creation was rolled back.'; }
					else { mysqli_stmt_close($profile); }
				}
				if ($editError === '') { header('Location: userreg.php?added=1'); exit; }
			} else { mysqli_stmt_close($add); $editError = 'The account could not be added.'; }
		}
	}
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_user'])) {
	$editID = (int)$_POST['save_user'];
	$fullname = trim($_POST['fullname'] ?? '');
	$username = trim($_POST['username'] ?? '');
	$newPassword = (string)($_POST['password'] ?? '');
	if ($fullname === '' || $username === '') {
		$editError = 'Name and username are required.';
	} elseif (isset($_POST['course']) && (!isset($programs[strtolower(trim($_POST['course']))]) || !in_array($_POST['yearlvl'] ?? '', array('1', '2', '3', '4'), true))) {
		$editError = 'Choose a defined program and year level (1-4).';
	} else {
		$current = mysqli_prepare($conn, 'SELECT username FROM usertbl WHERE ID = ? LIMIT 1');
		mysqli_stmt_bind_param($current, 'i', $editID);
		mysqli_stmt_execute($current);
		mysqli_stmt_bind_result($current, $currentUsername);
		$accountExists = mysqli_stmt_fetch($current);
		mysqli_stmt_close($current);
		$taken = false;
		if ($accountExists && strcasecmp($username, $currentUsername) !== 0) {
			$dup = mysqli_prepare($conn, 'SELECT 1 FROM usertbl WHERE username = ? AND ID <> ? LIMIT 1');
			mysqli_stmt_bind_param($dup, 'si', $username, $editID);
			mysqli_stmt_execute($dup);
			mysqli_stmt_store_result($dup);
			$taken = mysqli_stmt_num_rows($dup) > 0;
			mysqli_stmt_close($dup);
		}
		if ($taken) {
			$editError = 'That username is already taken.';
		} else {
			$upd = mysqli_prepare($conn, 'UPDATE usertbl SET fullname = ?, username = ? WHERE ID = ?');
			mysqli_stmt_bind_param($upd, 'ssi', $fullname, $username, $editID);
			$accountSaved = mysqli_stmt_execute($upd);
			mysqli_stmt_close($upd);
			if ($newPassword !== '') {
				$pw = mysqli_prepare($conn, 'UPDATE usertbl SET password = ? WHERE ID = ?');
				mysqli_stmt_bind_param($pw, 'si', $newPassword, $editID);
				mysqli_stmt_execute($pw);
				mysqli_stmt_close($pw);
			}
			if (isset($_POST['course'])) {
				$course = strtolower(trim($_POST['course']));
				$yearlvl = trim($_POST['yearlvl'] ?? '');
				$studentFields = array('studnum', 'fname', 'minitial', 'lname', 'contact', 'emailaddress', 'address');
				$values = array(); foreach ($studentFields as $field) { $values[$field] = trim($_POST[$field] ?? ''); }
				$emailColumn = mysqli_query($conn, "SHOW COLUMNS FROM studenttbl LIKE 'emailaddress'");
				if ($emailColumn && ($emailInfo = mysqli_fetch_assoc($emailColumn)) && strpos(strtolower($emailInfo['Type']), 'varchar(14)') === 0) { mysqli_query($conn, 'ALTER TABLE studenttbl MODIFY emailaddress varchar(254) NOT NULL'); }
				$accountIDText = (string)$editID;
				$profileIDQuery = mysqli_prepare($conn, 'SELECT ID FROM studenttbl WHERE accountID = ? LIMIT 1');
				mysqli_stmt_bind_param($profileIDQuery, 's', $accountIDText); mysqli_stmt_execute($profileIDQuery); mysqli_stmt_bind_result($profileIDQuery, $studentRecordID); $hasProfile = mysqli_stmt_fetch($profileIDQuery); mysqli_stmt_close($profileIDQuery);
				$profileSaved = false;
				if ($hasProfile) {
					$st = mysqli_prepare($conn, 'UPDATE studenttbl SET course = ?, yearlvl = ?, studnum = ?, fname = ?, minitial = ?, lname = ?, contact = ?, emailaddress = ?, address = ? WHERE ID = ?');
					if ($st) {
						mysqli_stmt_bind_param($st, 'sssssssssi', $course, $yearlvl, $values['studnum'], $values['fname'], $values['minitial'], $values['lname'], $values['contact'], $values['emailaddress'], $values['address'], $studentRecordID);
						$profileSaved = mysqli_stmt_execute($st); mysqli_stmt_close($st);
					}
				}
				if (!$hasProfile) { $editError = 'No student profile is linked to this account, so student details were not updated.'; }
				elseif (!$profileSaved) { $editError = 'Student details were not saved: ' . mysqli_error($conn); }
			}
			if ($accountSaved && $editError === '') { header('Location: userreg.php?saved=1'); exit; }
			if (!$accountSaved) { $editError = 'Account changes could not be saved.'; }
		}
	}
	$_GET['edit'] = $editID;
}
$editUser = null;
if (isset($_GET['edit'])) {
	$editQuery = mysqli_prepare($conn, 'SELECT s.*, u.ID, u.username, u.fullname, u.user_type, s.ID AS studID FROM usertbl u LEFT JOIN studenttbl s ON s.accountID = u.ID WHERE u.ID = ? LIMIT 1');
	$editWanted = (int)$_GET['edit'];
	mysqli_stmt_bind_param($editQuery, 'i', $editWanted);
	mysqli_stmt_execute($editQuery);
	$editResult = mysqli_stmt_get_result($editQuery);
	$editUser = $editResult ? mysqli_fetch_assoc($editResult) : null;
	mysqli_stmt_close($editQuery);
	if ($editUser && $editError !== '') {
		$editUser['fullname'] = $_POST['fullname'] ?? $editUser['fullname'];
		$editUser['username'] = $_POST['username'] ?? $editUser['username'];
	}
}
$users = mysqli_query($conn, 'SELECT u.ID, s.ID AS studID, u.username, u.fullname, u.user_type, s.studnum, s.course, s.yearlvl FROM usertbl u LEFT JOIN studenttbl s ON s.accountID = u.ID ORDER BY u.ID');
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title>Accounts | Long Brown University</title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Lora:wght@500;600&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
	<link href="style.css?v=20261127" rel="stylesheet" type="text/css" />
</head>
<body class="lbu">
	<header class="lbu-hero">
		<div class="lbu-bar">
			<a class="lbu-logo" href="adminindex.php" aria-label="Long Brown University home"><img src="../logo.png" alt="Long Brown University crest"><span>Long Brown University</span><span class="lbu-tag">Admin</span></a>
			<nav class="lbu-nav" aria-label="Administration navigation"><?php include('includes/menu1.php'); ?></nav>
		</div>
		<div class="lbu-pagehead">
			<p class="lbu-eyebrow">Enrollment administration</p>
			<h1>Accounts</h1>
			<p>Student and administrator accounts currently registered in the system.</p>
			
		</div>
	</header>
	<main class="lbu-panel">
<?php if (isset($_GET['saved'])) { ?><p class="lbu-alert" role="status">Account updated.</p><?php } ?>
<?php if (isset($_GET['added'])) { ?><p class="lbu-alert" role="status">Account added.</p><?php } ?>
<?php if ($editError !== '') { ?><p class="lbu-alert" role="alert"><?php echo userreg_escape($editError); ?></p><?php } ?>
<details class="lbu-add-panel"<?php echo ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user']) && $editError !== '') ? ' open' : ''; ?>><summary class="lbu-btn lbu-btn--gold">+ Add user</summary>
<form method="post" class="lbu-form"><input type="hidden" name="add_user" value="1" />
	<div><label class="lbu-label" for="new_fullname">Full name</label><input id="new_fullname" name="fullname" maxlength="20" required value="<?php echo userreg_escape($_POST['fullname'] ?? ''); ?>"></div>
	<div><label class="lbu-label" for="new_username">Username</label><input id="new_username" name="username" maxlength="11" required value="<?php echo userreg_escape($_POST['username'] ?? ''); ?>"></div>
	<div><label class="lbu-label" for="new_password">Temporary password</label><input id="new_password" type="password" name="password" maxlength="11" required autocomplete="new-password"></div>
	<div><label class="lbu-label" for="new_role">Role</label><select id="new_role" name="user_type" required><option value="student">Student</option><option value="admin">Administrator</option></select></div>
	<div><label class="lbu-label" for="new_studnum">Student number (students)</label><input id="new_studnum" name="studnum" maxlength="11" value="<?php echo userreg_escape($_POST['studnum'] ?? ''); ?>"></div>
	<div><label class="lbu-label" for="new_course">Program (students)</label><select id="new_course" name="course"><option value="">Select program</option><?php foreach ($programs as $code => $label) { ?><option value="<?php echo $code; ?>"<?php echo strtolower($_POST['course'] ?? '') === $code ? ' selected' : ''; ?>><?php echo $label; ?></option><?php } ?></select></div>
	<div><label class="lbu-label" for="new_yearlvl">Year level (students)</label><select id="new_yearlvl" name="yearlvl"><option value="">Select year</option><?php for ($year = 1; $year <= 4; $year++) { ?><option value="<?php echo $year; ?>"<?php echo ($_POST['yearlvl'] ?? '') === (string)$year ? ' selected' : ''; ?>>Year <?php echo $year; ?></option><?php } ?></select></div>
	<div class="lbu-form-actions"><button class="lbu-btn lbu-btn--gold" type="submit">Create account</button></div>
</form></details>
<?php if ($editUser) { ?>
<form method="post" class="lbu-form">
	<h2>Edit account: <?php echo userreg_escape($editUser['username']); ?></h2>
	<input type="hidden" name="save_user" value="<?php echo (int)$editUser['ID']; ?>" />
	<div><label class="lbu-label" for="f_fullname">Full name</label><input type="text" name="fullname" id="f_fullname" maxlength="20" required value="<?php echo userreg_escape($editUser['fullname']); ?>"></div>
	<div><label class="lbu-label" for="f_username">Username</label><input type="text" name="username" id="f_username" maxlength="11" required value="<?php echo userreg_escape($editUser['username']); ?>"></div>
	<div><label class="lbu-label" for="f_password">New password (blank = keep)</label><input type="password" name="password" id="f_password" maxlength="11" autocomplete="new-password"></div>
	<?php if ($editUser['studID']) { ?>
	<div><label class="lbu-label" for="f_course">Course</label><select name="course" id="f_course" required><?php foreach ($programs as $code => $label) { ?><option value="<?php echo $code; ?>"<?php echo strtolower($editUser['course']) === $code ? ' selected' : ''; ?>><?php echo $label; ?></option><?php } ?></select></div>
	<div><label class="lbu-label" for="f_yearlvl">Year level</label><select name="yearlvl" id="f_yearlvl" required><?php for ($year = 1; $year <= 4; $year++) { ?><option value="<?php echo $year; ?>"<?php echo (int)preg_replace('/\D/', '', $editUser['yearlvl']) === $year ? ' selected' : ''; ?>>Year <?php echo $year; ?></option><?php } ?></select></div>
	<?php foreach (array('studnum' => 'Student number', 'fname' => 'First name', 'minitial' => 'Middle initial', 'lname' => 'Last name', 'contact' => 'Contact number', 'emailaddress' => 'Email address') as $field => $label) { ?><div><label class="lbu-label" for="f_<?php echo $field; ?>"><?php echo $label; ?></label><input type="text" name="<?php echo $field; ?>" id="f_<?php echo $field; ?>" value="<?php echo userreg_escape($editUser[$field] ?? ''); ?>"></div><?php } ?>
	<div class="lbu-wide"><label class="lbu-label" for="f_address">Address</label><input type="text" name="address" id="f_address" maxlength="500" value="<?php echo userreg_escape($editUser['address'] ?? ''); ?>"></div>
	<?php } ?>
	<div class="lbu-form-actions"><a class="lbu-btn lbu-btn--ghost" href="userreg.php">Cancel</a><button class="lbu-btn lbu-btn--gold" type="submit">Save changes</button></div>
</form>
<?php } ?>
<div class="lbu-table-wrap"><table class="lbu-table"><thead><tr><th>Name</th><th>Username</th><th>Role</th><th>Student number</th><th>Program / year</th><th>Action</th></tr></thead><tbody>
<?php if ($users) { while ($user = mysqli_fetch_assoc($users)) { ?><tr><td><?php echo userreg_escape($user['fullname']); ?></td><td><?php echo userreg_escape($user['username']); ?></td><td><span class="lbu-badge<?php echo $user['user_type'] === 'admin' ? ' lbu-badge--dark' : ''; ?>"><?php echo userreg_escape(ucfirst($user['user_type'])); ?></span></td><td><?php echo userreg_escape($user['studnum'] ?: '—'); ?></td><td><?php echo userreg_escape($user['course'] ? strtoupper($user['course']).' · '.$user['yearlvl'] : '—'); ?></td><td style="white-space:nowrap"><a class="lbu-btn lbu-btn--sm" href="userreg.php?edit=<?php echo (int)$user['ID']; ?>">Edit</a> <?php if ($user['studID']) { ?><form class="lbu-inline" method="post" action="deletecurruser.php" onsubmit="return confirm('Delete this student account and their enrollment requests?');"><input type="hidden" name="accid" value="<?php echo (int)$user['studID']; ?>" /><button class="lbu-btn lbu-btn--sm lbu-btn--ghost" type="submit">Delete</button></form><?php } else { echo '—'; } ?></td></tr><?php } } ?></tbody></table></div></main>
	<footer class="lbu-footer"><p>Long Brown University · Enrollment Administration</p></footer>
</body>
</html>
