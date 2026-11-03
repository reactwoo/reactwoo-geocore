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


# Directory artwork lives here for the 10up deploy action. Neither zip ships it.
SKIP_DIR_PREFIXES = (
    ".wordpress-org/",
)


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


# WordPress.org rejects these anywhere in a plugin zip, including inside vendor/.
_WPORG_PROHIBITED_EXTENSIONS = (".phar", ".sh", ".zip", ".gz", ".tgz", ".tar", ".rar", ".7z")
# Reviewers also reject non-plugin sources. The MaxMind C extension is the case
# that failed review: .c, .h, .m4, and .w32. .phpt/.neon/.toml are maintainer files
# that GeoIP2 3.4 and the reader 1.14 package added beside that extension.
_WPORG_VENDOR_SOURCE_EXTENSIONS = (".c", ".h", ".m4", ".w32", ".phpt", ".neon", ".toml")
_WPORG_VENDOR_MAINTAINER_NAMES = {"mise.lock"}
_WPORG_VCS_DIRS = {".git", ".svn", ".hg", ".bzr"}


def _path_is_under_vendor(parts: list[str]) -> bool:
    return any(part == "vendor" for part in parts)


def _path_is_maxmind_reader_ext(parts: list[str]) -> bool:
    """True for vendor/maxmind-db/reader/ext and everything inside it."""
    for index, part in enumerate(parts):
        if part != "maxmind-db":
            continue
        tail = parts[index : index + 4]
        if tail[:3] == ["maxmind-db", "reader", "ext"]:
            return True
    return False


def wporg_prohibited_names(names: list[str]) -> list[str]:
    """Return archive paths WordPress.org will reject before review."""
    bad: list[str] = []
    for name in names:
        parts = [part for part in name.split("/") if part]
        if any(part.lower() in _WPORG_VCS_DIRS for part in parts):
            bad.append(name)
            continue
        filename = parts[-1].lower() if parts else name.lower()
        if any(filename.endswith(ext) for ext in _WPORG_PROHIBITED_EXTENSIONS):
            bad.append(name)
            continue
        if not _path_is_under_vendor(parts):
            continue
        if _path_is_maxmind_reader_ext(parts):
            bad.append(name)
            continue
        if filename in _WPORG_VENDOR_MAINTAINER_NAMES or any(
            filename.endswith(ext) for ext in _WPORG_VENDOR_SOURCE_EXTENSIONS
        ):
            bad.append(name)
    return bad


# Sanesecurity foxhole_js.cdb. Entry numbers are 1-based file positions.
# Sizes are uncompressed and inclusive. JS_Zip_2's published filename regex does
# not match a normal .js name; it is applied here as any .js file in that size
# window, which is the false positive this order avoids.
FOXHOLE_JS_ZIP_RULES: tuple[tuple[str, int, int, int], ...] = (
    ("Sanesecurity.Foxhole.JS_Zip_1", 1, 0, 512_000),
    ("Sanesecurity.Foxhole.JS_Zip_2", 2, 0, 51_200),
    ("Sanesecurity.Foxhole.JS_Zip_4", 4, 90_000, 512_000),
) + tuple(
    (f"Sanesecurity.Foxhole.JS_Zip_{entry}", entry, 3_000, 512_000) for entry in range(11, 25)
)

# Entries that must not be a .js file, whatever its size.
JS_FORBIDDEN_ENTRIES = frozenset({1, 2, 4, *range(11, 25)})


def path_is_javascript(path: str) -> bool:
    """True when the zip member name ends in .js (not .json)."""
    name = path.replace("\\", "/").rstrip("/").rsplit("/", 1)[-1]
    return name.lower().endswith(".js")


def javascript_at_forbidden_entries(paths: list[str]) -> list[int]:
    """1-based positions where a .js file would trip the Foxhole layout."""
    return [
        index
        for index, path in enumerate(paths, start=1)
        if index in JS_FORBIDDEN_ENTRIES and path_is_javascript(path)
    ]


def foxhole_js_zip_matches(entries: list[tuple[str, int]]) -> list[str]:
    """Rule names from foxhole_js.cdb that match this ordered (name, uncompressed size) list."""
    hits: list[str] = []
    for rule, entry, low, high in FOXHOLE_JS_ZIP_RULES:
        index = entry - 1
        if index < 0 or index >= len(entries):
            continue
        filename, size = entries[index]
        if path_is_javascript(filename) and low <= int(size) <= high:
            hits.append(rule)
    return hits


def order_zip_rels(rels: list[str], main_rel: str, readme_rel: str = "readme.txt") -> list[str]:
    """
    Write the plugin bootstrap and readme first, then other non-JS files, then
    JS. Both groups are sorted so two builds of the same tree match. If that
    still leaves a .js file at entry 1, 2, 4, or 11-24, non-JS files fill those
    slots.
    """
    unique: list[str] = []
    seen: set[str] = set()
    for rel in rels:
        rel = rel.replace("\\", "/")
        if rel in seen or rel.endswith("/"):
            continue
        seen.add(rel)
        unique.append(rel)

    head: list[str] = []
    if main_rel in seen:
        head.append(main_rel)
    if readme_rel in seen and readme_rel not in head:
        head.append(readme_rel)
    head_set = set(head)
    non_js = sorted(
        (rel for rel in unique if rel not in head_set and not path_is_javascript(rel)),
        key=str.casefold,
    )
    js = sorted((rel for rel in unique if path_is_javascript(rel)), key=str.casefold)
    preferred = head + non_js + js
    if not javascript_at_forbidden_entries(preferred):
        return preferred

    total = len(preferred)
    slots: list[str | None] = [None] * total
    for index, rel in enumerate(head):
        if index < total:
            slots[index] = rel
    pool = list(non_js)
    for position in range(1, total + 1):
        if slots[position - 1] is not None:
            continue
        if position in JS_FORBIDDEN_ENTRIES and pool:
            slots[position - 1] = pool.pop(0)
    rest = pool + js
    for index in range(total):
        if slots[index] is None and rest:
            slots[index] = rest.pop(0)
    ordered = [rel for rel in slots if rel is not None]
    blocked = javascript_at_forbidden_entries(ordered)
    if blocked or rest:
        listed = ", ".join(str(number) for number in blocked) or "unknown"
        raise RuntimeError(
            "Zip would still place a .js file at Foxhole entry "
            f"{listed}. Entries 1, 2, 4, and 11-24 must not be .js files."
        )
    return ordered


def _plugin_main_rel(base: Path, folder: str) -> str:
    pkg_path = base / "package.json"
    cfg: dict = {}
    if pkg_path.is_file():
        try:
            data = json.loads(pkg_path.read_text(encoding="utf-8"))
        except (OSError, json.JSONDecodeError):
            data = {}
        raw = data.get("reactwooBuild")
        if isinstance(raw, dict):
            cfg = raw
    return str(cfg.get("mainPhp") or f"{folder}.php")


def collect_ordered_members(base: Path, target: str) -> tuple[str, list[tuple[str, Path | bytes]]]:
    """Files the zip will contain, in Foxhole-safe order. Payload is a path or main-php bytes."""
    root_folder, _zip_name = _zip_paths(base, target)
    ignore_rules = _compile_distignore(base) if target == "wporg" else []
    main_rel = _plugin_main_rel(base, root_folder)
    found: dict[str, Path | bytes] = {}

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
                if rel.startswith(SKIP_DIR_PREFIXES):
                    continue
                if target == "wporg" and _is_distignored(rel, ignore_rules):
                    continue
                found[rel] = filepath

    for filename in INCLUDE_FILES:
        if target == "wporg" and _is_distignored(filename, ignore_rules):
            continue
        filepath = base / filename
        if not filepath.is_file():
            continue
        if filename == main_rel:
            found[filename] = _main_php_bytes(filepath, target)
        else:
            found[filename] = filepath

    ordered = order_zip_rels(list(found), main_rel, "readme.txt")
    return root_folder, [(rel, found[rel]) for rel in ordered]


def write_ordered_zip(out: Path, root_folder: str, members: list[tuple[str, Path | bytes]]) -> None:
    if out.exists():
        out.unlink()
    with zipfile.ZipFile(out, "w", zipfile.ZIP_DEFLATED) as zf:
        for rel, payload in members:
            arcname = f"{root_folder}/{rel}"
            if isinstance(payload, bytes):
                zf.writestr(arcname, payload)
            else:
                zf.write(payload, arcname=arcname)


def zip_file_entries(zf: zipfile.ZipFile) -> list[tuple[str, int]]:
    """Ordered file members and uncompressed sizes. Directory entries are ignored."""
    return [(info.filename, info.file_size) for info in zf.infolist() if not info.is_dir()]


def assert_zip_avoids_foxhole_js(zf: zipfile.ZipFile) -> None:
    entries = zip_file_entries(zf)
    names = [name for name, _size in entries]
    blocked = javascript_at_forbidden_entries(names)
    hits = foxhole_js_zip_matches(entries)
    if not blocked and not hits:
        return
    detail: list[str] = []
    if blocked:
        detail.append(".js at entries " + ", ".join(str(number) for number in blocked))
    if hits:
        detail.append("rules " + ", ".join(hits))
    raise RuntimeError("Zip trips ClamAV Foxhole JS rules: " + "; ".join(detail))


def _assert_wporg_zip(zf: zipfile.ZipFile, root_folder: str) -> None:
    names = zf.namelist()
    prohibited = wporg_prohibited_names(names)
    if prohibited:
        raise RuntimeError(
            "WordPress.org zip contains prohibited files "
            "(VCS directories, .phar/.sh/.zip/.gz/.tgz/.tar/.rar/.7z, "
            "or vendor C sources .c/.h/.m4/.w32 and MaxMind reader ext/):\n"
            + "\n".join(prohibited[:30])
        )
    banned_fragments = (
        "class-rwgc-satellite-updater.php",
        "class-rwgc-page-version.php",
        "class-rwgc-page-version-routing.php",
        "class-rwgc-routing.php",
        "class-rwgc-variant-manager.php",
        "/docs/",
        "/tests/",
        "/ai-handoff/",
        "/.github/",
        "/scripts/",
        "composer.lock",
        "cacert.pem",
        "assets/icon-128x128.png",
        "assets/icon-256x256.png",
        "phpunit",
        "threat-model",
    )
    bad = [name for name in names if any(fragment in name for fragment in banned_fragments)]
    if bad:
        raise RuntimeError("WordPress.org zip contains excluded paths:\n" + "\n".join(bad[:30]))
    main = f"{root_folder}/reactwoo-geocore.php"
    composer_manifest = f"{root_folder}/composer.json"
    if main not in names:
        raise RuntimeError(f"WordPress.org zip is missing {main}")
    if composer_manifest not in names:
        raise RuntimeError(
            "WordPress.org zip is missing composer.json. Plugin Check warns when vendor/ is shipped without it."
        )
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
    if any(".wordpress-org/" in name for name in names):
        raise RuntimeError("WordPress.org zip contains .wordpress-org assets.")


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
    _root_folder, zip_name = _zip_paths(base, target)
    out = base / zip_name
    root_folder, members = collect_ordered_members(base, target)
    write_ordered_zip(out, root_folder, members)

    with zipfile.ZipFile(out, "r") as zf:
        if target == "wporg":
            _assert_wporg_zip(zf, root_folder)
        assert_zip_avoids_foxhole_js(zf)
        names = zf.namelist()
        bad_backslashes = [n for n in names if "\\" in n]
        nested = [n for n in names if n.startswith(f"{root_folder}/{root_folder}/")]
        if bad_backslashes or nested:
            raise RuntimeError(
                "Invalid zip structure detected: "
                f"backslashes={len(bad_backslashes)} nested_root={len(nested)}"
            )
        if any(".wordpress-org/" in name for name in names):
            raise RuntimeError("Zip contains .wordpress-org directory assets.")

    print(f"Created ({target}): {out}")


if __name__ == "__main__":
    main()
