<?php
error_reporting(E_ALL ^ E_DEPRECATED);
	//CONNECTING HOST
$conn=mysqli_connect("localhost","root","","online_enroll",3307);
//CONNECTING DATABASE
mysqli_select_db($conn,"online_enroll");

// Campus updates shown on the student home page (table is created on first use).
$articlesCheck = mysqli_query($conn, "SHOW TABLES LIKE 'articles_tbl'");
if ($articlesCheck && mysqli_num_rows($articlesCheck) === 0) {
	mysqli_query($conn, "CREATE TABLE articles_tbl (ID int NOT NULL AUTO_INCREMENT PRIMARY KEY, label varchar(60) NOT NULL, title varchar(120) NOT NULL, body varchar(400) NOT NULL, link varchar(40) NOT NULL DEFAULT '', visible tinyint(1) NOT NULL DEFAULT 1, sort_order int NOT NULL DEFAULT 0, created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
	mysqli_query($conn, "INSERT INTO articles_tbl (label, title, body, link, sort_order) VALUES ('Student guide', 'Plan your next semester', 'Your next semester is a chance to shape the way you learn. Explore the subjects available for your program, compare their schedules, and choose a combination that supports your goals.\n\nBefore you submit, review your selected courses and estimated fees so you know what to expect. Your request will be sent to the university office for review, and you can follow its status from your student home page.', 'enroll.php', 1), ('Academic life', 'Make your curriculum your roadmap', 'Every subject adds a new piece to your academic journey. Use your curriculum as a guide to explore the classes connected to your program, discover topics you are excited to study, and plan the skills you want to build.\n\nSchedules and subject details can help you picture the semester ahead. Take a moment to review the options, ask questions when something is unclear, and make choices that feel manageable alongside your other commitments.', 'showsubs.php', 2), ('Campus community', 'Learning grows through community', 'A university experience is shaped by more than the classes on your schedule. It grows through the people you meet, the ideas you share, and the small moments when classmates help one another move forward.\n\nAs a student at fictional Long Brown University, you are part of a community still being imagined. Keep your profile details current so the university office can support your enrollment, and bring curiosity and kindness to the learning community you help create.', 'studentprofile.php', 3)");
}
$imgCol = mysqli_query($conn, "SHOW COLUMNS FROM articles_tbl LIKE 'image'");
if ($imgCol && mysqli_num_rows($imgCol) === 0) {
	mysqli_query($conn, "ALTER TABLE articles_tbl ADD COLUMN image MEDIUMBLOB NULL, ADD COLUMN image_type varchar(30) NOT NULL DEFAULT ''");
}
$yearCol = mysqli_query($conn, "SHOW COLUMNS FROM subjecttbl LIKE 'year_level'");
if ($yearCol && mysqli_num_rows($yearCol) === 0) { mysqli_query($conn, 'ALTER TABLE subjecttbl ADD COLUMN year_level TINYINT NOT NULL DEFAULT 1'); }
?>
