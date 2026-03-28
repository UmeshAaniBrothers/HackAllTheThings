-- Ad Intelligence Dashboard - Schema V2
-- Run AFTER schema.sql to add tables for all advanced features.

USE ad_intelligence;

-- =====================================================
-- 1. ALERTING & NOTIFICATION SYSTEM
-- =====================================================

CREATE TABLE IF NOT EXISTS alert_rules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    name VARCHAR(255) NOT NULL,
    rule_type ENUM('new_ad','ad_stopped','new_country','landing_change','burst_detected','custom') NOT NULL,
    advertiser_id VARCHAR(64) NULL,
    conditions JSON NULL,
    channels JSON NOT NULL,           -- e.g. ["email","telegram","slack"]
    is_active TINYINT(1) DEFAULT 1,
    last_triggered_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_type (rule_type),
    INDEX idx_active (is_active),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alert_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rule_id BIGINT UNSIGNED NULL,
    alert_type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    channel VARCHAR(20) NOT NULL,
    advertiser_id VARCHAR(64) NULL,
    metadata JSON NULL,
    delivery_status ENUM('sent','failed','pending') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_rule (rule_id),
    INDEX idx_type_date (alert_type, created_at),
    INDEX idx_advertiser (advertiser_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_channels (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    channel_type ENUM('email','telegram','slack') NOT NULL,
    config JSON NOT NULL,             -- email: {to}, telegram: {bot_token, chat_id}, slack: {webhook_url}
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_type (channel_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 2. COMPETITOR WATCHLISTS
-- =====================================================

CREATE TABLE IF NOT EXISTS watchlists (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    group_label VARCHAR(100) NULL,     -- e.g. "Finance Apps", "Gaming Apps"
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_group (group_label)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS watchlist_advertisers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    watchlist_id BIGINT UNSIGNED NOT NULL,
    advertiser_id VARCHAR(64) NOT NULL,
    advertiser_name VARCHAR(255) NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE INDEX idx_watchlist_adv (watchlist_id, advertiser_id),
    INDEX idx_advertiser (advertiser_id),
    FOREIGN KEY (watchlist_id) REFERENCES watchlists(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 3. TREND & PATTERN DETECTION
-- =====================================================

CREATE TABLE IF NOT EXISTS trend_snapshots (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    advertiser_id VARCHAR(64) NOT NULL,
    snapshot_date DATE NOT NULL,
    ads_launched INT DEFAULT 0,
    ads_stopped INT DEFAULT 0,
    active_ads INT DEFAULT 0,
    new_countries INT DEFAULT 0,
    velocity_score DECIMAL(8,2) DEFAULT 0,
    is_burst TINYINT(1) DEFAULT 0,
    burst_magnitude DECIMAL(8,2) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE INDEX idx_adv_date (advertiser_id, snapshot_date),
    INDEX idx_burst (is_burst)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS detected_patterns (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pattern_type ENUM('burst','seasonality','scaling','decline','revival_wave') NOT NULL,
    advertiser_id VARCHAR(64) NULL,
    description TEXT NOT NULL,
    confidence DECIMAL(5,2) DEFAULT 0,
    metadata JSON NULL,
    detected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_type (pattern_type),
    INDEX idx_advertiser (advertiser_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 4. CREATIVE FINGERPRINTING & A/B TESTING
-- =====================================================

CREATE TABLE IF NOT EXISTS creative_fingerprints (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    creative_id VARCHAR(128) NOT NULL,
    text_fingerprint VARCHAR(64) NOT NULL,     -- simhash of text content
    image_fingerprint VARCHAR(128) NULL,        -- perceptual hash of image
    cluster_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_creative (creative_id),
    INDEX idx_text_fp (text_fingerprint),
    INDEX idx_image_fp (image_fingerprint),
    INDEX idx_cluster (cluster_id),
    FOREIGN KEY (creative_id) REFERENCES ads(creative_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS creative_clusters (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    advertiser_id VARCHAR(64) NOT NULL,
    cluster_name VARCHAR(255) NULL,
    member_count INT DEFAULT 0,
    is_ab_test TINYINT(1) DEFAULT 0,
    primary_creative_id VARCHAR(128) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_advertiser (advertiser_id),
    INDEX idx_ab_test (is_ab_test)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 5. LANDING PAGE INTELLIGENCE
-- =====================================================

CREATE TABLE IF NOT EXISTS landing_pages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    url_hash VARCHAR(64) NOT NULL,
    url TEXT NOT NULL,
    domain VARCHAR(255) NOT NULL,
    funnel_type ENUM('app_install','lead_gen','ecommerce','content','saas','other') NULL,
    page_title TEXT NULL,
    meta_description TEXT NULL,
    app_name VARCHAR(255) NULL,
    app_category VARCHAR(100) NULL,
    pricing_detected TEXT NULL,
    has_form TINYINT(1) DEFAULT 0,
    has_pricing TINYINT(1) DEFAULT 0,
    has_app_download TINYINT(1) DEFAULT 0,
    technologies JSON NULL,
    last_scraped_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE INDEX idx_url_hash (url_hash),
    INDEX idx_domain (domain),
    INDEX idx_funnel (funnel_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS landing_page_changes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    landing_page_id BIGINT UNSIGNED NOT NULL,
    field_changed VARCHAR(50) NOT NULL,
    old_value TEXT NULL,
    new_value TEXT NULL,
    detected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_landing (landing_page_id),
    FOREIGN KEY (landing_page_id) REFERENCES landing_pages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 6. ADVERTISER PROFILING (DNA)
-- =====================================================

CREATE TABLE IF NOT EXISTS advertiser_profiles (
    advertiser_id VARCHAR(64) PRIMARY KEY,
    display_name VARCHAR(255) NULL,
    total_lifetime_ads INT DEFAULT 0,
    active_duration_days INT DEFAULT 0,
    dominant_ad_type ENUM('text','image','video') NULL,
    dominant_cta_style VARCHAR(100) NULL,
    primary_countries JSON NULL,
    primary_platforms JSON NULL,
    avg_campaign_duration_days DECIMAL(8,2) NULL,
    ad_frequency_per_week DECIMAL(8,2) NULL,
    intelligence_score DECIMAL(5,2) DEFAULT 0,
    profile_updated_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_score (intelligence_score)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 7. AI INTELLIGENCE LAYER
-- =====================================================

CREATE TABLE IF NOT EXISTS ai_ad_analysis (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    creative_id VARCHAR(128) NOT NULL,
    hooks_detected JSON NULL,              -- ["limited offer","free trial","urgency"]
    sentiment ENUM('aggressive','moderate','soft','neutral') NULL,
    sentiment_score DECIMAL(5,2) NULL,
    copy_cluster_id INT NULL,
    keywords JSON NULL,
    persuasion_techniques JSON NULL,
    analyzed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_creative (creative_id),
    INDEX idx_sentiment (sentiment),
    FOREIGN KEY (creative_id) REFERENCES ads(creative_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 8. PERFORMANCE ESTIMATION
-- =====================================================

CREATE TABLE IF NOT EXISTS performance_scores (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    creative_id VARCHAR(128) NOT NULL,
    longevity_score DECIMAL(5,2) DEFAULT 0,
    geo_expansion_score DECIMAL(5,2) DEFAULT 0,
    duplication_score DECIMAL(5,2) DEFAULT 0,
    overall_score DECIMAL(5,2) DEFAULT 0,
    performance_label ENUM('winner','strong','average','weak','testing') NULL,
    calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_creative (creative_id),
    INDEX idx_label (performance_label),
    INDEX idx_score (overall_score),
    FOREIGN KEY (creative_id) REFERENCES ads(creative_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 9. TAGGING SYSTEM
-- =====================================================

CREATE TABLE IF NOT EXISTS tags (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    tag_type ENUM('manual','auto') DEFAULT 'manual',
    color VARCHAR(7) DEFAULT '#6c757d',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ad_tags (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    creative_id VARCHAR(128) NOT NULL,
    tag_id BIGINT UNSIGNED NOT NULL,
    tagged_by ENUM('user','system') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE INDEX idx_ad_tag (creative_id, tag_id),
    INDEX idx_tag (tag_id),
    FOREIGN KEY (creative_id) REFERENCES ads(creative_id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 10. MULTI-USER & API KEYS
-- =====================================================

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(255) NULL,
    role ENUM('admin','analyst','viewer') DEFAULT 'analyst',
    api_key VARCHAR(64) NULL,
    api_rate_limit INT DEFAULT 1000,       -- requests per hour
    last_login_at DATETIME NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE INDEX idx_email (email),
    UNIQUE INDEX idx_api_key (api_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS api_usage_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    api_key VARCHAR(64) NOT NULL,
    endpoint VARCHAR(255) NOT NULL,
    method VARCHAR(10) NOT NULL,
    response_code INT NOT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_api_key (api_key),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_saved_dashboards (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    config JSON NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 11. EXPORT & SCHEDULED REPORTS
-- =====================================================

CREATE TABLE IF NOT EXISTS scheduled_reports (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    name VARCHAR(255) NOT NULL,
    report_type ENUM('overview','advertiser','comparison','watchlist','custom') NOT NULL,
    format ENUM('csv','pdf','json') DEFAULT 'csv',
    filters JSON NULL,
    schedule_cron VARCHAR(100) NOT NULL,    -- e.g. "0 8 * * 1" for Monday 8am
    delivery_channel VARCHAR(20) DEFAULT 'email',
    delivery_config JSON NULL,
    last_run_at DATETIME NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 12. PROXY & ANTI-BLOCKING
-- =====================================================

CREATE TABLE IF NOT EXISTS proxy_pool (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    proxy_url VARCHAR(512) NOT NULL,
    proxy_type ENUM('http','https','socks5') DEFAULT 'http',
    country VARCHAR(10) NULL,
    is_active TINYINT(1) DEFAULT 1,
    success_count INT DEFAULT 0,
    fail_count INT DEFAULT 0,
    avg_response_ms INT NULL,
    last_used_at DATETIME NULL,
    last_failed_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_active (is_active),
    INDEX idx_type (proxy_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 13. DATA QUALITY
-- =====================================================

CREATE TABLE IF NOT EXISTS data_quality_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    check_type ENUM('missing_field','anomaly','duplicate','stale_data','format_error') NOT NULL,
    entity_type VARCHAR(50) NOT NULL,      -- 'ad', 'targeting', 'asset', etc.
    entity_id VARCHAR(128) NULL,
    severity ENUM('low','medium','high','critical') DEFAULT 'medium',
    description TEXT NOT NULL,
    resolved TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_type (check_type),
    INDEX idx_severity (severity),
    INDEX idx_resolved (resolved)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 14. ASSET DEDUPLICATION
-- =====================================================

ALTER TABLE ad_assets
    ADD COLUMN file_hash VARCHAR(64) NULL AFTER local_path,
    ADD INDEX idx_file_hash (file_hash);
