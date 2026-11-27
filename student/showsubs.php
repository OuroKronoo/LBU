<?php
session_start();
include('includes/conn.php');
include('logcheck.php');

$semesterColumn = mysqli_query($conn, "SHOW COLUMNS FROM student_subject LIKE 'semester'");
if ($semesterColumn && mysqli_num_rows($semesterColumn) === 0) {
	mysqli_query($conn, "ALTER TABLE student_subject ADD COLUMN semester varchar(20) NOT NULL DEFAULT ''");
}

function curriculum_escape($value)
{
	return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$accountID = isset($_SESSION['userID']) ? (int)$_SESSION['userID'] : 0;
$student = null;
$profileQuery = mysqli_prepare($conn, 'SELECT studnum, fname, minitial, lname, course, yearlvl FROM studenttbl WHERE accountID = ? LIMIT 1');
if ($profileQuery) {
	mysqli_stmt_bind_param($profileQuery, 'i', $accountID);
	mysqli_stmt_execute($profileQuery);
	$profileResult = mysqli_stmt_get_result($profileQuery);
	$student = $profileResult ? mysqli_fetch_assoc($profileResult) : null;
	mysqli_stmt_close($profileQuery);
}
$studentYear = $student ? (int)preg_replace('/\D/', '', (string)$student['yearlvl']) : 0;

$subjects = array();
$selectedCodes = array();
$isEnrollmentPreview = isset($_POST['subject']) && is_array($_POST['subject']);
$semester = isset($_POST['sem']) ? trim($_POST['sem']) : '';
$message = '';

if ($student && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_request'])) {
	$cancelID = (int)$_POST['cancel_request'];
	$cancel = mysqli_prepare($conn, "DELETE FROM student_subject WHERE ID = ? AND studentID = ? AND approved = '0'");
	if ($cancel) { mysqli_stmt_bind_param($cancel, 'is', $cancelID, $student['studnum']); mysqli_stmt_execute($cancel); $cancelled = mysqli_stmt_affected_rows($cancel) > 0; mysqli_stmt_close($cancel); }
	header('Location: studentindex.php?enrollment=' . (!empty($cancelled) ? 'cancelled' : 'unchanged'));
	exit;
}

if ($student && isset($_POST['reg'])) {
	$semester = isset($_POST['sem']) && in_array($_POST['sem'], array('1st Sem', '2nd Sem'), true) ? $_POST['sem'] : '';
	// Never trust posted totals: rebuild the subject list and units from the database.
	$postedCodes = array_unique(array_filter(array_map('trim', explode(',', isset($_POST['contents']) ? (string)$_POST['contents'] : '')), 'strlen'));
	$validCodes = array();
	$units = 0;
	foreach ($postedCodes as $code) {
		$check = mysqli_prepare($conn, 'SELECT subjectcode, numofunits FROM subjecttbl WHERE subjectcode = ? AND LOWER(course_con) = LOWER(?) AND year_level = ? LIMIT 1');
		if (!$check) {
			continue;
		}
		mysqli_stmt_bind_param($check, 'ssi', $code, $student['course'], $studentYear);
		mysqli_stmt_execute($check);
		$checkResult = mysqli_stmt_get_result($check);
		$row = $checkResult ? mysqli_fetch_assoc($checkResult) : null;
		mysqli_stmt_close($check);
		if ($row) {
			$validCodes[] = $row['subjectcode'];
			$units += (int)$row['numofunits'];
		}
	}
	$contents = implode(',', $validCodes);
	$alreadyEnrolledCodes = array();
	$enrolledQuery = mysqli_prepare($conn, "SELECT subjects FROM student_subject WHERE studentID = ? AND approved = '1'");
	if ($enrolledQuery) {
		mysqli_stmt_bind_param($enrolledQuery, 's', $student['studnum']);
		mysqli_stmt_execute($enrolledQuery);
		$enrolledResult = mysqli_stmt_get_result($enrolledQuery);
		while ($enrolledResult && ($enrolledRow = mysqli_fetch_assoc($enrolledResult))) {
			foreach (array_filter(array_map('trim', explode(',', (string)$enrolledRow['subjects'])), 'strlen') as $enrolledCode) {
				$alreadyEnrolledCodes[] = strtoupper($enrolledCode);
			}
		}
		mysqli_stmt_close($enrolledQuery);
	}
	$repeatedCodes = array_values(array_filter($validCodes, function ($code) use ($alreadyEnrolledCodes) {
		return in_array(strtoupper($code), $alreadyEnrolledCodes, true);
	}));
	$alreadyEnrolled = false;
	if ($semester !== '') {
		$dup = mysqli_prepare($conn, "SELECT 1 FROM student_subject WHERE studentID = ? AND semester = ? AND approved IN ('0','1') LIMIT 1");
		if ($dup) {
			mysqli_stmt_bind_param($dup, 'ss', $student['studnum'], $semester);
			mysqli_stmt_execute($dup);
			mysqli_stmt_store_result($dup);
			$alreadyEnrolled = mysqli_stmt_num_rows($dup) > 0;
			mysqli_stmt_close($dup);
		}
	}
	if ($semester === '') {
		$message = 'Please choose a semester before submitting.';
	} elseif (!$validCodes) {
		$message = 'No valid subjects were selected for your course.';
	} elseif ($repeatedCodes) {
		$message = 'You are already enrolled in: ' . implode(', ', $repeatedCodes) . '. Remove those subjects before submitting.';
	} elseif ($alreadyEnrolled) {
		$message = 'You already have a pending or approved enrollment request for ' . $semester . '.';
	} else {
		$insert = mysqli_prepare($conn, "INSERT INTO student_subject (studentID, subjects, totalunits, approved, semester) VALUES (?, ?, ?, '0', ?)");
		if ($insert) {
			mysqli_stmt_bind_param($insert, 'ssis', $student['studnum'], $contents, $units, $semester);
			if (mysqli_stmt_execute($insert)) {
				mysqli_stmt_close($insert);
				header('Location: studentindex.php?enrollment=sent');
				exit;
			}
			mysqli_stmt_close($insert);
		}
		$message = 'Your enrollment request could not be submitted. Please try again.';
	}
}

if ($student && $isEnrollmentPreview) {
	foreach (array_unique($_POST['subject']) as $code) {
		$code = trim((string)$code);
		if ($code === '') {
			continue;
		}
		$subjectQuery = mysqli_prepare($conn, 'SELECT subjectcode, subjecttitle, description, numofunits, room, day, time, instructor, islab, istutorial FROM subjecttbl WHERE subjectcode = ? AND LOWER(course_con) = LOWER(?) AND year_level = ? LIMIT 1');
		if (!$subjectQuery) {
			continue;
		}
		mysqli_stmt_bind_param($subjectQuery, 'ssi', $code, $student['course'], $studentYear);
		mysqli_stmt_execute($subjectQuery);
		$subjectResult = mysqli_stmt_get_result($subjectQuery);
		$subject = $subjectResult ? mysqli_fetch_assoc($subjectResult) : null;
		mysqli_stmt_close($subjectQuery);
		if ($subject) {
			$subjects[] = $subject;
			$selectedCodes[] = $subject['subjectcode'];
		}
	}
} elseif ($student) {
	$subjectQuery = mysqli_prepare($conn, 'SELECT subjectcode, subjecttitle, description, numofunits, room, day, time, instructor, islab, istutorial FROM subjecttbl WHERE LOWER(course_con) = LOWER(?) AND year_level = ? ORDER BY subjectcode');
	if ($subjectQuery) {
		mysqli_stmt_bind_param($subjectQuery, 'si', $student['course'], $studentYear);
		mysqli_stmt_execute($subjectQuery);
		$subjectResult = mysqli_stmt_get_result($subjectQuery);
		if ($subjectResult) {
			while ($subject = mysqli_fetch_assoc($subjectResult)) {
				$subjects[] = $subject;
			}
		}
		mysqli_stmt_close($subjectQuery);
	}
}

$lbuActive = $isEnrollmentPreview ? 'enroll.php' : 'showsubs.php';
$totalUnits = 0;
$extraFees = 0;
$labCount = 0;
$tutorialCount = 0;
foreach ($subjects as $subject) {
	$totalUnits += (int)$subject['numofunits'];
	if ((int)$subject['islab']) { $labCount++; $extraFees += 1500; }
	if ((int)$subject['istutorial']) { $tutorialCount++; $extraFees += 1500; }
}
$tuitionFee = $totalUnits * 296;
$miscellaneousFee = 2050;
$registrationFee = 150;
$systemFee = 50;
$total = $tuitionFee + $miscellaneousFee + $registrationFee + $systemFee + $extraFees;
$studentName = $student ? trim($student['fname'] . ' ' . $student['minitial'] . ' ' . $student['lname']) : '';
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title><?php echo $isEnrollmentPreview ? 'Enrollment Preview' : 'Curriculum'; ?> | Long Brown University</title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Lora:wght@500;600&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
	<link href="style.css?v=20261127" rel="stylesheet" type="text/css" />
</head>
<body class="lbu">
<?php if ($isEnrollmentPreview) { ?>
	<header class="lbu-hero">
		<div class="lbu-bar">
			<a class="lbu-logo" href="studentindex.php" aria-label="Long Brown University home"><img src="../logo.png" alt="Long Brown University crest"><span>Long Brown University</span></a>
			<nav class="lbu-nav" aria-label="Student navigation"><?php include('includes/menu.php'); ?></nav>
		</div>
		<div class="lbu-pagehead">
			<p class="lbu-eyebrow">Step 2 of 2 · Review fees</p>
			<h1>Enrollment preview</h1>
			<p>Review your selected subjects and estimated fees before submitting.</p>
			
		</div>
	</header>
<?php } else { ?>
	<header class="lbu-hero">
		<div class="lbu-bar">
			<a class="lbu-logo" href="studentindex.php" aria-label="Long Brown University home"><img src="../logo.png" alt="Long Brown University crest"><span>Long Brown University</span></a>
			<nav class="lbu-nav" aria-label="Student navigation"><?php include('includes/menu.php'); ?></nav>
		</div>
		<div class="lbu-pagehead">
			<p class="lbu-eyebrow">Student portal</p>
			<h1><?php echo $student ? curriculum_escape(strtoupper($student['course'])) . ' curriculum' : 'Curriculum'; ?></h1>
			<p>Subjects currently listed for your program.</p>
			
		</div>
	</header>
<?php } ?>
	<main class="lbu-panel">
		<?php if ($message !== '') { ?><p class="lbu-alert" role="alert"><?php echo curriculum_escape($message); ?></p><?php } ?>
		<?php if (!$student) { ?>
			<p class="lbu-empty">We could not find a student profile for this account. Please contact the university office for help.</p>
		<?php } elseif ($isEnrollmentPreview) { ?>
			<div class="lbu-strip"><div><small>Student</small><b><?php echo curriculum_escape($studentName); ?></b></div><div><small>Student number</small><b><?php echo curriculum_escape($student['studnum']); ?></b></div><div><small>Course</small><b><?php echo curriculum_escape(strtoupper($student['course'])); ?></b></div><div><small>Year level</small><b><?php echo curriculum_escape($student['yearlvl']); ?></b></div><div><small>Semester</small><b><?php echo curriculum_escape($semester !== '' ? $semester : 'Not selected'); ?></b></div></div>
			<?php if (!$subjects) { ?><div class="lbu-empty">No valid subjects were selected. Return to enrollment and choose at least one subject.</div>
			<?php } else { ?>
				<div class="lbu-table-wrap"><table class="lbu-table"><thead><tr><th>Subject</th><th>Description</th><th>Schedule</th><th class="num">Units</th><th>Est. tuition</th></tr></thead><tbody>
				<?php foreach ($subjects as $subject) { ?><tr><td><strong><?php echo curriculum_escape($subject['subjectcode']); ?></strong><br><?php echo curriculum_escape($subject['subjecttitle']); ?></td><td><?php echo curriculum_escape($subject['description']); ?></td><td><?php echo curriculum_escape(trim($subject['day'] . ' ' . $subject['time'] . ' ' . $subject['room'])); ?><br><?php echo curriculum_escape($subject['instructor']); ?></td><td class="num"><?php echo (int)$subject['numofunits']; ?></td><td>₱<?php echo number_format((int)$subject['numofunits'] * 296, 2); ?></td></tr><?php } ?>
				</tbody></table></div>
				<div class="lbu-fee-breakdown"><h2>Estimated fee breakdown</h2><div><span>Tuition (<?php echo $totalUnits; ?> units × ₱296)</span><b>₱<?php echo number_format($tuitionFee, 2); ?></b></div><div><span>Miscellaneous fee</span><b>₱<?php echo number_format($miscellaneousFee, 2); ?></b></div><div><span>Registration fee</span><b>₱<?php echo number_format($registrationFee, 2); ?></b></div><div><span>System fee</span><b>₱<?php echo number_format($systemFee, 2); ?></b></div><?php if ($labCount) { ?><div><span>Laboratory fee (<?php echo $labCount; ?> × ₱1,500)</span><b>₱<?php echo number_format($labCount * 1500, 2); ?></b></div><?php } ?><?php if ($tutorialCount) { ?><div><span>Tutorial fee (<?php echo $tutorialCount; ?> × ₱1,500)</span><b>₱<?php echo number_format($tutorialCount * 1500, 2); ?></b></div><?php } ?><div class="lbu-fee-total"><span>Estimated total</span><b>₱<?php echo number_format($total, 2); ?></b></div><p class="lbu-muted">This is an estimate based on your selected subjects and the fee rates configured for this system.</p></div>
				<form method="post" class="lbu-actions screen-only" onsubmit="return confirm('Submit this enrollment request?');">
					<input type="hidden" name="reg" value="1" /><input type="hidden" name="contents" value="<?php echo curriculum_escape(implode(',', $selectedCodes)); ?>" /><input type="hidden" name="units" value="<?php echo (int)$totalUnits; ?>" /><input type="hidden" name="sem" value="<?php echo curriculum_escape($semester); ?>" />
					<a class="lbu-btn lbu-btn--ghost" href="enroll.php">← Back to subjects</a><button class="lbu-btn lbu-btn--gold" type="submit">Submit enrollment request</button>
				</form>
			<?php } ?>
		<?php } else { ?>
			<div class="lbu-strip"><div><small>Student</small><b><?php echo curriculum_escape($studentName); ?></b></div><div><small>Year level</small><b><?php echo curriculum_escape($student['yearlvl']); ?></b></div><div><small>Subjects listed</small><b><?php echo count($subjects); ?></b></div></div>
			<?php if (!$subjects) { ?><div class="lbu-empty">There are no subjects listed for this course yet. Please check back later or contact the university office.</div>
			<?php } else { ?><div class="lbu-table-wrap"><table class="lbu-table"><thead><tr><th>Subject code</th><th>Subject title</th><th>Description</th><th class="num">Units</th><th>Schedule</th></tr></thead><tbody>
				<?php foreach ($subjects as $subject) { ?><tr><td><strong><?php echo curriculum_escape($subject['subjectcode']); ?></strong></td><td><?php echo curriculum_escape($subject['subjecttitle']); ?></td><td><?php echo curriculum_escape($subject['description']); ?></td><td class="num"><?php echo (int)$subject['numofunits']; ?></td><td><?php echo curriculum_escape(trim($subject['day'] . ' ' . $subject['time'] . ' ' . $subject['room'])); ?></td></tr><?php } ?>
				</tbody></table></div><div class="lbu-actions"><a class="lbu-btn" href="enroll.php">Go to enrollment</a></div><?php } ?>
		<?php } ?>
	</main>
	<footer class="lbu-footer"><p>Long Brown University · Student Enrollment</p></footer>
</body>
</html>
