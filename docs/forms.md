# Forms & Form Requests

The CMS form system provides YAML-defined forms with field validation, email notifications, and an API endpoint for receiving submissions.

## File Locations

Form definitions:
```
app-configs/cms/forms/{form-key}.yml
```

YAML Configurations:
```
app-configs/cms/lists/form-requests-list.yml
app-configs/cms/details/form-type-detail.yml
```

Livewire Components:
```
app-modules/cms/resources/views/components/form-requests-list.blade.php
app-modules/cms/resources/views/components/form-request-detail.blade.php
app-modules/cms/resources/views/components/form-type-detail.blade.php
app-modules/cms/resources/views/components/form-types-list.blade.php
```

## Form Definition YAML

Each form is defined by a YAML file. Example: `app-configs/cms/forms/contact.yml`

```yaml
key: contact
title: Contact Form
description: 'General contact form for customer inquiries'
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
      required: 'Name is required.'
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
      required: 'Email address is required.'
      email: 'Please enter a valid email address.'
    placeholder: your@email.com
  - name: phone
    label: Phone
    type: tel
    required: false
    validation:
      - nullable
      - string
      - 'max:255'
    placeholder: '+49 ...'
  - name: message
    label: Message
    type: textarea
    required: true
    rows: 4
    validation:
      - required
      - string
      - 'max:2000'
    error_messages:
      required: 'Please enter a message.'
    placeholder: 'Your message...'
success_message: 'Thank you! Your message has been sent successfully.'
submit_button_text: 'Send Message'
```

## Form YAML Properties

| Property | Description |
|----------|-------------|
| `key` | Unique form identifier (used in API requests) |
| `title` | Form display title |
| `description` | Optional description |
| `send_email` | Whether to send email notification on submission |
| `email_subject` | Email subject line (supports placeholders) |
| `email_body` | HTML email body (supports placeholders) |
| `notification_email` | Recipient email address for notifications |
| `fields` | Array of form field definitions |
| `success_message` | Message shown after successful submission |
| `submit_button_text` | Label for the submit button |

## Field Properties

| Property | Description |
|----------|-------------|
| `name` | Field identifier |
| `label` | Display label |
| `type` | Input type: `text`, `email`, `tel`, `textarea` |
| `required` | Whether the field is mandatory |
| `validation` | Array of Laravel validation rules |
| `error_messages` | Custom validation error messages |
| `placeholder` | Input placeholder text |
| `rows` | Number of rows for textarea fields |

## Email Notifications

When `send_email: true`, the CMS sends an email notification on each form submission. The email subject and body support placeholders:

| Placeholder | Description |
|-------------|-------------|
| `{{form_title}}` | Title of the form |
| `{{submission_date}}` | Date and time of submission |
| `{{field:name}}` | Value of the field named `name` |
| `{{field:email}}` | Value of the field named `email` |

Example with email configuration:

```yaml
key: newsletter
title: Newsletter Signup
send_email: true
email_subject: 'Newsletter Signup: {{field:email}}'
email_body: |
  <h2>New Newsletter Signup</h2>
  <p><strong>Email:</strong> {{field:email}}</p>
  <p><strong>Name:</strong> {{field:name}}</p>
  <hr>
  <p><small>Signed up on {{submission_date}}</small></p>
notification_email: newsletter@example.com
```

The `FormType` model's `replacePlaceholders()` method handles placeholder substitution at send time.

## Syncing Form Types

Form YAML files are synced to the database using the artisan command:

```bash
php artisan cms:sync-form-types
```

The `FormTypeSyncService` handles synchronization:

- Scans `app-configs/cms/forms/` for YAML files
- Creates or updates `FormType` records for each tenant
- Tracks file modification time to skip unchanged files
- Use `--force` to re-sync all files regardless of modification time

## Form Requests

Submitted form data is stored in the `form_requests` table. Each request contains:

| Column | Description |
|--------|-------------|
| `form` | Form key (matches YAML `key`) |
| `tenant_id` | Tenant that received the submission |
| `data` | JSON-encoded form field values |
| `created_at` | Submission timestamp |

View submissions at `/cms/form-requests`.

## Admin Panel

The form type detail view (`/cms/form-type/{id}`) allows administrators to:

- View and edit form type configuration
- Preview email templates with placeholder highlighting
- Test email sending
- View associated form submissions

## Built-in Forms

| Form | Key | Description |
|------|-----|-------------|
| Contact | `contact` | Name, email, phone, message |
| Newsletter | `newsletter` | Email signup with optional name |

## API Submission

Form submissions can be sent via the REST API. See [API](api.md) for endpoint details.

## Next Steps

- [API](api.md) — Learn about the form submission API endpoint
- [Settings](settings.md) — Configure CMS-wide settings
