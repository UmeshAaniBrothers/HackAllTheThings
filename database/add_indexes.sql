-- Missing indexes for performance
ALTER TABLE ad_details ADD INDEX idx_creative_snapshot (creative_id, snapshot_date);
ALTER TABLE ad_assets ADD INDEX idx_creative_url (creative_id, original_url(255));
ALTER TABLE ad_targeting ADD UNIQUE INDEX uk_targeting (creative_id, country, platform);
ALTER TABLE ads ADD INDEX idx_advertiser_status (advertiser_id, status);
ALTER TABLE ads ADD INDEX idx_advertiser_first_seen (advertiser_id, first_seen);
