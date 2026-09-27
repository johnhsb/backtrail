<?php
// Load status
$status = get_status();

// Set operation type
$status->op = 'restore';

// Save status
unset($status->file);
set_status($status);

// Force refresh the list of disks
$disks = get_disks(TRUE);

// Get partition options
$options = get_part_options($disks, array(), '/iso9660|fat.*|ext\d|btrfs|ntfs/');

page_header('restore', 1, 'Where is the backup image?', 'Choose the drive or network folder that contains the backup you want to restore.');
$local_tip = 'The backup image is located on a drive connected directly to this computer';
$local_empty = 'There are no available local partitions to restore from.';
?>

<form id="redo_form">
  <?php include('_location.inc.php'); ?>
  <div class="bt-actions">
    <button type="button" class="btn btn-outline-secondary" onClick="BT.show('welcome');"><i class="fas fa-arrow-left me-1"></i> Back</button>
    <button type="submit" class="btn btn-primary">Next <i class="fas fa-arrow-right ms-1"></i></button>
  </div>
</form>

<script>
$("#redo_form").on('submit', function (event) {
	event.preventDefault();
	BT.submitLocation(this, 'restore-2', 'Checking source drive...');
});
</script>
