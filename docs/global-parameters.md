# Global Parameters

Global parameters are key-value pairs for site-wide settings such as footer text, contact information, or social media links. They support multi-language values.

## File Locations

YAML Configurations:
```
app-configs/cms/details/global-parameter-detail.yml
app-configs/cms/lists/global-parameters-list.yml
```

Livewire Components:
```
app-modules/cms/resources/views/components/global-parameter-detail.blade.php
app-modules/cms/resources/views/components/global-parameters-list.blade.php
```

## Managing Global Parameters

Navigate to `/cms/global-parameters` to manage parameters. Each parameter has:

| Field | Type | Description |
|-------|------|-------------|
| `key` | text | Parameter identifier, unique per tenant (e.g., `footer_text`, `phone_number`) |
| `value` | text | Parameter value — a JSON language map when the parameter is translatable |
| `is_translatable` | checkbox | Whether the value is kept per language |

## Multi-Language Support

The global parameters list supports language filtering via the `LanguageFilterTrait`. When multiple languages are active, a language switcher allows viewing and editing values for each language.

## Use Cases

| Key | Example Value | Usage |
|-----|---------------|-------|
| `footer_text` | Company tagline | Footer section |
| `phone_number` | +49 123 456789 | Contact information |
| `email` | info@example.com | Contact email |
| `facebook_url` | https://facebook.com/... | Social media links |
| `instagram_url` | https://instagram.com/... | Social media links |

## GlobalParameter Model

The `GlobalParameter` model:

- Uses `BelongsToTenant` trait for tenant scoping
- Uses `$guarded = []`
- Simple key-value storage with tenant isolation

## Accessing Parameters

Global parameters can be queried by key. Inside a noerd session the tenant
scope applies automatically; in a job or command the tenant must be explicit:

```php
$value = GlobalParameter::query()
    ->where('tenant_id', $tenantId)
    ->where('key', 'footer_text')
    ->value('value');
```

## Next Steps

- [Settings](settings.md) — Configure CMS-wide settings
- [Languages](languages.md) — Set up multi-language support
