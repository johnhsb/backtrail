<?php
// Collapsible details of the selected backup image.
// Expects: $image; optional $size_notes (array of class, icon, msg)
$fields = array(
	'Name'       => h($image->id),
	'Version'    => h($image->version),
	'Created'    => h($image->timestamp),
	'Notes'      => '<i>'.h($image->notes).'</i>',
	'Drive size' => round($image->drive_bytes / (1024**3), 2).'G ('.number_format($image->drive_bytes).' bytes)',
);
if (is_legacy($image->version)) $fields['Version'] .= ' <i class="fas fa-info-circle text-warning" data-bs-toggle="tooltip" title="Backup created by a previous version of Redo Rescue; compatibility not guaranteed"></i>';
foreach (($size_notes ?? array()) as $n) $fields['Drive size'] .= " <i class='fas fa-".$n['icon']." text-".$n['class']."' data-bs-toggle='tooltip' title='".$n['msg']."'></i>";
?>
<details class="bt-card bt-details mt-3">
  <summary>Image details</summary>
  <dl>
    <?php foreach ($fields as $k=>$v) print "<dt>$k</dt><dd>$v</dd>"; ?>
  </dl>
</details>
