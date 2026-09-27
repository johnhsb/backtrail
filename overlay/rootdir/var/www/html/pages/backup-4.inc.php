<?php
// Load status
$status = get_status();

// Nothing gets posted here. The drive should have been mounted
// in the previous step.

// Warn if bytes free is lower than this threshold:
define('FREE_SPACE_THRESHOLD', 100000000);

$u = get_usage();

page_header('backup', 4, 'Choose a folder', 'The selected drive has <b>'.h($u['free']).'</b> of free space. Choose the folder to save the backup in.');
?>

<form id="redo_form">
  <div class="bt-card">
    <label class="form-label fw-medium" for="dir">Folder <i class="fas fa-info-circle bt-field-help" data-bs-toggle="tooltip" title="Folder to save the backup in"></i></label>
    <div class="input-group">
      <input class="form-control" id="dir" name="dir" placeholder="/" type="text" value="<?php if (property_exists($status, 'dir')) print h($status->dir); ?>">
      <button class="btn btn-outline-secondary" type="button" onClick="BT.choose('dir', '#dir', 'Invalid folder selected', 'A valid folder has been selected for you.');"><i class="fas fa-folder-open me-1"></i> Select</button>
    </div>
  </div>
  <div class="bt-actions">
    <button type="button" class="btn btn-outline-secondary" onClick="BT.show('backup-3');"><i class="fas fa-arrow-left me-1"></i> Back</button>
    <button type="submit" class="btn btn-primary">Next <i class="fas fa-arrow-right ms-1"></i></button>
  </div>
</form>

<script>
<?php if ($u['free_bytes'] < FREE_SPACE_THRESHOLD) { ?>
bootbox.alert({
	title: 'Low space warning',
	message: '<p class="mb-0">There is only <?php print h($u['free']); ?> free on the selected destination drive.</p>'
});
<?php } ?>

$("#redo_form").on('submit', function (event) {
	event.preventDefault();
	BT.post('backup-5', { dir: $('#dir').val() });
});
</script>
