-- Ad Intelligence Dashboard - Database Schema
-- Run this against your MySQL database to create all required tables.

CREATE DATABASE IF NOT EXISTS ad_intelligence
    DEFAULT CHARACTER SET utf8mb4
    DEFAULT COLLATE utf8mb4_unicode_ci;

USE ad_intelligence;

-- Raw API response storage (before processing)
CREATE TABLE IF NOT EXISTS raw_payloads (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    advertiser_id VARCHAR(64) NOT NULL,
    raw_json LONGTEXT NOT NULL,
    processed_flag TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_advertiser (advertiser_id),
    INDEX idx_processed (processed_flag)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Core ads table
CREATE TABLE IF NOT EXISTS ads (
    creative_id VARCHAR(128) PRIMARY KEY,
    advertiser_id VARCHAR(64) NOT NULL,
    ad_type ENUM('text','image','video') DEFAULT 'text',
    first_seen DATETIME NOT NULL,
    last_seen DATETIME NOT NULL,
    status ENUM('active','inactive') DEFAULT 'active',
    view_count BIGINT UNSIGNED DEFAULT 0,
    hash_signature VARCHAR(64) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_advertiser (advertiser_id),
    INDEX idx_status (status),
    INDEX idx_last_seen (last_seen),
    INDEX idx_first_seen (first_seen),
    INDEX idx_view_count (view_count)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ad content versioning (new row per change)
CREATE TABLE IF NOT EXISTS ad_details (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    creative_id VARCHAR(128) NOT NULL,
    headline TEXT,
    description TEXT,
    cta VARCHAR(255),
    landing_url TEXT,
    display_url VARCHAR(512),
    ad_width INT UNSIGNED,
    ad_height INT UNSIGNED,
    headlines_json TEXT COMMENT 'JSON array of all headline variations (responsive ads)',
    descriptions_json TEXT COMMENT 'JSON array of all description variations',
    tracking_ids_json TEXT COMMENT 'JSON array of tracking IDs (GA, GTM, FB Pixel)',
    snapshot_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_creative (creative_id),
    INDEX idx_snapshot (snapshot_date),
    FOREIGN KEY (creative_id) REFERENCES ads(creative_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ad media assets (images, videos)
CREATE TABLE IF NOT EXISTS ad_assets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    creative_id VARCHAR(128) NOT NULL,
    type ENUM('image','video','text') NOT NULL,
    original_url TEXT,
    local_path VARCHAR(512),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_creative (creative_id),
    FOREIGN KEY (creative_id) REFERENCES ads(creative_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Geographic and platform targeting
CREATE TABLE IF NOT EXISTS ad_targeting (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    creative_id VARCHAR(128) NOT NULL,
    country VARCHAR(10) NOT NULL,
    platform VARCHAR(50),
    detected_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_creative (creative_id),
    INDEX idx_country (country),
    INDEX idx_platform (platform),
    FOREIGN KEY (creative_id) REFERENCES ads(creative_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Detected products/apps per advertiser
CREATE TABLE IF NOT EXISTS ad_products (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    advertiser_id VARCHAR(64) NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    product_type ENUM('app','website','game','service','other') DEFAULT 'other',
    store_platform ENUM('ios','playstore','web') DEFAULT 'web',
    store_url TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_advertiser (advertiser_id),
    INDEX idx_name (product_name),
    UNIQUE KEY uk_adv_product (advertiser_id, product_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Map creatives to products
CREATE TABLE IF NOT EXISTS ad_product_map (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    creative_id VARCHAR(128) NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_creative_product (creative_id, product_id),
    INDEX idx_product (product_id),
    FOREIGN KEY (creative_id) REFERENCES ads(creative_id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES ad_products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Managed advertisers (tracked via UI/CLI)
CREATE TABLE IF NOT EXISTS managed_advertisers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    advertiser_id VARCHAR(64) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    status ENUM('new','active','fetching','paused','error','deleted') DEFAULT 'new',
    region VARCHAR(10) DEFAULT NULL COMMENT 'Default country/region code for this advertiser (IN, US, GB...)',
    total_ads INT DEFAULT 0,
    active_ads INT DEFAULT 0,
    last_fetch_ads INT DEFAULT 0,
    last_fetched_at DATETIME NULL,
    fetch_count INT DEFAULT 0,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- App metadata cache (from App Store / Play Store APIs)
CREATE TABLE IF NOT EXISTS app_metadata (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id BIGINT UNSIGNED NOT NULL,
    store_platform ENUM('ios','playstore') NOT NULL,
    store_url TEXT,
    bundle_id VARCHAR(255),
    app_name VARCHAR(255),
    icon_url TEXT,
    developer_name VARCHAR(255),
    developer_url TEXT,
    description TEXT,
    category VARCHAR(128),
    rating DECIMAL(3,2),
    rating_count INT UNSIGNED DEFAULT 0,
    price VARCHAR(32),
    release_date DATE,
    last_updated DATE,
    version VARCHAR(32),
    screenshots TEXT,
    downloads VARCHAR(64),
    fetched_at DATETIME NOT NULL,
    UNIQUE KEY uk_product (product_id),
    INDEX idx_store_platform (store_platform),
    FOREIGN KEY (product_id) REFERENCES ad_products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- YouTube video metadata cache
CREATE TABLE IF NOT EXISTS youtube_metadata (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    video_id VARCHAR(16) NOT NULL,
    title VARCHAR(512),
    description TEXT,
    channel_name VARCHAR(255),
    channel_id VARCHAR(64),
    channel_url TEXT,
    view_count BIGINT UNSIGNED DEFAULT 0,
    like_count BIGINT UNSIGNED DEFAULT 0,
    comment_count BIGINT UNSIGNED DEFAULT 0,
    publish_date DATETIME,
    duration VARCHAR(32),
    thumbnail_url TEXT,
    fetched_at DATETIME NOT NULL,
    UNIQUE KEY uk_video (video_id),
    INDEX idx_channel (channel_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Scrape activity logging
CREATE TABLE IF NOT EXISTS scrape_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    advertiser_id VARCHAR(64) NOT NULL,
    ads_found INT DEFAULT 0,
    new_ads INT DEFAULT 0,
    updated_ads INT DEFAULT 0,
    removed_ads INT DEFAULT 0,
    status ENUM('success','partial','failed') DEFAULT 'success',
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_advertiser (advertiser_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
