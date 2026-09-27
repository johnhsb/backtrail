#!/usr/bin/env python3
"""Check the web app translations against the text used in the code.

    python3 tools/i18n-check.py

Lists, for every language in lang/languages.php, the strings that are
missing or no longer used, and translations whose %s / %1$s placeholders
differ from the English text. Needs the php command-line interpreter to
read the language files. Exits with status 1 if anything is missing or
wrong (unused entries are only reported).
"""
import glob
import json
import os
import re
import subprocess
import sys

WEB = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "overlay/rootdir/var/www/html")
WEB = os.path.normpath(WEB)

# Keys that reach t() through a variable (page_header() operation and step names)
INDIRECT = ["Back up", "Restore", "Verify", "Source", "Partitions", "Destination", "Folder", "Name",
            "Image", "Target", "Options"]

CALL = re.compile(r"""\bt\(\s*'((?:[^'\\]|\\.)*)'|\bt\(\s*"((?:[^"\\]|\\.)*)\"""")
PLACEHOLDER = re.compile(r"%(?:\d+\$)?s")


def code_keys():
    files = [f for f in glob.glob(WEB + "/**/*.php", recursive=True) if "/lang/" not in f]
    files += glob.glob(WEB + "/assets/backtrail/*.js")
    keys = set(INDIRECT)
    for f in files:
        with open(f, encoding="utf-8") as fh:
            for m in CALL.finditer(fh.read()):
                key = m.group(1) if m.group(1) is not None else m.group(2)
                keys.add(key.replace("\\'", "'").replace('\\"', '"'))
    return keys


def php_array(path):
    out = subprocess.run(["php", "-r", "echo json_encode(include $argv[1]);", path],
                         capture_output=True, text=True, check=True).stdout
    return json.loads(out)


def placeholders(text):
    found = PLACEHOLDER.findall(text)
    # Numbered placeholders may be reordered; plain %s must keep their count
    return sorted(found) if any("$" in p for p in found) else len(found)


def main():
    keys = code_keys()
    languages = php_array(os.path.join(WEB, "lang/languages.php"))
    failed = False
    for code in languages:
        if code == "en":
            continue
        path = os.path.join(WEB, "lang", code + ".php")
        if not os.path.exists(path):
            print(f"{code}: lang/{code}.php is missing")
            failed = True
            continue
        strings = php_array(path)
        missing = sorted(keys - set(strings))
        unused = sorted(set(strings) - keys)
        wrong = sorted(k for k in keys & set(strings) if placeholders(strings[k]) != placeholders(k))
        for k in missing:
            print(f"{code}: missing: {k}")
        for k in wrong:
            print(f"{code}: placeholders differ: {k} => {strings[k]}")
        for k in unused:
            print(f"{code}: unused: {k}")
        failed |= bool(missing or wrong)
        print(f"{code}: {len(strings)} strings, {len(missing)} missing, {len(wrong)} with wrong placeholders, {len(unused)} unused")
    sys.exit(1 if failed else 0)


if __name__ == "__main__":
    main()
