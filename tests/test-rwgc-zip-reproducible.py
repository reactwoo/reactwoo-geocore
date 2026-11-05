#!/usr/bin/env python3
"""The publish zip is two identical runs of scripts/package_zip.py on the git tree."""

from __future__ import annotations

import hashlib
import os
import subprocess
import sys
import tempfile
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
FAILED = 0


def check(label: str, ok: bool) -> None:
    global FAILED
    if ok:
        print(f"ok  - {label}")
        return
    print(f"FAIL - {label}")
    FAILED += 1


def sha256(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def test_workflows_call_package_zip() -> None:
    publish = (ROOT / ".github/workflows/publish-update.yml").read_text(encoding="utf-8")
    deploy = (ROOT / ".github/workflows/deploy-wordpress-org.yml").read_text(encoding="utf-8")

    check("publish workflow calls package_zip.py", "\n        run: python scripts/package_zip.py\n" in publish)
    check(
        "publish workflow does not install Composer dependencies before packaging",
        "composer install" not in publish,
    )
    check("publish workflow pins CPython 3.12", 'python-version: "3.12"' in publish)
    check(
        "publish artifact key is unchanged",
        'ARTIFACT_KEY="plugins/${PLUGIN_SLUG}/${VERSION}/${PLUGIN_SLUG}.zip"' in publish,
    )
    check(
        "publish latest key is unchanged",
        'LATEST_KEY="plugins/${PLUGIN_SLUG}/latest/${PLUGIN_SLUG}.zip"' in publish,
    )
    check(
        "publish upload uses the unversioned zip name",
        'aws s3 cp "${PLUGIN_SLUG}.zip"' in publish,
    )
    check("publish workflow does not call git archive", "git archive" not in publish)
    check(
        "WordPress.org deploy calls package_zip.py --target wporg",
        "\n        run: python3 scripts/package_zip.py --target wporg\n" in deploy,
    )
    check(
        "WordPress.org deploy does not install Composer dependencies before packaging",
        "composer install" not in deploy,
    )
    check("WordPress.org deploy pins CPython 3.12", 'python-version: "3.12"' in deploy)


def _package(dest: Path, target: str | None, ci: bool) -> None:
    env = os.environ.copy()
    env.pop("SOURCE_DATE_EPOCH", None)
    if ci:
        env["CI"] = "true"
    else:
        env.pop("CI", None)
    command = [sys.executable, "scripts/package_zip.py"]
    if target:
        command.extend(["--target", target])
    subprocess.run(command, cwd=dest, env=env, check=True)


def test_git_tree_zip_is_reproducible() -> None:
    """Package HEAD, which is the tree the publish job checks out."""
    with tempfile.TemporaryDirectory() as tmp:
        dest = Path(tmp) / "tree"
        dest.mkdir()
        archive = subprocess.check_output(["git", "-C", str(ROOT), "archive", "HEAD"])
        subprocess.run(["tar", "-x", "-C", str(dest)], input=archive, check=True)

        _package(dest, None, ci=True)
        publish_zip = dest / "reactwoo-geocore.zip"
        check("CI package_zip.py writes reactwoo-geocore.zip", publish_zip.is_file())
        if not publish_zip.is_file():
            return
        first = sha256(publish_zip)
        with zipfile.ZipFile(publish_zip) as archive_zip:
            info = archive_zip.getinfo("reactwoo-geocore/reactwoo-geocore.php")
        check(
            "publish zip timestamp is fixed at 1980-01-01",
            info.date_time == (1980, 1, 1, 0, 0, 0),
        )
        check("publish zip permissions are 0644", (info.external_attr >> 16) & 0o777 == 0o644)

        _package(dest, None, ci=True)
        second = sha256(publish_zip)
        check("two CI package_zip.py runs share a SHA-256", first == second)
        print(f"publish zip SHA-256 {first}")

        _package(dest, None, ci=False)
        local_zips = [
            path
            for path in dest.glob("reactwoo-geocore-*.zip")
            if not path.name.endswith("-wporg.zip")
        ]
        local_zip = local_zips[0] if local_zips else publish_zip
        check(
            "local package_zip.py bytes match the publish zip",
            sha256(local_zip) == first,
        )

        wporg_zip = dest / "reactwoo-geocore-wporg.zip"
        _package(dest, "wporg", ci=True)
        check("WordPress.org package_zip.py writes reactwoo-geocore-wporg.zip", wporg_zip.is_file())
        if not wporg_zip.is_file():
            return
        wporg_first = sha256(wporg_zip)
        _package(dest, "wporg", ci=True)
        check("two WordPress.org package_zip.py runs share a SHA-256", wporg_first == sha256(wporg_zip))


def main() -> None:
    test_workflows_call_package_zip()
    test_git_tree_zip_is_reproducible()
    if FAILED:
        print(f"{FAILED} failed")
        sys.exit(1)
    print("publish zip reproducibility checks passed")


if __name__ == "__main__":
    main()
