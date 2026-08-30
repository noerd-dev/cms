# Articles

The CMS articles system provides a blog/news feature with authors, multilingual content, and publication scheduling.

## File Locations

YAML Configurations:
```
app-configs/cms/details/article-detail.yml
app-configs/cms/details/author-detail.yml
app-configs/cms/lists/articles-list.yml
app-configs/cms/lists/authors-list.yml
```

Livewire Components:
```
app-modules/cms/resources/views/components/article-detail.blade.php
app-modules/cms/resources/views/components/author-detail.blade.php
app-modules/cms/resources/views/components/articles-list.blade.php
app-modules/cms/resources/views/components/authors-list.blade.php
```

## Authors

Authors are managed at `/cms/authors`. Each author has:

| Field | Type | Description |
|-------|------|-------------|
| `name` | text | Author display name |
| `is_active` | checkbox | Whether the author is active |
| `bio` | textarea | Author biography |

## Articles

Articles are managed at `/cms/articles`. Each article has:

| Field | Type | Description |
|-------|------|-------------|
| `title` | translatableText | Article title in each active language |
| `slug` | translatableText | URL slug (auto-generated from title) |
| `is_active` | checkbox | Whether the article is published |
| `author_id` | authorRelation | Link to an author |
| `publication_date` | date | Scheduled publication date |
| `body` | richText | Article content |

## Article YAML Configuration

```yaml
title: Article
fields:
  - name: detailData.title
    label: Title
    type: translatableText
    colspan: 5
  - name: detailData.slug
    label: Slug
    type: translatableText
    colspan: 5
  - name: detailData.is_active
    label: 'Active?'
    type: checkbox
    colspan: 2
  - name: detailData.featured_image
    label: Featured Image
    type: image
    colspan: 12
  - name: detailData.author_id
    label: Author
    type: authorRelation
    relationField: relationTitles.author_id
    colspan: 6
  - name: detailData.publication_date
    label: Publication Date
    type: date
    colspan: 6
  - name: detailData.body
    label: Content
    type: richText
    colspan: 12
```

## Publication States

Articles have three effective states:

| State | Conditions |
|-------|------------|
| **Published** | `is_active = true` and `publication_date <= now()` |
| **Scheduled** | `is_active = true` and `publication_date > now()` |
| **Unpublished** | `is_active = false` |

## Published Scope

The `Article` model provides a `published()` scope that returns only visible articles:

```php
Article::published()->get();
```

This scope filters for:
- `is_active` is `true`
- `publication_date` is not null
- `publication_date` is less than or equal to the current date/time

## Article Model

The `Article` model:

- `title` and `slug` are cast to arrays for multi-language support
- `is_active` defaults to `true`
- `publication_date` is cast to a date
- Relations: `author()` (belongsTo Author)

## Author Model

The `Author` model:

- `is_active` is cast to boolean
- Relations: `articles()` (hasMany Article)

## Articles List

The articles list (`/cms/articles`) displays:

| Column | Description |
|--------|-------------|
| `title` | Article title |
| `author_name` | Author display name |
| `publication_date` | Publication date |
| `is_active` | Active status toggle |

## Next Steps

- [Pages](pages.md) — Create standalone content pages
- [Languages](languages.md) — Configure languages for multilingual articles
