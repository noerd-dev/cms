@verbatim
## Website Module

The public website frontend for `noerd/cms` (Composer package `noerd/website`, namespace
`Noerd\Website`, requires `noerd/cms`): it renders a tenant's CMS pages, elements, navigation,
collections, articles and the contact form. It is NOT a tenant app (no `app-configs/`, no
navigation, no backend screens) and NOT a package you `composer require` from Packagist: the
CMS module ships it as a boilerplate that `noerd:install-website` copies into
`app-modules/website`, where the project customises layouts, elements and styling freely. The
framework rules come from the `noerd/noerd` guideline and the CMS rules from the `noerd/cms`
guideline — this block only adds what is specific to this module.

### Boilerplate, not a submodule
- `app-modules/website` is a plain directory tracked by the HOST repository, required through
  the `app-modules/*` path repository. `noerd:install-website` (shipped by `noerd/cms`, source
  `app-modules/cms/website-boilerplate/`) refuses to overwrite a target that has its own `.git`
  and otherwise replaces the whole directory — customisations live here, never in the boilerplate
- An installed copy diverges from the boilerplate by design. Compare with the boilerplate before
  porting a fix in either direction; this guideline describes the copy as the boilerplate ships it

### Request flow
- `WebsiteMiddleware` (alias `website`) resolves the tenant: `?uuid=` from the QUERY STRING only
  (never a request body), else the session `uuid` → `tenants.uuid`, otherwise the FIRST tenant,
  404 when none exists. It sets `TenantContext` BEFORE any language lookup, stores
  `session('selectedTenantId')`, sets the request attributes `tenant_id` / `tenant`, shares
  `globals` and `tenant` with every view, switches the VISITOR's language (`Noerd\Website\Support\WebsiteLanguage`, session key
  `website.language` — never the backend's `selectedLanguage`) on
  `?language=` only to a code of `CmsLanguageCodes::active()` and resets a language the tenant
  does not run to its default. Read the tenant in controllers/components from
  `request()->attributes->get('tenant_id')` — there is no noerd user on the frontend. Residual
  risk by design: whoever knows a tenant's uuid sees its ACTIVE pages on any host serving this
  website; an installation with several tenants on own domains binds the tenant to the host
- The read models carry NO tenant scope: every public query names the tenant AND `is_active`
  itself (pages, the homepage, redirect targets, navigation targets, collection entries);
  articles go through `Article::published()`. There is no route that serves a page by its id
- Routes (`routes/website-routes.php`, middleware `['web', WebsiteMiddleware::class]`):
  `/index` (`website.index`, the homepage from `CmsSetting.homepage_page_id`), `/blog`
  (`website.articles`), `/blog/{slug}` (`website.article`, `Article::published()`). The catch-all
  `/{slug?}/{slug2?}/{slug3?}` (`website.slug`) is registered in `$this->app->booted()` so it
  gets the LOWEST priority — every other module's routes win; keep it there
- `WebsiteController::slug()` joins the segments to `/a/b/c` and matches it per language of
  `CmsLanguageCodes::active()` (default first, setting the visitor's language), only active pages of
  the tenant; an unmatched path tries a managed redirect, then 404. `index()` answers 404 for a
  missing, inactive or foreign homepage. Both pass `meta` (`Noerd\Cms\Support\PageMeta::of()`:
  `meta_title`, `meta_description`, `meta_noindex`, `og_image` as absolute delivery URL) to the
  layout, which prints it into `<head>`
- A view composer on the LAYOUTS and page views only (`website::components.layouts.*`,
  `website::page|article|articles|welcome`, never `*`) adds `$navigation` — every navigation
  group keyed by its lower-case key (`navigation.main`, `navigation.footer`, …;
  `WebsiteService::getNavigations()`: one query for the items, one `whereIn` for their pages, a
  tree by `parent_id` + `sort_order` with `children` of `{name, href, new_tab}`, only active pages
  of the tenant, links through `SafeUrl`) — and `$globals` (a translatable parameter in the
  selected language). `WebsiteService` is bound `scoped()` and memoizes per request
- Managed redirects (`cms_redirects`): `WebsiteController::resolveRedirect()` through the local
  `Redirect` read model whose `normalizePath()` MIRRORS `Noerd\Cms\Models\Redirect::normalizePath()`
  — the two must stay identical. A redirect only answers a path no active page claims and only
  ever targets a path of this site (`SafeUrl::isSitePath()`), never another host

### Rendering
- Cookie banner and Google tag: the layout asks the CMS's `WebsiteTrackingService::forRequest($tenantId,
  $host)` — the ONE gate — and includes `website::partials.tracking-head` (styles, the consent-bound
  loader `window.loadWebsiteGoogleTag`) and `website::partials.cookie-consent` (the banner of
  `devrabiul/laravel-cookie-consent`, whose analytics category calls the loader). The tag never loads
  without consent and never on a host that shares the backend session
- The contact form submits through `Noerd\Cms\Forms\FormSubmissionService::submit()` (form type
  `contact`): the form YAML decides fields, rules and mails, a missing form definition refuses the
  submission — the component never writes `cms_form_requests` itself
- `resources/views/page.blade.php` renders `website::layouts.{page.layout ?? 'weblayout'}`
  (`<x-dynamic-component>`) and one Livewire component per element. Layouts live in
  `resources/views/components/layouts/` — the CMS page editor discovers them through
  `config('cms.layout_path')` — empty by default, then the `components/layouts` folder of this
  module's `website` view namespace
- `PageElementService` extends the CMS service (`Noerd\Cms\Services\PageElementService`, like the
  boilerplate): `processPageElements($page, $language)` localizes each element's JSON and resolves
  media ids; `getComponentMapping()` maps snake_case keys to Livewire components
  `{module}::elements.{kebab-file}` (the Livewire namespace of the module that ships the file —
  `website::elements.slider`; there is no bare `addLocation()`) found in `app-modules/*/resources/views/components/elements/`, the
  project's `resources/views/components/elements/` and `config('cms.page_elements_path')`.
  The running text of the CMS document editor is the CMS element `rich_text` (rendered by the
  CMS, sanitized). Unknown keys render nothing — only with `app.debug` or locally a box naming the
  key (never the element data)
- Editor data is untrusted on the public page: every field a YAML types as rich text
  (`…RichText`, element-collection rows included) is SANITIZED by the CMS service when the data is
  prepared (`RichTextValues`) — that is what keeps `{!! $element->text !!}` safe; a plain text
  field is printed with `{{ }}`, never `{!! !!}`. Every URL from editor data goes through
  `Noerd\Cms\Support\SafeUrl` (`href()`, `orNull()`, `cssUrl()` for a CSS `url()`); a value
  inside an Alpine/Livewire directive through `Js::from()`; `target="_blank"` carries
  `rel="noopener noreferrer"`
- Elements are Blade + YAML pairs (`text-block-1-column.blade.php` + `.yml`; kebab file →
  snake element key). The Blade side is a Volt class `new class extends Component { use NoerdElement; }`
  — `Noerd\Website\Traits\NoerdElement` mounts `data` (as `$this->element`) and `collections`
  and offers `collection($key)` / `hasCollection()` / `getCollectionData()`; the abstract
  `Noerd\Website\Components\BaseWebsiteElement` is the class-based variant with an `initialize()`
  hook. The YAML (`title`, `description`, `group`, `fields`) is the editor form, see the CMS
  guideline
- `src/Models/` are lightweight READ models on the CMS tables (`Page` → `cms_pages` with JSON
  `name`/`slug`, `ElementPage` → `content_elements` ordered by `sort`, `Collection`
  (`entries()`: the active entries of its own tenant, rich text sanitized),
  `GlobalParameter`, `Language`, `Navigation`, `Redirect`, `FormRequest` (the only one with
  `BelongsToTenant`), `Tenant` → `tenants`). Write paths and business rules belong to
  `Noerd\Cms\Models\*`; `Article` and `CmsSetting` are used from there directly
- `website::contact-form` (Livewire) validates and stores a `FormRequest` for the session tenant.
  Cloudflare Turnstile is OPTIONAL and no dependency: the rule
  (`RyanChandler\LaravelCloudflareTurnstile\Rules\Turnstile`) and the widget
  (`<x-dynamic-component component="turnstile">`, never a static `<x-turnstile>` tag, which fails
  to compile without the package) are reached by class name only while
  `services.turnstile.enabled` is set and the package is installed — then fail-closed.
  `config/website.php` (`media_url`, `google_maps_key`) is merged at boot and never published
- `website::frontend-language-switcher` lists the tenant's active `cms_languages` and maps the
  current slug to its translation on `setLanguage()` — only a code it offers, and it only
  redirects to a path of this site (`SafeUrl::isSitePath()`; a referer path `//evil.com` falls
  back to `/`)
- Articles (`ArticleController`): `featured_image` is a media id, resolved through
  `PageMeta::imageUrl()`; the rich-text body is printed through `HtmlSanitizer::sanitize()`
- Formatting on the public frontend uses the TENANT locale with an explicit id:
  `CurrencyHelper::format($amount, $tenantId)`, `FormatHelper::date($date, FormatHelper::tenantLocale($tenantId))`
- Translations: `resources/lang/de.json` (English keys); the provider also registers the
  `website::` translation namespace. Views/Livewire namespace `website::`

### Commands
- `php artisan noerd:install-website` — lives in `noerd/cms`; copies the boilerplate, ensures
  the `app-modules/*` path repository and the quick-menu link, offers to migrate
- `php artisan noerd:update-website` — deliberately a no-op (nothing is published, the config
  is merged at runtime); it exists so `noerd:update-all` covers the module

### Tests
- Pest tests flat in `tests/`, bound to `\Tests\TestCase` + `RefreshDatabase` (host-bound,
  MySQL); run with `php artisan test --compact app-modules/website/tests`
- Factories in `database/factories/` build the read models (`Page`, `Navigation`, `Language`,
  `FormRequest`, `Tenant`); requests carry `?uuid={tenant uuid}` to select the tenant
- Prove mechanics, never the current configuration (see the `noerd-testing` skill)

### Reference implementations
- `src/Middleware/WebsiteMiddleware.php` — tenant + language resolution for a public frontend
- `resources/views/components/elements/text-block-1-column.blade.php` + `.yml` — the minimal
  element pair
- `resources/views/components/contact-form.blade.php` — a frontend Livewire form writing a CMS
  table for the session tenant, with an optional package reached by class name only
@endverbatim
