<?php
session_start();
include('includes/conn.php');
include('logcheck.php');

function home_escape($value)
{
	return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Serve the image of a visible article (images are stored in the database).
if (isset($_GET['img'])) {
	$imgID = (int)$_GET['img'];
	$q = mysqli_prepare($conn, 'SELECT image, image_type FROM articles_tbl WHERE ID = ? AND visible = 1 AND image IS NOT NULL');
	mysqli_stmt_bind_param($q, 'i', $imgID);
	mysqli_stmt_execute($q);
	mysqli_stmt_bind_result($q, $imgBlob, $imgMime);
	if (mysqli_stmt_fetch($q)) {
		header('Content-Type: ' . $imgMime);
		header('X-Content-Type-Options: nosniff');
		echo $imgBlob;
		exit;
	}
	http_response_code(404);
	exit;
}

$student = null;
$accountID = isset($_SESSION['userID']) ? (int)$_SESSION['userID'] : 0;
$profileQuery = mysqli_prepare($conn, 'SELECT studnum, fname, course, yearlvl FROM studenttbl WHERE accountID = ? LIMIT 1');
if ($profileQuery) {
	mysqli_stmt_bind_param($profileQuery, 'i', $accountID);
	mysqli_stmt_execute($profileQuery);
	$profileResult = mysqli_stmt_get_result($profileQuery);
	$student = $profileResult ? mysqli_fetch_assoc($profileResult) : null;
	mysqli_stmt_close($profileQuery);
}

$enrollments = array();
if ($student) {
	$history = mysqli_prepare($conn, 'SELECT ID, subjects, totalunits, approved FROM student_subject WHERE studentID = ? ORDER BY ID DESC LIMIT 10');
	if ($history) {
		mysqli_stmt_bind_param($history, 's', $student['studnum']);
		mysqli_stmt_execute($history);
		$historyResult = mysqli_stmt_get_result($history);
		while ($historyResult && ($enrollment = mysqli_fetch_assoc($historyResult))) { $enrollment['semester'] = ''; $enrollments[] = $enrollment; }
		mysqli_stmt_close($history);
	}
}

// Visible campus updates, managed by the admin on the Admin home page.
$articles = array();
$articleResult = mysqli_query($conn, 'SELECT ID, label, title, body, (image IS NOT NULL) AS has_image FROM articles_tbl WHERE visible = 1 ORDER BY sort_order, ID');
while ($articleResult && ($articleRow = mysqli_fetch_assoc($articleResult))) {
	$articles[] = $articleRow;
}
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title>Student Home | Long Brown University</title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Lora:wght@500;600&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
	<link href="style.css?v=20261128" rel="stylesheet" type="text/css" />
</head>
<body class="lbu">
	<header class="lbu-hero lbu-hero--home">
		<div class="lbu-bar">
			<a class="lbu-logo" href="studentindex.php" aria-label="Long Brown University home"><img src="../logo.png" alt="Long Brown University crest"><span>Long Brown University</span></a>
			<nav class="lbu-nav" aria-label="Student navigation"><?php include('includes/menu.php'); ?></nav>
		</div>
		<div class="lbu-pagehead">
			<p class="lbu-eyebrow">Student portal</p>
			<h1>Welcome back<?php echo $student ? ',<br>'.home_escape($student['fname']).'.' : '.'; ?></h1>
			<p>Learn with purpose. Lead with heart.</p>
			<div class="lbu-actions lbu-hero-actions"><a class="lbu-btn lbu-btn--gold" href="enroll.php">Enroll now</a><a class="lbu-btn lbu-btn--line" href="showsubs.php">View curriculum</a></div>
		</div>
	</header>
	<main class="lbu-panel">
		<?php if (isset($_GET['enrollment']) && $_GET['enrollment'] === 'sent') { ?><p class="lbu-alert" role="status"><strong>Your enrollment request was submitted.</strong> It is now pending review by the university office.</p><?php } ?>
		<?php if ($student) { ?><div class="lbu-strip">
			<div><small>Program</small><b><?php echo home_escape(strtoupper($student['course'])); ?></b></div>
			<div><small>Year level</small><b><?php echo home_escape($student['yearlvl']); ?></b></div>
			<div><small>Student no.</small><b><?php echo home_escape($student['studnum']); ?></b></div>
		</div><?php } else { ?><p class="lbu-empty">Your student portal is ready. Review your curriculum, check your profile, or continue to subject enrollment.</p><?php } ?>
		<?php if ($student) { ?><section class="lbu-enrollment-history"><button class="lbu-btn lbu-btn--gold lbu-enrollment-toggle" type="button" aria-expanded="false" aria-controls="enrollment-history-panel"><span>View enrollment requests</span><span class="lbu-enrollment-count"><?php echo count($enrollments); ?> recent</span><span class="lbu-enrollment-icon" aria-hidden="true">+</span></button><div class="lbu-enrollment-panel" id="enrollment-history-panel" hidden><?php if (!$enrollments) { ?><p class="lbu-empty">You have not submitted an enrollment request yet.</p><?php } else { ?><div class="lbu-table-wrap"><table class="lbu-table"><thead><tr><th>Semester</th><th>Subjects</th><th>Units</th><th>Status</th></tr></thead><tbody><?php foreach ($enrollments as $enrollment) { $state = (string)$enrollment['approved']; ?><tr><td><?php echo home_escape($enrollment['semester'] ?: 'Legacy record'); ?></td><td><?php echo home_escape(str_replace(',', ', ', trim($enrollment['subjects'], ','))); ?></td><td><?php echo (int)$enrollment['totalunits']; ?></td><td><?php echo $state === '1' ? 'Approved' : ($state === '2' ? 'Rejected' : 'Pending review'); ?></td></tr><?php } ?></tbody></table></div><?php } ?></div></section><?php } ?>
<?php if ($articles) { ?>
			<h2>Campus updates</h2>
			<div class="lbu-cards">
<?php foreach ($articles as $article) {
	$imageURL = $article['has_image'] ? 'studentindex.php?img=' . (int)$article['ID'] : '';
	$art = $imageURL !== '' ? '<img src="' . home_escape($imageURL) . '" alt="">' : '<div class="lbu-card-art"></div>';
		$preview = trim(preg_replace('/\s+/', ' ', $article['body']));
	if (function_exists('mb_strlen') && mb_strlen($preview) > 150) { $preview = mb_substr($preview, 0, 150) . '…'; } elseif (strlen($preview) > 150) { $preview = substr($preview, 0, 150) . '…'; }
	$inner = $art . '<div><span class="lbu-label">' . home_escape($article['label']) . '</span><h3>' . home_escape($article['title']) . '</h3><p>' . home_escape($preview) . '</p><span class="lbu-card-hint">Read article</span></div>';
	echo '<button type="button" class="lbu-card lbu-article-trigger" data-title="' . home_escape($article['title']) . '" data-label="' . home_escape($article['label']) . '" data-body="' . home_escape($article['body']) . '" data-image="' . home_escape($imageURL) . '">' . $inner . '</button>';
} ?>
			</div>
            <dialog class="lbu-article-dialog" id="article-dialog" aria-labelledby="article-dialog-title">
                <div class="lbu-article-dialog-head"><span class="lbu-label" id="article-dialog-label"></span><form method="dialog"><button class="lbu-dialog-close" aria-label="Close article">×</button></form></div>
                <img id="article-dialog-image" class="lbu-article-dialog-image" alt="" hidden>
                <h2 id="article-dialog-title"></h2>
                <p id="article-dialog-body"></p>
            </dialog>
            <script>
				(function () {
                    var enrollmentToggle = document.querySelector('.lbu-enrollment-toggle');
                    if (enrollmentToggle) {
                        enrollmentToggle.addEventListener('click', function () {
                            var panel = document.getElementById(enrollmentToggle.getAttribute('aria-controls'));
                            var expanded = enrollmentToggle.getAttribute('aria-expanded') === 'true';
                            enrollmentToggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
                            panel.hidden = expanded;
                            enrollmentToggle.querySelector('.lbu-enrollment-icon').textContent = expanded ? '+' : '−';
                            enrollmentToggle.querySelector('span:first-child').textContent = expanded ? 'View enrollment requests' : 'Hide enrollment requests';
                        });
                    }
                    var dialog = document.getElementById('article-dialog');
                    var image = document.getElementById('article-dialog-image');
                    document.querySelectorAll('.lbu-article-trigger').forEach(function (card) {
                        card.addEventListener('click', function () {
                            document.getElementById('article-dialog-label').textContent = card.dataset.label;
                            document.getElementById('article-dialog-title').textContent = card.dataset.title;
                            document.getElementById('article-dialog-body').textContent = card.dataset.body;
                            image.hidden = !card.dataset.image;
                            if (card.dataset.image) image.src = card.dataset.image;
                            dialog.showModal();
                        });
                    });
				}());
			</script>
<?php } else { ?>
			<script>
				(function () {
					var toggle = document.querySelector('.lbu-enrollment-toggle');
					if (!toggle) return;
					toggle.addEventListener('click', function () {
						var panel = document.getElementById(toggle.getAttribute('aria-controls'));
						var expanded = toggle.getAttribute('aria-expanded') === 'true';
						toggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
						panel.hidden = expanded;
						toggle.querySelector('.lbu-enrollment-icon').textContent = expanded ? '+' : '−';
						toggle.querySelector('span:first-child').textContent = expanded ? 'View enrollment requests' : 'Hide enrollment requests';
					});
				}());
			</script>
<?php } ?>
		</main>
	<footer class="lbu-footer"><p>Long Brown University · Student Enrollment</p></footer>
</body>
</html>
