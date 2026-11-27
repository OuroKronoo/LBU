<?php
session_start();
include('conn.php');

$loginError = '';
if(isset($_POST['submit'])){
	$username = isset($_POST['username']) ? trim($_POST['username']) : '';
	$password = isset($_POST['password']) ? $_POST['password'] : '';
	$loginQuery = mysqli_prepare($conn, 'SELECT ID, username, password, user_type FROM usertbl WHERE username = ? AND password = ? LIMIT 1');
	if ($loginQuery) {
		mysqli_stmt_bind_param($loginQuery, 'ss', $username, $password);
		mysqli_stmt_execute($loginQuery);
		mysqli_stmt_bind_result($loginQuery, $userID, $dbUsername, $dbPassword, $userType);
		if (mysqli_stmt_fetch($loginQuery)) {
			mysqli_stmt_close($loginQuery);
			session_regenerate_id(true);
			$_SESSION['username'] = $dbUsername;
			$_SESSION['type'] = $userType;
			$_SESSION['logcheck'] = 1;
			$_SESSION['userID'] = (int)$userID;
			$destination = ($userType === 'admin') ? 'Admin/adminindex.php' : 'student/studentindex.php';
			header('Location: ' . $destination);
			exit;
		}
		mysqli_stmt_close($loginQuery);
	}
	$loginError = 'That username and password were not recognized. Please try again.';
}
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title>Login | Long Brown University</title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Lora:wght@500;600&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
	<link href="style.css?v=20261127" rel="stylesheet" type="text/css" />
</head>
<body class="lbu lbu-login">
	<div class="lbu-login-copy">
		<img src="logo.png" alt="Long Brown University crest">
		<p class="lbu-eyebrow">Maysan · Valenzuela City</p>
		<h1>Long Brown<br>University</h1>
		<p>Learn with purpose. Lead with heart.</p>
	</div>
	<form class="lbu-login-card" method="POST">
		<h2>Sign in</h2>
		<p class="lbu-login-sub">Students and staff use the same login.</p>
		<?php if ($loginError !== '') { ?><p class="lbu-alert" role="alert"><?php echo htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8'); ?></p><?php } ?>
		<label for="username">Username</label>
		<input id="username" type="text" name="username" required autocomplete="username">
		<label for="password">Password</label>
		<input id="password" type="password" name="password" required autocomplete="current-password">
		<button type="submit" name="submit" value="Login" class="lbu-btn lbu-btn--gold">Log in</button>
		<p class="lbu-login-help">Trouble signing in? Contact the registrar’s office.</p>
	</form>
</body>
</html>
