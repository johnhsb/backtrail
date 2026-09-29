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

# Pretendard's license reserves its name for the original fonts, so the
# converted subsets are renamed "Backtrail Sans" (see pf2-rename.py)
TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT
grub-mkfont -r $RANGE -s 16 -o "$TMP/regular.pf2" "$SRC/Pretendard-Regular.otf"
grub-mkfont -n "Pretendard SemiBold" -r $RANGE -s 22 -o "$TMP/semibold.pf2" "$SRC/Pretendard-SemiBold.otf"
python3 "$(dirname "$0")/pf2-rename.py" "$TMP/regular.pf2" "$OUT/backtrail-sans-regular-16.pf2" "Backtrail Sans"
python3 "$(dirname "$0")/pf2-rename.py" "$TMP/semibold.pf2" "$OUT/backtrail-sans-semibold-22.pf2" "Backtrail Sans SemiBold"
ls -l "$OUT"/backtrail-sans-*.pf2
