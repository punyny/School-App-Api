<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## School API Quick Start

### Local (PHP CLI)

```bash
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

### Dev Modes

This project includes 2 simple dev modes so you can switch between normal local work and phone testing with ngrok.

#### 1. Local normal mode

Use this when developing on your Mac:

```bash
./scripts/dev/use-local-mode.sh
php artisan serve --host=0.0.0.0 --port=8001
```

Default local URL:

```text
http://127.0.0.1:8001
```

You can also pass a custom local URL:

```bash
./scripts/dev/use-local-mode.sh http://192.168.1.97:8001
```

#### 2. Phone testing with ngrok

Use this when your phone is on a different Wi-Fi or mobile data.

Install ngrok first:

```bash
brew install ngrok/ngrok/ngrok
ngrok config add-authtoken YOUR_TOKEN
```

Start Laravel:

```bash
php artisan serve --host=0.0.0.0 --port=8001
```

Start ngrok:

```bash
./scripts/dev/start-ngrok.sh 8001
```

Copy the generated `https://...ngrok-free.app` URL and switch the app into phone mode:

```bash
./scripts/dev/use-ngrok-mode.sh https://your-ngrok-url.ngrok-free.app
```

This updates `APP_URL` and clears Laravel cache, which helps email magic links work correctly on your phone.

### Docker

See [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md).

### Web Panels (Role-based)

After entering an email or username at `/login`, the web app sends a one-time sign-in link. Dashboards and CRUD pages are available by role after the user opens that email link:

- `super-admin`: `/super-admin/*` + `/panel/*` (students, announcements, attendance, homeworks, scores)
- `admin`: `/admin/*` + `/panel/*` (students, announcements, attendance, homeworks, scores)
- `teacher`: `/teacher/*` + `/panel/*` (announcements, attendance, homeworks, scores)
- `student`: `/student/dashboard`
- `parent`: `/parent/dashboard`
- `audit logs` (admin / super-admin): `/panel/audit-logs`

New users created by admin/super-admin can be created without a password for web access. In local development, the default `MAIL_MAILER=log` writes sign-in and verification links to your Laravel log unless you configure SMTP.

### Real-time Notifications (Broadcast + Echo/Pusher)

Events for `message`, `announcement`, and `incident report` are broadcast to private user channels:

- Channel: `private-users.{userId}`
- Event: `.realtime.notification`

Enable it with Pusher-compatible settings in `.env`:

```bash
BROADCAST_CONNECTION=pusher
PUSHER_APP_ID=your_app_id
PUSHER_APP_KEY=your_app_key
PUSHER_APP_SECRET=your_app_secret
PUSHER_APP_CLUSTER=mt1
PUSHER_HOST=
PUSHER_PORT=443
PUSHER_SCHEME=https
```

Install Pusher PHP SDK before using the `pusher` broadcast driver:

```bash
composer require pusher/pusher-php-server
```

If `BROADCAST_CONNECTION=log`, notifications are still created in DB but not pushed in real-time.

### API Tools

- OpenAPI spec: `docs/openapi.yaml`
- Postman collection: `docs/postman/school-api.postman_collection.json`
- Postman environment: `docs/postman/school-api.local.postman_environment.json`

### New Production Extensions

- API versioning: both `/api/*` and `/api/v1/*` are supported.
- Dashboard summary API: `GET /api/dashboard/summary`
- Report card APIs:
  - `GET /api/report-cards/{student}`
  - `GET /api/report-cards/{student}/pdf`
- Academic promotion API:
  - `POST /api/academic/promotions/promote-class`
- Bulk CSV APIs:
  - `POST /api/students/import/csv`, `GET /api/students/export/csv`
  - `POST /api/scores/import/csv`
  - `POST /api/attendance/import/csv`
- Soft-delete restore APIs:
  - `POST /api/users/{userId}/restore`
  - `POST /api/classes/{classId}/restore`
  - `POST /api/students/{studentId}/restore`
- Notification broadcast queue/scheduling:
  - `POST /api/notifications/broadcast`

### Monitoring and Admin IP Allowlist

Add these env keys when deploying:

```bash
SECURITY_ADMIN_IP_ALLOWLIST=127.0.0.1,10.0.0.5
SECURITY_SLOW_API_MS=1200
```

- `X-Request-Id` and `X-Response-Time-Ms` headers are attached to API responses.
- Slow API calls are logged automatically.
- `admin` / `super-admin` routes can be IP-restricted using `SECURITY_ADMIN_IP_ALLOWLIST`.

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
