# AGENTS.md — WordPress PSR Request Handler

## Project Overview

A PSR-15 server request handler wrapper around WordPress core. Enables WordPress to operate in a PSR-7 request/response context, allowing integration with persistent servers (Swoole, ReactPHP, Amp) and PSR-15 middleware. Part of the WordPress-PSR project.

Default branch is `main`.

## Build, Test & Lint Commands

```bash
composer install                    # Install dependencies (includes rector transform)
composer test                       # Run PHPUnit tests
composer test:lint                  # Run PHPCS + PHPStan
composer fix                        # Auto-fix PHPCS violations

# Individual tools
vendor/bin/phpunit                  # PHPUnit tests
vendor/bin/phpcs --standard=phpcs.xml src/   # Code style check
vendor/bin/phpstan analyse          # Static analysis
vendor/bin/rector process           # Apply Rector transformations
```

## Project Structure

```
request-handler/
├── src/
│   ├── WordPressRequestHandler.php # Main PSR-15 RequestHandlerInterface impl
│   ├── wp-functions.php            # WordPress function replacements
│   └── pluggable-functions.php     # Pluggable function overrides
├── tests/                          # PHPUnit tests
├── wordpress/                      # WordPress core (installed via Composer)
├── rector.php                      # Rector config — transforms WP core for long-running use
├── phpcs.xml                       # PHPCS config (WordPress-Core standard)
├── phpunit.xml                     # PHPUnit config
├── DECISION.md                     # Architecture decisions
├── composer.json
└── README.md
```

## Code Style & Conventions

- **PHP version**: >= 8.1
- **Coding standard**: WordPress-Core (via `phpcs.xml`)
- **Namespace**: `WordPressPsr\` (PSR-4 → `src/`)
- **Test namespace**: `WordPressPsr\Tests\` (PSR-4 → `tests/`)
- **Minimum WordPress**: 6.0

## Key Patterns

- Implements `Psr\Http\Server\RequestHandlerInterface`
- Converts PSR-7 requests to WordPress globals, runs WordPress, captures output as PSR-7 response
- `rector.php` transforms WordPress core: `exit`/`die` → `wp_exit()`, `header()` → action hooks
- WordPress core is a Composer dependency (`johnpbloch/wordpress-core`)
- Post-install/post-update hooks automatically run Rector transforms
- Uses `dflydev/fig-cookies` for PSR-7 cookie handling

## Important Notes

- This is a **library** (type: `library`), not a WordPress plugin
- The `wordpress/` directory contains Composer-installed WordPress core — do not edit directly
- Rector runs automatically on `composer install/update` — transforms are applied to WordPress core files
- Designed for long-running PHP processes — WordPress `exit`/`die` calls are intercepted
