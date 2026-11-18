-- PREPARED ONLY. Not executed. Requires the approved read-only connection and schema inventory.
-- Base columns only: usable before the experience metadata migration when these columns are confirmed.
-- No ID repair, code assignment, deduplication, seeding or publication occurs here.

SELECT a.location_id, l.name AS linked_location_name, l.country, a.item_type,
       COUNT(*) AS attraction_count,
       SUM(CASE WHEN l.id IS NULL THEN 1 ELSE 0 END) AS orphan_count,
       SUM(CASE WHEN NULLIF(TRIM(a.description),'') IS NULL THEN 1 ELSE 0 END) AS description_missing,
       SUM(CASE WHEN NULLIF(TRIM(a.address),'') IS NULL THEN 1 ELSE 0 END) AS address_missing
FROM attraction AS a
LEFT JOIN location AS l ON l.id = a.location_id
GROUP BY a.location_id, l.name, l.country, a.item_type
ORDER BY a.location_id, a.item_type;

-- Name matching supplies candidates for human review, never an automatic stable-identity assignment.
SELECT expected.location_key, expected.canonical_name, l.id AS candidate_location_id,
       l.name AS candidate_location_name, l.country
FROM (
 SELECT 'vn-ha-noi' AS location_key, 'Hà Nội' AS canonical_name
 UNION ALL SELECT 'vn-ho-chi-minh','Hồ Chí Minh'
 UNION ALL SELECT 'vn-da-lat','Đà Lạt'
 UNION ALL SELECT 'vn-phu-quoc','Phú Quốc'
) AS expected
LEFT JOIN location AS l ON TRIM(l.name) = expected.canonical_name
ORDER BY expected.location_key, l.id;
-- Collation can make this comparison accent/case insensitive. Check country/region/address manually.
-- Zero matches or multiple candidates must be resolved by a reviewer, never by auto-increment ID.

SELECT location_id, item_type, name, COUNT(*) AS candidate_duplicates
FROM attraction
GROUP BY location_id, item_type, name
HAVING COUNT(*) > 1
ORDER BY candidate_duplicates DESC, location_id, name;

-- First review page only. For another page, replace 0 with the last reviewed ID after approval.
SELECT a.id, a.name, a.item_type, a.location_id, l.name AS linked_location_name,
       l.country, a.address, a.description, a.lat, a.lng, a.open_time, a.close_time, a.is_active
FROM attraction AS a
LEFT JOIN location AS l ON l.id = a.location_id
WHERE a.id > 0
ORDER BY a.id
LIMIT 500;
-- Compare candidate rows with the seed review CSV and trusted external information.
-- A valid FK confirms existence of a location ID, not the real-world correctness of that location.
