#!/usr/bin/env python3
"""Foxhole JS_Zip rules must not match the release zip entry order."""

from __future__ import annotations

import hashlib
import importlib.util
import io
import os
import sys
import tempfile
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


def entries_of(archive: zipfile.ZipFile) -> list[tuple[str, int]]:
    return PACKAGE.zip_file_entries(archive)


def write_zip(names_and_sizes: list[tuple[str, int]]) -> zipfile.ZipFile:
    buf = io.BytesIO()
    with zipfile.ZipFile(buf, "w") as archive:
        for name, size in names_and_sizes:
            archive.writestr(name, b"x" * size)
    buf.seek(0)
    return zipfile.ZipFile(buf)


def test_rules_match_the_published_windows() -> None:
    first = [("reactwoo-geocore/admin/js/admin.js", 100)]
    check(
        "JS_Zip_1 matches a small .js file at entry 1",
        PACKAGE.foxhole_js_zip_matches(first) == ["Sanesecurity.Foxhole.JS_Zip_1"],
    )
    check(
        "JS_Zip_1 ignores a .js file larger than 512000 bytes",
        PACKAGE.foxhole_js_zip_matches([("reactwoo-geocore/admin/js/admin.js", 512_001)]) == [],
    )
    check(
        "JS_Zip_1 ignores .json",
        PACKAGE.foxhole_js_zip_matches([("reactwoo-geocore/admin/js/admin.json", 100)]) == [],
    )

    second = [("reactwoo-geocore/reactwoo-geocore.php", 20), ("reactwoo-geocore/admin/js/admin.js", 1_000)]
    check(
        "JS_Zip_2 matches a .js file at entry 2 within 51200 bytes",
        PACKAGE.foxhole_js_zip_matches(second) == ["Sanesecurity.Foxhole.JS_Zip_2"],
    )
    oversized = [("reactwoo-geocore/reactwoo-geocore.php", 20), ("reactwoo-geocore/admin/js/admin.js", 51_201)]
    check(
        "JS_Zip_2 ignores a .js file above 51200 bytes",
        "Sanesecurity.Foxhole.JS_Zip_2" not in PACKAGE.foxhole_js_zip_matches(oversized),
    )

    fourth = [
        ("reactwoo-geocore/reactwoo-geocore.php", 10),
        ("reactwoo-geocore/readme.txt", 10),
        ("reactwoo-geocore/includes/class.php", 10),
        ("reactwoo-geocore/admin/js/admin.js", 100_000),
    ]
    check(
        "JS_Zip_4 matches a .js file at entry 4 between 90000 and 512000 bytes",
        PACKAGE.foxhole_js_zip_matches(fourth) == ["Sanesecurity.Foxhole.JS_Zip_4"],
    )
    fourth_small = list(fourth)
    fourth_small[3] = ("reactwoo-geocore/admin/js/admin.js", 1_000)
    check(
        "JS_Zip_4 ignores a small .js file at entry 4",
        PACKAGE.foxhole_js_zip_matches(fourth_small) == [],
    )

    eleventh = [("reactwoo-geocore/file-%02d.php" % index, 10) for index in range(10)]
    eleventh.append(("reactwoo-geocore/admin/js/admin.js", 4_000))
    check(
        "JS_Zip_11 matches a .js file of 3000-512000 bytes at entry 11",
        PACKAGE.foxhole_js_zip_matches(eleventh) == ["Sanesecurity.Foxhole.JS_Zip_11"],
    )
    eleventh_tiny = list(eleventh)
    eleventh_tiny[10] = ("reactwoo-geocore/admin/js/admin.js", 100)
    check(
        "a tiny .js file at entry 11 is outside the size window",
        PACKAGE.foxhole_js_zip_matches(eleventh_tiny) == [],
    )
    check(
        "entry 11 is still a forbidden .js position",
        PACKAGE.javascript_at_forbidden_entries([name for name, _size in eleventh_tiny]) == [11],
    )

    with write_zip(first) as archive:
        stored = entries_of(archive)
    check(
        "a built zip reports the same JS_Zip_1 hit",
        PACKAGE.foxhole_js_zip_matches(stored) == ["Sanesecurity.Foxhole.JS_Zip_1"],
    )


def test_order_is_stable_and_safe() -> None:
    rels = [
        "admin/js/admin.js",
        "admin/css/admin.css",
        "includes/class-rwgc-plugin.php",
        "readme.txt",
        "reactwoo-geocore.php",
        "assets/icon.svg",
        "admin/js/rules.js",
    ]
    rels.extend(f"includes/part-{index:02d}.php" for index in range(30))
    ordered = PACKAGE.order_zip_rels(rels, "reactwoo-geocore.php", "readme.txt")
    reversed_order = PACKAGE.order_zip_rels(list(reversed(rels)), "reactwoo-geocore.php", "readme.txt")
    check("plugin bootstrap is entry 1", ordered[0] == "reactwoo-geocore.php")
    check("readme.txt is entry 2", ordered[1] == "readme.txt")
    check("order does not depend on walk order", ordered == reversed_order)
    check("no .js file sits on a forbidden entry", PACKAGE.javascript_at_forbidden_entries(ordered) == [])
    first_js = next(index for index, rel in enumerate(ordered) if rel.lower().endswith(".js"))
    check(
        "every .js file is after the non-JS files",
        all(not rel.lower().endswith(".js") for rel in ordered[:first_js]),
    )

    packed = [(f"reactwoo-geocore/{rel}", 4_000 if rel.endswith(".js") else 20) for rel in ordered]
    check("ordered names do not match a Foxhole rule", PACKAGE.foxhole_js_zip_matches(packed) == [])

    with write_zip(packed) as archive:
        built = entries_of(archive)
    check("a zip written in that order does not match a Foxhole rule", PACKAGE.foxhole_js_zip_matches(built) == [])

    scarce = ["plugin.php", "readme.txt", "admin/js/a.js", "admin/js/b.js", "includes/only.php"]
    placed = PACKAGE.order_zip_rels(scarce, "plugin.php", "readme.txt")
    check(
        "a short archive still keeps .js off entries 1, 2, and 4",
        PACKAGE.javascript_at_forbidden_entries(placed) == [],
    )
    check("short archive still starts with the bootstrap and readme", placed[:2] == ["plugin.php", "readme.txt"])

    blocked = False
    try:
        PACKAGE.order_zip_rels(["plugin.php", "readme.txt"] + [f"admin/js/{index}.js" for index in range(20)], "plugin.php")
    except RuntimeError:
        blocked = True
    check("refuses an archive that cannot avoid a .js file at entry 4", blocked)


def test_packaged_trees() -> None:
    for target in ("reactwoo", "wporg"):
        root, members = PACKAGE.collect_ordered_members(ROOT, target)
        rels = [rel for rel, _payload in members]
        check(f"{target}: bootstrap is first", rels[0] == "reactwoo-geocore.php")
        check(f"{target}: readme.txt is second", rels[1] == "readme.txt")
        check(f"{target}: no .js file on a forbidden entry", PACKAGE.javascript_at_forbidden_entries(rels) == [])
        buf = io.BytesIO()
        with zipfile.ZipFile(buf, "w", zipfile.ZIP_DEFLATED) as archive:
            for rel, payload in members:
                arcname = f"{root}/{rel}"
                if isinstance(payload, bytes):
                    archive.writestr(arcname, payload)
                else:
                    archive.write(payload, arcname=arcname)
        buf.seek(0)
        with zipfile.ZipFile(buf) as archive:
            hits = PACKAGE.foxhole_js_zip_matches(PACKAGE.zip_file_entries(archive))
            check(f"{target}: built zip matches no Foxhole JS_Zip rule", hits == [])
            if target == "wporg":
                raised = False
                try:
                    PACKAGE._assert_wporg_zip(archive, root)
                except RuntimeError as exc:
                    raised = True
                    print(f"FAIL - wporg zip guard: {exc}")
                    global FAILED
                    FAILED += 1
                if not raised:
                    print("ok  - wporg: directory zip guard still passes")
            main = archive.read(f"{root}/reactwoo-geocore.php").decode("utf-8")
        if target == "wporg":
            check("wporg: distribution constant is rewritten inside the zip", "define( 'RWGC_DISTRIBUTION', 'wporg' );" in main)
            check("wporg: working tree constant is not left in the zip", "define( 'RWGC_DISTRIBUTION', 'reactwoo' );" not in main)
        else:
            check("reactwoo: distribution constant stays reactwoo", "define( 'RWGC_DISTRIBUTION', 'reactwoo' );" in main)


def test_zip_bytes_are_reproducible() -> None:
    members = [
        ("readme.txt", b"readme\n"),
        ("reactwoo-geocore.php", b"<?php\n"),
        ("admin/js/admin.js", b"console.log(1);\n"),
        ("includes/class-rwgc-plugin.php", b"<?php\n"),
    ]
    previous = os.environ.get("SOURCE_DATE_EPOCH")
    os.environ["SOURCE_DATE_EPOCH"] = "1700000000"
    hashes = []
    try:
        for _ in range(2):
            handle = tempfile.NamedTemporaryFile(suffix=".zip", delete=False)
            handle.close()
            path = Path(handle.name)
            try:
                PACKAGE.write_ordered_zip(path, "reactwoo-geocore", members)
                hashes.append(hashlib.sha256(path.read_bytes()).hexdigest())
                with zipfile.ZipFile(path) as archive:
                    info = archive.getinfo("reactwoo-geocore/reactwoo-geocore.php")
                    check(
                        "zip timestamp comes from SOURCE_DATE_EPOCH",
                        info.date_time[:5] == (2023, 11, 14, 22, 13),
                    )
                    check("zip permissions are 0644", (info.external_attr >> 16) & 0o777 == 0o644)
            finally:
                path.unlink(missing_ok=True)
    finally:
        if previous is None:
            os.environ.pop("SOURCE_DATE_EPOCH", None)
        else:
            os.environ["SOURCE_DATE_EPOCH"] = previous
    check("two builds of the same bytes share a SHA-256", len(hashes) == 2 and hashes[0] == hashes[1])


def main() -> None:
    test_rules_match_the_published_windows()
    test_order_is_stable_and_safe()
    test_packaged_trees()
    test_zip_bytes_are_reproducible()
    if FAILED:
        print(f"{FAILED} failed")
        sys.exit(1)
    print("zip JS entry order checks passed")


if __name__ == "__main__":
    main()
