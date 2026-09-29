# Security

## Supported versions

Backtrail is a live system: each release is a new ISO, and fixes ship in a
new release rather than as updates to a running system.

| Version | Supported |
|---|---|
| Latest 1.x release | Yes |
| Earlier releases | No; use the latest release |
| Redo Rescue (any version) | No; Redo Rescue is no longer maintained |

The 32-bit system is based on Debian 12 and can receive security fixes only
until Debian 12 LTS ends (expected mid-2028).

## Reporting a vulnerability

Please report vulnerabilities privately through GitHub:
**[Report a vulnerability](https://github.com/johnhsb/backtrail/security/advisories/new)**
(Security tab → "Report a vulnerability"). Do not open a public issue.

Include the Backtrail version (shown on the boot menu), whether the 64-bit or
32-bit system was running, and the steps to reproduce. You should receive a
reply within 7 days. Once a fix is released, the advisory is published with
credit to the reporter unless you ask otherwise.

Vulnerabilities in Debian packages included in the image (the kernel, PHP,
nginx, WebKitGTK and others) should also be reported to
[Debian](https://www.debian.org/security/); Backtrail picks up their fixes in
the next release.

## Security model

Backtrail is meant to run for a short time on a machine you control, on a
network you trust. It needs full access to the disks, so:

* The desktop logs in automatically as `root`. The `root` password is
  `redo`, as documented in the README, so anyone with the password and
  network access can log in once SSH is enabled.
* SSH is off until `/root/enable-ssh` is run from the desktop.
* A VNC server starts with the desktop, protected by a short random
  password shown in the app bar, for remote assistance.
* The backup and restore app is served by nginx on port 80 and its PHP
  backend runs as `root`. It has no login, so anyone who can reach the
  machine on port 80 can use it, including restoring over a disk.
* The firewall drops incoming connections except SSH (22), HTTP (80) and
  VNC (5900).

Until these are tightened, run Backtrail only on a trusted network, or
disconnect the network cable when you back up to a local disk.
