# AGENTS.md — noerd/website

Contributor notes for humans and AI agents working on the Website module. The rules for
building WITH noerd (lists, details, pages, modals, modules, tests) come from the `noerd/noerd`
Boost guideline and skills, the CMS rules from the `noerd/cms` guideline; the module-specific
rules are in `resources/boost/guidelines/core.blade.php`. All are rendered into the host project's
agent files by `php artisan boost:update` (add `noerd/website` to the `packages` array in
`boost.json` — Boost resolves it through the `app-modules/*` path repository).

## What this module is

The public frontend of `noerd/cms`: tenant resolution by `?uuid=`, slug routing to CMS pages,
layouts, page elements (Blade + YAML pairs rendered as Volt components), navigation, articles,
the language switcher and a contact form (optionally protected by Cloudflare Turnstile). It is neither a tenant app nor a
git submodule: `noerd:install-website` (shipped by the CMS module) copies the boilerplate from
`app-modules/cms/website-boilerplate/` into `app-modules/website`, and from then on the copy is
project code tracked by the host repository. An installed copy diverges from the boilerplate —
that is expected.

## Layout

- `resources/views/components/layouts/` — page layouts; the CMS editor discovers them via
  `config('cms.layout_path')` (empty by default: this folder, through the `website` view namespace)
- `resources/views/components/elements/` — element pairs (`{name}.blade.php` + `{name}.yml`);
  the frontend finds them through the CMS `PageElementService` (which `src/Services/PageElementService`
  extends)
- `resources/views/components/` — Livewire `contact-form`, `frontend-language-switcher`
  (namespace `website::`); `resources/views/page|article|articles|welcome.blade.php`
- `routes/website-routes.php` — `/index`, `/blog`, `/blog/{slug}`; the catch-all slug route is
  registered in the provider's `booted()` callback
- `src/Middleware/WebsiteMiddleware.php`, `src/Controllers/` (`WebsiteController`,
  `ArticleController`), `src/Services/` (`WebsiteService`, `PageElementService`), `src/Models/` (read models on the `cms_*` tables), `src/Traits/NoerdElement.php`,
  `src/Components/BaseWebsiteElement.php`, `src/Commands/WebsiteUpdateCommand.php`,
  `src/Providers/WebsiteServiceProvider.php`
- `config/website.php` (merged, never published), `database/factories/`, `tests/` (Pest, flat),
  `resources/lang/de.json`

## Commands

- `php artisan noerd:install-website` — in the CMS module; copies the boilerplate (refuses when
  the target is its own git repository)
- `php artisan noerd:update-website` — no-op by design, exists for `noerd:update-all`

## Working on the module

- Tests bind `\Tests\TestCase` + `RefreshDatabase` (host-bound, MySQL). From the host project:
  `php artisan test --compact app-modules/website/tests`. Requests select the tenant with
  `?uuid=`; `PageElementRenderTest` shows how to point `CMS_PAGE_ELEMENTS_PATH` at the shipped
  elements. Tests prove mechanics, never the current configuration.
- Format from the host project root with an explicit path: `vendor/bin/pint app-modules/website`
  (the module has no Pint config of its own)
- The module depends on `noerd/noerd` and `noerd/cms` only; never reference another optional
  module. Project-specific page data lives in the CMS (`custom_attributes` on `Page`, element
  YAML fields), not in new columns here
- Keep `Redirect::normalizePath()` identical to the CMS model (the CMS suite's
  `RedirectNormalizePathParityTest` compares them); keep the catch-all route last
- When a feature changes: update the affected element YAML, `resources/lang/de.json`, the tests,
  `resources/boost/guidelines/core.blade.php` and `README.md`; decide explicitly whether the
  change also belongs in `app-modules/cms/website-boilerplate/`
