-- Migration: Add new columns to ad_details for expanded data extraction
-- Run this on existing databases to add support for:
--   - display_url: visible URL shown in the ad
--   - ad_width/ad_height: ad creative dimensions
--   - headlines_json: all headline variations for responsive ads
--   - descriptions_json: all description variations
--   - tracking_ids_json: GA, GTM, FB Pixel IDs found in the ad

ALTER TABLE ad_details
    ADD COLUMN display_url VARCHAR(512) AFTER landing_url,
    ADD COLUMN ad_width INT UNSIGNED AFTER display_url,
    ADD COLUMN ad_height INT UNSIGNED AFTER ad_width,
    ADD COLUMN headlines_json TEXT COMMENT 'JSON array of all headline variations (responsive ads)' AFTER ad_height,
    ADD COLUMN descriptions_json TEXT COMMENT 'JSON array of all description variations' AFTER headlines_json,
    ADD COLUMN tracking_ids_json TEXT COMMENT 'JSON array of tracking IDs (GA, GTM, FB Pixel)' AFTER descriptions_json;
