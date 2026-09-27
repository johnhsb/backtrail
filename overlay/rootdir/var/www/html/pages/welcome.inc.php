<?php
// List the drives connected to this computer (cached after the first load)
$disks = get_disks();
$drives = array();
foreach ($disks->blockdevices as $d) {
	if ($d->type !== 'disk') continue;
	$labels = array();
	if (property_exists($d, 'children')) foreach ($d->children as $c) {
		if (!empty($c->label)) $labels[] = $c->label.(empty($c->fstype) ? '' : " ($c->fstype)");
	}
	$desc = array_filter(array(
		disk_model($d),
		strtoupper($d->tran ?? ''),
		property_exists($d, 'os') ? $d->os : '',
		implode(', ', $labels),
	));
	$drives[] = array('name' => $d->name, 'size' => $d->size, 'desc' => implode(' · ', $desc));
}
?>

<h1 class="bt-title mt-2">What would you like to do?</h1>
<p class="bt-lead">Everything runs from this USB drive. Nothing on your disks changes until you confirm.</p>

<div class="bt-ops">
  <button type="button" class="bt-op-card" onClick="BT.show('backup-1');">
    <span class="bt-ico"><i class="fas fa-upload"></i></span>
    <span class="bt-t">Back up</span>
    <span class="bt-d">Save an image of a drive to a USB disk or a network share.</span>
    <span class="bt-m">Saves used space only · .redo image</span>
  </button>
  <button type="button" class="bt-op-card" onClick="BT.show('verify-1');">
    <span class="bt-ico"><i class="fas fa-check-circle"></i></span>
    <span class="bt-t">Verify</span>
    <span class="bt-d">Check a saved image for errors without writing to any disk.</span>
    <span class="bt-m">Read-only</span>
  </button>
  <button type="button" class="bt-op-card danger" onClick="BT.show('restore-1');">
    <span class="bt-ico"><i class="fas fa-download"></i></span>
    <span class="bt-t">Restore</span>
    <span class="bt-d">Write a saved image back to a drive.</span>
    <span class="bt-m">Overwrites the target drive</span>
  </button>
</div>

<?php if (sizeof($drives) > 0) { ?>
<div class="bt-label">Detected drives</div>
<?php foreach ($drives as $d) { ?>
<div class="bt-drive">
  <span class="bt-dev"><?php print h($d['name']); ?></span>
  <span><?php print h($d['desc']); ?></span>
  <span class="bt-size"><?php print h($d['size']); ?></span>
</div>
<?php } ?>
<?php } ?>
