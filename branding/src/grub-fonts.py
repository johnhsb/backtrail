#!/usr/bin/env python3
"""Build the GRUB bitmap fonts used by the boot menu theme.

    python3 branding/src/grub-fonts.py NotoSansCJK-Regular.ttc NotoSansCJK-Medium.ttc

The fonts are subsets of Noto Sans CJK (SIL OFL 1.1; the files from
https://github.com/notofonts/noto-cjk, Sans/OTC). They are written in
GRUB's PF2 format the same way grub-mkfont does it (monochrome FreeType
rendering at the pixel size, empty rows and columns cut, glyphs sorted by
code point), so grub-mkfont is not needed. Requires freetype-py.

The fonts are named "Backtrail Sans", and the theme refers to them by that
name, so it does not depend on the source font's name. Noto Sans CJK's
license reserves no font name; it is copied next to the fonts with Adobe's
copyright notice, as the license requires.
"""
import os
import struct
import sys

import freetype

OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "../../overlay/image/boot/grub/fonts")
# Basic Latin and Latin-1: the boot menu text is English (Noto Sans CJK has
# only some of Latin Extended-A)
RANGES = [(0x20, 0x7E), (0xA0, 0xFF)]
# Face 1 of the Noto Sans CJK collections is the Korean one; its Latin
# glyphs are the same in every face
FACE = 1
FONTS = [
    # (source argument, size in pixels, family, output file)
    (0, 16, "Backtrail Sans", "backtrail-sans-regular-16.pf2"),
    (1, 22, "Backtrail Sans Medium", "backtrail-sans-medium-22.pf2"),
]


def glyph(face, code):
    """One glyph as grub-mkfont's add_glyph() stores it, or None."""
    index = face.get_char_index(code)
    if not index:
        return None
    face.load_glyph(index, freetype.FT_LOAD_RENDER | freetype.FT_LOAD_MONOCHROME)
    slot = face.glyph
    bm = slot.bitmap
    rows, width, pitch, buf = bm.rows, bm.width, bm.pitch, bm.buffer

    def px(x, y):
        return buf[x // 8 + y * pitch] & (1 << (7 - (x & 7)))

    def row_empty(y):
        return not any(px(x, y) for x in range(width))

    def col_empty(x):
        return not any(px(x, y) for y in range(rows))

    top = next((y for y in range(rows) if not row_empty(y)), rows)
    bottom = next((rows - 1 - y for y in range(rows - 1, -1, -1) if not row_empty(y)), rows)
    if top + bottom >= rows:
        bottom = 0
    left = next((x for x in range(width) if not col_empty(x)), width)
    right = next((width - 1 - x for x in range(width - 1, -1, -1) if not col_empty(x)), width)
    if left + right >= width:
        right = 0

    w, h = width - left - right, rows - top - bottom
    bits = bytearray((w * h + 7) // 8)
    n = 0
    for y in range(top, top + h):
        for x in range(left, left + w):
            if px(x, y):
                bits[n // 8] |= 0x80 >> (n % 8)
            n += 1
    return dict(code=code, w=w, h=h, x=slot.bitmap_left + left,
                y=slot.bitmap_top - h - top, dw=slot.metrics.horiAdvance // 64, bits=bytes(bits))


def pf2(path, size, family, face_index=FACE):
    face = freetype.Face(path, index=face_index)
    face.set_pixel_sizes(size, size)
    glyphs = [g for lo, hi in RANGES for c in range(lo, hi + 1) if (g := glyph(face, c))]
    glyphs.sort(key=lambda g: g["code"])

    max_w = max_h = min_y = max_y = 0
    for g in glyphs:
        max_w, max_h = max(max_w, g["w"]), max(max_h, g["h"])
        if min_y > g["y"] > -size:
            min_y = g["y"]
        max_y = max(max_y, g["y"] + g["h"])
    bold = face.style_flags & freetype.FT_STYLE_FLAG_BOLD
    italic = face.style_flags & freetype.FT_STYLE_FLAG_ITALIC
    style = " ".join(s for s, on in (("Bold", bold), ("Italic", italic)) if on) or "Regular"

    def string(tag, text):
        data = text.encode() + b"\0"
        return tag + struct.pack(">I", len(data)) + data

    def be16(tag, value):
        return tag + struct.pack(">IH", 2, value)

    head = (b"FILE" + struct.pack(">I", 4) + b"PFF2"
            + string(b"NAME", f"{family} {style} {size}")
            + string(b"FAMI", family)
            + string(b"WEIG", "bold" if bold else "normal")
            + string(b"SLAN", "italic" if italic else "normal")
            + be16(b"PTSZ", size) + be16(b"MAXW", max_w) + be16(b"MAXH", max_h)
            + be16(b"ASCE", max_y if max_y > 0 else 1)
            + be16(b"DESC", -min_y if min_y < 0 else 1))
    offset = len(head) + 8 + len(glyphs) * 9 + 8
    index, data = bytearray(), bytearray()
    for g in glyphs:
        index += struct.pack(">IBI", g["code"], 0, offset + len(data))
        data += struct.pack(">HHhhh", g["w"], g["h"], g["x"], g["y"], g["dw"]) + g["bits"]
    return (head + b"CHIX" + struct.pack(">I", len(index)) + bytes(index)
            + b"DATA" + b"\xff\xff\xff\xff" + bytes(data))


if __name__ == "__main__":
    if len(sys.argv) != 1 + len(FONTS):
        sys.exit("usage: grub-fonts.py NotoSansCJK-Regular.ttc NotoSansCJK-Medium.ttc")
    for arg, size, family, name in FONTS:
        out = os.path.normpath(os.path.join(OUT, name))
        with open(out, "wb") as f:
            f.write(pf2(sys.argv[1 + arg], size, family))
        print(f"{out}: {family} {size}px, {os.path.getsize(out)} bytes")
