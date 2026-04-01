-- fix_app_names.sql
-- Fixes app names that were derived from package names instead of real store names.
-- Run this once, then the updated enrichAppMetadata() will prevent recurrence.

-- Step 1: Update product_name from app_metadata where store has a better name
UPDATE ad_products p
INNER JOIN app_metadata am ON am.product_id = p.id
SET p.product_name = am.app_name
WHERE am.app_name IS NOT NULL
  AND am.app_name != ''
  AND am.app_name != p.product_name
  AND LENGTH(am.app_name) > 2;

-- Step 2: Delete incomplete app_metadata records (fetch failed, stored bad name)
-- so enrichAppMetadata() will re-fetch them
DELETE am FROM app_metadata am
WHERE am.icon_url IS NULL
  AND am.rating IS NULL
  AND am.developer_name IS NULL
  AND am.downloads IS NULL;

-- Step 3: For products that have same store_url but different names,
-- normalize the name to the one from app_metadata
-- (handles cases where app_metadata exists for one copy but not the other)
UPDATE ad_products p1
INNER JOIN ad_products p2 ON p1.store_url = p2.store_url
  AND p1.id != p2.id
  AND p1.store_url IS NOT NULL
  AND p1.store_url != ''
  AND p1.store_url != 'not_found'
INNER JOIN app_metadata am ON am.product_id = p2.id
  AND am.app_name IS NOT NULL AND am.app_name != ''
SET p1.product_name = am.app_name
WHERE p1.product_name != am.app_name;

-- Step 4: Show results
SELECT
  (SELECT COUNT(*) FROM ad_products p
   INNER JOIN app_metadata am ON am.product_id = p.id
   WHERE p.product_name = am.app_name AND am.app_name IS NOT NULL AND am.app_name != '') AS products_with_correct_name,
  (SELECT COUNT(*) FROM app_metadata
   WHERE icon_url IS NULL AND rating IS NULL AND developer_name IS NULL AND downloads IS NULL) AS incomplete_metadata_remaining,
  (SELECT COUNT(*) FROM ad_products
   WHERE store_platform IN ('ios', 'playstore') AND store_url IS NOT NULL AND store_url != '' AND store_url != 'not_found') AS total_store_products,
  (SELECT COUNT(*) FROM app_metadata) AS total_metadata_records;
