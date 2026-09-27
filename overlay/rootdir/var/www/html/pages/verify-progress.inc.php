<?php
// Load template and begin making timed AJAX calls
$op = 'verify';
$title = t('Verifying backup image');
$lead = t('Checking the integrity of the selected backup image. Nothing is written to your disks.');
$show_dest = FALSE;
$target = NULL;
include('_progress.inc.php');
?>
<script>
BT.runProgress({
	endpoint: '/ajax/execute-verify.php',
	showDest: false,
	doneTitle: <?php print js(t('Verification complete')); ?>,
	failTitle: <?php print js(t('Verification failed')); ?>,
	cancelMessage: <?php print js(t('Canceling stops the check before every partition has been verified.')); ?>
});
</script>
