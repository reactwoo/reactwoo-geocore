#!/usr/bin/env python3
"""Reproduce WordPress.org upload pre-checks against the directory zip.

Source of truth (WordPress/wordpress.org):
  wordpress.org/public_html/wp-content/plugins/plugin-directory/shortcodes/class-upload-handler.php
  SHA 474eb62924904af05054905ed30f37873041eaba

Helpers:
  class-trademarks.php SHA a863e609331cd11fccf6881b580e24ffe2ca1e93
  readme/class-validator.php SHA 50822b01fdd4e543da86dd23190bb070d21d2033
  readme/class-parser.php (header sanitizers and license keywords)
  tools/class-helpscout.php REJECTED_SLUG_REGEX
  cli/class-import.php Update URI check
  jobs/class-plugin-scan.php verdict is false only for type ERROR

Account and directory-database checks are reported as SKIP. They need the
WordPress.org site, not the zip.
"""

from __future__ import annotations

import re
import sys
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
DEFAULT_ZIP = ROOT / "reactwoo-geocore-wporg.zip"
REQUIRE_ZIP = "--require-zip" in sys.argv
ZIP_ARG = next((arg for arg in sys.argv[1:] if not arg.startswith("--")), "")

# Upload_Handler::has_reserved_slug(), plus Helpscout::REJECTED_SLUG_REGEX.
RESERVED_SLUGS = {
    "about",
    "admin",
    "browse",
    "category",
    "developers",
    "developer",
    "featured",
    "filter",
    "new",
    "page",
    "plugins",
    "popular",
    "post",
    "search",
    "tag",
    "updated",
    "upload",
    "wp-admin",
    "jquery",
    "wordpress",
    "akismet-anti-spam",
    "site-kit-by-google",
    "yoast-seo",
    "woo",
    "wp-media-folder",
    "wp-file-download",
    "wp-table-manager",
    "acf-repeater",
    "acf-flexible-content",
    "acf-options-page",
    "acf-gallery",
}
REJECTED_SLUG = re.compile(r"^rejected-(.+)-rejected(-\d+)?$", re.I)

# Trademarks::$trademarked_slugs. A term ending in "-" cannot start the slug.
# Any other term cannot appear anywhere, except "woocommerce" may end with
# "-for-woocommerce". Portmanteau "woo" cannot start the slug.
TRADEMARKED_SLUGS = (
    "adobe-",
    "adsense-",
    "advanced-custom-fields-",
    "adwords-",
    "akismet-",
    "all-in-one-wp-migration",
    "amazon-",
    "android-",
    "apple-",
    "applenews-",
    "applepay-",
    "aws-",
    "azon-",
    "bbpress-",
    "bing-",
    "booking-com",
    "bootstrap-",
    "buddypress-",
    "chatgpt-",
    "chat-gpt-",
    "cloudflare-",
    "contact-form-7-",
    "cpanel-",
    "disqus-",
    "divi-",
    "dropbox-",
    "easy-digital-downloads-",
    "elementor-",
    "envato-",
    "fbook",
    "facebook",
    "fb-",
    "fb-messenger",
    "fedex-",
    "feedburner",
    "firefox-",
    "fontawesome-",
    "font-awesome-",
    "ganalytics-",
    "gberg",
    "github-",
    "givewp-",
    "google-",
    "googlebot-",
    "googles-",
    "gravity-form-",
    "gravity-forms-",
    "gravityforms-",
    "gtmetrix-",
    "gutenberg",
    "guten-",
    "hubspot-",
    "ig-",
    "insta-",
    "instagram",
    "internet-explorer-",
    "ios-",
    "jetpack-",
    "macintosh-",
    "macos-",
    "mailchimp-",
    "microsoft-",
    "ninja-forms-",
    "oculus",
    "onlyfans-",
    "only-fans-",
    "opera-",
    "paddle-",
    "paypal-",
    "pinterest-",
    "plugin",
    "skype-",
    "stripe-",
    "tiktok-",
    "tik-tok-",
    "trustpilot",
    "twitch-",
    "twitter-",
    "tweet",
    "ups-",
    "usps-",
    "vvhatsapp",
    "vvcommerce",
    "vva-",
    "vvoo",
    "wa-",
    "webpush-vn",
    "wh4tsapps",
    "whatsapp",
    "whats-app",
    "watson",
    "windows-",
    "wocommerce",
    "woocom-",
    "woocommerce",
    "woocomerce",
    "woo-commerce",
    "woo-",
    "wo-",
    "wordpress",
    "wordpess",
    "wpress",
    "wp-",
    "wp-mail-smtp-",
    "yandex-",
    "yahoo-",
    "yoast",
    "youtube-",
    "you-tube-",
)
FOR_USE_EXCEPTIONS = {"woocommerce"}
PORTMANTEAUS = ("woo",)

# Filesystem::list patterns in process_upload().
VCS_DIR = re.compile(r"\.(git|svn|hg|bzr)$", re.I)
PROHIBITED_FILE = re.compile(r"\.(phar|sh|zip|gz|tgz|rar|tar|7z)$", re.I)
UPDATE_URI = re.compile(r"^(https?://)?(wordpress\.org|w\.org)/plugins?/([^/]+)/?$", re.I)
WP_VERSION = re.compile(r"^\d+\.\d(\.\d+)?$")
PHP_VERSION = re.compile(r"^\d+(\.\d+){1,2}$")
# Current stable from api.wordpress.org/core/version-check/1.7/ on 2026-10-09.
WP_CORE_STABLE_BRANCH = 7.1

PLUGIN_HEADERS = {
    "Name": "Plugin Name",
    "PluginURI": "Plugin URI",
    "Version": "Version",
    "Description": "Description",
    "Author": "Author",
    "AuthorURI": "Author URI",
    "TextDomain": "Text Domain",
    "RequiresWP": "Requires at least",
    "RequiresPHP": "Requires PHP",
    "UpdateURI": "Update URI",
    "RequiresPlugins": "Requires Plugins",
    "License": "License",
}

RESULTS: list[tuple[str, str, str]] = []


def record(status: str, code: str, detail: str) -> None:
    RESULTS.append((status, code, detail))
    print(f"{status:<4} {code}: {detail}")


def sanitize_title_with_dashes(title: str) -> str:
    """ASCII path of sanitize_title_with_dashes() after generate_plugin_slug()."""
    title = re.sub(r"&.+?;", "", title)
    title = title.replace(".", "-")
    title = title.lower()
    title = re.sub(r"[^%a-z0-9 _-]", "", title)
    title = re.sub(r"\s+", "-", title)
    title = re.sub(r"-+", "-", title)
    return title.strip("-")


def generate_plugin_slug(plugin_name: str) -> str:
    """Upload_Handler::generate_plugin_slug()."""
    slug = re.sub(r"[^a-z0-9 _.-]", "", plugin_name, flags=re.I)
    slug = slug.replace("_", "-")
    return sanitize_title_with_dashes(slug)


def check_slug(plugin_slug: str) -> list[str]:
    """Trademarks::check_slug() with no user exceptions."""
    hits: list[str] = []
    for trademark in TRADEMARKED_SLUGS:
        if trademark.endswith("-"):
            if plugin_slug.startswith(trademark):
                hits.append(trademark)
            continue
        if trademark in plugin_slug:
            if trademark in FOR_USE_EXCEPTIONS and plugin_slug.endswith("-for-" + trademark):
                continue
            hits.append(trademark)
    for portmanteau in PORTMANTEAUS:
        if plugin_slug.startswith(portmanteau) and not any(hit.startswith(portmanteau) for hit in hits):
            hits.append(portmanteau + "-")
    return list(dict.fromkeys(hits))


def unexpected_paths(names: list[str]) -> list[str]:
    """Basenames matching process_upload() unexpected_files.

    Directory pattern is .git, .svn, .hg, or .bzr. File pattern is
    .phar, .sh, .zip, .gz, .tgz, .rar, .tar, or .7z.
    """
    bad: list[str] = []
    for name in names:
        parts = [part for part in name.split("/") if part]
        vcs = next((part for part in parts if VCS_DIR.search(part)), "")
        if vcs:
            bad.append(vcs)
            continue
        filename = parts[-1] if parts else name
        if PROHIBITED_FILE.search(filename):
            bad.append(filename)
    return bad


def parse_plugin_headers(text: str) -> dict[str, str]:
    """get_file_data() over the first 8 KiB."""
    chunk = text.replace("\r", "\n")[:8192]
    found: dict[str, str] = {}
    for key, label in PLUGIN_HEADERS.items():
        match = re.search(rf"^[ \t/*#@]*{re.escape(label)}:(.*)$", chunk, re.M | re.I)
        value = match.group(1).strip() if match else ""
        value = re.sub(r"\s*(?:\*/|\?>).*$", "", value).strip()
        found[key] = value
    return found


def parse_readme(text: str) -> tuple[str, dict[str, str], str]:
    lines = text.replace("\r\n", "\n").replace("\r", "\n").split("\n")
    name = ""
    if lines and lines[0].startswith("==="):
        name = lines[0].strip("= \t")
    headers: dict[str, str] = {}
    short_lines: list[str] = []
    seen_header = False
    past_headers = False
    for line in lines[1:]:
        if line.startswith("=="):
            break
        if not line.strip():
            if seen_header:
                past_headers = True
            continue
        if not past_headers and ":" in line and not line.startswith("="):
            key, value = line.split(":", 1)
            headers[key.strip().lower()] = value.strip()
            seen_header = True
            continue
        past_headers = True
        short_lines.append(line.strip())
    return name, headers, " ".join(part for part in short_lines if part).strip()


def license_error(license_name: str) -> str:
    """Parser::validate_license(). Empty string means compatible."""

    def sanitize(value: str) -> str:
        value = value.lower().replace("licence", "license")
        value = value.replace("clauses", "clause").replace("creative commons", "cc")
        value = re.sub(r"(version |v)([0-9])", r"\2", value, flags=re.I)
        value = re.sub(r"(\s*[^a-z0-9. ]+\s*)", "", value, flags=re.I)
        return re.sub(r"\s+", "", value)

    compatible = [
        "GPL",
        "General Public License",
        "MIT",
        "ISC",
        "Expat",
        "Apache 2",
        "Apache License 2",
        "X11",
        "Modified BSD",
        "New BSD",
        "3 Clause BSD",
        "BSD 3",
        "FreeBSD",
        "Simplified BSD",
        "2 Clause BSD",
        "BSD 2",
        "MPL",
        "Mozilla Public License",
        "Public Domain",
        "CC0",
        "Unlicense",
        "CC BY",
        "zlib",
    ]
    incompatible = [
        "4 Clause BSD",
        "BSD 4 Clause",
        "Apache 1",
        "CC BY-NC",
        "CC-NC",
        "NonCommercial",
        "CC BY-ND",
        "NoDerivative",
        "EUPL",
        "OSL",
        "Personal use",
        "without permission",
        "without prior auth",
        "you may not",
        "Proprietery",
        "proprietary",
    ]
    cleaned = sanitize(license_name)
    for match in incompatible:
        if sanitize(match) in cleaned:
            return "invalid_license"
    for match in compatible:
        if sanitize(match) in cleaned:
            return ""
    return "unknown_license"


def plugin_author_uri_clash(plugin_uri: str, author_uri: str) -> bool:
    return bool(plugin_uri) and bool(author_uri) and plugin_uri == author_uri


def run_fixtures() -> None:
    slug = generate_plugin_slug("ReactWoo Geo Core")
    record(
        "PASS" if slug == "reactwoo-geo-core" else "FAIL",
        "fixture_generate_plugin_slug",
        f"ReactWoo Geo Core -> {slug}",
    )
    woo_hits = check_slug("reactwoo-geo-core")
    record(
        "PASS" if woo_hits == [] else "FAIL",
        "fixture_trademark_reactwoo",
        "reactwoo-geo-core hits " + (", ".join(woo_hits) if woo_hits else "nothing"),
    )
    woo_prefix = check_slug("woo-maps")
    record(
        "PASS" if "woo-" in woo_prefix else "FAIL",
        "fixture_trademark_woo_prefix",
        "woo-maps hits " + ", ".join(woo_prefix),
    )
    record(
        "PASS" if plugin_author_uri_clash("https://reactwoo.com/", "https://reactwoo.com/") else "FAIL",
        "fixture_plugin_author_uri",
        "identical URIs are the plugin_author_uri rejection",
    )
    record(
        "PASS" if not plugin_author_uri_clash("https://reactwoo.com/geo-core/", "https://reactwoo.com/") else "FAIL",
        "fixture_distinct_uris",
        "geo-core URI differs from the author URI",
    )
    record(
        "PASS" if re.search(r"[^\d.]", "1.9.0-beta") and not re.search(r"[^\d.]", "1.9.0") else "FAIL",
        "fixture_invalid_version",
        "only digits and periods are accepted",
    )
    sample = unexpected_paths(
        [
            "reactwoo-geocore/vendor/maxmind/web-service-common/dev-bin/release.sh",
            "reactwoo-geocore/vendor/foo/library.tgz",
            "reactwoo-geocore/vendor/foo/.git/config",
            "reactwoo-geocore/readme.txt",
        ]
    )
    record(
        "PASS" if sample == ["release.sh", "library.tgz", ".git"] else "FAIL",
        "fixture_unexpected_files",
        "flagged " + ", ".join(sample),
    )
    record(
        "PASS" if "wordpress" in RESERVED_SLUGS and REJECTED_SLUG.search("rejected-demo-rejected") else "FAIL",
        "fixture_reserved_slug",
        "reserved list and rejected-* -rejected regex",
    )


def run_zip(path: Path) -> None:
    with zipfile.ZipFile(path) as archive:
        names = [name for name in archive.namelist() if not name.endswith("/")]
        roots = {name.split("/", 1)[0] for name in names}
        if roots != {"reactwoo-geocore"}:
            record("FAIL", "zip_root", "expected reactwoo-geocore/, found " + ", ".join(sorted(roots)))
            return
        main_name = "reactwoo-geocore/reactwoo-geocore.php"
        readme_name = "reactwoo-geocore/readme.txt"
        if main_name not in names:
            record("FAIL", "no_plugin_file", main_name)
            return
        headers = parse_plugin_headers(archive.read(main_name).decode("utf-8", errors="replace"))
        readme_text = archive.read(readme_name).decode("utf-8", errors="replace") if readme_name in names else ""

    flagged = unexpected_paths(names)
    record(
        "PASS" if not flagged else "FAIL",
        "unexpected_files",
        "none" if not flagged else ", ".join(flagged[:20]),
    )
    record(
        "PASS" if headers["Name"] else "FAIL",
        "no_name",
        headers["Name"] or "Plugin Name is empty",
    )
    slug = generate_plugin_slug(headers["Name"]) if headers["Name"] else ""
    record(
        "PASS" if slug else "FAIL",
        "unsupported_name",
        f"assigned slug {slug or '(empty)'}",
    )
    reserved = slug in RESERVED_SLUGS or bool(REJECTED_SLUG.search(slug))
    record(
        "PASS" if slug and not reserved else "FAIL",
        "reserved_name",
        f"{slug} is not reserved" if not reserved else f"{slug} is reserved",
    )
    name_hits = check_slug(slug) if slug else ["(no slug)"]
    record(
        "PASS" if name_hits == [] else "FAIL",
        "trademarked_name",
        "name and slug clear trademarks" if not name_hits else "hits " + ", ".join(name_hits),
    )
    record(
        "PASS" if len(slug) >= 5 else "FAIL",
        "slug_length",
        f"{len(slug)} characters (minimum 5)",
    )
    record(
        "PASS" if headers["Description"] else "FAIL",
        "no_description",
        "header Description is set" if headers["Description"] else "empty",
    )
    record(
        "PASS" if headers["Version"] else "FAIL",
        "no_version",
        headers["Version"] or "empty",
    )
    invalid_version = bool(headers["Version"]) and bool(re.search(r"[^\d.]", headers["Version"]))
    record(
        "PASS" if headers["Version"] and not invalid_version else "FAIL",
        "invalid_version",
        headers["Version"] or "empty",
    )
    clash = plugin_author_uri_clash(headers["PluginURI"], headers["AuthorURI"])
    record(
        "PASS" if not clash else "FAIL",
        "plugin_author_uri",
        f"Plugin URI {headers['PluginURI'] or '(empty)'}; Author URI {headers['AuthorURI'] or '(empty)'}",
    )
    record(
        "PASS" if readme_text else "FAIL",
        "no_readme",
        readme_name if readme_text else "missing readme.txt and readme.md",
    )

    readme_name_value, readme_headers, short = parse_readme(readme_text) if readme_text else ("", {}, "")
    license_name = readme_headers.get("license", "")
    record(
        "PASS" if license_name else "FAIL",
        "no_license",
        license_name or "readme License is empty",
    )
    if license_name:
        license_code = license_error(license_name)
        record(
            "FAIL" if license_code == "invalid_license" else "PASS",
            "readme_license",
            license_code or f"compatible ({license_name})",
        )
        if license_code == "unknown_license":
            record("NOTE", "unknown_license", "readme validator note, not an upload rejection")

    record(
        "PASS" if readme_name_value else "FAIL",
        "readme_plugin_name",
        readme_name_value or "missing === Plugin Name ===",
    )
    if readme_name_value:
        readme_hits = check_slug(generate_plugin_slug(readme_name_value))
        record(
            "PASS" if not readme_hits else "FAIL",
            "readme_trademarked_name",
            "clear" if not readme_hits else ", ".join(readme_hits),
        )
        if readme_name_value != headers["Name"]:
            record(
                "WARN",
                "mismatched_plugin_name",
                f"readme {readme_name_value!r} != header {headers['Name']!r} (Plugin Check warning, not an upload rejection)",
            )
        else:
            record("PASS", "mismatched_plugin_name", "readme title matches Plugin Name")

    stable = readme_headers.get("stable tag", "")
    if not stable or "trunk" in stable.lower():
        record("WARN", "stable_tag_invalid", stable or "(missing)")
    else:
        record("PASS", "stable_tag", stable)
    record(
        "PASS" if stable == headers["Version"] else "FAIL",
        "stable_tag_mismatch",
        f"Stable tag {stable or '(missing)'} vs Version {headers['Version'] or '(missing)'}",
    )

    requires = readme_headers.get("requires at least", "")
    requires_php = readme_headers.get("requires php", "")
    tested = readme_headers.get("tested up to", "")
    record(
        "PASS" if requires == headers["RequiresWP"] and WP_VERSION.match(requires) else "FAIL",
        "requires_at_least",
        f"readme {requires or '(missing)'} vs header {headers['RequiresWP'] or '(missing)'}",
    )
    record(
        "PASS" if requires_php == headers["RequiresPHP"] and PHP_VERSION.match(requires_php) else "FAIL",
        "requires_php",
        f"readme {requires_php or '(missing)'} vs header {headers['RequiresPHP'] or '(missing)'}",
    )
    tested_ok = bool(WP_VERSION.match(tested)) and float(tested.split(".")[0] + "." + tested.split(".")[1]) <= WP_CORE_STABLE_BRANCH + 0.1
    tested_current = bool(WP_VERSION.match(tested)) and float(f"{tested.split('.')[0]}.{tested.split('.')[1]}") == WP_CORE_STABLE_BRANCH
    record(
        "PASS" if tested_ok and tested_current and tested.count(".") == 1 else "FAIL",
        "tested_up_to",
        f"{tested or '(missing)'} against WordPress {WP_CORE_STABLE_BRANCH:.1f}",
    )

    tags = [tag.strip() for tag in readme_headers.get("tags", "").split(",") if tag.strip()]
    ignored = [tag for tag in tags if tag.lower() in {"plugin", "wordpress"}]
    record(
        "PASS" if len(tags) <= 5 and not ignored else "WARN",
        "readme_tags",
        f"{len(tags)} tags" + (f", ignored {ignored}" if ignored else ""),
    )
    record(
        "PASS" if 0 < len(short) <= 150 else "WARN",
        "short_description",
        f"{len(short)} characters (maximum 150; over is a readme warning, not an upload rejection)",
    )

    update_uri = headers["UpdateURI"]
    if not update_uri:
        record("PASS", "invalid_update_uri", "Update URI header is absent")
    else:
        match = UPDATE_URI.match(update_uri)
        ok = bool(match) and match.group(3) == slug
        record(
            "PASS" if ok else "FAIL",
            "invalid_update_uri",
            update_uri + ("" if ok else " (import throws unless it is wordpress.org/plugins/{slug}/)"),
        )

    if headers["RequiresPlugins"]:
        record("WARN", "requires_plugins", headers["RequiresPlugins"])
    else:
        record("PASS", "requires_plugins", "header is absent")

    domain = headers["TextDomain"]
    if domain == slug:
        record("PASS", "textdomain_mismatch", f"{domain} matches the assigned slug")
    else:
        record(
            "WARN",
            "textdomain_mismatch",
            (
                f"Text Domain {domain or '(empty)'} != assigned slug {slug}. "
                "Not an upload rejection. class-upload-handler.php stores header_textdomain and does not compare it. "
                "Plugin Check emits textdomain_mismatch from add_result_warning_for_file when --slug is the generated slug "
                "(jobs/class-plugin-scan.php passes that slug). Verdict becomes false only when a result type is ERROR."
            ),
        )

    for code, detail in (
        ("submissions_paused", "holiday flag is an account setting"),
        ("2fa_required", "account setting"),
        ("unsafe_email", "account setting"),
        ("queue_limit", "account queue"),
        ("already_exists", "needs the plugin directory database"),
        ("already_submitted", "needs the plugin directory database"),
        ("already_exists_in_the_wild", "needs wporg_stats_get_plugin_name_install_count"),
        ("readme_name_clash", "needs the plugin directory database"),
        ("failed_checks", "Plugin Check verdict is run separately; only ERROR fails the upload"),
        ("error_upload", "PHP upload and site upload-size limit"),
    ):
        record("SKIP", code, detail)


def main() -> None:
    print("WordPress.org upload pre-check")
    print("handler: class-upload-handler.php @ 474eb62924904af05054905ed30f37873041eaba")
    run_fixtures()
    zip_path = Path(ZIP_ARG) if ZIP_ARG else DEFAULT_ZIP
    if zip_path.is_file():
        print(f"zip: {zip_path}")
        run_zip(zip_path)
    elif REQUIRE_ZIP:
        record("FAIL", "zip_missing", str(zip_path))
    else:
        record("SKIP", "zip_missing", f"{zip_path} not built; source fixtures still ran")

    failed = [item for item in RESULTS if item[0] == "FAIL"]
    print(f"\n{len(RESULTS)} checks, {len(failed)} failed")
    if failed:
        sys.exit(1)


if __name__ == "__main__":
    main()
