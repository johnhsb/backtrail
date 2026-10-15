# Restoring a Backtrail backup with Redo Rescue 4.0

This test checks that an image made by Backtrail can be verified and
restored by Redo Rescue 4.0.0, the last Redo Rescue release. Backtrail
continues Redo Rescue's version numbers partly so that Redo Rescue reads its
images as current ones (see [HERITAGE.md](../../HERITAGE.md#version-numbers)),
but the version number is not the only thing that can differ:

* Redo Rescue 4.0 is based on Debian 11 and reads partition images with its
  older `partclone`. Backtrail writes them with the `partclone` of Debian 13
  (64-bit) or Debian 12 (32-bit).
* The image information file (`.redo`, JSON) may hold fields that Redo
  Rescue does not know.

Run this test before each release, and after changes to backup, the image
format or the `partclone` version. It takes about 30 minutes.

## What you need

* QEMU: `apt install qemu-system-x86 qemu-utils` on Debian, or
  `brew install qemu` on macOS (replace `-enable-kvm` with `-accel hvf` in
  the commands below).
* The Backtrail ISO to test, for example `backtrail-5.0.2.iso`, built with
  `sudo ./make` or downloaded from a Build ISO run in GitHub Actions.
* `redorescue-4.0.0.iso` from the
  [Redo Backup project on SourceForge](https://sourceforge.net/projects/redobackup/files/).
* About 10 GB of free space for three test disks:

```bash
mkdir -p compat && cd compat
for d in source backup restore; do qemu-img create -f qcow2 $d.qcow2 8G; done
```

| Disk | Use |
|---|---|
| `source` | The disk that is backed up |
| `backup` | Holds the image and the reference checksums |
| `restore` | Blank disk that Redo Rescue restores onto |

Copy the two ISOs into `compat/`, or give their full paths below.

## Step 1: back up with Backtrail

Boot Backtrail with the `source` and `backup` disks. In the live system they
are `/dev/vda` (source) and `/dev/vdb` (backup).

```bash
qemu-system-x86_64 -enable-kvm -m 2048 -smp 2 -boot d \
    -cdrom backtrail-5.0.2.iso \
    -drive file=source.qcow2,if=virtio \
    -drive file=backup.qcow2,if=virtio \
    -nic user -vga std
```

Open a terminal on the desktop (it runs as root) and create the source disk:
a GPT disk with a FAT32 EFI partition and an ext4 partition with a Korean
label, holding ordinary files, a large random file and a file with a Korean
name. Then format the backup disk and save the reference checksums and
partition table on it.

```bash
parted -s /dev/vda mklabel gpt \
    mkpart ESP fat32 1MiB 257MiB set 1 esp on \
    mkpart data ext4 257MiB 3GiB
mkfs.vfat -F32 -n EFI /dev/vda1
mkfs.ext4 -q -L 데이터 /dev/vda2
mkdir -p /mnt/s1 /mnt/s2 /mnt/b
mount /dev/vda1 /mnt/s1
mount /dev/vda2 /mnt/s2
echo efi > /mnt/s1/test.txt
cp -r /usr/share/doc /mnt/s2/doc
head -c 300M /dev/urandom > /mnt/s2/big.bin
echo 안녕 > /mnt/s2/한글파일.txt

parted -s /dev/vdb mklabel gpt mkpart backup ext4 1MiB 100%
mkfs.ext4 -q -L backup /dev/vdb1
mount /dev/vdb1 /mnt/b
for p in s1 s2; do
    (cd /mnt/$p && find . -type f -print0 | sort -z | xargs -0 sha256sum) > /mnt/b/before-$p.txt
done
sfdisk -d /dev/vda > /mnt/b/sfdisk-before.txt
umount /mnt/s1 /mnt/s2 /mnt/b
sync
```

In the Backtrail app:

1. **Back up** `vda` with both partitions selected, to the `vdb1` partition,
   with the name `compat`.
2. **Verify** the new `compat.redo` image, so that a later failure in Redo
   Rescue is not a bad image.
3. Shut down the virtual machine.

## Step 2: verify and restore with Redo Rescue 4.0

Boot Redo Rescue with the `backup` and the blank `restore` disk only. The
source disk is left out, so nothing can be restored over it by mistake.
This time `/dev/vda` is the backup disk and `/dev/vdb` the restore disk.

```bash
qemu-system-x86_64 -enable-kvm -m 2048 -smp 2 -boot d \
    -cdrom redorescue-4.0.0.iso \
    -drive file=backup.qcow2,if=virtio \
    -drive file=restore.qcow2,if=virtio \
    -nic user -vga std
```

In the Redo Rescue app:

1. **Verify** `compat.redo` on `vda1`. The image details must show the
   Backtrail version (5.0.1 or later) and both partitions with their
   sizes, and the verification must
   finish without errors.
2. **Restore** the same image to `vdb`, restoring all partitions and the
   partition table.

Then compare the restored disk with the reference in a terminal. Each `diff`
must print nothing, and both file system checks must report a clean file
system.

```bash
mkdir -p /mnt/b /mnt/r1 /mnt/r2
mount /dev/vda1 /mnt/b
mount /dev/vdb1 /mnt/r1
mount /dev/vdb2 /mnt/r2
for p in 1 2; do
    (cd /mnt/r$p && find . -type f -print0 | sort -z | xargs -0 sha256sum) \
        | diff /mnt/b/before-s$p.txt - && echo "partition $p: files match"
done
sfdisk -d /dev/vdb | sed 's/vdb/vda/g' | diff /mnt/b/sfdisk-before.txt - \
    && echo "partition table matches"
umount /mnt/r1 /mnt/r2
fsck.vfat -n /dev/vdb1
fsck.ext4 -fn /dev/vdb2
```

## Results

| # | Check | If it fails, look at |
|---|---|---|
| 1 | Redo Rescue opens the image and shows its version and partitions | The `.redo` file (JSON fields) |
| 2 | Verification passes | The `partclone` image format (Debian 13 or 12 against Debian 11) |
| 3 | The restore finishes without errors | Partition names and the `sfdisk` dump |
| 4 | File checksums, the partition table (with UUIDs) and both `fsck` runs match | The restored data |

Record the result in the release pull request or issue: the Backtrail
version and build, which system made the image (64-bit or 32-bit), and the
four checks. If check 2 or 3 fails, the README's and HERITAGE.md's
statements about compatibility with Redo Rescue must be corrected before the
release.

The 32-bit system has an older `partclone` (Debian 12), so an image made
with it is closer to Redo Rescue 4.0's. Test the 64-bit system first; to
test the 32-bit one, add `-cpu qemu32` to the first command.

## Optional: why the version number matters

This shows what Redo Rescue does with an image whose version is below 2.0,
which is what a Backtrail image would have recorded had Backtrail's numbers
started at 1.0.0. Before step 2, boot Backtrail again with the backup disk
and make a copy of the image information marked 1.0.0, pointing at the same
partition images:

```bash
mount /dev/vdb1 /mnt/b && cd /mnt/b
sed 's/"version":"[^"]*"/"version":"1.0.0"/' compat.redo > compat-v1.redo
for f in compat_*.img; do ln -s "$f" "compat-v1${f#compat}"; done
cd / && umount /mnt/b
```

Redo Rescue 4.0 takes `compat-v1.redo` for an old Redo Backup 1.0.x image
and looks for files that do not exist, so its verification fails, while
`compat.redo` passes. Backtrail itself tells old images apart by their
`.backup` format, so it should verify both.
