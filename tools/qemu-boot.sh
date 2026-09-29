#!/bin/bash
#
# Boot a Backtrail ISO in QEMU with test disks, for trying a build by hand.
#
#     tools/qemu-boot.sh [-u] [-i] [-r RAM_MB] [ISO]
#
#   -u   boot as UEFI (needs OVMF; the ISO's Secure Boot files are used
#        without Secure Boot enforcement)
#   -i   run as a 32-bit CPU (qemu32), so the boot menu starts the 32-bit
#        system; without it the 64-bit one starts
#   -r   memory in MB (default 2048)
#
# ISO defaults to backtrail-VERSION.iso in the project folder. Two blank
# disks are created in test-disks/ (kept between runs): "source" to back up
# and "target" to restore onto, so a full backup and restore can be tried
# without touching a real drive. Delete test-disks/ to start over.
#
# The VNC server of the live system is not exposed. Use the QEMU window.
#
set -eu

HERE=$(cd "$(dirname "$0")/.." && pwd)
RAM=2048
UEFI=0
CPU=max

while getopts "uir:h" opt; do
	case $opt in
		u) UEFI=1 ;;
		i) CPU=qemu32 ;;
		r) RAM=$OPTARG ;;
		*) sed -n '3,20p' "$0" | sed 's/^# \{0,1\}//'; exit 1 ;;
	esac
done
shift $((OPTIND - 1))

VER=$(sed -n 's/^VER=//p' "$HERE/make")
ISO=${1:-$HERE/backtrail-$VER.iso}
[ -f "$ISO" ] || { echo "ISO not found: $ISO (build it with: sudo ./make)" >&2; exit 1; }
command -v qemu-system-x86_64 >/dev/null || { echo "qemu-system-x86_64 is not installed" >&2; exit 1; }

DISKS=$HERE/test-disks
mkdir -p "$DISKS"
[ -f "$DISKS/source.qcow2" ] || qemu-img create -q -f qcow2 "$DISKS/source.qcow2" 8G
[ -f "$DISKS/target.qcow2" ] || qemu-img create -q -f qcow2 "$DISKS/target.qcow2" 8G

ARGS=(-m "$RAM" -smp 2 -cpu "$CPU" -boot d
	-drive "file=$ISO,media=cdrom,readonly=on"
	-drive "file=$DISKS/source.qcow2,if=virtio"
	-drive "file=$DISKS/target.qcow2,if=virtio"
	-nic user -display gtk -vga std)
[ -w /dev/kvm ] && ARGS+=(-enable-kvm) || echo "note: /dev/kvm is not available; QEMU will be slow"

if [ $UEFI = 1 ]; then
	FW=""
	for f in /usr/share/OVMF/OVMF_CODE_4M.fd /usr/share/OVMF/OVMF_CODE.fd /usr/share/ovmf/OVMF.fd; do
		[ -f "$f" ] && { FW=$f; break; }
	done
	[ -n "$FW" ] || { echo "OVMF firmware not found (install the ovmf package)" >&2; exit 1; }
	ARGS+=(-drive "if=pflash,format=raw,readonly=on,file=$FW")
fi

echo "Booting $ISO (${CPU}, ${RAM} MB, $([ $UEFI = 1 ] && echo UEFI || echo BIOS))"
exec qemu-system-x86_64 "${ARGS[@]}"
