-- PREPARED FOR THE PROJECT OWNER. NOT EXECUTED BY THE ASSISTANT.
-- MySQL 8.0. Check individual blocks; do not run the entire file blindly.
-- No password, authentication hash, data modification or privilege modification is requested.

-- A1. OWNER/ADMIN: confirm the server before any account setup.
SELECT VERSION() AS mysql_version, @@hostname AS server_host,
       @@port AS server_port, @@server_uuid AS server_uuid;
-- Stop if this is not the intended MySQL 8.0 server. The installed binary version is not proof.

-- A2. OWNER/ADMIN: choose the GRANT spelling based on partial_revokes; review mandatory roles.
SELECT @@GLOBAL.partial_revokes AS partial_revokes,
       @@GLOBAL.mandatory_roles AS mandatory_roles,
       @@GLOBAL.activate_all_roles_on_login AS activate_all_roles_on_login;
-- The simple setup guide requires mandatory_roles to be empty; otherwise stop for a role review.

SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = 'hotel_booking';
SELECT User, Host FROM mysql.user WHERE User = 'aurel_catalog_ro';
-- Before CREATE USER, expect the schema to exist and ZERO rows for this username.
-- If any such user exists, stop. Do not reuse it via IF NOT EXISTS or change its privileges blindly.

-- B. OWNER/ADMIN: AFTER the owner has created the new account and granted SELECT.
SHOW GRANTS FOR 'aurel_catalog_ro'@'127.0.0.1';
SELECT FROM_USER, FROM_HOST, TO_USER, TO_HOST
FROM mysql.role_edges
WHERE TO_USER = 'aurel_catalog_ro' AND TO_HOST = '127.0.0.1';
SELECT User, Host, Proxied_user, Proxied_host, With_grant
FROM mysql.proxies_priv
WHERE User = 'aurel_catalog_ro' AND Host = '127.0.0.1';
SELECT @@GLOBAL.mandatory_roles AS mandatory_roles;
-- Expect only USAGE + one database SELECT grant; no grant option; no roles/proxy/mandatory roles.

-- C1. OWNER, CONNECTED AS THE NEW RO USER: check authenticated identity and effective role inputs.
SELECT DATABASE() AS selected_database, USER() AS connection_identity,
       CURRENT_USER() AS authenticated_account, CURRENT_ROLE() AS active_roles,
       @@hostname AS server_host, @@port AS server_port, @@server_uuid AS server_uuid;
SHOW GRANTS;
SHOW GRANTS FOR CURRENT_USER;
SELECT * FROM information_schema.APPLICABLE_ROLES;
SELECT * FROM information_schema.ENABLED_ROLES;
-- Expect database hotel_booking, CURRENT_USER aurel_catalog_ro@127.0.0.1, active_roles NONE.
-- If identity/roles/grants differ, STOP. Do not test forbidden operations on production.

-- C2. OWNER/RO: inventory explicit global/database/table/column grants for the expected account.
SELECT GRANTEE, PRIVILEGE_TYPE, IS_GRANTABLE
FROM information_schema.USER_PRIVILEGES
WHERE GRANTEE = CONCAT(QUOTE('aurel_catalog_ro'), '@', QUOTE('127.0.0.1'));

SELECT GRANTEE, TABLE_SCHEMA, PRIVILEGE_TYPE, IS_GRANTABLE
FROM information_schema.SCHEMA_PRIVILEGES
WHERE GRANTEE = CONCAT(QUOTE('aurel_catalog_ro'), '@', QUOTE('127.0.0.1'));

SELECT GRANTEE, TABLE_SCHEMA, TABLE_NAME, PRIVILEGE_TYPE, IS_GRANTABLE
FROM information_schema.TABLE_PRIVILEGES
WHERE GRANTEE = CONCAT(QUOTE('aurel_catalog_ro'), '@', QUOTE('127.0.0.1'));

SELECT GRANTEE, TABLE_SCHEMA, TABLE_NAME, COLUMN_NAME, PRIVILEGE_TYPE, IS_GRANTABLE
FROM information_schema.COLUMN_PRIVILEGES
WHERE GRANTEE = CONCAT(QUOTE('aurel_catalog_ro'), '@', QUOTE('127.0.0.1'));
-- Expected: no global capabilities (USAGE/empty only); schema SELECT only, not grantable.
-- No explicit table/column grants for this freshly created account. Decode schema escaping per guide.
-- Also inspect SHOW GRANTS for dynamic capabilities; these four tables alone are not a full role audit.

-- C3. OWNER/RO: verify that the catalog table metadata is visible, without reading unrelated data rows.
SELECT TABLE_NAME, TABLE_TYPE, ENGINE, TABLE_COLLATION
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'hotel_booking'
  AND TABLE_NAME IN ('location','attraction','attraction_image','hotel','hotel_image','guide_category','guide_post','migrations')
ORDER BY TABLE_NAME;

SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'hotel_booking'
  AND TABLE_NAME IN ('location','attraction','attraction_image','hotel','hotel_image','guide_category','guide_post')
ORDER BY TABLE_NAME, ORDINAL_POSITION;

SELECT TABLE_NAME, CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = 'hotel_booking'
  AND TABLE_NAME IN ('location','attraction','attraction_image','hotel','hotel_image','guide_post')
ORDER BY TABLE_NAME, CONSTRAINT_NAME, ORDINAL_POSITION;

-- C4. OWNER/RO: execute only after the metadata inventory confirms these are BASE TABLES.
SHOW CREATE TABLE hotel_booking.location;
SHOW CREATE TABLE hotel_booking.attraction;
SHOW INDEX FROM hotel_booking.attraction;
-- SHOW CREATE TABLE reads the definition; it does not execute CREATE TABLE.
-- Missing optional catalog tables are an audit result, not a reason to grant CREATE or run a migration.
