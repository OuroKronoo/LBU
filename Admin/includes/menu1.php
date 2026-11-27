<?php
$lbuCurrent = isset($lbuActive) ? $lbuActive : basename($_SERVER['PHP_SELF']);
$lbuB = isset($lbuBase) ? $lbuBase : '';
$lbuInc = ($lbuB === '') ? 'includes/' : '';
?>
    <ul>
      <li class="nav-item"><a class="nav-link<?php echo $lbuCurrent === 'adminindex.php' ? ' is-active" aria-current="page' : ''; ?>" href="<?php echo $lbuB; ?>adminindex.php">Home</a></li>
      <li class="nav-item"><a class="nav-link<?php echo $lbuCurrent === 'userreg.php' ? ' is-active" aria-current="page' : ''; ?>" href="<?php echo $lbuB; ?>userreg.php">Manage Users</a></li>
      <li class="nav-item"><a class="nav-link<?php echo $lbuCurrent === 'subjectreg.php' ? ' is-active" aria-current="page' : ''; ?>" href="<?php echo $lbuB; ?>subjectreg.php">Manage Subjects</a></li>
      <li class="nav-item"><a class="nav-link<?php echo $lbuCurrent === 'report.php' ? ' is-active" aria-current="page' : ''; ?>" href="<?php echo $lbuInc; ?>report.php">Generate Report</a></li>
      <li class="nav-item"><a class="nav-link" href="<?php echo $lbuInc; ?>logout.php">Log Out</a></li>
    </ul>
