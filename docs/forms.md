# Forms & Form Requests

The CMS form system provides YAML-defined forms with field validation, two separate mails (the
notification of the form recipients and the confirmation of the sender) and one way in for every
submission — the token API and the website's own forms alike.

## File Locations

Form definitions:
```
app-configs/cms/forms/{form-key}.yml
```

YAML Configurations:
```
app-configs/cms/lists/form-requests-list.yml
app-configs/cms/lists/form-types-list.yml
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

Each form is defined by a YAML file in camelCase keys (`Noerd\Cms\Forms\FormDefinition`). Example:
`app-configs/cms/forms/contact.yml`

```yaml
key: contact
title: Contact Form
description: 'General contact form for customer inquiries'
sendEmail: true
emailSubject: 'New contact request from {{field:name}}'
emailBody: |
  <h2>New contact request</h2>
  <p><strong>Name:</strong> {{field:name}}</p>
  <p><strong>Message:</strong><br>{{field:message}}</p>
sendConfirmation: true
confirmationField: email
confirmationSubject: 'Thank you for your message'
confirmationBody: |
  <p>Hello {{field:name}},</p>
  <p>thank you for your message. We will get back to you soon.</p>
fields:
  - name: name
    label: Name
    type: text
    required: true
    validation:
      - string
      - 'max:255'
    errorMessages:
      required: 'Name is required.'
    placeholder: 'Your full name'
  - name: email
    label: Email
    type: email
    required: true
    validation:
      - email
      - 'max:255'
    errorMessages:
      required: 'Email address is required.'
      email: 'Please enter a valid email address.'
    placeholder: your@email.com
  - name: phone
    label: Phone
    type: tel
    validation:
      - string
      - 'max:255'
  - name: message
    label: Message
    type: textarea
    required: true
    rows: 4
    validation:
      - string
      - 'max:2000'
successMessage: 'Thank you! Your message has been sent successfully.'
submitButtonText: 'Send Message'
```

## Form YAML Properties

| Property | Description |
|----------|-------------|
| `key` | Unique form identifier (used in API requests) |
| `title` | Form display title |
| `description` | Optional description |
| `sendEmail` | Send the NOTIFICATION to the form recipients |
| `emailSubject` / `emailBody` | Subject and HTML body of the notification (placeholders) |
| `notificationEmail` | Recipient of the notification when the CMS settings name no form recipients |
| `sendConfirmation` | Send the CONFIRMATION to the sender |
| `confirmationField` | The field holding the sender's address (default `email`) |
| `confirmationSubject` / `confirmationBody` | Subject and HTML body of the confirmation (placeholders) |
| `fields` | Array of form field definitions |
| `successMessage` | Message shown after successful submission |
| `submitButtonText` | Label for the submit button |

A key the file does not declare leaves the column of the form type alone: what an admin edited on
the form type screen survives a re-sync.

## Field Properties

| Property | Description |
|----------|-------------|
| `name` | Field identifier |
| `label` | Display label |
| `type` | Input type: `text`, `email`, `tel`, `textarea` |
| `required` | Whether the field is mandatory — `required: true` and a `required` rule mean the same, write one of them |
| `validation` | Laravel validation rules (a list or `a\|b`); a field that is not required is `nullable` |
| `errorMessages` | Custom validation error messages (English translation keys) |
| `placeholder` | Input placeholder text |
| `rows` | Number of rows for textarea fields |

The earlier snake_case keys (`send_email`, `email_subject`, `email_body`, `notification_email`,
`success_message`, `submit_button_text`, `error_messages`) are no longer read. `noerd:update-cms`
rewrites them (see "Vocabulary rewrite" below); `cms:sync-form-types` refuses a file that still
carries one.

## The Two Mails

`Noerd\Cms\Forms\FormMailer` decides who gets which mail:

- the **notification** (`sendEmail`) goes to the form recipients of the CMS settings, else to the form
  type's `notificationEmail` — never to an address the visitor typed (job `SendFormNotificationEmail`)
- the **confirmation** (`sendConfirmation`) goes to the valid address in the field
  `confirmationField` of the submission — and only there (job `SendFormConfirmationEmail`)

Both are queued after the submission was stored and run in the submission's tenant. Subjects and
bodies support placeholders:

| Placeholder | Description |
|-------------|-------------|
| `{{form_title}}` | Title of the form |
| `{{submission_date}}` | Date and time of submission (tenant locale) |
| `{{field:name}}` | Value of the field named `name` |

The `FormType` model's `replacePlaceholders()` method handles placeholder substitution at send time;
in the HTML body every substituted value is escaped.

## Submissions

`Noerd\Cms\Forms\FormSubmissionService::submit($tenantId, $formKey, $data)` is the ONE way a
submission enters the CMS. It is fail-closed:

- the form type must exist for the tenant and be active, its form YAML must be readable and declare
  fields — otherwise `FormSubmissionException` (nothing is stored, no mail goes out)
- the data is narrowed to the declared fields and validated by their rules (`ValidationException`)
- the encoded data may not exceed 64 KB

The token API ([API](api.md)) and the website's contact form use it.

## Syncing Form Types

`noerd:install-cms` and `noerd:update-cms` sync the form YAML into `cms_form_types`
(`FormTypeSyncService`); run it by hand with:

```bash
php artisan cms:sync-form-types [--tenant=ID] [--force]
```

- Scans `app-configs/cms/forms/` and creates or updates a form type per CMS tenant
- Skips unchanged files (modification time) unless `--force`
- A form type whose YAML file disappeared is switched inactive (`is_active`): the API refuses it,
  the row and its requests stay. The file coming back switches it active again

## Vocabulary Rewrite

`noerd:update-cms` rewrites the project's form YAML (`app-configs/cms/forms/*.yml`) to the camelCase
vocabulary before it syncs — line by line, comments and formatting stay, idempotent. Look first with:

```bash
php artisan cms:migrate-form-yaml --dry-run
```

Up to 0.5 `send_email: true` mailed the recipients AND the visitor, with the same texts. A file that
switched it on and declares no confirmation gets `sendConfirmation: true` plus a copy of the subject
and body as `confirmationSubject` / `confirmationBody`, so visitors keep receiving a mail — review that
text, it was written for the recipients. The database migration does the same for existing form types.

## Form Requests

Submitted form data is stored in the `cms_form_requests` table. Each request contains:

| Column | Description |
|--------|-------------|
| `form` | Form key (matches YAML `key`) |
| `form_type_id` | The form type |
| `tenant_id` | Tenant that received the submission |
| `data` | JSON-encoded form field values |
| `created_at` | Submission timestamp |

View submissions at `/cms/form-requests`. "Resend notification" sends the notification again to the
form recipients (write permission on form requests).

## Admin Panel

The form type detail view (`/cms/form-type/{id}`) allows administrators to:

- switch the notification and the confirmation and edit their subjects and bodies
- pick the field that holds the sender's address
- preview both mails with sample data and send a test mail

## Built-in Forms

| Form | Key | Description |
|------|-----|-------------|
| Contact | `contact` | Name, email, phone, message |
| Newsletter | `newsletter` | Email signup with optional name |

## Next Steps

- [API](api.md) — Learn about the form submission API endpoint
- [Settings](settings.md) — Configure CMS-wide settings
