<?php
// Load status
$status = get_status();

// Set drive name
// A newly chosen drive wins; otherwise keep the saved one (Back button)
$status->drive = preg_replace('/[^A-Za-z0-9_\-]/', '', $_REQUEST['drive'] ?? ($status->drive ?? ''));

// Save status
set_status($status);

// Load cached list of disks
$disks = get_disks();
foreach ($disks->blockdevices as $d) if ($d->name==$status->drive) $disk = $d;
if (!isset($disk)) crash(t('Unable to read information for selected drive.'));

// Build the partition list (extended partitions are only containers)
$parts = array();
foreach (($disk->children ?? array()) as $p) {
	if ($p->parttype=='0x5') continue;
	$notice = '';
	if (get_fs_tool($p->fstype)=='dd') $notice = ' <i class="fas fa-info-circle bt-field-help" data-bs-toggle="tooltip" title="'.h(t('This filesystem requires imaging the entire partition, rather than simply the saved data on it.')).'"></i>';
	if (substr((string) $p->fstype,0,6)=='crypto') $notice .= ' <i class="fas fa-lock text-success ms-1" data-bs-toggle="tooltip" title="'.h(t('This partition is encrypted.')).'"></i>';
	if ($p->fstype=='swap') $notice = ' <i class="fas fa-info-circle bt-field-help" data-bs-toggle="tooltip" title="'.h(t('In most cases it is not necessary to image a swap partition.')).'"></i>';
	if (isset($status->parts)) {
		// Restore the current setting
		$checked = in_array($p->name, $status->parts);
	} else {
		// Check most partitions by default
		$checked = ($p->fstype!=='swap');
	}
	$desc = trim(implode(' · ', array_filter(array($p->label ?? '', $p->os ?? ''))));
	$parts[] = array('p' => $p, 'notice' => $notice, 'checked' => $checked, 'desc' => $desc, 'bytes' => size_bytes($p->size));
}
$model = disk_model($disk);
// Label only the segments of the partition bar that are wide enough to read
$total_bytes = max(array_sum(array_column($parts, 'bytes')), 1);

page_header('backup', 2, t('Choose partitions to save'), t('The partition table and boot record are always included in the backup.'));
?>

<form id="redo_form">
  <div class="bt-split">
    <div class="bt-card">
      <div class="bt-diskhead">
        <span class="bt-dev"><?php print h($disk->name); ?></span>
        <span><?php print h($model); ?></span>
        <span class="bt-size"><?php print h($disk->size); ?><?php if (!empty($disk->pttype)) print ' · '.h(strtoupper($disk->pttype)); ?></span>
      </div>
      <div class="bt-pbar" aria-hidden="true">
        <?php foreach ($parts as $x) { ?>
        <span data-part="<?php print h($x['p']->name); ?>" style="flex: <?php print max($x['bytes'], 1); ?> 1 0" title="<?php print h($x['p']->name.' · '.$x['p']->size); ?>"><?php if ($x['bytes'] / $total_bytes >= .08) print h($x['p']->name); ?></span>
        <?php } ?>
      </div>
      <div class="table-responsive">
        <table class="table table-hover" id="parts">
          <thead>
            <tr>
              <th><input class="form-check-input" type="checkbox" aria-label="<?php print t('Select all partitions'); ?>"></th>
              <th><?php print t('Partition'); ?></th>
              <th><?php print t('Label'); ?></th>
              <th><?php print t('Filesystem'); ?></th>
              <th><?php print t('Type'); ?></th>
              <th class="text-end"><?php print t('Size'); ?></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($parts as $x) { $p = $x['p']; ?>
            <tr>
              <td><input class="form-check-input" type="checkbox" name="parts[]" id="part_<?php print h($p->name); ?>" value="<?php print h($p->name); ?>" data-bytes="<?php print $x['bytes']; ?>"<?php print $x['checked'] ? ' checked' : ''; ?>></td>
              <td><label class="bt-dev" for="part_<?php print h($p->name); ?>"><?php print h($p->name); ?></label></td>
              <td><?php print h($x['desc']); ?></td>
              <td class="text-nowrap"><span class="bt-tag"><?php print h($p->fstype ?: 'raw'); ?></span><?php print $x['notice']; ?></td>
              <td class="text-nowrap"><?php print h($p->ptdesc ?? ''); ?></td>
              <td class="text-end text-nowrap"><?php print h($p->size); ?></td>
            </tr>
          <?php } ?>
          </tbody>
        </table>
      </div>
    </div>
    <div class="bt-card bt-summary">
      <span class="text-muted"><?php print t('Size of selected partitions'); ?></span>
      <span class="bt-big" id="sel-size">0 B</span>
      <span class="text-muted"><?php print t('Only used space is saved for supported filesystems, so the image is usually much smaller.'); ?></span>
      <div class="bt-row"><span><?php print t('Selected'); ?></span><b><?php print t('%1$s of %2$s', '<span id="sel-count">0</span>', sizeof($parts)); ?></b></div>
      <div class="bt-row"><span><?php print t('Drive size'); ?></span><b><?php print h($disk->size); ?></b></div>
    </div>
  </div>

  <div class="bt-actions">
    <button type="button" class="btn btn-outline-secondary" onClick="BT.show('backup-1');"><i class="fas fa-arrow-left me-1"></i> <?php print t('Back'); ?></button>
    <button type="submit" class="btn btn-primary"><?php print t('Next'); ?> <i class="fas fa-arrow-right ms-1"></i></button>
  </div>
</form>

<script>
BT.bindPartitions('#parts');
$("#redo_form").on('submit', function (event) {
	event.preventDefault();
	var formdata = $(this).serializeArray();
	// Include unchecked boxes so the selection is saved
	formdata = formdata.concat($('#parts tbody input[type=checkbox]:not(:checked)').map(function () {
		return { name: this.name, value: false };
	}).get());
	BT.post('backup-3', formdata);
});
</script>
