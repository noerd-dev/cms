## CMS Module

The CMS module is a multi-tenant, multi-language content management system. It provides pages with a visual element builder, collections for structured data, hierarchical navigation, forms with email notifications, articles with publication scheduling, and language management.

### Pages

- **Model:** `Noerd\Cms\Models\Page` with traits `BelongsToTenant`, `HasFactory`
- **Translatable fields** (cast as arrays): `name`, `slug`, `meta_title`, `meta_description`
- **Data column:** `data` (array cast) stores collection-specific field data
- **Casts:** `meta_noindex` (boolean), `is_active` defaults to `true`
- **Relationships:** `elements()` hasMany ElementPage, `collection()` belongsTo Collection
- Pages use `pageData` as the Livewire component property (array, never a model property)
- Slugs are auto-generated from the name; non-default languages get a language prefix (e.g., `/en/page-name`)
- Layouts are discovered from `website/resources/views/components/layouts/`

@verbatim
<code-snippet name="Page Detail Component Pattern" lang="php">
public array $pageData = [];

public function mount(?string $collectionKey = null): void
{
    $this->mountDetail();

    $page = $this->modelId ? Page::find($this->modelId) : new Page();

    // Translatable fields must be initialized as arrays
    $this->pageData = $page->toArray();
    foreach (['name', 'slug', 'meta_title', 'meta_description'] as $field) {
        if (! is_array($this->pageData[$field] ?? null)) {
            $this->pageData[$field] = $this->initializeEmptySlugArray();
        }
    }
}
</code-snippet>
@endverbatim

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
  - name: model.text
    label: Text
    type: translatableRichText
    colspan: 12
</code-snippet>
@endverbatim

### Collections

- YAML definitions in `app-configs/cms/collections/` (e.g., `services.yml`, `sliders.yml`)
- `hasPage: true` — entries are full pages with URL, layout, and element builder
- `hasPage: false` — entries are data-only records without dedicated pages
- Data is stored in the `Page.data` JSON column
- `FieldTypeConverter` auto-converts between translatable/non-translatable formats based on field type (called in Page model's `saving` boot)
- `CollectionHelper` (singleton) loads and parses collection YAML configs

@verbatim
<code-snippet name="Collection YAML with hasPage: true" lang="yaml">
title: cms_service
titleList: cms_services
key: SERVICES
buttonList: cms_new_service
description: ''
hasPage: true
fields:
  - name: pageData.name
    label: Name
    type: translatableText
    colspan: 6
  - name: image
    label: Image
    type: image
    colspan: 6
</code-snippet>

<code-snippet name="Collection YAML with hasPage: false" lang="yaml">
title: cms_slider
titleList: cms_sliders
key: SLIDERS
buttonList: cms_new_slider
description: ''
hasPage: false
fields:
  - name: image
    label: Image
    type: image
    colspan: 6
</code-snippet>
@endverbatim

### Navigation

- **Model:** `Noerd\Cms\Models\Navigation` — table `cms_navigations`
- **Relationships:** `page()` belongsTo Page, `collection()` belongsTo Collection, `parent()`/`children()` self-referencing hierarchy
- `navigation_key` groups navigation items (e.g., main menu, footer)
- Entries can link to a page or an external URL
- Children are ordered by `sort_order`
- Names are translatable (stored as text, displayed per language)
- Casts: `new_tab` (boolean)

### Forms

- YAML definitions in `app-configs/cms/forms/` (e.g., `contact.yml`)
- **Model:** `Noerd\Cms\Models\FormType` with `send_email`, `notification_email`, `email_subject`, `email_body`
- **Sync:** `FormTypeSyncService` syncs YAML to database via `php artisan cms:sync-form-types`
- Only re-syncs when the YAML file has changed (checks modification time) unless `--force` is used
- **Email placeholders:** `@verbatim{{field:name}}@endverbatim`, `@verbatim{{form_title}}@endverbatim`, `@verbatim{{submission_date}}@endverbatim`
- **API endpoint:** `POST /api/cms/form-requests` (protected by `CmsApiAuth` middleware)

@verbatim
<code-snippet name="Form YAML Structure" lang="yaml">
key: contact
title: Kontaktformular
description: 'Allgemeines Kontaktformular für Kundenanfragen'
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
      required: 'Der Name ist erforderlich.'
    placeholder: 'Ihr vollständiger Name'
  - name: email
    label: E-Mail
    type: email
    required: true
    validation:
      - required
      - email
      - 'max:255'
    error_messages:
      required: 'Die E-Mail-Adresse ist erforderlich.'
      email: 'Bitte geben Sie eine gültige E-Mail-Adresse ein.'
    placeholder: ihre@email.de
success_message: 'Vielen Dank! Ihre Nachricht wurde erfolgreich gesendet.'
submit_button_text: 'Nachricht senden'
</code-snippet>
@endverbatim

### Articles

- **Models:** `Author` (has many articles) and `Article` (belongs to author)
- **Translatable fields:** `title`, `slug` (both cast as arrays)
- **Casts:** `is_active` (boolean), `publication_date` (date)
- **Scope:** `published()` filters for `is_active = true`, `publication_date` not null, and `publication_date <= today`
- Articles use `articleData` as the Livewire component property

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
- Supported language codes: `de`, `en`, `fr`, `es`, `it`, `nl`
- `LanguageFilterTrait` provides session-based language selection for Livewire components

@verbatim
<code-snippet name="LanguageFilterTrait Usage" lang="php">
use Noerd\Cms\Traits\LanguageFilterTrait;

class PagesListComponent extends Component
{
    use LanguageFilterTrait;

    public function mount(): void
    {
        // Returns session language or tenant default (fallback: 'de')
        $language = $this->ensureDefaultLanguage();

        // Check if tenant has multiple active languages
        if ($this->hasMultipleLanguages()) {
            // Add language filter to list
            $this->filters[] = $this->getLanguageListFilter();
        }
    }
}
</code-snippet>
@endverbatim

### API Authentication

- **Middleware:** `CmsApiAuth` (`cms_api` alias)
- Token resolution priority: `Authorization: Bearer <token>` → `X-API-Key: <token>` → query param `api_token`
- Looks up `NoerdUser` by `api_token`, validates `selected_tenant_id`, verifies tenant exists
- Attaches `tenant_id`, `tenant`, and `user` to request attributes
- Returns 401 JSON on any authentication failure

@verbatim
<code-snippet name="API Form Submission Example" lang="bash">
curl -X POST https://example.test/api/cms/form-requests \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"form_type_key": "contact", "data": {"name": "John", "email": "john@example.com", "message": "Hello"}}'
</code-snippet>
@endverbatim

### Translations

- Key format: `cms_{key}` (e.g., `cms_dashboard`)
- Labels: `cms_label_{key}` (e.g., `cms_label_page`)
- Navigation: `cms_nav_{key}` (e.g., `cms_nav_pages`)
- Tabs: `cms_tab_{key}` (e.g., `cms_tab_general`)
- Stored in `app-modules/cms/resources/lang/de.json` and `en.json`
- Use `loadJsonTranslationsFrom()` in the CMS ServiceProvider

### YAML File Locations

- Lists: `app-configs/cms/lists/` (e.g., `pages-list.yml`, `articles-list.yml`)
- Details: `app-configs/cms/details/` (e.g., `page-detail.yml`, `article-detail.yml`)
- Collections: `app-configs/cms/collections/` (e.g., `services.yml`, `sliders.yml`)
- Forms: `app-configs/cms/forms/` (e.g., `contact.yml`)
- Navigation: `app-configs/cms/navigation.yml`
- When modifying YAML files, sync both `app-configs/cms/` and `app-modules/cms/app-configs/cms/`
