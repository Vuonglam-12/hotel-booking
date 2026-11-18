-- PREPARED ONLY: not executed during Stage 5A.
-- Requires separately approved read-only access. Never obtain credentials from project .env.
-- First connect interactively using the approved host, port, account and database.
-- Verify this first block against the approval BEFORE executing the remaining blocks.
-- Server read_only alone does not prove the current account lacks write privileges.

SELECT DATABASE() AS selected_database, CURRENT_USER() AS authenticated_account,
       @@hostname AS server_host, @@port AS server_port, @@server_uuid AS server_uuid,
       VERSION() AS mysql_version, @@version_comment AS version_comment,
       @@session.sql_mode AS session_sql_mode, @@global.sql_mode AS global_sql_mode,
       @@session.time_zone AS session_time_zone, @@global.time_zone AS global_time_zone,
       @@system_time_zone AS system_time_zone, @@foreign_key_checks AS foreign_key_checks,
       @@character_set_database AS database_charset, @@collation_database AS database_collation,
       @@read_only AS server_read_only;
SHOW GRANTS;

-- Continue only after identity/database/account and read-only grants have been approved.
SELECT TABLE_NAME, ENGINE, TABLE_COLLATION, TABLE_ROWS, DATA_LENGTH, INDEX_LENGTH
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('location','attraction','attraction_image','guide_category','guide_post','hotel','hotel_image','migrations')
ORDER BY TABLE_NAME;
-- TABLE_ROWS is an InnoDB estimate; do not present it as an exact production row count.

SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT,
       CHARACTER_SET_NAME, COLLATION_NAME, EXTRA
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('location','attraction','attraction_image','guide_category','guide_post','hotel','hotel_image')
ORDER BY TABLE_NAME, ORDINAL_POSITION;

SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME, SUB_PART
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('location','attraction','attraction_image','guide_category','guide_post','hotel','hotel_image')
ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;

SELECT k.TABLE_NAME, k.CONSTRAINT_NAME, k.COLUMN_NAME,
       k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME, r.UPDATE_RULE, r.DELETE_RULE
FROM information_schema.KEY_COLUMN_USAGE AS k
JOIN information_schema.REFERENTIAL_CONSTRAINTS AS r
  ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
 AND r.TABLE_NAME = k.TABLE_NAME AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
WHERE k.TABLE_SCHEMA = DATABASE()
  AND (k.TABLE_NAME IN ('location','attraction','attraction_image','guide_category','guide_post','hotel','hotel_image')
       OR k.REFERENCED_TABLE_NAME IN ('location','attraction','guide_category','guide_post'))
ORDER BY k.TABLE_NAME, k.CONSTRAINT_NAME, k.ORDINAL_POSITION;
-- Incoming FK metadata is needed to avoid breaking shared IDs. No rows in unrelated tables are read.

-- Execute only if the preceding inventory confirms the Laravel migrations table exists.
SELECT migration, batch
FROM migrations
WHERE migration IN (
 '2026_10_23_000001_add_experience_catalog_metadata',
 '2026_10_23_000002_create_travel_guides',
 '2026_10_23_000003_widen_guide_publication_datetime')
ORDER BY migration;
-- If a rollback is later planned, a separately reviewed read of other names/batches may be required.
-- Do not use a shorthand timestamp prefix to select deployment migrations.
