# Website Module

The public website frontend for the Noerd CMS. This module is a boilerplate: `php artisan noerd:install-website` copies it into a project as `app-modules/website`, where it becomes project-owned code that you customize freely (layout, elements, styling).

## What it provides

- **Catch-all slug routing** — every active CMS page is served under its slug, per language (the tenant's default language without a prefix, other languages with their `/{lang}/...` slugs as stored). Registered with the lowest priority so it never shadows other module routes.
- **Homepage** — `/index` renders the page configured as the tenant's homepage (CMS → Settings); a missing or inactive homepage answers 404.
- **Blog** — `/blog` lists the tenant's published articles (`Article::published()`), `/blog/{slug}` shows one; the featured image is a media id, resolved to its delivery URL.
- **Managed redirects** — a path no active page claims is looked up in the CMS redirects and answered with a 301 to the target page — only ever to a path of this site, never another host.
- **Page meta** — the layout prints `meta_title`, `meta_description`, `meta_noindex` and the Open Graph image of the page (`Noerd\Cms\Support\PageMeta`).
- **Page elements** — the blade/YAML element pairs in `resources/views/components/elements/` are discovered by the CMS element picker; the element data is localized and rendered through the shared `PageElementService` (a thin subclass of the CMS implementation).
- **Navigation & globals** — a view composer shares `$navigation` (all CMS navigation keys, each a tree of items with `children` by `parent_id` and `sort_order`, only active pages of the tenant) and `$globals` (global parameters) with the layouts and page views, memoized per request.
- **Multi-language** — languages come from the CMS language configuration (`cms_languages`); the language switcher swaps between the page's slugs.
- **Contact form** — a Livewire form storing `form_requests` rows, optionally protected by Cloudflare Turnstile (`services.turnstile`, see Configuration).
- **Tenant resolution** — `WebsiteMiddleware` resolves the tenant by `?uuid=` in the query string (the backend quick-menu link; never from a request body), the uuid remembered in the session, or falls back to the first tenant for single-tenant installations. Whoever knows a tenant's uuid sees its active pages; an installation that serves several tenants on their own domains should bind the tenant to the host instead.
- **Safe output** — rich text fields are sanitized by the CMS when the element data is prepared, plain text is escaped, URLs from editor data pass `Noerd\Cms\Support\SafeUrl` (http/https, mailto, tel, site paths — never `javascript:` or another host), values in Alpine/Livewire directives go through `Js::from()`. An element without a template renders nothing (a hint only with `app.debug`).

## Requirements

The module requires `noerd/cms` (models, language codes, element processing) and `noerd/noerd`. Both are declared in `composer.json` and are installed before this module.

## Assets

The layout (`resources/views/components/layouts/weblayout.blade.php`) uses the host project's Vite entries `resources/css/app.css` and `resources/js/app.js` — Tailwind CSS must be available there. The module ships no own CSS/JS build.

## Configuration

`config/website.php`:

- `media_url` (`MEDIA_URL`) — optional prefix for media file paths rendered by image elements.
- `google_maps_key` (`GOOGLE_MAPS_API_KEY`) — required by the google-map element.

Cloudflare Turnstile is optional and NOT a dependency of this module. To use it, install the package yourself (`composer require ryangjchandler/laravel-cloudflare-turnstile`) and configure `config/services.php` → `services.turnstile` (`enabled`, `TURNSTILE_SITE_KEY` / `TURNSTILE_SECRET_KEY`). Without the package, or while `services.turnstile.enabled` is off, the widget is not rendered and the contact form skips the check; with both the token is validated fail-closed, so a missing or invalid token rejects the submission.

## Adding a page element

Create a blade/YAML pair in `resources/views/components/elements/`:

- `my-element.blade.php` — a Livewire single-file component using the `NoerdElement` trait; it receives the localized element data as `$element`.
- `my-element.yml` — `title`, `description`, `group` and the `fields` the CMS editor shows (block-style YAML, English labels).

The kebab-case file name maps to the snake_case element key (`my-element` → `my_element`).

## Commands

- `php artisan noerd:update-website` — nothing to publish (the configuration is merged at runtime); it exists so `noerd:update-all` covers the module.

## Tests

Pest tests live in `tests/`; run them from the host project with `php artisan test --compact app-modules/website/tests`.
