<?php
// Load status
$status = get_status();

// Nothing gets posted here. The drive should have been mounted
// in the previous step.

page_header('verify', 2, 'Select the backup image', 'Choose the backup image (.redo) to check.');
?>

<form id="redo_form">
  <div class="bt-card">
    <label class="form-label fw-medium" for="file">Image file <i class="fas fa-info-circle bt-field-help" data-bs-toggle="tooltip" title="Backups made by Backtrail and Redo Rescue (.redo), or by Redo Backup 1.0 (.backup)"></i></label>
    <div class="input-group">
      <input class="form-control" id="file" name="file" placeholder="mybackup.redo" type="text" value="<?php if (property_exists($status, 'file')) print h($status->file); ?>">
      <button class="btn btn-outline-secondary" type="button" onClick="BT.choose('file', '#file', 'Invalid image file', 'Please select a valid backup image.');"><i class="fas fa-folder-open me-1"></i> Select</button>
    </div>
  </div>
  <div class="bt-actions">
    <button type="button" class="btn btn-outline-secondary" onClick="BT.show('verify-1');"><i class="fas fa-arrow-left me-1"></i> Back</button>
    <button type="submit" class="btn btn-primary">Next <i class="fas fa-arrow-right ms-1"></i></button>
  </div>
</form>

<script>
$("#redo_form").on('submit', function (event) {
	event.preventDefault();
	BT.submit('/ajax/save-id.php', { type: 'verify', file: $('#file').val() }, 'verify-3', 'Invalid image file');
});
</script>
