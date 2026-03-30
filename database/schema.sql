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
