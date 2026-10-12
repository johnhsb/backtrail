#!/bin/bash
#
# Check the app's nginx site as make writes it: other host names, and
# requests to action.php or ajax/ without the X-Requested-With header of the
# app's own calls, must be refused.
#
#     tests/nginx/run.sh
#
# Runs a separate nginx (no root needed) on port 8098 with the site taken
# from make. There is no PHP behind it, so a request that nginx lets through
# gets 502 and a refused one 403. Needs nginx and curl.
#
set -eu

HERE=$(cd "$(dirname "$0")/../.." && pwd)
PORT=${PORT:-8098}
TMP=$(mktemp -d)
trap 'kill "$(cat "$TMP/nginx.pid" 2>/dev/null)" 2>/dev/null || true; rm -rf "$TMP"' EXIT

NGINX=$(command -v nginx || echo /usr/sbin/nginx)
[ -x "$NGINX" ] || { echo "nginx is not installed" >&2; exit 1; }

# The site is in an unquoted here-document in make: \$ stands for $
awk '/^cat > \/etc\/nginx\/sites-available\/redo/ { f = 1; next } f && /^END$/ { exit } f' "$HERE/make" \
	| sed -e 's/\\\$/$/g' -e 's/\$PHPV/0/g' -e "s/127\.0\.0\.1:80 /127.0.0.1:$PORT /" > "$TMP/site.conf"
grep -q "listen.*:$PORT" "$TMP/site.conf" || { echo "site not found in make" >&2; exit 1; }
cp /etc/nginx/fastcgi_params "$TMP/"
cat > "$TMP/nginx.conf" <<END
pid $TMP/nginx.pid;
error_log $TMP/error.log;
events {}
http {
	access_log off;
	client_body_temp_path $TMP/body;
	fastcgi_temp_path $TMP/fastcgi;
	proxy_temp_path $TMP/proxy;
	uwsgi_temp_path $TMP/uwsgi;
	scgi_temp_path $TMP/scgi;
	include $TMP/site.conf;
}
END
"$NGINX" -p "$TMP" -c "$TMP/nginx.conf" -t -q
"$NGINX" -p "$TMP" -c "$TMP/nginx.conf"

failed=0
check() {
	local want=$1; shift
	local got
	got=$(curl -s -o /dev/null -w '%{http_code}' --path-as-is "$@")
	if [ "$got" = "$want" ]; then echo "ok   $want  $*"; else echo "FAIL $got (want $want)  $*"; failed=1; fi
}
URL=http://127.0.0.1:$PORT
XHR=(-H 'X-Requested-With: XMLHttpRequest')

check 502 "$URL/index.php"
check 502 "$URL/index.php?page=welcome" -H 'Host: localhost'
check 403 "$URL/index.php" -H 'Host: evil.example'
check 403 "$URL/ajax/save-id.php" "${XHR[@]}" -H 'Host: rebind.example'
check 502 "$URL/ajax/save-id.php" "${XHR[@]}" -X POST
check 403 "$URL/ajax/save-id.php" -X POST
check 403 "$URL/ajax/execute-restore.php"
check 502 "$URL/action.php?page=welcome" "${XHR[@]}"
check 403 "$URL/action.php?page=welcome"
check 403 "$URL/AJAX/exit.php"
check 403 "$URL/%61jax/exit.php"
check 403 "$URL/./ajax/exit.php"
check 403 "$URL/ajax/save-id.php" -H 'X-Requested-With: fetch'

[ $failed = 0 ] && echo "nginx site: all checks passed"
exit $failed
