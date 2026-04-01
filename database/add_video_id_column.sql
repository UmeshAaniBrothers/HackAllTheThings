-- Add normalized video_id column to ad_assets for fast YouTube lookups
-- This eliminates slow LIKE CONCAT('%', video_id, '%') queries

-- Step 1: Add the column
ALTER TABLE ad_assets ADD COLUMN video_id VARCHAR(20) NULL AFTER original_url;

-- Step 2: Populate from existing YouTube URLs
UPDATE ad_assets
SET video_id = SUBSTRING_INDEX(SUBSTRING_INDEX(original_url, 'v=', -1), '&', 1)
WHERE type = 'video'
  AND original_url LIKE '%youtube.com%watch%v=%'
  AND video_id IS NULL;

-- Also handle youtu.be short URLs
UPDATE ad_assets
SET video_id = SUBSTRING_INDEX(SUBSTRING_INDEX(original_url, 'youtu.be/', -1), '?', 1)
WHERE type = 'video'
  AND original_url LIKE '%youtu.be/%'
  AND video_id IS NULL;

-- Step 3: Add index
ALTER TABLE ad_assets ADD INDEX idx_video_id (video_id);
