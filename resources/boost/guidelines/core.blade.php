## CMS Module

The CMS module is a multi-tenant, multi-language content management system. It provides pages with a Notion-like document editor (running text with elements), collections for structured data, hierarchical navigation, forms with email notifications, articles with publication scheduling, and language management.

### Contents — the shared base of pages, intranet pages, articles

- `Noerd\Cms\Models\Content` (`contents`, `BelongsToTenant`, NO object read guard — permissions are
  checked on the record that extends it) carries what every page-like record shares: `type` (a
  `ContentTypeRegistry` key), `name` (language map) and the elements (`ContentElement`,
  `content_elements`: `content_id`, `element_key`, `sort`, `data`). The table is installed by
  the CMS migrations as soon as `noerd/cms` is required — a module using contents needs the
  package, NOT the CMS tenant app
- A kind of content is an EXTENSION table with `tenant_id` + `content_id` (unique, FK cascade)
  whose model uses `Noerd\Cms\Concerns\ExtendsContent` and implements `contentType()`: the
  content is created on `creating`, `name` (`contentAttributes()`) reads and writes like a
  column of the model (`toArray()` included, never a `content` key), `elements()` goes through
  the content, `replicate()` gives the copy a content of its own with copied elements, and the
  content goes with the record (a soft-deleting record keeps it until the force delete). The
  name is no column of the extension table: lists search and sort it through
  `scopeWhereContentNameContains()` / `scopeOrderByContentName()` — a NoerdList on such a model
  uses `Noerd\Cms\Traits\ListsContentRecords` (`contentRecordRows()` in `listData()`)
- Register every type in the owning provider's `boot()`:
  `app(ContentTypeRegistry::class)->register(key:, label:, modelClass:, detailRoute:, detailComponent:, languageSource:)`
  (`cms.page` = website page; other modules add their own, e.g. `blog.post`). The registry is the one
  place a type maps to its record (`Content::extension()`, the record of the content's tenant), to the
  screen that opens it (`recordUrl()`) and to the LANGUAGES its document is written in:
  `languageSource: ContentLanguageSource::Cms` (default — the tenant's website languages,
  `cms_languages`) or `ContentLanguageSource::Setup` (the backend's setup languages — a module whose
  content is no website, e.g. UKI). The editor offers, accepts and stores only the source's codes
@verbatim
- The editor of a content is ONE document (Notion-like): running text and elements in between,
  with H1–H3, bold, italic, lists, quote and link anywhere (toolbar, bubble menu, Markdown input
  rules), and `//` opens the insert menu (text blocks + the elements of the content type). An
  element's fields are edited INLINE in the document. The storage stays one `content_elements`
  row per block: the running text between two elements is the element `rich_text` (YAML
  `pickable: false`, field `text` `translatableRichText`, rendered by the CMS itself and sanitized)
  - A detail uses `Noerd\Cms\Traits\EditsContentDocument` (`editableContent()`,
    `contentDocumentType()`), renders `<x-cms::content-document :config="$this->contentDocumentConfig()" />`
    inside `@assets <x-cms::editor-assets /> @endassets`, and calls
    `persistContentDocument($record->ownContent(), $record)` at the end of `store()` — also for a
    NEW record. It writes ONLY into the record's own content (same tenant, same content type, the
    record's `content_id`; without the record: the content `editableContent()` answers) and throws
    `Noerd\Cms\Exceptions\ForeignContentException` otherwise
  - `ExtendsContent` binds a record to its content for life: a `content_id` of another tenant,
    of another type or of another record is refused on create, a changed `content_id` on update
    (`ForeignContentException`); the hooks resolve the content through `ownContent()` — the
    record's tenant and type, never the current context or `withoutGlobalScopes()`
  - The editor writes `contentDocumentDraft` (`{language, sections}`, untrusted, no round trip);
    `Noerd\Cms\Services\ContentDocumentService` merges it (`apply()`: sections matched by `uid`,
    never by a client row id; an element needs a key the type offers or one the document already
    holds; data reduced to the YAML fields; all HTML through `HtmlSanitizer`) and writes it
    (`persist()`: sort = position, missing rows deleted through the model). The structure is
    shared by every language, the running text is per language: a split keeps the other
    languages on the first part, a merge (`absorbed`) appends them
  - The draft contract (client input): the draft language and the codes of a translatable map
    must be codes of the content type's language source
    (`ContentDocumentService::editableLanguageCodes($contentType)`) — a draft in another language
    is refused; a NEW image value only as a media id the tenant holds (a stored legacy
    string stays while the client sends it back unchanged); a `select` with inline options only
    one of its option values; at most `MAX_SECTIONS` (500) sections, text values up to
    `MAX_TEXT_LENGTH` (200 000 characters). A refused value keeps the stored one
  - An inline select option is offered and stored as the option ITSELF (`{value, label}` entries:
    `value`), exactly like the core select — never its array index. The migration
    `store_select_option_values_in_content_elements` rewrote index values stored before 0.6
    (`Services\Maintenance\SelectOptionValueRepair`)
  - Saving never destroys what the editor cannot show: an element whose YAML is unknown keeps its
    data untouched, a plain legacy string of a translatable field becomes the default language
  - The JavaScript is the CMS's OWN bundle (Tiptap 3, `resources/js/`, `npm run build` in the
    module, `dist/build` is committed), served by the route `cms.asset`; field renderers of the
    inline form live in `resources/js/document/fields.js` — a new YAML field type used by an
    element needs a renderer there (unknown types show a notice)
@endverbatim
- An element YAML may name the types it serves (`contentTypes:` list); without it the element
  serves `cms.page` only, so website elements never appear in another kind of content
  (`FieldHelper::getAllElementsGrouped($contentType)`, the picker receives `contentType`)
- `Noerd\Cms\Support\ContentText::markdown($content)` is the readable text of a content (name +
  the text fields of every element, per the element YAML, in the tenant's default language) — the
  knowledge assistant indexes it; never read element data by hand for that

### Pages

- **Model:** `Noerd\Cms\Models\Page` (`cms_pages`) with traits `BelongsToTenant`, `ExtendsContent`, `HasFactory` — the website extension of a content (`cms.page`); `name` and the elements live on the content
- **Translatable fields** (cast as arrays): `name` (on the content), `slug`, `meta_title`, `meta_description`
- **Data column:** `data` (array cast) stores collection-specific field data
- **Casts:** `meta_noindex`, `is_active` (boolean; defaults to `true`)
- **Relationships:** `content()`, `elements()` (through the content), `collection()` belongsTo Collection
- The page editor is a custom `NoerdDetail` component (`cms::page-detail`): `$detailModel = Page::class`, `$detailPrimary = 'pageId'`, form state in `$detailData` (array — the Eloquent model is never a component property). It overrides `mount()` (collection entry detection), `store()` (plain page vs. collection entry) and `updated()` (slug derivation) and delegates to `PageSlugService` (slug generation + uniqueness), the document editor (`EditsContentDocument`), `PageCopyService` (copy a page) and `CollectionEntryStore` (persist collection entries and element-collection rows)
- Every document write path (`persistContentDocument()`, the media picker, element collections) checks `canSaveObject()` itself (the generic `WriteGuardHook` only covers `store`/`delete`)
- The collection of the edited entry is SERVER state (`#[Locked]` `collectionKey`/`collectionLayout`): an existing page edits the collection it belongs to, the layout is re-resolved from the definition on every request, and every handler (`pageSelected`, the image handlers) writes only a field the server-side layout declares with the matching type. `store()` persists `writableDetailData()` plus explicitly computed keys — never `content_id`, `collection_id`, `id` or `tenant_id` from the form — validates through `validateFromLayout()` (the `layout` must be a discovered layout) and ends with `finishStore()`
- Every custom `store()` of the module follows the same rules: `writableDetailData()`, find → fill → save (an id that no longer resolves stores nothing, never `updateOrCreate(['id' => …])`), `finishStore()`; ids from the form are validated against the current tenant (`Rule::exists(...)->where('tenant_id', …)`)
- Components WITHOUT a route (`element-collection-row-detail`, `element-collection-field`) are mountable through the modal stack by any signed-in user: they ask `AccessHelper::canUseApp('CMS')` and the page permissions in `mount()` AND every action, resolve the element collection from locked identifiers (one of the tenant whose owner exists) and take the row schema from the server-side definition (collection definition, element YAML, the collection's stored `element_fields`) — never from mount arguments
- Slugs are auto-generated from the name; non-default languages get a language prefix (e.g., `/en/page-name`)
- EVERY save path of a slug (page editor, collection entries, articles, copies) goes through
  `PageSlugService`: `violation()` refuses a slug naming another host (`//evil.com`,
  `https://…`) and — for pages — a reserved prefix (`RESERVED_PREFIXES`: `/api`, `/blog`, `/cms`,
  `/cms-assets`, `/index`, `/livewire`, `/noerd`, `/storage`, … plus
  `config('cms.reserved_slug_prefixes')`); `normalize()` gives the stored shape (leading `/`,
  lower case, no `//`, no trailing slash, no query/fragment — the shape of
  `Redirect::normalizePath()`); `ensureUnique()` / `prepare()` keep a slug unique across EVERY
  active language of the tenant (the website resolves a slug in any of them).
  `php artisan cms:check-slugs` reports stored slugs that break the rule (read only)
- Layouts are discovered from `config('cms.layout_path')` (`Noerd\Cms\Support\PageLayouts`) — when empty, from the `components/layouts` folder of the `website` view namespace (the installed website module, wherever it lives)

@verbatim
<code-snippet name="Page Detail Component Pattern" lang="php">
public ?string $detailPrimary = 'pageId';

public $detailModel = Page::class;

// Derived values are read from the loaded record (or the fresh model of a new
// one) in detailLoaded() — never by re-finding the record in mount().
protected function detailLoaded(Model $model): void
{
    // Translatable fields must be initialized as arrays for every active language
    // (LanguageFilterTrait::activeLanguageCodes() — never a hard-coded code list)
    foreach (['name', 'slug'] as $field) {
        if (! is_array($this->detailData[$field] ?? null)) {
            $this->detailData[$field] = array_fill_keys($this->activeLanguageCodes(), '');
        }
    }
}
</code-snippet>
@endverbatim

### Images Store the Media ID

- `noerd/media` is a HARD requirement, not an option: `CmsInstallCommand::getRequiredModules()`
  returns `['MEDIA' => 'noerd:install-media']`, so `noerd:install-cms` installs a missing media
  module first — as a dependency that asks nothing — and every tenant that gets the CMS gets the
  media app in the same prompt. Never install the CMS for a tenant without MEDIA
- An image field (`type: image` in an element YAML or a collection definition) stores the MEDIA ID,
  never a URL: the media disk mirrors the library's folder tree, so a file's path changes the
  moment it is moved. `page-detail`, the document editor and `element-collection-row-detail`
  write `$media->id`. A detail with image fields uses `Noerd\Cms\Traits\HandlesImageFields`
  (plus `WithFileUploads`) and implements `imageFieldNames()` from its SERVER-SIDE layout: it
  answers the core image element (`imageUploads.{field}`, `openSelectMediaModal()`,
  `deleteImage()`, `mediaSelected` with the locked `mediaSelectToken`) — uploads are validated as
  raster images within `media.max_upload_size`, picked ids must exist in the tenant, and only a
  user who may save the record writes one
- The URL is resolved on the way OUT, centrally in `Noerd\Cms\Support\MediaValues` — called from
  `HandlesPageElements::processPageElements()` and `PageElementService::processCollectionPageData()`
  (plus the website copies). Element Blades therefore keep reading a plain URL from
  `$element->image` and no project's element templates have to change. A value that is not
  a bare integer — a legacy `/storage/…` string, an external URL — is passed through untouched
- That URL is the DELIVERY URL (`MediaResolverContract::getImageUrl()`): a size-limited, cached
  variant behind the media module's signed `media.image` route, never the oversized original (an
  SVG keeps its plain URL). An image field may name another variant of `config('media.variants')`
  with `variant: teaser` in its YAML; `web` is the default. The URL is relative and carries a query
  string — element Blades keep prefixing `config('website.media_url')` and never append to it

### Elements

- Elements are Blade + YAML pairs in a module's `resources/views/components/elements/` — below `app-modules/` or in `vendor/` alike; `Noerd\Cms\Support\ElementDirectories` is the ONE place the folders are found (through the module's Livewire namespace, plus `app-modules/*` and the project folder, which shadows a module element) — never glob `app-modules/*` for elements again
- File naming: kebab-case files (e.g., `text-block-1-column.blade.php`) map to snake_case element keys (`text_block_1_column`)
- `HandlesPageElements::getComponentMapping()` mounts a module's element through the Livewire namespace that owns its components folder (`cms::elements.rich-text`, `website::elements.slider`) — modules register no bare component names; only an element of the project's `resources/views/components/elements/` or of `cms.page_elements_path` keeps the bare `elements.{file}` name
- `FieldHelper::getAllElements()` discovers all elements across modules
- `FieldHelper::getAllElementsGrouped(?string $contentType)` groups elements by their `group` YAML property, narrowed to the content type (`contentTypes:`, default `cms.page`)
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

- Definitions are per-tenant rows in the `cms_collection_definitions` table (**model:** `Noerd\Cms\Models\CollectionDefinition`), managed via the `/cms/collection-definitions` UI
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
- A `link` is at most 255 characters (the column) and must be a safe URL
  (`Noerd\Cms\Support\SafeUrl`: http/https, mailto, tel, site paths — never `javascript:`,
  `data:` or a protocol-relative `//host`)
- Names are translatable (`name` cast as array, one value per language code)
- Casts: `name` (array), `new_tab` (boolean)

### Forms

- YAML definitions in `app-configs/cms/forms/` (e.g., `contact.yml`), read ONLY in camelCase keys
  (`Noerd\Cms\Forms\FormDefinition`): `key`, `title`, `description`, `sendEmail`, `emailSubject`,
  `emailBody`, `notificationEmail`, `sendConfirmation`, `confirmationField` (default `email`),
  `confirmationSubject`, `confirmationBody`, `successMessage`, `submitButtonText`, `fields` (`name`,
  `label`, `type`, `required`, `validation`, `errorMessages`, `placeholder`, `rows`). `required: true`
  and a `required` rule mean the same — write one; a field that is not required is `nullable`.
  Labels, messages and button texts are English translation keys, the German text lives in `de.json`
- The earlier snake_case keys are NOT read: `Forms\FormYamlMigrator` rewrites them (run by
  `noerd:update-cms`, or `php artisan cms:migrate-form-yaml [--dry-run]`); `cms:sync-form-types`
  refuses a file that still carries one
- **Model:** `Noerd\Cms\Models\FormType` — the notification (`send_email`, `email_subject`,
  `email_body`, `notification_email`), the confirmation (`send_confirmation`, `confirmation_subject`,
  `confirmation_body`, `confirmation_field`) and `is_active`; `definition()` reads its form YAML
- **Two mails, one rule** (`Forms\FormMailer`): the notification goes ONLY to the form recipients of
  the CMS settings, else `notification_email` — never to the visitor; the confirmation goes ONLY to
  the valid address in `confirmation_field` of the submission. Jobs `SendFormNotificationEmail` /
  `SendFormConfirmationEmail`, queued after the commit, run in the submission's tenant
- **Submissions** enter ONLY through `Forms\FormSubmissionService::submit($tenantId, $formKey, $data)`
  (the token API and the website's forms): FAIL-CLOSED — an unknown or inactive form type, or a form
  YAML that is missing or declares no fields, throws `FormSubmissionException` (nothing stored, no
  mail); the data is narrowed to the declared fields and validated (`ValidationException`)
- **Sync:** `FormTypeSyncService` runs in `ensureModuleSetup()` of `noerd:install-cms` AND
  `noerd:update-cms` (by hand: `php artisan cms:sync-form-types [--tenant=] [--force]`); re-syncs
  only changed files unless `--force`; a form type whose YAML disappeared becomes inactive — rows
  with requests are never deleted
- **Email placeholders:** `@verbatim{{field:name}}@endverbatim`, `@verbatim{{form_title}}@endverbatim`, `@verbatim{{submission_date}}@endverbatim`
- **API endpoint:** `POST /api/cms/form-requests` (protected by `CmsApiAuth` middleware + a route-level
  throttle) — `FormSubmissionService`, a refusal answers `422` with its message

@verbatim
<code-snippet name="Form YAML Structure" lang="yaml">
key: contact
title: Contact Form
description: 'General contact form for customer enquiries'
sendEmail: true
emailSubject: 'New request from {{field:name}}'
emailBody: '<p>{{field:message}}</p>'
sendConfirmation: true
confirmationField: email
confirmationSubject: 'Thank you for your message'
confirmationBody: '<p>Hello {{field:name}}, we will get back to you soon.</p>'
fields:
  - name: name
    label: Name
    type: text
    required: true
    validation:
      - string
      - 'max:255'
    errorMessages:
      required: 'The name is required.'
    placeholder: 'Your full name'
  - name: email
    label: Email
    type: email
    required: true
    validation:
      - email
      - 'max:255'
    errorMessages:
      required: 'The email address is required.'
      email: 'Please enter a valid email address.'
    placeholder: you@example.com
successMessage: 'Thank you! Your message has been sent successfully.'
submitButtonText: 'Send message'
</code-snippet>
@endverbatim

### Articles

- **Models:** `Author` (has many articles) and `Article` (belongs to author)
- **Translatable fields:** `title`, `slug` (both cast as arrays)
- **Casts:** `is_active` (boolean), `publication_date` (date)
- **Scope:** `published(?string $today = null)` filters for `is_active = true`, `publication_date` not null, and `publication_date` before tomorrow — today in the app timezone (noerd keeps none per tenant) unless the caller names it; a plain comparison on the indexed column, never `DATE(column)`
- The article editor is a custom `NoerdDetail` component (`cms::cms-article-detail`): it overrides `mount()` (translatable title/slug arrays), `updated()` (slug from the title through `PageSlugService`) and `store()`, and reacts to `authorSelected`

@verbatim
<code-snippet name="Article Published Scope" lang="php">
public function scopePublished(Builder $query, ?string $today = null): Builder
{
    $today = $today !== null ? Carbon::parse($today) : now(config('app.timezone'));

    return $query->where($this->qualifyColumn('is_active'), true)
        ->whereNotNull($this->qualifyColumn('publication_date'))
        ->where($this->qualifyColumn('publication_date'), '<', $today->copy()->addDay()->toDateString());
}

// Usage
$articles = Article::published()->with('author')->latest('publication_date')->get();
</code-snippet>
@endverbatim

### Languages

- **Model:** `CmsLanguage` — table `cms_languages`, casts `is_active` and `is_default` as boolean
- `ensureDefaultLanguageForTenant(int $tenantId)` creates the default language (`en`) of a tenant without any — run when the CMS app is assigned (`TenantAppAssigned`, `Listeners\EnsureDefaultCmsLanguage`) and by install/update for every CMS tenant, never for a tenant without the CMS. Another tenant's languages: `CmsLanguage::query()->ofTenant($id)` (the former `CmsLanguage::forTenant()` is gone — it collided with the tenant singletons' `forTenant()`)
- Boot logic: only one default per tenant; if default is deleted, next active becomes default; first language is auto-default
- Language codes are tenant-configurable through the UI; `Noerd\Cms\Support\CmsLanguageCodes` resolves the active codes per tenant (`active()`) and the recognition baseline (`known()` — built-ins `de,en,fr,es,it,nl` plus every configured code)
- `LanguageFilterTrait` provides session-based language selection for Livewire components plus the code helpers `defaultLanguageCode()`, `activeLanguageCodes()` and `selectedLanguageCode()` — components never query `CmsLanguage` for codes themselves
- The backend's editing language is the session key `selectedLanguage`; the website VISITOR's language has its own (`website.language`, the boilerplate's `WebsiteLanguage`) — a website visit never changes the backend language
- Every language/collection-definition lookup resolves the tenant through `TenantHelper::currentTenantId()` (the tenant context, else the signed-in user's selection — never a bare session value) and memoizes per request (`Noerd\Support\RequestState`)

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
- Looks up `NoerdUser` by `api_token`, validates `selected_tenant_id`, verifies the tenant exists, that the user may still work in it (`canAccessTenant()` — a removed member keeps the account) and that it runs the CMS app
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

@verbatim
### Website Output

- The website prints editor data to anonymous visitors. `HandlesPageElements::processPageElements()`
  and `PageElementService` sanitize every field an element YAML (or an element collection's row
  schema, or a collection definition) types as rich text (`Noerd\Cms\Support\RichTextValues`,
  any `…RichText` type) — that keeps `{!! $element->text !!}` in element templates safe; plain
  text fields are printed escaped. `CollectionEntryStore` sanitizes collection entries and
  element-collection rows on save as well
- URLs from editor data go through `Noerd\Cms\Support\SafeUrl` at render time (`href()`,
  `orNull()`, `cssUrl()` for a CSS `url()`, `isSitePath()` for redirect targets); values inside
  Alpine/Livewire directives through `Js::from()`
- `Noerd\Cms\Support\PageMeta::of($page, $language)` is what a website layout prints into
  `<head>` (`meta_title`, `meta_description`, `meta_noindex`, `og_image` as absolute delivery URL);
  `PageMeta::imageUrl()` resolves any image field value (media id or legacy URL)

### Data Integrity

- Deleting a `Collection` deletes its rows THROUGH THE MODELS (`Collection::deleting`), so their
  contents, elements and element collections go too — never a mass `delete()` on
  `cms_collections`. Copying a page (`PageCopyService`) or duplicating an element-collection row
  (`CollectionEntryStore::duplicateRow()`) copies the element collections with their rows
  (`ElementCollectionService::copyOwned()`)
- `php artisan cms:prune-orphaned-contents [--tenant=] [--force]` reports (with `--force`
  deletes) `cms.page` contents no `cms_pages` row references — left behind by collection deletes
  before 0.6. Content types of other modules are never touched
@endverbatim

### Access Control

- Routes carry `['noerd', 'app-access:cms']`. Settings, languages and collection definitions are admin-only through `ComponentAccessGuard::registerAdminComponents()` in the provider — never the `setup` middleware, which would switch the selected app. Every write path outside `store()`/`delete()` (element mutators, `copy()`, `manage()`) checks `canSaveObject()` / `AccessHelper::canWriteObject()` itself
- `Page` and `Navigation` use `GuardedByObjectPermission`, so hand-built counters (dashboard) honour the object read permission

### Settings Page

- `/cms/settings` is a noerd settings page: `cms::cms-settings-page` uses the `NoerdSettingsPage` trait with `public array $settingsModels = ['detailData' => CmsSetting::class];`
- Layout comes exclusively from `settings/cms-settings-page.yml`; the custom `store()` override adds the validation the YAML cannot express and ends with `validateFromLayout()` / `persistSettings()`
- The dynamic homepage picker is the CMS-registered `homepageSelect` field type (`cms::components.forms.input-homepage-select`); the other CMS field type is `elementCollection`. Field types are camelCase (0.6 renamed `homepage-select` / `element-collection`, removed `collection-select`; stored collection schemas were migrated)
- The cookie banner, its lifetime and the Google tag (`google_analytics_id`, `GTM-…` or `G-…`, `CmsSetting::GOOGLE_TAG_PATTERN`, validated on save) reach the website ONLY through `Services\WebsiteTrackingService::forRequest($tenantId, $host)`: the tag only with the banner, loaded by the banner after consent, and only on a host that shares no session with the backend (not `app.url`, not below `session.domain`)
- `noerd:install-cms` seeds a starter homepage only for a CMS tenant without any page and without a settings row (`DefaultHomepageSeeder`, `CmsSetting::updateForTenant()`); `noerd:update-cms` never creates one
- `CmsSetting` is the tenant singleton (`cms_settings`, unique `tenant_id`, `TenantSingleton`: read through `CmsSetting::forTenant()`, `tenantDefaults()` before the first save); `formRecipientsForTenant()` parses the comma-separated recipients, `cookieLifetimeInDays()` resolves the consent duration with config fallback

### Translations

- Use English text as translation keys (e.g., `__('Pages')`, not `__('cms_label_page')`)
- Only `de.json` needed: `app-modules/cms/resources/lang/de.json`
- No `en.json` — English works by fallback (key = English text)
- Use `loadJsonTranslationsFrom()` in the CMS ServiceProvider

### Database

- `contents` and `content_elements` are the shared base tables of every module's contents — the only tables without the prefix
- Every other table carries the `cms_` prefix (`cms_pages`, `cms_collections`, `cms_collection_definitions`, `cms_form_types`, `cms_form_requests`, `cms_global_parameters`, `cms_authors`, `cms_articles`, `cms_navigations`, `cms_redirects`, `cms_languages`, `cms_settings`)
- `Collection` and `CollectionDefinition` use `BelongsToTenant` like every other content model; queries for ANOTHER tenant name it with the core scopes (`ofTenant($id)`, `acrossTenants()`) — `withoutGlobalScopes()` only where more than the tenant filter is meant (a cascade past the read guard, a uniqueness check)

### Configuration

- `config/cms.php` (`cms.*`): `website_url`, `page_elements_path`, `layout_path` (null = the
  `components/layouts` folder of the `website` view namespace, `Support\PageLayouts`),
  `reserved_slug_prefixes`, `collection_field_types`. Up to 0.5 it was `config/noerd_cms.php`
  (`noerd_cms.*`): for the 0.6 line a project's earlier file is still merged over the defaults while
  it has no `config/cms.php` (`Support\LegacyCmsConfig`, a notice in the log outside production);
  install/update publish `config/cms.php` only when the project has neither, and never overwrite one
- The screens whose bare names collided across modules are `cms::cms-settings-page`,
  `cms::cms-article-detail`, `cms::cms-articles-list` (YAML `settings/cms-settings-page.yml`,
  `details/cms-article-detail.yml`, `lists/cms-articles-list.yml`; the route names and URLs stayed).
  `noerd:update-cms` moves a project's earlier copies to the new names
- The shipped `app-configs/` pass the core's `ConfigLinter` without an error
  (`tests/Feature/ShippedConfigLintTest.php`)

### YAML File Locations

- Lists: `app-configs/cms/lists/` (e.g., `pages-list.yml`, `cms-articles-list.yml`)
- Details: `app-configs/cms/details/` (e.g., `page-detail.yml`, `cms-article-detail.yml`)
- Settings: `app-configs/cms/settings/cms-settings-page.yml`
- Forms: `app-configs/cms/forms/` (e.g., `contact.yml`)
- Navigation: `app-configs/cms/navigation.yml`
- When modifying YAML files, sync both `app-configs/cms/` and `app-modules/cms/app-configs/cms/`
