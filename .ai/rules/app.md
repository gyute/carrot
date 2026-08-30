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
