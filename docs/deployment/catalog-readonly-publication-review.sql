-- PREPARED ONLY. Not executed. Use only if ALL metadata columns/tables below exist in the approved target.
-- Intended for an approved migrated copy or a later approved post-migration read-only review.
-- These SELECTs report missing data; they never verify, repair or publish a record.

SELECT a.id, a.name, a.item_type, a.location_id, l.name AS location_name,
       l.code AS location_code, a.experience_location_code AS verification_location_code,
       a.slug, a.experience_category, a.is_active,
       a.experience_verified_by, a.experience_verified_at, a.experience_published_at, a.experience_archived_at,
       CASE WHEN l.id IS NULL THEN 'ORPHAN'
            WHEN NULLIF(TRIM(l.code),'') IS NULL THEN 'MISSING_LOCATION_CODE'
            WHEN a.experience_location_code IS NULL THEN 'UNVERIFIED_LOCATION'
            WHEN a.experience_location_code <> l.code THEN 'LOCATION_SNAPSHOT_MISMATCH'
            ELSE 'SNAPSHOT_MATCH_REQUIRES_HUMAN_REVIEW' END AS location_review,
       CASE WHEN NULLIF(TRIM(a.description),'') IS NULL THEN 1 ELSE 0 END AS description_missing,
       CASE WHEN NULLIF(TRIM(a.address),'') IS NULL THEN 1 ELSE 0 END AS address_missing,
       CASE WHEN NULLIF(TRIM(a.experience_summary),'') IS NULL THEN 1 ELSE 0 END AS summary_missing,
       CASE WHEN NULLIF(TRIM(a.experience_source_url),'') IS NULL THEN 1 ELSE 0 END AS source_missing,
       a.experience_duration_minutes, a.open_time, a.close_time,
       COALESCE(images.image_count,0) AS image_count,
       COALESCE(images.sources_missing,0) AS image_sources_missing,
       COALESCE(images.primary_count,0) AS primary_image_count
FROM attraction AS a
LEFT JOIN location AS l ON l.id = a.location_id
LEFT JOIN (
 SELECT attraction_id, COUNT(*) AS image_count,
        SUM(CASE WHEN NULLIF(TRIM(source_note),'') IS NULL THEN 1 ELSE 0 END) AS sources_missing,
        SUM(CASE WHEN is_primary = 1 THEN 1 ELSE 0 END) AS primary_count
 FROM attraction_image GROUP BY attraction_id
) AS images ON images.attraction_id = a.id
WHERE a.id > 0
ORDER BY a.id
LIMIT 500;
-- Verify image files/public URLs and rights separately; an image database row is not proof of either.
-- Caption, category/type match and hours/duration provenance require review as well.

SELECT slug, COUNT(*) AS row_count FROM attraction WHERE slug IS NOT NULL GROUP BY slug HAVING COUNT(*) > 1;
SELECT code, COUNT(*) AS row_count FROM location WHERE code IS NOT NULL GROUP BY code HAVING COUNT(*) > 1;

SELECT p.id, p.legacy_id, p.title, p.slug, p.status, p.is_featured,
       p.published_at, p.archived_at, p.author_name, p.cover_path,
       p.legacy_import_state, p.legacy_content_sha256,
       p.category_id, c.name AS category_name, c.is_active AS category_active,
       p.location_id, l.name AS location_name,
       CASE WHEN NULLIF(TRIM(p.body_html),'') IS NULL THEN 1 ELSE 0 END AS content_missing
FROM guide_post AS p
LEFT JOIN guide_category AS c ON c.id = p.category_id
LEFT JOIN location AS l ON l.id = p.location_id
WHERE p.legacy_id BETWEEN 1 AND 9
ORDER BY p.legacy_id;
-- Before final editorial approval, expect all imported legacy rows to remain draft,
-- with legacy ID 1 tracked as pending final approval and IDs 2-9 incomplete.
-- Preserve the original publication dates in legacy_metadata; never infer missing authors/licenses.

SELECT legacy_id, COUNT(*) AS row_count
FROM guide_post WHERE legacy_id IS NOT NULL GROUP BY legacy_id HAVING COUNT(*) > 1;
SELECT slug, COUNT(*) AS row_count FROM guide_post GROUP BY slug HAVING COUNT(*) > 1;
