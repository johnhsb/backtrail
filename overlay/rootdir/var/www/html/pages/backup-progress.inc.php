<?php
// Load the template and begin making timed AJAX calls
$op = 'backup';
$title = 'Creating backup image';
$lead = 'Saving a snapshot of the selected partitions to the destination drive.';
$show_dest = TRUE;
$target = NULL;
include('_progress.inc.php');
?>
<script>
BT.runProgress({
	endpoint: '/ajax/execute-backup.php',
	showDest: true,
	doneTitle: 'Backup complete',
	failTitle: 'Backup failed',
	cancelMessage: 'Canceling stops the backup, and the image will be incomplete.'
});
</script>
