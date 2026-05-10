<?php
defined('ABSPATH') || exit;

class LakiHub_DB {

    public static function install() {
        global $wpdb;
        $c = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // CRM — kontakter (selskaper og personer)
        dbDelta("CREATE TABLE {$wpdb->prefix}laki_contacts (
            id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            type        ENUM('company','person') NOT NULL DEFAULT 'company',
            name        VARCHAR(255) NOT NULL,
            org_nr      VARCHAR(20)  DEFAULT NULL,
            email       VARCHAR(255) DEFAULT NULL,
            phone       VARCHAR(50)  DEFAULT NULL,
            address     VARCHAR(500) DEFAULT NULL,
            category    VARCHAR(100) DEFAULT NULL,
            status      ENUM('lead','active','inactive') NOT NULL DEFAULT 'active',
            brreg_data  LONGTEXT     DEFAULT NULL,
            notes       LONGTEXT     DEFAULT NULL,
            created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) $c;");

        // Prosjekter
        dbDelta("CREATE TABLE {$wpdb->prefix}laki_projects (
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

        // Timeføring
        dbDelta("CREATE TABLE {$wpdb->prefix}laki_time_entries (
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

        // Inntekter / fakturaer
        dbDelta("CREATE TABLE {$wpdb->prefix}laki_revenue (
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

        update_option('laki_hub_db_version', LAKI_HUB_VERSION);
    }

    /**
     * Run lightweight ALTER TABLE migrations for upgrades that don't involve
     * creating new tables (dbDelta handles new tables; ALTER is needed for
     * adding columns to existing tables).
     *
     * Safe to call on every plugins_loaded — each check is a cheap SHOW COLUMNS.
     */
    public static function maybe_migrate(): void {
        global $wpdb;
        $t = $wpdb->prefix . 'laki_contacts';

        // v1.1 — add company_id so persons can be linked to a company contact.
        $cols = array_column(
            $wpdb->get_results("SHOW COLUMNS FROM `$t`", ARRAY_A) ?: [],
            'Field'
        );
        if (!in_array('company_id', $cols, true)) {
            $wpdb->query(
                "ALTER TABLE `$t`
                 ADD COLUMN `company_id` BIGINT UNSIGNED DEFAULT NULL AFTER `type`,
                 ADD INDEX  `idx_company_id` (`company_id`)"
            );
        }
    }
}
