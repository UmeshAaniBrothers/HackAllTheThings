-- Add headline_source column to track where headline text came from
-- Values: 'ad' (from ad creative/preview), 'youtube' (from YouTube video title), NULL (legacy/unknown)

ALTER TABLE ad_details
    ADD COLUMN headline_source VARCHAR(20) DEFAULT NULL AFTER tracking_ids_json;

-- Backfill: mark existing YouTube-sourced headlines
-- If the headline matches the youtube_metadata title for the same creative, it's from YouTube
UPDATE ad_details d
INNER JOIN ad_assets ass ON ass.creative_id = d.creative_id AND ass.type = 'video' AND ass.original_url LIKE '%youtube.com%'
INNER JOIN youtube_metadata ym ON CONCAT('https://www.youtube.com/watch?v=', ym.video_id) = ass.original_url
SET d.headline_source = 'youtube'
WHERE d.headline IS NOT NULL
  AND d.headline = ym.title;

-- Mark remaining non-null headlines as 'ad' sourced
UPDATE ad_details SET headline_source = 'ad'
WHERE headline IS NOT NULL AND headline != '' AND headline_source IS NULL;
