"""Render Backtrail PNG assets into the overlay (requires cairosvg).

    python3 branding/src/render.py

Writes the GRUB theme, Plymouth theme, desktop wallpaper and icons, and the
web app favicon and logos. The vector sources are in build.py.
"""
import math
import os
import shutil
import sys

import cairosvg

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import build  # noqa: E402

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", ".."))
GRUB = os.path.join(ROOT, "overlay/image/boot/grub/theme")
PLYMOUTH = os.path.join(ROOT, "overlay/rootdir/usr/share/plymouth/themes/backtrail")
HOME = os.path.join(ROOT, "overlay/rootdir/root/.local/share")
WEB = os.path.join(ROOT, "overlay/rootdir/var/www/html")

NAVY = "#0E2A47"
NAVY_LIGHT = "#12345A"
NAVY_DEEP = "#0A1F36"
CONTOUR = "#3CC4D9"
TRACK = "#21405F"
PANEL = "#10233A"
TEXT = "#E4EEF4"


def png(svg_text, path, width=None, height=None):
    os.makedirs(os.path.dirname(path), exist_ok=True)
    cairosvg.svg2png(bytestring=svg_text.encode(), write_to=path,
                     output_width=width, output_height=height)
    print(f"{os.path.relpath(path, ROOT):70s} {os.path.getsize(path):8d} bytes")


def doc(w, h, body):
    return f'<svg xmlns="http://www.w3.org/2000/svg" width="{w}" height="{h}" viewBox="0 0 {w} {h}">{body}</svg>'


def smooth_closed(points):
    """Closed Catmull-Rom spline through points, as an SVG path."""
    n = len(points)
    d = f"M{points[0][0]:.1f} {points[0][1]:.1f}"
    for i in range(n):
        p0, p1, p2, p3 = points[i - 1], points[i], points[(i + 1) % n], points[(i + 2) % n]
        c1 = (p1[0] + (p2[0] - p0[0]) / 6, p1[1] + (p2[1] - p0[1]) / 6)
        c2 = (p2[0] - (p3[0] - p1[0]) / 6, p2[1] - (p3[1] - p1[1]) / 6)
        d += f" C{c1[0]:.1f} {c1[1]:.1f} {c2[0]:.1f} {c2[1]:.1f} {p2[0]:.1f} {p2[1]:.1f}"
    return d + " Z"


def contours(cx, cy, rings, step, start=70):
    """Topographic rings around a summit, as used on the boot and desktop backgrounds."""
    out = []
    for k in range(rings):
        r0 = start + k * step
        pts = []
        for a in range(0, 360, 12):
            t = math.radians(a)
            r = r0 * (1 + 0.09 * math.sin(2 * t + 0.5 + 0.12 * k)
                      + 0.05 * math.sin(3 * t + 1.3 - 0.08 * k)
                      + 0.025 * math.sin(5 * t + 0.6 * k))
            pts.append((cx + r * math.cos(t), cy + r * math.sin(t) * 0.86))
        opacity = max(0.035, 0.16 - k * 0.0065)
        out.append(f'<path d="{smooth_closed(pts)}" fill="none" stroke="{CONTOUR}" '
                   f'stroke-width="2" opacity="{opacity:.3f}"/>')
    return "".join(out)


# Desktop wallpapers, one per screen shape; the session picks the closest
# (overlay/rootdir/root/.config/openbox/autostart). background.png is the
# fallback, with the logo inside the part that stays visible on any shape.
WALLPAPERS = {"16x9": (1920, 1080), "16x10": (1920, 1200), "4x3": (1600, 1200),
              "5x4": (1280, 1024), "21x9": (2560, 1080)}


def background(w, h, with_logo, safe=False):
    body = (
        '<defs><radialGradient id="g" cx="0.2" cy="0" r="1.1">'
        f'<stop offset="0" stop-color="{NAVY_LIGHT}"/><stop offset="0.6" stop-color="{NAVY}"/>'
        f'<stop offset="1" stop-color="{NAVY_DEEP}"/></radialGradient></defs>'
        f'<rect width="{w}" height="{h}" fill="url(#g)"/>'
        + contours(w * 0.77, h * 0.39, 22, h * 0.046)
        + f'<circle cx="{w * 0.77:.1f}" cy="{h * 0.39:.1f}" r="{h * 0.012:.1f}" fill="{CONTOUR}" opacity=".5"/>'
    )
    if with_logo:
        # small lockup above the bottom panel, bottom right; sizes are for a
        # 1080-pixel-high image and scale with the height
        k = h / 1080
        lock = build.lockup("dark", 56)
        lw = float(lock.split('viewBox="0 0 ')[1].split(" ")[0])
        inner = lock.split(">", 1)[1].rsplit("</svg>", 1)[0].split("</title>", 1)[1]
        # The fallback image is filled onto screens of any shape, which crops
        # its sides; on 5:4, the narrowest common shape, only x = 15%..85%
        # stays visible, so there the lockup ends at 82% of the width
        right = w * 0.82 if safe else w - 64 * k
        body += (f'<svg x="{right - lw * k:.1f}" y="{h - (104 + 56) * k:.1f}" width="{lw * k:.1f}" '
                 f'height="{56 * k:.1f}" viewBox="0 0 {lw:.2f} 64" opacity=".92">{inner}</svg>')
    return doc(w, h, body)


def rounded_slices(prefix, radius, fill, stroke=None, stroke_w=0, opacity=1):
    """Nine-slice pixmaps (GRUB styled boxes): nw n ne w c e sw s se."""
    size = radius * 2 + 1
    rect = (f'<rect x="{stroke_w / 2}" y="{stroke_w / 2}" width="{size - stroke_w}" height="{size - stroke_w}" '
            f'rx="{radius - stroke_w / 2}" fill="{fill}" fill-opacity="{opacity}"'
            + (f' stroke="{stroke}" stroke-width="{stroke_w}"' if stroke else "") + "/>")
    r = radius
    parts = {"nw": (0, 0, r, r), "n": (r, 0, 1, r), "ne": (r + 1, 0, r, r),
             "w": (0, r, r, 1), "c": (r, r, 1, 1), "e": (r + 1, r, r, 1),
             "sw": (0, r + 1, r, r), "s": (r, r + 1, 1, r), "se": (r + 1, r + 1, r, r)}
    for name, (x, y, w, h) in parts.items():
        svg = (f'<svg xmlns="http://www.w3.org/2000/svg" width="{w}" height="{h}" '
               f'viewBox="{x} {y} {w} {h}">{rect}</svg>')
        png(svg, os.path.join(GRUB, f"{prefix}_{name}.png"))


def line_icon(paths, size=32, color="#C3D4E0"):
    return doc(size, size, f'<g transform="scale({size / 24})" fill="none" stroke="{color}" stroke-width="1.8" '
                           f'stroke-linecap="round" stroke-linejoin="round">{paths}</g>')


def grub_theme():
    png(background(1920, 1080, with_logo=False), os.path.join(GRUB, "background.png"))
    png(build.lockup("dark", 64), os.path.join(GRUB, "logo.png"), height=72)
    rounded_slices("select", 10, CONTOUR)
    rounded_slices("terminal", 12, PANEL, stroke="#1F6F86", stroke_w=2, opacity=0.97)
    icons = os.path.join(GRUB, "icons")
    # on a navy tile so it stays visible on the cyan selection bar
    png(build.app_icon(small=True), os.path.join(icons, "backtrail.png"), 32, 32)
    screen = '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/>'
    # countdown: ticks disappear around a track ring, seconds shown inside
    png(doc(56, 56, f'<circle cx="28" cy="28" r="23" fill="none" stroke="{TRACK}" stroke-width="3"/>'),
        os.path.join(GRUB, "timeout_ring.png"))
    png(doc(7, 7, f'<circle cx="3.5" cy="3.5" r="3.2" fill="{CONTOUR}"/>'), os.path.join(GRUB, "timeout_tick.png"))
    png(line_icon(screen), os.path.join(icons, "screen.png"))


def plymouth_theme():
    white = build.PALETTES["white"]
    png(build.svg(64, 64, build.mark(white, mono=True), "Backtrail"), os.path.join(PLYMOUTH, "logo.png"), 144, 144)
    wm = build.WM
    x0, y0, x1, y1 = wm["bounds"]
    wordmark = (f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="{x0} {y0} {x1 - x0} {y1 - y0}">'
                f'<path d="{wm["Back"]}" fill="#fff"/><path d="{wm["trail"]}" fill="#fff" fill-opacity=".85"/></svg>')
    png(wordmark, os.path.join(PLYMOUTH, "wordmark.png"), height=40)
    png(doc(240, 6, f'<rect width="240" height="6" rx="3" fill="{TRACK}"/>'), os.path.join(PLYMOUTH, "progress_box.png"))
    png(doc(240, 6, f'<rect width="240" height="6" rx="3" fill="{CONTOUR}"/>'), os.path.join(PLYMOUTH, "progress_bar.png"))
    png(doc(360, 96, f'<rect x="1" y="1" width="358" height="94" rx="14" fill="{PANEL}" stroke="#1F6F86" stroke-width="2"/>'),
        os.path.join(PLYMOUTH, "box.png"))
    png(doc(240, 40, '<rect width="240" height="40" rx="8" fill="#ffffff"/>'), os.path.join(PLYMOUTH, "entry.png"))
    lock = ('<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>')
    png(line_icon(lock, 40, TEXT), os.path.join(PLYMOUTH, "lock.png"))
    png(doc(14, 14, f'<circle cx="7" cy="7" r="5" fill="{NAVY}"/>'), os.path.join(PLYMOUTH, "bullet.png"))


def desktop():
    png(background(1920, 1080, with_logo=True, safe=True), os.path.join(HOME, "backgrounds/background.png"))
    for name, (w, h) in WALLPAPERS.items():
        png(background(w, h, with_logo=True), os.path.join(HOME, f"backgrounds/background-{name}.png"))
    icon = build.app_icon()
    os.makedirs(os.path.join(HOME, "icons"), exist_ok=True)
    with open(os.path.join(HOME, "icons/backtrail.svg"), "w") as f:
        f.write(icon)
    png(icon, os.path.join(HOME, "icons/backtrail.png"), 48, 48)


def web():
    png(build.app_icon(small=True), os.path.join(WEB, "favicon.png"), 32, 32)
    for name in ("backtrail-logo-dark.svg", "backtrail-mark-dark.svg"):
        shutil.copy(os.path.join(ROOT, "branding", name), os.path.join(WEB, "images", name))
        print(f"{os.path.relpath(os.path.join(WEB, 'images', name), ROOT):70s} (copied)")


if __name__ == "__main__":
    grub_theme()
    plymouth_theme()
    desktop()
    web()
