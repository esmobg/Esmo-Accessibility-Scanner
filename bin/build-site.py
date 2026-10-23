#!/usr/bin/env python3
"""Assemble the GitHub Pages website from site/ into an output folder.

Usage:
    python bin/build-site.py                                 # -> .site-build/
    python bin/build-site.py --out _site --require-release   # used by .github/workflows/pages.yml

Copies site/, adds the icon, banner and screenshots from wp-org-assets/
(so binary files are not duplicated in git) and replaces the __VERSION__
and __BUILD_DATE__ placeholders.

__VERSION__ is the latest *published GitHub release* that has the plugin zip
attached, so the download buttons never point to a release that does not
exist yet (for example right after the version is bumped on main). Use
--version to override it. Without network access the script falls back to
the plugin header version (for local previews only) unless --require-release
is given.
"""

from __future__ import annotations

import argparse
import datetime
import json
import os
import re
import shutil
import subprocess
import sys
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
REPO = os.environ.get("GITHUB_REPOSITORY", "esmobg/Esmo-Accessibility-Scanner")
SLUG = "esmo-accessibility-scanner"
LEGACY_SLUG = "accessibility-guardian"  # zip name of releases up to 1.2.0
VERSION_RE = re.compile(r"^\d+\.\d+\.\d+$")
IMAGES = [
    "icon.svg",
    "icon-256x256.png",
    "banner-1544x500.png",
    "screenshot-1.png",
    "screenshot-2.png",
    "screenshot-3.png",
    "screenshot-4.png",
]
TEXT_SUFFIXES = {".html", ".xml", ".txt"}


def plugin_version() -> str:
    header = (ROOT / f"{SLUG}.php").read_text(encoding="utf-8")
    match = re.search(r"^\s*\*\s*Version:\s*(\S+)", header, re.MULTILINE)
    if not match:
        sys.exit("Could not read Version from the plugin header.")
    return match.group(1)


def latest_release() -> tuple[str, str]:
    """Version and zip asset name of the latest published release.

    Accepts the current zip name and the pre-1.3.0 name (accessibility-guardian-x.y.z.zip),
    so links stay valid until the first release under the new name exists.
    """
    request = urllib.request.Request(
        f"https://api.github.com/repos/{REPO}/releases/latest",
        headers={"Accept": "application/vnd.github+json", "User-Agent": "esmoas-build-site"},
    )
    token = os.environ.get("GITHUB_TOKEN") or os.environ.get("GH_TOKEN")
    if token:
        request.add_header("Authorization", f"Bearer {token}")

    with urllib.request.urlopen(request, timeout=15) as response:
        release = json.load(response)

    version = str(release.get("tag_name", "")).lstrip("v")
    if not VERSION_RE.match(version):
        raise RuntimeError(f"latest release tag {release.get('tag_name')!r} is not a version number")
    assets = {asset.get("name") for asset in release.get("assets", [])}
    for name in (f"{SLUG}-{version}.zip", f"{LEGACY_SLUG}-{version}.zip"):
        if version and name in assets:
            return version, name
    raise RuntimeError(f"latest release {release.get('tag_name')!r} has no plugin zip attached")


def resolve_release(args: argparse.Namespace) -> tuple[str, str]:
    if args.version:
        version = args.version.lstrip("v")
        if not VERSION_RE.match(version):
            sys.exit(f"--version must look like 1.2.3, got {args.version!r}")
        return version, f"{SLUG}-{version}.zip"
    try:
        return latest_release()
    except Exception as error:  # noqa: BLE001 - network or API problems
        if args.require_release:
            sys.exit(f"Could not determine the latest release: {error}")
        fallback = plugin_version()
        print(f"warning: {error}; using the plugin header version {fallback} (preview only)", file=sys.stderr)
        return fallback, f"{SLUG}-{fallback}.zip"


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("--out", default=".site-build", help="Output folder (default: .site-build).")
    parser.add_argument("--version", help="Release version to link to (default: latest published release).")
    parser.add_argument("--require-release", action="store_true", help="Fail instead of falling back to the plugin header version.")
    args = parser.parse_args()

    version, zip_name = resolve_release(args)

    out = Path(args.out)
    if not out.is_absolute():
        out = ROOT / out
    out = out.resolve()
    # The folder is deleted first: only allow a throwaway folder inside the repository.
    if out == ROOT or ROOT not in out.parents:
        sys.exit(f"--out must be a folder inside {ROOT}")
    if subprocess.check_output(["git", "ls-files", "--", str(out)], cwd=ROOT, text=True).strip():
        sys.exit(f"--out {out} contains tracked files; choose an empty or ignored folder")
    shutil.rmtree(out, ignore_errors=True)
    shutil.copytree(ROOT / "site", out)

    img_dir = out / "assets" / "img"
    img_dir.mkdir(parents=True, exist_ok=True)
    for name in IMAGES:
        shutil.copy2(ROOT / "wp-org-assets" / name, img_dir / name)

    replacements = {
        "__VERSION__": version,
        "__ZIP__": zip_name,
        "__BUILD_DATE__": datetime.date.today().isoformat(),
    }
    for path in out.rglob("*"):
        if path.is_file() and path.suffix in TEXT_SUFFIXES:
            text = path.read_text(encoding="utf-8")
            for placeholder, value in replacements.items():
                text = text.replace(placeholder, value)
            path.write_text(text, encoding="utf-8", newline="\n")

    leftovers = [p for p in out.rglob("*") if p.is_file() and p.suffix in TEXT_SUFFIXES and ("__VERSION__" in p.read_text(encoding="utf-8") or "__ZIP__" in p.read_text(encoding="utf-8"))]
    if leftovers:
        sys.exit(f"Unreplaced placeholders in: {', '.join(str(p) for p in leftovers)}")

    print(f"site built in {out} (release {version}, {zip_name})")


if __name__ == "__main__":
    main()
