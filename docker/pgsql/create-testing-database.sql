-- Provisions the dedicated test database so the Pest suite never touches the
-- development data. Runs once on first container init (empty data volume).
-- The test DB name mirrors phpunit.xml (DB_DATABASE=fluxdesk_test).
CREATE DATABASE "fluxdesk_test";
GRANT ALL PRIVILEGES ON DATABASE "fluxdesk_test" TO "fluxdesk";
