# API Client Authentication Guide

## Overview

This API uses JWT bearer authentication (`lexik/jwt-authentication-bundle`).

Authentication flow:
- Call `POST /api/login` with JSON credentials to obtain a JWT.
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
curl -X POST http://localhost/api/login \
  -H 'Content-Type: application/json' \
  -d '{"username":"demo","password":"demo"}'
```

Call a protected endpoint:

```bash
curl http://localhost/api/me \
  -H "Authorization: Bearer <token>"
```

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
