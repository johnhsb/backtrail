<?php
// Load status
$status = get_status();

// Save the posted directory
if (array_key_exists('dir', $_REQUEST)) $status->dir = $_REQUEST['dir'];

// Make sure the path exists
if (!is_dir(sane_path($status->dir))) crash('Not a valid path: '.h(sane_path($status->dir)), 'backup-4');

// Save status
set_status($status);

$suggested_name = date('Ymd');
if (!empty($status->hostname)) $suggested_name .= '-'.$status->hostname;

page_header('backup', 5, 'Name the backup', 'The name identifies this backup image when you restore it later.');
?>

<form id="redo_form">
  <div class="bt-card">
    <div class="mb-3">
      <label class="form-label fw-medium" for="name">Name <i class="fas fa-info-circle bt-field-help" data-bs-toggle="tooltip" title="Use only letters, numbers, dashes and underscores"></i></label>
      <input class="form-control" id="name" name="name" placeholder="<?php print h($suggested_name); ?>" value="<?php print h($suggested_name); ?>" type="text">
    </div>
    <div>
      <label class="form-label fw-medium" for="notes">Notes <span class="text-muted fw-normal">(optional)</span></label>
      <input class="form-control" id="notes" name="notes" placeholder="What is on this drive, or why you made this backup" value="" type="text">
    </div>
  </div>
  <div class="bt-actions">
    <button type="button" class="btn btn-outline-secondary" onClick="BT.show('backup-4');"><i class="fas fa-arrow-left me-1"></i> Back</button>
    <button type="submit" class="btn btn-primary"><i class="fas fa-upload me-1"></i> Start backup</button>
  </div>
</form>

<script>
$("#redo_form").on('submit', function (event) {
	event.preventDefault();
	BT.submit('/ajax/save-id.php', { type: 'backup', name: $('#name').val(), notes: $('#notes').val() }, 'backup-progress', 'Invalid backup name');
});
</script>
