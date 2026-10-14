<?php
require_once('../functions.inc.php');

// Start or stop remote assistance (the VNC server), then return the remote
// access part of the app bar
$action = $_POST['action'] ?? '';
if ($action === 'start') {
	$out = shell_exec('systemctl start backtrail-vnc.service 2>&1');
	if (trim(shell_exec('systemctl is-active backtrail-vnc.service')) !== 'active') {
		print json_encode(array('status' => FALSE, 'error' => trim((string) $out)));
		exit;
	}
} elseif ($action === 'stop') {
	shell_exec('systemctl stop backtrail-vnc.service 2>&1');
}
$status = get_status();
print json_encode(array('status' => TRUE, 'html' => remote_html($status->ip ?? '')));
