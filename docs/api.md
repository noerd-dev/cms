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

### 3. Query Parameter

```bash
curl -X POST "/api/cms/form-requests?api_token=YOUR_API_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"data": {"name": "John"}, "form": "contact"}'
```

## Authentication Flow

The `CmsApiAuth` middleware:

1. Extracts the token from the request (header or query param)
2. Looks up a `NoerdUser` with a matching `api_token`
3. Verifies the user has a `selected_tenant_id`
4. Validates the tenant exists
5. Attaches `tenant_id`, `tenant`, and `user` to the request attributes
6. Returns `401 Unauthorized` JSON response on any failure

## Email Notifications

If the matching `FormType` has `send_email: true`, an email notification is sent after a successful submission. See [Forms](forms.md) for email template configuration.

## Next Steps

- [Forms](forms.md) — Define form types and email templates
- [Overview](overview.md) — Return to the CMS overview
