<?php
// Load status
$status = get_status();

// Set operation type
$status->op = 'backup';

// Clear drive selection
unset($status->drive);

// Save status
set_status($status);

// Force refresh the list of disks
$disks = get_disks(TRUE);

// Get list of disk options
$disk_options = get_disk_options($disks);
if (sizeof($disk_options)==0) crash(t('FATAL ERROR: No disks found!'));

page_header('backup', 1, t('Select the drive to back up'), t('Choose the disk connected to this computer that contains the data you want to save.'));
?>

<form id="redo_form">
  <div class="bt-card">
    <label class="form-label fw-medium" for="drive"><?php print t('Source drive'); ?> <i class="fas fa-info-circle bt-field-help" data-bs-toggle="tooltip" title="<?php print h(t('The disk connected to your computer that contains the information you want to back up')); ?>"></i></label>
    <select id="drive" class="form-select">
      <?php foreach ($disk_options as $ov=>$od) print "<option value='".h($ov)."'>".h($od)."</option>"; ?>
    </select>
  </div>
  <div class="bt-actions">
    <button type="button" class="btn btn-outline-secondary" onClick="BT.show('welcome');"><i class="fas fa-arrow-left me-1"></i> <?php print t('Back'); ?></button>
    <button type="submit" class="btn btn-primary"><?php print t('Next'); ?> <i class="fas fa-arrow-right ms-1"></i></button>
  </div>
</form>

<script>
$("#redo_form").on('submit', function (event) {
	event.preventDefault();
	BT.post('backup-2', { drive: $('#drive').val() });
});
</script>
