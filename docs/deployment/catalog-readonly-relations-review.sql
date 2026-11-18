-- PREPARED OFFLINE IN PHASE 5C.1. NEVER EXECUTED.
-- Use only a separately approved dedicated READ-ONLY account.
-- First verify identity/effective privileges and inspect schema with catalog-readonly-schema.sql.
-- Run individual blocks only after all referenced tables/columns have been confirmed.
-- This file does not repair, seed, migrate, assign location codes or publish anything.

-- Block 1: exact base counts. These are individual read observations, not a backup snapshot.
SELECT 'location' AS entity, COUNT(*) AS exact_rows FROM location
UNION ALL SELECT 'attraction', COUNT(*) FROM attraction
UNION ALL SELECT 'hotel', COUNT(*) FROM hotel;

-- Block 2: aggregate each child separately to avoid multiplying attraction/hotel counts.
SELECT l.id, l.name, l.region, l.country,
       COALESCE(a.attraction_count, 0) AS attraction_count,
       COALESCE(a.active_attraction_count, 0) AS active_attraction_count,
       COALESCE(h.hotel_count, 0) AS hotel_count,
       COALESCE(h.active_hotel_count, 0) AS active_hotel_count
FROM location AS l
LEFT JOIN (
    SELECT location_id, COUNT(*) AS attraction_count,
           SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active_attraction_count
    FROM attraction GROUP BY location_id
) AS a ON a.location_id = l.id
LEFT JOIN (
    SELECT location_id, COUNT(*) AS hotel_count,
           SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_hotel_count
    FROM hotel GROUP BY location_id
) AS h ON h.location_id = l.id
ORDER BY l.id;
-- An active attraction is not necessarily verified or published.

-- Block 3: orphan relationships; never delete or reassign IDs automatically.
SELECT 'attraction_without_location' AS issue, COUNT(*) AS row_count
FROM attraction AS a LEFT JOIN location AS l ON l.id = a.location_id WHERE l.id IS NULL
UNION ALL
SELECT 'hotel_without_location', COUNT(*)
FROM hotel AS h LEFT JOIN location AS l ON l.id = h.location_id WHERE l.id IS NULL;

-- Block 4: duplicate candidates within a location; collation may ignore accents/case.
SELECT location_id, item_type, TRIM(name) AS candidate_name, COUNT(*) AS row_count
FROM attraction
GROUP BY location_id, item_type, TRIM(name)
HAVING COUNT(*) > 1
ORDER BY row_count DESC, location_id, candidate_name;

-- Block 5: same-name candidates across locations, not proof of a wrong relationship.
SELECT item_type, TRIM(name) AS candidate_name, COUNT(*) AS row_count,
       COUNT(DISTINCT location_id) AS distinct_location_count
FROM attraction
GROUP BY item_type, TRIM(name)
HAVING COUNT(DISTINCT location_id) > 1
ORDER BY distinct_location_count DESC, candidate_name;

-- Block 6: quality inventory from base columns, without inventing missing coordinates/prices.
SELECT item_type, COUNT(*) AS row_count,
       SUM(CASE WHEN NULLIF(TRIM(description), '') IS NULL THEN 1 ELSE 0 END) AS description_missing,
       SUM(CASE WHEN NULLIF(TRIM(address), '') IS NULL THEN 1 ELSE 0 END) AS address_missing,
       SUM(CASE WHEN lat IS NULL OR lng IS NULL THEN 1 ELSE 0 END) AS coordinates_incomplete,
       SUM(CASE WHEN lat < -90 OR lat > 90 OR lng < -180 OR lng > 180 THEN 1 ELSE 0 END) AS coordinates_out_of_range,
       SUM(CASE WHEN price_min IS NULL OR price_max IS NULL THEN 1 ELSE 0 END) AS price_incomplete,
       SUM(CASE WHEN price_min > price_max THEN 1 ELSE 0 END) AS price_range_inverted,
       SUM(CASE WHEN price_min = 0 AND price_max = 0 THEN 1 ELSE 0 END) AS zero_range_not_confirmed_free
FROM attraction GROUP BY item_type ORDER BY item_type;

-- Block 7: base review page. Change the cursor only after reviewing the previous page.
SELECT a.id, a.name, a.item_type, a.location_id, l.name AS linked_location_name,
       a.address, a.lat, a.lng, a.price_min, a.price_max, a.is_active
FROM attraction AS a LEFT JOIN location AS l ON l.id = a.location_id
WHERE a.id > 0 ORDER BY a.id LIMIT 500;
-- A valid foreign key or equal name does not verify real-world geography.

-- Block 8: optional; requires location.code to exist.
SELECT id, name, region, country, code
FROM location
WHERE code IN ('vn-ha-noi', 'vn-ho-chi-minh', 'vn-da-lat', 'vn-phu-quoc')
ORDER BY code, id;
-- Compare these rows AND name/country candidates from catalog-readonly-location-review.sql.
-- A code that points to the wrong real-world place still needs human review.

-- Block 9: optional; requires attraction_image and its metadata columns to exist.
SELECT ai.id, ai.attraction_id, a.name AS attraction_name,
       ai.image_url, ai.is_primary, ai.caption, ai.source_note
FROM attraction_image AS ai LEFT JOIN attraction AS a ON a.id = ai.attraction_id
WHERE ai.id > 0 ORDER BY ai.id LIMIT 500;
-- Check local file existence, approved storage roots and rights separately; never fetch arbitrary remote URLs.

-- Block 10: optional; requires attraction_image. Only counts rows, not usable/licensed images.
SELECT a.id, a.name, a.location_id, COUNT(ai.id) AS image_row_count,
       SUM(CASE WHEN ai.is_primary = 1 THEN 1 ELSE 0 END) AS primary_image_count
FROM attraction AS a LEFT JOIN attraction_image AS ai ON ai.attraction_id = a.id
GROUP BY a.id, a.name, a.location_id
HAVING COUNT(ai.id) = 0 OR SUM(CASE WHEN ai.is_primary = 1 THEN 1 ELSE 0 END) <> 1
ORDER BY a.id;

-- Block 11: optional; requires hotel_image. Reads public image metadata only.
SELECT hi.id, hi.hotel_id, h.name AS hotel_name, hi.image_url, hi.is_primary
FROM hotel_image AS hi LEFT JOIN hotel AS h ON h.id = hi.hotel_id
WHERE hi.id > 0 ORDER BY hi.id LIMIT 500;
