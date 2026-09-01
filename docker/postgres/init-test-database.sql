-- The suite runs against Postgres, like production, so it needs a database of
-- its own: RefreshDatabase would otherwise wipe the one you develop against.
-- Postgres runs this once, when the volume is first created. On a volume that
-- already exists, create it by hand:
--   docker exec carrot-pgsql psql -U carrot -d carrot -c 'CREATE DATABASE carrot_test OWNER carrot;'
CREATE DATABASE carrot_test OWNER carrot;
