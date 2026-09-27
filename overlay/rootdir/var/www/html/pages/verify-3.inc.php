<?php
// Load status
$status = get_status();

// Set drive name and size
// A newly chosen drive wins; otherwise keep the saved one (Back button)
$status->drive = preg_replace('/[^A-Za-z0-9_\-]/', '', $_REQUEST['drive'] ?? ($status->drive ?? ''));
$status->drive_bytes = get_dev_bytes($status->drive);

// Save status
set_status($status);

// Load image details
$image = get_image_info();
if (is_string($image)) crash($image, 'verify-2');

page_header('verify', 3, t('Choose partitions to check'), t('Partitions saved in raw mode can be restored but not verified.'));
?>

<form id="redo_form">
  <div class="bt-card">
    <div class="table-responsive">
      <table class="table table-hover" id="parts">
        <thead>
          <tr>
            <th><input class="form-check-input" type="checkbox" aria-label="<?php print t('Select all partitions'); ?>"></th>
            <th><?php print t('Partition'); ?></th>
            <th><?php print t('Details'); ?></th>
            <th><?php print t('Filesystem'); ?></th>
            <th><?php print t('Type'); ?></th>
            <th class="text-end"><?php print t('Size'); ?></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($image->parts as $name=>$p) { $raw = empty($p->fs); ?>
          <tr<?php print $raw ? ' class="text-muted"' : ''; ?>>
            <td><input class="form-check-input" type="checkbox" name="verify_parts[]" id="verify_<?php print h($name); ?>" value="<?php print h($name); ?>" data-bytes="<?php print (float) $p->bytes; ?>" <?php print $raw ? 'disabled' : 'checked'; ?>></td>
            <td><label class="bt-dev" for="verify_<?php print h($name); ?>"><?php print h($name); ?></label></td>
            <td><?php print h($p->desc); ?></td>
            <td class="text-nowrap">
              <?php if ($raw) { ?>
              <span class="bt-tag">raw</span> <i class="fas fa-info-circle text-danger" data-bs-toggle="tooltip" title="<?php print h(t('This partition was cloned in raw mode and can be restored, but only valid filesystems can be verified')); ?>"></i>
              <?php } else { ?>
              <span class="bt-tag"><?php print h($p->fs); ?></span>
              <?php } ?>
            </td>
            <td class="text-nowrap"><?php print h($p->type); ?></td>
            <td class="text-end text-nowrap"><?php print h($p->size); ?></td>
          </tr>
        <?php } ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php include('_image-details.inc.php'); ?>

  <div class="bt-actions">
    <button type="button" class="btn btn-outline-secondary" onClick="BT.show('verify-2');"><i class="fas fa-arrow-left me-1"></i> <?php print t('Back'); ?></button>
    <button type="submit" class="btn btn-primary"><i class="fas fa-check-circle me-1"></i> <?php print t('Start verification'); ?></button>
  </div>
</form>

<script>
<?php if (isset($status->type)) { ?>
// Restore the previous selection
$('#parts tbody input[type=checkbox]').prop('checked', false);
<?php if (isset($status->parts)) foreach ($status->parts as $s=>$d) print '$("#verify_'.sane_dev($s).'").prop("checked", true);'; ?>
<?php } ?>
BT.bindPartitions('#parts');

$("#redo_form").on('submit', function (event) {
	event.preventDefault();
	var vars = $(this).serializeArray();
	vars.push({ name: 'type', value: 'verify' });
	BT.submit('/ajax/save-target.php', vars, 'verify-progress', <?php print js(t('Unable to start verification')); ?>);
});
</script>
