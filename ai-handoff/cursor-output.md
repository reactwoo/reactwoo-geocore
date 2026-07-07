# Cursor output — Critical bug investigation (v1.8.113)

## Status

**done**

## Bug fixed

Rule Tester parent-aware Elementor assignment discovery passed every raw Elementor parent ID to descendants, even when that parent had no Geo Core assignment row. `assignment_hidden_by_ancestor()` can only traverse assignment rows, so an assigned widget under an unassigned wrapper could miss a hidden assigned ancestor and be reported as visible even though the frontend would suppress it. Document-level Elementor assignments had the same disconnect from element assignments, while frontend document targeting can suppress the entire rendered document.

## Files changed

- `includes/class-rwgc-elementor-assignment-discovery.php` — descendants now inherit the nearest assigned ancestor ID through unassigned wrappers, seeded by an assigned document root when present.
- `tests/Targeting/RWGCElementorAssignmentDiscoveryTest.php` — regression tests for assigned section → unassigned container → assigned widget and document assignment → unassigned container → assigned widget.

## What was not changed

- No broad Rule Tester or Elementor frontend refactor.
- Did not change rendered product impact dynamic query behavior; a lower-severity tester completeness concern remains around serialized product rule meta lookup.

## Commands run and results

```bash
sudo apt-get update && sudo apt-get install -y php-cli php-xml php-mbstring composer
composer install --no-interaction --prefer-dist
php -l includes/class-rwgc-elementor-assignment-discovery.php
php -l tests/Targeting/RWGCElementorAssignmentDiscoveryTest.php
vendor/bin/phpunit --bootstrap tests/bootstrap.php --stderr tests/Targeting/RWGCElementorAssignmentDiscoveryTest.php
git checkout -- vendor/autoload.php vendor/composer/InstalledVersions.php vendor/composer/LICENSE vendor/composer/autoload_classmap.php vendor/composer/autoload_psr4.php vendor/composer/autoload_real.php vendor/composer/autoload_static.php vendor/composer/installed.json vendor/composer/installed.php vendor/composer/platform_check.php
php -l includes/class-rwgc-elementor-assignment-discovery.php
php -l tests/Targeting/RWGCElementorAssignmentDiscoveryTest.php
```

Results:

- PHP lint passed for changed files.
- Isolated PHPUnit regression passed: `OK (2 tests, 10 assertions)`.
- Composer-generated tracked vendor metadata was restored after validation.

## Remaining errors

None for the fixed issue.
