<?php
session_start();
include('logcheck.php');
include('includes/conn.php');

function admin_home_escape($value)
{
	return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Serve an article image (preview for the admin). Images are stored in the database, not as files.
if (isset($_GET['img'])) {
	$imgID = (int)$_GET['img'];
	$q = mysqli_prepare($conn, 'SELECT image, image_type FROM articles_tbl WHERE ID = ? AND image IS NOT NULL');
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

// ----- Campus updates (articles shown on the student home page): add, edit, hide/show, delete -----
if (empty($_SESSION['csrf'])) {
	$_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$articleError = '';
$editWanted = isset($_GET['edit']) ? $_GET['edit'] : null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['article_action'])) {
	$act = $_POST['article_action'];
	$aid = (int)($_POST['id'] ?? 0);
	if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
		$articleError = 'Your session expired. Please try again.';
	} elseif ($act === 'save') {
		$label = trim($_POST['label'] ?? '');
		$title = trim($_POST['title'] ?? '');
		$body = trim($_POST['body'] ?? '');
		$link = '';
		$visible = isset($_POST['visible']) ? 1 : 0;
		// Optional image upload (JPG, PNG, GIF or WebP, up to 800 KB) and "remove image" checkbox.
		$imgData = null;
		$imgMime = '';
		$imgError = '';
		$removeImage = isset($_POST['remove_image']);
		if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
			$f = $_FILES['image'];
			$mimes = array(IMAGETYPE_JPEG => 'image/jpeg', IMAGETYPE_PNG => 'image/png', IMAGETYPE_GIF => 'image/gif', IMAGETYPE_WEBP => 'image/webp');
			$info = ($f['error'] === UPLOAD_ERR_OK && is_uploaded_file($f['tmp_name'])) ? getimagesize($f['tmp_name']) : false;
			if ($f['error'] !== UPLOAD_ERR_OK) {
				$imgError = 'The image could not be uploaded.';
			} elseif ($f['size'] > 800 * 1024) {
				$imgError = 'The image is too large (maximum 800 KB).';
			} elseif (!$info || !isset($mimes[$info[2]])) {
				$imgError = 'Please choose a JPG, PNG, GIF or WebP image.';
			} else {
				$imgData = file_get_contents($f['tmp_name']);
				$imgMime = $mimes[$info[2]];
			}
		}
		if ($title === '' || $body === '') {
			$articleError = 'A title and a short text are required.';
		} elseif (mb_strlen($label) > 60 || mb_strlen($title) > 120 || mb_strlen($body) > 10000) {
			$articleError = 'Label (60), title (120) or text (10,000) is too long.';
		} elseif ($imgError !== '') {
			$articleError = $imgError;
		} else {
			if ($aid > 0) {
				$st = mysqli_prepare($conn, 'UPDATE articles_tbl SET label = ?, title = ?, body = ?, link = ?, visible = ? WHERE ID = ?');
				mysqli_stmt_bind_param($st, 'ssssii', $label, $title, $body, $link, $visible, $aid);
				mysqli_stmt_execute($st);
				mysqli_stmt_close($st);
				$savedID = $aid;
				$doneMsg = 'updated';
			} else {
				$st = mysqli_prepare($conn, 'INSERT INTO articles_tbl (label, title, body, link, visible, sort_order) VALUES (?, ?, ?, ?, ?, (SELECT COALESCE(MAX(a.sort_order), 0) + 1 FROM articles_tbl a))');
				mysqli_stmt_bind_param($st, 'ssssi', $label, $title, $body, $link, $visible);
				mysqli_stmt_execute($st);
				$savedID = mysqli_insert_id($conn);
				mysqli_stmt_close($st);
				$doneMsg = 'added';
			}
			if ($savedID > 0 && $imgData !== null) {
				$st = mysqli_prepare($conn, 'UPDATE articles_tbl SET image = ?, image_type = ? WHERE ID = ?');
				mysqli_stmt_bind_param($st, 'ssi', $imgData, $imgMime, $savedID);
				mysqli_stmt_execute($st);
				mysqli_stmt_close($st);
			} elseif ($savedID > 0 && $removeImage) {
				$st = mysqli_prepare($conn, "UPDATE articles_tbl SET image = NULL, image_type = '' WHERE ID = ?");
				mysqli_stmt_bind_param($st, 'i', $savedID);
				mysqli_stmt_execute($st);
				mysqli_stmt_close($st);
			}
			header('Location: adminindex.php?article=' . $doneMsg . '#articles');
			exit;
		}
		$editWanted = $aid > 0 ? (string)$aid : 'new';
	} elseif ($act === 'toggle' && $aid > 0) {
		$st = mysqli_prepare($conn, 'UPDATE articles_tbl SET visible = 1 - visible WHERE ID = ?');
		mysqli_stmt_bind_param($st, 'i', $aid);
		mysqli_stmt_execute($st);
		mysqli_stmt_close($st);
		header('Location: adminindex.php?article=toggled#articles');
		exit;
	} elseif ($act === 'delete' && $aid > 0) {
		$st = mysqli_prepare($conn, 'DELETE FROM articles_tbl WHERE ID = ?');
		mysqli_stmt_bind_param($st, 'i', $aid);
		mysqli_stmt_execute($st);
		mysqli_stmt_close($st);
		header('Location: adminindex.php?article=deleted#articles');
		exit;
	}
}
$editArticle = null;
if ($editWanted === 'new') {
	$editArticle = array('ID' => 0, 'label' => '', 'title' => '', 'body' => '', 'link' => '', 'visible' => 1, 'has_image' => 0);
} elseif ($editWanted !== null && ctype_digit((string)$editWanted)) {
	$st = mysqli_prepare($conn, 'SELECT ID, label, title, body, link, visible, (image IS NOT NULL) AS has_image FROM articles_tbl WHERE ID = ?');
	$wantedID = (int)$editWanted;
	mysqli_stmt_bind_param($st, 'i', $wantedID);
	mysqli_stmt_execute($st);
	$res = mysqli_stmt_get_result($st);
	$editArticle = $res ? mysqli_fetch_assoc($res) : null;
	mysqli_stmt_close($st);
}
if ($editArticle && $articleError !== '' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	foreach (array('label', 'title', 'body', 'link') as $k) { $editArticle[$k] = $_POST[$k] ?? ''; }
	$editArticle['visible'] = isset($_POST['visible']) ? 1 : 0;
}
$articleList = array();
$listRes = mysqli_query($conn, 'SELECT ID, label, title, body, link, visible, (image IS NOT NULL) AS has_image FROM articles_tbl ORDER BY sort_order, ID');
while ($listRes && ($row = mysqli_fetch_assoc($listRes))) { $articleList[] = $row; }
$articleMsg = array('added' => 'Article added.', 'updated' => 'Article updated.', 'toggled' => 'Article visibility changed.', 'deleted' => 'Article deleted.');

$userCount = 0;
$subjectCount = 0;
$requestCount = 0;
$countResult = mysqli_query($conn, 'SELECT COUNT(*) AS total FROM usertbl');
if ($countResult) { $countRow = mysqli_fetch_assoc($countResult); $userCount = (int)$countRow['total']; }
$countResult = mysqli_query($conn, 'SELECT COUNT(*) AS total FROM subjecttbl');
if ($countResult) { $countRow = mysqli_fetch_assoc($countResult); $subjectCount = (int)$countRow['total']; }
$countResult = mysqli_query($conn, "SELECT COUNT(*) AS total FROM student_subject WHERE approved = '0'");
if ($countResult) { $countRow = mysqli_fetch_assoc($countResult); $requestCount = (int)$countRow['total']; }
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title>Administration | Long Brown University</title>
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
			<h1>Welcome, administrator</h1>
			<p>Review accounts, keep subjects current, and follow enrollment requests.</p>
			
		</div>
	</header>
	<main class="lbu-panel">
		<div class="lbu-stats"><div class="lbu-stat"><span class="lbu-label">Accounts</span><strong><?php echo $userCount; ?></strong></div><div class="lbu-stat"><span class="lbu-label">Subjects</span><strong><?php echo $subjectCount; ?></strong></div><div class="lbu-stat lbu-stat--hl"><span class="lbu-label">Requests awaiting review</span><strong><?php echo $requestCount; ?></strong></div></div>
			<section id="articles" aria-labelledby="admin-articles-heading" style="margin-bottom:28px">
				<div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;margin-bottom:14px">
					<h2 id="admin-articles-heading" style="margin:0">Articles &amp; campus updates</h2>
					<a class="lbu-btn lbu-btn--sm lbu-btn--gold" href="adminindex.php?edit=new#articles">+ Add article</a>
				</div>
				<p class="lbu-muted" style="margin:0 0 14px">These appear as cards under "Campus updates" on the student home page. Hidden articles stay saved but students can't see them.</p>
				<?php if (isset($_GET['article'], $articleMsg[$_GET['article']])) { ?><p class="lbu-alert" role="status"><?php echo admin_home_escape($articleMsg[$_GET['article']]); ?></p><?php } ?>
				<?php if ($articleError !== '') { ?><p class="lbu-alert" role="alert"><?php echo admin_home_escape($articleError); ?></p><?php } ?>
				<?php if ($editArticle) { ?>
				<form method="post" action="adminindex.php#articles" class="lbu-form" enctype="multipart/form-data">
					<h2><?php echo $editArticle['ID'] ? 'Edit article' : 'New article'; ?></h2>
					<input type="hidden" name="article_action" value="save" /><input type="hidden" name="id" value="<?php echo (int)$editArticle['ID']; ?>" /><input type="hidden" name="csrf" value="<?php echo admin_home_escape($_SESSION['csrf']); ?>" />
					<div><label class="lbu-label" for="a_label">Label (small heading)</label><input id="a_label" type="text" name="label" maxlength="60" value="<?php echo admin_home_escape($editArticle['label']); ?>"></div>
					<div class="lbu-wide"><label class="lbu-label" for="a_title">Title</label><input id="a_title" type="text" name="title" maxlength="120" required value="<?php echo admin_home_escape($editArticle['title']); ?>"></div>
					<div style="grid-column:1/-1"><label class="lbu-label" for="a_body">Article text</label><textarea id="a_body" name="body" maxlength="10000" rows="8" required><?php echo admin_home_escape($editArticle['body']); ?></textarea><small class="lbu-muted">Use separate paragraphs to make longer articles easy to read.</small></div>
					<div class="lbu-wide"><label class="lbu-label" for="a_image">Image (JPG, PNG, GIF or WebP, max 800 KB)</label><input id="a_image" type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp"><?php if (!empty($editArticle['has_image'])) { ?><div class="lbu-img-current"><img src="adminindex.php?img=<?php echo (int)$editArticle['ID']; ?>&amp;t=<?php echo time(); ?>" alt="Current article image"><label class="lbu-check-row"><input class="lbu-check" type="checkbox" name="remove_image" value="1"> Remove current image</label></div><?php } ?></div>
			<div class="lbu-wide"><p class="lbu-muted">The article card opens a full reading view. Use the text above to explain the update in detail; no section link is needed.</p></div>
					<div><label class="lbu-check-row"><input class="lbu-check" type="checkbox" name="visible" value="1"<?php echo $editArticle['visible'] ? ' checked' : ''; ?>> Visible to students</label></div>
					<div class="lbu-form-actions"><a class="lbu-btn lbu-btn--ghost" href="adminindex.php#articles">Cancel</a><button class="lbu-btn lbu-btn--gold" type="submit">Save article</button></div>
				</form>
				<?php } ?>
				<div class="lbu-table-wrap"><table class="lbu-table"><thead><tr><th>Image</th><th>Label</th><th>Title</th><th>Text</th><th>Status</th><th>Action</th></tr></thead><tbody>
<?php if (!$articleList) { ?><tr><td colspan="6" class="lbu-muted">No articles yet. Use "Add article" to create one.</td></tr><?php } ?>
<?php foreach ($articleList as $a) { ?>
					<tr><td><?php if ($a['has_image']) { ?><img class="lbu-thumb" src="adminindex.php?img=<?php echo (int)$a['ID']; ?>" alt=""><?php } else { echo '—'; } ?></td><td><?php echo admin_home_escape($a['label'] ?: '—'); ?></td><td><strong><?php echo admin_home_escape($a['title']); ?></strong></td><td><?php echo admin_home_escape($a['body']); ?></td>
					<td><?php echo $a['visible'] ? '<span class="lbu-badge lbu-badge--ok">Visible</span>' : '<span class="lbu-badge">Hidden</span>'; ?></td>
					<td style="white-space:nowrap"><a class="lbu-btn lbu-btn--sm" href="adminindex.php?edit=<?php echo (int)$a['ID']; ?>#articles">Edit</a>
						<form method="post" action="adminindex.php#articles" class="lbu-inline"><input type="hidden" name="article_action" value="toggle" /><input type="hidden" name="id" value="<?php echo (int)$a['ID']; ?>" /><input type="hidden" name="csrf" value="<?php echo admin_home_escape($_SESSION['csrf']); ?>" /><button class="lbu-btn lbu-btn--sm lbu-btn--ghost" type="submit"><?php echo $a['visible'] ? 'Hide' : 'Show'; ?></button></form>
						<form method="post" action="adminindex.php#articles" class="lbu-inline" onsubmit="return confirm('Delete this article permanently?');"><input type="hidden" name="article_action" value="delete" /><input type="hidden" name="id" value="<?php echo (int)$a['ID']; ?>" /><input type="hidden" name="csrf" value="<?php echo admin_home_escape($_SESSION['csrf']); ?>" /><button class="lbu-btn lbu-btn--sm lbu-btn--ghost" type="submit">Delete</button></form></td></tr>
<?php } ?>
				</tbody></table></div>
			</section>
			<section aria-labelledby="admin-actions-heading">
				<h2 id="admin-actions-heading">Quick actions</h2>
				<div class="lbu-actions"><a class="lbu-btn" href="userreg.php">Manage accounts</a><a class="lbu-btn lbu-btn--ghost" href="subjectreg.php">Manage subjects</a><a class="lbu-btn lbu-btn--ghost" href="includes/report.php">Enrollment report</a></div>
			</section>
		</main>
	<footer class="lbu-footer"><p>Long Brown University · Enrollment Administration</p></footer>
</body>
</html>
