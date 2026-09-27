<?php
// Load status
$status = get_status();

// Set operation type
$status->op = 'verify';

// Save status
unset($status->file);
set_status($status);

// Force refresh the list of disks
$disks = get_disks(TRUE);

// Get partition options
$options = get_part_options($disks, array(), '/iso9660|fat.*|ext\d|btrfs|ntfs/');

page_header('verify', 1, t('Where is the backup image?'), t('Choose the drive or network folder that contains the backup you want to check.'));
$local_tip = t('The backup image is located on a drive connected directly to this computer');
$local_empty = t('There are no available local partitions to read from.');
?>

<form id="redo_form">
  <?php include('_location.inc.php'); ?>
  <div class="bt-actions">
    <button type="button" class="btn btn-outline-secondary" onClick="BT.show('welcome');"><i class="fas fa-arrow-left me-1"></i> <?php print t('Back'); ?></button>
    <button type="submit" class="btn btn-primary"><?php print t('Next'); ?> <i class="fas fa-arrow-right ms-1"></i></button>
  </div>
</form>

<script>
$("#redo_form").on('submit', function (event) {
	event.preventDefault();
	BT.submitLocation(this, 'verify-2', <?php print js(t('Checking source drive...')); ?>);
});
</script>
