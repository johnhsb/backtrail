<?php
// Load template, confirm restore, and begin making timed AJAX calls

// Load status
$status = get_status();

$op = 'restore';
$title = 'Restoring backup image';
$lead = 'Writing the saved snapshot to the target drive.';
$show_dest = FALSE;
$target = $status->drive;
include('_progress.inc.php');
?>
<script>
BT.runProgress({
	endpoint: '/ajax/execute-restore.php',
	showDest: false,
	doneTitle: 'Restore complete',
	failTitle: 'Restore failed',
	cancelMessage: 'Canceling stops the restore partway, and the target partitions will be left incomplete.',
	confirm: {
		title: 'Restore now?',
		message: '<p class="mb-0">Existing data on the selected target partitions of <b><?php print h($status->drive); ?></b> will be <b>permanently overwritten</b>.</p>',
		label: 'Restore and overwrite',
		backPage: 'restore-4'
	}
});
</script>
