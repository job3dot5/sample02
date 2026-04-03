# Backend (BFF) Secure Consumption Example

This document describes the implementation example where:

- `apps/backend` is a Node.js/Express backend-for-frontend (BFF)
- `apps/frontend` is a Vue.js app that only calls local BFF endpoints

The browser never handles `API_USERNAME`, `API_PASSWORD`, or bearer tokens.

Related frontend documentation: [../frontend/README.md](../frontend/README.md)

## Technical stack (BFF)

- Node.js 22
- Express 4
- openapi-client-axios (client generated from `/docs/openapi.v1.yaml`)
- Axios (HTTP transport via OpenAPI client)
- Multer (`multipart/form-data` for upload)
- CORS
- Dotenv (configuration)

## Full example stack

- Backend BFF: Node.js + Express (`apps/backend`)
- Frontend: Vue 3 + Vite (`apps/frontend`)
- API: Symfony REST API (`apps/api`)

## Architecture

Flow:

1. Frontend calls (example `http://localhost:3000/images/:id`)
2. Backend authenticates against Symfony API (`POST /api/v1/login`)
3. Backend caches and refreshes JWT bearer token
4. Backend forwards request to Symfony API (`GET /api/v1/image/{id}`)
5. Backend returns data/blob to frontend

## Docker dev run

The existing Docker environment includes:

- `backend` service (Express, port `3000`)
- `frontend` service (Vite, port `5173`)

Run everything:

```bash
cp .env.example .env
docker compose up -d --build
```

Then open:

- Frontend: `http://localhost:5173`
- Backend health: `http://localhost:3000/health`

## Backend env variables

In Docker mode, backend/frontend variables are defined in the root `/.env`
(created from `/.env.example`) and injected via `docker-compose.yml`.

Used variables:

- `BACKEND_API_BASE_URL` (default `https://nginx`, container-to-container HTTPS)
- `BACKEND_NODE_TLS_REJECT_UNAUTHORIZED` (default `0`, dev only for self-signed cert)
- `BACKEND_API_USERNAME`
- `BACKEND_API_PASSWORD`
- `BACKEND_FRONTEND_ORIGIN`
- `BACKEND_JWT_ISSUER` (default `urn:sample02:api`)
- `BACKEND_JWT_AUDIENCE` (default `urn:sample02:client`)

## Frontend usage example

```js
const res = await fetch('http://localhost:3000/images/1');
const blob = await res.blob();
const imageUrl = URL.createObjectURL(blob);
```

## Upload limits

The BFF proxies upload requests to the Symfony API.
Current stack limits come from PHP runtime configuration:

- `upload_max_filesize=2M`
- `post_max_size=8M`

If the file exceeds `2M`, upload is rejected with `413`.
