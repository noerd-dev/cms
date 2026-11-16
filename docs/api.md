# CMS API

The CMS provides a REST API for submitting form data from external websites or applications.

## File Locations

Routes:
```
app-modules/cms/routes/cms-api.php
```

Controller:
```
app-modules/cms/src/Http/Controllers/FormRequestController.php
```

Middleware:
```
app-modules/cms/src/Http/Middleware/CmsApiAuth.php
```

## Form Submission Endpoint

```
POST /api/cms/form-requests
```

### Request Format

```json
{
  "data": {
    "name": "John Doe",
    "email": "john@example.com",
    "message": "Hello!"
  },
  "form": "contact"
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `data` | object | Yes | Form field values |
| `form` | string | Yes | Form key (matches YAML `key`, max 255 characters) |

### Response

**Success (201 Created):**

```json
{
  "id": 42,
  "created_at": "2026-03-17T10:30:00.000000Z"
}
```

### Error Responses

| Status | Description |
|--------|-------------|
| 401 | Missing or invalid API token |
| 422 | Validation error (missing `data` or `form`) |

## Authentication

The API uses token-based authentication via the `CmsApiAuth` middleware. Tokens are validated against the `api_token` field on the `NoerdUser` model.

Provide the token using one of these methods (in order of priority):

### 1. Authorization Header (Recommended)

```bash
curl -X POST /api/cms/form-requests \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"data": {"name": "John"}, "form": "contact"}'
```

### 2. X-API-Key Header

```bash
curl -X POST /api/cms/form-requests \
  -H "X-API-Key: YOUR_API_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"data": {"name": "John"}, "form": "contact"}'
```

## Authentication Flow

The `CmsApiAuth` middleware:

1. Extracts the token from the `Authorization: Bearer` or `X-API-Key` header (never from the query string — query parameters end up in access logs)
2. Looks up a `NoerdUser` with a matching `api_token`
3. Verifies the user has a `selected_tenant_id`
4. Validates the tenant exists, that the user may still work in it (a member, or a super admin — removing the user from the tenant revokes the token's access) and that it runs the CMS app
5. Attaches `tenant_id`, `tenant`, and `user` to the request attributes
6. Returns `401 Unauthorized` JSON response on any failure

## Validation

The submission goes through `FormSubmissionService` and is FAIL-CLOSED: the `form` value must match an ACTIVE FormType `key` of the token's tenant (`422 Unknown form type.` otherwise), and its form YAML must be readable and declare `fields` (`422 The form definition is missing.` — nothing is stored). The submitted `data` is narrowed to the declared fields and validated by their rules (with their `errorMessages`, `422 Validation failed` with `errors`), and oversized payloads are rejected (`422 Payload too large.`). The endpoint is rate limited (`throttle:60,1`).

## Email Notifications

After a successful submission the CMS queues the mails the form type sends: the notification of the form recipients (`sendEmail`) and the confirmation of the sender (`sendConfirmation`). See [Forms](forms.md).

## Next Steps

- [Forms](forms.md) — Define form types and email templates
- [Overview](overview.md) — Return to the CMS overview
