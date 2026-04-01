-- App Groups: Custom categorization for apps
-- Run: mysql -u root -p your_database < database/migrate_app_groups.sql

CREATE TABLE IF NOT EXISTS app_groups (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(128) NOT NULL,
    description TEXT,
    color VARCHAR(7) DEFAULT '#6c757d' COMMENT 'Hex color for UI badge',
    icon VARCHAR(64) DEFAULT 'bi-collection' COMMENT 'Bootstrap icon class',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS app_group_keywords (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_id BIGINT UNSIGNED NOT NULL,
    keyword VARCHAR(512) NOT NULL COMMENT 'Matched against app name, category, description',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_group (group_id),
    INDEX idx_keyword (keyword),
    UNIQUE KEY uk_group_keyword (group_id, keyword),
    FOREIGN KEY (group_id) REFERENCES app_groups(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS app_group_members (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    matched_keyword VARCHAR(512) COMMENT 'Which keyword triggered the match',
    auto_assigned TINYINT(1) DEFAULT 1 COMMENT '1=auto, 0=manual',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_group_product (group_id, product_id),
    INDEX idx_group (group_id),
    INDEX idx_product (product_id),
    FOREIGN KEY (group_id) REFERENCES app_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES ad_products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
