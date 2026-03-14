# Deployment (Docker)

## 1) Prepare environment

```bash
cp .env.docker.example .env
```

Then edit `.env` if you need custom database credentials.

## 2) Start containers

```bash
docker compose up -d --build
```

App endpoints:
- API base: `http://localhost:8080/api`
- OpenAPI YAML: `http://localhost:8080/api/docs/openapi.yaml`

## 3) Run migrations + seed demo data

```bash
docker compose exec app php artisan migrate --seed --force
```

Demo login accounts use password: `password123`
- `superadmin@example.com`
- `admin@example.com`
- `teacher@example.com`
- `student@example.com`
- `parent@example.com`

## 4) Useful operations

```bash
# run tests

docker compose exec app php artisan test

# clear cache

docker compose exec app php artisan optimize:clear

# stop stack

docker compose down
```

To remove database volume too:

```bash
docker compose down -v
```
