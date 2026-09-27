<?php
// Collapsible details of the selected backup image.
// Expects: $image; optional $size_notes (array of class, icon, msg)
$fields = array(
	t('Name')       => h($image->id),
	t('Version')    => h($image->version),
	t('Created')    => h($image->timestamp),
	t('Notes')      => '<i>'.h($image->notes).'</i>',
	t('Drive size') => t('%1$s GB (%2$s bytes)', round($image->drive_bytes / (1024**3), 2), number_format($image->drive_bytes)),
);
if (is_legacy($image->version)) $fields[t('Version')] .= ' <i class="fas fa-info-circle text-warning" data-bs-toggle="tooltip" title="'.h(t('Backup created by an older version of Redo Rescue. Compatibility is not guaranteed.')).'"></i>';
foreach (($size_notes ?? array()) as $n) $fields[t('Drive size')] .= " <i class='fas fa-".$n['icon']." text-".$n['class']."' data-bs-toggle='tooltip' title='".h($n['msg'])."'></i>";
?>
<details class="bt-card bt-details mt-3">
  <summary><?php print t('Image details'); ?></summary>
  <dl>
    <?php foreach ($fields as $k=>$v) print "<dt>$k</dt><dd>$v</dd>"; ?>
  </dl>
</details>
