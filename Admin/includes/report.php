<?php
session_start();
if (!isset($_SESSION['logcheck']) || $_SESSION['logcheck'] != 1 || !isset($_SESSION['type']) || $_SESSION['type'] !== 'admin') {
	header('Location: ../../index.php');
	exit;
}
include('conn.php');
// Reject a pending request (POST only).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject'])) {
	$rejectID = (int)$_POST['reject'];
	$reject = mysqli_prepare($conn, "UPDATE student_subject SET approved = '2' WHERE ID = ? AND approved = '0'");
	if ($reject) {
		mysqli_stmt_bind_param($reject, 'i', $rejectID);
		mysqli_stmt_execute($reject);
		mysqli_stmt_close($reject);
	}
	header('Location: report.php' . (isset($_POST['back']) ? '?' . http_build_query(array_intersect_key($_POST['back'], array_flip(array('status', 'sem', 'course', 'q')))) : ''));
	exit;
}
// Approve a pending request (POST only).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve'])) {
	$approveID = (int)$_POST['approve'];
	$approve = mysqli_prepare($conn, "UPDATE student_subject SET approved = '1' WHERE ID = ? AND approved = '0' AND studentID <> '' AND totalunits > 0 AND subjects <> ''");
	if ($approve) {
		mysqli_stmt_bind_param($approve, 'i', $approveID);
		mysqli_stmt_execute($approve);
		mysqli_stmt_close($approve);
	}
	header('Location: report.php' . (isset($_POST['back']) ? '?' . http_build_query(array_intersect_key($_POST['back'], array_flip(array('status', 'sem', 'course', 'q')))) : ''));
	exit;
}
function enrollment_report_escape($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

// Filters (GET), applied with prepared-statement parameters.
$fStatus = isset($_GET['status']) && in_array($_GET['status'], array('0', '1', '2'), true) ? $_GET['status'] : '';
$fSem = isset($_GET['sem']) && in_array($_GET['sem'], array('1st Sem', '2nd Sem'), true) ? $_GET['sem'] : '';
$fCourse = isset($_GET['course']) ? trim($_GET['course']) : '';
$fQuery = isset($_GET['q']) ? trim($_GET['q']) : '';
$where = array();
$types = '';
$params = array();
if ($fStatus !== '') { $where[] = 'ss.approved = ?'; $types .= 's'; $params[] = $fStatus; }
if ($fSem !== '') { $where[] = 'ss.semester = ?'; $types .= 's'; $params[] = $fSem; }
if ($fCourse !== '') { $where[] = 'LOWER(st.course) = LOWER(?)'; $types .= 's'; $params[] = $fCourse; }
if ($fQuery !== '') {
	$where[] = "(ss.studentID LIKE ? OR CONCAT_WS(' ', st.fname, st.minitial, st.lname) LIKE ?)";
	$types .= 'ss';
	$like = '%' . $fQuery . '%';
	$params[] = $like;
	$params[] = $like;
}
$sql = 'SELECT ss.*, st.fname, st.minitial, st.lname, st.course FROM student_subject ss LEFT JOIN studenttbl st ON st.studnum = ss.studentID'
	. ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY ss.ID DESC';
$rows = array();
$stmt = mysqli_prepare($conn, $sql);
if ($stmt) {
	if ($params) {
		mysqli_stmt_bind_param($stmt, $types, ...$params);
	}
	mysqli_stmt_execute($stmt);
	$res = mysqli_stmt_get_result($stmt);
	while ($res && ($r = mysqli_fetch_assoc($res))) {
		$rows[] = $r;
	}
	mysqli_stmt_close($stmt);
}

// Summary figures for the filtered rows.
$counts = array('0' => 0, '1' => 0, '2' => 0);
$unitsTotal = 0;
foreach ($rows as $r) {
	$k = (string)$r['approved'];
	$counts[isset($counts[$k]) ? $k : '0']++;
	$unitsTotal += (int)$r['totalunits'];
}

// Subject titles (loaded once) and program list for the filter.
$titles = array();
$subjQ = mysqli_query($conn, 'SELECT subjectcode, subjecttitle FROM subjecttbl');
while ($subjQ && ($s = mysqli_fetch_assoc($subjQ))) {
	$titles[$s['subjectcode']] = $s['subjecttitle'];
}
$courses = array();
$courseQ = mysqli_query($conn, "SELECT DISTINCT LOWER(course) AS c FROM studenttbl WHERE course <> '' ORDER BY c");
while ($courseQ && ($c = mysqli_fetch_assoc($courseQ))) {
	$courses[] = $c['c'];
}
$backFields = array('status' => $fStatus, 'sem' => $fSem, 'course' => $fCourse, 'q' => $fQuery);
$adminName = isset($_SESSION['username']) ? $_SESSION['username'] : 'admin';
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title>Enrollment Report | Long Brown University</title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Lora:wght@500;600&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
	<link href="../style.css?v=20261127" rel="stylesheet" type="text/css" />
</head>
<body class="lbu">
	<header class="lbu-hero">
		<div class="lbu-bar">
			<a class="lbu-logo" href="../adminindex.php" aria-label="Long Brown University home"><img src="../../logo.png" alt="Long Brown University crest"><span>Long Brown University</span><span class="lbu-tag">Admin</span></a>
			<nav class="lbu-nav" aria-label="Administration navigation"><?php $lbuBase = '../'; include('../includes/menu1.php'); ?></nav>
		</div>
		<div class="lbu-pagehead">
			<p class="lbu-eyebrow">Enrollment administration</p>
			<h1>Enrollment report</h1>
			<p>Review enrollment requests submitted by students.</p>
		</div>
	</header>
	<main class="lbu-panel">
		<p class="lbu-print-only">Long Brown University · Enrollment report · Generated <?php echo date('F j, Y g:i A'); ?> by <?php echo enrollment_report_escape($adminName); ?></p>
		<form method="get" class="lbu-form screen-only">
			<div><label class="lbu-label" for="f_status">Status</label><select id="f_status" name="status">
				<option value="">All</option>
				<option value="0"<?php echo $fStatus === '0' ? ' selected' : ''; ?>>Pending</option>
				<option value="1"<?php echo $fStatus === '1' ? ' selected' : ''; ?>>Approved</option>
				<option value="2"<?php echo $fStatus === '2' ? ' selected' : ''; ?>>Rejected</option>
			</select></div>
			<div><label class="lbu-label" for="f_sem">Semester</label><select id="f_sem" name="sem">
				<option value="">All</option>
				<option<?php echo $fSem === '1st Sem' ? ' selected' : ''; ?>>1st Sem</option>
				<option<?php echo $fSem === '2nd Sem' ? ' selected' : ''; ?>>2nd Sem</option>
			</select></div>
			<div><label class="lbu-label" for="f_course">Program</label><select id="f_course" name="course">
				<option value="">All</option>
				<?php foreach ($courses as $c) { ?><option value="<?php echo enrollment_report_escape($c); ?>"<?php echo strtolower($fCourse) === $c ? ' selected' : ''; ?>><?php echo enrollment_report_escape(strtoupper($c)); ?></option><?php } ?>
			</select></div>
			<div><label class="lbu-label" for="f_q">Student name or number</label><input id="f_q" type="text" name="q" maxlength="50" value="<?php echo enrollment_report_escape($fQuery); ?>"></div>
			<div class="lbu-form-actions"><a class="lbu-btn lbu-btn--ghost" href="report.php">Reset</a><button class="lbu-btn" type="submit">Apply filters</button><button class="lbu-btn lbu-btn--gold" type="button" onclick="window.print()">Print report</button></div>
		</form>
		<div class="lbu-strip"><div><small>Requests</small><b><?php echo count($rows); ?></b></div><div><small>Pending</small><b><?php echo $counts['0']; ?></b></div><div><small>Approved</small><b><?php echo $counts['1']; ?></b></div><div><small>Rejected</small><b><?php echo $counts['2']; ?></b></div><div><small>Total units</small><b><?php echo $unitsTotal; ?></b></div></div>
		<div class="lbu-table-wrap"><table class="lbu-table"><thead><tr><th>Student</th><th>Student number</th><th>Program</th><th>Subjects</th><th class="num">Units</th><th>Semester</th><th>Status</th><th class="screen-only">Action</th></tr></thead><tbody>
<?php if (!$rows) { ?><tr><td colspan="8" class="lbu-muted">No enrollment requests match these filters.</td></tr><?php } ?>
<?php foreach ($rows as $request) {
	$name = trim($request['fname'] . ' ' . $request['minitial'] . ' ' . $request['lname']);
	$codes = array_filter(array_map('trim', explode(',', (string)$request['subjects'])), 'strlen');
	$state = (string)$request['approved'];
?>
			<tr>
				<td><?php echo enrollment_report_escape($name ?: 'Student profile unavailable'); ?></td>
				<td><?php echo enrollment_report_escape($request['studentID'] !== '' ? $request['studentID'] : '—'); ?></td>
				<td><?php echo enrollment_report_escape($request['course'] ? strtoupper($request['course']) : '—'); ?></td>
				<td><?php if (!$codes) { echo '—'; } foreach ($codes as $code) { ?><div><strong><?php echo enrollment_report_escape($code); ?></strong><?php echo isset($titles[$code]) ? ' — ' . enrollment_report_escape($titles[$code]) : ''; ?></div><?php } ?></td>
				<td class="num"><?php echo (int)$request['totalunits']; ?></td>
				<td><?php echo enrollment_report_escape(isset($request['semester']) && $request['semester'] !== '' ? $request['semester'] : '—'); ?></td>
				<td><?php if ($state === '1') { ?><span class="lbu-badge lbu-badge--ok">Approved</span><?php } elseif ($state === '2') { ?><span class="lbu-badge">Rejected</span><?php } else { ?><span class="lbu-badge lbu-badge--wait">Pending</span><?php } ?></td>
				<td class="screen-only" style="white-space:nowrap"><?php if ($state === '0') { ?>
					<form method="post" class="lbu-inline" onsubmit="return confirm('Reject this enrollment request?');"><input type="hidden" name="reject" value="<?php echo (int)$request['ID']; ?>" /><?php foreach ($backFields as $bk => $bv) { ?><input type="hidden" name="back[<?php echo $bk; ?>]" value="<?php echo enrollment_report_escape($bv); ?>" /><?php } ?><button class="lbu-btn lbu-btn--sm lbu-btn--ghost" type="submit">Reject</button></form>
					<form method="post" class="lbu-inline" onsubmit="return confirm('Approve this enrollment request?');"><input type="hidden" name="approve" value="<?php echo (int)$request['ID']; ?>" /><?php foreach ($backFields as $bk => $bv) { ?><input type="hidden" name="back[<?php echo $bk; ?>]" value="<?php echo enrollment_report_escape($bv); ?>" /><?php } ?><button class="lbu-btn lbu-btn--sm" type="submit">Approve</button></form>
				<?php } else { echo '—'; } ?></td>
			</tr>
<?php } ?>
		</tbody></table></div>
	</main>
	<footer class="lbu-footer"><p>Long Brown University · Enrollment Administration</p></footer>
</body>
</html>
