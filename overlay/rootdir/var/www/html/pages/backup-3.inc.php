<?php
// Load status
$status = get_status();

// Unmount destination drive, if mounted
if (!unmount()) crash('Mountpoint busy or unable to be released', 'backup-2');

// Set partition list
$err = "No partitions selected. Go back and select the parts to save.";
$err_link = "backup-2";

// Check if parts are selected; if so, assign them and then use $_REQUEST to override
if (array_key_exists('parts', $_REQUEST)) {
	$status->parts = array();
	foreach ($_REQUEST['parts'] as $p) {
		if ($p=='false') continue;
		$status->parts[] = preg_replace('/[^A-Za-z0-9_\-]/', '', $p);
	}
}
if (empty($status->parts)) crash($err, $err_link);

// Save status
set_status($status);

// Load cached list of disks
$disks = get_disks();
foreach ($disks->blockdevices as $d) if ($d->name==$status->drive) $disk = $d;
if (!isset($disk)) crash('Unable to read information for selected drive.');

// Get partition options
$options = get_part_options($disks, $status->parts);

page_header('backup', 3, 'Choose where to save the backup', 'Save to a drive connected to this computer or to a shared folder on your network.');
$local_tip = 'Save to a drive connected directly to this computer';
$local_empty = 'There are no available local partitions to save to.';
?>

<form id="redo_form">
  <?php include('_location.inc.php'); ?>
  <div class="bt-actions">
    <button type="button" class="btn btn-outline-secondary" onClick="BT.show('backup-2');"><i class="fas fa-arrow-left me-1"></i> Back</button>
    <button type="submit" class="btn btn-primary">Next <i class="fas fa-arrow-right ms-1"></i></button>
  </div>
</form>

<script>
$("#redo_form").on('submit', function (event) {
	event.preventDefault();
	BT.submitLocation(this, 'backup-4', 'Checking destination drive...');
});
</script>
