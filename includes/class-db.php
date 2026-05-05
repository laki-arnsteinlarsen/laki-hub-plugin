<?php
defined('ABSPATH') || exit;

class Edifice_DB {

    public static function install() {
        global $wpdb;
        $c = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta("CREATE TABLE {$wpdb->prefix}edifice_contacts (
            id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            type        VARCHAR(50)  NOT NULL DEFAULT 'company',
            name        VARCHAR(255) NOT NULL,
            org_nr      VARCHAR(20)  DEFAULT NULL,
            email       VARCHAR(255) DEFAULT NULL,
            phone       VARCHAR(50)  DEFAULT NULL,
            address     VARCHAR(500) DEFAULT NULL,
            category    TEXT         DEFAULT NULL,
            status      ENUM('lead','active','inactive') NOT NULL DEFAULT 'active',
            brreg_data  LONGTEXT     DEFAULT NULL,
            notes       LONGTEXT     DEFAULT NULL,
            created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) $c;");

        dbDelta("CREATE TABLE {$wpdb->prefix}edifice_projects (
            id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            contact_id  BIGINT UNSIGNED DEFAULT NULL,
            name        VARCHAR(255) NOT NULL,
            description LONGTEXT     DEFAULT NULL,
            status      ENUM('active','on-hold','completed','cancelled') NOT NULL DEFAULT 'active',
            start_date  DATE         DEFAULT NULL,
            end_date    DATE         DEFAULT NULL,
            budget      DECIMAL(12,2) DEFAULT NULL,
            created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) $c;");

        dbDelta("CREATE TABLE {$wpdb->prefix}edifice_time_entries (
            id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            project_id  BIGINT UNSIGNED DEFAULT NULL,
            contact_id  BIGINT UNSIGNED DEFAULT NULL,
            date        DATE         NOT NULL,
            hours       DECIMAL(5,2) NOT NULL DEFAULT 0,
            description VARCHAR(500) DEFAULT NULL,
            billable    TINYINT(1)   NOT NULL DEFAULT 1,
            hourly_rate DECIMAL(10,2) DEFAULT NULL,
            created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) $c;");

        dbDelta("CREATE TABLE {$wpdb->prefix}edifice_revenue (
            id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            contact_id  BIGINT UNSIGNED DEFAULT NULL,
            project_id  BIGINT UNSIGNED DEFAULT NULL,
            type        ENUM('invoice','payment','recurring') NOT NULL DEFAULT 'invoice',
            description VARCHAR(500) DEFAULT NULL,
            amount      DECIMAL(12,2) NOT NULL DEFAULT 0,
            currency    VARCHAR(3)   NOT NULL DEFAULT 'NOK',
            date        DATE         NOT NULL,
            due_date    DATE         DEFAULT NULL,
            status      ENUM('draft','sent','paid','overdue') NOT NULL DEFAULT 'draft',
            invoice_nr  VARCHAR(50)  DEFAULT NULL,
            created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) $c;");

        // ── Digital products / passive income ─────────────────────────────────

        dbDelta("CREATE TABLE {$wpdb->prefix}edifice_products (
            id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name        VARCHAR(255) NOT NULL,
            type        VARCHAR(50)  NOT NULL DEFAULT 'ebook',
            brand       VARCHAR(100) NOT NULL DEFAULT 'LAKI',
            status      VARCHAR(20)  NOT NULL DEFAULT 'active',
            description LONGTEXT     DEFAULT NULL,
            created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) $c;");

        dbDelta("CREATE TABLE {$wpdb->prefix}edifice_product_listings (
            id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            product_id     BIGINT UNSIGNED NOT NULL,
            platform       VARCHAR(50)  NOT NULL DEFAULT 'Gumroad',
            listing_url    VARCHAR(500) DEFAULT NULL,
            price          DECIMAL(10,2) NOT NULL DEFAULT 0,
            currency       VARCHAR(3)   NOT NULL DEFAULT 'USD',
            listing_status VARCHAR(30)  NOT NULL DEFAULT 'live',
            notes          LONGTEXT     DEFAULT NULL,
            created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) $c;");

        dbDelta("CREATE TABLE {$wpdb->prefix}edifice_product_revenue (
            id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            listing_id    BIGINT UNSIGNED NOT NULL,
            snapshot_date DATE         NOT NULL,
            revenue       DECIMAL(12,2) NOT NULL DEFAULT 0,
            sales_count   INT UNSIGNED  NOT NULL DEFAULT 0,
            currency      VARCHAR(3)   NOT NULL DEFAULT 'USD',
            notes         LONGTEXT     DEFAULT NULL,
            synced_at     DATETIME     DEFAULT NULL,
            created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_listing_date (listing_id, snapshot_date)
        ) $c;");

        update_option('edifice_db_version', EDIFICE_VERSION);
    }

    public static function maybe_migrate() {
        global $wpdb;

        // ── Migration 0: Rename WP options laki_hub_* → edifice_* ───────────────
        $option_map = [
            'laki_hub_page_id'    => 'edifice_page_id',
            'laki_hub_db_version' => 'edifice_db_version',
        ];
        foreach ($option_map as $old_key => $new_key) {
            $val = get_option($old_key);
            if ($val !== false && get_option($new_key) === false) {
                update_option($new_key, $val);
                delete_option($old_key);
            }
        }

        // ── Migration 1: Rename laki_ tables → edifice_ ─────────────────────────
        $table_map = [
            'laki_contacts'     => 'edifice_contacts',
            'laki_projects'     => 'edifice_projects',
            'laki_time_entries' => 'edifice_time_entries',
            'laki_revenue'      => 'edifice_revenue',
        ];
        foreach ($table_map as $old_suffix => $new_suffix) {
            $old = $wpdb->prefix . $old_suffix;
            $new = $wpdb->prefix . $new_suffix;
            $old_exists = $wpdb->get_var("SHOW TABLES LIKE '$old'") === $old;
            $new_exists = $wpdb->get_var("SHOW TABLES LIKE '$new'") === $new;

            if ($old_exists && ! $new_exists) {
                $wpdb->query("RENAME TABLE `$old` TO `$new`");
            } elseif ($old_exists && $new_exists) {
                $old_rows = (int) $wpdb->get_var("SELECT COUNT(*) FROM `$old`");
                $new_rows = (int) $wpdb->get_var("SELECT COUNT(*) FROM `$new`");
                if ($old_rows > 0 && $new_rows === 0) {
                    $wpdb->query("INSERT INTO `$new` SELECT * FROM `$old`");
                }
                if ($old_rows === 0 || $new_rows === 0) {
                    $wpdb->query("DROP TABLE IF EXISTS `$old`");
                }
            }
        }

        $table = $wpdb->prefix . 'edifice_contacts';

        $cols = $wpdb->get_results($wpdb->prepare(
            "SELECT COLUMN_NAME, COLUMN_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s",
            DB_NAME, $table
        ), OBJECT_K);

        if (empty($cols)) return;

        // ── Migration 2: type ENUM → VARCHAR(50) ────────────────────────────────
        if (isset($cols['type']) && stripos($cols['type']->COLUMN_TYPE, 'enum') !== false) {
            $wpdb->query("ALTER TABLE `$table` MODIFY `type` VARCHAR(50) NOT NULL DEFAULT 'company'");
        }

        // ── Migration 3: category VARCHAR → TEXT (JSON array) ───────────────────
        if (isset($cols['category']) && stripos($cols['category']->COLUMN_TYPE, 'varchar') !== false) {
            $wpdb->query("ALTER TABLE `$table` MODIFY `category` TEXT DEFAULT NULL");
        }
    }
}
