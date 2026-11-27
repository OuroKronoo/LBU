<?php
session_start();
include('logcheck.php');
include('includes/conn.php');
function subjectreg_escape($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
$programs = array('bsit' => 'BSIT', 'bsce' => 'BSCE', 'bsed' => 'BSED');
$days = array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday');
$startTimes = array('07:00' => '7:00 AM', '09:00' => '9:00 AM', '10:30' => '10:30 AM', '12:30' => '12:30 PM', '14:00' => '2:00 PM', '15:00' => '3:00 PM', '17:00' => '5:00 PM', '17:30' => '5:30 PM', '18:00' => '6:00 PM', '18:30' => '6:30 PM');
$rooms = array('bsit' => array('BSIT ROOM 1', 'BSIT ROOM 2', 'BSIT ROOM 3', 'BSIT ROOM 4', 'BSIT ROOM 5'), 'bsce' => array('BSCE ROOM 1', 'BSCE ROOM 2', 'BSCE ROOM 3', 'BSCE ROOM 4', 'BSCE ROOM 5'), 'bsed' => array('BSED ROOM 1', 'BSED ROOM 2', 'BSED ROOM 3', 'BSED ROOM 4', 'BSED ROOM 5'));
function subject_schedule_conflicts($conn, $room, $day, $startMinutes, $endMinutes, $excludeID = 0)
{
	$schedules = mysqli_prepare($conn, 'SELECT ID, time FROM subjecttbl WHERE room = ? AND day = ? AND ID <> ?');
	if (!$schedules) { return true; }
	mysqli_stmt_bind_param($schedules, 'ssi', $room, $day, $excludeID);
	mysqli_stmt_execute($schedules);
	$result = mysqli_stmt_get_result($schedules);
	$conflict = false;
	while ($result && ($schedule = mysqli_fetch_assoc($result))) {
		if (!preg_match('/^(\d{2}):(\d{2})-(\d{2}):(\d{2})$/', trim($schedule['time']), $matches)) { continue; }
		$existingStart = ((int)$matches[1] * 60) + (int)$matches[2];
		$existingEnd = ((int)$matches[3] * 60) + (int)$matches[4];
		if ($startMinutes < $existingEnd && $existingStart < $endMinutes) { $conflict = true; break; }
	}
	mysqli_stmt_close($schedules);
	return $conflict;
}

// Edit a subject (POST), then show the edit form for ?edit=ID (GET).
$editError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_subject'])) {
	$f = array();
	foreach (array('subjectcode', 'subjecttitle', 'description', 'course_con', 'day', 'time', 'room', 'instructor') as $k) { $f[$k] = trim($_POST[$k] ?? ''); }
	$units = (int)($_POST['numofunits'] ?? 0);
	$yearLevel = (int)($_POST['year_level'] ?? 0);
	$startTime = $_POST['start_time'] ?? ''; $minutes = $startTime !== '' ? (int)substr($startTime, 0, 2) * 60 + (int)substr($startTime, 3, 2) : 0;
	if ($f['subjectcode'] === '' || $f['subjecttitle'] === '' || !isset($programs[strtolower($f['course_con'])]) || !in_array($units, array(2, 3, 4, 5), true) || $yearLevel < 1 || $yearLevel > 4 || !in_array($f['day'], $days, true) || !isset($startTimes[$startTime]) || $minutes + ($units * 60) > 20 * 60 + 30 || ($minutes >= 17 * 60 + 30 && $units > 3)) {
		$editError = 'Choose a defined program, year level (1-4), and units (1-12). Code and title are required.';
	} else {
		$f['time'] = $startTime . '-' . sprintf('%02d:%02d', (int)(($minutes + $units * 60) / 60), ($minutes + $units * 60) % 60);
		$f['course_con'] = strtolower($f['course_con']);
		$occupied = subject_schedule_conflicts($conn, $f['room'], $f['day'], $minutes, $minutes + ($units * 60));
		$dup = mysqli_prepare($conn, 'SELECT 1 FROM subjecttbl WHERE subjectcode = ? AND LOWER(course_con) = LOWER(?) LIMIT 1');
		mysqli_stmt_bind_param($dup, 'ss', $f['subjectcode'], $f['course_con']); mysqli_stmt_execute($dup); mysqli_stmt_store_result($dup); $taken = mysqli_stmt_num_rows($dup) > 0; mysqli_stmt_close($dup);
		if (!in_array($f['room'], $rooms[$f['course_con']], true)) { $editError = 'Choose a room assigned to the selected program.'; }
		elseif ($occupied) { $editError = 'That room is occupied during part or all of this day and time.'; }
		elseif ($taken) { $editError = 'That subject code already exists for this program.'; }
		else {
			$add = mysqli_prepare($conn, 'INSERT INTO subjecttbl (subjectcode, subjecttitle, description, numofunits, course_con, day, time, room, instructor, islab, istutorial, year_level) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
			$lab = isset($_POST['islab']) ? 1 : 0; $tutorial = isset($_POST['istutorial']) ? 1 : 0;
			mysqli_stmt_bind_param($add, 'sssisssssiii', $f['subjectcode'], $f['subjecttitle'], $f['description'], $units, $f['course_con'], $f['day'], $f['time'], $f['room'], $f['instructor'], $lab, $tutorial, $yearLevel);
			if (mysqli_stmt_execute($add)) { mysqli_stmt_close($add); header('Location: subjectreg.php?added=1'); exit; }
			mysqli_stmt_close($add); $editError = 'The subject could not be added.';
		}
	}
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_subject'])) {
	$editID = (int)$_POST['save_subject'];
	$f = array();
	foreach (array('subjectcode', 'subjecttitle', 'description', 'course_con', 'day', 'time', 'room', 'instructor') as $k) {
		$f[$k] = trim($_POST[$k] ?? '');
	}
	$units = (int)($_POST['numofunits'] ?? 0);
	$yearLevel = (int)($_POST['year_level'] ?? 0);
	$startTime = $_POST['start_time'] ?? ''; $minutes = $startTime !== '' ? (int)substr($startTime, 0, 2) * 60 + (int)substr($startTime, 3, 2) : 0;
	if ($f['subjectcode'] === '' || $f['subjecttitle'] === '' || !isset($programs[strtolower($f['course_con'])]) || !in_array($units, array(2, 3, 4, 5), true) || $yearLevel < 1 || $yearLevel > 4 || !in_array($f['day'], $days, true) || !isset($startTimes[$startTime]) || $minutes + ($units * 60) > 20 * 60 + 30 || ($minutes >= 17 * 60 + 30 && $units > 3)) {
		$editError = 'Choose a defined program, year level (1-4), and units (1-12). Code and title are required.';
	} else {
		$f['time'] = $startTime . '-' . sprintf('%02d:%02d', (int)(($minutes + $units * 60) / 60), ($minutes + $units * 60) % 60);
		$f['course_con'] = strtolower($f['course_con']);
		$occupied = subject_schedule_conflicts($conn, $f['room'], $f['day'], $minutes, $minutes + ($units * 60), $editID);
		$cur = mysqli_prepare($conn, 'SELECT subjectcode FROM subjecttbl WHERE ID = ?');
		mysqli_stmt_bind_param($cur, 'i', $editID);
		mysqli_stmt_execute($cur);
		mysqli_stmt_bind_result($cur, $oldCode);
		$exists = mysqli_stmt_fetch($cur);
		mysqli_stmt_close($cur);
		$dup = mysqli_prepare($conn, 'SELECT 1 FROM subjecttbl WHERE subjectcode = ? AND LOWER(course_con) = LOWER(?) AND ID <> ? LIMIT 1');
		mysqli_stmt_bind_param($dup, 'ssi', $f['subjectcode'], $f['course_con'], $editID);
		mysqli_stmt_execute($dup);
		mysqli_stmt_store_result($dup);
		$taken = mysqli_stmt_num_rows($dup) > 0;
		mysqli_stmt_close($dup);
		if (!in_array($f['room'], $rooms[$f['course_con']], true)) {
			$editError = 'Choose a room assigned to the selected program.';
		} elseif ($occupied) {
			$editError = 'That room is occupied during part or all of this day and time.';
		} elseif (!$exists) {
			$editError = 'Subject not found.';
		} elseif ($taken) {
			$editError = 'That subject code already exists for this program.';
		} else {
			$upd = mysqli_prepare($conn, 'UPDATE subjecttbl SET subjectcode = ?, subjecttitle = ?, description = ?, numofunits = ?, course_con = ?, day = ?, time = ?, room = ?, instructor = ?, year_level = ? WHERE ID = ?');
			mysqli_stmt_bind_param($upd, 'sssisssssii', $f['subjectcode'], $f['subjecttitle'], $f['description'], $units, $f['course_con'], $f['day'], $f['time'], $f['room'], $f['instructor'], $yearLevel, $editID);
			mysqli_stmt_execute($upd);
			mysqli_stmt_close($upd);
			header('Location: subjectreg.php?saved=1');
			exit;
		}
	}
	$_GET['edit'] = $editID;
}
$editSubject = null;
if (isset($_GET['edit'])) {
	$editQuery = mysqli_prepare($conn, 'SELECT ID, subjectcode, subjecttitle, description, numofunits, course_con, day, time, room, instructor, year_level FROM subjecttbl WHERE ID = ? LIMIT 1');
	$editWanted = (int)$_GET['edit'];
	mysqli_stmt_bind_param($editQuery, 'i', $editWanted);
	mysqli_stmt_execute($editQuery);
	$editResult = mysqli_stmt_get_result($editQuery);
	$editSubject = $editResult ? mysqli_fetch_assoc($editResult) : null;
	mysqli_stmt_close($editQuery);
	if ($editSubject && $editError !== '') {
		foreach ($editSubject as $k => $v) {
			if (isset($_POST[$k])) { $editSubject[$k] = $_POST[$k]; }
		}
	}
}
$subjects = mysqli_query($conn, 'SELECT ID, subjectcode, subjecttitle, description, numofunits, course_con, day, time, room, instructor, year_level FROM subjecttbl ORDER BY course_con, year_level, subjectcode');
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title>Subjects | Long Brown University</title>
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
			<h1>Subject catalog</h1>
			<p>Subjects currently listed for university programs.</p>
			
		</div>
	</header>
	<main class="lbu-panel">
<?php if (isset($_GET['saved'])) { ?><p class="lbu-alert" role="status">Subject updated.</p><?php } ?>
<?php if (isset($_GET['added'])) { ?><p class="lbu-alert" role="status">Subject added to the catalog.</p><?php } ?>
<?php if (isset($_GET['delete']) && $_GET['delete'] === 'inuse') { ?><p class="lbu-alert" role="alert">This subject is used in enrollment records, so it cannot be deleted. You can still edit its details and schedule.</p><?php } elseif (isset($_GET['delete']) && $_GET['delete'] === 'done') { ?><p class="lbu-alert" role="status">Subject deleted.</p><?php } ?>
<?php if ($editError !== '') { ?><p class="lbu-alert" role="alert"><?php echo subjectreg_escape($editError); ?></p><?php } ?>
<details class="lbu-add-panel"<?php echo ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_subject']) && $editError !== '') ? ' open' : ''; ?>><summary class="lbu-btn lbu-btn--gold">+ Add subject</summary>
<form method="post" class="lbu-form lbu-subject-form"><input type="hidden" name="add_subject" value="1" />
	<div><label class="lbu-label" for="a_subjectcode">Code</label><input id="a_subjectcode" name="subjectcode" maxlength="100" required value="<?php echo subjectreg_escape($_POST['subjectcode'] ?? ''); ?>"></div>
	<div><label class="lbu-label" for="a_subjecttitle">Title</label><input id="a_subjecttitle" name="subjecttitle" maxlength="100" required value="<?php echo subjectreg_escape($_POST['subjecttitle'] ?? ''); ?>"></div>
	<div class="lbu-wide"><label class="lbu-label" for="a_description">Description</label><input id="a_description" name="description" maxlength="100" value="<?php echo subjectreg_escape($_POST['description'] ?? ''); ?>"></div>
	<div><label class="lbu-label" for="a_units">Units</label><select id="a_units" name="numofunits" required><?php foreach (array(2, 3, 4, 5) as $unitOption) { ?><option value="<?php echo $unitOption; ?>"<?php echo (int)($_POST['numofunits'] ?? 3) === $unitOption ? ' selected' : ''; ?>><?php echo $unitOption; ?></option><?php } ?></select></div>
	<div><label class="lbu-label" for="a_course">Program</label><select id="a_course" name="course_con" required><?php foreach ($programs as $code => $label) { ?><option value="<?php echo $code; ?>"<?php echo strtolower($_POST['course_con'] ?? '') === $code ? ' selected' : ''; ?>><?php echo $label; ?></option><?php } ?></select></div>
	<div><label class="lbu-label" for="a_year">Year level</label><select id="a_year" name="year_level" required><?php for ($year = 1; $year <= 4; $year++) { ?><option value="<?php echo $year; ?>"<?php echo (int)($_POST['year_level'] ?? 1) === $year ? ' selected' : ''; ?>>Year <?php echo $year; ?></option><?php } ?></select></div>
	<div><label class="lbu-label" for="a_day">Day</label><select id="a_day" name="day" required><?php foreach ($days as $dayOption) { ?><option value="<?php echo $dayOption; ?>"<?php echo ($_POST['day'] ?? '') === $dayOption ? ' selected' : ''; ?>><?php echo $dayOption; ?></option><?php } ?></select></div><div class="lbu-time-field"><label class="lbu-label" for="a_time">Start time</label><select id="a_time" name="start_time" required><?php foreach ($startTimes as $timeValue => $timeLabel) { ?><option value="<?php echo $timeValue; ?>"<?php echo ($_POST['start_time'] ?? '07:00') === $timeValue ? ' selected' : ''; ?>><?php echo $timeLabel; ?></option><?php } ?></select><small>Ends at <strong id="a_end_time">9:00 AM</strong></small></div>
	<div><label class="lbu-label" for="a_room">Room</label><select id="a_room" name="room" required><?php foreach ($rooms['bsit'] as $roomOption) { ?><option value="<?php echo $roomOption; ?>"><?php echo $roomOption; ?></option><?php } ?></select></div><div><label class="lbu-label" for="a_instructor">Instructor</label><input id="a_instructor" name="instructor" maxlength="100" value="<?php echo subjectreg_escape($_POST['instructor'] ?? ''); ?>"></div>
	<div><label class="lbu-check-row"><input class="lbu-check" type="checkbox" name="islab" value="1"> Laboratory subject</label></div><div><label class="lbu-check-row"><input class="lbu-check" type="checkbox" name="istutorial" value="1"> Tutorial subject</label></div>
	<div class="lbu-form-actions"><button class="lbu-btn lbu-btn--gold" type="submit">Add to catalog</button></div>
</form></details>
<?php if ($editSubject) { ?>
<form method="post" class="lbu-form lbu-subject-form">
	<h2>Edit subject: <?php echo subjectreg_escape($editSubject['subjectcode']); ?></h2>
	<input type="hidden" name="save_subject" value="<?php echo (int)$editSubject['ID']; ?>" />
	<div><label class="lbu-label" for="f_subjectcode">Code</label><input type="text" name="subjectcode" id="f_subjectcode" maxlength="100" required value="<?php echo subjectreg_escape($editSubject['subjectcode']); ?>"></div>
	<div><label class="lbu-label" for="f_subjecttitle">Title</label><input type="text" name="subjecttitle" id="f_subjecttitle" maxlength="100" required value="<?php echo subjectreg_escape($editSubject['subjecttitle']); ?>"></div>
	<div class="lbu-wide"><label class="lbu-label" for="f_description">Description</label><input type="text" name="description" id="f_description" maxlength="100" value="<?php echo subjectreg_escape($editSubject['description']); ?>"></div>
	<div><label class="lbu-label" for="f_numofunits">Units</label><select name="numofunits" id="f_numofunits" required><?php foreach (array(2, 3, 4, 5) as $unitOption) { ?><option value="<?php echo $unitOption; ?>"<?php echo (int)$editSubject['numofunits'] === $unitOption ? ' selected' : ''; ?>><?php echo $unitOption; ?></option><?php } ?></select></div>
	<div><label class="lbu-label" for="f_course_con">Program</label><select name="course_con" id="f_course_con" required><?php foreach ($programs as $code => $label) { ?><option value="<?php echo $code; ?>"<?php echo strtolower($editSubject['course_con']) === $code ? ' selected' : ''; ?>><?php echo $label; ?></option><?php } ?></select></div>
	<div><label class="lbu-label" for="f_year_level">Year level</label><select name="year_level" id="f_year_level" required><?php for ($year = 1; $year <= 4; $year++) { ?><option value="<?php echo $year; ?>"<?php echo (int)$editSubject['year_level'] === $year ? ' selected' : ''; ?>>Year <?php echo $year; ?></option><?php } ?></select></div>
	<div><label class="lbu-label" for="f_day">Day</label><select name="day" id="f_day" required><?php foreach ($days as $dayOption) { ?><option value="<?php echo $dayOption; ?>"<?php echo $editSubject['day'] === $dayOption ? ' selected' : ''; ?>><?php echo $dayOption; ?></option><?php } ?></select></div>
	<div class="lbu-time-field"><label class="lbu-label" for="f_time">Start time</label><select name="start_time" id="f_time" required><?php $savedStart = substr($editSubject['time'], 0, 5); foreach ($startTimes as $timeValue => $timeLabel) { ?><option value="<?php echo $timeValue; ?>"<?php echo $savedStart === $timeValue ? ' selected' : ''; ?>><?php echo $timeLabel; ?></option><?php } ?></select><small>Ends at <strong id="f_end_time"></strong></small></div>
	<div><label class="lbu-label" for="f_room">Room</label><select name="room" id="f_room" required><?php foreach ($rooms[strtolower($editSubject['course_con'])] ?? $rooms['bsit'] as $roomOption) { ?><option value="<?php echo $roomOption; ?>"<?php echo $editSubject['room'] === $roomOption ? ' selected' : ''; ?>><?php echo $roomOption; ?></option><?php } ?></select></div>
	<div><label class="lbu-label" for="f_instructor">Instructor</label><input type="text" name="instructor" id="f_instructor" maxlength="100" value="<?php echo subjectreg_escape($editSubject['instructor']); ?>"></div>
	<div class="lbu-form-actions"><a class="lbu-btn lbu-btn--ghost" href="subjectreg.php">Cancel</a><button class="lbu-btn lbu-btn--gold" type="submit">Save changes</button></div>
</form>
<?php } ?>
<div class="lbu-table-wrap"><table class="lbu-table"><thead><tr><th>Code</th><th>Subject</th><th>Description</th><th class="num">Units</th><th>Program</th><th>Year</th><th>Schedule</th><th>Action</th></tr></thead><tbody>
<?php if ($subjects) { while ($subject = mysqli_fetch_assoc($subjects)) { ?><tr><td><strong><?php echo subjectreg_escape($subject['subjectcode']); ?></strong></td><td><?php echo subjectreg_escape($subject['subjecttitle']); ?></td><td><?php echo subjectreg_escape($subject['description']); ?></td><td class="num"><?php echo (int)$subject['numofunits']; ?></td><td><?php echo subjectreg_escape(strtoupper($subject['course_con'])); ?></td><td>Year <?php echo (int)$subject['year_level']; ?></td><td><?php echo subjectreg_escape(trim($subject['day'].' '.$subject['time'].' '.$subject['room']) ?: '—'); ?></td><td style="white-space:nowrap"><a class="lbu-btn lbu-btn--sm" href="subjectreg.php?edit=<?php echo (int)$subject['ID']; ?>">Edit</a> <form class="lbu-inline" method="post" action="deletecurrsubj.php" onsubmit="return confirm('Delete this subject?');"><input type="hidden" name="accid" value="<?php echo (int)$subject['ID']; ?>" /><button class="lbu-btn lbu-btn--sm lbu-btn--ghost" type="submit">Delete</button></form></td></tr><?php } } ?></tbody></table></div>
<script>(function(){var rooms=<?php echo json_encode($rooms); ?>;function bind(uId,tId,eId){var u=document.getElementById(uId),t=document.getElementById(tId),e=document.getElementById(eId);if(!u||!t||!e)return;function update(){var p=t.value.split(':'),s=+p[0]*60+(+p[1]),x=s+(+u.value*60),h=Math.floor(x/60);Array.prototype.forEach.call(u.options,function(o){o.disabled=s+(parseInt(o.value,10)*60)>1230});if(u.selectedOptions[0].disabled){var valid=Array.prototype.filter.call(u.options,function(o){return !o.disabled});if(valid.length)u.value=valid[0].value;x=s+(+u.value*60);h=Math.floor(x/60)}e.textContent=(h%12||12)+':'+('0'+x%60).slice(-2)+' '+(h>=12?'PM':'AM')}u.addEventListener('change',update);t.addEventListener('change',update);update()}function bindRooms(courseId,roomId){var c=document.getElementById(courseId),r=document.getElementById(roomId);if(!c||!r)return;function update(){var old=r.value,list=rooms[c.value]||[];r.innerHTML='';list.forEach(function(name){var option=document.createElement('option');option.value=name;option.textContent=name;r.appendChild(option)});if(list.indexOf(old)>=0)r.value=old}c.addEventListener('change',update);update()}bind('a_units','a_time','a_end_time');bind('f_numofunits','f_time','f_end_time');bindRooms('a_course','a_room');bindRooms('f_course_con','f_room')}());</script></main>
	<footer class="lbu-footer"><p>Long Brown University · Enrollment Administration</p></footer>
</body>
</html>
