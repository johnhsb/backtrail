<?php
// Load the template and begin making timed AJAX calls
$op = 'backup';
$title = t('Creating backup image');
$lead = t('Saving a snapshot of the selected partitions to the destination drive.');
$show_dest = TRUE;
$target = NULL;
include('_progress.inc.php');
?>
<script>
BT.runProgress({
	endpoint: '/ajax/execute-backup.php',
	showDest: true,
	doneTitle: <?php print js(t('Backup complete')); ?>,
	failTitle: <?php print js(t('Backup failed')); ?>,
	cancelMessage: <?php print js(t('Canceling stops the backup, and the image will be incomplete.')); ?>
});
</script>
