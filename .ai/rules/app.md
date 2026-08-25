---
paths:
  - 'app/**'
---

# App

## Fortify authenticates by username, not email
config/fortify.php sets 'username' => 'username'. Users log in with a login ID (users.username, unique, lowercase, 4-20 chars matching /^[a-z][a-z0-9_-]*$/), never with their email. Email is still used for password reset and verification ('email' => 'email').
Registration rules live in ProfileValidationRules::usernameRules() and are applied in App\Actions\Fortify\CreateNewUser, which lowercases and trims the input before validating. Login POSTs must send `username`; the login rate limiter keys on Fortify::username().
