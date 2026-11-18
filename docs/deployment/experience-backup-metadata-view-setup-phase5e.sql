-- PHASE 5E PROPOSAL ONLY. NOT EXECUTED. OWNER REVIEW AND APPROVAL REQUIRED.
-- ON HOLD BY OWNER: current workflow is the three SELECTs in
-- experience-backup-owner-inventory-phase5e.sql via phpMyAdmin. Do not execute this setup.
-- Owner executes with the owner's own credential; never give that credential to Codex.
-- Creates metadata objects OUTSIDE hotel_booking. No business-table DML/DDL.
-- Do not use --force, CREATE OR REPLACE, or IF NOT EXISTS. Stop at the first error.
-- The owner/definer must have full metadata visibility for hotel_booking:
-- TRIGGER and EVENT over that schema, plus SHOW_ROUTINE or global SELECT for routines.
-- A restricted creator could produce an incomplete view. Do not label that zero-object proof.

-- A. OWNER PREFLIGHT, read-only. Verify before running B.
-- Expected: MySQL 8.0.30, port 3306, UUID below, new_schema_already_exists = 0.
SELECT VERSION() AS mysql_version, @@port AS server_port, @@server_uuid AS server_uuid,
       CURRENT_USER() AS creator,
       (SELECT COUNT(*) FROM information_schema.SCHEMATA
        WHERE SCHEMA_NAME = 'aurel_backup_audit_057fe2d6') AS new_schema_already_exists;
-- Expected UUID: 7880df8a-92cd-11f0-85c1-a841f442ddbd.
-- Do not continue if identity differs, the schema exists, or creator visibility is uncertain.

-- B. OWNER-APPROVED WRITES: exactly one NEW auxiliary schema and one read-only metadata view.
-- No user creation, no admin grant, no change to hotel_booking.
CREATE DATABASE `aurel_backup_audit_057fe2d6`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- DEFINER defaults to the creating owner account. It must remain valid and sufficiently privileged.
-- Fixed WHERE clauses, no parameters, no object bodies, no customer/business rows or dynamic SQL.
-- UNION ALL + TEMPTABLE makes this view non-updatable.
CREATE ALGORITHM = TEMPTABLE
SQL SECURITY DEFINER VIEW `aurel_backup_audit_057fe2d6`.`catalog_objects_v1` AS
SELECT _utf8mb4'SCOPE' COLLATE utf8mb4_unicode_ci AS object_kind,
       _utf8mb4'hotel_booking' COLLATE utf8mb4_unicode_ci AS object_schema,
       _utf8mb4'catalog-inventory-v1' COLLATE utf8mb4_unicode_ci AS object_name,
       _utf8mb4'' COLLATE utf8mb4_unicode_ci AS parent_name,
       _utf8mb4'' COLLATE utf8mb4_unicode_ci AS object_detail
UNION ALL
SELECT 'TRIGGER', TRIGGER_SCHEMA, TRIGGER_NAME, EVENT_OBJECT_TABLE,
       CAST(EVENT_MANIPULATION AS CHAR(64))
FROM information_schema.TRIGGERS
WHERE TRIGGER_SCHEMA = 'hotel_booking'
UNION ALL
SELECT 'ROUTINE', ROUTINE_SCHEMA, ROUTINE_NAME, CAST('' AS CHAR(64)),
       CAST(ROUTINE_TYPE AS CHAR(64))
FROM information_schema.ROUTINES
WHERE ROUTINE_SCHEMA = 'hotel_booking'
UNION ALL
SELECT 'EVENT', EVENT_SCHEMA, EVENT_NAME, CAST('' AS CHAR(64)), CAST(STATUS AS CHAR(64))
FROM information_schema.EVENTS
WHERE EVENT_SCHEMA = 'hotel_booking';

-- C. OWNER-APPROVED GRANT: SELECT reads the view; SHOW VIEW allows Codex to audit its definition.
-- Object-level grant: auxiliary schema is a literal identifier, not a database-level wildcard pattern.
GRANT SELECT, SHOW VIEW
ON `aurel_backup_audit_057fe2d6`.`catalog_objects_v1`
TO 'aurel_catalog_ro'@'127.0.0.1';

-- D. OWNER CHECK, read-only. Send these metadata counts/definition, never credentials.
SHOW CREATE VIEW `aurel_backup_audit_057fe2d6`.`catalog_objects_v1`;
SHOW GRANTS FOR 'aurel_catalog_ro'@'127.0.0.1';
SELECT
    (SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA='hotel_booking') AS owner_triggers,
    (SELECT COUNT(*) FROM `aurel_backup_audit_057fe2d6`.`catalog_objects_v1` WHERE object_kind='TRIGGER') AS view_triggers,
    (SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA='hotel_booking') AS owner_routines,
    (SELECT COUNT(*) FROM `aurel_backup_audit_057fe2d6`.`catalog_objects_v1` WHERE object_kind='ROUTINE') AS view_routines,
    (SELECT COUNT(*) FROM information_schema.EVENTS WHERE EVENT_SCHEMA='hotel_booking') AS owner_events,
    (SELECT COUNT(*) FROM `aurel_backup_audit_057fe2d6`.`catalog_objects_v1` WHERE object_kind='EVENT') AS view_events;
-- Each owner/view pair must agree. One SCOPE row must always exist, even if all object counts are zero.

-- E. CODEX/RO READS, only AFTER owner approval, setup and explicit grant/role re-verification.
-- Check server identity separately; the SCOPE row is not server authentication.
SELECT object_kind, object_schema, object_name, parent_name, object_detail
FROM `aurel_backup_audit_057fe2d6`.`catalog_objects_v1`
ORDER BY object_kind, object_name;

SELECT TABLE_SCHEMA, TABLE_NAME, SECURITY_TYPE, IS_UPDATABLE
FROM information_schema.VIEWS
WHERE TABLE_SCHEMA='aurel_backup_audit_057fe2d6' AND TABLE_NAME='catalog_objects_v1';

-- F. DIRECT GRANT ALTERNATIVES, DOCUMENTATION ONLY: keep commented; not part of the recommendation.
-- SHOW_ROUTINE is read-only but GLOBAL, exposing routine definitions across schemas:
-- GRANT SHOW_ROUTINE ON *.* TO 'aurel_catalog_ro'@'127.0.0.1';
-- SHOW VIEW is read-only, needed only if dumping existing views directly:
-- GRANT SHOW VIEW ON `hotel\_booking`.* TO 'aurel_catalog_ro'@'127.0.0.1';
-- Do NOT grant these to the audit RO account: they also permit object modification.
-- GRANT TRIGGER ON `hotel\_booking`.* TO 'aurel_catalog_ro'@'127.0.0.1';
-- GRANT EVENT ON `hotel\_booking`.* TO 'aurel_catalog_ro'@'127.0.0.1';
-- The escaped underscore spelling assumes partial_revokes=0, as verified on the current server.
-- No GRANT EXECUTE, CREATE ROUTINE, ALTER ROUTINE, FILE, PROCESS, SUPER, or SELECT ON *.* is proposed.
