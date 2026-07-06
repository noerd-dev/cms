---
name: cms-website-import
description: |
  Use this skill when migrating a hand-written Blade website (under `resources/views/`) into the Noerd CMS module. Trigger on requests like "migrate the static site to the CMS", "import these blade pages into the CMS", "transfer hardcoded pages and nav to the database", "convert this Blade site into CMS pages and elements", or when the user explains they just installed `app-modules/cms` in a project that already has a static site and wants pages, navigation, forms, and collections seeded into the DB with the website routes wired up. Skip for greenfield CMS work, for editing an already-imported page, or for non-CMS Blade refactors.
license: MIT
metadata:
  author: noerd
---

# CMS Website Import

Migrate a hand-written Blade website into the Noerd CMS module. The CMS ships as a complete subsystem (Page, ElementPage, Navigation, Collection, FormType, CmsLanguage, WebsiteController, WebsiteMiddleware, weblayout, ~26 ready-made elements) and is multi-tenant + multi-language. This skill produces a deterministic procedure that turns static templates into DB-driven pages.

## Deliverables

A successful run produces:

- One timestamped Laravel migration in `database/migrations/` that seeds **Pages**, **ElementPage** rows, **Navigation**, **GlobalParameters**, **CollectionDefinitions** and **Collection** rows for every tenant. Idempotent. Empty `down()`.
- New element `*.blade.php` + `*.yml` pairs in `resources/views/components/elements/` for any section that has no matching CMS element.
- New form YAMLs in `app-configs/cms/forms/`, synced via `php artisan forms:sync`.
- A verification checklist proving every migrated URL renders with no browser errors.

## Working procedure (run phases in order)

### Phase 1 — Inventory

Do **not** write code yet. Build a complete inventory and present it to the user as markdown for confirmation.

1. List every `*.blade.php` under `resources/views/` (exclude `app-modules/`).
2. Categorize files: page templates (full pages), layouts, partials, components.
3. For each page record: `@extends`, `@section` content, headings, paragraphs, button labels, image `src`, meta tags, and every `route()`/anchor link.
4. Extract navigation markup (usually inside a layout) — list every link with label + href.
5. Extract footer markup the same way.
6. Read `routes/web.php` and any controllers under `app/Http/Controllers/` that render these views — map URL → view.
7. Identify **repeated structures** across pages (services grids, team grids, testimonials, sliders, project cards). These become collections.
8. Identify **forms** (`<form>`, Livewire `wire:submit` handlers). These become FormTypes.
9. Output the inventory as a table:

   | Source view | URL | Layout | Sections (top→bottom) | Images | Forms | Collection candidates |
   |-------------|-----|--------|-----------------------|--------|-------|----------------------|

   Stop and ask the user to confirm before continuing.

### Phase 2 — Element mapping

For each section in the inventory, map to an existing CMS element. Discover the full set with `\Noerd\Cms\Helpers\FieldHelper::getAllElements()`. Common mapping:

| Source markup pattern               | Existing element key                  |
|-------------------------------------|---------------------------------------|
| Single rich-text block              | `text_block_1_column`                 |
| Two text columns                    | `text_block_2_column`                 |
| Two columns 2/3 + 1/3               | `text_block_2_column_2_3`             |
| Three text columns                  | `text_block_3_column`                 |
| Page hero / large headline + intro  | `text_block_header`                   |
| Single image                        | `image_block_1_column`                |
| Two/three image grid                | `images_block_2_column` / `_3_column` |
| Slider/carousel from a collection   | `slider`                              |
| Card grid of collection items       | `collection_cards`                    |
| Map embed                           | `google_map`                          |
| CTA button / link button            | `button_link`                         |

If no existing element matches, generate a new pair from `templates/element.blade.php.stub` + `templates/element.yml.stub`. **Naming rule:** kebab-case file → snake_case key (`team-member-grid.blade.php` ↔ `team_member_grid`). Place in **`resources/views/components/elements/`** (project-level — `FieldHelper::getAllElements()` discovers it via the second glob).

### Phase 3 — Pages, Collections, Navigation

Produce a written seed plan (table form) before generating SQL. Rules:

- **Pages** — one `pages` row per inventory page. Translatable fields are JSON arrays even with a single language: `name => json_encode(['de' => 'Startseite'])`, `slug => json_encode(['de' => '/startseite'])`. Slugs always start with `/`. `layout` defaults to `null` (resolves to `weblayout` at render). Update `cms_settings.homepage_page_id` for the homepage.
- **Collections** — for each repeated structure, seed a `collection_definitions` row via the migration's `upsertCollectionDefinition(...)` helper (see `templates/seed_imported_website.php.stub`). Use `hasPage: true` if items get their own URL (services, projects); `hasPage: false` for embedded data (sliders, testimonials, team members). Each collection item is a `pages` row with `collection_id` set.
- **Navigation** — insert into `cms_navigations` with `navigation_key` `main` or `footer` (lowercase, matching the demo seeder). Set `page_id` for internal links, `link` for external. Use `parent_id` + `sort_order` for nested menus.
- **Globals** — site-wide values (phone, email, site title) go in `global_parameters` as translatable JSON values keyed by name.

### Phase 4 — Element data shape (CRITICAL)

Every `element_page.data` field that the yml declares as `translatableText` / `translatableRichText` **must be wrapped per language**, even with one language:

```php
'data' => json_encode([
    'text'  => ['de' => '<p>Inhalt …</p>'],   // translatable
    'image' => '/images/foo.jpg',              // flat — non-translatable type
]),
```

`HandlesPageElements::localizeArray()` decides translatability by checking whether **all** array keys are language codes (`de, en, fr, es, it, nl`). Anything else stays flat. Element rows must set `sort` (1, 2, 3 …) and the correct snake_case `element_key`.

### Phase 5 — Forms

For every `<form>` found:

1. Convert each input to a YAML field (`name`, `label`, `type`, `required`, `validation`, optional `error_messages`, `placeholder`).
2. Save as `app-configs/cms/forms/{key}.yml`, modeled on `templates/form.yml.stub`.
3. Append `php artisan forms:sync --force` to the post-migration step list.
4. Replace the original `<form>` in any leftover Blade with the CMS form-render component (or rely on the new CMS page).

### Phase 6 — Routing & middleware (verify, don't recreate)

The CMS module already provides:

- `WebsiteController::slug()` — slug-based lookup with `whereJsonContains('slug->de', …)`.
- Catch-all route registered in `WebsiteServiceProvider::registerCatchAllRoutes()` (lowest priority via `$this->app->booted()`).
- `WebsiteMiddleware` — tenant + language resolution.

Verify these checks before declaring success:

- `bootstrap/providers.php` includes `Noerd\Website\Providers\WebsiteServiceProvider`.
- `routes/web.php` has **no** conflicting `Route::get('/{any}', …)` with higher priority.
- `bootstrap/app.php` registers the `web` middleware group.
- At least one `Tenant`, one `CmsLanguage` flagged `is_default`, and `cms_settings.homepage_page_id` is populated.

If the project still has `Route::get('/', fn() => view('welcome'))` returning a static page, instruct the user to remove it (or change `/` to redirect to `/index` so the catch-all serves the homepage — the same pattern used in `routes/web.php`).

### Phase 7 — Generate the migration

Generate a fresh migration file:

```bash
php artisan make:migration seed_imported_website_content --no-interaction
```

Replace the body with content modeled on `templates/seed_imported_website.php.stub` (which mirrors `app-modules/cms/database/migrations/2026_02_17_000000_seed_demo_website_data.php`). Requirements:

- Iterate `cms_settings` rows (or every `tenants` row if `cms_settings` is unpopulated).
- **Idempotent**: every insert wrapped in `exists()` / `whereJsonContains()` check or `updateOrInsert()`.
- `down()` is empty — never delete imported data on rollback.
- Use `DB::table()` not Eloquent (so it doesn't depend on app boot order).

## Verification (mandatory final step)

Run all of these and report results:

1. `php artisan migrate --no-interaction` — must complete without errors.
2. `php artisan forms:sync --force` — every form synced.
3. `get-absolute-url` MCP tool → build URL for each migrated page.
4. `browser-logs` MCP tool → zero errors after each URL load.
5. Visually compare homepage and one detail page to the original Blade output. Report any missing content.
6. `php artisan test --compact` if CMS tests exist.
7. `vendor/bin/pint --dirty --format agent` on every new PHP file.

## Common pitfalls

- **Translatable fields must be JSON arrays.** `'name' => 'Startseite'` silently breaks the admin UI — always `['de' => 'Startseite']`.
- **Slugs always start with `/`.** `whereJsonContains('slug->de', '/foo')` only matches exact strings.
- **Element keys are snake_case; element files are kebab-case.** Mismatches cause `processPageElements()` to fall back to `text_block_1_column`.
- **Navigation `navigation_key`** — the `Navigation::navigationKey` mutator uppercases on save, but `WebsiteService::getNavigation()` queries with whatever string is passed (`main`, `footer`). Keep the seeder lowercase to match the demo migration; the mutator handles both.
- **Don't store images as base64.** Copy assets to `public/storage/...` (or wherever the project keeps uploads) and store the URL string in `data`.
- **Don't use `App\Models\…`** — those models likely don't exist in the project. Use the namespaced module models (`Noerd\Cms\Models\Page`) or `DB::table()`.
- **`cms_settings.homepage_page_id`** must point to the imported homepage; otherwise visiting `/` finds nothing.

## Reference files (read-only)

| File | Use |
|------|-----|
| `app-modules/cms/database/migrations/2026_02_17_000000_seed_demo_website_data.php` | **canonical migration template** |
| `app-modules/cms/database/migrations/2026_01_30_080256_seed_default_homepage.php` | homepage + `cms_settings` setup |
| `app-modules/cms/database/migrations/2025_12_15_173420_seed_default_english_language.php` | language seed pattern |
| `app-modules/cms/src/Models/Page.php` | translatable casts |
| `app-modules/cms/website-boilerplate/src/Models/Page.php` | website-side `elements()` ordering |
| `app-modules/cms/website-boilerplate/src/Models/ElementPage.php` | join + `element_key` fallback |
| `app-modules/cms/src/Models/Navigation.php` | navigation_key mutator |
| `app-modules/cms/src/Models/Collection.php` & `CollectionDefinition.php` | collection schema |
| `app-modules/cms/src/Models/CmsLanguage.php` | default-language guarantees |
| `app-modules/cms/src/Helpers/FieldHelper.php` | element discovery globs |
| `app-modules/cms/src/Helpers/CollectionHelper.php` | collection field resolution |
| `app-modules/cms/src/Traits/HandlesPageElements.php` | translatable-array detection |
| `app-modules/cms/website-boilerplate/src/Controllers/WebsiteController.php` | slug resolution |
| `app-modules/cms/website-boilerplate/src/Middleware/WebsiteMiddleware.php` | tenant + language |
| `app-modules/cms/website-boilerplate/src/Providers/WebsiteServiceProvider.php` | catch-all route |
| `app-modules/cms/website-boilerplate/resources/views/page.blade.php` | element render loop |
| `app-modules/cms/website-boilerplate/resources/views/components/layouts/weblayout.blade.php` | layout slot + nav |
| `app-modules/cms/website-boilerplate/resources/views/components/elements/*.{blade.php,yml}` | element pair reference |
| `app-modules/cms/app-configs/cms/forms/contact.yml` | form YAML reference |

## Templates

Stubs for generated files live next to this skill:

- `templates/seed_imported_website.php.stub` — migration boilerplate
- `templates/element.blade.php.stub` — anonymous Livewire element
- `templates/element.yml.stub` — element field schema
- `templates/form.yml.stub` — form definition

Copy a stub, fill in the placeholders (`{{ … }}`), then write to the destination path.
