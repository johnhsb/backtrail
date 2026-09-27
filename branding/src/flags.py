"""Generate the small flag icons for the web app language menu.

    python3 branding/src/flags.py

Every flag is drawn in a 3:2 box (60x40) so the menu lines up; shapes are
simplified for 14-20 px display (for example, the Spanish civil flag
without the coat of arms). Standard library only.
"""
import math
import os

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", ".."))
OUT = os.path.join(ROOT, "overlay/rootdir/var/www/html/images/flags")
W, H = 60, 40


def svg(body, title):
    return (f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {W} {H}" width="{W}" height="{H}">'
            f'<title>{title}</title>{body}</svg>\n')


def stripes(colors, vertical=False):
    n = len(colors)
    out = []
    for i, c in enumerate(colors):
        if vertical:
            out.append(f'<rect x="{W * i / n:g}" width="{W / n + .01:g}" height="{H}" fill="{c}"/>')
        else:
            out.append(f'<rect y="{H * i / n:g}" width="{W}" height="{H / n + .01:g}" fill="{c}"/>')
    return "".join(out)


def star(cx, cy, r, rot=0.0, fill="#FFDE00"):
    """Five-pointed star; rot turns the top point clockwise (radians)."""
    pts = []
    for k in range(10):
        rad = r if k % 2 == 0 else r * 0.382
        a = rot - math.pi / 2 + k * math.pi / 5
        pts.append(f"{cx + rad * math.cos(a):.3f},{cy + rad * math.sin(a):.3f}")
    return f'<polygon points="{" ".join(pts)}" fill="{fill}"/>'


def us():
    body = stripes(["#B22234" if i % 2 == 0 else "#FFFFFF" for i in range(13)])
    cw, ch = W * 0.4, H * 7 / 13
    body += f'<rect width="{cw:g}" height="{ch:.3f}" fill="#3C3B6E"/>'
    for row in range(9):
        cols = 6 if row % 2 == 0 else 5
        for j in range(cols):
            x = cw * ((j * 2 + 1) if cols == 6 else (j * 2 + 2)) / 12
            y = ch * (row + 1) / 10
            body += f'<circle cx="{x:.2f}" cy="{y:.2f}" r=".75" fill="#FFFFFF"/>'
    return svg(body, "United States")


def kr():
    # Taegeuk diameter is half the flag height; trigrams sit on the diagonals
    d = H / 2
    r = d / 2
    bar, gap = d / 12, d / 24
    angle = math.degrees(math.atan2(H, W))

    def trigram(pattern, direction):
        # pattern: inner to outer, True = solid bar
        out = []
        x = r + d / 4
        for solid in pattern:
            x0 = x * direction if direction > 0 else -x - bar
            if solid:
                out.append(f'<rect x="{x0:.3f}" y="{-d / 4:.3f}" width="{bar:.3f}" height="{d / 2:.3f}"/>')
            else:
                half = (d / 2 - gap) / 2
                out.append(f'<rect x="{x0:.3f}" y="{-d / 4:.3f}" width="{bar:.3f}" height="{half:.3f}"/>')
                out.append(f'<rect x="{x0:.3f}" y="{gap / 2:.3f}" width="{bar:.3f}" height="{half:.3f}"/>')
            x += bar + gap
        return "".join(out)

    body = f'<rect width="{W}" height="{H}" fill="#FFFFFF"/>'
    body += f'<g transform="translate({W / 2:g} {H / 2:g})">'
    # Geon (upper left) and gon (lower right) on the falling diagonal
    body += f'<g transform="rotate({angle:.3f})" fill="#000000">'
    body += trigram([True, True, True], -1) + trigram([False, False, False], 1)
    body += (f'<circle r="{r:g}" fill="#0047A0"/>'
             f'<path d="M{-r:g} 0A{r:g} {r:g} 0 0 1 {r:g} 0A{r / 2:g} {r / 2:g} 0 0 0 0 0'
             f'A{r / 2:g} {r / 2:g} 0 0 1 {-r:g} 0Z" fill="#CD2E3A"/></g>')
    # Gam (upper right) and ri (lower left) on the rising diagonal
    body += f'<g transform="rotate({-angle:.3f})" fill="#000000">'
    body += trigram([False, True, False], 1) + trigram([True, False, True], -1)
    body += '</g></g>'
    return svg(body, "South Korea")


def jp():
    body = f'<rect width="{W}" height="{H}" fill="#FFFFFF"/><circle cx="{W / 2:g}" cy="{H / 2:g}" r="{H * 0.3:g}" fill="#BC002D"/>'
    return svg(body, "Japan")


def cn():
    u = W / 30  # the flag is laid out on a 30x20 grid
    body = f'<rect width="{W}" height="{H}" fill="#EE1C25"/>'
    body += star(5 * u, 5 * u, 3 * u)
    for x, y in [(10, 2), (12, 4), (12, 7), (10, 9)]:
        # each small star points at the centre of the large star
        rot = math.atan2(5 - y, 5 - x) + math.pi / 2
        body += star(x * u, y * u, u, rot)
    return svg(body, "China")


def es():
    body = (f'<rect width="{W}" height="{H}" fill="#AA151B"/>'
            f'<rect y="{H / 4:g}" width="{W}" height="{H / 2:g}" fill="#F1BF00"/>')
    return svg(body, "Spain")


def de():
    return svg(stripes(["#000000", "#DD0000", "#FFCE00"]), "Germany")


def fr():
    return svg(stripes(["#0055A4", "#FFFFFF", "#EF4135"], vertical=True), "France")


def br():
    m = W * 1.7 / 20
    body = (f'<rect width="{W}" height="{H}" fill="#009C3B"/>'
            f'<polygon points="{m:.2f},{H / 2:g} {W / 2:g},{m:.2f} {W - m:.2f},{H / 2:g} {W / 2:g},{H - m:.2f}" fill="#FFDF00"/>'
            f'<circle cx="{W / 2:g}" cy="{H / 2:g}" r="{H * 0.2125:.2f}" fill="#002776"/>'
            f'<path d="M{W / 2 - 8.2:.2f} {H / 2 - 1.6:.2f}Q{W / 2:g} {H / 2 - 4.2:.2f} {W / 2 + 8.4:.2f} {H / 2 + 1.6:.2f}" '
            f'fill="none" stroke="#FFFFFF" stroke-width="1.3"/>')
    return svg(body, "Brazil")


FLAGS = {"us": us, "kr": kr, "jp": jp, "cn": cn, "es": es, "de": de, "fr": fr, "br": br}


def main():
    os.makedirs(OUT, exist_ok=True)
    for name, draw in FLAGS.items():
        with open(os.path.join(OUT, name + ".svg"), "w") as f:
            f.write(draw())
    print(f"{len(FLAGS)} flags written to {OUT}")


if __name__ == "__main__":
    main()
