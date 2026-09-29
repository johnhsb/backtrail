#!/bin/bash
#
# Backtrail: Backup and recovery made easy
# Copyright (C) 2026 The Backtrail Authors
# Based on Redo Rescue <redorescue.com>
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

VER=1.0.0
# Each target (ARCH:BASE) becomes a separate live system in the same ISO;
# the boot menu picks the one matching the CPU. Debian 13 has no i386 kernel.
TARGETS="amd64:trixie i386:bookworm"
FILE=setup.sh
USER=redo
NONFREE=true

# Set colored output codes
red='\e[1;31m'
wht='\e[1;37m'
yel='\e[1;33m'
off='\e[0m'

# Show title
echo -e "\n$off---------------------------"
echo -e "$wht   BACKTRAIL ISO CREATOR$off"
echo -e "       Version $VER"
echo -e "---------------------------\n"

# Check: Must be root
if [ "$EUID" -ne 0 ]
	then echo -e "$red* ERROR: Must be run as root.$off\n"
	exit
fi

# Check: No spaces in cwd
if [[ `pwd` == *" "* ]]
	then echo -e "$red* ERROR: Current absolute pathname contains a space.$off\n"
	exit
fi

# Get requested action
ACTION=$1

unmount_chroot() {
	#
	# Release chroot mounts (proc, sys, dev/pts) left by an interrupted build
	#
	grep -oE " $(pwd)/rootdir[^ ]*" /proc/mounts | sort -r | xargs -r umount -lf
}

# Never leave host filesystems mounted inside a build root
trap unmount_chroot EXIT

set_target() {
	#
	# Select build target, given as ARCH:BASE (e.g. i386:bookworm)
	#
	ARCH=${1%%:*}
	BASE=${1#*:}
	ROOT=rootdir-$ARCH
	LIVE=image/live-$ARCH
	# Downloaded packages are kept here between builds (see chroot_exec)
	PKGCACHE=cache/apt-$BASE-$ARCH
	# Run 32-bit chroots with a 32-bit personality so `uname -m` is i686
	PERS=""
	if [ "$ARCH" == "i386" ]; then PERS="setarch i686"; fi
	echo -e "$yel* Target: $ARCH ($BASE)$off"
}

clean() {
	#
	# Remove all build files
	#
	unmount_chroot
	rm -rf --one-file-system image scratch rootdir rootdir-* *.iso
	echo -e "$yel* All clean!$off\n"
	exit
}

prepare() {
	#
	# Prepare host environment
	#
	echo -e "$yel* Building $ROOT from scratch.$off"
	unmount_chroot
	rm -rf --one-file-system $ROOT
	CACHE=debootstrap-$BASE-$ARCH.tar.gz
	if [ -f "$CACHE" ]; then
		echo -e "$yel* $CACHE exists, extracting existing archive...$off"
		sleep 2
		tar zxvf $CACHE
	else
		echo -e "$yel* $CACHE does not exist, running debootstrap...$off"
		sleep 2
		apt-get install debootstrap squashfs-tools grub-pc-bin \
			grub-efi-amd64-signed shim-signed mtools xorriso \
			rsync dosfstools
		mkdir -p $ROOT
		if ! debootstrap \
			--arch=$ARCH \
			--variant=minbase \
			$BASE $ROOT; then
			echo -e "$red* ERROR: debootstrap failed for $ARCH ($BASE).$off\n"
			exit 1
		fi
		# Write to a temporary name so an interrupted run leaves no bad cache
		tar zcvf $CACHE.tmp ./$ROOT && mv $CACHE.tmp $CACHE
	fi

}

script_init() {
	#
	# Setup script: Base configuration
	#
	cat > $ROOT/$FILE <<EOL
#!/bin/bash

# System mounts
mount none -t proc /proc;
mount none -t sysfs /sys;
mount none -t devpts /dev/pts

# Set hostname
echo 'backtrail' > /etc/hostname
echo 'backtrail' > /etc/debian_chroot

# Set hosts
cat > /etc/hosts <<END
127.0.0.1	localhost
127.0.1.1	backtrail
::1		localhost ip6-localhost ip6-loopback
ff02::1		ip6-allnodes
ff02::2		ip6-allrouters
END

# Set default locale: UTF-8 so non-ASCII names (e.g. CJK labels)
# are not escaped, while program messages stay in English for parsing
cat >> /etc/bash.bashrc <<END
export LANG="C.UTF-8"
export LC_ALL="C.UTF-8"
END

# Export environment
export HOME=/root; export LANG=C; export LC_ALL=C;

# Keep downloaded packages in the package cache (the host's cache folder is
# mounted there); "apt" deletes them after installing by default
echo 'Binary::apt::APT::Keep-Downloaded-Packages "true";' > /etc/apt/apt.conf.d/90build-cache

EOL
}

script_build() {
	#
	# Setup script: Install packages
	#
	if [ "$ARCH" == "i386" ]; then
		KERN="686"
	else
		KERN="amd64"
	fi
	if [ "$BASE" == "trixie" ]; then
		# Trixie-specific PHP version and packages
		# (hfsutils and reiser4progs were removed from Debian 13)
		PHPV="8.4"
		PKGS="volumeicon-alsa exfatprogs fonts-pretendard"
	elif [ "$BASE" == "bookworm" ]; then
		# Bookworm-specific PHP version and packages
		PHPV="8.2"
		PKGS="volumeicon-alsa exfatprogs hfsutils reiser4progs"
	elif [ "$BASE" == "bullseye" ]; then
		# Bullseye-specific PHP version and packages
		PHPV="7.4"
		PKGS="volumeicon-alsa curlftpfs exfat-utils hfsutils reiser4progs"
	elif [ "$BASE" == "buster" ]; then
		# Buster uses PHP 7.3
		PHPV="7.3"
		PKGS="volti obmenu curlftpfs exfat-utils hfsutils reiser4progs"
	else
		# Stretch uses PHP 7.0
		PHPV="7.0"
		PKGS="volti obmenu curlftpfs exfat-utils hfsutils reiser4progs"
	fi
	if [ "$BASE" == "buster" ] || [ "$BASE" == "stretch" ]; then
		SECSUITE="$BASE/updates"
	else
		SECSUITE="$BASE-security"
	fi
	cat >> $ROOT/$FILE <<'EOL'
# Keep files the live system never uses out of the image: program
# translations (the session runs in C.UTF-8 and the web app has its own)
# and the Noto CJK serif faces (the desktop and the app use sans-serif)
cat > /etc/dpkg/dpkg.cfg.d/90backtrail-exclude <<END
path-exclude=/usr/share/locale/*
path-include=/usr/share/locale/locale.alias
path-exclude=/usr/share/fonts/opentype/noto/NotoSerifCJK*
END

# Leave the graphics drivers that load large firmware (AMD, NVIDIA, Intel)
# out of the initramfs. Plymouth and live-boot add every KMS driver, which
# also copies over 150 MB of firmware that the live filesystem already has;
# the drivers load from there once it is mounted, and the boot splash uses
# the firmware framebuffer until then.
mkdir -p /etc/initramfs-tools/hooks
cat > /etc/initramfs-tools/hooks/backtrail-gpu <<'END'
#!/bin/sh
PREREQ=""
prereqs() { echo "$PREREQ"; }
case "$1" in
prereqs) prereqs; exit 0 ;;
esac

DRM="/lib/modules/${version}/kernel/drivers/gpu/drm"
DIRS="amd radeon nouveau i915 xe"

# Initramfs-tools 0.144 and later (Debian 13) queue modules and copy them
# after the hooks have run: take these drivers off the queue
if [ -n "${__MODULES_TO_ADD}" ] && [ -f "${__MODULES_TO_ADD}" ]; then
	for d in $DIRS; do
		[ -d "$DRM/$d" ] || continue
		find "$DRM/$d" -name '*.ko*' -printf '%f\n' | sed 's/\.ko.*$//'
	done | sort -u > "${DESTDIR}/.gpu-modules"
	grep -v -x -F -f "${DESTDIR}/.gpu-modules" "${__MODULES_TO_ADD}" > "${DESTDIR}/.modules" || true
	cat "${DESTDIR}/.modules" > "${__MODULES_TO_ADD}"
	rm -f "${DESTDIR}/.gpu-modules" "${DESTDIR}/.modules"
fi

# Older versions (Debian 12) copy modules and firmware straight away
for d in $DIRS; do
	rm -rf "${DESTDIR}/usr/lib/modules/${version}/kernel/drivers/gpu/drm/$d"
done
for d in amdgpu radeon nvidia i915 xe; do
	rm -rf "${DESTDIR}/usr/lib/firmware/$d" "${DESTDIR}/usr/lib/firmware/updates/$d"
done
END
chmod 755 /etc/initramfs-tools/hooks/backtrail-gpu

EOL
	cat >> $ROOT/$FILE <<EOL
# Enable stable updates and security repositories
cat > /etc/apt/sources.list <<END
deb http://deb.debian.org/debian $BASE main
deb http://deb.debian.org/debian $BASE-updates main
deb http://security.debian.org/debian-security $SECSUITE main
END

# Apply updates to the base system
export DEBIAN_FRONTEND=noninteractive
apt update --yes
apt upgrade --yes

# Install packages. python3-gi and gir1.2-webkit2-4.1 run the app window
# (/usr/local/bin/backtrail-app, WebKitGTK). lxpolkit is named so that it
# is the polkit agent network-manager-gnome needs: apt would otherwise pick
# ukui-polkit on Debian 13, which pulls in Qt, OpenCV and GDAL (about 190 MB).
apt install --no-install-recommends --yes \
	\
	linux-image-$KERN live-boot systemd-sysv firmware-linux-free sudo \
        vim-tiny pm-utils iptables-persistent iputils-ping net-tools wget \
	openssh-client openssh-server rsync less \
	\
	xserver-xorg x11-xserver-utils xinit openbox obconf slim \
	plymouth plymouth-themes compton dbus-x11 libnotify-bin xfce4-notifyd \
	gir1.2-notify-0.7 tint2 nitrogen xfce4-appfinder xfce4-power-manager \
	gsettings-desktop-schemas lxrandr lxmenu-data lxterminal lxappearance \
	network-manager-gnome lxpolkit wpasupplicant gtk2-engines gnome-themes-extra gtk-theme-switch \
	fonts-noto-cjk pcmanfm libfm-modules gpicview mousepad x11vnc pwgen \
	xvkbd librsvg2-common zstd \
	papirus-icon-theme adwaita-icon-theme gtk-update-icon-cache \
	\
	beep laptop-detect os-prober discover lshw-gtk hdparm smartmontools fdisk \
	nmap time lvm2 gparted gnome-disk-utility baobab gddrescue testdisk \
	dosfstools ntfs-3g reiserfsprogs jfsutils \
	smbclient cifs-utils nfs-common sshfs partclone pigz yad f2fs-tools \
	exfat-fuse btrfs-progs \
	\
	nginx php-fpm php-cli python3-gi gir1.2-webkit2-4.1 $PKGS
EOL
	if [ "$BASE" != "trixie" ]; then
		cat >> $ROOT/$FILE <<'EOL'

# Pretendard (UI font) is packaged from Debian 13 on. It has no dependencies,
# so take only that package from trixie; the low pin keeps everything else
# on the base release.
echo 'deb http://deb.debian.org/debian trixie main' > /etc/apt/sources.list.d/trixie.list
cat > /etc/apt/preferences.d/trixie <<END
Package: *
Pin: release n=trixie
Pin-Priority: 100
END
apt update --yes
apt install --no-install-recommends --yes fonts-pretendard/trixie
rm -f /etc/apt/sources.list.d/trixie.list /etc/apt/preferences.d/trixie
apt update --yes
EOL
	fi
	cat >> $ROOT/$FILE <<EOL

# Modify /etc/issue banner
perl -p -i -e 's/^D/Backtrail $VER\nBased on D/' /etc/issue

# Set vi editor preferences
perl -p -i -e 's/^set compatible$/set nocompatible/g' /etc/vim/vimrc.tiny

# Use local RTC in Linux (via /etc/adjtime) and disable network time updates
# (systemd-timesyncd is only a recommended package, so it is usually absent)
if [ -e /lib/systemd/system/systemd-timesyncd.service ]; then
	systemctl disable systemd-timesyncd.service
fi

# Disable SSH server and delete keys
systemctl disable ssh
rm -f /etc/ssh/ssh_host_*

# Add regular user
useradd --create-home $USER --shell /bin/bash
adduser $USER sudo
echo '$USER:$USER' | chpasswd

# Prepare single-user system
echo 'root:$USER' | chpasswd
echo 'default_user root' >> /etc/slim.conf
echo 'auto_login yes' >> /etc/slim.conf
echo "Setting default plymouth theme..."
plymouth-set-default-theme -R backtrail
update-initramfs -u
ln -s /usr/bin/pcmanfm /usr/bin/nautilus

# Icon theme caches (built by gtk-update-icon-cache) are kept: Papirus has
# over 40,000 icons, and without a cache every app scans them at startup.
# Cursors still come from Adwaita, which Papirus does not replace.

# Restore legacy X11 cursor names that Debian 13's Adwaita dropped, e.g.
# openbox's app-launch pointer and GTK3 drag-and-drop (older themes have them)
cd /usr/share/icons/Adwaita/cursors
for PAIR in left_ptr_watch:progress left_ptr_help:help hand:hand2 \
	dnd-copy:copy dnd-link:alias dnd-none:no-drop dnd-no-drop:no-drop \
	dnd-ask:context-menu h_double_arrow:sb_h_double_arrow \
	v_double_arrow:sb_v_double_arrow circle:not-allowed crossed_circle:not-allowed; do
	[ -e "\${PAIR%%:*}" ] || ln -s "\${PAIR#*:}" "\${PAIR%%:*}"
done
cd /

# Configure nginx/php-fpm application server
perl -p -i -e 's/^user = .*$/user = root/g' /etc/php/$PHPV/fpm/pool.d/www.conf
perl -p -i -e 's/^group = .*$/group = root/g' /etc/php/$PHPV/fpm/pool.d/www.conf
# Workers start with a cleared environment; commands they run need UTF-8
echo 'env[LANG] = C.UTF-8' >> /etc/php/$PHPV/fpm/pool.d/www.conf
perl -p -i -e 's/^ExecStart=(.*)$/ExecStart=\$1 -R/g' /lib/systemd/system/php$PHPV-fpm.service
cat > /etc/nginx/sites-available/redo <<'END'
server {
	listen		80 default_server;
	server_name	localhost;
	root		/var/www/html;
	index		index.php;
	location ~* \.php$ {
		fastcgi_pass	unix:/run/php/php$PHPV-fpm.sock;
		include		fastcgi_params;
		fastcgi_param	SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
		fastcgi_param	SCRIPT_NAME \$fastcgi_script_name;
	}
}
END
rm -f /etc/nginx/sites-enabled/default
ln -s /etc/nginx/sites-available/redo /etc/nginx/sites-enabled/
EOL
}

script_add_nonfree() {
	#
	# Setup script: Install non-free packages for hardware support
	#
	# Non-free firmware does not comply with the Debian DFSG and is
	# not included in official releases.  For more information, see
	# <https://www.debian.org/social_contract> and also
	# <http://wiki.debian.org/Firmware>.
	#
	# WARNING: Wireless connections are *NOT* recommended for backup
	# and restore operations, but are included for other uses.
	#
	if [ "$BASE" == "trixie" ]; then
		# Debian 13 moved these out of firmware-misc-nonfree, which only
		# recommends them: Intel graphics (i915/xe), Intel network and misc,
		# MediaTek and Ralink Wi-Fi, NVIDIA graphics
		NFPKGS="firmware-intel-graphics firmware-intel-misc firmware-mediatek firmware-nvidia-graphics"
	else
		NFPKGS=""
	fi
	cat >> $ROOT/$FILE <<EOL
echo "Adding non-free packages..."
# Briefly activate repos to install non-free firmware packages
perl -p -i -e 's/main$/main non-free non-free-firmware/' /etc/apt/sources.list
apt update --yes
# Wireless firmware for Intel, Realtek, Qualcomm Atheros and Broadcom
# adapters, for computers without a wired port. WARNING: Wireless
# connections are NOT recommended for backup/restore!
#
# To include more firmware, add packages here to create a custom image.
# Recommended packages are not installed, so every package is named here;
# CPU microcode (amd64-microcode, intel-microcode, recommended by
# firmware-linux-nonfree) is left out to keep the image small.
#
apt install --no-install-recommends --yes \
	firmware-linux-nonfree \
	firmware-misc-nonfree \
	firmware-amd-graphics \
	firmware-iwlwifi \
	firmware-realtek \
	firmware-atheros \
	firmware-brcm80211 \
	$NFPKGS
perl -p -i -e 's/ non-free non-free-firmware$//' /etc/apt/sources.list
apt update --yes
EOL
}

script_shell() {
	#
	# Setup script: Insert command to open shell for making changes
	#
	cat >> $ROOT/$FILE << EOL
echo -e "$red>>> Opening interactive shell. Type 'exit' when done making changes.$off"
echo
bash
EOL
}

script_exit() {
	#
	# Setup script: Clean up and exit
	#
	cat >> $ROOT/$FILE <<EOL
# Save space
rm -f /usr/bin/{rpcclient,smbcacls,smbclient,smbcquotas,smbget,smbspool,smbtar}
rm -rf /usr/share/doc
rm -rf /usr/share/man
find /usr/share/locale -mindepth 1 -maxdepth 1 ! -name locale.alias -exec rm -rf {} +

# Clean up and exit
apt-get autoremove
# Drop cached versions that can no longer be downloaded, then detach the
# host's package cache before emptying the image's own cache
apt-get autoclean
rm -f /etc/apt/apt.conf.d/90build-cache
if mountpoint -q /var/cache/apt/archives; then umount /var/cache/apt/archives; fi
if ! mountpoint -q /var/cache/apt/archives; then apt-get clean; fi
rm -rf /var/lib/dbus/machine-id
rm -rf /tmp/*
rm -f /etc/resolv.conf
rm -f /etc/debian_chroot
rm -rf /var/lib/apt/lists/????????*
umount -lf /proc;
umount /sys;
umount /dev/pts
exit
EOL
}

chroot_exec() {
	#
	# Execute setup script inside chroot environment
	#
	echo -e "$yel* Copying assets to root directory...$off"
	# Copy assets before configuring plymouth theme
	rsync -h --info=progress2 --archive \
		./overlay/rootdir/usr/share/* \
		./$ROOT/usr/share/

	# Copy /etc/resolv.conf before running setup script
	cp /etc/resolv.conf ./$ROOT/etc/

	# Reuse packages downloaded by earlier builds: mount the host's cache
	# folder over the chroot's package cache (unmounted by the setup script
	# before it cleans up, or by unmount_chroot if the build stops)
	mkdir -p $PKGCACHE $ROOT/var/cache/apt/archives
	mount --bind $PKGCACHE $ROOT/var/cache/apt/archives
	echo -e "$yel* Package cache: $PKGCACHE ($(du -sh $PKGCACHE | cut -f1))$off"

	# Run setup script inside chroot
	chmod +x $ROOT/$FILE
	echo
	echo -e "$red>>> ENTERING CHROOT SYSTEM ($ARCH)$off"
	echo
	sleep 2
	$PERS chroot $ROOT/ /bin/bash -c "./$FILE"
	if mountpoint -q $ROOT/var/cache/apt/archives; then umount $ROOT/var/cache/apt/archives; fi
	echo
	echo -e "$red>>> EXITED CHROOT SYSTEM$off"
	echo
	sleep 2
	rm -f $ROOT/$FILE
}

create_livefs() {
	#
	# Prepare to create new image
	#
	echo -e "$yel* Preparing $ARCH image...$off"
	rm -f $ROOT/root/.bash_history
	rm -rf $LIVE
	mkdir -p $LIVE

	# Apply changes from overlay
	echo -e "$yel* Applying changes from overlay...$off"
	rsync -h --info=progress2 --archive \
		./overlay/rootdir/ \
		./$ROOT/

	# Fix permissions
	$PERS chroot $ROOT/ /bin/bash -c "chown -R root: /etc /root"
	$PERS chroot $ROOT/ /bin/bash -c "chown -R www-data: /var/www/html"
	$PERS chroot $ROOT/ /bin/bash -c "chown root: /usr/local/bin/backtrail-app /usr/share/icons/hicolor/*/apps/backtrail.*"

	# List the app icon in the icon theme cache
	$PERS chroot $ROOT/ /bin/bash -c "gtk-update-icon-cache -q -f /usr/share/icons/hicolor"

	# Enable startup of Redo monitor service
	$PERS chroot $ROOT/ /bin/bash -c "chmod 644 /etc/systemd/system/redo.service"
	$PERS chroot $ROOT/ /bin/bash -c "systemctl enable redo"

	# Update version number
	echo $VER > $ROOT/var/www/html/VERSION

	# List installed packages and their source packages (empty when named
	# like the package), so the matching Debian sources can be found
	$PERS chroot $ROOT/ dpkg-query -W \
		-f='${binary:Package}\t${Version}\t${Source}\n' > $LIVE/filesystem.packages

	# Copy kernel and initial ramdisk
	cp $ROOT/boot/vmlinuz* $LIVE/vmlinuz
	cp $ROOT/boot/initrd* $LIVE/initrd

	# Compress live filesystem
	echo -e "$yel* Compressing live filesystem...$off"
	# zstd: smaller than gzip and twice as fast to decompress (-e must be last)
	mksquashfs $ROOT/ $LIVE/filesystem.squashfs -comp zstd -e boot
}

create_iso() {
	#
	# Create hybrid BIOS/UEFI ISO image containing every target's live system
	#
	for T in $TARGETS; do
		if [ ! -s "image/live-${T%%:*}/filesystem.squashfs" ]; then
			echo -e "$red* ERROR: The ${T%%:*} squashfs live filesystem is missing.$off\n"
			exit 1
		fi
	done

	# Apply image changes from overlay (drop boot assets left by older builds)
	echo -e "$yel* Applying image changes from overlay...$off"
	rm -rf image/isolinux image/boot/grub/theme image/boot/grub/fonts
	rsync -h --info=progress2 --archive \
		./overlay/image/* \
		./image/

	# Update version number
	perl -p -i -e "s/\\\$VERSION/$VER/g" image/boot/grub/grub.cfg

	# Prepare boot image
	touch image/REDO
	mkdir -p {image/EFI/{boot,debian},image/boot/grub/{fonts,theme},scratch}
	cp /usr/share/grub/ascii.pf2 image/boot/grub/fonts/
	cp /usr/lib/shim/shimx64.efi.signed image/EFI/boot/bootx64.efi
	cp /usr/lib/grub/x86_64-efi-signed/grubx64.efi.signed image/EFI/boot/grubx64.efi
	cp -r /usr/lib/grub/x86_64-efi image/boot/grub/

	# Create EFI partition
	UFAT="scratch/efiboot.img"
	dd if=/dev/zero of=$UFAT bs=1M count=4
	mkfs.vfat $UFAT
	mcopy -s -i $UFAT image/EFI ::

	# Create image for BIOS and CD-ROM ("cpuid" detects 64-bit CPUs)
	grub-mkstandalone \
		--format=i386-pc \
		--output=scratch/core.img \
		--install-modules="linux normal iso9660 biosdisk memdisk search help tar ls all_video font gfxmenu png cpuid test" \
		--modules="linux normal iso9660 biosdisk search help all_video font gfxmenu png cpuid test" \
		--locales="" \
		--fonts="" \
		"boot/grub/grub.cfg=image/boot/grub/grub.cfg"

	# Prepare image for UEFI
	cat /usr/lib/grub/i386-pc/cdboot.img scratch/core.img > scratch/bios.img

	# Create final ISO image
	xorriso \
		-as mkisofs \
		-iso-level 3 \
		-full-iso9660-filenames \
		-joliet-long \
		-volid "Backtrail $VER" \
		-eltorito-boot \
			boot/grub/bios.img \
			-no-emul-boot \
			-boot-load-size 4 \
			-boot-info-table \
			--eltorito-catalog boot/grub/boot.cat \
		--grub2-boot-info \
		--grub2-mbr /usr/lib/grub/i386-pc/boot_hybrid.img \
		-eltorito-alt-boot \
			-e EFI/efiboot.img \
			-no-emul-boot \
		-append_partition 2 0xef scratch/efiboot.img \
		-output backtrail-$VER.iso \
		-graft-points \
			image \
			/boot/grub/bios.img=scratch/bios.img \
			/EFI/efiboot.img=scratch/efiboot.img

	# Remove scratch directory
	rm -rf scratch

	# Report final ISO size
	echo -e "$yel\nISO image saved:"
	du -sh backtrail-$VER.iso
	echo -e "$off"
	echo
	echo "Done."
	echo
}


#
# Execute functions based on the requested action
#

if [ "$ACTION" == "clean" ]; then
	# Clean all build files
	clean
fi

if [ "$ACTION" == "" ]; then
	# Build new ISO image with a live system for every target
	rm -rf image scratch backtrail-$VER.iso
	for T in $TARGETS; do
		set_target $T
		prepare
		script_init
		script_build
		if [ "$NONFREE" = true ]; then
			echo -e "$yel* Including non-free packages...$off"
			script_add_nonfree
		else
			echo -e "$yel* Excluding non-free packages.$off"
		fi
		script_exit
		chroot_exec
		create_livefs
	done
	create_iso
fi

if [ "$ACTION" == "changes" ]; then
	# Enter existing system(s) to make changes; optionally name one
	# architecture, e.g. "./make changes i386"
	if [ -n "$2" ] && [[ " $TARGETS" != *" $2:"* ]]; then
		echo -e "$red* ERROR: Unknown architecture '$2' (targets: $TARGETS).$off\n"
		exit 1
	fi
	echo -e "$yel* Updating existing image.$off"
	for T in $TARGETS; do
		if [ -n "$2" ] && [ "${T%%:*}" != "$2" ]; then continue; fi
		set_target $T
		if [ ! -d "$ROOT" ]; then
			echo -e "$red* ERROR: $ROOT does not exist; run a full build first.$off\n"
			exit 1
		fi
		script_init
		script_shell
		script_exit
		chroot_exec
		create_livefs
	done
	create_iso
fi

if [ "$ACTION" == "boot" ]; then
	# Rebuild existing ISO image (update bootloader)
	create_iso
fi
