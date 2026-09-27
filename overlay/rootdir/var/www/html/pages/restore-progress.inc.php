<?php
// Load template, confirm restore, and begin making timed AJAX calls

// Load status
$status = get_status();

$op = 'restore';
$title = t('Restoring backup image');
$lead = t('Writing the saved snapshot to the target drive.');
$show_dest = FALSE;
$target = $status->drive;
include('_progress.inc.php');
?>
<script>
BT.runProgress({
	endpoint: '/ajax/execute-restore.php',
	showDest: false,
	doneTitle: <?php print js(t('Restore complete')); ?>,
	failTitle: <?php print js(t('Restore failed')); ?>,
	cancelMessage: <?php print js(t('Canceling stops the restore partway, and the target partitions will be left incomplete.')); ?>,
	confirm: {
		title: <?php print js(t('Restore now?')); ?>,
		message: '<p class="mb-0">' + <?php print js(t('Existing data on the selected target partitions of %s will be permanently overwritten.', '<b>'.h($status->drive).'</b>')); ?> + '</p>',
		label: <?php print js(t('Restore and overwrite')); ?>,
		backPage: 'restore-4'
	}
});
</script>
