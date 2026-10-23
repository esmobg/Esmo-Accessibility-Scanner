#!/usr/bin/env python3
"""Build the WordPress.org release zip for Esmo Accessibility Scanner.

Usage:
    python bin/build-zip.py              # -> dist/esmo-accessibility-scanner-<version>.zip
    python bin/build-zip.py --dir out/   # also unpack the plugin folder into out/

Only files tracked by git are packed (new files must be `git add`ed first), so
stray local files such as .env or scratch scripts can never ship. Symlinks are
rejected. The list is filtered by .distignore. Every pattern is matched against each path segment and the
full relative path. The zip root folder is always `esmo-accessibility-scanner/`,
whatever the repository folder is called.
"""

from __future__ import annotations

import argparse
import fnmatch
import re
import shutil
import subprocess
import sys
import zipfile
from pathlib import Path

SLUG = "esmo-accessibility-scanner"
ROOT = Path(__file__).resolve().parent.parent


def plugin_version() -> str:
    header = (ROOT / f"{SLUG}.php").read_text(encoding="utf-8")
    match = re.search(r"^\s*\*\s*Version:\s*(\S+)", header, re.MULTILINE)
    if not match:
        sys.exit("Could not read Version from the plugin header.")
    return match.group(1)


def ignore_patterns() -> list[str]:
    lines = (ROOT / ".distignore").read_text(encoding="utf-8").splitlines()
    return [line.strip().strip("/") for line in lines if line.strip() and not line.startswith("#")]


def is_ignored(path: str, patterns: list[str]) -> bool:
    segments = path.split("/")
    return any(
        fnmatch.fnmatch(path, pattern) or any(fnmatch.fnmatch(segment, pattern) for segment in segments)
        for pattern in patterns
    )


def source_files() -> list[str]:
    output = subprocess.check_output(["git", "ls-files", "--cached"], cwd=ROOT, text=True)
    files = sorted({line.strip() for line in output.splitlines() if line.strip()})
    links = [f for f in files if (ROOT / f).is_symlink()]
    if links:
        sys.exit(f"Refusing to pack symlinks: {', '.join(links)}")
    return [f for f in files if (ROOT / f).is_file()]


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("--dir", help="Also unpack the built plugin into this directory.")
    args = parser.parse_args()

    version = plugin_version()
    readme = (ROOT / "readme.txt").read_text(encoding="utf-8")
    stable = re.search(r"^Stable tag:\s*(\S+)", readme, re.MULTILINE)
    if not stable or stable.group(1) != version:
        sys.exit(f"readme.txt Stable tag ({stable.group(1) if stable else 'missing'}) does not match Version {version}.")

    patterns = ignore_patterns()
    files = [f for f in source_files() if not is_ignored(f, patterns)]

    dist = ROOT / "dist"
    dist.mkdir(exist_ok=True)
    target = dist / f"{SLUG}-{version}.zip"
    with zipfile.ZipFile(target, "w", zipfile.ZIP_DEFLATED) as archive:
        for name in files:
            archive.write(ROOT / name, f"{SLUG}/{name}")

    print(f"{len(files)} files -> {target.relative_to(ROOT)} ({target.stat().st_size:,} bytes)")

    if args.dir:
        out = Path(args.dir).resolve()
        shutil.rmtree(out / SLUG, ignore_errors=True)
        out.mkdir(parents=True, exist_ok=True)
        with zipfile.ZipFile(target) as archive:
            archive.extractall(out)
        print(f"unpacked to {out / SLUG}")


if __name__ == "__main__":
    main()
