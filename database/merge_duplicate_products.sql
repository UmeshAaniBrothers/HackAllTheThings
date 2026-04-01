-- Merge duplicate ad_products that have the same store_url (same app, different advertisers)
-- This keeps the product with the most metadata and migrates all references.

-- Step 1: Create temp table of duplicates — keep the one with best metadata (has app_metadata, most ad mappings)
CREATE TEMPORARY TABLE IF NOT EXISTS product_merge AS
SELECT
    dp.store_url,
    dp.id AS duplicate_id,
    keeper.id AS keeper_id
FROM ad_products dp
INNER JOIN (
    -- For each store_url, pick the "best" product: has app_metadata first, then most ads mapped
    SELECT store_url, MIN(best_id) AS id
    FROM (
        SELECT p.store_url,
            FIRST_VALUE(p.id) OVER (
                PARTITION BY p.store_url
                ORDER BY (CASE WHEN am.id IS NOT NULL THEN 0 ELSE 1 END),
                         (SELECT COUNT(*) FROM ad_product_map pm WHERE pm.product_id = p.id) DESC,
                         p.id ASC
            ) AS best_id
        FROM ad_products p
        LEFT JOIN app_metadata am ON am.product_id = p.id
        WHERE p.store_url IS NOT NULL
          AND p.store_url != ''
          AND p.store_url != 'not_found'
          AND p.store_platform IN ('ios', 'playstore')
    ) ranked
    GROUP BY store_url
) keeper ON dp.store_url = keeper.store_url
WHERE dp.id != keeper.id
  AND dp.store_url IS NOT NULL
  AND dp.store_url != ''
  AND dp.store_url != 'not_found'
  AND dp.store_platform IN ('ios', 'playstore');

-- Step 2: Migrate ad_product_map references (ignore if already exists)
INSERT IGNORE INTO ad_product_map (creative_id, product_id)
SELECT pm.creative_id, m.keeper_id
FROM ad_product_map pm
INNER JOIN product_merge m ON pm.product_id = m.duplicate_id;

-- Step 3: Migrate app_group_members references (ignore if already exists)
INSERT IGNORE INTO app_group_members (group_id, product_id, matched_keyword, auto_assigned)
SELECT agm.group_id, m.keeper_id, agm.matched_keyword, agm.auto_assigned
FROM app_group_members agm
INNER JOIN product_merge m ON agm.product_id = m.duplicate_id;

-- Step 4: Delete old mappings for duplicates
DELETE pm FROM ad_product_map pm
INNER JOIN product_merge m ON pm.product_id = m.duplicate_id;

DELETE agm FROM app_group_members agm
INNER JOIN product_merge m ON agm.product_id = m.duplicate_id;

-- Step 5: Delete duplicate app_metadata
DELETE am FROM app_metadata am
INNER JOIN product_merge m ON am.product_id = m.duplicate_id;

-- Step 6: Delete the duplicate products
DELETE p FROM ad_products p
INNER JOIN product_merge m ON p.id = m.duplicate_id;

-- Cleanup
DROP TEMPORARY TABLE IF EXISTS product_merge;

-- Show result
SELECT 'Merge complete' AS status,
       (SELECT COUNT(*) FROM ad_products WHERE store_platform IN ('ios','playstore')) AS total_products;
