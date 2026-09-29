#!/bin/bash
#
# Back up, wreck and restore a disk with the real web app, on loop devices.
#
#     sudo tools/e2e-loop.sh
#
# Runs the app's PHP code in PHP's built-in web server, and the Redo monitor
# service that runs the commands, then drives the app's ajax endpoints the
# way the browser does: back up two partitions to a second (destination)
# disk, wipe the first disk, restore it, verify the image, and compare file
# checksums before and after. There is no browser or GUI involved.
#
# Needs root, on a Debian machine or a privileged container or VM (loop
# devices and partition nodes), with: php-cli partclone pigz fdisk parted curl
# e2fsprogs util-linux. Only the loop devices made here are touched, but the
# app writes to fixed paths: /tmp/status.json, /tmp/redo.log, /root/cmd.txt
# and /mnt/remote, so don't run this on a machine that is in the middle of a
# real backup.
#
set -u

HERE=$(cd "$(dirname "$0")/.." && pwd)
WEB=$HERE/overlay/rootdir/var/www/html
PORT=${PORT:-8099}
TIMEOUT=${TIMEOUT:-300}
URL=http://127.0.0.1:$PORT

red='\e[1;31m'; grn='\e[1;32m'; off='\e[0m'
fail() { echo -e "${red}FAIL: $*${off}" >&2; exit 1; }
step() { echo -e "\n== $*"; }

[ "$EUID" -eq 0 ] || fail "must be run as root"
missing=()
for tool in php partclone.extfs pigz sfdisk mkfs.ext4 losetup curl partprobe wipefs blkid; do
	command -v "$tool" >/dev/null || missing+=("$tool")
done
[ ${#missing[@]} -eq 0 ] || fail "not installed: ${missing[*]}
Install them with: apt install partclone pigz fdisk parted curl e2fsprogs util-linux php-cli"
[ ! -e /root/cmd.txt ] || fail "/root/cmd.txt exists: a Redo monitor may be running here"
! mountpoint -q /mnt/remote || fail "/mnt/remote is mounted"

WORK=$(mktemp -d /tmp/backtrail-e2e.XXXXXX)
DATA_LOOP=""
DEST_LOOP=""
PIDS=()

cleanup() {
	for pid in "${PIDS[@]}"; do kill "$pid" 2>/dev/null; done
	umount -f /mnt/remote 2>/dev/null
	umount "$WORK"/mnt* 2>/dev/null
	[ -z "$DATA_LOOP" ] || losetup -d "$DATA_LOOP" 2>/dev/null
	[ -z "$DEST_LOOP" ] || losetup -d "$DEST_LOOP" 2>/dev/null
	rm -f /root/cmd.txt /tmp/status.json /tmp/disks.json
	# Keep the work folder only when something failed, for its logs
	if [ "${OK:-}" = 1 ]; then rm -rf "$WORK"; else echo "Logs are in $WORK"; fi
}
trap cleanup EXIT

# Get a request's answer; the ajax endpoints answer with JSON
# The server answers one request at a time; a request that takes longer than
# this (os-prober can be slow) is reported instead of waiting for ever
REQUEST_TIMEOUT=${REQUEST_TIMEOUT:-180}
get() { curl -fsS --max-time "$REQUEST_TIMEOUT" "$URL/$1" || fail "request failed or timed out: $1"; }
post() { local path=$1; shift; curl -fsS --max-time "$REQUEST_TIMEOUT" "$URL/$path" "$@" || fail "request failed or timed out: $path"; }

wait_for_partitions() {
	local loop=$1; shift
	for _ in $(seq 1 20); do
		local ok=1
		for n in "$@"; do [ -b "${loop}p$n" ] || ok=0; done
		[ $ok = 1 ] && return 0
		partprobe "$loop" 2>/dev/null
		sleep 0.5
	done
	fail "partition nodes of $loop did not appear (does this environment provide them?)"
}

# Poll an operation's ajax endpoint until it reports it is done
run_operation() {
	local endpoint=$1 what=$2 waited=0 answer shown="" now
	while :; do
		answer=$(post "ajax/$endpoint") || exit 1
		case $answer in
			*'"done"'*) echo "$what: done"; return 0 ;;
			*'"status":false'*) fail "$what: $answer" ;;
		esac
		# Show the overall progress when it changes
		now=$(echo "$answer" | grep -o '"overall_pct":"\?[0-9.]*' | grep -o '[0-9.]*$')
		if [ -n "$now" ] && [ "$now" != "$shown" ]; then echo "$what: $now%"; shown=$now; fi
		if [ $waited -ge "$TIMEOUT" ]; then
			echo "--- last answer: $answer" >&2
			echo "--- /root/cmd.txt: $(cat /root/cmd.txt 2>&1)" >&2
			echo "--- partclone running: $(pgrep -a partclone || echo no)" >&2
			echo "--- tail of /tmp/redo.log:" >&2; tail -n 15 /tmp/redo.log >&2
			echo "--- $WORK/monitor.err:" >&2; tail -n 15 "$WORK/monitor.err" >&2
			fail "$what: not finished after ${TIMEOUT}s"
		fi
		sleep 2; waited=$((waited + 2))
	done
}

checksums() { (cd "$1" && find . -type f -print0 | sort -z | xargs -0 sha256sum); }

step "Preparing the app and the loop disks"
# The app reads its version from a file that the ISO build writes
cp -r "$WEB" "$WORK/www"
echo 1.0.0 > "$WORK/www/VERSION"
rm -f /tmp/status.json /tmp/disks.json /tmp/redo.log
echo '{}' > /tmp/status.json

truncate -s 512M "$WORK/data.img"
truncate -s 2G "$WORK/dest.img"
DATA_LOOP=$(losetup --find --show --partscan "$WORK/data.img") || fail "losetup"
DEST_LOOP=$(losetup --find --show --partscan "$WORK/dest.img") || fail "losetup"
DATA=${DATA_LOOP#/dev/}
DEST=${DEST_LOOP#/dev/}

echo 'label: dos
start=2048, size=200MiB, type=83
size=200MiB, type=83' | sfdisk -q "$DATA_LOOP" || fail "sfdisk (data)"
echo 'label: dos
start=2048, type=83' | sfdisk -q "$DEST_LOOP" || fail "sfdisk (destination)"
wait_for_partitions "$DATA_LOOP" 1 2
wait_for_partitions "$DEST_LOOP" 1
mkfs.ext4 -q -L bt-one "${DATA_LOOP}p1" || fail "mkfs"
mkfs.ext4 -q -L bt-two "${DATA_LOOP}p2" || fail "mkfs"
mkfs.ext4 -q -L bt-dest "${DEST_LOOP}p1" || fail "mkfs"

for n in 1 2; do
	mkdir -p "$WORK/mnt$n"
	mount "${DATA_LOOP}p$n" "$WORK/mnt$n" || fail "mount"
	dd if=/dev/urandom of="$WORK/mnt$n/random.bin" bs=1M count=$((n * 8)) status=none
	echo "partition $n" > "$WORK/mnt$n/name.txt"
	mkdir -p "$WORK/mnt$n/日本語 folder"
	echo "non-ASCII name" > "$WORK/mnt$n/日本語 folder/ファイル.txt"
done
sync
checksums "$WORK/mnt1" > "$WORK/before1.sum"
checksums "$WORK/mnt2" > "$WORK/before2.sum"
umount "$WORK/mnt1" "$WORK/mnt2"

# Errors are logged, not shown, so they cannot spoil the JSON answers
php -d display_errors=0 -d log_errors=1 -d error_log="$WORK/php-errors.log" \
	-S "127.0.0.1:$PORT" -t "$WORK/www" > "$WORK/server.log" 2>&1 &
PIDS+=($!)
# The service (redo.service) appends the monitor's output to /tmp/redo.log,
# which is where the app reads partclone's progress from
bash "$HERE/overlay/rootdir/root/redo-monitor" >> /tmp/redo.log 2> "$WORK/monitor.err" &
PIDS+=($!)
for _ in $(seq 1 20); do curl -fsS -o /dev/null "$URL/action.php?page=welcome" 2>/dev/null && break; sleep 0.5; done
curl -fsS -o /dev/null "$URL/action.php?page=welcome" || fail "the app did not start (see $WORK/server.log)"

step "Backup of ${DATA}p1 and ${DATA}p2 to ${DEST}p1"
rm -f /tmp/disks.json
echo "listing drives (the app runs lsblk, os-prober and fdisk; this can take a while)"
get "action.php?page=backup-1" >/dev/null
get "action.php?page=backup-2&drive=$DATA" >/dev/null
post "action.php?page=backup-3" -d "parts[]=${DATA}p1" -d "parts[]=${DATA}p2" >/dev/null
echo "mounting the destination"
mount_answer=$(post "ajax/mount-drive.php" -d type=local --data-urlencode "vars=local_part=${DEST}p1") || exit 1
case $mount_answer in *'"status":true'*) ;; *) fail "mounting the destination: $mount_answer" ;; esac
get "action.php?page=backup-5&dir=/" >/dev/null
post "ajax/save-id.php" -d type=backup -d name=e2e -d notes=e2e-test >/dev/null
echo "running the backup"
run_operation execute-backup.php "backup"
[ -s /mnt/remote/e2e.redo ] || fail "no image file was written"
ls -l /mnt/remote

step "Wiping $DATA"
umount -f "${DATA_LOOP}p1" "${DATA_LOOP}p2" 2>/dev/null
wipefs --all --force "$DATA_LOOP" >/dev/null
dd if=/dev/zero of="$DATA_LOOP" bs=1M count=8 status=none
partprobe "$DATA_LOOP" 2>/dev/null
sleep 1
[ ! -b "${DATA_LOOP}p1" ] || echo "note: partition nodes are still present after wiping"

step "Verifying the image"
rm -f /tmp/disks.json
get "action.php?page=verify-1" >/dev/null
post "ajax/save-id.php" -d type=verify -d file=/e2e.redo >/dev/null
get "action.php?page=verify-3&drive=$DATA" >/dev/null
answer=$(post "ajax/save-target.php" -d type=verify -d "verify_parts[]=${DATA}p1" -d "verify_parts[]=${DATA}p2") || exit 1
case $answer in *'"status":true'*) ;; *) fail "verify target: $answer" ;; esac
run_operation execute-verify.php "verify"

step "Restoring the whole drive"
rm -f /tmp/disks.json
get "action.php?page=restore-1" >/dev/null
post "ajax/save-id.php" -d type=restore -d file=/e2e.redo >/dev/null
get "action.php?page=restore-4&drive=$DATA" >/dev/null
answer=$(post "ajax/save-target.php" -d type=baremetal -d "baremetal_parts[]=${DATA}p1" -d "baremetal_parts[]=${DATA}p2") || exit 1
case $answer in *'"status":true'*) ;; *) fail "restore target: $answer" ;; esac
run_operation execute-restore.php "restore"

step "Comparing the restored files"
wait_for_partitions "$DATA_LOOP" 1 2
for n in 1 2; do
	mount -o ro "${DATA_LOOP}p$n" "$WORK/mnt$n" || fail "restored partition $n does not mount"
	checksums "$WORK/mnt$n" > "$WORK/after$n.sum"
	diff -u "$WORK/before$n.sum" "$WORK/after$n.sum" || fail "partition $n differs after the restore"
	umount "$WORK/mnt$n"
	echo "partition $n: identical"
done
for n in 1 2; do
	label=$(blkid -s LABEL -o value "${DATA_LOOP}p$n")
	[ "$label" = "$([ "$n" = 1 ] && echo bt-one || echo bt-two)" ] || fail "partition $n label changed to '$label'"
done

if [ -s "$WORK/php-errors.log" ]; then
	echo "PHP logged errors during the run:"
	cat "$WORK/php-errors.log"
	fail "the app raised PHP errors"
fi

OK=1
echo -e "\n${grn}PASS: backup, verify and restore work on ${DATA} (${DEST} as destination)${off}"
