# Heritage: From Redo Rescue to Backtrail

Backtrail continues [Redo Rescue](https://github.com/redorescue/redorescue),
the backup and recovery live system by Zebradots Software. It starts from Redo
Rescue's last commit, `ec1f4f2` ("Update for PHP8", 2023-10-30), and keeps its
full history. This document describes what Backtrail 1.0 changed from Redo
Rescue and why. For the per-release changelog of both projects, see
[CHANGES.md](CHANGES.md).


## Background

* Redo Rescue (first released as Redo Backup in 2010) made bare-metal backup
  and recovery possible in a few clicks from a live CD or USB stick.
* Its last release is 4.0.0 (2021), based on Debian 11. Development stopped
  in October 2023 while 5.0.0, based on Debian 12 (bookworm) for 64-bit PCs
  only, was still unreleased.
* Debian 13 (trixie) has since been released. Debian 12 has left regular
  security support and entered its LTS period.
* Redo Rescue images are 64-bit only, so older 32-bit PCs, which often need a
  rescue tool the most, cannot run them.
* Building and reviewing the last Redo Rescue script revealed several
  problems: missing host build dependencies, chroot mounts left behind by
  interrupted builds, an integer overflow in 32-bit PHP, and missing icons,
  fonts and locale support once the base moved to trixie.

Backtrail takes up the work so that Redo Rescue's users have a maintained
tool, and so that existing `.redo` backups stay restorable.


## Principles

1. **Compatibility**: backups made with Redo Rescue restore with Backtrail.
   Internal names that scripts and images depend on stay unchanged.
2. **Current base system**: move the 64-bit system to Debian 13 for newer
   hardware support and a longer security support window.
3. **One ISO for 32-bit and 64-bit PCs**: detect the CPU at boot and start
   the matching system automatically.
4. **Reliable builds**: interrupted or failed builds must not leave host
   mounts or broken caches behind.
5. **CJK text display**: show Chinese, Japanese and Korean text correctly on
   the desktop and in the application.
6. **Own identity**: Redo Rescue's logos and graphics are not licensed for
   derived projects, so Backtrail has its own name, artwork and a consistent
   look from the boot menu to the application.


## Changes from Redo Rescue

### Platform and security

| Area | Redo Rescue | Backtrail | Benefit |
|---|---|---|---|
| 64-bit system | Debian 12, Linux 6.1, PHP 8.2 | **Debian 13, Linux 6.12, PHP 8.4** | Newer hardware support, longer security support |
| 32-bit system | Not available | **Debian 12 i386 (686 kernel), PHP 8.2** | Runs on older 32-bit PCs |
| Boot selection | 64-bit only | **Automatic by CPU**: GRUB `cpuid -l` on BIOS; 64-bit on UEFI | One USB stick for all PCs; UEFI Secure Boot kept |
| Manual fallback | — | 32-bit menu entry on 64-bit BIOS machines | Recovery option if the 64-bit system fails |
| Security updates | Packages from the base release only | **`-updates` and `-security` repositories, plus a full upgrade** | The app's web engine (WebKitGTK), kernel and PHP ship with current security fixes |
| App access | nginx listened on every address and the firewall allowed port 80; the app's backend runs as root with no login, so anyone on the network could start a backup or restore | **Served on `127.0.0.1` only**, port 80 closed | Only the person at the machine, or a helper connected through VNC, can use the app |
| VNC password | 4 random lowercase letters | **8 characters** (the VNC maximum), no capitals or look-alike characters | Much harder to guess on a shared network |

Debian 13 no longer provides an i386 kernel, so the 32-bit system uses
Debian 12. The ISO contains two independent live systems (`/live-amd64` and
`/live-i386`), each with its own kernel, initrd and squashfs.

### Backup and restore application

| Issue | Redo Rescue behavior | Backtrail |
|---|---|---|
| 32-bit PHP integer limit | Sizes above 2 GiB were clamped to 2147483647: restores were refused as "will not fit" and progress went negative (-35.9% at 50% in testing) | `to_bytes()` keeps large values as floats; 64-bit behavior is unchanged |
| ReiserFS and NILFS2 | Called `partclone.reiser4` / `partclone.nilfs2`, which do not match ReiserFS or do not exist in Debian (`partclone.reiser4` was also removed in trixie) | Imaged in raw mode, which the application already supports |
| Non-ASCII names | Under the `C` locale, tools such as `lsblk` escape non-ASCII text, so CJK partition labels appeared as `\xed\x95\x9c...` | **`C.UTF-8` locale**: names are shown as-is |
| Starting a backup on PHP 8 | `backup_init()` counted the selected partitions with `get_object_vars()`, but a backup's partition list is an array, so PHP 8 stopped with a `TypeError` before the backup began | Counted as an array for backups, restores and verifications |
| Changing the drive | After going back from the partition or restore-options step, the drive chosen first was kept even when another was selected | A newly chosen drive replaces the saved one |
| Partition table tools on trixie | `sfdisk` and `fdisk` moved to the separate `fdisk` package, which Debian 13 no longer installs as a dependency; 64-bit backups saved no partition table dump, so a restored GPT disk had no backup GPT header, and partition types were blank | `fdisk` is installed explicitly |
| Full restore to `mmcblk`, `loop` and `nbd` drives | Only `nvme` drives got the `p` before the partition number, so images restored to `mmcblk0p1` style drives (eMMC, SD cards) went to `mmcblk01`, which does not exist | The `p` is added whenever the drive's name ends in a digit, as the kernel names partitions |
| Target size check | The drive's size was checked only when the target was chosen; the check of each partition ran after the drive had been wiped, when the partitions first exist | The drive's size is checked again right before anything on it changes |
| Passwords in mount commands | CIFS and FTP passwords went into the command line inside quotes and into the log; a quote in a password broke the command | Values are quoted as one argument each, CIFS passwords go through `PASSWD`, and the log hides them |
| Leftover progress | Starting a second operation without reloading the app resumed the first one's progress and waited forever | The first step of each operation clears the previous selections and progress |

The locale is set for every path that runs commands:

* `/etc/locale.conf` for systemd services (Redo monitor, php-fpm)
* `/etc/default/locale` for the login session (slim → X → app window)
* `env[LANG]` in the php-fpm pool, because workers start with a cleared
  environment
* `/etc/bash.bashrc` for terminal shells

`C.UTF-8` is built into glibc, so no extra package is needed. Program
messages remain in English, so the application's parsing of partclone and
mount output is unaffected. A language-specific locale such as
`zh_CN.UTF-8`, `ja_JP.UTF-8` or `ko_KR.UTF-8` would translate those messages
and break that parsing.

### Build script (`make`)

| Issue | Redo Rescue | Backtrail |
|---|---|---|
| Interrupted build | `proc`, `sys` and `dev/pts` stayed mounted inside the build root | Unmounted automatically on exit or interrupt; `clean` unmounts first and deletes with `--one-file-system` |
| Failed debootstrap | A broken cache archive was created and reused by the next build | Cache is written only after success, via an atomic rename |
| Missing host tools | Without `rsync` and `mkfs.vfat` the build still "succeeded", producing an ISO with no overlay applied and a broken UEFI boot image | `./make` checks every tool and GRUB file it uses before it starts, and installs the missing packages with `apt-get` |
| Build targets | One system | `TARGETS="amd64:trixie i386:bookworm"`; `./make changes amd64` or `i386` updates a single system |
| 32-bit chroot | — | Runs under `setarch i686`, so `uname -m` reports a 32-bit machine |
| Legacy code | Unused Debian 9 isolinux path, and package lists for Debian 9 to 11 | Removed; only trixie and bookworm are built |
| Repeated downloads | Every full build downloaded all packages again (about 700 per system); only the debootstrap base was cached | `cache/apt-BASE-ARCH` is mounted over the chroot's package cache, so rebuilds only fetch changed packages; the image itself still ships without `.deb` files |

### Desktop

| Issue | Redo Rescue / first trixie build | Backtrail |
|---|---|---|
| Icons | Most icons blank on trixie: Adwaita became almost all SVG, and the SVG loader and full-color icons were skipped by `--no-install-recommends` | `librsvg2-common` (both systems) and the Papirus icon theme, whose full-color icons replace `adwaita-icon-theme-legacy`; icon caches are kept so apps start without scanning Papirus's 40,000 icons |
| Cursors | Trixie's Adwaita dropped legacy X11 names, so openbox's app-launch pointer fell back to the old X11 cursor | 12 missing names linked to their Adwaita equivalents (trixie only) |
| Notification daemon | Autostart path hard-coded to `i386-linux-gnu`, so it did not start on 64-bit | Architecture-independent path |
| Wi-Fi | Removed in the unreleased Redo Rescue 5.0.0: no Intel, Realtek, Atheros or Broadcom firmware, and on trixie no `wpasupplicant`, so NetworkManager could not use any wireless adapter | Those four firmware packages plus MediaTek and Ralink firmware (non-free build) and `wpasupplicant` are installed; wired connections remain recommended for backup and restore |
| "Unnamed Window" | SLiM 1.4.1 (trixie) leaves a full-screen window with no name or class after auto-login; Openbox showed it as a black window over the wallpaper and in the taskbar | An Openbox rule keeps windows with no name and no class minimized and out of the taskbar |
| CJK text | No CJK font installed; CJK characters showed as boxes | **`fonts-noto-cjk`** (Chinese, Japanese, Korean) |

### Name, artwork and interface

Redo Rescue's license requires derived projects to replace its logos and
graphics, so the project is named **Backtrail** and every original graphic
was removed.
Internal names (the `.redo` image format, `redo.service`, the `redo` user)
are unchanged, so existing backups and scripts keep working.

| Area | Redo Rescue | Backtrail |
|---|---|---|
| Logo | Redo Rescue logo | Contour-line mark generated from source (`branding/src`); see [branding/README.md](branding/README.md) |
| Boot menu | GRUB theme with Redo artwork and Helvetica bitmap fonts, and a "Choose language" submenu that only offered English | Navy contour background, Backtrail logo, Noto Sans CJK fonts, countdown ring; the language submenu is removed because the web app has its own language menu |
| Boot splash | Plymouth `redo` theme | Plymouth `backtrail` theme in the same colors |
| Desktop | Numix GTK and Openbox theme, Adwaita icons, Lato font | `Backtrail` Openbox theme, matching tint2 panel, GTK Adwaita with Papirus icons, Noto Sans CJK, new wallpaper and app icon |
| Web framework | Bootstrap 3.4, jQuery 1.12, Bootbox 5, plus bootstrap-notify, jquery-validation and animate.css | **Bootstrap 5.3, jQuery 3.7, Bootbox 6**; unused libraries removed |
| Application | Separate page layouts | Step indicator, drive list on the welcome screen, partition map with selected size, shared location, progress and image-detail views, light and dark modes |
| Names | Redo Rescue, `redorescue` hostname, `redorescue-VERSION.iso` | Backtrail, `backtrail` hostname, `backtrail-VERSION.iso` |

**Fonts.** Interface text uses Noto Sans CJK, which covers Latin, Korean,
Japanese and Chinese in one family and is packaged for both Debian 12 and 13.
The Korean face is the default, and `local.conf` gives text tagged `ja` or
`zh` the matching regional face, so Han characters take the right forms.
Desktop settings name the generic `Sans` family and the boot menu uses
bitmap subsets built from the same font. An earlier version used Pretendard
for Latin and Korean; it was dropped because the 32-bit system had to take
it from Debian 13, fontconfig needed an exception for Korean, and its
license reserves its name, so the boot menu subsets had to be renamed.

**Fixes found during the rewrite:**

* Choosing a file that is not a valid image returned two JSON objects, and
  the page did nothing; it now shows the error.
* "Select all" in the selective restore tab also changed the checkboxes in
  the full-recovery tab.
* PHP 8.4 notices for disks and partitions without a vendor, filesystem or
  OS entry, and a fatal error when the partition step was opened without a
  selection.
* The vendor name was repeated in drive descriptions ("WD WD Elements"),
  because lsblk pads it with spaces.
* The "will not fit" restore error printed PHP code instead of the sizes.
* Starting a verification or restore with an invalid backup file reached the
  progress page and failed with a server error; the error is now shown.

**Small screens.** On 1024x600 netbook screens the app has about 536 pixels
of height, so the step buttons stay pinned to the bottom of the window when a
page is taller than that, and spacing tightens below 700 pixels. The desktop
wallpaper is drawn for five screen shapes and the session picks the closest
one, so the logo keeps its place and proportions on any resolution.

**Languages.** The web app can be used in English, Korean, Japanese,
Simplified Chinese, Spanish, German, French and Brazilian Portuguese, chosen
from a menu in the app bar that shows each language's flag and name. Text in
the code stays in English and passes through `t()` (PHP) or `BT.t()`
(JavaScript); each language has one file, `lang/<tag>.php`, that maps the
English text to its translation, and anything missing falls back to English.
The choice is kept in a cookie, so server messages (errors, progress
details) follow it too, and the current step is shown again in the new
language without losing the selections made so far. Adding a language takes
an entry in `lang/languages.php`, a translation file and a flag;
`tools/i18n-check.py` reports missing strings and broken placeholders. The
language cannot be changed while an operation is running, and output from
partclone in the detailed log stays in English.

### Size and speed

The squashfs and initrd are compressed with zstd (default levels) instead of
gzip. Sizes were measured by recompressing the live filesystems and initrds
of a gzip build that already included the CJK fonts:

| Component | gzip | zstd |
|---|---|---|
| 64-bit squashfs | 884 MB | 818 MB (-7.5%) |
| 32-bit squashfs | 686 MB | 628 MB (-8.5%) |
| 64-bit initrd | 146.5 MB | 130.1 MB (-11%) |
| 32-bit initrd | 88.6 MB | 71.2 MB (-20%) |
| **ISO (estimated)** | **1,847 MB** | **about 1,690 MB (-158 MB)** |

Decompressing the full 64-bit filesystem on one CPU core took 2.6 s with
zstd versus 5.2 s with gzip, which speeds up booting and application
start-up, especially on older CPUs. xz would be smaller but decompressed
about six times slower than zstd, so it was not used.

The 64-bit initrd shrinks less because about 50 MB of it is stored
uncompressed: CPU microcode (about 14 MB), which the kernel requires that
way, and kernel modules, which are already xz-compressed.

### Image size

Adding the language, font, icon and wireless changes brought the ISO to
1,934 MB. Every installed package and area was measured by compressing it
the way the build does (zstd squashfs), and packages that the build did not
ask for were traced to what pulled them in. Changes made:

| Change | 64-bit | 32-bit |
|---|---|---|
| App window on WebKitGTK instead of Chromium (Chromium 148 MB out, WebKit 71 MB in) | -77 MB | -66 MB |
| `lxpolkit` as the polkit agent: trixie picked `ukui-polkit`, which brought 78 packages (Qt 5, OpenCV, GDAL) | -62 MB | - |
| No program translations (`/usr/share/locale`): the session runs in `C.UTF-8` and the web app has its own translations | -44 MB | -42 MB |
| No Noto CJK serif faces: the desktop and the app use sans-serif | -39 MB | -39 MB |
| Graphics drivers that load firmware (amdgpu, radeon, nouveau, i915, xe) left out of the initrd; plymouth and live-boot added every one, copying over 150 MB of firmware that the live filesystem already has | -67 MB | -22 MB |
| No CPU microcode (initrd and squashfs) | -28 MB | -28 MB |
| **Total (estimated)** | **-317 MB** | **-197 MB** |

The ISO is expected to be about 1,420 MB (-27%). The initrd change was
checked by building both initrds with the hook: the 64-bit initrd without
microcode is about 48 MB instead of 130 MB, the 32-bit one about 36 MB
instead of 72 MB, and the small display drivers used by virtual machines
(bochs, virtio, qxl, vmwgfx) and the simple framebuffer drivers stay in.

Other large dependencies are needed and stay: `cpp` (for `xrdb`, part of
`x11-xserver-utils`), Ghostscript (through imlib2, used by tint2 and
Openbox), Tk (x11vnc), `grub-common` (os-prober, used to name installed
systems) and the Samba client (`smbtree`, used to find network shares).
Non-free firmware is installed without recommended packages; on trixie the
Intel graphics, Intel network, MediaTek and NVIDIA firmware packages are
named explicitly because `firmware-misc-nonfree` only recommends them.

### Repository

* `README.md` and `CHANGES.md` updated for the dual-architecture build and
  the Backtrail name; this document describes the changes from Redo Rescue.
* `.gitignore` added for build output (ISO, caches, build roots, logs).


## Verification

* **Package sets**: both systems' full package lists (about 700 packages
  each) resolve without conflicts in simulated installs.
* **32-bit PHP**: old and new code compared on real 32-bit PHP 8.2.
* **Automatic boot selection**: under QEMU, Pentium III, Atom N270 and
  Core Duo CPUs chose the 32-bit system; Core 2 Duo and qemu64 chose the
  64-bit system.
* **Locale**: requests sent to a real php-fpm 8.4 confirmed that commands
  print non-ASCII names correctly only with `env[LANG]` set.
* **Build script**: a dry run with stubbed build tools covered a full build,
  Ctrl+C during debootstrap, `clean`, `changes` and `boot`.
* **Real builds**: full builds booted under QEMU; icons confirmed after the
  icon fix.
* **Theme**: the GRUB theme was booted under QEMU. Every web app page was
  checked in headless Chromium in light and dark modes, with stub disk tools
  and a sample image, and pages were linted with PHP 8.4 and 32-bit PHP 8.2.
  Font selection was checked with fontconfig for each CJK language.
* **End-to-end runs**: the built ISO was booted in QEMU with a GPT test disk
  (FAT32, ext4 with a Korean label and file names, a raw partition), a
  backup disk and a blank target disk. On both the 64-bit and the 32-bit
  system, a backup, a verification and a full system recovery were run
  through the web app's own requests; the restored disk matched the source
  (partition table with UUIDs, fsck clean, every file checksum, the raw
  partition byte for byte). A 64-bit backup verified on the 32-bit system.
  Booting with UEFI Secure Boot enabled (Microsoft keys) reached the app
  with the kernel in lockdown mode, and the VNC server answered. The same
  runs were repeated on the rebuilt ISO after fixing the missing `fdisk`
  package, and the restored GPT disk then had its backup header.
* **Screens**: the wallpaper was checked in the VM at 1920x1080, 1280x800,
  1024x768, 1280x1024 and 1024x600, and the app at 1024x600.
* **App window**: before the switch from Chromium, the WebKitGTK window was
  tested in the VM on the 64-bit system and on an emulated 32-bit CPU
  without SSE3: page layout, language menu, dark mode, the language and
  theme being kept after the window is reopened, drop-down lists, tooltips,
  tabs, the folder picker, a full backup with its completion dialog,
  copying the log to the clipboard and the Exit button.
* **Languages**: the main pages were checked for layout and wrapping across
  the eight languages, the language menu was used to switch languages mid-flow,
  and backup and verification runs were simulated with stub tools to check
  the translated progress and error messages.


## Limitations and trade-offs

* **ISO size**: the ISO carries two systems, so it is larger than a
  single-system image (about 1.4 GB).
* **No web browser**: the app runs in its own window, which cannot open
  other sites, so the desktop has no general web browser.
* **Boot splash**: AMD, NVIDIA and Intel graphics drivers load after the
  live filesystem is mounted, so on those GPUs the splash screen starts on
  the firmware framebuffer and may appear later, or as text when booting
  in BIOS mode.
* **No CPU microcode**: microcode updates are not loaded at boot. Most
  CPUs run correctly without them, but a few rely on them to fix bugs.
* **32-bit support period**: the 32-bit system gets security updates only
  until Debian 12 LTS ends (expected mid-2028). Debian has no newer i386
  release to move to.
* **32-bit CPU requirements**: an i686-class CPU is required. The app
  window uses WebKitGTK, which Debian builds for i386 without SSE2; it was
  tested on an emulated CPU without SSE3, where Chromium refused to start.
* **32-bit UEFI**: machines with 32-bit-only UEFI firmware are not
  supported (same as Redo Rescue).
* **Language**: the web app is translated, but the desktop, system tools
  and boot menu remain in English, and no input method is included for
  typing CJK text. The translations were written for this release and
  still need review by native speakers.
* **Debian 9**: the legacy isolinux build path was removed, so Debian 9
  images can no longer be built.
