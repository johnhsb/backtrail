<?php
#
# Redo Rescue: Backup and Recovery Made Easy <redorescue.com>
# Copyright (C) 2010-2023 Zebradots Software
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program. If not, see <http://www.gnu.org/licenses/>.
#

define('TMP_DIR', '/tmp/');
define('VER_FILE', __DIR__.'/VERSION');
define('CMD_FILE', '/root/cmd.txt');
define('LOG_FILE', TMP_DIR.'redo.log');
define('DISKS_FILE', TMP_DIR.'disks.json');
define('STATUS_FILE', TMP_DIR.'status.json');
define('VNCPASS_FILE', TMP_DIR.'vncpasswd');
define('MOUNTPOINT', '/mnt/remote');
define('FILE_EXTENSION', 'redo');
define('LANG_COOKIE', 'bt-lang');

//
// Interface languages offered in the language menu (see lang/languages.php)
//
function languages() {
	static $languages = NULL;
	if ($languages === NULL) $languages = include(__DIR__.'/lang/languages.php');
	return $languages;
}

//
// Interface language chosen in the language menu (English by default)
//
function lang() {
	$lang = $_COOKIE[LANG_COOKIE] ?? 'en';
	return array_key_exists($lang, languages()) ? $lang : 'en';
}

//
// Translations for the current language: English text => translated text
//
function lang_strings() {
	static $strings = NULL;
	if ($strings === NULL) {
		$strings = array();
		$file = __DIR__.'/lang/'.lang().'.php';
		if (lang() !== 'en' && is_readable($file)) $strings = include($file);
	}
	return $strings;
}

//
// Translate English interface text; extra arguments fill in %s placeholders
//
function t($text, ...$args) {
	$strings = lang_strings();
	$out = $strings[$text] ?? $text;
	return $args ? vsprintf($out, $args) : $out;
}

//
// Return version number
//
function get_version() {
	return trim(file_get_contents(VER_FILE));
}

//
// Determine tool for the given filesystem
//
function get_fs_tool($fs) {
	$fs = (string) $fs;
	if (preg_match('/btrfs/i', $fs)) return 'btrfs';
	if (preg_match('/exfat/i', $fs)) return 'exfat';
	if (preg_match('/ext/i', $fs)) return 'extfs';
	if (preg_match('/f2fs/i', $fs)) return 'f2fs';
	if (preg_match('/fat/i', $fs)) return 'fat';
	if (preg_match('/hfs/i', $fs)) return 'hfsp';
	if (preg_match('/minix/i', $fs)) return 'minix';
	if (preg_match('/ntfs/i', $fs)) return 'ntfs';
	if (preg_match('/xfs/i', $fs)) return 'xfs';
	// Debian's partclone has no nilfs2/reiserfs tools (reiser4 dropped in trixie)
	return 'dd';
}

//
// Retrieve current status and variables
//
function get_status() {
	$status = json_decode(file_get_contents(STATUS_FILE))
		or die('Unable to read status file');
	return $status;
}

//
// Save current status and variables
//
function set_status($status) {
	file_put_contents(STATUS_FILE, json_encode($status))
		or die('Unable to save status file');
	return TRUE;
}

//
// Fetch disk details (from cache if available)
//
function get_disks($force_refresh=FALSE) {
	if ( (file_exists(DISKS_FILE)) && (!$force_refresh) ) {
		return json_decode(file_get_contents(DISKS_FILE));
	} else {
		$disks = shell_exec('lsblk -JO');
		$list = json_decode($disks) or die("Unable to read device list.");
		$oslist = explode(PHP_EOL, trim(shell_exec('os-prober')));
		foreach ($oslist as $line) {
			$osd = explode(':', $line);
			if ((is_array($osd)) && (sizeof($osd)>2)) {
				$part = str_replace('/dev/', '', $osd[0]);
				$os = $osd[1];
				foreach ($list->blockdevices as &$l) {
					if (property_exists($l, 'children')) {
						//print "Disk ".$l->name." has partitions...\n";
						foreach ($l->children as &$c) {
							if ($c->name==$part) {
								//print "* ".$c->name." has $os installed.\n";
								$c->os = $os;
								$l->os = $os;
							}
						}
					}
				}
			}
		}
		$typelist = explode(PHP_EOL, trim(shell_exec('fdisk -l -o Device,Type')));
		foreach ($typelist as $t) if (preg_match('/^\/dev\//', $t)) {
			list($part, $type) = explode('  ', $t);
			$part = str_replace('/dev/', '', $part);
			if ((strlen($part)>3) && (strlen($type)>3)) {
				foreach ($list->blockdevices as &$l) {
					if (property_exists($l, 'children')) {
						//print "Disk ".$l->name." has partitions...\n";
						foreach ($l->children as &$c) {
							if ($c->name==$part) {
								//print "* ".$c->name." is type $type.\n";
								$c->ptdesc = $type;
							}
						}
					}
				}
			}
		}
		file_put_contents(DISKS_FILE, json_encode($list));
		return $list;
	}
}

//
// Get disk vendor and model (unused)
//
function get_disk_model($dev) {
	return trim(shell_exec("sudo hdparm -I ".sane_dev($dev)." | grep 'Model Number' | perl -p -e 's/^.*:\s+//'"));
}

//
// Get list of disks for dropdown
//
function get_disk_options($disks, $type_filter='/(^disk)/') {
	$options = array();
	foreach ($disks->blockdevices as $d) {
		if (!preg_match($type_filter, $d->type)) continue;
		$d->tran = strtoupper((string) $d->tran);
		if ($d->type=='rom') $d->type = 'CD/DVD';
		$os = NULL; if (property_exists($d, 'os')) $os = $d->os;
		$desc = $d->size;
		$desc .= (empty($d->tran)?"":" $d->tran");
		$desc .= (empty($d->type)?"":" $d->type");
		$model = disk_model($d);
		$desc .= (empty($model)?"":", $model");
		$options[$d->name] = "$d->name: ".$desc.(empty($os)?"":", $os");
	}
	return $options;
}

//
// Get list of partitions for dropdown
//
function get_part_options($disks, $exclude=array(), $fstype_filter='/fat.*|exfat|ext\d|ntfs|btrfs|f2fs/') {
	$options = array();
	foreach ($disks->blockdevices as $d) {
		// First check for children (partitions)
		if (property_exists($d, 'children')) {
			// The disk has children, so check each part
			foreach ($d->children as $c) {
				if (in_array($c->name, $exclude)) continue;
				if (!preg_match($fstype_filter, (string) $c->fstype)) continue;
				$p = array(
					'name'	=> $c->name,
					'vendor'=> $d->vendor,
					'model'	=> $d->model,
					'size'	=> $c->size,
					'type'	=> $c->type,
					'tran'	=> $c->tran,
					'fstype'=> $c->fstype,
					'ptdesc'=> $c->ptdesc ?? '',
					'label'	=> $c->label,
					'os'	=> $c->os ?? '',
				);
				$options[$c->name] = clean_part_desc($p);
			}
		}
		// Then check the device itself for a valid filesystem
		if (in_array($d->name, $exclude)) continue;
		if (!preg_match($fstype_filter, (string) $d->fstype)) continue;
		$p = array(
			'name'	=> $d->name,
			'vendor'=> $d->vendor,
			'model'	=> $d->model,
			'size'	=> $d->size,
			'type'	=> $d->type,
			'tran'	=> $d->tran,
			'fstype'=> $d->fstype,
			'ptdesc'=> (property_exists($d, 'ptdesc')?$d->ptdesc:''),
			'label'	=> (property_exists($d, 'label')?$d->label:''),
			'os'	=> (property_exists($d, 'os')?$d->os:''),
		);
		$options[$d->name] = clean_part_desc($p);
	}
	return $options;
}

//
// Return a well-formatted partition description
//
function clean_part_desc($d=array()) {
	$desc = $d['name'].": ".$d['size'];
	$desc .= (empty($d['tran'])?"":" ".strtoupper($d['tran']));
	$desc .= (empty($d['type'])?"":" ".$d['type']);
	$desc .= (empty($d['fstype'])?"":" (".$d['fstype'].")");
	$desc .= (empty($d['ptdesc'])?"":" ".$d['ptdesc']);
	$d['vendor'] = trim(preg_replace('/\s+/', ' ', (string) $d['vendor']));
	$d['model'] = trim(preg_replace('/\s+/', ' ', (string) $d['model']));
	if ($d['vendor'] !== '') $d['model'] = trim(str_replace($d['vendor'], '', $d['model']));
	$vm = trim($d['vendor'].' '.$d['model']);
	$desc .= (empty($vm)?"":" on $vm");
	$desc .= (empty($d['label'])?"":", ".$d['label']);
	$desc .= (empty($d['os'])?"":", ".$d['os']);
	return $desc;
}

//
// Mount a given drive
//
function mount_drive($vars) {
	shell_exec('mkdir -p '.MOUNTPOINT);
	if (!unmount()) return array('status'=>FALSE, 'error'=>t('Mountpoint busy or unable to be unmounted'));
	$error = t('Failed to mount drive');
	switch ($vars['type']) {
	case 'local':
		$dev = preg_replace('/[^a-z0-9]/', '', $vars['local_part']);
		$cmd = 'mount /dev/'.$dev.' '.MOUNTPOINT.' 2>&1';
		$error = shell_exec($cmd);
		$status = get_status();
		$status->source = $dev;
		set_status($status);
		break;
	case 'nfs':
		$host = preg_replace('/[^a-zA-Z0-9\.\-\_]/', '', $vars['nfs_host']);
		$path = preg_replace('/[^a-zA-Z0-9\.\-\_ \/]/', '', $vars['nfs_share']);
		$cmd = "mount '$host:$path' ".MOUNTPOINT.' 2>&1';
		$error = shell_exec($cmd);
		break;
	case 'cifs':
		$loc = trim(preg_replace('/\\\+|\/+/', '|', $vars['cifs_location']));
		list($host, $path) = explode('|', trim($loc, '|'));
		$host = preg_replace('/[^a-zA-Z0-9\.\-\_]/', '', $host);
		$path = preg_replace('/[^a-zA-Z0-9\.\-\_ ]/', '', $path);
		$domain = preg_replace('/[^a-zA-Z0-9\.\-\_]/', '', $vars['cifs_domain']);
		$user = trim(preg_replace('/[^a-zA-Z0-9\.\-\_]/', '', $vars['cifs_username']));
		$pass = $vars['cifs_password'];
		$cmd = "mount.cifs '//$host/$path' ".MOUNTPOINT." -o ";
		$options = array();
		if (empty($user) && empty($pass)) $options[] = "guest";
		if (!empty($domain)) $options[] = "dom=$domain";
		if (!empty($user)) $options[] = "user=$user";
		if (!empty($pass)) $options[] = "pass='$pass'";
		$cmd .= trim(implode(',', $options), ',');
		$cmd .= ' 2>&1';
		$error = shell_exec($cmd);
		break;
	case 'ssh':
		$host = trim(preg_replace('/[^a-zA-Z0-9\.\-\_]/', '', $vars['ssh_host']));
		$user = trim(preg_replace('/[^a-zA-Z0-9\.\-\_]/', '', $vars['ssh_username']));
		$pass = $vars['ssh_password'];
		$dir = $vars['ssh_folder'];
		if (empty($user) || empty($pass)) return array('status'=>FALSE, 'error'=>t('Missing username or password'));
		$cmd = "sshfs -o StrictHostKeyChecking=no,password_stdin $user@$host:$dir ".MOUNTPOINT;
		$error = open_pipe_command($cmd, $pass);
		break;
	case 'ftp':
		$host = trim(preg_replace('/[^a-zA-Z0-9\.\-\_]/', '', $vars['ftp_host']));
		$user = trim(preg_replace('/[^a-zA-Z0-9\.\-\_]/', '', $vars['ftp_username']));
		$pass = $vars['ftp_password'];
		$cmd = "curlftpfs $host ".MOUNTPOINT;
		if (!empty($user)) {
			$cmd .= " -o 'user=$user";
			if (!empty($pass)) $cmd .= ':'.$pass;
			$cmd .= "'";
		}
		$cmd .= ' 2>&1';
		$error = shell_exec($cmd);
		break;
	default:
		return array('status'=>FALSE, 'error'=>t('Unknown mount type'));
		break;
	}
	// Log command used to mount filesystem
	file_put_contents(LOG_FILE, "Executing: $cmd\n", FILE_APPEND);
	// Confirm drive is mounted and return result in an array
	$m = trim(shell_exec('mount | grep '.MOUNTPOINT));
	if (trim((string) $error) === '') $error = t('Failed to mount drive');
	if (strlen($m)<16) return array('status'=>FALSE, 'error'=>nl2br(h($error)));
	$u = get_usage();
	if (sizeof($u)<7) return array('status'=>FALSE, 'error'=>nl2br(h($error)));
	$u['status'] = TRUE;
	return $u;
}

//
// Get size of block device in bytes
//
function get_dev_bytes($drive) {
	return to_bytes(shell_exec('blockdev --getsize64 /dev/'.sane_dev($drive)));
}

//
// Convert a byte count to a number; it becomes a float when it exceeds
// PHP_INT_MAX (sizes over 2 GiB on 32-bit systems), where intval() clamps
//
function to_bytes($str) {
	$str = trim((string) $str);
	return is_numeric($str) ? $str + 0 : 0;
}

//
// Get usage summary of mounted filesystem
//
function get_usage() {
	$free_bytes = to_bytes(shell_exec('df --block-size=1 --output=avail '.MOUNTPOINT.' | sed 1d'));
	$df = trim(shell_exec('df -HT '.MOUNTPOINT.' | grep '.MOUNTPOINT));
	$df = preg_replace('/ +/', ' ', $df);
	$list = explode(' ', $df);
	if (sizeof($list) > 7) {
		// The mounted device has a space in its name, and output
		// from `df` uses single spaces as delimiters. Nice!
		$d1 = array_shift($list);
		$d2 = array_shift($list);
		array_unshift($list, trim($d1).' '.trim($d2));
	}
	list($dev, $fs, $size, $used, $free, $pct, $mnt) = $list;
	if (preg_match('/\%/', $pct)) return array(
		'dev'   => $dev,
		'fs'    => $fs,
		'size'  => $size,
		'used'  => $used,
		'free'  => $free,
		'free_bytes' => $free_bytes,
		'pct'   => preg_replace('/[^0-9\.]/', '', $pct),
		'mnt'   => $mnt,
	);
	return array();
}

//
// Open directory selector dialog
//
function choose_dir($start=NULL, $message=NULL) {
	if (is_null($message)) $message = t('Select destination folder');
	if (is_null($start)) $start = '/'.trim(MOUNTPOINT, '/').'/';
	$dir = shell_exec('yad --display=:0 --center --maximized --file-selection --directory --filename="'.$start.'" --title='.escapeshellarg($message).' --window-icon=folder --timeout=300 --close-on-unfocus');
	return $dir;
}

//
// Open file selector dialog
//
function choose_file($start=NULL, $message=NULL) {
	if (is_null($message)) $message = t('Select backup file');
	if (is_null($start)) $start = '/'.trim(MOUNTPOINT, '/').'/';
	$file = shell_exec('yad --display=:0 --center --maximized --file-selection --file-filter="*.redo *.backup" --filename="'.$start.'" --title='.escapeshellarg($message).' --window-icon=folder-documents --timeout=300 --close-on-unfocus');
	return $file;
}

//
// Return absolute path under mountpoint with no trailing slash
//
function sane_path($path) {
	// Return absolute path with no trailing slash
	$path = str_replace('\\', '/', trim($path));
	$path = preg_replace('/\.\.+/', '', $path);
	$path = preg_replace('/\/\/+/', '/', $path);
	$path = str_replace(MOUNTPOINT, '', $path);
	$path = trim($path, '/');
	$path = MOUNTPOINT.(empty($path)?"":"/$path");
	return $path;
}

//
// Escape path for shell commands
//
function escape_path($path) {
	// Prepare a path for use as a shell argument
	$path = escapeshellcmd($path);
	$path = str_replace(' ', '\ ', $path);
	return $path;
}

//
// Get IP and try to fetch hostname via rDNS
//
function get_host_details() {
	$cmd = 'hostname -I | awk \'{print $1}\'';
	$ip = trim(shell_exec($cmd));
	$name = gethostbyaddr($ip);
	if ($name==$ip) $name = '';
	return array(
		'ip'	=> $ip,
		'name'	=> $name,
	);
}

//
// Show libnotify notice
//
function system_notice($title=NULL, $message="Welcome!", $icon='dialog-information') {
	system('export DISPLAY=:0; notify-send '.escapeshellarg((string) $title).' '.escapeshellarg($message).' -i '.escapeshellarg($icon));
}

//
// Unmount remote filesystem
//
function unmount($loc=MOUNTPOINT, $force=TRUE) {
	$val = shell_exec('umount '.($force?'--force':'').' '.$loc.' 2>&1');
	if (preg_match('/busy/', (string) $val)) return FALSE;
	return TRUE;
}

//
// Exit application
//
function exit_app() {
	sync_drives();
	unmount();
	killall_ops();
	// Save empty status
	set_status(new stdClass());
	system('killall chromium');
}

//
// Kill all backup/restore operations
//
function killall_ops() {
	system('killall -9 -r partclone*');
	unmount();
}

//
// Execute a piped process we can write data to
//
function open_pipe_command($cmd, $data) {
	$descriptorspec = array(
		0 => array("pipe", "r"), // STDIN: Pipe the child will read from
		1 => array("pipe", "w"), // STDOUT: Pipe the child will write to
		2 => array("pipe", "w"), // STDERR: Pipe the child writes errors to
	);
	$process = proc_open($cmd, $descriptorspec, $pipes);
	if (is_resource($process)) {
		fwrite($pipes[0], $data);
		fclose($pipes[0]);
		//$output = stream_get_contents($pipes[1]);
		fclose($pipes[1]);
		$error = stream_get_contents($pipes[2]);
		fclose($pipes[2]);
		$return_value = proc_close($process);
		//print "* Command returned: $return_value\n";
		//print "* Errors: $error\n";
		if ($return_value!==0) return $error.PHP_EOL.$output;
		return NULL;
	} else {
		return t('Failed to execute command');
	}
}

//
// Scan network for shared drives (CIFS)
//
function search_network_shares() {
	$sharelist = array();
	$sharedata = explode(PHP_EOL, trim(shell_exec("smbtree -N")));
	foreach ($sharedata as $sd) {
		if (preg_match('/^(\w*)$/', $sd)) $domain = trim($sd);
		$matches = array();
	        if (preg_match('/\\\\\\\(\w*)\\\\(\w| )*  */', $sd, $matches)) {
	                list($location, $host, $path) = $matches;
			$sharelist[] = array(
				'domain'	=> trim($domain),
				'location'	=> trim($location),
				'host'		=> $host,
				'path'		=> $path,
			);
	        }
	}
	return $sharelist;
}

//
// Insert javascript snippet to activate tooltips
//
function activate_tooltips() {
	print '<script>BT.initTooltips();</script>';
}

//
// Escape text for HTML output
//
function h($text) {
	return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

//
// Quote text as a JavaScript string literal (safe in <script> and, via h(),
// in HTML attributes)
//
function js($text) {
	return json_encode((string) $text, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
}

//
// Disk vendor and model without repeating the vendor (lsblk pads the vendor)
//
function disk_model($d) {
	$vendor = trim((string) ($d->vendor ?? ''));
	$model = trim((string) ($d->model ?? ''));
	if ($vendor !== '' && stripos($model, $vendor) === 0) $vendor = '';
	return trim("$vendor $model");
}

//
// Convert an lsblk size such as "476.9G" to bytes
//
function size_bytes($size) {
	if (!preg_match('/^([\d.]+)([BKMGTP]?)/i', trim((string) $size), $m)) return 0;
	$exp = strpos('BKMGTP', strtoupper($m[2] ?: 'B'));
	return (float) $m[1] * pow(1024, $exp);
}

//
// Print the operation name, step indicator, title and lead text of a page
//
function page_header($op, $step, $title, $lead='') {
	$steps = array(
		'backup'  => array('Source', 'Partitions', 'Destination', 'Folder', 'Name'),
		'restore' => array('Source', 'Image', 'Target', 'Options'),
		'verify'  => array('Source', 'Image', 'Partitions'),
	);
	$names = array('backup' => 'Back up', 'restore' => 'Restore', 'verify' => 'Verify');
	print '<div class="bt-op">'.t($names[$op]).'</div>';
	if ($step > 0) {
		print '<ol class="bt-steps mt-3" aria-label="'.t('Steps').'">';
		foreach ($steps[$op] as $i => $name) {
			$n = $i + 1;
			$state = ($n < $step) ? 'done' : (($n == $step) ? 'now' : '');
			$dot = ($n < $step) ? '<i class="fas fa-check"></i>' : $n;
			print "<li class='$state'".($n == $step ? " aria-current='step'" : '')."><span class='bt-dot'>$dot</span>".t($name)."</li>";
		}
		print '</ol>';
	}
	print '<h1 class="bt-title">'.$title.'</h1>';
	if (!empty($lead)) print '<p class="bt-lead">'.$lead.'</p>';
}

//
// Show a fatal error message and optional back button
//
function crash($message=NULL, $page=NULL) {
	if (is_null($message)) $message = t('Something went wrong!');
	print "<div class='bt-crash mx-auto mt-4'>";
	print "  <div class='alert alert-danger d-flex gap-2'><i class='fas fa-exclamation-triangle mt-1'></i><div><b>$message</b></div></div>";
	if (!empty($page)) print "  <button class='btn btn-outline-secondary' onClick='BT.show(\"$page\");'><i class='fas fa-arrow-left me-1'></i> ".t('Back')."</button>";
	print "</div>";
	beep('warning');
	die();
}

//
// Show debug information for development
//
function debug() {
	return;
	global $disks, $status;
	print "<div class='well'><pre>";
	print_r($status);
	print "</pre></div>";
}

//
// Play specified beep sound
//
function beep($type='error') {
	switch ($type) {
	case 'done':
		$a = '-l 100 -f 1200 -n -l 100 -f 1800 -n -l 100 -f 2400';
		break;
	case 'warning':
		$a = '-f 250 -r 3 -l 50';
		break;
	case 'error':
	default:
		$a = '-l 1000 -f 100';
	}
	exec('beep '.$a); 
}

//
// Initialize a new backup, return any errors
//
function backup_init() {
	global $status;
	if ($status->id=='') return FALSE;
	$status->progress = array(
		'wait'	=> $status->parts,
		'exec'	=> array(),
		'done'	=> array(),
	);
	$status->start_time = time();
	set_status($status);
	$disks = get_disks();
	$status->bytes_total = 0;
	$status->bytes_done = 0;
	if (count((array) $status->parts)<1) return t('No partitions selected');
	foreach ($status->parts as $p) {
		$part_bytes = get_dev_bytes($p);
		$status->bytes_total += $part_bytes;
		foreach ($disks->blockdevices as $d) if ($d->name==$status->drive) {
			foreach ($d->children as $c) if ($c->name==$p) {
				$da = array();
				if (!empty($c->label)) $da[] = $c->label;
				if (!empty($c->os)) $da[] = $c->os;
				$desc = trim(implode(' ', $da));
				$status->details[$p] = array(
					'bytes'	=> $part_bytes,
					'size'	=> $c->size,
					'type'	=> $c->ptdesc,
					'fs'	=> $c->fstype,
					'desc'	=> $desc,
				);
			}
		}
	}
	$json_data = array(
		'id'		=> $status->id,
		'version'	=> get_version(),
		'timestamp'	=> date('r'),
		'notes'		=> $status->notes,
		'drive_bytes'	=> get_dev_bytes($status->drive),
		'parts'		=> $status->details,
		'mbr_bin'	=> base64_encode(extract_mbr($status->drive)),
		'sfd_bin'	=> base64_encode(extract_sfd($status->drive)),
	);
	$json_file = sane_path($status->dir).'/'.$status->id.'.'.FILE_EXTENSION;
	if (file_exists($json_file)) return t('File already exists (%s)', $json_file);
	$written = file_put_contents($json_file, json_encode($json_data));
	if ($written===FALSE) return t('Unable to write file (%s)', $json_file);
	$status->logline = 0;
	set_status($status);
	shell_exec("truncate -s 0 ".LOG_FILE);
	return NULL;
}

//
// Initialize a new restore, return any errors
//
function restore_init() {
	global $status;
	if (empty($status->type)) return FALSE;
	$status->progress = array(
		'wait'	=> array_keys( (array) $status->parts ),
		'exec'	=> array(),
		'done'	=> array(),
	);
	$status->start_time = time();
	set_status($status);
	$disks = get_disks();
	$status->bytes_total = 0;
	$status->bytes_done = 0;
	if (count((array) $status->parts)<1) return t('No partitions selected');
	foreach ($status->parts as $sp=>$tp) {
		$status->bytes_total += $status->image->parts->$sp->bytes;
	}
	$status->logline = 0;
	set_status($status);
	shell_exec("truncate -s 0 ".LOG_FILE);
	if ($status->type=='baremetal') {
		// Restore MBR and partition table
		$mbr = tempnam(TMP_DIR, 'mbr_');
		file_put_contents($mbr, base64_decode($status->image->mbr_bin));
		$sfd = tempnam(TMP_DIR, 'sfd_');
		file_put_contents($sfd, base64_decode($status->image->sfd_bin));
		if (!unmount($status->drive.'*')) return t('Target partition busy or unable to be unmounted');
		$log = shell_exec("wipefs --all --force /dev/".$status->drive);
		$log .= sleep(0.5);
		$log .= shell_exec("dd if=$mbr of=/dev/".$status->drive." bs=32768 count=1 2>&1");
		$log .= shell_exec("sync");
		$log .= sleep(0.5);
		$log .= shell_exec("sfdisk --force /dev/".$status->drive." < $sfd");
		$log .= shell_exec("sync");
		$log .= sleep(0.5);
		$log .= shell_exec("partprobe /dev/".$status->drive);
		$log .= sleep(0.5);
		@unlink($mbr);
		@unlink($sfd);
		file_put_contents(LOG_FILE, $log, FILE_APPEND);
		if (isset($status->source)) {
			$vars = array('type'=>'local', 'local_part'=>$status->source);
			mount_drive($vars);
		}
	}
	foreach ((array) $status->parts as $src=>$dst) {
		// Make sure the destination partitions are large enough
		/*
		 * $part_check = shell_exec("file /dev/$dst | grep 'block special' | wc -l");
		 * if ($part_check!==1) return "Failed to restore partition table";
		 */
		$dst_size = get_dev_bytes($dst);
		if ($dst_size < $status->image->parts->$src->bytes)
			return t('Original partition %1$s (%2$s bytes) will not fit on destination %3$s (%4$s bytes)', $src, number_format($status->image->parts->$src->bytes), $dst, number_format($dst_size));
	}
	return NULL;
}

//
// Initialize verification of a backup image, return any errors
//
function verify_init() {
	global $status;
	if (empty($status->type)) return FALSE;
	$status->progress = array(
		'wait'	=> array_keys( (array) $status->parts ),
		'exec'	=> array(),
		'done'	=> array(),
	);
	$status->start_time = time();
	set_status($status);
	$disks = get_disks();
	$status->bytes_total = 0;
	$status->bytes_done = 0;
	if (count((array) $status->parts)<1) return t('No partitions selected');
	foreach ($status->parts as $sp=>$tp) {
		$status->bytes_total += $status->image->parts->$sp->bytes;
	}
	$status->logline = 0;
	set_status($status);
	shell_exec("truncate -s 0 ".LOG_FILE);
	return NULL;
}

//
// Retrieve backup image information from .redo file
//
function get_image_info() {
	global $status;
	if ( !property_exists($status, 'file') || empty($status->file) )
		return t('No backup file specified');
	if (!file_exists(sane_path($status->file)))
		return t('Backup file not found (%s)', $status->file);
	if (preg_match('/\.backup$/', $status->file)) {
		// Old .backup format from version 1.0.4 or earlier
		$data = json_decode(get_legacy_image_info());
	} else {
		// New .redo format from version 2.0.0 or later
		$data = json_decode(file_get_contents(sane_path($status->file)));
	}
	if (!is_object($data))
		return t('Unable to interpret backup file: %s', gettype($data).(is_string($data)?' ('.$data.')':''));
	return $data;
}

//
// Retrieve legacy backup image information from old .backup file (v1.0.x)
//
function get_legacy_image_info() {
	global $status;
	$prefix_path = sane_path(preg_replace('/\.backup$/', '', $status->file));
	$drive_size = file_get_contents($prefix_path.'.size');
	if (to_bytes($drive_size)==0)
		return t('Unable to get original drive size of legacy image');
	$mbr_data = file_get_contents($prefix_path.'.mbr');
	if (strlen($mbr_data)<32768)
		return t('Unable to open MBR data of legacy image');
	$sfd_file = file($prefix_path.'.sfdisk');
	// Reformat legacy sfdisk data to ignore extraneous lines that cause errors
	foreach ($sfd_file as $l) if (!preg_match('/^$|^    \-/', $l)) $sfd_data .= $l;
	if (strlen($sfd_data)<128)
		return t('Unable to open sfdisk data of legacy image');
	$timestamp = date('r', filemtime($prefix_path.'.backup'));
	$img_parts = explode("\n", file_get_contents($prefix_path.'.backup'));
	$parts = array();
	foreach ($img_parts as $p) {
		$src_drive = preg_replace('/[0-9]/', '', $p);
		$part_num = preg_replace('/[^0-9]/', '', $p);
		$cmd = 'cat '.$prefix_path.'_part'.$part_num.'.000 ';
		$cmd .= ' | pigz --decompress --stdout';
		$cmd .= ' | partclone.info --logfile '.TMP_DIR.$p.'.info --source -';
		$details = shell_exec($cmd);
		$details = file_get_contents(TMP_DIR.$p.'.info');
		// Get blocksize
		preg_match('/Block size:\s+(\d+) Byte/', $details, $matches);
		$blocksize = $matches[1];
		// Get size
		preg_match('/Device size:\s+(.*) = (\d+) Blocks/', $details, $matches);
		$blocks = $matches[2];
		$size = ($blocks * $blocksize) / 1024**2;
		if ($size > 1000) {
			$size = round($size / 1024, 2).'G';
		} else {
			$size = round($size, 2).'M';
		}
		// Get filesystem
		preg_match('/File system:\s+(.*)/', $details, $matches);
		$fs = $matches[1];
		$parts[$p] = array(
			'bytes'	=> $blocks * $blocksize,
			'size'	=> $size,
			'type'	=> strtoupper($fs),
			'fs'	=> strtolower($fs),
			'desc'	=> '',
		);
	}
	$data = array(
		'id'		=> basename($prefix_path),
		'version'	=> '1.0.x',
		'timestamp'	=> $timestamp,
		'notes'		=> 'Legacy backup image',
		'drive_bytes'	=> to_bytes($drive_size),
		'parts'		=> $parts,
		'mbr_bin'	=> base64_encode($mbr_data),
		'sfd_bin'	=> base64_encode($sfd_data),
	);
	return json_encode($data);
}

//
// Backup the given partition
//
function backup_part($dev) {
	global $status;
	if (process_running())
		return('A process is already running!');
	$dev = sane_dev($dev);
	@unlink(TMP_DIR.$dev.'.log');
	// Prepare command to create a backup
	$fs_tool = get_fs_tool($status->details->$dev->fs);
	$fs_mode = "";
	if ($fs_tool!='dd') $fs_mode = "--clone";
	$cmd = "partclone.$fs_tool $fs_mode --force --UI-fresh 1 --logfile ".TMP_DIR."$dev.log ";
	$cmd .= " --source /dev/$dev --no_block_detail ";
	$cmd .= " | pigz --stdout ";
	$cmd .= " | split --numeric-suffixes=1 --suffix-length=3 --additional-suffix=.img --bytes=4096M - ";
	$cmd .= escape_path(sane_path($status->dir)).'/'.$status->id.'_'.$dev.'_';
	file_put_contents(CMD_FILE, $cmd);
	return $cmd;
}

//
// Restore the given partition to the given target
//
function restore_part($src, $dst=NULL) {
	global $status;
	if ($dst==NULL) $dst = $src;
	if (process_running())
		return('A process is already running!');
	$src = sane_dev($src);
	$dst = sane_dev($dst);
	@unlink(TMP_DIR.$src.'.log');
	// Prepare command to restore a backup
	$image_files = preg_replace('/\.redo$/', '', escape_path(MOUNTPOINT.$status->file)).'_'.$src.'_??*.img';
	if (!unmount('/dev/'.$dst)) return t('Unable to unmount target partition');
	// Use of partclone.restore deprecated; use filesystem-specific binary with "--restore"
	$fs_tool = get_fs_tool($status->image->parts->$src->fs);
	// Is this a legacy backup image? If so, adjust the source file format
	if (is_legacy($status->image->version)) {
		// Handle restoring from an old backup image
		$prefix_path = escape_path(sane_path(preg_replace('/\.backup$/', '', $status->file)));
		$part_num = preg_replace('/[^0-9]/', '', $src);
		$image_files = $prefix_path.'_part'.$part_num.'.???';
	}
	$cmd = "cat $image_files ";
	$cmd .= " | pigz --decompress --stdout ";
	$cmd .= " | partclone.$fs_tool --restore --force --UI-fresh 1 ";
	$cmd .= " --logfile ".TMP_DIR."$src.log --overwrite /dev/$dst --no_block_detail";
	file_put_contents(CMD_FILE, $cmd);
	return $cmd;
}

//
// Verify the integrity of the given image
//
function verify_part($src) {
	global $status;
	if (process_running())
		return('A process is already running!');
	$src = sane_dev($src);
	@unlink(TMP_DIR.$src.'.log');
	// Prepare command to verify a backup
	$image_files = preg_replace('/\.redo$/', '', escape_path(MOUNTPOINT.$status->file)).'_'.$src.'_??*.img';
	// Is this a legacy backup image? If so, adjust the source file format
	if (is_legacy($status->image->version)) {
		// Handle restoring from an old backup image
		$prefix_path = escape_path(sane_path(preg_replace('/\.backup$/', '', $status->file)));
		$part_num = preg_replace('/[^0-9]/', '', $src);
		$image_files = $prefix_path.'_part'.$part_num.'.???';
	}
	$cmd = "cat $image_files ";
	$cmd .= " | pigz --decompress --stdout ";
	$cmd .= " | partclone.chkimg --force --UI-fresh 1 --source - ";
	$cmd .= " --logfile ".TMP_DIR."$src.log --no_block_detail";
	file_put_contents(CMD_FILE, $cmd);
	return $cmd;
}

//
// Determine if the image version is older than this release
//
function is_legacy($ver) {
	if (version_compare($ver, '2.0', '<')) return TRUE;
	return FALSE;
}

//
// Return an array of new lines from the process log
//
function get_log_lines() {
	global $status;
	$data = trim(file_get_contents(LOG_FILE));
	$data = preg_replace('/\r/', "\n", $data);
	$data = preg_replace('/\n\s+\n/', "\n", $data);
	$data = explode("\n", $data);
	$offset = $status->logline;
	$status->logline = sizeof($data);
	set_status($status);
	return array_slice($data, $offset);
}

//
// Abort AJAX processing and return an error to the AJAX call
//
function ajax_abort($error) {
	killall_ops();
	beep('error');
	$return['log_msg'] = $error;
	$return['status'] = FALSE;
	exit(json_encode($return));
}

//
// Check if a backup/restore process is currently running
//
function process_running() {
	$procs = intval(trim(shell_exec('pgrep --count partclone')));
	if ($procs > 0) return TRUE;
	return FALSE;
}

//
// Sync drives to flush all writes
//
function sync_drives() {
	shell_exec('sync');
}

//
// Sanitize a device identifier (e.g. 'sda' or 'sda1')
//
function sane_dev($dev) {
	return preg_replace('/[^a-zA-Z0-9\-\_]/', '', $dev);
}

//
// Fetch sfdisk-formatted partition table data
//
function extract_sfd($dev) {
	$dev = sane_dev($dev);
	$sf_file = TMP_DIR.'sfdata.txt';
	$result = exec('sfdisk --dump /dev/'.$dev.' > '.$sf_file);
	$data = file_get_contents($sf_file);
	@unlink($sf_file);
	return $data;
}

//
// Fetch MBR from drive
//
function extract_mbr($dev) {
	$dev = sane_dev($dev);
	$mbr_file = TMP_DIR.'mbr.dat';
	$result = exec('dd if=/dev/'.$dev.' of='.$mbr_file.' bs=32k count=1');
	$data = file_get_contents($mbr_file);
	@unlink($mbr_file);
	return $data;
}

//
// Get details of legacy backup image
//
function legacy_image_details() {
	$details = shell_exec("cat image.* | pigz --decompress --stdout | partclone.info --source -");
	return $details;
}

?>
