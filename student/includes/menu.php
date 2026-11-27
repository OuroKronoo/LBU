<?php $lbuCurrent = isset($lbuActive) ? $lbuActive : basename($_SERVER['PHP_SELF']); ?>
	<ul>
		<li class="nav-item"><a class="nav-link<?php echo $lbuCurrent === 'studentindex.php' ? ' is-active" aria-current="page' : ''; ?>" href="studentindex.php">Home</a></li>
		<li class="nav-item"><a class="nav-link<?php echo $lbuCurrent === 'studentprofile.php' ? ' is-active" aria-current="page' : ''; ?>" href="studentprofile.php">Profile</a></li>
		<li class="nav-item"><a class="nav-link<?php echo $lbuCurrent === 'enroll.php' ? ' is-active" aria-current="page' : ''; ?>" href="enroll.php">Enroll Now</a></li>
		<li class="nav-item"><a class="nav-link<?php echo $lbuCurrent === 'showsubs.php' ? ' is-active" aria-current="page' : ''; ?>" href="showsubs.php">Curriculum</a></li>
		<li class="nav-item"><a class="nav-link" href="includes/logout.php">Log Out</a></li>
	</ul>
