# RTC Project Backend (Technical Runbook)

This repo hosts the RTC backend (Laravel) and Docker stack for local development and production.

## Requirements
- Docker + Docker Compose
- Linux/macOS/WSL2 recommended

## Project Layout
- `laravel/` Laravel application
- `docker-compose.yml` Local/dev stack (PHP-FPM, Nginx, MySQL)
- `docker-compose.prod.yml` Production stack (PHP-FPM, Nginx, MySQL, Caddy)
- `nginx.conf`, `nginx.prod.conf` Nginx configs
- `caddy/Caddyfile` TLS + reverse proxy config for production

## Local Development (Docker)
1) Start applications
```bash
docker compose up -d --build
```
3) Run app setup (inside container)
```bash
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
```
4) Access
- API: `http://localhost:8000`

## Production (first start)
1) Start the production stack
```bash
docker compose -f docker-compose.prod.yml up -d --build
```
2) Run Laravel setup (one time)
```bash
docker compose -f docker-compose.prod.yml exec app php artisan key:generate
docker compose -f docker-compose.prod.yml exec app php artisan migrate --seed
```

## Useful Commands
- View logs: `docker compose logs -f`
- Enter app container: `docker compose exec app bash`
- Cache clear: `docker compose exec app php artisan optimize:clear`

## Notes
- MySQL port mapping:
  - Dev: `3310 -> 3306`
  - Prod: `3310 -> 3306`
- File uploads are stored in the shared `storage_data` volume.
