# API Client Guide

## Overview

This API uses JWT bearer authentication (`lexik/jwt-authentication-bundle`).

Authentication flow:
- Call `POST /api/v1/login` with JSON credentials to obtain a JWT.
- Send this token on protected endpoints with `Authorization: Bearer <token>`.
- Only the `Authorization` header extractor is enabled (no query parameter, no cookie).
- API errors are returned as `application/problem+json`.

## JWT behavior

- Token lifetime: `30 minutes` (`token_ttl: 1800`)
- `iss`: `urn:sample02:api` (set at token creation, validated on protected requests)
- `aud`: `urn:sample02:client` (set at token creation, validated on protected requests)
- `sub`: authenticated user identifier (`user_id_claim: sub`)

## Example requests

Get a JWT token:

```bash
curl -X POST http://localhost/api/v1/login \
  -H 'Content-Type: application/json' \
  -d '{"username":"demo","password":"demo"}'
```

Call a protected endpoint:

```bash
curl http://localhost/api/v1/me \
  -H "Authorization: Bearer <token>"
```

Upload an image (JWT required):

```bash
curl -X POST http://localhost/api/v1/images \
  -H "Authorization: Bearer <token>" \
  -F "file=@/path/to/image.jpg"
```

Poll upload job status (JWT required):

```bash
curl http://localhost/api/v1/image-jobs/<job_id> \
  -H "Authorization: Bearer <token>"
```

List images (JWT required, paginated):

```bash
curl "http://localhost/api/v1/images?page=1&per_page=20" \
  -H "Authorization: Bearer <token>"
```

List one image by id:

```bash
curl "http://localhost/api/v1/images?id=1" \
  -H "Authorization: Bearer <token>"
```

List multiple images by ids:

```bash
curl "http://localhost/api/v1/images?ids=1,2,5" \
  -H "Authorization: Bearer <token>"
```

Render an image by id (JWT required):

```bash
curl http://localhost/api/v1/image/1 \
  -H "Authorization: Bearer <token>"
```

Render a specific variant:

```bash
curl "http://localhost/api/v1/image/1?variant=thumbnail" \
  -H "Authorization: Bearer <token>"
```

## Bruno API client use case (dev workflow) 
https://www.usebruno.com/

### 1. Environment variables

In Bruno, define environment variables:
- `username`
- `password`

### 2. Basic login request

Create a request:
- Method: `POST`
- URL: `https://sample02.dev/api/v1/login`
- Header: `Content-Type: application/json`
- Header: `Accept: application/json`
- Body (JSON):

```json
{
  "username": "{{username}}",
  "password": "{{password}}"
}
```

### 3. Pre-request script (store JWT in `access_token`)

Use this Bruno pre-request script:

```javascript
const username = String(bru.getEnvVar("username") || "");
const password = String(bru.getEnvVar("password") || "");

const res = await bru.sendRequest({
  method: "POST",
  url: "https://sample02.dev/api/v1/login",
  headers: {
    "Content-Type": "application/json",
    "Accept": "application/json"
  },
  data: { username, password }
});

console.log("status:", res.status);
console.log("data:", res.data);
console.log("body:", res.body);

const payload =
  res.data ??
  (typeof res.body === "string" ? JSON.parse(res.body) : res.body);

if (!payload?.token) {
  throw new Error(`Token not found in response: ${JSON.stringify(res)}`);
}

bru.setVar("access_token", payload.token);
```

### 4. POST `/api/v1/images` with bearer token

Create a second request:
- Method: `POST`
- URL: `https://sample02.dev/api/v1/images`
- Header: `Authorization: Bearer {{access_token}}`
- Body type: `multipart/form-data`
- Field: `file` (type `file`)

Note:
- Keep Bruno auth mode disabled/none if you set `Authorization` manually in headers.
- The response returns `job_id` and `status: queued`.

### 5. GET `/api/v1/image-jobs/{job_id}` with bearer token (poll)

Create a request:
- Method: `GET`
- URL: `https://sample02.dev/api/v1/image-jobs/{{job_id}}`
- Header: `Authorization: Bearer {{access_token}}`

Response body:

```json
{
  "status": "completed",
  "image_id": 1,
  "error": null
}
```

Status values:
- `queued`
- `processing`
- `completed`
- `failed`

When status is `completed`, use `image_id` for render requests.

### 6. GET `/api/v1/images` with bearer token (list + filters)

Create a request:
- Method: `GET`
- URL: `https://sample02.dev/api/v1/images`
- Header: `Authorization: Bearer {{access_token}}`

Supported query parameters:
- `page` (integer >= 1, default `1`)
- `per_page` (integer 1..100, default `20`)
- `id` (single image id)
- `ids` (comma-separated image ids: `1,2,5`)

Rules:
- `id` and `ids` are mutually exclusive.
- When `id` or `ids` is provided, pagination parameters are ignored.
- The response includes `analysis.cost` when AI analysis cost exists.

Examples:
- `https://sample02.dev/api/v1/images?page=1&per_page=20`
- `https://sample02.dev/api/v1/images?id=1`
- `https://sample02.dev/api/v1/images?ids=1,2,5`

### 7. GET `/api/v1/image/{id}` with bearer token

Create a request:
- Method: `GET`
- URL: `https://sample02.dev/api/v1/image/{{image_id_from_job}}`
- Optional query param: `variant` with `original`, `thumbnail`, or `resized`
- Header: `Authorization: Bearer {{access_token}}`

Example:
- `https://sample02.dev/api/v1/image/1`
- `https://sample02.dev/api/v1/image/1?variant=thumbnail`

## Public key usage (optional for API clients)

- The API validates JWT signatures server-side.
- API clients only need local JWT signature/claim verification if they have a specific requirement to verify token content before using it.

## Error format

When an API call fails, the response body follows Problem Details:

```json
{
  "type": "urn:sample02:error:invalid-credentials",
  "title": "Invalid credentials",
  "status": 401,
  "detail": "Username or password is incorrect."
}
```
