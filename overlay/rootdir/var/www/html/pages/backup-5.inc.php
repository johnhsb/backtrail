<?php
// Load status
$status = get_status();

// Save the posted directory
if (array_key_exists('dir', $_REQUEST)) $status->dir = $_REQUEST['dir'];

// Make sure the path exists
if (!is_dir(sane_path($status->dir))) crash(t('Not a valid path: %s', h(sane_path($status->dir))), 'backup-4');

// Save status
set_status($status);

$suggested_name = date('Ymd');
if (!empty($status->hostname)) $suggested_name .= '-'.$status->hostname;

page_header('backup', 5, t('Name the backup'), t('The name identifies this backup image when you restore it later.'));
?>

<form id="redo_form">
  <div class="bt-card">
    <div class="mb-3">
      <label class="form-label fw-medium" for="name"><?php print t('Name'); ?> <i class="fas fa-info-circle bt-field-help" data-bs-toggle="tooltip" title="<?php print h(t('Use only letters, numbers, dashes and underscores')); ?>"></i></label>
      <input class="form-control" id="name" name="name" placeholder="<?php print h($suggested_name); ?>" value="<?php print h($suggested_name); ?>" type="text">
    </div>
    <div>
      <label class="form-label fw-medium" for="notes"><?php print t('Notes'); ?> <span class="text-muted fw-normal"><?php print t('(optional)'); ?></span></label>
      <input class="form-control" id="notes" name="notes" placeholder="<?php print h(t('What is on this drive, or why you made this backup')); ?>" value="" type="text">
    </div>
  </div>
  <div class="bt-actions">
    <button type="button" class="btn btn-outline-secondary" onClick="BT.show('backup-4');"><i class="fas fa-arrow-left me-1"></i> <?php print t('Back'); ?></button>
    <button type="submit" class="btn btn-primary"><i class="fas fa-upload me-1"></i> <?php print t('Start backup'); ?></button>
  </div>
</form>

<script>
$("#redo_form").on('submit', function (event) {
	event.preventDefault();
	BT.submit('/ajax/save-id.php', { type: 'backup', name: $('#name').val(), notes: $('#notes').val() }, 'backup-progress', <?php print js(t('Invalid backup name')); ?>);
});
</script>
