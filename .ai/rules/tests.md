---
paths:
  - 'tests/**'
---

# Tests

## The suite runs on Postgres, not SQLite
phpunit.xml forces DB_CONNECTION=pgsql and DB_DATABASE=carrot_test; host/port/credentials come from .env, so a changed DB_PORT still works. Never move it back to SQLite: SQLite has no real column types and reads JSON out of a text column, so `where('data->message', ...)` passed every run while Postgres rejected it with "operator does not exist: text ->> unknown" - a 500 on /inbox/{ulid} that a correct, existing test could not catch.
jsonb does not preserve the key order it was handed. Comparing a decoded jsonb column with toBe() fails on order alone; ksort() first, or compare with toEqual().
Create the test database once: docker exec carrot-pgsql psql -U carrot -d carrot -c 'CREATE DATABASE carrot_test OWNER carrot'. compose.yaml does it for a fresh volume via docker/postgres/init-test-database.sql.
