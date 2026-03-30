-- Adminer 4.7.8 MySQL dump

SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

SET NAMES utf8mb4;

DROP TABLE IF EXISTS `ab_test_detections`;
CREATE TABLE `ab_test_detections` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `advertiser_id` varchar(64) NOT NULL,
  `test_type` enum('url_variant','headline_variant','cta_variant') NOT NULL,
  `creative_ids` text NOT NULL,
  `variant_count` int(11) NOT NULL DEFAULT 2,
  `similarity_score` decimal(4,2) DEFAULT NULL,
  `confidence_score` decimal(5,1) DEFAULT NULL,
  `winner_creative_id` varchar(128) DEFAULT NULL,
  `winner_status` enum('confirmed','likely','pending') DEFAULT 'pending',
  `detected_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_advertiser` (`advertiser_id`),
  KEY `idx_type` (`test_type`),
  KEY `idx_detected` (`detected_at`),
  KEY `idx_winner` (`winner_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `ads`;
CREATE TABLE `ads` (
  `creative_id` varchar(128) NOT NULL,
  `advertiser_id` varchar(64) NOT NULL,
  `organization_id` int(10) unsigned DEFAULT NULL,
  `ad_type` enum('text','image','video') DEFAULT 'text',
  `first_seen` datetime NOT NULL,
  `last_seen` datetime NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `hash_signature` varchar(64) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`creative_id`),
  KEY `idx_advertiser` (`advertiser_id`),
  KEY `idx_status` (`status`),
  KEY `idx_last_seen` (`last_seen`),
  KEY `idx_first_seen` (`first_seen`),
  KEY `idx_advertiser_status` (`advertiser_id`,`status`),
  KEY `idx_advertiser_created` (`advertiser_id`,`created_at`),
  KEY `idx_status_lastseen` (`status`,`last_seen`),
  KEY `idx_ads_org` (`organization_id`),
  KEY `idx_ads_created_date` (`created_at`),
  KEY `idx_ads_status_adv` (`status`,`advertiser_id`),
  KEY `idx_ads_created_adv` (`created_at`,`advertiser_id`),
  KEY `idx_ads_adv_status_created` (`advertiser_id`,`status`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `advertiser_fetch_log`;
CREATE TABLE `advertiser_fetch_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `advertiser_id` varchar(64) NOT NULL,
  `status` enum('success','failed') NOT NULL,
  `ads_found` int(10) unsigned NOT NULL DEFAULT 0,
  `pages_fetched` int(10) unsigned NOT NULL DEFAULT 0,
  `duration_ms` int(10) unsigned DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_advertiser` (`advertiser_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`),
  KEY `idx_adv_created` (`advertiser_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `advertiser_profiles`;
CREATE TABLE `advertiser_profiles` (
  `advertiser_id` varchar(64) NOT NULL,
  `display_name` varchar(255) DEFAULT NULL,
  `total_lifetime_ads` int(11) DEFAULT 0,
  `active_duration_days` int(11) DEFAULT 0,
  `dominant_ad_type` enum('text','image','video') DEFAULT NULL,
  `dominant_cta_style` varchar(100) DEFAULT NULL,
  `primary_countries` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`primary_countries`)),
  `primary_platforms` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`primary_platforms`)),
  `avg_campaign_duration_days` decimal(8,2) DEFAULT NULL,
  `ad_frequency_per_week` decimal(8,2) DEFAULT NULL,
  `intelligence_score` decimal(5,2) DEFAULT 0.00,
  `profile_updated_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`advertiser_id`),
  KEY `idx_score` (`intelligence_score`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `ad_assets`;
CREATE TABLE `ad_assets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `creative_id` varchar(128) NOT NULL,
  `type` enum('image','video','text') NOT NULL,
  `original_url` text DEFAULT NULL,
  `local_path` varchar(512) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_creative` (`creative_id`),
  CONSTRAINT `ad_assets_ibfk_1` FOREIGN KEY (`creative_id`) REFERENCES `ads` (`creative_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `ad_details`;
CREATE TABLE `ad_details` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `creative_id` varchar(128) NOT NULL,
  `headline` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `cta` varchar(255) DEFAULT NULL,
  `landing_url` text DEFAULT NULL,
  `snapshot_date` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_creative` (`creative_id`),
  KEY `idx_snapshot` (`snapshot_date`),
  CONSTRAINT `ad_details_ibfk_1` FOREIGN KEY (`creative_id`) REFERENCES `ads` (`creative_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `ad_tags`;
CREATE TABLE `ad_tags` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `creative_id` varchar(128) NOT NULL,
  `tag_id` bigint(20) unsigned NOT NULL,
  `tagged_by` enum('user','system') DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_ad_tag` (`creative_id`,`tag_id`),
  KEY `idx_tag` (`tag_id`),
  CONSTRAINT `ad_tags_ibfk_1` FOREIGN KEY (`creative_id`) REFERENCES `ads` (`creative_id`) ON DELETE CASCADE,
  CONSTRAINT `ad_tags_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `ad_targeting`;
CREATE TABLE `ad_targeting` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `creative_id` varchar(128) NOT NULL,
  `country` varchar(10) NOT NULL,
  `platform` varchar(50) DEFAULT NULL,
  `detected_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_creative` (`creative_id`),
  KEY `idx_country` (`country`),
  KEY `idx_platform` (`platform`),
  KEY `idx_creative_country_platform` (`creative_id`,`country`,`platform`),
  KEY `idx_targeting_detected` (`detected_at`),
  CONSTRAINT `ad_targeting_ibfk_1` FOREIGN KEY (`creative_id`) REFERENCES `ads` (`creative_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `ai_ad_analysis`;
CREATE TABLE `ai_ad_analysis` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `creative_id` varchar(128) NOT NULL,
  `hooks_detected` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`hooks_detected`)),
  `sentiment` enum('aggressive','moderate','soft','neutral') DEFAULT NULL,
  `sentiment_score` decimal(5,2) DEFAULT NULL,
  `copy_cluster_id` int(11) DEFAULT NULL,
  `keywords` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`keywords`)),
  `persuasion_techniques` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`persuasion_techniques`)),
  `analyzed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_creative` (`creative_id`),
  KEY `idx_sentiment` (`sentiment`),
  KEY `idx_creative_sentiment` (`creative_id`,`sentiment`),
  CONSTRAINT `ai_ad_analysis_ibfk_1` FOREIGN KEY (`creative_id`) REFERENCES `ads` (`creative_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `alert_log`;
CREATE TABLE `alert_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `rule_id` bigint(20) unsigned DEFAULT NULL,
  `alert_type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `channel` varchar(20) NOT NULL,
  `advertiser_id` varchar(64) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `delivery_status` enum('sent','failed','pending') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_rule` (`rule_id`),
  KEY `idx_type_date` (`alert_type`,`created_at`),
  KEY `idx_advertiser` (`advertiser_id`),
  KEY `idx_created_status` (`created_at`,`delivery_status`),
  KEY `idx_alert_log_status` (`delivery_status`,`created_at`),
  KEY `idx_alert_log_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `alert_rules`;
CREATE TABLE `alert_rules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `organization_id` int(10) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `rule_type` enum('new_ad','ad_stopped','new_country','landing_change','burst_detected','custom') NOT NULL,
  `advertiser_id` varchar(64) DEFAULT NULL,
  `conditions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`conditions`)),
  `channels` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`channels`)),
  `is_active` tinyint(1) DEFAULT 1,
  `last_triggered_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_type` (`rule_type`),
  KEY `idx_active` (`is_active`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `anomaly_log`;
CREATE TABLE `anomaly_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `anomaly_type` varchar(50) NOT NULL COMMENT 'volume_spike, volume_drop, geo_expansion, creative_shift',
  `severity` enum('info','warning','critical') NOT NULL DEFAULT 'warning',
  `advertiser_id` varchar(64) DEFAULT NULL,
  `z_score` decimal(8,4) DEFAULT NULL,
  `current_value` decimal(12,2) DEFAULT NULL,
  `expected_value` decimal(12,2) DEFAULT NULL,
  `message` text NOT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `is_acknowledged` tinyint(1) NOT NULL DEFAULT 0,
  `acknowledged_by` int(10) unsigned DEFAULT NULL,
  `detected_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_anomaly_type` (`anomaly_type`),
  KEY `idx_anomaly_severity` (`severity`),
  KEY `idx_anomaly_advertiser` (`advertiser_id`),
  KEY `idx_anomaly_detected` (`detected_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `api_usage_log`;
CREATE TABLE `api_usage_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `api_key` varchar(64) NOT NULL,
  `endpoint` varchar(255) NOT NULL,
  `method` varchar(10) NOT NULL,
  `response_code` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_api_key` (`api_key`),
  KEY `idx_created` (`created_at`),
  KEY `idx_key_created` (`api_key`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `aso_app_metadata`;
CREATE TABLE `aso_app_metadata` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `app_id` varchar(255) NOT NULL,
  `store` enum('play','appstore') NOT NULL DEFAULT 'play',
  `title` varchar(500) DEFAULT NULL,
  `subtitle` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `short_description` varchar(255) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `developer` varchar(255) DEFAULT NULL,
  `rating` decimal(3,2) DEFAULT NULL,
  `review_count` int(10) unsigned DEFAULT 0,
  `installs` varchar(50) DEFAULT NULL,
  `version` varchar(50) DEFAULT NULL,
  `country` varchar(5) NOT NULL DEFAULT 'US',
  `metadata_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata_json`)),
  `collected_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_app_store_country` (`app_id`,`store`,`country`),
  KEY `idx_category` (`category`),
  KEY `idx_store` (`store`),
  KEY `idx_collected` (`collected_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `aso_keyword_rankings`;
CREATE TABLE `aso_keyword_rankings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `keyword` varchar(255) NOT NULL,
  `app_id` varchar(255) NOT NULL,
  `store` enum('play','appstore') NOT NULL DEFAULT 'play',
  `country` varchar(5) NOT NULL DEFAULT 'US',
  `rank_position` int(10) unsigned DEFAULT NULL,
  `snapshot_date` date NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_keyword_app_date` (`keyword`,`app_id`,`store`,`country`,`snapshot_date`),
  KEY `idx_keyword` (`keyword`),
  KEY `idx_app` (`app_id`),
  KEY `idx_date` (`snapshot_date`),
  KEY `idx_keyword_date` (`keyword`,`snapshot_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `aso_tracked_apps`;
CREATE TABLE `aso_tracked_apps` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `app_id` varchar(255) NOT NULL,
  `store` enum('play','appstore') NOT NULL DEFAULT 'play',
  `country` varchar(5) NOT NULL DEFAULT 'US',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_app_store_country` (`app_id`,`store`,`country`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `audit_log`;
CREATE TABLE `audit_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(100) NOT NULL,
  `entity_id` varchar(128) DEFAULT NULL,
  `old_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_value`)),
  `new_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_value`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_audit_user` (`user_id`),
  KEY `idx_audit_entity` (`entity_type`,`entity_id`),
  KEY `idx_audit_action` (`action`),
  KEY `idx_audit_created` (`created_at`),
  KEY `idx_audit_created_desc` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `brand_safety_violations`;
CREATE TABLE `brand_safety_violations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `creative_id` varchar(128) DEFAULT NULL,
  `advertiser_id` varchar(64) DEFAULT NULL,
  `category` varchar(50) NOT NULL,
  `keyword` varchar(255) NOT NULL,
  `severity` enum('critical','high','medium','low') DEFAULT 'low',
  `context` text DEFAULT NULL,
  `detected_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_advertiser` (`advertiser_id`),
  KEY `idx_category` (`category`),
  KEY `idx_severity` (`severity`),
  KEY `idx_detected` (`detected_at`),
  KEY `idx_creative` (`creative_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `budget_estimates`;
CREATE TABLE `budget_estimates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `advertiser_id` varchar(64) NOT NULL,
  `estimated_monthly` decimal(12,2) NOT NULL,
  `estimated_annual` decimal(14,2) NOT NULL,
  `confidence` enum('low','medium','high') DEFAULT 'low',
  `multipliers_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`multipliers_json`)),
  `calculated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_advertiser` (`advertiser_id`),
  KEY `idx_calculated` (`calculated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `campaign_correlations`;
CREATE TABLE `campaign_correlations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `correlation_type` enum('simultaneous','response','wave','messaging') NOT NULL,
  `advertiser_ids` text NOT NULL,
  `correlation_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`correlation_data`)),
  `detected_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_type` (`correlation_type`),
  KEY `idx_detected` (`detected_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `cluster_members`;
CREATE TABLE `cluster_members` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cluster_id` bigint(20) unsigned NOT NULL,
  `creative_id` varchar(128) NOT NULL,
  `similarity_score` float DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uidx_cluster_creative` (`cluster_id`,`creative_id`),
  KEY `idx_cluster` (`cluster_id`),
  KEY `idx_creative` (`creative_id`),
  CONSTRAINT `cluster_members_ibfk_1` FOREIGN KEY (`cluster_id`) REFERENCES `creative_clusters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `competitive_snapshots`;
CREATE TABLE `competitive_snapshots` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `advertiser_id` varchar(64) NOT NULL,
  `competitive_score` int(11) NOT NULL DEFAULT 0,
  `market_share_score` int(11) NOT NULL DEFAULT 0,
  `velocity_score` int(11) NOT NULL DEFAULT 0,
  `geo_score` int(11) NOT NULL DEFAULT 0,
  `platform_score` int(11) NOT NULL DEFAULT 0,
  `longevity_score` int(11) NOT NULL DEFAULT 0,
  `diversity_score` int(11) NOT NULL DEFAULT 0,
  `estimated_spend_usd` decimal(12,2) DEFAULT NULL,
  `spend_confidence` enum('low','medium','high') DEFAULT 'low',
  `rank_position` int(10) unsigned DEFAULT NULL,
  `snapshot_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_adv_date` (`advertiser_id`,`snapshot_date`),
  KEY `idx_comp_date` (`snapshot_date`),
  KEY `idx_comp_score` (`competitive_score`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `copy_scores`;
CREATE TABLE `copy_scores` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `creative_id` varchar(128) NOT NULL,
  `advertiser_id` varchar(64) DEFAULT NULL,
  `total_score` int(11) NOT NULL DEFAULT 0,
  `grade` varchar(3) NOT NULL DEFAULT 'F',
  `readability_score` int(11) DEFAULT 0,
  `emotional_score` int(11) DEFAULT 0,
  `specificity_score` int(11) DEFAULT 0,
  `urgency_score` int(11) DEFAULT 0,
  `social_proof_score` int(11) DEFAULT 0,
  `benefit_score` int(11) DEFAULT 0,
  `cta_score` int(11) DEFAULT 0,
  `headline_score` int(11) DEFAULT 0,
  `recommendations_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`recommendations_json`)),
  `scored_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_creative` (`creative_id`),
  KEY `idx_advertiser` (`advertiser_id`),
  KEY `idx_score` (`total_score`),
  KEY `idx_grade` (`grade`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `creative_analysis_cache`;
CREATE TABLE `creative_analysis_cache` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `advertiser_id` varchar(64) NOT NULL,
  `analysis_json` longtext NOT NULL,
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_advertiser` (`advertiser_id`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `creative_clusters`;
CREATE TABLE `creative_clusters` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `advertiser_id` varchar(64) NOT NULL,
  `cluster_name` varchar(255) DEFAULT NULL,
  `member_count` int(11) DEFAULT 0,
  `is_ab_test` tinyint(1) DEFAULT 0,
  `primary_creative_id` varchar(128) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_advertiser` (`advertiser_id`),
  KEY `idx_ab_test` (`is_ab_test`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `creative_fingerprints`;
CREATE TABLE `creative_fingerprints` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `creative_id` varchar(128) NOT NULL,
  `text_fingerprint` varchar(64) NOT NULL,
  `image_fingerprint` varchar(128) DEFAULT NULL,
  `phash` varchar(64) DEFAULT NULL,
  `phash_source` varchar(20) DEFAULT NULL,
  `cluster_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_creative` (`creative_id`),
  KEY `idx_text_fp` (`text_fingerprint`),
  KEY `idx_image_fp` (`image_fingerprint`),
  KEY `idx_cluster` (`cluster_id`),
  KEY `idx_phash` (`phash`),
  CONSTRAINT `creative_fingerprints_ibfk_1` FOREIGN KEY (`creative_id`) REFERENCES `ads` (`creative_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `daily_stats`;
CREATE TABLE `daily_stats` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `stat_date` date NOT NULL,
  `advertiser_id` varchar(64) DEFAULT NULL,
  `total_ads` int(10) unsigned DEFAULT 0,
  `active_ads` int(10) unsigned DEFAULT 0,
  `new_ads` int(10) unsigned DEFAULT 0,
  `stopped_ads` int(10) unsigned DEFAULT 0,
  `countries_targeted` int(10) unsigned DEFAULT 0,
  `avg_ad_duration_days` decimal(8,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_date_advertiser` (`stat_date`,`advertiser_id`),
  KEY `idx_stats_date` (`stat_date`),
  KEY `idx_stats_advertiser` (`advertiser_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `data_quality_log`;
CREATE TABLE `data_quality_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `check_type` enum('missing_field','anomaly','duplicate','stale_data','format_error') NOT NULL,
  `entity_type` varchar(50) NOT NULL,
  `entity_id` varchar(128) DEFAULT NULL,
  `severity` enum('low','medium','high','critical') DEFAULT 'medium',
  `description` text NOT NULL,
  `resolved` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_type` (`check_type`),
  KEY `idx_severity` (`severity`),
  KEY `idx_resolved` (`resolved`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `detected_patterns`;
CREATE TABLE `detected_patterns` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `pattern_type` enum('burst','seasonality','scaling','decline','revival_wave') NOT NULL,
  `advertiser_id` varchar(64) DEFAULT NULL,
  `description` text NOT NULL,
  `confidence` decimal(5,2) DEFAULT 0.00,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `detected_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_type` (`pattern_type`),
  KEY `idx_advertiser` (`advertiser_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `digest_subscriptions`;
CREATE TABLE `digest_subscriptions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `frequency` enum('daily','weekly') NOT NULL DEFAULT 'daily',
  `sections` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Array of section names to include' CHECK (json_valid(`sections`)),
  `preferred_hour` tinyint(3) unsigned NOT NULL DEFAULT 8 COMMENT 'Hour of day (0-23) to send',
  `preferred_day` tinyint(3) unsigned NOT NULL DEFAULT 1 COMMENT 'Day of week (0=Sun, 1=Mon) for weekly',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_freq` (`user_id`,`frequency`),
  KEY `idx_digest_active` (`is_active`,`frequency`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `domain_rate_stats`;
CREATE TABLE `domain_rate_stats` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `domain` varchar(255) NOT NULL,
  `requests_count` int(10) unsigned DEFAULT 0,
  `last_request_at` timestamp NULL DEFAULT NULL,
  `blocked_count` int(10) unsigned DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_domain` (`domain`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `fatigue_snapshots`;
CREATE TABLE `fatigue_snapshots` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `advertiser_id` varchar(64) NOT NULL,
  `fatigue_score` int(11) NOT NULL DEFAULT 0,
  `fatigue_grade` varchar(2) NOT NULL DEFAULT 'A',
  `active_creatives` int(11) DEFAULT 0,
  `avg_age_days` decimal(6,1) DEFAULT NULL,
  `oldest_days` int(11) DEFAULT NULL,
  `recommendation` text DEFAULT NULL,
  `snapshot_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_advertiser` (`advertiser_id`),
  KEY `idx_score` (`fatigue_score`),
  KEY `idx_snapshot` (`snapshot_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `gap_analysis_results`;
CREATE TABLE `gap_analysis_results` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `advertiser_id` varchar(64) NOT NULL,
  `gap_category` enum('geographic','format','messaging','scale','timing') NOT NULL,
  `gap_type` varchar(50) NOT NULL,
  `detail` text DEFAULT NULL,
  `impact_score` int(11) DEFAULT 0,
  `action_recommendation` text DEFAULT NULL,
  `analyzed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_advertiser` (`advertiser_id`),
  KEY `idx_category` (`gap_category`),
  KEY `idx_impact` (`impact_score`),
  KEY `idx_analyzed` (`analyzed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `health_checks`;
CREATE TABLE `health_checks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `check_type` varchar(50) NOT NULL,
  `status` enum('healthy','degraded','down') DEFAULT 'healthy',
  `response_time_ms` decimal(10,2) DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `checked_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_health_type` (`check_type`),
  KEY `idx_health_checked` (`checked_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `job_queue`;
CREATE TABLE `job_queue` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `job_type` varchar(100) NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`)),
  `priority` int(11) DEFAULT 0,
  `status` enum('pending','processing','completed','dead') NOT NULL DEFAULT 'pending',
  `attempts` int(10) unsigned DEFAULT 0,
  `max_retries` int(10) unsigned DEFAULT 3,
  `error_message` text DEFAULT NULL,
  `result` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`result`)),
  `scheduled_at` datetime NOT NULL DEFAULT current_timestamp(),
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_status_scheduled` (`status`,`scheduled_at`),
  KEY `idx_type` (`job_type`),
  KEY `idx_priority` (`priority`,`id`),
  KEY `idx_completed` (`completed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `landing_pages`;
CREATE TABLE `landing_pages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `url_hash` varchar(64) NOT NULL,
  `url` text NOT NULL,
  `domain` varchar(255) NOT NULL,
  `funnel_type` enum('app_install','lead_gen','ecommerce','content','saas','other') DEFAULT NULL,
  `page_title` text DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `app_name` varchar(255) DEFAULT NULL,
  `app_category` varchar(100) DEFAULT NULL,
  `pricing_detected` text DEFAULT NULL,
  `has_form` tinyint(1) DEFAULT 0,
  `has_pricing` tinyint(1) DEFAULT 0,
  `has_app_download` tinyint(1) DEFAULT 0,
  `technologies` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`technologies`)),
  `last_scraped_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_url_hash` (`url_hash`),
  KEY `idx_domain` (`domain`),
  KEY `idx_funnel` (`funnel_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `landing_page_changes`;
CREATE TABLE `landing_page_changes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `landing_page_id` bigint(20) unsigned NOT NULL,
  `field_changed` varchar(50) NOT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `detected_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_landing` (`landing_page_id`),
  KEY `idx_page_detected` (`landing_page_id`,`detected_at`),
  CONSTRAINT `landing_page_changes_ibfk_1` FOREIGN KEY (`landing_page_id`) REFERENCES `landing_pages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `managed_advertisers`;
CREATE TABLE `managed_advertisers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `advertiser_id` varchar(64) NOT NULL,
  `name` varchar(255) NOT NULL,
  `status` enum('new','active','fetching','paused','error','deleted') NOT NULL DEFAULT 'new',
  `fetch_interval` int(10) unsigned NOT NULL DEFAULT 21600 COMMENT 'Seconds between auto-fetches',
  `total_ads` int(10) unsigned NOT NULL DEFAULT 0,
  `active_ads` int(10) unsigned NOT NULL DEFAULT 0,
  `last_fetched_at` datetime DEFAULT NULL,
  `last_fetch_ads` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'Ads found in last fetch',
  `fetch_count` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'Total fetch attempts',
  `error_message` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_advertiser_id` (`advertiser_id`),
  KEY `idx_status` (`status`),
  KEY `idx_last_fetched` (`last_fetched_at`),
  KEY `idx_name` (`name`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `market_trend_snapshots`;
CREATE TABLE `market_trend_snapshots` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `snapshot_type` enum('momentum','forecast','health','seasonality') NOT NULL,
  `snapshot_json` longtext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_type` (`snapshot_type`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `notification_channels`;
CREATE TABLE `notification_channels` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `channel_type` enum('email','telegram','slack') NOT NULL,
  `config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`config`)),
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_type` (`channel_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `organizations`;
CREATE TABLE `organizations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `plan` enum('free','pro','enterprise') NOT NULL DEFAULT 'free',
  `max_advertisers` int(10) unsigned NOT NULL DEFAULT 10,
  `max_users` int(10) unsigned NOT NULL DEFAULT 5,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `performance_scores`;
CREATE TABLE `performance_scores` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `creative_id` varchar(128) NOT NULL,
  `longevity_score` decimal(5,2) DEFAULT 0.00,
  `geo_expansion_score` decimal(5,2) DEFAULT 0.00,
  `duplication_score` decimal(5,2) DEFAULT 0.00,
  `overall_score` decimal(5,2) DEFAULT 0.00,
  `performance_label` enum('winner','strong','average','weak','testing') DEFAULT NULL,
  `calculated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_creative` (`creative_id`),
  KEY `idx_label` (`performance_label`),
  KEY `idx_score` (`overall_score`),
  KEY `idx_creative_score` (`creative_id`,`overall_score`),
  CONSTRAINT `performance_scores_ibfk_1` FOREIGN KEY (`creative_id`) REFERENCES `ads` (`creative_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `proxy_pool`;
CREATE TABLE `proxy_pool` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `proxy_url` varchar(512) NOT NULL,
  `proxy_type` enum('http','https','socks5') DEFAULT 'http',
  `country` varchar(10) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `success_count` int(11) DEFAULT 0,
  `fail_count` int(11) DEFAULT 0,
  `avg_response_ms` int(11) DEFAULT NULL,
  `last_used_at` datetime DEFAULT NULL,
  `last_failed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_active` (`is_active`),
  KEY `idx_type` (`proxy_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `raw_payloads`;
CREATE TABLE `raw_payloads` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `advertiser_id` varchar(64) NOT NULL,
  `organization_id` int(10) unsigned DEFAULT NULL,
  `raw_json` longtext NOT NULL,
  `processed_flag` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_advertiser` (`advertiser_id`),
  KEY `idx_processed` (`processed_flag`),
  KEY `idx_payloads_org` (`organization_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `request_nonces`;
CREATE TABLE `request_nonces` (
  `nonce_hash` varchar(64) NOT NULL,
  `used_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`nonce_hash`),
  KEY `idx_nonce_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `roi_signal_estimates`;
CREATE TABLE `roi_signal_estimates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `creative_id` varchar(128) NOT NULL,
  `advertiser_id` varchar(64) DEFAULT NULL,
  `roi_signal` int(11) NOT NULL DEFAULT 0,
  `verdict` varchar(30) DEFAULT NULL,
  `confidence` varchar(10) DEFAULT NULL,
  `longevity_score` int(11) DEFAULT 0,
  `investment_score` int(11) DEFAULT 0,
  `refresh_score` int(11) DEFAULT 0,
  `competitive_score` int(11) DEFAULT 0,
  `estimated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_creative` (`creative_id`),
  KEY `idx_advertiser` (`advertiser_id`),
  KEY `idx_signal` (`roi_signal`),
  KEY `idx_verdict` (`verdict`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `saved_filters`;
CREATE TABLE `saved_filters` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `session_id` varchar(128) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `page` varchar(50) NOT NULL COMMENT 'Dashboard page: explorer, timeline, geo, etc.',
  `filter_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`filter_data`)),
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_filter_user` (`user_id`),
  KEY `idx_filter_session` (`session_id`),
  KEY `idx_filter_page` (`page`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `scheduled_reports`;
CREATE TABLE `scheduled_reports` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `report_type` enum('overview','advertiser','comparison','watchlist','custom') NOT NULL,
  `format` enum('csv','pdf','json') DEFAULT 'csv',
  `filters` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`filters`)),
  `schedule_cron` varchar(100) NOT NULL,
  `delivery_channel` varchar(20) DEFAULT 'email',
  `delivery_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`delivery_config`)),
  `last_run_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `scrape_logs`;
CREATE TABLE `scrape_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `advertiser_id` varchar(64) NOT NULL,
  `ads_found` int(11) DEFAULT 0,
  `new_ads` int(11) DEFAULT 0,
  `updated_ads` int(11) DEFAULT 0,
  `removed_ads` int(11) DEFAULT 0,
  `status` enum('success','partial','failed') DEFAULT 'success',
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_advertiser` (`advertiser_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `share_of_voice`;
CREATE TABLE `share_of_voice` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `advertiser_id` varchar(64) NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `ad_count` int(10) unsigned NOT NULL DEFAULT 0,
  `total_market_ads` int(10) unsigned NOT NULL DEFAULT 0,
  `share_pct` decimal(8,4) NOT NULL DEFAULT 0.0000,
  `active_ads` int(10) unsigned NOT NULL DEFAULT 0,
  `new_ads` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_sov_adv_period` (`advertiser_id`,`period_start`),
  KEY `idx_sov_period` (`period_start`),
  KEY `idx_sov_share` (`share_pct`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `sse_connections`;
CREATE TABLE `sse_connections` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `session_id` varchar(64) NOT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `connected_at` datetime NOT NULL,
  `last_heartbeat` datetime NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_session` (`session_id`),
  KEY `idx_heartbeat` (`last_heartbeat`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `tags`;
CREATE TABLE `tags` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `tag_type` enum('manual','auto') DEFAULT 'manual',
  `color` varchar(7) DEFAULT '#6c757d',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `trend_snapshots`;
CREATE TABLE `trend_snapshots` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `advertiser_id` varchar(64) NOT NULL,
  `snapshot_date` date NOT NULL,
  `ads_launched` int(11) DEFAULT 0,
  `ads_stopped` int(11) DEFAULT 0,
  `active_ads` int(11) DEFAULT 0,
  `new_countries` int(11) DEFAULT 0,
  `velocity_score` decimal(8,2) DEFAULT 0.00,
  `is_burst` tinyint(1) DEFAULT 0,
  `burst_magnitude` decimal(8,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_adv_date` (`advertiser_id`,`snapshot_date`),
  KEY `idx_burst` (`is_burst`),
  KEY `idx_advertiser_date` (`advertiser_id`,`snapshot_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `role` enum('admin','analyst','viewer') DEFAULT 'analyst',
  `organization_id` int(10) unsigned DEFAULT NULL,
  `team` varchar(100) DEFAULT NULL,
  `api_key` varchar(64) DEFAULT NULL,
  `api_key_previous` varchar(128) DEFAULT NULL,
  `api_key_expires_at` timestamp NULL DEFAULT NULL,
  `api_key_rotated_at` timestamp NULL DEFAULT NULL,
  `api_rate_limit` int(11) DEFAULT 1000,
  `last_login_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `api_scopes` varchar(500) NOT NULL DEFAULT 'read' COMMENT 'Comma-separated: read,write,admin,export,alerts',
  `failed_login_count` int(10) unsigned NOT NULL DEFAULT 0,
  `locked_until` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_email` (`email`),
  UNIQUE KEY `idx_api_key` (`api_key`),
  KEY `idx_users_api_key_previous` (`api_key_previous`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `user_saved_dashboards`;
CREATE TABLE `user_saved_dashboards` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`config`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `user_saved_dashboards_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `watchlists`;
CREATE TABLE `watchlists` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `organization_id` int(10) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `group_label` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_group` (`group_label`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `watchlist_advertisers`;
CREATE TABLE `watchlist_advertisers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `watchlist_id` bigint(20) unsigned NOT NULL,
  `advertiser_id` varchar(64) NOT NULL,
  `advertiser_name` varchar(255) DEFAULT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_watchlist_adv` (`watchlist_id`,`advertiser_id`),
  KEY `idx_advertiser` (`advertiser_id`),
  CONSTRAINT `watchlist_advertisers_ibfk_1` FOREIGN KEY (`watchlist_id`) REFERENCES `watchlists` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `webhooks`;
CREATE TABLE `webhooks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL DEFAULT 0,
  `name` varchar(100) NOT NULL DEFAULT '',
  `url` text NOT NULL,
  `secret` varchar(128) NOT NULL,
  `events` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`events`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `consecutive_failures` int(10) unsigned NOT NULL DEFAULT 0,
  `last_delivery_at` datetime DEFAULT NULL,
  `last_failure_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `webhook_deliveries`;
CREATE TABLE `webhook_deliveries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `webhook_id` bigint(20) unsigned NOT NULL,
  `event` varchar(100) NOT NULL,
  `payload` text DEFAULT NULL,
  `response_code` int(11) NOT NULL DEFAULT 0,
  `response_body` text DEFAULT NULL,
  `is_success` tinyint(1) NOT NULL DEFAULT 0,
  `delivered_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_webhook` (`webhook_id`),
  KEY `idx_event` (`event`),
  KEY `idx_delivered` (`delivered_at`),
  CONSTRAINT `fk_delivery_webhook` FOREIGN KEY (`webhook_id`) REFERENCES `webhooks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 2026-03-30 07:57:47
