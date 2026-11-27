<?php
session_start();
include('includes/conn.php');
include('logcheck.php');

function enroll_escape($value)
{
	return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$student = null;
$subjects = array();
$enrolledCodes = array();
$accountID = isset($_SESSION['userID']) ? (int)$_SESSION['userID'] : 0;
$profileQuery = mysqli_prepare($conn, 'SELECT studnum, course, yearlvl FROM studenttbl WHERE accountID = ? LIMIT 1');
if ($profileQuery) {
	mysqli_stmt_bind_param($profileQuery, 'i', $accountID);
	mysqli_stmt_execute($profileQuery);
	$profileResult = mysqli_stmt_get_result($profileQuery);
	$student = $profileResult ? mysqli_fetch_assoc($profileResult) : null;
	mysqli_stmt_close($profileQuery);
}
if ($student) {
	$enrolledQuery = mysqli_prepare($conn, "SELECT subjects FROM student_subject WHERE studentID = ? AND approved = '1'");
	if ($enrolledQuery) {
		mysqli_stmt_bind_param($enrolledQuery, 's', $student['studnum']);
		mysqli_stmt_execute($enrolledQuery);
		$enrolledResult = mysqli_stmt_get_result($enrolledQuery);
		while ($enrolledResult && ($record = mysqli_fetch_assoc($enrolledResult))) {
			foreach (array_filter(array_map('trim', explode(',', (string)$record['subjects'])), 'strlen') as $enrolledCode) { $enrolledCodes[] = strtoupper($enrolledCode); }
		}
		mysqli_stmt_close($enrolledQuery);
	}
	$yearLevel = (int)preg_replace('/\D/', '', (string)$student['yearlvl']);
	$subjectQuery = mysqli_prepare($conn, 'SELECT subjectcode, subjecttitle, description, numofunits FROM subjecttbl WHERE LOWER(course_con) = LOWER(?) AND year_level = ? ORDER BY subjectcode');
	if ($subjectQuery) {
		mysqli_stmt_bind_param($subjectQuery, 'si', $student['course'], $yearLevel);
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
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title>Enroll Now | Long Brown University</title>
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
			<p class="lbu-eyebrow">Step 1 of 2 · Choose subjects</p>
			<h1>Enroll in subjects</h1>
			<p>Select the subjects for your enrollment request.</p>
			
		</div>
	</header>
	<main class="lbu-panel">
		<?php if (!$student) { ?><p class="lbu-empty">We couldn’t find a student profile for this account. Please contact the university office for help.</p>
		<?php } else { ?>
			<div class="lbu-strip"><div><small>Course</small><b><?php echo enroll_escape(strtoupper($student['course'])); ?></b></div><div><small>Year level</small><b><?php echo enroll_escape($student['yearlvl']); ?></b></div></div>
			<?php if (!$subjects) { ?><div class="lbu-empty">No subjects are currently listed for this course.</div>
			<?php } else { ?>
			<form method="post" action="showsubs.php">
				<div class="lbu-table-wrap"><table class="lbu-table"><thead><tr><th class="num">Select</th><th>Code</th><th>Subject</th><th>Description</th><th class="num">Units</th></tr></thead><tbody>
				<?php foreach ($subjects as $subject) { $isEnrolled = in_array(strtoupper($subject['subjectcode']), $enrolledCodes, true); ?><tr<?php echo $isEnrolled ? ' class="lbu-subject-enrolled"' : ''; ?>><td class="num"><?php if ($isEnrolled) { ?><span class="lbu-badge lbu-badge--ok">Enrolled</span><?php } else { ?><input class="lbu-check" type="checkbox" name="subject[]" value="<?php echo enroll_escape($subject['subjectcode']); ?>" aria-label="Select <?php echo enroll_escape($subject['subjectcode']); ?>" /><?php } ?></td><td><strong><?php echo enroll_escape($subject['subjectcode']); ?></strong></td><td><?php echo enroll_escape($subject['subjecttitle']); ?></td><td><?php echo enroll_escape($subject['description']); ?></td><td class="num"><?php echo (int)$subject['numofunits']; ?></td></tr><?php } ?>
				</tbody></table></div>
				<div class="lbu-actions"><label for="semester" class="lbu-label">Semester</label><select class="lbu-select" id="semester" name="sem"><option value="1st Sem">1st semester</option><option value="2nd Sem">2nd semester</option></select><button class="lbu-btn" type="submit">Review selected subjects →</button></div>
			</form>
			<?php } ?>
		<?php } ?>
	</main>
	<footer class="lbu-footer"><p>Long Brown University · Student Enrollment</p></footer>
</body>
</html>
