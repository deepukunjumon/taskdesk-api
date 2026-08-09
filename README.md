# TaskDesk API

Laravel 12 API backend for TaskDesk.

- **Phase 1**: architectural skeleton — auth, roles, base SOLID patterns.
- **Phase 2**: Work Register — the task/support-call log, with a full status
  state machine, audit timeline, and department/role-scoped access.

Dashboard KPIs, Reports, Search, Knowledge Base, and notifications are still out of scope.

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

   This seeds:
   - The three roles (`superadmin`, `admin`, `employee`)
   - Two departments (`IT Support` / `ITS`, `Finance` / `FIN`)
   - A handful of branches/clients, categories, and default SLA hours (Critical=4,
     High=24, Medium=72, Low=120)
   - Test users, all with password `password`:

   | Email                          | Role       | Department |
   |----------------------------------|-----------|-----------|
   | superadmin@taskdesk.test       | superadmin | —          |
   | admin@taskdesk.test            | admin      | IT Support |
   | employee@taskdesk.test         | employee   | IT Support |
   | financeadmin@taskdesk.test     | admin      | Finance    |
   | financeemployee@taskdesk.test  | employee   | Finance    |

   The Finance pair exists specifically so department-scoping (an Admin from one
   department cannot see another's work items) is demoable/testable out of the box.

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

## Work Register endpoints

| Method | Endpoint                          | Description                                            |
|--------|------------------------------------|----------------------------------------------------------|
| GET    | `/api/work-items`                 | Paginated, filterable, sortable list (scoped by role/department) |
| POST   | `/api/work-items`                 | Create (superadmin/admin only)                          |
| GET    | `/api/work-items/{id}`            | Detail, including full timeline history                 |
| PATCH  | `/api/work-items/{id}`            | Update fields (employees may only touch `resolution`/`remarks`) |
| PATCH  | `/api/work-items/{id}/status`     | Status transition, via the state machine                |
| PATCH  | `/api/work-items/{id}/reassign`   | Reassignment (superadmin/admin only)                     |
| DELETE | `/api/work-items/{id}`            | Logical delete — sets `status = deleted`, row is never removed |
| GET/POST | `/api/departments`               | Lookup + basic create                                    |
| GET/POST | `/api/branches`                  | Lookup + basic create                                    |
| GET/POST | `/api/categories`                | Lookup + basic create (optional `?department_id=`)        |
| GET    | `/api/sla-settings`                | List SLA hours per priority                              |
| PATCH  | `/api/sla-settings/{id}`           | Update SLA hours (superadmin/admin only)                 |

Index filters: `status`, `priority`, `department_id`, `assigned_to_id`, `entry_type`, `branch_id`,
`category_id`, `date_from`, `date_to`, `sort_by`, `sort_dir`, `per_page`.

**Status state machine**: `open → in_progress → (pending ⇄ in_progress) → closed`, plus a direct
`open → closed` path for work that was already done and is only being logged after the fact (the
timestamps still get populated — `start_time` is backfilled to "now" if it was never set, same as
entering `in_progress`). Enforced by `App\Services\WorkItemStatusTransitioner` — invalid transitions
return a 422, and closing requires `resolution` to be set. Every status change and reassignment
writes a row to `work_item_timelines` (actor, from/to status, optional note).

**Authorization**: superadmin sees/edits everything; admin is scoped to their own department;
employees can only view/update (`resolution`/`remarks`/status) items assigned to them and cannot
reassign or delete. This lives in exactly two places: `App\Policies\WorkItemPolicy` (single-item
checks) and `EloquentWorkItemRepository::paginate()` (list-level scoping) — no ad-hoc `where()`
clauses elsewhere. Deleted items (`status = deleted`) are excluded from every list automatically.

**The frontend never re-derives any of these rules.** Every `WorkItem` API response includes:
- `permissions` — `{ can_update, can_update_status, can_reassign, can_delete }`, computed by
  `WorkItemPolicy` for the current user against this specific item.
- `editable_fields` — exactly which field names the current user may send to `PATCH
  /work-items/{id}` (`WorkItemPolicy::editableFields()` — also the single source of truth
  `UpdateWorkItemRequest` uses to build its validation rules, so the two can never drift apart).
- `next_statuses` — the valid targets for `PATCH /work-items/{id}/status` right now
  (`WorkItemStatusTransitioner::nextStatuses()`), excluding `deleted` since that's reached only via
  the dedicated `DELETE` endpoint.

The authenticated user's own `UserResource` (from `/api/me`) similarly carries an `abilities` object
(currently just `can_create_work_items`) for list-level abilities that aren't tied to one item.

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
for every future resource in this codebase; `WorkItem` follows the same pattern (see
`WorkItemRepositoryInterface` / `EloquentWorkItemRepository` / `WorkItemService` /
`WorkItemController` / `WorkItemPolicy`).

Department/Branch/Category/SlaSetting lookups are intentionally **not** routed through the full
Repository/Service pattern — they're plain CRUD with no business logic, called out in the Phase 2
spec as "basic CRUD, low priority." Their controllers talk to Eloquent directly.

`work_id` (e.g. `W0001`) is generated by `WorkItemService::create()` using a dedicated
`work_item_sequences` counter table locked with `lockForUpdate()` inside a DB transaction — this is
what makes concurrent creation collision-free (see `EloquentWorkItemRepository::nextWorkNumber()`).

## Testing

Tests run against MySQL (this environment has no `pdo_sqlite` extension). Create a dedicated test
database and adjust the credentials in `phpunit.xml` if yours differ from `root`/`root`:

```bash
mysql -u root -p -e "CREATE DATABASE taskdesk_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php artisan test
```

Feature tests cover:
- Successful login, invalid-credentials rejection
- A role-gated endpoint returning 403 for a user without the required role (`/api/users`)
- Sequential, collision-free `work_id` generation across many rapid creations
- The full valid status transition chain, and rejection of invalid transitions (422)
- Closing a work item without a `resolution` failing
- Every status change and reassignment writing a `work_item_timelines` row
- An employee unable to view/update another employee's work item, or reassign
- An admin unable to view or create work items in another department
- List-level department/assignee scoping (admin sees only their department; employee sees only
  their own items; superadmin sees everything)
