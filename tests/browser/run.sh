#!/bin/bash
#
# Check that pages in backtrail-browser cannot reach this machine through its
# loopback addresses, while other addresses work.
#
#     xvfb-run -a tests/browser/run.sh
#
# Serves a probe on port 8097 and opens a page that asks for one image from
# a non-loopback address of this machine and others from 127.0.0.1 and
# localhost. Only the first request may arrive. Needs a display (or
# xvfb-run), python3-gi and gir1.2-webkit2-4.1.
#
set -eu

HERE=$(cd "$(dirname "$0")/../.." && pwd)
PORT=${PORT:-8097}
TMP=$(mktemp -d)
LOG=$TMP/requests.log
trap 'kill $SERVER 2>/dev/null || true; rm -rf "$TMP"' EXIT

IP=$(hostname -I | awk '{print $1}')
[ -n "$IP" ] || { echo "no non-loopback address to test with" >&2; exit 1; }

python3 -m http.server "$PORT" --bind 0.0.0.0 --directory "$TMP" 2> "$LOG" &
SERVER=$!
sleep 1

PAGE="data:text/html,<p>probe</p><img src=http://$IP:$PORT/allowed><img src=http://127.0.0.1:$PORT/blocked-ip><img src=http://localhost:$PORT/blocked-name>"
XDG_CACHE_HOME=$TMP/cache timeout 30 python3 "$HERE/overlay/rootdir/usr/local/bin/backtrail-browser" "$PAGE" \
	|| [ $? = 124 ] || { echo "the browser exited with an error" >&2; exit 1; }

echo "Requests received:"; grep -o 'GET /[^ ]*' "$LOG" || true
grep -q 'GET /allowed' "$LOG" || { echo "FAIL: the page did not load (no request to $IP)" >&2; exit 1; }
if grep -q 'GET /blocked' "$LOG"; then
	echo "FAIL: the page reached a loopback address" >&2
	exit 1
fi
echo "browser: loopback addresses are blocked"
