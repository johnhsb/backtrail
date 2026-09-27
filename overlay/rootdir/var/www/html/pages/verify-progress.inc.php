<?php
// Load template and begin making timed AJAX calls
$op = 'verify';
$title = 'Verifying backup image';
$lead = 'Checking the integrity of the selected backup image. Nothing is written to your disks.';
$show_dest = FALSE;
$target = NULL;
include('_progress.inc.php');
?>
<script>
BT.runProgress({
	endpoint: '/ajax/execute-verify.php',
	showDest: false,
	doneTitle: 'Verification complete',
	failTitle: 'Verification failed',
	cancelMessage: 'Canceling stops the check before every partition has been verified.'
});
</script>
