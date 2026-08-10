# TaskDesk API

Laravel 12 API backend for TaskDesk.

- **Phase 1**: architectural skeleton — auth, roles, base SOLID patterns.
- **Phase 2**: Task Register — the task/support-call log, with a full status
  state machine, audit timeline, and department/role-scoped access.
- **Phase 3**: role model simplified to `superadmin` / `admin` / `user`; department is no
  longer an authorization boundary. Task assignment is instead governed by a `manager_id`
  reporting hierarchy — see [Roles and task assignment](#roles-and-task-assignment) below.

Dashboard rollups by hierarchy, Reports, Search, Knowledge Base, and notifications are still out
of scope.

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
   - The three roles (`superadmin`, `admin`, `user`)
   - Two departments (`IT Support` / `ITS`, `Finance` / `FIN`) — categorization only, not an
     authorization boundary
   - A handful of branches/clients, categories, and default SLA hours (Critical=4,
     High=24, Medium=72, Low=120)
   - Test users, all with password `password`, including a 3-level `manager_id` reporting
     chain for exercising task-assignment authorization end to end:

   | Email                          | Role       | Manager           | Department |
   |----------------------------------|-----------|--------------------|-----------|
   | superadmin@taskdesk.test       | superadmin | —                  | —          |
   | admin@taskdesk.test            | admin      | —                  | —          |
   | director@taskdesk.test         | user       | —                  | IT Support |
   | manager@taskdesk.test          | user       | director           | IT Support |
   | employee@taskdesk.test         | user       | manager            | IT Support |
   | teammate@taskdesk.test         | user       | manager            | IT Support |
   | financemanager@taskdesk.test   | user       | —                  | Finance    |
   | financeemployee@taskdesk.test  | user       | financemanager     | Finance    |

   `director → manager → {employee, teammate}` is a 3-level chain (director can assign
   directly to employee/teammate); employee and teammate are peers and cannot assign to each
   other; the Finance pair is an unrelated branch for verifying cross-hierarchy denial.

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
| GET    | `/api/users`  | Yes (superadmin/admin) | Example role-gated endpoint (returns 403 for a plain `user`) |
| GET    | `/api/users/me/assignable` | Yes | The actor's own record plus everyone they may assign a task to — descendants for a plain user, everyone for admin/superadmin |
| PATCH  | `/api/users/{id}/manager` | Yes (superadmin/admin) | Sets/changes a user's `manager_id`; returns 422 if it would create a cycle |

Authenticate subsequent requests with `Authorization: Bearer <token>`.

## Task Register endpoints

| Method | Endpoint                          | Description                                            |
|--------|------------------------------------|----------------------------------------------------------|
| GET    | `/api/work-items`                 | Paginated, filterable, sortable list (scoped by role) |
| POST   | `/api/work-items`                 | Create — any authenticated user, gated per-assignee by `TaskAssignmentAuthorizer` |
| GET    | `/api/work-items/{id}`            | Detail, including full timeline history                 |
| PATCH  | `/api/work-items/{id}`            | Update fields (a plain `user` may only touch `resolution`/`remarks`) |
| PATCH  | `/api/work-items/{id}/status`     | Status transition, via the state machine                |
| PATCH  | `/api/work-items/{id}/reassign`   | Reassignment — gated per-target by `TaskAssignmentAuthorizer` |
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

**Authorization**: superadmin and admin see/edit everything, globally — role authorization is a
simple check, never conditioned on department. A plain `user` can only view/update
(`resolution`/`remarks`/status) items assigned to them and cannot reassign or delete. This lives in
exactly two places: `App\Policies\WorkItemPolicy` (single-item checks) and
`EloquentWorkItemRepository::paginate()` (list-level scoping) — no ad-hoc `where()` clauses
elsewhere. Deleted items (`status = deleted`) are excluded from every list automatically.

## Roles and task assignment

Three roles: `superadmin` and `admin` are global (not tied to a department) and can assign a task
to anyone. Everyone else is a plain `user` — "manager" is not a role, it's a position in the
reporting hierarchy (a `user` who has other users reporting to them via `manager_id`).

**Who can assign a task to user X**: X themself (always), any of X's managers at any depth up the
chain, or any admin/superadmin. This is decided in exactly one place —
`App\Services\TaskAssignmentAuthorizer::canAssign()` — and `WorkItemPolicy`/`WorkItemController`
delegate to it for every create/reassign check rather than duplicating the rule.

**`App\Services\HierarchyService`** is the single source of truth for all `manager_id` traversal
(ancestors, descendants, cycle detection), implemented with `WITH RECURSIVE` CTEs rather than
looping in PHP — no other class writes a raw recursive query against `manager_id`. Setting a
`manager_id` is checked against `wouldCreateCycle()` on every change (`PATCH
/api/users/{id}/manager`), not just at user creation.

`department_id` on `users` is nullable and used only for categorization/reporting — it is never an
authorization boundary. `GET /api/users/me/assignable` backs the frontend's "Assign To" dropdown so
it never has to re-derive the hierarchy rules itself.

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
- A plain user unable to view/update another user's work item, or reassign an item to someone
  they're not authorized to assign
- Admin/superadmin authorization is global — view, create, and delete work items in any department
- List-level scoping (admin/superadmin see everything; a plain user sees only their own items)
- Task assignment authorization (`tests/Feature/WorkItems/TaskAssignmentAuthorizationTest.php`):
  self-assignment always succeeds; a direct manager can assign to their direct report; a manager
  three levels up can assign directly to a report three levels down (full-chain traversal, not just
  one level); peers cannot assign to each other; an unrelated user in a different branch of the
  hierarchy cannot assign even within the same department; admin/superadmin can assign to anyone;
  a `manager_id` change that would create a cycle is rejected with a 422; `HierarchyService`
  returns full-depth ancestor/descendant lists for a 3+ level chain
