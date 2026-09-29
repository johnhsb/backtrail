# Backtrail brand assets

Backtrail replaces the original Redo Rescue logos and graphics, which are not
licensed for use by derived projects (see the License section of the README).

The mark shows two contour lines around a summit. The rings also read as the
tracks of a disk platter, and the dotted trail leads from the summit back to
the start: a backup marks a point you can return to, and a restore retraces
the way back.

## Files

| File | Use |
|---|---|
| `backtrail-logo.svg` | Mark and wordmark, for light backgrounds |
| `backtrail-logo-dark.svg` | Mark and wordmark, for the navy brand color and dark backgrounds |
| `backtrail-logo-white.svg` | Single-color version |
| `backtrail-mark*.svg` | Mark only, 32 px and larger (`-dark`, `-white` as above) |
| `backtrail-mark-small*.svg` | Simplified mark for 16–24 px (favicon, panel icons) |
| `backtrail-app-icon.svg` | Mark on a rounded navy tile, 32 px and larger |
| `backtrail-app-icon-small.svg` | Tile with the simplified mark, 16–24 px |
| `backtrail-social.png` | Repository social preview, 1280x640 (rendered by `src/render.py`) |

## Colors

| Name | Hex | Use |
|---|---|---|
| Navy | `#0E2A47` | Brand background, wordmark on light backgrounds |
| Contour | `#3CC4D9` | Inner ring and "trail" on dark backgrounds |
| Contour (on light) | `#1D8FA5` | Inner ring and "trail" on light backgrounds |
| Trail | `#FF8A5B` | Trail on dark backgrounds |
| Trail (on light) | `#E8683A` | Trail on light backgrounds |

The web app, boot menu, splash screen and desktop use the same palette:

| Name | Hex | Use |
|---|---|---|
| Contour ink | `#16798C` | Links, buttons and focus on light backgrounds (5:1 on white) |
| Deep navy | `#0A1826` | Dark mode background |
| Panel | `#10233A` | Dark mode cards, tint2 tooltips |
| Track | `#21405F` | Dark mode borders, GRUB countdown track |
| Danger | `#C62F3A` | Restore and other destructive actions |

## Typeface

Interface text uses **Pretendard** (SIL OFL 1.1, Debian package
`fonts-pretendard`), which covers Latin and all Hangul syllables. Noto Sans
CJK covers Chinese and Japanese, and `overlay/rootdir/etc/fonts/local.conf`
selects the regional Noto Sans CJK face for text tagged `ja` or `zh`.
Monospace text (device names, logs) uses DejaVu Sans Mono.

## Regenerating

All SVGs are generated from `src/build.py` so that they share one geometry.
It needs only the Python standard library:

    python3 branding/src/build.py branding

The wordmark is set in Sora SemiBold and stored as outlines in
`src/wordmark.json`, so the font is not needed to use or rebuild the logos.
To change the wordmark, download `Sora[wght].ttf` from Google Fonts and run
`src/wordmark.py` (requires `fonttools` and `uharfbuzz`):

    python3 branding/src/wordmark.py 'Sora[wght].ttf'

Sora is licensed under the SIL Open Font License 1.1.

### Theme images

`src/render.py` renders the PNG files used outside the web app from the same
geometry: the GRUB theme (background, logo, selection boxes, countdown ring
and menu icons), the Plymouth `backtrail` splash, the desktop wallpapers and
app icon, the web app favicon, and the repository social preview
(`backtrail-social.png`). There is one wallpaper per screen shape
(`background-16x9.png`, `-16x10`, `-4x3`, `-5x4`, `-21x9`); the Openbox
autostart picks the closest one, and `background.png` is the fallback. It needs `cairosvg`:

    python3 branding/src/render.py

`src/grub-fonts.sh` builds the GRUB bitmap fonts (`pretendard-*.pf2`) from
Pretendard. It needs `grub-mkfont` from `grub-common`:

    branding/src/grub-fonts.sh /usr/share/fonts/opentype/pretendard

`src/flags.py` draws the flag icons of the web app language menu
(`images/flags/*.svg`) in one 3:2 size, simplified for 14–20 px:

    python3 branding/src/flags.py
