## CMS Module

The CMS module is a multi-tenant, multi-language content management system. It provides pages with a visual element builder, collections for structured data, hierarchical navigation, forms with email notifications, articles with publication scheduling, and language management.

### Pages

- **Model:** `Noerd\Cms\Models\Page` with traits `BelongsToTenant`, `HasFactory`
- **Translatable fields** (cast as arrays): `name`, `slug`, `meta_title`, `meta_description`
- **Data column:** `data` (array cast) stores collection-specific field data
- **Casts:** `meta_noindex`, `is_active` (boolean; defaults to `true`)
- **Relationships:** `elements()` hasMany ElementPage, `collection()` belongsTo Collection
- The page editor is a custom `NoerdDetail` component (`cms::page-detail`): `$detailModel = Page::class`, `$detailPrimary = 'pageId'`, form state in `$detailData` (array — the Eloquent model is never a component property). It overrides `mount()` (collection entry detection), `store()` (plain page vs. collection entry) and `updated()` (slug derivation) and delegates to three services: `PageSlugService` (slug generation + uniqueness), `PageElementEditorService` (add/move/duplicate/delete elements, copy a page) and `CollectionEntryStore` (persist collection entries and element-collection rows)
- Element mutations are guarded like `store()`: every mutator starts with `canSaveObject()` (the generic `WriteGuardHook` only covers `store`/`delete`)
- Slugs are auto-generated from the name; non-default languages get a language prefix (e.g., `/en/page-name`)
- Layouts are discovered from `config('noerd_cms.layout_path')` (`Noerd\Cms\Support\PageLayouts`), default `app-modules/website/resources/views/components/layouts/`

@verbatim
<code-snippet name="Page Detail Component Pattern" lang="php">
public ?string $detailPrimary = 'pageId';

public $detailModel = Page::class;

public function mount(?string $collectionKey = null): void
{
    $this->initDetail();

    $page = $this->modelId ? (Page::find($this->modelId) ?? new Page()) : new Page();

    // Translatable fields must be initialized as arrays for every active language
    // (LanguageFilterTrait::activeLanguageCodes() — never a hard-coded code list)
    $this->detailData = $page->toArray();
    foreach (['name', 'slug'] as $field) {
        if (! is_array($this->detailData[$field] ?? null)) {
            $this->detailData[$field] = array_fill_keys($this->activeLanguageCodes(), '');
        }
    }
}
</code-snippet>
@endverbatim

### Images Store the Media ID

- An image field (`type: image` in an element YAML or a collection definition) stores the MEDIA ID,
  never a URL: the media disk mirrors the library's folder tree, so a file's path changes the
  moment it is moved. `page-detail`, `element-page-detail` and `element-collection-row-detail`
  write `$media->id`
- The URL is resolved on the way OUT, centrally in `Noerd\Cms\Support\MediaValues` — called from
  `HandlesPageElements::processPageElements()` and `PageElementService::processCollectionPageData()`
  (plus the website copies). Element Blades therefore keep reading a plain URL from
  `$element->image` and no project's element templates have to change. A value that is not
  a bare integer — a legacy `/storage/…` string, an external URL — is passed through untouched
- The migration `store_media_ids_in_cms_data` rewrites existing URLs in `cms_pages.data` and
  `cms_page_elements.data` to ids; it must run BEFORE `media:restructure`, which changes the paths
  those URLs were built from

### Elements

- Elements are Blade + YAML pairs in `app-modules/*/resources/views/components/elements/`
- File naming: kebab-case files (e.g., `text-block-1-column.blade.php`) map to snake_case element keys (`text_block_1_column`)
- `FieldHelper::getAllElements()` discovers all elements across modules
- `FieldHelper::getAllElementsGrouped()` groups elements by their `group` YAML property
- Translatable fields are auto-initialized with empty values for each active language

@verbatim
<code-snippet name="Element YAML Structure" lang="yaml">
title: 'Single Column Text'
description: 'Simple text block with rich text editor'
group: 'Text'
fields:
  - name: detailData.text
    label: Text
    type: translatableRichText
    colspan: 12
</code-snippet>
@endverbatim

### Collections

- Definitions are per-tenant rows in the `collection_definitions` table (**model:** `Noerd\Cms\Models\CollectionDefinition`), managed via the `/cms/collection-definitions` UI
- Resolved at runtime through `CollectionDefinitionRepositoryContract` (database-backed, decorated by `ElementAwareCollectionDefinitionRepository` for element collections)
- `hasPage: true` — entries are full pages with URL, layout, and element builder
- `hasPage: false` — entries are data-only records without dedicated pages
- Data is stored in the `Page.data` JSON column
- `FieldTypeConverter` auto-converts between translatable/non-translatable formats based on field type (called in Page model's `saving` boot)
- `CollectionHelper` (singleton) resolves collection definitions via the repository

@verbatim
<code-snippet name="Collection Definition Row" lang="php">
CollectionDefinition::create([
    'tenant_id' => $tenantId,
    'filename' => 'services',          // lowercase, hyphenated identifier (used in URLs)
    'key' => 'SERVICES',               // stable uppercase key referenced by templates
    'title' => 'Service',
    'title_list' => 'Services',
    'has_page' => true,                // entries become full pages
    'fields' => [
        ['name' => 'detailData.name', 'label' => 'Name', 'type' => 'translatableText', 'colspan' => 6],
        ['name' => 'detailData.image', 'label' => 'Image', 'type' => 'image', 'colspan' => 6],
    ],
]);
</code-snippet>
@endverbatim

### Navigation

- **Model:** `Noerd\Cms\Models\Navigation` — table `cms_navigations`
- **Relationships:** `page()` belongsTo Page, `collection()` belongsTo Collection, `parent()`/`children()` self-referencing hierarchy
- `navigation_key` groups navigation items (e.g., main menu, footer)
- Entries can link to a page or an external URL
- Children are ordered by `sort_order`
- Names are translatable (`name` cast as array, one value per language code)
- Casts: `name` (array), `new_tab` (boolean)

### Forms

- YAML definitions in `app-configs/cms/forms/` (e.g., `contact.yml`) — labels, messages and button texts are English translation keys, the German text lives in `de.json`
- **Model:** `Noerd\Cms\Models\FormType` with `send_email`, `notification_email`, `email_subject`, `email_body`
- **Sync:** `FormTypeSyncService` syncs YAML to database via `php artisan cms:sync-form-types`
- Only re-syncs when the YAML file has changed (checks modification time) unless `--force` is used
- **Email placeholders:** `@verbatim{{field:name}}@endverbatim`, `@verbatim{{form_title}}@endverbatim`, `@verbatim{{submission_date}}@endverbatim`
- **API endpoint:** `POST /api/cms/form-requests` (protected by `CmsApiAuth` middleware + a route-level throttle). The payload field `form` must match an existing FormType `key` of the token's tenant; submissions are validated against the form YAML rules, undeclared data keys are dropped, and the confirmation email job is dispatched when `send_email` is enabled

@verbatim
<code-snippet name="Form YAML Structure" lang="yaml">
key: contact
title: Contact Form
description: 'General contact form for customer enquiries'
send_email: true
fields:
  - name: name
    label: Name
    type: text
    required: true
    validation:
      - required
      - string
      - 'max:255'
    error_messages:
      required: 'The name is required.'
    placeholder: 'Your full name'
  - name: email
    label: Email
    type: email
    required: true
    validation:
      - required
      - email
      - 'max:255'
    error_messages:
      required: 'The email address is required.'
      email: 'Please enter a valid email address.'
    placeholder: you@example.com
success_message: 'Thank you! Your message has been sent successfully.'
submit_button_text: 'Send message'
</code-snippet>
@endverbatim

### Articles

- **Models:** `Author` (has many articles) and `Article` (belongs to author)
- **Translatable fields:** `title`, `slug` (both cast as arrays)
- **Casts:** `is_active` (boolean), `publication_date` (date)
- **Scope:** `published()` filters for `is_active = true`, `publication_date` not null, and `publication_date <= today`
- The article editor is a custom `NoerdDetail` component (`cms::article-detail`): it overrides `mount()` (translatable title/slug arrays), `updated()` (slug from the title through `PageSlugService`) and `store()`, and reacts to `authorSelected`

@verbatim
<code-snippet name="Article Published Scope" lang="php">
public function scopePublished(Builder $query): Builder
{
    return $query->where('is_active', true)
        ->whereNotNull('publication_date')
        ->where('publication_date', '<=', now()->toDateString());
}

// Usage
$articles = Article::published()->with('author')->latest('publication_date')->get();
</code-snippet>
@endverbatim

### Languages

- **Model:** `CmsLanguage` — table `cms_languages`, casts `is_active` and `is_default` as boolean
- `ensureDefaultLanguageForTenant(int $tenantId)` ensures exactly one default language per tenant
- Boot logic: only one default per tenant; if default is deleted, next active becomes default; first language is auto-default
- Language codes are tenant-configurable through the UI; `Noerd\Cms\Support\CmsLanguageCodes` resolves the active codes per tenant (`active()`) and the recognition baseline (`known()` — built-ins `de,en,fr,es,it,nl` plus every configured code)
- `LanguageFilterTrait` provides session-based language selection for Livewire components plus the code helpers `defaultLanguageCode()`, `activeLanguageCodes()` and `selectedLanguageCode()` — components never query `CmsLanguage` for codes themselves

@verbatim
<code-snippet name="LanguageFilterTrait Usage" lang="php">
use Livewire\Attributes\Computed;
use Noerd\Cms\Traits\LanguageFilterTrait;
use Noerd\Traits\NoerdList;

new class extends Component
{
    use LanguageFilterTrait;
    use NoerdList;

    public $listModel = Page::class;

    public function mount(): void
    {
        $this->mountList();

        // Returns the session language or the tenant default
        $this->ensureDefaultLanguage();
    }

    // The list's Excel-style filters — a language picklist when the tenant runs several languages
    #[Computed]
    public function tableFilters(): array
    {
        return $this->hasMultipleLanguages() ? [$this->getLanguageListFilter()] : [];
    }
};
</code-snippet>
@endverbatim

### API Authentication

- **Middleware:** `CmsApiAuth` (`cms_api` alias)
- Token resolution: `Authorization: Bearer <token>` → `X-API-Key: <token>` (never the query string)
- Looks up `NoerdUser` by `api_token`, validates `selected_tenant_id`, verifies the tenant exists and runs the CMS app
- Attaches `tenant_id`, `tenant`, and `user` to request attributes
- Returns 401 JSON on any authentication failure

@verbatim
<code-snippet name="API Form Submission Example" lang="bash">
curl -X POST https://example.test/api/cms/form-requests \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"form": "contact", "data": {"name": "John", "email": "john@example.com", "message": "Hello"}}'
</code-snippet>
@endverbatim

### Redirects

- **Model:** `Noerd\Cms\Models\Redirect` — table `cms_redirects`, unique per tenant and `source_path`; `normalizePath()` is the canonical form (lowercase, no trailing slash, no query/fragment) shared with the website boilerplate's copy
- Screens: `cms::redirects-list` / `cms::redirect-detail` (`redirects-list.yml`, `redirect-detail.yml`); the detail rejects `/`, an already redirected path and a path that belongs to the target page itself

### Access Control

- Routes carry `['noerd', 'app-access:cms']`. Settings, languages and collection definitions are admin-only through `ComponentAccessGuard::registerAdminComponents()` in the provider — never the `setup` middleware, which would switch the selected app. Every write path outside `store()`/`delete()` (element mutators, `copy()`, `manage()`) checks `canSaveObject()` / `AccessHelper::canWriteObject()` itself
- `Page` and `Navigation` use `GuardedByObjectPermission`, so hand-built counters (dashboard) honour the object read permission

### Settings Page

- `/cms/settings` is a noerd settings page: `cms::settings-page` uses the `NoerdSettingsPage` trait with `public array $settingsModels = ['detailData' => CmsSetting::class];`
- Layout comes exclusively from `settings/settings-page.yml`; the custom `store()` override adds the validation the YAML cannot express and ends with `validateFromLayout()` / `persistSettings()`
- The dynamic homepage picker is the CMS-registered `homepage-select` field type (`cms::components.forms.input-homepage-select`)
- `CmsSetting` is the tenant singleton (`cms_settings`, unique `tenant_id`); `formRecipientsForTenant()` parses the comma-separated recipients, `cookieLifetimeInDays()` resolves the consent duration with config fallback

### Translations

- Use English text as translation keys (e.g., `__('Pages')`, not `__('cms_label_page')`)
- Only `de.json` needed: `app-modules/cms/resources/lang/de.json`
- No `en.json` — English works by fallback (key = English text)
- Use `loadJsonTranslationsFrom()` in the CMS ServiceProvider

### Database

- Every table carries the `cms_` prefix (`cms_pages`, `cms_page_elements`, `cms_collections`, `cms_collection_definitions`, `cms_form_types`, `cms_form_requests`, `cms_global_parameters`, `cms_authors`, `cms_articles`, `cms_navigations`, `cms_redirects`, `cms_languages`, `cms_settings`); an installation from 0.1.x is renamed by the `rename_cms_tables_with_prefix` migration
- `Collection` and `CollectionDefinition` use `BelongsToTenant` like every other content model; queries for ANOTHER tenant go through explicit helpers (`CmsLanguage::forTenant()`)

### YAML File Locations

- Lists: `app-configs/cms/lists/` (e.g., `pages-list.yml`, `articles-list.yml`)
- Details: `app-configs/cms/details/` (e.g., `page-detail.yml`, `article-detail.yml`)
- Settings: `app-configs/cms/settings/settings-page.yml`
- Forms: `app-configs/cms/forms/` (e.g., `contact.yml`)
- Navigation: `app-configs/cms/navigation.yml`
- When modifying YAML files, sync both `app-configs/cms/` and `app-modules/cms/app-configs/cms/`
