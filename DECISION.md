# Decision: Eliminate the `superdav42/wordpress-core` Fork

**Issue:** [#7 — Review: Eliminate need for wordpress-core fork entirely](https://github.com/WordPress-PSR/request-handler/issues/7)
**Status:** Decided
**Date:** 2026-03-26

---

## Background

`wordpress-psr/request-handler` currently depends on `superdav42/wordpress-core`, a fork of
`johnpbloch/wordpress-core`. The fork was last synced with upstream WordPress in November 2023
and carries a maintenance burden: every WordPress release requires a manual rebase.

### What the fork actually changes

Audit of the fork's non-upstream commits reveals two categories of change:

**Category 1 — Hook wrapper functions added to `wp-includes/functions.php`**

Four thin wrapper functions that fire WordPress actions before delegating to the native PHP call:

```php
function wp_exit($message = '')        { do_action('wp_exit', $message);                    exit($message); }
function wp_header($header, ...)       { do_action('wp_header', $header, ...);               header($header, ...); }
function wp_header_remove($header)     { do_action('wp_header_remove', $header);             header_remove($header); }
function wp_set_cookie($name, ...)     { do_action('wp_set_cookie', $name, ...);             setcookie($name, ...); }
```

These are the targets of the four Rector rules already in `src/Rector/`:
`NoExit`, `NewHeaderFunction`, `NewHeaderRemoveFunction`, `NewCookieFunction`.

**Category 2 — `require_once` → `require` in several admin files**

Files changed: `wp-admin/admin.php`, `wp-admin/menu-header.php`, `wp-admin/index.php`,
`wp-login.php`, `wp-includes/template.php`, `wp-admin/setup-config.php`, `wp-cron.php`,
`wp-admin/edit.php`, `wp-load.php`.

Purpose: allow WordPress files to be re-included across multiple requests in a long-running PHP
process (Swoole/ReactPHP/RoadRunner). `require_once` would silently skip re-execution on the
second request.

**Category 3 — Conditional function guards in `wp-admin/menu-header.php`**

`if (!function_exists(...))` guards added so the file can be included more than once per process.

---

## Options Analysed

### Option A — Composer post-install script + Rector (issue's primary candidate)

Run `vendor/bin/rector process` as a `post-install-cmd` / `post-update-cmd` Composer script
against the installed `johnpbloch/wordpress-core` package. Patch files (via
`cweagans/composer-patches`) handle the structural `require_once` → `require` changes.

**Feasibility assessment:**

| Concern | Finding |
|---------|---------|
| Rector as runtime dep | Rector is already in `require-dev`. Moving it to `require` adds ~15 MB to production installs. Alternatively, a standalone Rector PHAR can be downloaded at install time. |
| Rector correctness | The four custom rules (`NoExit`, `NewHeaderFunction`, `NewHeaderRemoveFunction`, `NewCookieFunction`) are already written and tested. They target simple AST patterns (function calls and `exit`/`die` nodes). Reliability is high. |
| `require_once` → `require` patches | `cweagans/composer-patches` applies unified diff patches. These patches are stable across WP minor versions because the surrounding context rarely changes. They will need updating on major WP releases — but that is a bounded, detectable failure (patch apply fails loudly). |
| Install time | Rector on the full WP codebase takes ~60–120 s on a cold run. With `--cache-dir` this drops to ~5 s on subsequent installs. Acceptable for a library dependency. |
| Idempotency | Rector is idempotent: running it twice on already-transformed code is a no-op. |
| CI impact | CI already runs `composer install`; the post-install hook runs automatically. No pipeline changes needed beyond ensuring `rector/rector` is available. |
| WP update path | `composer update johnpbloch/wordpress-core` triggers `post-update-cmd`, re-applying all transformations. Zero manual steps. |

**Verdict: Viable. Recommended as the immediate solution.**

---

### Option B — PHP stream wrapper / autoloader hook (issue's "Option B")

Intercept `require`/`include` via a custom `php://` stream wrapper or `stream_wrapper_register`.
Transform source on-the-fly before PHP parses it. Cache transformed files to disk.

**Feasibility assessment:**

| Concern | Finding |
|---------|---------|
| Correctness | Requires a full PHP tokeniser/parser in the stream wrapper. Rector's PhpParser backend is the only reliable option — making this Option A with extra latency. |
| Performance | Every uncached `require` pays a parse + transform + write-cache round-trip. First-request latency in a long-running process is acceptable; cold-start on a traditional FPM setup is not. |
| Fragility | Stream wrappers interact poorly with OPcache (OPcache caches the *original* stream, not the transformed output, unless `opcache.file_cache` is configured to point at the transformed files). This is a known, hard-to-debug failure mode. |
| Complexity | ~300–500 lines of wrapper code that must be loaded before any WordPress `require`. Ordering is fragile. |
| Maintenance | Any PHP version that changes stream wrapper semantics breaks the approach silently. |

**Verdict: Not recommended.** The OPcache interaction alone disqualifies it for production use.
The complexity cost is not justified when Option A achieves the same goal more reliably.

---

### Option C — WordPress core upstream contribution (issue's "Option C")

Propose `wp_exit()`, `wp_header()`, `wp_header_remove()`, `wp_set_cookie()` for inclusion in
WordPress core. The `wp_die()` handler system and `wp_send_json()` are precedents.

**Feasibility assessment:**

| Concern | Finding |
|---------|---------|
| Technical merit | Strong. These are thin, hookable wrappers over PHP builtins. The pattern is already established in WP core (`wp_die`, `wp_send_json`, `wp_redirect`). |
| Acceptance likelihood | Moderate. WordPress core team accepts well-argued, backward-compatible additions. The `wp_exit()` wrapper in particular has been discussed in the community. |
| Timeline | 6–18 months from proposal to stable release, assuming acceptance. Realistically 2–3 WP major versions. |
| `require_once` → `require` | This change is *not* suitable for upstream. It is a long-running-process concern that the core team will not accept for traditional FPM deployments. This part of the fork cannot be upstreamed. |

**Verdict: Pursue in parallel as a long-term goal, not a replacement for Option A.**
The `require_once` changes mean the fork cannot be fully eliminated via upstream alone.

---

## Decision: Option A (Composer post-install + Rector + patches)

**Recommendation: Implement Option A immediately. File upstream proposals for the hook functions
as a parallel long-term effort.**

### Rationale

1. **The four hook functions are the only semantic changes.** The `require_once` → `require`
   changes are mechanical and patch-file-stable. Together they are fully automatable.

2. **Rector rules already exist.** `src/Rector/` contains all four rules. The transformation
   logic is proven — it is what produced the current fork. Running it at install time is
   lower risk than maintaining a diverging fork.

3. **The fork is stale.** Last synced November 2023. WordPress 6.4, 6.5, 6.6, 6.7, 6.8, 6.9
   have been released since. Every release widens the diff and increases merge conflict risk.

4. **Option B fails on OPcache.** Any production PHP deployment uses OPcache. The stream
   wrapper approach is not production-safe without non-standard OPcache configuration.

5. **Option C cannot fully replace the fork.** The `require_once` → `require` changes will
   not be accepted upstream. Option C reduces but does not eliminate the fork dependency.

---

## Implementation Plan

### Phase 1 — Patch files for structural changes (1–2 days)

1. Install `johnpbloch/wordpress-core` at a pinned version (e.g. `6.7.*`) alongside the fork.
2. Diff the fork against the equivalent upstream tag for each modified file:
   - `wp-admin/admin.php` — `require_once` → `require` for menu files
   - `wp-admin/menu-header.php` — conditional function guards
   - `wp-admin/index.php`, `wp-login.php`, `wp-includes/template.php`,
     `wp-admin/setup-config.php`, `wp-cron.php`, `wp-admin/edit.php`, `wp-load.php`
3. Generate unified diff patch files, store in `patches/` directory.
4. Add `cweagans/composer-patches` to `require` in `composer.json`.
5. Register patches in `composer.json` `extra.patches` section.

### Phase 2 — Rector post-install hook (1 day)

1. Move `rector/rector` from `require-dev` to `require` **or** download the Rector PHAR
   in the post-install script (preferred: keeps production footprint minimal).
2. Add a `post-install-cmd` and `post-update-cmd` script to `composer.json`:

   ```json
   "scripts": {
     "post-install-cmd": ["@rector-transform"],
     "post-update-cmd":  ["@rector-transform"],
     "rector-transform": [
       "vendor/bin/rector process --config rector.php --no-progress --quiet"
     ]
   }
   ```

3. Update `rector.php` to target `vendor/johnpbloch/wordpress-core/` (the new install path)
   rather than `wordpress/`.

4. Add `--cache-dir .rector-cache` to the Rector invocation and add `.rector-cache/` to
   `.gitignore`.

### Phase 3 — Switch dependency (half day)

1. In `composer.json`:
   - Remove `superdav42/wordpress-core` from `require`.
   - Remove the VCS repository entry for `git@github.com:superdav42/wordpress-core.git`.
   - Add `johnpbloch/wordpress-core: ^6.7` to `require`.
   - Remove the `replace` entry for `superdav42/wordpress-core`.
2. Run `composer install` and verify the post-install hook applies all transformations.
3. Run the test suite: `composer test`.

### Phase 4 — CI verification (half day)

1. Add a CI step that verifies the transformed files contain `wp_exit`, `wp_header`, etc.
   (grep-based smoke check — fast and explicit).
2. Verify the existing GitHub Actions matrix (PHP 8.1/8.2/8.3) passes end-to-end.

### Phase 5 — Upstream proposal (ongoing, low priority)

1. Open a WordPress Trac ticket proposing `wp_exit()`, `wp_header()`, `wp_header_remove()`,
   `wp_set_cookie()` as core functions.
2. Reference this project as a real-world use case.
3. Track in a separate issue; not a blocker for Phase 1–4.

---

## Risks and Mitigations

| Risk | Likelihood | Mitigation |
|------|-----------|------------|
| Patch fails on WP major version update | Medium | CI smoke-check catches it immediately. Patch regeneration is a 30-min task. |
| Rector rule misses a call site | Low | Rules target specific AST node types; no regex. Existing test suite covers the transformed paths. |
| `rector/rector` in `require` bloats production installs | Low-Medium | Use Rector PHAR downloaded at install time instead; or accept the dependency (Rector is MIT, well-maintained). |
| OPcache caches pre-transformation files | N/A | Option A writes transformed files to disk before PHP parses them; OPcache sees the transformed version. |
| WordPress removes a patched file | Low | Patch apply failure is loud; CI catches it on the next `composer update`. |

---

## Acceptance Criteria

- [ ] `composer install` on a clean checkout produces a functional WordPress installation
      without referencing `superdav42/wordpress-core`.
- [ ] `vendor/johnpbloch/wordpress-core/wp-includes/functions.php` contains `wp_exit`,
      `wp_header`, `wp_header_remove`, `wp_set_cookie` after install.
- [ ] `vendor/johnpbloch/wordpress-core/wp-admin/admin.php` uses `require` (not `require_once`)
      for menu files after install.
- [ ] `composer test` passes on PHP 8.1, 8.2, 8.3.
- [ ] No VCS repository entry for `superdav42/wordpress-core` in `composer.json`.
