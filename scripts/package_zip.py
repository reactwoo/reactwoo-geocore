#!/usr/bin/env python3
"""
Create a release zip with canonical WordPress plugin structure.

Zip root folder and output filename come from package.json → reactwooBuild
(pluginFolder, zipFile). Defaults match historical reactwoo-geocore.zip.

Targets:
  reactwoo (default) — same file set the R2 / api.reactwoo.com publish job ships.
  wporg              — WordPress.org directory zip. Reads .distignore, omits the
                       self-hosted updater, and sets RWGC_DISTRIBUTION to wporg
                       inside the zip only (the working tree stays reactwoo).
"""

from __future__ import annotations

import argparse
import json
import os
import re
import zipfile
from pathlib import Path

_DEFAULT_FOLDER = "reactwoo-geocore"
_DISTRIBUTION_REACTWOO = "define( 'RWGC_DISTRIBUTION', 'reactwoo' );"
_DISTRIBUTION_WPORG = "define( 'RWGC_DISTRIBUTION', 'wporg' );"


def _is_ci_environment() -> bool:
    return os.environ.get("CI", "").lower() in ("1", "true", "yes")


def _read_plugin_version(base: Path, cfg: dict, folder: str) -> str | None:
    """Read semver from the plugin header * Version: line."""
    main_php = cfg.get("mainPhp") or f"{folder}.php"
    php_path = base / str(main_php)
    if not php_path.is_file():
        return None
    try:
        text = php_path.read_text(encoding="utf-8", errors="replace")
    except OSError:
        return None
    match = re.search(r"^\s*\*\s*Version:\s*([^\s\r\n]+)", text, re.MULTILINE)
    return match.group(1).strip() if match else None


def _zip_paths(base: Path, target: str) -> tuple[str, str]:
    """Read pluginFolder and zipFile from package.json reactwooBuild."""
    pkg_path = base / "package.json"
    zip_name = f"{_DEFAULT_FOLDER}.zip"
    if not pkg_path.is_file():
        folder, zfile = _DEFAULT_FOLDER, zip_name
    else:
        try:
            data = json.loads(pkg_path.read_text(encoding="utf-8"))
        except (OSError, json.JSONDecodeError):
            data = {}
        cfg = data.get("reactwooBuild")
        if not isinstance(cfg, dict):
            cfg = {}
        folder = str(cfg.get("pluginFolder") or _DEFAULT_FOLDER)
        zfile = str(cfg.get("zipFile") or f"{folder}.zip")
        version_in_zip = cfg.get("versionInZipFile", True)
        if target != "wporg" and version_in_zip and not _is_ci_environment():
            version = _read_plugin_version(base, cfg, folder)
            if version:
                stem = Path(zfile).stem
                suffix = Path(zfile).suffix or ".zip"
                zfile = f"{stem}-{version}{suffix}"
    if target == "wporg":
        stem = Path(zfile).stem
        suffix = Path(zfile).suffix or ".zip"
        if not stem.endswith("-wporg"):
            zfile = f"{stem}-wporg{suffix}"
    return folder, zfile


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


def _compile_distignore(base: Path) -> list[tuple[re.Pattern[str], bool]]:
    """Return (regex, negated) rules from .distignore. Later rules win."""
    path = base / ".distignore"
    if not path.is_file():
        raise RuntimeError("Missing .distignore. The WordPress.org target requires it.")
    rules: list[tuple[re.Pattern[str], bool]] = []
    for raw in path.read_text(encoding="utf-8").splitlines():
        line = raw.strip()
        if not line or line.startswith("#"):
            continue
        negated = line.startswith("!")
        pattern = line[1:].strip() if negated else line
        pattern = pattern.lstrip("/")
        if pattern == "":
            continue
        rules.append((_distignore_regex(pattern), negated))
    return rules


def _distignore_regex(pattern: str) -> re.Pattern[str]:
    """Translate a small gitignore-like pattern to a full-path regex."""
    directory = pattern.endswith("/")
    pattern = pattern.rstrip("/")
    parts: list[str] = []
    i = 0
    while i < len(pattern):
        if pattern.startswith("**/", i):
            parts.append("(?:.*/)?")
            i += 3
            continue
        if pattern.startswith("**", i):
            parts.append(".*")
            i += 2
            continue
        char = pattern[i]
        if char == "*":
            parts.append("[^/]*")
        elif char == "?":
            parts.append("[^/]")
        else:
            parts.append(re.escape(char))
        i += 1
    body = "".join(parts)
    if "/" not in pattern.replace("**/", ""):
        anchored = rf"(?:^|/){body}$"
    else:
        anchored = rf"^{body}$"
    if directory:
        anchored = anchored[:-1] + r"(?:/.*)?$"
    return re.compile(anchored)


def _is_distignored(rel: str, rules: list[tuple[re.Pattern[str], bool]]) -> bool:
    """True when .distignore excludes this path or one of its parents."""
    parts = rel.split("/")
    candidates = ["/".join(parts[: i + 1]) for i in range(len(parts))]
    ignored = False
    for candidate in candidates:
        for regex, negated in rules:
            if regex.search(candidate):
                ignored = not negated
    return ignored


def _main_php_bytes(path: Path, target: str) -> bytes:
    text = path.read_text(encoding="utf-8")
    if target == "wporg":
        if _DISTRIBUTION_REACTWOO not in text:
            raise RuntimeError(
                "reactwoo-geocore.php is missing the reactwoo distribution constant. "
                "The WordPress.org zip rewrites that line; refusing to guess."
            )
        text = text.replace(_DISTRIBUTION_REACTWOO, _DISTRIBUTION_WPORG, 1)
        if "pre_set_site_transient_update_plugins" in text:
            raise RuntimeError("Main plugin file still references the update transient.")
    return text.encode("utf-8")


def _assert_wporg_zip(zf: zipfile.ZipFile, root_folder: str) -> None:
    names = zf.namelist()
    banned_fragments = (
        "class-rwgc-satellite-updater.php",
        "/docs/",
        "/tests/",
        "/ai-handoff/",
        "/.github/",
        "/scripts/",
        "composer.json",
        "composer.lock",
        "phpunit",
        "threat-model",
    )
    bad = [name for name in names if any(fragment in name for fragment in banned_fragments)]
    if bad:
        raise RuntimeError("WordPress.org zip contains excluded paths:\n" + "\n".join(bad[:30]))
    main = f"{root_folder}/reactwoo-geocore.php"
    if main not in names:
        raise RuntimeError(f"WordPress.org zip is missing {main}")
    main_text = zf.read(main).decode("utf-8")
    if _DISTRIBUTION_WPORG not in main_text:
        raise RuntimeError("WordPress.org zip did not set RWGC_DISTRIBUTION to wporg.")
    if _DISTRIBUTION_REACTWOO in main_text:
        raise RuntimeError("WordPress.org zip still defines the reactwoo distribution.")
    joined = "\n".join(names)
    if "pre_set_site_transient_update_plugins" in joined:
        raise RuntimeError("WordPress.org zip filename list mentions the updater hook.")
    for name in names:
        if not name.endswith(".php"):
            continue
        if "class-rwgc-satellite-updater.php" in name:
            raise RuntimeError(name)
        body = zf.read(name).decode("utf-8", errors="replace")
        if "pre_set_site_transient_update_plugins" in body:
            raise RuntimeError(f"{name} still hooks the plugin update transient.")


def main() -> None:
    parser = argparse.ArgumentParser(description="Package ReactWoo Geo Core.")
    parser.add_argument(
        "--target",
        choices=("reactwoo", "wporg"),
        default="reactwoo",
        help="reactwoo keeps the existing R2 zip contents. wporg builds the directory zip.",
    )
    args = parser.parse_args()
    target = str(args.target)

    base = Path(__file__).resolve().parent.parent
    _assert_shippable_vendor(base)
    root_folder, zip_name = _zip_paths(base, target)
    out = base / zip_name
    ignore_rules = _compile_distignore(base) if target == "wporg" else []

    if out.exists():
        out.unlink()

    with zipfile.ZipFile(out, "w", zipfile.ZIP_DEFLATED) as zf:
        for dirname in INCLUDE_DIRS:
            if target == "wporg" and _is_distignored(dirname, ignore_rules):
                continue
            dirpath = base / dirname
            if not dirpath.is_dir():
                continue
            for root, _dirs, files in os.walk(dirpath):
                for filename in files:
                    filepath = Path(root) / filename
                    rel = filepath.relative_to(base).as_posix()
                    if target == "wporg" and _is_distignored(rel, ignore_rules):
                        continue
                    arcname = f"{root_folder}/{rel}"
                    zf.write(filepath, arcname=arcname)

        for filename in INCLUDE_FILES:
            if target == "wporg" and _is_distignored(filename, ignore_rules):
                continue
            filepath = base / filename
            if not filepath.is_file():
                continue
            arcname = f"{root_folder}/{filename}"
            if filename == "reactwoo-geocore.php":
                zf.writestr(arcname, _main_php_bytes(filepath, target))
            else:
                zf.write(filepath, arcname=arcname)

        if target == "wporg":
            _assert_wporg_zip(zf, root_folder)

    with zipfile.ZipFile(out, "r") as zf:
        names = zf.namelist()
        bad_backslashes = [n for n in names if "\\" in n]
        nested = [n for n in names if n.startswith(f"{root_folder}/{root_folder}/")]
        if bad_backslashes or nested:
            raise RuntimeError(
                "Invalid zip structure detected: "
                f"backslashes={len(bad_backslashes)} nested_root={len(nested)}"
            )

    print(f"Created ({target}): {out}")


if __name__ == "__main__":
    main()
