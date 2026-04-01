-- Performance indexes for Ad Intelligence Dashboard
-- Run this on the production database to speed up common queries

-- Composite index for advertiser + time filtering (most common combo)
ALTER TABLE ads ADD INDEX idx_advertiser_first_seen (advertiser_id, first_seen);

-- Index for ad_type filtering
ALTER TABLE ads ADD INDEX idx_ad_type (ad_type);

-- Index for status + last_seen (activity queries)
ALTER TABLE ads ADD INDEX idx_status_last_seen (status, last_seen);

-- Composite indexes for ad_targeting lookups
ALTER TABLE ad_targeting ADD INDEX idx_targeting_creative_country (creative_id, country);

-- Index for ad_assets type lookups
ALTER TABLE ad_assets ADD INDEX idx_assets_type (type);

-- Index for ad_product_map lookups
ALTER TABLE ad_product_map ADD INDEX idx_prodmap_creative (creative_id);

-- Index for youtube_metadata view count sorting
ALTER TABLE youtube_metadata ADD INDEX idx_yt_views (view_count DESC);

-- Index for app_metadata product lookups
ALTER TABLE app_metadata ADD INDEX idx_appmeta_product (product_id);

-- Index for first_seen time-based queries
ALTER TABLE ads ADD INDEX idx_first_seen (first_seen);

-- Index for view_count sorting
ALTER TABLE ads ADD INDEX idx_view_count (view_count);
