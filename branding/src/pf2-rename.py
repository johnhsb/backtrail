#!/usr/bin/env python3
"""Rename a GRUB PF2 font, keeping its glyphs unchanged.

    branding/src/pf2-rename.py IN.pf2 OUT.pf2 FAMILY

Pretendard's license (SIL OFL 1.1) reserves the name "Pretendard", and a
converted or subset font is a Modified Version that may not use it. The boot
menu fonts are subsets of Pretendard converted by grub-mkfont, so
grub-fonts.sh gives them a family name of their own with this script.

Replaces the FAMI section with FAMILY and the family at the start of the
NAME section (the name GRUB themes refer to, e.g. "FAMILY Regular 16"), and
moves the glyph offsets in CHIX by the change in header size.
"""
import struct
import sys


def sections(data):
    i = 0
    while i < len(data):
        tag, size = data[i:i + 4], struct.unpack(">I", data[i + 4:i + 8])[0]
        if tag == b"DATA":
            # the last section; its size field is not used
            yield tag, data[i + 8:], i
            return
        yield tag, data[i + 8:i + 8 + size], i
        i += 8 + size


def rename(data, family):
    parts = dict((tag, body) for tag, body, _ in sections(data))
    old_family = parts[b"FAMI"].rstrip(b"\0").decode()
    old_name = parts[b"NAME"].rstrip(b"\0").decode()
    if not old_name.startswith(old_family):
        sys.exit(f"font name {old_name!r} does not start with its family {old_family!r}")
    new = {b"FAMI": family.encode() + b"\0",
           b"NAME": (family + old_name[len(old_family):]).encode() + b"\0"}
    delta = sum(len(new[t]) - len(parts[t]) for t in new)

    out = bytearray()
    for tag, body, pos in sections(data):
        if tag in new:
            body = new[tag]
        elif tag == b"CHIX":
            # entries: code point (4), storage flags (1), absolute offset (4)
            body = bytearray(body)
            for e in range(0, len(body), 9):
                offset = struct.unpack(">I", body[e + 5:e + 9])[0]
                body[e + 5:e + 9] = struct.pack(">I", offset + delta)
        if tag == b"DATA":
            out += tag + data[pos + 4:pos + 8] + body
        else:
            out += tag + struct.pack(">I", len(body)) + body
    return bytes(out), old_name, new[b"NAME"].rstrip(b"\0").decode()


if __name__ == "__main__":
    if len(sys.argv) != 4:
        sys.exit(__doc__.strip().splitlines()[2].strip())
    src, dst, family = sys.argv[1:]
    data, old, new = rename(open(src, "rb").read(), family)
    with open(dst, "wb") as f:
        f.write(data)
    print(f"{dst}: {old!r} -> {new!r}")
