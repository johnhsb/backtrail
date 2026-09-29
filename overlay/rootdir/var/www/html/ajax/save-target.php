<?php
require_once('../functions.inc.php');

// Load status
$status = get_status();

// Load image details
$error = '';
$image = get_image_info();
if (is_string($image)) $error = $image;
$status->image = $image;

// Parse request to build src->target map
$status->type = preg_replace('/[^a-z]/', '', $_REQUEST['type']);
$status->parts = array();
if (!empty($error)) $status->type = 'invalid';
switch ($status->type) {
case 'invalid':
	// Report the image error below
	break;
case 'verify':
	foreach ($_REQUEST['verify_parts'] as $part) {
		$part = sane_dev($part);
		// We don't need to specify a target; use the same device ID
		$status->parts[$part] = $part;
	}
	break;
case 'baremetal':
	foreach ($_REQUEST['baremetal_parts'] as $part) {
		$part = sane_dev($part);
		// Target partitions remapped to target drive + part number
		$status->parts[$part] = baremetal_target($status->drive, $part);
	}
	$size_diff = $status->drive_bytes - $status->image->drive_bytes;
	if ($size_diff < 0)
		$error = t('Target drive is %s MB smaller than original', number_format(abs($size_diff / 1024**2)));
	break;
case 'selective':
	foreach ($_REQUEST['selective_parts'] as $part) {
		$src = trim(sane_dev($part));
		$dst = trim(sane_dev($_REQUEST['map_'.$src] ?? ''));
		if (empty($src)) {
			if (empty($error)) $error = t('Invalid partition specified');
			continue;
		}
		if (empty($dst)) {
			if (empty($error)) $error = t('Partition %s selected but no target specified', $src);
			continue;
		}
		$status->parts[$src] = $dst;
		$size_diff = get_dev_bytes($dst) - $status->image->parts->$src->bytes;
		if ($size_diff < 0)
			if (empty($error)) $error = t('Target partition %1$s is %2$s MB smaller than original', $dst, number_format(abs($size_diff / 1024**2)));
	}
	break;
default:
	$error = t('Invalid operation type requested');
	break;
}

// Make sure a partition is selected for restore
if ( sizeof( (array) $status->parts ) == 0 )
	if (empty($error)) $error = t('No partitions selected');

// Stop if there was an error
if (!empty($error)) {
	print json_encode(array(
		'status' => FALSE,
		'error' => $error,
	));
	exit;
}

// Success
set_status($status);
print json_encode(array(
	'status' => TRUE,
));

?>
