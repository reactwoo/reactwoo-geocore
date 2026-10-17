#!/usr/bin/env python3
"""The WordPress.org packager must refuse VCS dirs and prohibited extensions."""

from __future__ import annotations

import importlib.util
import io
import sys
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
SPEC = importlib.util.spec_from_file_location("package_zip", ROOT / "scripts" / "package_zip.py")
if SPEC is None or SPEC.loader is None:
    raise RuntimeError("Could not load scripts/package_zip.py")
PACKAGE = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(PACKAGE)

FAILED = 0


def check(label: str, ok: bool) -> None:
    global FAILED
    if ok:
        print(f"ok  - {label}")
        return
    print(f"FAIL - {label}")
    FAILED += 1


def main() -> None:
    release = "reactwoo-geocore/vendor/maxmind/web-service-common/dev-bin/release.sh"
    check(
        "release.sh is prohibited",
        release in PACKAGE.wporg_prohibited_names([release, "reactwoo-geocore/readme.txt"]),
    )
    samples = (
        "reactwoo-geocore/vendor/foo/library.phar",
        "reactwoo-geocore/vendor/foo/library.sh",
        "reactwoo-geocore/vendor/foo/library.zip",
        "reactwoo-geocore/vendor/foo/library.gz",
        "reactwoo-geocore/vendor/foo/library.tgz",
        "reactwoo-geocore/vendor/foo/library.tar",
        "reactwoo-geocore/vendor/foo/library.rar",
        "reactwoo-geocore/vendor/foo/library.7z",
        "reactwoo-geocore/vendor/foo/.git/config",
        "reactwoo-geocore/vendor/foo/.svn/entries",
        "reactwoo-geocore/vendor/foo/.hg/dirstate",
        "reactwoo-geocore/vendor/foo/.bzr/branch-format",
    )
    for sample in samples:
        check(sample.rsplit("/", 1)[-1] + " is prohibited", sample in PACKAGE.wporg_prohibited_names([sample]))

    source_samples = (
        "reactwoo-geocore/vendor/maxmind-db/reader/ext/php_maxminddb.h",
        "reactwoo-geocore/vendor/maxmind-db/reader/ext/maxminddb.c",
        "reactwoo-geocore/vendor/maxmind-db/reader/ext/config.m4",
        "reactwoo-geocore/vendor/maxmind-db/reader/ext/config.w32",
        "reactwoo-geocore/vendor/maxmind-db/reader/ext/tests/001-load.phpt",
        "reactwoo-geocore/vendor/maxmind-db/reader/ext/bundled-include/maxminddb_config.h",
        "reactwoo-geocore/vendor/geoip2/geoip2/mise.toml",
        "reactwoo-geocore/vendor/geoip2/geoip2/mise.lock",
        "reactwoo-geocore/vendor/maxmind/web-service-common/phpstan.neon",
        "reactwoo-geocore/vendor/somewhere/extra.c",
    )
    for sample in source_samples:
        check(sample.rsplit("/", 1)[-1] + " is prohibited", sample in PACKAGE.wporg_prohibited_names([sample]))

    safe = [
        "reactwoo-geocore/readme.txt",
        "reactwoo-geocore/vendor/autoload.php",
        "reactwoo-geocore/uninstall.php",
        "reactwoo-geocore/includes/class-rwgc-satellite-updater-stub.php",
    ]
    check("runtime files are allowed", PACKAGE.wporg_prohibited_names(safe) == [])

    rules = PACKAGE._compile_distignore(ROOT)
    check(
        "distignore excludes vendor dev-bin/release.sh",
        PACKAGE._is_distignored(release, rules),
    )
    check(
        "distignore keeps vendor/autoload.php",
        not PACKAGE._is_distignored("vendor/autoload.php", rules),
    )
    ext_files = (
        "vendor/maxmind-db/reader/ext/php_maxminddb.h",
        "vendor/maxmind-db/reader/ext/maxminddb.c",
        "vendor/maxmind-db/reader/ext/config.m4",
        "vendor/maxmind-db/reader/ext/config.w32",
        "vendor/maxmind-db/reader/ext/tests/001-load.phpt",
        "vendor/geoip2/geoip2/mise.toml",
        "vendor/maxmind-db/reader/lychee.toml",
        "vendor/geoip2/geoip2/mise.lock",
        "vendor/maxmind/web-service-common/phpstan.neon",
        "vendor/nested/helper.c",
        "vendor/nested/helper.h",
    )
    for rel in ext_files:
        check("distignore excludes " + rel, PACKAGE._is_distignored(rel, rules))
    check(
        "distignore keeps GeoIP2 reader class",
        not PACKAGE._is_distignored("vendor/geoip2/geoip2/src/Database/Reader.php", rules),
    )

    buf = io.BytesIO()
    with zipfile.ZipFile(buf, "w") as archive:
        archive.writestr(release, "#!/bin/sh\n")
        archive.writestr(
            "reactwoo-geocore/reactwoo-geocore.php",
            "define( 'RWGC_DISTRIBUTION', 'wporg' );\n",
        )
    buf.seek(0)
    raised = False
    message = ""
    with zipfile.ZipFile(buf) as archive:
        try:
            PACKAGE._assert_wporg_zip(archive, "reactwoo-geocore")
        except RuntimeError as exc:
            raised = True
            message = str(exc)
    check("packager refuses a zip that still contains release.sh", raised and "release.sh" in message)

    if FAILED:
        print(f"\n{FAILED} assertion(s) failed")
        sys.exit(1)
    print("\nAll zip guard checks passed.")


if __name__ == "__main__":
    main()
