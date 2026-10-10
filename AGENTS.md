# AGENTS.md

For product architecture, workflow, and release process see `docs/AGENTS.md`,
`docs/geo-core-cursor-master-plan.md`, and `.cursor/rules/`.

## Cursor Cloud specific instructions

This repo is a **single WordPress plugin** (`reactwoo-geocore`) — a MaxMind-based
geolocation engine (detection, shortcodes, REST, rules, page-variant routing).
There is no bundled dev server or Docker; "running the app" means loading the
plugin inside a WordPress install.

### Dependencies / tests
- PHP 8.3 + Composer are provisioned on the VM. The startup update script runs
  `composer install`, which is required because the dev dependency **PHPUnit is
  gitignored** (`/vendor/phpunit/`, `/vendor/bin/`); the runtime deps
  (`geoip2`, `maxmind-db`) are committed under `vendor/`.
- Test commands live in `composer.json` scripts (`test`, `test:rules`,
  `test:rwgc-rule-evaluator`, `test:ai-snapshot`, `test:all`). They use stubbed
  WP functions (`tests/bootstrap.php`) and need **no WordPress or database**.
- Two tests are **broken in the committed tree (pre-existing, not env issues)**:
  - `vendor/bin/phpunit` aborts with a fatal in
    `tests/Targeting/RWGCAssistantTargetServiceTest.php` (a `class WP_Post` is
    declared inside a test method — PHP forbids nested class declarations).
  - `composer test:rwgc-rule-evaluator` fails "Hide mode should suppress…"
    because the CLI script never loads `includes/functions-rwgc.php`, so
    `rwgc_visibility_mode_allows_render()` is undefined and
    `should_render_content()` falls back to the raw match.
  - `composer test:rules` and `composer test:ai-snapshot` pass. To exercise the
    rest of the PHPUnit suite, run it against the valid files, e.g.
    `vendor/bin/phpunit tests/Engine tests/Ai tests/TargetingRuleEvaluatorTest.php`.

### Running the plugin end-to-end (WordPress + MySQL)
A working WordPress 7.0 install lives at `/home/ubuntu/wp` with the plugin
symlinked in (`wp-content/plugins/reactwoo-geocore -> /workspace`). MariaDB and
WP-CLI are installed. Neither service auto-starts, so each session:
- Start MariaDB (data dir `/var/lib/mysql`):
  `sudo mkdir -p /run/mysqld && sudo chown mysql:mysql /run/mysqld`
  then `sudo -u mysql /usr/sbin/mariadbd --datadir=/var/lib/mysql --socket=/run/mysqld/mysqld.sock --pid-file=/run/mysqld/mysqld.pid &`
- Start the site: `cd /home/ubuntu/wp && wp server --host=127.0.0.1 --port=8080 --allow-root`
- Admin login: `admin` / `admin123`; Geo admin at `admin.php?page=rwgc-dashboard`.
- DB creds (in `wp-config.php`): db `wordpress`, user `wp`, pass `wppass`.

### Geo detection notes
- MaxMind reads `wp-content/uploads/reactwoo-geocore/GeoLite2-Country.mmdb`. A
  free MaxMind **test** DB is placed there for dev; it only knows MaxMind's
  synthetic test IPs (e.g. `81.2.69.160`→GB, `89.160.20.112`→SE,
  `216.160.83.56`→US). Real production detection needs a real GeoLite2 DB.
- Visitor IP is read from `X-Forwarded-For` etc.; private/localhost IPs are not
  in the test DB, so browser hits from `127.0.0.1` fall back to
  `fallback_country` (default `US`, `source=fallback_error`). Simulate a country
  with `curl -H "X-Forwarded-For: 81.2.69.160" .../reactwoo-geocore/v1/location`.
- The admin "MaxMind license key not configured" banner refers to auto-update of
  the DB via a MaxMind account; it does not mean the DB is missing.
