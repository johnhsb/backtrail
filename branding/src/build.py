"""Generate the Backtrail logo SVG set (concept C, "Contour Platter")."""
import json
import os
import sys

WM = json.load(open(os.path.join(os.path.dirname(__file__), "wordmark.json")))

# Two contour rings (64x64 grid): slightly irregular like real contour lines,
# nested around the summit, which doubles as the disk spindle.
RING_OUTER = "M8 34 C6 18 20 5 36 5.5 C52 6 60 16 59 30 C58 46 46 58 30 58 C15 58 9 48 8 34 Z"
RING_INNER = "M19 32 C18 22.5 25.5 15.5 35.5 15.5 C45.5 15.5 50 22 49.2 30.2 C48.4 39.5 42 45 33 45 C24.5 45 19.5 40 19 32 Z"
SUMMIT = (35.2, 29.8)
TRAIL = "M32.5 33.5 C26 38.5 19 43 11.5 49"
ARROW = "8.4,51.4 16.98,50.18 11.52,43.32"

PALETTES = {
    # for light backgrounds
    "light": dict(outer="#93D1DD", mid="#5DB8CA", inner="#1D8FA5", summit="#0E2A47", trail="#E8683A",
                  back="#0E2A47", tail="#1D8FA5"),
    # for the navy brand background and other dark backgrounds
    "dark": dict(outer="#1F6F86", mid="#2C9BB2", inner="#3CC4D9", summit="#3CC4D9", trail="#FF8A5B",
                 back="#FFFFFF", tail="#3CC4D9"),
    # single colour, e.g. boot splash
    "white": dict(outer="#FFFFFF", mid="#FFFFFF", inner="#FFFFFF", summit="#FFFFFF", trail="#FFFFFF",
                  back="#FFFFFF", tail="#FFFFFF"),
}
NAVY = "#0E2A47"


def mark(p, mono=False):
    o = ' opacity=".5"' if mono else ""
    return (
        f'<path d="{RING_OUTER}" fill="none" stroke="{p["outer"]}" stroke-width="2.8"{o}/>'
        f'<path d="{RING_INNER}" fill="none" stroke="{p["inner"]}" stroke-width="2.8"/>'
        f'<circle cx="{SUMMIT[0]}" cy="{SUMMIT[1]}" r="4" fill="{p["summit"]}"/>'
        f'<path d="{TRAIL}" fill="none" stroke="{p["trail"]}" stroke-width="3.2" stroke-linecap="round" stroke-dasharray="0.1 5.4"/>'
        f'<polygon points="{ARROW}" fill="{p["trail"]}" stroke="{p["trail"]}" stroke-width="1.6" stroke-linejoin="round"/>'
    )


def mark_small(p, mono=False):
    # 16-24 px: same rings, heavier strokes, solid trail
    o = ' opacity=".55"' if mono else ""
    return (
        f'<path d="{RING_OUTER}" fill="none" stroke="{p["mid"]}" stroke-width="5"{o}/>'
        f'<path d="{RING_INNER}" fill="none" stroke="{p["inner"]}" stroke-width="5"/>'
        f'<circle cx="{SUMMIT[0]}" cy="{SUMMIT[1]}" r="5.2" fill="{p["summit"]}"/>'
        f'<path d="M31 35 C25 40 18 44.5 12.5 48.5" fill="none" stroke="{p["trail"]}" stroke-width="5" stroke-linecap="round"/>'
        f'<polyline points="19.6,50.6 9.5,50.6 12.4,41.2" fill="none" stroke="{p["trail"]}" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>'
    )


def svg(w, h, body, title):
    return (f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {w:g} {h:g}" width="{w:g}" height="{h:g}" role="img">'
            f'<title>{title}</title>{body}</svg>\n')


def wordmark(p, x, y, s):
    # glyph outlines are in font units, baseline at y=0, y pointing down
    return (f'<g transform="translate({x:.3f} {y:.3f}) scale({s:.5f})">'
            f'<path d="{WM["Back"]}" fill="{p["back"]}"/><path d="{WM["trail"]}" fill="{p["tail"]}"/></g>')


def lockup(pal, mark_px=64):
    p = PALETTES[pal]
    s = mark_px * 0.50 / WM["cap"]            # cap height = half the mark height
    gap = mark_px * 0.24
    x0 = mark_px + gap - WM["bounds"][0] * s
    baseline = mark_px / 2 + WM["cap"] * s / 2  # centre cap height on the mark
    w = x0 + WM["bounds"][2] * s
    body = f'<svg x="0" y="0" width="{mark_px}" height="{mark_px}" viewBox="0 0 64 64">{mark(p, mono=pal == "white")}</svg>' + wordmark(p, x0, baseline, s)
    return svg(round(w, 2), mark_px, body, "Backtrail")


def app_icon(small=False):
    p = PALETTES["dark"]
    inner = mark_small(p) if small else mark(p)
    body = (f'<rect width="64" height="64" rx="14" fill="{NAVY}"/>'
            f'<g transform="translate(6.4 6.4) scale(0.8)">{inner}</g>')
    return svg(64, 64, body, "Backtrail")


def files():
    return {
        "backtrail-mark.svg": svg(64, 64, mark(PALETTES["light"]), "Backtrail"),
        "backtrail-mark-dark.svg": svg(64, 64, mark(PALETTES["dark"]), "Backtrail"),
        "backtrail-mark-white.svg": svg(64, 64, mark(PALETTES["white"], mono=True), "Backtrail"),
        "backtrail-mark-small.svg": svg(64, 64, mark_small(PALETTES["light"]), "Backtrail"),
        "backtrail-mark-small-dark.svg": svg(64, 64, mark_small(PALETTES["dark"]), "Backtrail"),
        "backtrail-mark-small-white.svg": svg(64, 64, mark_small(PALETTES["white"], mono=True), "Backtrail"),
        "backtrail-logo.svg": lockup("light"),
        "backtrail-logo-dark.svg": lockup("dark"),
        "backtrail-logo-white.svg": lockup("white"),
        "backtrail-app-icon.svg": app_icon(),
        "backtrail-app-icon-small.svg": app_icon(small=True),
    }


def main(out):
    os.makedirs(out, exist_ok=True)
    for name, content in files().items():
        with open(os.path.join(out, name), "w") as f:
            f.write(content)
        print(f"{name:34s} {len(content):6d} bytes")


if __name__ == "__main__":
    main(sys.argv[1])
