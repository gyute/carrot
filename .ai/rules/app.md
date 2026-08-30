---
paths:
  - 'app/**'
---

# App

## Fortify authenticates by username, not email
config/fortify.php sets 'username' => 'username'. Users log in with a login ID (users.username, unique, lowercase, 4-20 chars matching /^[a-z][a-z0-9_-]*$/), never with their email. Email is still used for password reset and verification ('email' => 'email').
Registration rules live in ProfileValidationRules::usernameRules() and are applied in App\Actions\Fortify\CreateNewUser, which lowercases and trims the input before validating. Login POSTs must send `username`; the login rate limiter keys on Fortify::username().

## Roles live on the user row, and a manager is scoped to one department
users.role is member / manager / admin (App\Enums\UserRole, cast on the model). Gate 'admin' and Gate 'reviewer' are defined in AppServiceProvider; a reviewer is an admin or a manager.
User::isManagerOf(?string $department) is the department-stage check and is false for a null department, so a tool with no department never falls to a manager. Scopes: User::query()->admins(), User::query()->managersOf($department).
Two ways in, one pair of columns: Admin\UserController (/admin/users, admins only) and `php artisan carrot:promote <username> --role=manager --department=<name>` (`--role=admin`, `--revoke`) for a box with no browser. Both refuse a manager without a department.

## People are retired, never deleted
`users` soft-deletes. `App\Actions\Users\RetireUser` is the only way out: it hands the tools they owned to a successor, deletes what was private (inbox messages, bell notifications, passkeys, 2FA), scrubs name/email/username, and soft-deletes the row.

Deleting the row outright used to throw. `tool_submissions.user_id` cascades, and Postgres re-checks `tools.approved_submission_id` while that cascade is mid-flight, so anyone who had ever had a tool published could not be removed at all. Keeping the row also keeps the approval history readable - it renders as `退職したユーザー` rather than blank.

Because of the soft delete, every relation that names who did something carries `->withTrashed()` (13 of them, on Tool, ToolSubmission, ToolRequest, ToolRun and Message). Drop one and that column goes empty the moment the person leaves. Queries that must *not* see retired people - `User::query()->admins()`, `managersOf()`, `developmentTeam()`, the auth provider - get that for free from the global scope; `Admin\UserController::index` opts back in with `withTrashed()` so an admin can still see who left.

`demo:seed --clear` force-deletes its own accounts instead: they are not people, and a retired row would hold the login IDs the next seed wants back.

