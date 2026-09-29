# Contributing to Backtrail

Thank you for helping. Bug reports, translations, testing on real hardware
and code changes are all welcome. By taking part you agree to follow the
[Code of Conduct](CODE_OF_CONDUCT.md).

## Asking and reporting

* **Questions and ideas**: use
  [Discussions](https://github.com/johnhsb/backtrail/discussions).
* **Bugs**: open an [issue](https://github.com/johnhsb/backtrail/issues/new/choose)
  with the Backtrail version, the boot mode (BIOS or UEFI), whether the
  64-bit or 32-bit system ran, and the detailed log (the app's "Copy to
  clipboard" button).
* **Security problems**: do not open an issue; see [SECURITY.md](SECURITY.md).

Backups made with Redo Rescue must keep restoring with Backtrail. If you find
a Redo Rescue image that does not, please report it.

## Project layout

| Path | Contents |
|---|---|
| `make` | Build script: creates the live systems and the ISO |
| `overlay/rootdir/` | Files copied into each live system |
| `overlay/rootdir/var/www/html/` | The backup and restore app (PHP, Bootstrap 5) |
| `overlay/image/` | Files copied onto the ISO (boot menu) |
| `branding/` | Logos, colors and the generators for every image |
| `tools/` | Development checks |

[HERITAGE.md](HERITAGE.md) explains how and why Backtrail differs from
Redo Rescue.

## Building and testing

Build the ISO on Debian 13 (or in a Debian 13 container or VM) as described
in the README's [Build](README.md#build) section. `sudo ./make changes`
updates a built image without downloading everything again.

Before sending a change, test it in a virtual machine, for example:

    qemu-system-x86_64 -enable-kvm -m 2048 -cdrom backtrail-1.0.0.iso \
        -drive file=test-disk.qcow2,if=virtio

For changes to backup, restore or verification, run a backup and a full
restore of a test disk, and check the 32-bit system too when the change
touches PHP code (32-bit PHP handles large numbers differently).

## Checks

The same checks run on every pull request:

    shellcheck --severity=error make overlay/rootdir/root/enable-ssh overlay/rootdir/root/redo-monitor
    find overlay -name '*.php' -exec php -l {} \;
    python3 tools/i18n-check.py
    python3 branding/src/build.py branding && git diff --exit-code branding

## Translations

The app's text is in English in the code, wrapped in `t('...')` (PHP) or
`BT.t('...')` (JavaScript). Each language has one file,
`overlay/rootdir/var/www/html/lang/<tag>.php`; the steps for adding a
language are at the top of `lang/languages.php`. Run
`python3 tools/i18n-check.py` to find missing or broken entries. Reviews of
the existing translations by native speakers are especially welcome.

## Pull requests

* Keep each pull request to one change, and describe what it fixes and how
  you tested it.
* Write commit subjects in the imperative, without a trailing period
  ("Install fdisk explicitly").
* Add a line to the pending release in [CHANGES.md](CHANGES.md) for changes
  users will notice.
* Do not rename the `.redo` image format or the internal `redo` names
  (`redo.service`, the `redo` user); images and scripts depend on them.

Contributions are released under the GNU GPLv3, the project's license.
