<?php
require_once('../functions.inc.php');

$status = get_status();

switch($_REQUEST['type']) {

case 'backup':
	// Parse and save name of backup
	$maxlen = 128;
	$name = preg_replace('/[^a-zA-Z0-9\-\_]/', '', $_REQUEST['name']);
	if ($name=='') {
		print json_encode(array(
			'status' => FALSE,
			'error' => t('Invalid backup name'),
		));
		exit;
	} else {
		$status->id = $name;
		$status->notes = strip_tags($_REQUEST['notes']);
		if (strlen($status->notes) > $maxlen)
			$status->notes = substr($status->notes, 0, $maxlen-3).'...';
	}
	break;

case 'restore':
case 'verify':
	// Save path of image to restore from
	$status->file = $_REQUEST['file'];
	if (!file_exists(sane_path($status->file))) {
		print json_encode(array(
			'status' => FALSE,
			'error' => t('Invalid image file'),
		));
		exit;
	}
	break;

}

set_status($status);
print json_encode(array(
	'status' => TRUE,
));

?>
