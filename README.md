# TaskDesk API

Laravel 12 API backend for TaskDesk. Phase 1 scope: architectural skeleton only (auth, roles, base
SOLID patterns). No business modules yet — see the project spec for what's explicitly out of scope.

## Stack

- Laravel 12, PHP 8.3
- Laravel Sanctum (bearer token auth)
- spatie/laravel-permission (roles)
- MySQL 8

## Local setup

### Prerequisites

- PHP 8.3+ (this project pins `platform.php` to 8.3.20 in `composer.json`)
- Composer
- MySQL 8 running locally

### Steps

1. Install dependencies:

   ```bash
   composer install
   ```

2. Copy the environment file and generate an app key:

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. Edit `.env` and set your database credentials:

   ```
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=taskdesk
   DB_USERNAME=root
   DB_PASSWORD=your_password
   ```

4. Create the database:

   ```bash
   mysql -u root -p -e "CREATE DATABASE taskdesk CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   ```

5. Run migrations and seed roles + test users:

   ```bash
   php artisan migrate
   php artisan db:seed
   ```

   This seeds the three roles (`superadmin`, `admin`, `employee`) and one test user per role,
   all with password `password`:

   | Email                        | Role       |
   |-------------------------------|-----------|
   | superadmin@taskdesk.test     | superadmin |
   | admin@taskdesk.test          | admin      |
   | employee@taskdesk.test       | employee   |

6. Serve the app:

   ```bash
   php artisan serve
   ```

   API is now available at `http://127.0.0.1:8000/api`.

## Environment variables of note

- `CORS_ALLOWED_ORIGINS` — comma-separated list of allowed frontend origins (default matches the
  Vite dev server at `http://localhost:5173`).
- `SANCTUM_STATEFUL_DOMAINS` / `FRONTEND_URL` — reserved for future stateful/cookie-based auth if
  the app moves off bearer tokens; not required for the current token-based flow.

## Auth endpoints

| Method | Endpoint      | Auth required | Description                    |
|--------|---------------|----------------|--------------------------------|
| POST   | `/api/login`  | No             | Returns a Sanctum bearer token |
| POST   | `/api/logout` | Yes            | Revokes the current token      |
| GET    | `/api/me`     | Yes            | Returns the authenticated user |
| GET    | `/api/users`  | Yes (superadmin/admin) | Example role-gated endpoint (returns 403 for `employee`) |

Authenticate subsequent requests with `Authorization: Bearer <token>`.

## Architecture

Business logic never lives in controllers. The request lifecycle is:

```
Route -> Controller (HTTP only) -> Service (business logic) -> Repository (DB access, behind an interface)
```

- `app/Repositories/Contracts` — interfaces
- `app/Repositories/Eloquent` — Eloquent implementations, bound in `App\Providers\RepositoryServiceProvider`
- `app/Services` — business logic, depends on repository interfaces
- `app/Enums` — `Role` is the single source of truth for role name strings
- `app/Policies` — role-based authorization, registered in `App\Providers\AuthServiceProvider`

The `User` resource (repository + service + controller + policy) is the reference implementation
for every future resource in this codebase.

## Testing

Tests run against MySQL (this environment has no `pdo_sqlite` extension). Create a dedicated test
database and adjust the credentials in `phpunit.xml` if yours differ from `root`/`root`:

```bash
mysql -u root -p -e "CREATE DATABASE taskdesk_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php artisan test
```

Feature tests cover: successful login, invalid-credentials rejection, and a role-gated endpoint
returning 403 for a user without the required role (`/api/users`, restricted to
`superadmin`/`admin`).
