# AGENTS.md — noerd/cms

Contributor notes for humans and AI agents working on the CMS module. The rules for building
WITH noerd (lists, details, pages, modals, modules, tests) come from the `noerd/noerd` Boost
guideline and skills; the module-specific rules are in
`resources/boost/guidelines/core.blade.php`. Both are rendered into the host project's agent
files by `php artisan boost:update` (add `noerd/cms` to the `packages` array in `boost.json`).

## What this module is

A multi-tenant, multi-language CMS on the noerd framework: Pages with a visual element builder,
database-backed Collections, hierarchical Navigation, Forms with email notifications, Articles
with authors, per-tenant Languages, Global Parameters and a token-authenticated form API. The
`website-boilerplate/` folder is a template that `noerd:install-website` copies into a project
as its `app-modules/website` frontend module.

## Layout

- `app-configs/cms/` — YAML templates (lists/, details/, settings/, forms/, navigation.yml);
  the installed copy lives in the host's `app-configs/cms/` — change both
- `resources/views/components/` — Livewire single-file components, flat, Livewire namespace `cms::`
- `src/Models/`, `src/Services/`, `src/Repositories/` (collection definitions),
  `src/Helpers/` (FieldHelper, CollectionHelper), `src/Http/` (form API),
  `src/Commands/` (`CmsInstallCommand`, `CmsUpdateCommand`, `SyncFormTypesCommand`,
  `InstallWebsiteBoilerplateCommand`), `src/Providers/CmsServiceProvider.php`
  (field types `collection-select`, `element-collection`, `homepage-select`; relation types
  `pageRelation`, `authorRelation`)
- `database/migrations|factories/`, `tests/` (Pest), `resources/lang/de.json`
- The tenant app name is `CMS` (uppercase). Every table carries the `cms_` prefix and is created by
  the module migrations; `cms_settings` is a tenant singleton (unique `tenant_id`)
- Settings, languages and collection definitions are admin-only screens, registered through
  `ComponentAccessGuard::registerAdminComponents()` in the provider — never through the `setup`
  middleware, which would switch the selected app away from CMS
- `skills/cms-website-import/` — the Boost skill that migrates a static Blade site into the CMS; its
  `templates/` hold the element/form/seeder stubs the skill copies

## Commands

- `php artisan noerd:install-cms` — first installation (asks for the tenant assignment)
- `php artisan noerd:update-cms` — idempotent YAML update + starter-homepage seeding,
  discovered by `noerd:update-all`
- `php artisan cms:sync-form-types` — sync `app-configs/cms/forms/*.yml` into `form_types`
- `php artisan noerd:install-website` — copy the website boilerplate into the project

## Working on the module

- Tests: `php artisan test --compact app-modules/cms/tests` inside a host (Pest; tests prove
  mechanics, never the current YAML configuration; run sequentially — never in parallel with
  other suites when the test database is shared) or standalone with `composer install &&
  vendor/bin/pest` (Orchestra Testbench + sqlite via `Noerd\Cms\Tests\TestCase`, the setup CI
  runs). Every test file binds `Noerd\Cms\Tests\TestCase` itself; the fixtures are
  `CreatesCmsUser`, `CreatesElementFixtures` and `CreatesCollectionDefinitions`
- Format from the host project root with an explicit path: `vendor/bin/pint app-modules/cms`
  (a plain `--dirty` run silently skips submodule files)
- Keep the module independent of other optional modules. The ONLY allowed website touchpoint
  is the plain container string key `Noerd\Website\Services\PageElementService` in the
  ServiceProvider; project-specific fields go into `custom_attributes` (Page, Article, Author)
- Never hardcode language codes — resolve them through `Noerd\Cms\Support\CmsLanguageCodes`
  (`active()` per tenant, `known()` as the recognition baseline)
- A change to the website boilerplate must keep it consistent with the CMS schema and
  services — it ships inside this package and is copied verbatim into projects
- When a feature changes: update the YAML in both places, `resources/lang/de.json`, the tests,
  `resources/boost/guidelines/core.blade.php`, the matching `docs/*.md` page and, if the import
  workflow is affected, `skills/cms-website-import/SKILL.md`

## Release

- Tagging a version requires the `composer.json` `"version"` field to equal the tag in the
  tagged commit. A pushed tag is immutable — never move it; ship fixes as the next patch release.
- The retired `v1.x` tag series (pre-0.1 history) was deleted before the 0.2 release so a plain
  `composer require noerd/cms` resolves the 0.x line — never recreate tags above the current line.
