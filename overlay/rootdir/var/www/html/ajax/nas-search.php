<?php
require_once('../functions.inc.php');

$sharelist = search_network_shares();
$found = sizeof($sharelist);
if ($found==0) {
	print "<h5>".t('No shared drives found')."</h5>";
	print "<p class='mb-0'>".t('Enter the network share details manually.')."</p>";
	die();
}
?>

<h5><?php print ($found==1) ? t('Found 1 shared drive') : t('Found %s shared drives', $found); ?></h5>
<p><?php print t('Select a network share:'); ?></p>
<div class="list-group">
	<?php
	foreach ($sharelist as $s) {
		print "<button type='button' class='list-group-item list-group-item-action bt-share' data-location='".h($s['location'])."' data-domain='".h($s['domain'])."'>".h($s['location'])."</button>";
	}
	?>
</div>
