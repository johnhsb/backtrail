<?php
// Load status
$status = get_status();

// Force refresh the list of disks
$disks = get_disks(TRUE);

// Get list of disk options
$disk_options = get_disk_options($disks);
if (sizeof($disk_options)==0) crash('FATAL ERROR: No disks found!');

page_header('restore', 3, 'Select the target drive', 'Choose the disk to restore the image to. You can review what will be overwritten on the next step.');
?>

<form id="redo_form">
  <div class="bt-card">
    <label class="form-label fw-medium" for="drive">Target drive <i class="fas fa-info-circle bt-field-help" data-bs-toggle="tooltip" title="The disk connected to your computer that the image will be restored to"></i></label>
    <select id="drive" class="form-select">
      <?php foreach ($disk_options as $ov=>$od) print "<option value='".h($ov)."'>".h($od)."</option>"; ?>
    </select>
  </div>
  <div class="bt-actions">
    <button type="button" class="btn btn-outline-secondary" onClick="BT.show('restore-2');"><i class="fas fa-arrow-left me-1"></i> Back</button>
    <button type="submit" class="btn btn-primary">Next <i class="fas fa-arrow-right ms-1"></i></button>
  </div>
</form>

<script>
$("#redo_form").on('submit', function (event) {
	event.preventDefault();
	BT.post('restore-4', { drive: $('#drive').val() });
});
</script>
