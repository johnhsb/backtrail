# Fork Notes: Changes from Upstream

Backtrail (this fork) is based on [redorescue/redorescue](https://github.com/redorescue/redorescue)
at commit `ec1f4f2` ("Update for PHP8", 2023-10-30). For the per-release
changelog, see [CHANGES.md](CHANGES.md).


## Background

* Upstream development stopped during the pending 5.0.0 release, which was
  based on Debian 12 (bookworm) for 64-bit PCs only. The last published
  release is 4.0.0 (2021).
* Debian 13 (trixie) has since been released. Debian 12 has left regular
  security support and entered its LTS period.
* Upstream images are 64-bit only, so older 32-bit PCs, which often need a
  rescue tool the most, cannot run them.
* Building and reviewing the upstream script revealed several problems:
  missing host build dependencies, chroot mounts left behind by interrupted
  builds, an integer overflow in 32-bit PHP, and missing icons, fonts and
  locale support once the base moved to trixie.


## Goals

1. **Current base system**: move the 64-bit system to Debian 13 for newer
   hardware support and a longer security support window.
2. **One ISO for 32-bit and 64-bit PCs**: detect the CPU at boot and start
   the matching system automatically.
3. **Reliable builds**: interrupted or failed builds must not leave host
   mounts or broken caches behind.
4. **CJK text display**: show Chinese, Japanese and Korean text correctly in
   the browser and in the application.
5. **Own identity**: Redo Rescue's logos and graphics are not licensed for
   forks, so the fork needs its own name, artwork and a consistent look from
   the boot menu to the application.


## Improvements over upstream

### Platform and security

| Area | Upstream | This fork | Benefit |
|---|---|---|---|
| 64-bit system | Debian 12, Linux 6.1, PHP 8.2 | **Debian 13, Linux 6.12, PHP 8.4** | Newer hardware support, longer security support |
| 32-bit system | Not available | **Debian 12 i386 (686 kernel), PHP 8.2** | Runs on older 32-bit PCs |
| Boot selection | 64-bit only | **Automatic by CPU**: GRUB `cpuid -l` on BIOS; 64-bit on UEFI | One USB stick for all PCs; UEFI Secure Boot kept |
| Manual fallback | — | 32-bit menu entry on 64-bit BIOS machines | Recovery option if the 64-bit system fails |
| Security updates | Packages from the base release only | **`-updates` and `-security` repositories, plus a full upgrade** | Chromium, kernel and PHP ship with current security fixes |

Debian 13 no longer provides an i386 kernel, so the 32-bit system uses
Debian 12. The ISO contains two independent live systems (`/live-amd64` and
`/live-i386`), each with its own kernel, initrd and squashfs.

### Backup and restore application

| Issue | Upstream behavior | This fork |
|---|---|---|
| 32-bit PHP integer limit | Sizes above 2 GiB were clamped to 2147483647: restores were refused as "will not fit" and progress went negative (-35.9% at 50% in testing) | `to_bytes()` keeps large values as floats; 64-bit behavior is unchanged |
| ReiserFS and NILFS2 | Called `partclone.reiser4` / `partclone.nilfs2`, which do not match ReiserFS or do not exist in Debian (`partclone.reiser4` was also removed in trixie) | Imaged in raw mode, which the application already supports |
| Non-ASCII names | Under the `C` locale, tools such as `lsblk` escape non-ASCII text, so CJK partition labels appeared as `\xed\x95\x9c...` | **`C.UTF-8` locale**: names are shown as-is |
| Starting a backup on PHP 8 | `backup_init()` counted the selected partitions with `get_object_vars()`, but a backup's partition list is an array, so PHP 8 stopped with a `TypeError` before the backup began | Counted as an array for backups, restores and verifications |
| Changing the drive | After going back from the partition or restore-options step, the drive chosen first was kept even when another was selected | A newly chosen drive replaces the saved one |

The locale is set for every path that runs commands:

* `/etc/locale.conf` for systemd services (Redo monitor, php-fpm)
* `/etc/default/locale` for the login session (slim → X → Chromium)
* `env[LANG]` in the php-fpm pool, because workers start with a cleared
  environment
* `/etc/bash.bashrc` for terminal shells

`C.UTF-8` is built into glibc, so no extra package is needed. Program
messages remain in English, so the application's parsing of partclone and
mount output is unaffected. A language-specific locale such as
`zh_CN.UTF-8`, `ja_JP.UTF-8` or `ko_KR.UTF-8` would translate those messages
and break that parsing.

### Build script (`make`)

| Issue | Upstream | This fork |
|---|---|---|
| Interrupted build | `proc`, `sys` and `dev/pts` stayed mounted inside the build root | Unmounted automatically on exit or interrupt; `clean` unmounts first and deletes with `--one-file-system` |
| Failed debootstrap | A broken cache archive was created and reused by the next build | Cache is written only after success, via an atomic rename |
| Missing host tools | Without `rsync` and `mkfs.vfat` the build still "succeeded", producing an ISO with no overlay applied and a broken UEFI boot image | `rsync` and `dosfstools` added to the host dependencies |
| Build targets | One system | `TARGETS="amd64:trixie i386:bookworm"`; `./make changes amd64` or `i386` updates a single system |
| 32-bit chroot | — | Runs under `setarch i686`, so `uname -m` reports a 32-bit machine |
| Legacy code | Unused Debian 9 isolinux path | Removed |
| Repeated downloads | Every full build downloaded all packages again (about 700 per system); only the debootstrap base was cached | `cache/apt-BASE-ARCH` is mounted over the chroot's package cache, so rebuilds only fetch changed packages; the image itself still ships without `.deb` files |

### Desktop

| Issue | Upstream / first trixie build | This fork |
|---|---|---|
| Icons | Most icons blank on trixie: Adwaita became almost all SVG, and the SVG loader and full-color icons were skipped by `--no-install-recommends` | `librsvg2-common` (both systems) and the Papirus icon theme, whose full-color icons replace `adwaita-icon-theme-legacy`; icon caches are kept so apps start without scanning Papirus's 40,000 icons |
| Cursors | Trixie's Adwaita dropped legacy X11 names, so openbox's app-launch pointer fell back to the old X11 cursor | 12 missing names linked to their Adwaita equivalents (trixie only) |
| Notification daemon | Autostart path hard-coded to `i386-linux-gnu`, so it did not start on 64-bit | Architecture-independent path |
| "Unnamed Window" | SLiM 1.4.1 (trixie) leaves a full-screen window with no name or class after auto-login; Openbox showed it as a black window over the wallpaper and in the taskbar | An Openbox rule keeps windows with no name and no class minimized and out of the taskbar |
| CJK text | No CJK font installed; CJK characters showed as boxes | **`fonts-noto-cjk`** (Chinese, Japanese, Korean) |

### Name, artwork and interface

Redo Rescue's license requires forks to replace its logos and graphics, so
the fork was renamed **Backtrail** and every original graphic was removed.
Internal names (the `.redo` image format, `redo.service`, the `redo` user)
are unchanged, so existing backups and scripts keep working.

| Area | Upstream | This fork |
|---|---|---|
| Logo | Redo Rescue logo | Contour-line mark generated from source (`branding/src`); see [branding/README.md](branding/README.md) |
| Boot menu | GRUB theme with Redo artwork and Helvetica bitmap fonts, and a "Choose language" submenu that only offered English | Navy contour background, Backtrail logo, Pretendard fonts, countdown ring; the language submenu is removed because the web app has its own language menu |
| Boot splash | Plymouth `redo` theme | Plymouth `backtrail` theme in the same colors |
| Desktop | Numix GTK and Openbox theme, Adwaita icons, Lato font | `Backtrail` Openbox theme, matching tint2 panel, GTK Adwaita with Papirus icons, Pretendard, new wallpaper and app icon |
| Web framework | Bootstrap 3.4, jQuery 1.12, Bootbox 5, plus bootstrap-notify, jquery-validation and animate.css | **Bootstrap 5.3, jQuery 3.7, Bootbox 6**; unused libraries removed |
| Application | Separate page layouts | Step indicator, drive list on the welcome screen, partition map with selected size, shared location, progress and image-detail views, light and dark modes |
| Names | Redo Rescue, `redorescue` hostname, `redorescue-VERSION.iso` | Backtrail, `backtrail` hostname, `backtrail-VERSION.iso` |

**Fonts.** Interface text uses Pretendard, which covers Latin and all Hangul
syllables, with Noto Sans CJK for Chinese and Japanese. Debian 12 has no
Pretendard package, so the 32-bit build installs `fonts-pretendard` alone
from trixie; the package has no dependencies, and a low apt pin keeps every
other package on bookworm. Because Pretendard lacks the archaic jamo that
fontconfig requires for Korean, text tagged `ko` would otherwise fall back
to Noto Sans CJK KR; `local.conf` keeps it on Pretendard and gives text
tagged `ja` or `zh` the matching regional Noto Sans CJK face.

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

The 64-bit initrd shrinks less because it begins with about 50 MB of CPU
microcode, which the kernel requires uncompressed.

### Repository

* `README.md` and `CHANGES.md` updated for the dual-architecture build and
  the Backtrail name.
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
* **Languages**: the main pages were checked for layout and wrapping across
  the eight languages, the language menu was used to switch languages mid-flow,
  and backup and verification runs were simulated with stub tools to check
  the translated progress and error messages.


## Limitations and trade-offs

* **ISO size**: the ISO carries two systems, so it is larger than a
  single-system image (about 1.7 GB with zstd).
* **32-bit support period**: the 32-bit system gets security updates only
  until Debian 12 LTS ends (expected mid-2028). Debian has no newer i386
  release to move to.
* **32-bit CPU requirements**: an i686-class CPU is required. Chromium
  requires SSE3, so on CPUs without it (Pentium III, Pentium M, Athlon XP)
  the system may boot but the Backtrail interface is unlikely to start.
* **32-bit UEFI**: machines with 32-bit-only UEFI firmware are not
  supported (same as upstream).
* **Language**: the web app is translated, but the desktop, system tools
  and boot menu remain in English, and no input method is included for
  typing CJK text. The translations were written for this release and
  still need review by native speakers.
* **Debian 9**: the legacy isolinux build path was removed, so Debian 9
  images can no longer be built.
