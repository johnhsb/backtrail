<?php
require_once('../functions.inc.php');

$sharelist = search_network_shares();
$found = sizeof($sharelist);
if ($found==0) {
	print "<h5>No shared drives found</h5>";
	print "<p class='mb-0'>Enter the network share details manually.</p>";
	die();
}
?>

<h5>Found <?php print "$found shared drive".($found==1?'':'s'); ?></h5>
<p>Select a network share:</p>
<div class="list-group">
	<?php
	foreach ($sharelist as $s) {
		print "<button type='button' class='list-group-item list-group-item-action bt-share' data-location='".h($s['location'])."' data-domain='".h($s['domain'])."'>".h($s['location'])."</button>";
	}
	?>
</div>
