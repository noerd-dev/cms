# Website Module

The public website frontend for the Noerd CMS. This module is a boilerplate: `php artisan noerd:install-website` copies it into a project as `app-modules/website`, where it becomes project-owned code that you customize freely (layout, elements, styling).

## What it provides

- **Catch-all slug routing** — every active CMS page is served under its slug, per language (the tenant's default language without a prefix, other languages with their `/{lang}/...` slugs as stored). Registered with the lowest priority so it never shadows other module routes.
- **Homepage** — `/index` renders the page configured as the tenant's homepage (CMS → Settings).
- **Page elements** — the blade/YAML element pairs in `resources/views/components/elements/` are discovered by the CMS element picker; the element data is localized and rendered through the shared `PageElementService` (a thin subclass of the CMS implementation).
- **Navigation & globals** — a view composer shares `$navigation` (all CMS navigation keys) and `$globals` (global parameters) with every view.
- **Multi-language** — languages come from the CMS language configuration (`cms_languages`); the language switcher swaps between the page's slugs.
- **Contact form** — a Livewire form storing `form_requests` rows, protected by Cloudflare Turnstile (`services.turnstile`).
- **Tenant resolution** — `WebsiteMiddleware` resolves the tenant by `?uuid=`, `?uuid=` (the backend quick-menu link) or falls back to the first tenant for single-tenant installations.

## Requirements

The module requires `noerd/cms` (models, language codes, element processing) and `noerd/noerd`. Both are declared in `composer.json` and are installed before this module.

## Assets

The layout (`resources/views/components/layouts/weblayout.blade.php`) uses the host project's Vite entries `resources/css/app.css` and `resources/js/app.js` — Tailwind CSS must be available there. The module ships no own CSS/JS build.

## Configuration

`config/website.php`:

- `media_url` (`MEDIA_URL`) — optional prefix for media file paths rendered by image elements.
- `google_maps_key` (`GOOGLE_MAPS_API_KEY`) — required by the google-map element.

`config/services.php` → `services.turnstile` (`TURNSTILE_SITE_KEY` / `TURNSTILE_SECRET_KEY`) — optional. When either key is unset the widget is not rendered and the contact form skips the check; once both are set the token is validated fail-closed, so a missing or invalid token rejects the submission.

## Adding a page element

Create a blade/YAML pair in `resources/views/components/elements/`:

- `my-element.blade.php` — a Livewire single-file component using the `NoerdElement` trait; it receives the localized element data as `$element`.
- `my-element.yml` — `title`, `description`, `group` and the `fields` the CMS editor shows (block-style YAML, English labels).

The kebab-case file name maps to the snake_case element key (`my-element` → `my_element`).

## Tests

Pest tests live in `tests/`; run them from the host project with `php artisan test --compact app-modules/website/tests`.
