<?php
// Load status
$status = get_status();

// Set drive name and size
// A newly chosen drive wins; otherwise keep the saved one (Back button)
$status->drive = preg_replace('/[^A-Za-z0-9_\-]/', '', $_REQUEST['drive'] ?? ($status->drive ?? ''));
$status->drive_bytes = get_dev_bytes($status->drive);

// Save status
set_status($status);

// Force refresh the list of disks
$all_disks = get_disks(TRUE);

// Only show parts of the selected target drive
$disks = new stdClass();
$disks->blockdevices = array();
foreach ($all_disks->blockdevices as $e) if ($e->name==$status->drive)
	foreach (($e->children ?? array()) as $c)
		// Skip extended partitions
		if ($c->parttype!=='0x5') $disks->blockdevices[] = $c;
$options = get_part_options($disks, array(), '/.*/');
$options = array(''=>t('(None)')) + $options;

// Load image details
$image = get_image_info();
if (is_string($image)) crash($image, 'restore-3');

// Compare the original and target drive sizes
$size_notes = array();
$size_diff = $status->drive_bytes - $image->drive_bytes;
$size_diff_h = round($size_diff / 1024**3, 1);
if ($size_diff < 0)
	$size_notes[] = array('class' => 'warning', 'icon' => 'exclamation-triangle',
		'msg' => t('Target drive is %s GB smaller than the original. Some partitions may not fit.', $size_diff_h * -1));
if ($size_diff > 1024**2 * 100)
	$size_notes[] = array('class' => 'info', 'icon' => 'info-circle',
		'msg' => t('Target drive is %s GB larger than the original. You can enlarge partitions with GParted after the restore.', $size_diff_h));
if ($size_diff == 0)
	$size_notes[] = array('class' => 'info', 'icon' => 'info-circle', 'msg' => t('Target drive is the same size as the original'));

page_header('restore', 4, t('Choose what to restore'), t('Restoring to %s. Data on the selected target partitions will be overwritten.', '<span class="bt-dev">'.h($status->drive).'</span>'));
?>

<form id="redo_form">
  <ul id="redo_tabs" class="nav nav-tabs" role="tablist">
    <li class="nav-item" role="presentation"><button class="nav-link active" type="button" data-bs-toggle="tab" data-bs-target="#baremetal" role="tab"><?php print t('Full system recovery'); ?> <i class="fas fa-info-circle bt-field-help" data-bs-toggle="tooltip" title="<?php print h(t('Restores the backup image even if the target is blank. The boot record and partition table will be completely overwritten.')); ?>"></i></button></li>
    <li class="nav-item" role="presentation"><button class="nav-link" type="button" data-bs-toggle="tab" data-bs-target="#selective" role="tab"><?php print t('Restore data only'); ?> <i class="fas fa-info-circle bt-field-help" data-bs-toggle="tooltip" title="<?php print h(t('Keeps the current boot record and partition table. Only writes data into the existing partitions you select.')); ?>"></i></button></li>
  </ul>
  <div class="tab-content">

    <div class="tab-pane fade show active" id="baremetal" role="tabpanel">
      <div class="table-responsive">
        <table class="table table-hover" id="bm-parts">
          <thead>
            <tr>
              <th><input class="form-check-input" type="checkbox" aria-label="<?php print t('Select all partitions'); ?>"></th>
              <th><?php print t('Partition'); ?></th><th><?php print t('Details'); ?></th><th><?php print t('Filesystem'); ?></th><th><?php print t('Type'); ?></th><th class="text-end"><?php print t('Size'); ?></th><th></th><th><?php print t('Target'); ?></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($image->parts as $name=>$p) { ?>
            <tr>
              <td><input class="form-check-input" type="checkbox" checked name="baremetal_parts[]" id="baremetal_<?php print h($name); ?>" value="<?php print h($name); ?>"></td>
              <td><label class="bt-dev" for="baremetal_<?php print h($name); ?>"><?php print h($name); ?></label></td>
              <td><?php print h($p->desc); ?></td>
              <td class="text-nowrap"><span class="bt-tag"><?php print h($p->fs ?: 'raw'); ?></span></td>
              <td class="text-nowrap"><?php print h($p->type); ?></td>
              <td class="text-end text-nowrap"><?php print h($p->size); ?></td>
              <td><i class="fas fa-arrow-right text-muted"></i></td>
              <td class="bt-dev"><?php print h(baremetal_target($status->drive, $name)); ?></td>
            </tr>
          <?php } ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="tab-pane fade" id="selective" role="tabpanel">
      <div class="table-responsive">
        <table class="table" id="sel-parts">
          <thead>
            <tr>
              <th><input class="form-check-input" type="checkbox" id="sel-toggle" aria-label="<?php print t('Select all partitions'); ?>"></th>
              <th><?php print t('Partition'); ?></th><th><?php print t('Details'); ?></th><th><?php print t('Filesystem'); ?></th><th class="text-end"><?php print t('Size'); ?></th><th></th><th style="width: 30%"><?php print t('Target'); ?></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($image->parts as $name=>$p) { $n = h($name); ?>
            <tr>
              <td><input class="form-check-input" type="checkbox" name="selective_parts[]" id="selective_<?php print $n; ?>" value="<?php print $n; ?>" onClick="toggleEnabled('<?php print $n; ?>');"></td>
              <td><label class="bt-dev" for="selective_<?php print $n; ?>"><?php print $n; ?></label></td>
              <td><?php print h($p->desc); ?></td>
              <td class="text-nowrap"><span class="bt-tag"><?php print h($p->fs ?: 'raw'); ?></span></td>
              <td class="text-end text-nowrap"><?php print h($p->size); ?></td>
              <td><i class="fas fa-arrow-right text-muted"></i></td>
              <td>
                <select disabled name="map_<?php print $n; ?>" id="map_<?php print $n; ?>" class="form-select form-select-sm" onChange="updateOptions();">
                  <?php foreach ($options as $ov=>$od) print "<option value='".h($ov)."'>".h($od)."</option>"; ?>
                </select>
              </td>
            </tr>
          <?php } ?>
          </tbody>
        </table>
      </div>
      <div class="alert alert-warning d-flex gap-2 mt-3 mb-2">
        <i class="fas fa-exclamation-circle mt-1"></i>
        <div><?php print t('Restoring partitions to different targets will make a restored operating system unbootable. This option is for advanced users only.'); ?></div>
      </div>
    </div>

  </div>

  <?php include('_image-details.inc.php'); ?>

  <div class="bt-actions">
    <button type="button" class="btn btn-outline-secondary" onClick="BT.show('restore-3');"><i class="fas fa-arrow-left me-1"></i> <?php print t('Back'); ?></button>
    <button type="submit" class="btn btn-danger"><i class="fas fa-download me-1"></i> <?php print t('Restore'); ?></button>
  </div>
</form>

<script>
BT.bindPartitions('#bm-parts');

function toggleEnabled(part) {
	var on = $('#selective_' + part).is(':checked');
	$('#map_' + part).prop('disabled', !on);
	if (!on) $('#map_' + part).val('');
	updateOptions();
}

// A target partition can only be chosen once (except "None")
function updateOptions() {
	var $selects = $('#selective select');
	$selects.find('option').prop('disabled', false);
	$selects.each(function () {
		if ($(this).val() !== '') $selects.not(this).find('option[value="' + $(this).val() + '"]').prop('disabled', true);
	});
}

$('#sel-toggle').on('change', function () {
	var on = this.checked;
	$('#sel-parts tbody input[type=checkbox]').prop('checked', on);
	$('#selective select').prop('disabled', !on);
	if (!on) $('#selective select').val('');
	updateOptions();
});

<?php if (isset($status->type)) { ?>
// Restore the previous selection
$('#redo_form tbody input[type=checkbox]').prop('checked', false);
var savedTab = document.querySelector('#redo_tabs [data-bs-target="#<?php print preg_replace('/[^a-z]/', '', $status->type); ?>"]');
if (savedTab) bootstrap.Tab.getOrCreateInstance(savedTab).show();
<?php
if (isset($status->parts)) foreach ($status->parts as $s=>$d) {
	$s = sane_dev($s); $d = sane_dev($d);
	print '$("#baremetal_'.$s.'").prop("checked", true);';
	print '$("#selective_'.$s.'").prop("checked", true);';
	print '$("#map_'.$s.'").prop("disabled", false).val("'.$d.'");';
}
?>
updateOptions();
$('#bm-parts tbody input[type=checkbox]').first().trigger('change');
<?php } ?>

$("#redo_form").on('submit', function (event) {
	event.preventDefault();
	var type = $('#redo_tabs .nav-link.active').attr('data-bs-target');
	var vars = $(type + ' :input').serializeArray();
	vars.push({ name: 'type', value: type });
	BT.submit('/ajax/save-target.php', vars, 'restore-progress', <?php print js(t('Unable to start the restore')); ?>);
});
</script>
