import json, io, os
from fontTools.ttLib import TTFont
from fontTools.varLib import instancer
from fontTools.pens.svgPathPen import SVGPathPen
from fontTools.pens.transformPen import TransformPen
from fontTools.pens.boundsPen import BoundsPen
import uharfbuzz as hb

import sys
font = TTFont(sys.argv[1] if len(sys.argv) > 1 else "Sora[wght].ttf")
inst = instancer.instantiateVariableFont(font, {"wght": 600})
buf = io.BytesIO(); inst.save(buf); data = buf.getvalue()
inst = TTFont(io.BytesIO(data))
upm = inst["head"].unitsPerEm
gs = inst.getGlyphSet()

face = hb.Face(data); hfont = hb.Font(face)
text = "Backtrail"
b = hb.Buffer(); b.add_str(text); b.guess_segment_properties()
hb.shape(hfont, b, {"kern": True, "liga": True})
order = inst.getGlyphOrder()
track = -0.02 * upm
x = 0; parts = {"Back": [], "trail": []}
bp = BoundsPen(gs)
for i, (info, pos) in enumerate(zip(b.glyph_infos, b.glyph_positions)):
    name = order[info.codepoint]
    key = "Back" if info.cluster < 4 else "trail"
    pen = SVGPathPen(gs)
    # flip Y (font units are y-up); baseline at y=0
    gs[name].draw(TransformPen(pen, (1, 0, 0, -1, x + pos.x_offset, -pos.y_offset)))
    gs[name].draw(TransformPen(bp, (1, 0, 0, -1, x + pos.x_offset, -pos.y_offset)))
    parts[key].append(pen.getCommands())
    x += pos.x_advance + (track if i < len(b.glyph_infos) - 1 else 0)
xmin, ymin, xmax, ymax = bp.bounds
out = {"upm": upm, "advance": x, "bounds": [xmin, ymin, xmax, ymax],
       "cap": inst["OS/2"].sCapHeight, "xh": inst["OS/2"].sxHeight,
       "Back": " ".join(parts["Back"]), "trail": " ".join(parts["trail"])}
json.dump(out, open(os.path.join(os.path.dirname(os.path.abspath(__file__)), "wordmark.json"), "w"))
print({k: (round(v, 1) if isinstance(v, float) else v) for k, v in out.items() if k not in ("Back", "trail")}, "| path chars:", len(out["Back"]), len(out["trail"]))
