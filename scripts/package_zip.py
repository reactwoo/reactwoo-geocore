#!/usr/bin/env python3
"""
Create a release zip with canonical WordPress plugin structure.

Output: reactwoo-geocore.zip
Archive root: reactwoo-geocore/
"""

from __future__ import annotations

import os
import zipfile
from pathlib import Path

ROOT_FOLDER = "reactwoo-geocore"
OUTPUT_ZIP = "reactwoo-geocore.zip"

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


def main() -> None:
    base = Path(__file__).resolve().parent.parent
    out = base / OUTPUT_ZIP

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
                    arcname = f"{ROOT_FOLDER}/{rel}"
                    zf.write(filepath, arcname=arcname)

        for filename in INCLUDE_FILES:
            filepath = base / filename
            if not filepath.is_file():
                continue
            arcname = f"{ROOT_FOLDER}/{filename}"
            zf.write(filepath, arcname=arcname)

    with zipfile.ZipFile(out, "r") as zf:
        names = zf.namelist()
        bad_backslashes = [n for n in names if "\\" in n]
        nested = [n for n in names if n.startswith(f"{ROOT_FOLDER}/{ROOT_FOLDER}/")]
        if bad_backslashes or nested:
            raise RuntimeError(
                "Invalid zip structure detected: "
                f"backslashes={len(bad_backslashes)} nested_root={len(nested)}"
            )

    print(f"Created: {out}")


if __name__ == "__main__":
    main()
