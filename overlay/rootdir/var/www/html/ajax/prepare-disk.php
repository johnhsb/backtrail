<?php
require_once('../functions.inc.php');

// Erase a blank drive and give it one exFAT partition for backups. Only
// drives without partitions or data qualify, never the drive being backed up.
set_time_limit(120);
$status = get_status();
$error = prepare_backup_drive($_POST['drive'] ?? '', $status->drive ?? '');
print json_encode($error === NULL ? array('status' => TRUE) : array('status' => FALSE, 'error' => $error));
