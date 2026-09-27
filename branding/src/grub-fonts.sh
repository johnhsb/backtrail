#!/bin/bash
#
# Build the GRUB bitmap fonts used by the boot menu theme from Pretendard.
# Requires grub-mkfont (grub-common) and the Pretendard OTF files
# (Debian package fonts-pretendard).
#
#   branding/src/grub-fonts.sh [/usr/share/fonts/opentype/pretendard]
#
set -e
SRC=${1:-/usr/share/fonts/opentype/pretendard}
OUT=$(dirname "$0")/../../overlay/image/boot/grub/fonts
# Basic Latin and Latin-1/Extended-A: menu text and the language menu entries
RANGE=0x20-0x7E,0xA0-0x17F

grub-mkfont -r $RANGE -s 16 -o "$OUT/pretendard-regular-16.pf2" "$SRC/Pretendard-Regular.otf"
grub-mkfont -n "Pretendard SemiBold" -r $RANGE -s 22 -o "$OUT/pretendard-semibold-22.pf2" "$SRC/Pretendard-SemiBold.otf"
ls -l "$OUT"/pretendard-*.pf2
