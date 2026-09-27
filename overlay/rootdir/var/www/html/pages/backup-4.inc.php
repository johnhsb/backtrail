<?php
// Load status
$status = get_status();

// Nothing gets posted here. The drive should have been mounted
// in the previous step.

// Warn if bytes free is lower than this threshold:
define('FREE_SPACE_THRESHOLD', 100000000);

$u = get_usage();

page_header('backup', 4, t('Choose a folder'), t('The selected drive has %s of free space. Choose the folder to save the backup in.', '<b>'.h($u['free']).'</b>'));
?>

<form id="redo_form">
  <div class="bt-card">
    <label class="form-label fw-medium" for="dir"><?php print t('Folder'); ?> <i class="fas fa-info-circle bt-field-help" data-bs-toggle="tooltip" title="<?php print h(t('Folder to save the backup in')); ?>"></i></label>
    <div class="input-group">
      <input class="form-control" id="dir" name="dir" placeholder="/" type="text" value="<?php if (property_exists($status, 'dir')) print h($status->dir); ?>">
      <button class="btn btn-outline-secondary" type="button" onClick="BT.choose('dir', '#dir', <?php print h(js(t('Invalid folder selected'))); ?>, <?php print h(js(t('A valid folder has been selected for you.'))); ?>);"><i class="fas fa-folder-open me-1"></i> <?php print t('Select'); ?></button>
    </div>
  </div>
  <div class="bt-actions">
    <button type="button" class="btn btn-outline-secondary" onClick="BT.show('backup-3');"><i class="fas fa-arrow-left me-1"></i> <?php print t('Back'); ?></button>
    <button type="submit" class="btn btn-primary"><?php print t('Next'); ?> <i class="fas fa-arrow-right ms-1"></i></button>
  </div>
</form>

<script>
<?php if ($u['free_bytes'] < FREE_SPACE_THRESHOLD) { ?>
bootbox.alert({
	title: <?php print js(t('Low space warning')); ?>,
	message: '<p class="mb-0">' + <?php print js(t('There is only %s free on the selected destination drive.', h($u['free']))); ?> + '</p>'
});
<?php } ?>

$("#redo_form").on('submit', function (event) {
	event.preventDefault();
	BT.post('backup-5', { dir: $('#dir').val() });
});
</script>
