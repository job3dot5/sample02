# sample02

Docker base environment for a Symfony API project:
- `nginx` (HTTPS + domain routing)
- `php-fpm` (PHP 8.3 + Composer + Xdebug)

Current project state: `apps/api` is mounted in containers but no Symfony app is scaffolded yet.

## Stack

- PHP 8.3 FPM
- Nginx
- Docker Compose

## Local domain

This setup serves the project on `sample02.dev` with HTTPS.

### 1. Install mkcert (one-time)

```bash
sudo apt update
sudo apt install mkcert libnss3-tools
mkcert -install
```

### 2. Generate certificate

```bash
mkcert sample02.dev
```

Generated files:
- `sample02.dev.pem`
- `sample02.dev-key.pem`

### 3. Copy certificate files

```bash
mv sample02.dev.pem [project-path]/docker/nginx/ssl/sample02.dev.crt
mv sample02.dev-key.pem [project-path]/docker/nginx/ssl/sample02.dev.key
```

### 4. Add hosts entry

Edit `/etc/hosts` as root and add:

```text
127.0.0.1 sample02.dev
```

## Run

```bash
docker compose up -d --build
```

Open: `https://sample02.dev`

If `apps/api` is still empty, the server response can be `File not found.` until a Symfony app is created.

## Stop

```bash
docker compose down
```
