<?php
//
// Unit tests for the web app's helper functions.
//
//     php tests/php/run.php
//
// No test framework is needed. Commands built for mounting are run against
// stub programs, so no root access or real drive is involved. Exits with
// status 1 if any check fails.
//

error_reporting(E_ALL);
require_once(__DIR__.'/../../overlay/rootdir/var/www/html/functions.inc.php');

$failures = 0;
$checks = 0;

function check($name, $expected, $actual) {
	global $failures, $checks;
	$checks++;
	if ($expected === $actual) return;
	$failures++;
	echo "FAIL: $name\n  expected: ".var_export($expected, TRUE)."\n  actual:   ".var_export($actual, TRUE)."\n";
}

// Turn PHP warnings and notices into failures of the running check
$warnings = array();
set_error_handler(function ($no, $msg) use (&$warnings) {
	$warnings[] = $msg;
	return TRUE;
});

// Run $fn and return the PHP warnings it raised
function warnings_of($fn) {
	global $warnings;
	$warnings = array();
	$fn();
	return $warnings;
}

//
// Small helpers
//
check('sane_dev keeps device names', 'sda1', sane_dev('sda1'));
check('sane_dev keeps NVMe names', 'nvme0n1p2', sane_dev('nvme0n1p2'));
check('sane_dev strips shell characters', 'sdaabc', sane_dev('sda;a$(b)`c`'));

check('sane_path roots at the mountpoint', MOUNTPOINT.'/a/b', sane_path('/a/b/'));
check('sane_path drops parent references', MOUNTPOINT.'/a/b', sane_path('/a/../b'));
check('sane_path drops dot runs', MOUNTPOINT.'/etc', sane_path('/.../etc'));
check('sane_path collapses slashes', MOUNTPOINT.'/a/b', sane_path('//a///b'));
check('sane_path accepts backslashes', MOUNTPOINT.'/a/b', sane_path('\\a\\b'));
check('sane_path of nothing is the mountpoint', MOUNTPOINT, sane_path(''));
check('sane_path does not repeat the mountpoint', MOUNTPOINT.'/a', sane_path(MOUNTPOINT.'/a'));

check('baremetal_target: SATA disk', 'sdb2', baremetal_target('sdb', 'sda2'));
check('baremetal_target: NVMe disk', 'nvme1n1p2', baremetal_target('nvme1n1', 'nvme0n1p2'));
check('baremetal_target: eMMC to SATA', 'sda3', baremetal_target('sda', 'mmcblk0p3'));
check('baremetal_target: SATA to eMMC', 'mmcblk1p1', baremetal_target('mmcblk1', 'sda1'));
check('baremetal_target: loop device', 'loop7p10', baremetal_target('loop7', 'loop3p10'));
check('baremetal_target: two-digit partition', 'sda12', baremetal_target('sda', 'sda12'));

$fdisk = "Device       Type\n/dev/sda1    Linux filesystem\n/dev/sda2    EFI System\n\nDevice      Type\n/dev/sdb1 Linux\n/dev/sdb2 W95 FAT32\n/dev/sdb10 Linux swap\n/dev/sdc1 83\n";
check('parse_fdisk_types', array('sda1' => 'Linux filesystem', 'sda2' => 'EFI System', 'sdb1' => 'Linux',
	'sdb2' => 'W95 FAT32', 'sdb10' => 'Linux swap'), parse_fdisk_types($fdisk));
check('parse_fdisk_types ignores a device with no type', array(), parse_fdisk_types("/dev/loop0p1\n/dev/loop0p2 \n"));
check('parse_fdisk_types raises no warning', array(), warnings_of(function () { parse_fdisk_types("/dev/sda1 Linux\n/dev/sda2\nnoise"); }));
check('parse_fdisk_types of nothing', array(), parse_fdisk_types(NULL));

check('escape_path escapes spaces', 'a\\ b', escape_path('a b'));

check('to_bytes reads small numbers', 1024, to_bytes("1024\n"));
check('to_bytes keeps sizes over 2 GiB', 3000000000, to_bytes('3000000000'));
check('to_bytes ignores text', 0, to_bytes('n/a'));

check('is_legacy old .backup image', TRUE, is_legacy('/old/pc.backup'));
check('is_legacy .redo image', FALSE, is_legacy('/backups/pc.redo'));
check('is_legacy nothing', FALSE, is_legacy(NULL));

check('get_fs_tool ext4', 'extfs', get_fs_tool('ext4'));
check('get_fs_tool vfat', 'fat', get_fs_tool('vfat'));
check('get_fs_tool exfat before fat', 'exfat', get_fs_tool('exfat'));
check('get_fs_tool unknown falls back to raw', 'dd', get_fs_tool('reiserfs'));
check('get_fs_tool nothing falls back to raw', 'dd', get_fs_tool(NULL));

check('t returns English text', 'Back up', t('Back up'));
check('t fills placeholders', 'a of b', t('%1$s of %2$s', 'a', 'b'));
check('h escapes markup', '&lt;b&gt;&quot;', h('<b>"'));

//
// Mount commands: run them against stub programs that record what they get
//
$bin = sys_get_temp_dir().'/bt-test-'.getmypid();
mkdir($bin);
$out = "$bin/out";
$canary = "$bin/canary";
foreach (array('mount', 'mount.cifs', 'sshfs', 'curlftpfs') as $stub) {
	// Only sshfs is given input (the password), so only it reads stdin
	$stdin = $stub == 'sshfs' ? 'cat' : 'true';
	file_put_contents("$bin/$stub", "#!/bin/sh\n{ echo \"\$0\"; printf '%s\\n' \"\$@\"; echo \"PASSWD=\$PASSWD\"; $stdin; } > '$out' 2>&1\n");
	chmod("$bin/$stub", 0755);
}
putenv("PATH=$bin:".getenv('PATH'));

// Build the command for $vars, run it, and return what the stub received
function mounted($vars) {
	global $out, $canary;
	@unlink($out);
	$m = build_mount_command($vars);
	if (!empty($m['error'])) return array('error' => $m['error']);
	if ($m['stdin'] !== NULL) open_pipe_command($m['cmd'], $m['stdin']);
	else shell_exec($m['cmd']);
	$lines = file_exists($out) ? explode("\n", rtrim(file_get_contents($out), "\n")) : array();
	array_shift($lines); // the stub's own path
	return array('args' => $lines, 'log' => $m['log'], 'canary' => file_exists($canary), 'dev' => $m['dev']);
}

$evil = "x'; touch $canary; echo '\$(touch $canary)`touch $canary`,a=b";

// Local partition
$r = mounted(array('type' => 'local', 'local_part' => 'sdb1'));
check('local: arguments', array('/dev/sdb1', MOUNTPOINT, 'PASSWD='), $r['args']);
check('local: device', 'sdb1', $r['dev']);
$r = mounted(array('type' => 'local', 'local_part' => 'sdb1; touch '.$canary));
check('local: injection stays out of the command', FALSE, $r['canary']);

// NFS
$r = mounted(array('type' => 'nfs', 'nfs_host' => 'nas.lan', 'nfs_share' => '/export/my backups'));
check('nfs: arguments', array('nas.lan:/export/my backups', MOUNTPOINT, 'PASSWD='), $r['args']);
$r = mounted(array('type' => 'nfs', 'nfs_host' => $evil, 'nfs_share' => $evil));
check('nfs: injection stays out of the command', FALSE, $r['canary']);

// CIFS: the password reaches mount.cifs through PASSWD, whatever it contains
$r = mounted(array('type' => 'cifs', 'cifs_location' => '\\\\nas\\backup', 'cifs_domain' => 'WORK',
	'cifs_username' => 'ann', 'cifs_password' => $evil));
check('cifs: arguments', array('//nas/backup', MOUNTPOINT, '-o', 'dom=WORK,user=ann', "PASSWD=$evil"), $r['args']);
check('cifs: injection stays out of the command', FALSE, $r['canary']);
check('cifs: log has no password', FALSE, strpos($r['log'], 'touch') !== FALSE);
$r = mounted(array('type' => 'cifs', 'cifs_location' => '//nas/backup', 'cifs_domain' => '',
	'cifs_username' => '', 'cifs_password' => ''));
check('cifs: guest access', array('//nas/backup', MOUNTPOINT, '-o', 'guest', 'PASSWD='), $r['args']);
$r = mounted(array('type' => 'cifs', 'cifs_location' => 'nas', 'cifs_domain' => '',
	'cifs_username' => '', 'cifs_password' => ''));
check('cifs: host without a share raises no warning', array(), warnings_of(function () {
	build_mount_command(array('type' => 'cifs', 'cifs_location' => 'nas', 'cifs_domain' => '',
		'cifs_username' => '', 'cifs_password' => ''));
}));

// SSH: the password goes to stdin; the folder is one argument
$r = mounted(array('type' => 'ssh', 'ssh_host' => 'box.lan', 'ssh_username' => 'ann',
	'ssh_password' => 'pw', 'ssh_folder' => '/home/ann/my backups'));
check('ssh: arguments', array('-o', 'StrictHostKeyChecking=no,password_stdin', 'ann@box.lan:/home/ann/my backups',
	MOUNTPOINT, 'PASSWD=', 'pw'), $r['args']);
$r = mounted(array('type' => 'ssh', 'ssh_host' => 'box.lan', 'ssh_username' => 'ann',
	'ssh_password' => 'pw', 'ssh_folder' => $evil));
check('ssh: injection stays out of the command', FALSE, $r['canary']);
$r = mounted(array('type' => 'ssh', 'ssh_host' => 'box.lan', 'ssh_username' => '', 'ssh_password' => 'pw', 'ssh_folder' => '/'));
check('ssh: username is required', 'Missing username or password', $r['error'] ?? NULL);

// FTP
$r = mounted(array('type' => 'ftp', 'ftp_host' => 'ftp.lan', 'ftp_username' => 'ann', 'ftp_password' => $evil));
check('ftp: arguments', array('ftp.lan', MOUNTPOINT, '-o', "user=ann:$evil", 'PASSWD='), $r['args']);
check('ftp: injection stays out of the command', FALSE, $r['canary']);
check('ftp: log has no password', FALSE, strpos($r['log'], 'touch') !== FALSE);
$r = mounted(array('type' => 'ftp', 'ftp_host' => 'ftp.lan', 'ftp_username' => '', 'ftp_password' => ''));
check('ftp: anonymous access', array('ftp.lan', MOUNTPOINT, 'PASSWD='), $r['args']);

check('unknown mount type', 'Unknown mount type', build_mount_command(array('type' => 'x'))['error']);

// Piped commands report a failure without PHP warnings
$result = NULL;
$w = warnings_of(function () use (&$result) {
	$result = open_pipe_command('cat >/dev/null; echo oops >&2; exit 3', 'data');
});
check('open_pipe_command failure raises no warning', array(), $w);
check('open_pipe_command failure returns stderr', 'oops', trim((string) $result));
check('open_pipe_command success returns nothing', NULL, open_pipe_command('cat >/dev/null', 'data'));

// Clean up
array_map('unlink', glob("$bin/*"));
rmdir($bin);

echo ($failures ? "$failures of $checks checks failed\n" : "$checks checks passed\n");
exit($failures ? 1 : 0);
