#!/usr/bin/env python3
"""
Create a release zip with canonical WordPress plugin structure.

Zip root folder and output filename come from package.json → reactwooBuild
(pluginFolder, zipFile). Defaults match historical reactwoo-geocore.zip.
"""

from __future__ import annotations

import json
import os
import zipfile
from pathlib import Path

_DEFAULT_FOLDER = "reactwoo-geocore"


def _zip_paths(base: Path) -> tuple[str, str]:
    """Read pluginFolder and zipFile from package.json reactwooBuild."""
    pkg_path = base / "package.json"
    zip_name = f"{_DEFAULT_FOLDER}.zip"
    if not pkg_path.is_file():
        return _DEFAULT_FOLDER, zip_name
    try:
        data = json.loads(pkg_path.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError):
        return _DEFAULT_FOLDER, zip_name
    cfg = data.get("reactwooBuild")
    if not isinstance(cfg, dict):
        return _DEFAULT_FOLDER, zip_name
    folder = cfg.get("pluginFolder") or _DEFAULT_FOLDER
    zfile = cfg.get("zipFile") or f"{folder}.zip"
    return str(folder), str(zfile)

INCLUDE_DIRS = [
    "admin",
    "assets",
    "blocks",
    "docs",
    "includes",
    "vendor",
]

INCLUDE_FILES = [
    "reactwoo-geocore.php",
    "readme.txt",
    "license.txt",
    "uninstall.php",
    "composer.json",
]


def _assert_shippable_vendor(base: Path) -> None:
    """
    Customers must not run Composer on the server — the zip must contain a complete
    production vendor/. Fail fast if autoload was generated with dev deps (PHPUnit, etc.).
    Maintainer fix: composer install --no-dev --optimize-autoloader
    """
    static_path = base / "vendor" / "composer" / "autoload_static.php"
    if not static_path.is_file():
        raise RuntimeError(
            "Missing vendor/composer/autoload_static.php. Run "
            "`composer install --no-dev --optimize-autoloader` before packaging (maintainers only)."
        )
    text = static_path.read_text(encoding="utf-8", errors="replace")
    # Dev-only packages that must not appear in production autoload
    needles = ("myclabs", "phpunit", "DeepCopy\\")
    for n in needles:
        if n in text:
            raise RuntimeError(
                f"vendor/composer/autoload_static.php still references {n!r} (dev dependency). "
                "Run `composer install --no-dev --optimize-autoloader` before packaging (maintainers only)."
            )


def main() -> None:
    base = Path(__file__).resolve().parent.parent
    _assert_shippable_vendor(base)
    root_folder, zip_name = _zip_paths(base)
    out = base / zip_name

    if out.exists():
        out.unlink()

    with zipfile.ZipFile(out, "w", zipfile.ZIP_DEFLATED) as zf:
        for dirname in INCLUDE_DIRS:
            dirpath = base / dirname
            if not dirpath.is_dir():
                continue
            for root, _dirs, files in os.walk(dirpath):
                for filename in files:
                    filepath = Path(root) / filename
                    rel = filepath.relative_to(base).as_posix()
                    arcname = f"{root_folder}/{rel}"
                    zf.write(filepath, arcname=arcname)

        for filename in INCLUDE_FILES:
            filepath = base / filename
            if not filepath.is_file():
                continue
            arcname = f"{root_folder}/{filename}"
            zf.write(filepath, arcname=arcname)

    with zipfile.ZipFile(out, "r") as zf:
        names = zf.namelist()
        bad_backslashes = [n for n in names if "\\" in n]
        nested = [n for n in names if n.startswith(f"{root_folder}/{root_folder}/")]
        if bad_backslashes or nested:
            raise RuntimeError(
                "Invalid zip structure detected: "
                f"backslashes={len(bad_backslashes)} nested_root={len(nested)}"
            )

    print(f"Created: {out}")


if __name__ == "__main__":
    main()
