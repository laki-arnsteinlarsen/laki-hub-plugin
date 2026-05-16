<?php
defined('ABSPATH') || exit;

class Edifice_DB {

    public static function install() {
        global $wpdb;
        $c = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta("CREATE TABLE {$wpdb->prefix}edifice_contacts (
            id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            type            VARCHAR(50)  NOT NULL DEFAULT 'company',
            company_id      BIGINT UNSIGNED DEFAULT NULL,
            name            VARCHAR(255) NOT NULL,
            org_nr          VARCHAR(20)  DEFAULT NULL,
            email           VARCHAR(255) DEFAULT NULL,
            phone           VARCHAR(50)  DEFAULT NULL,
            mobile          VARCHAR(50)  DEFAULT NULL,
            address         VARCHAR(500) DEFAULT NULL,
            postal_address  VARCHAR(500) DEFAULT NULL,
            category        TEXT         DEFAULT NULL,
            status          ENUM('lead','active','inactive') NOT NULL DEFAULT 'active',
            tier            TINYINT UNSIGNED DEFAULT NULL,
            tier_frequency  VARCHAR(20)  DEFAULT NULL,
            tier_last_contact DATE       DEFAULT NULL,
            tier_next_action DATE        DEFAULT NULL,
            tier_next_action_note VARCHAR(500) DEFAULT NULL,
            tier_relation_note TEXT      DEFAULT NULL,
            linkedin_url    VARCHAR(500) DEFAULT NULL,
            instagram_url   VARCHAR(500) DEFAULT NULL,
            facebook_url    VARCHAR(500) DEFAULT NULL,
            x_url           VARCHAR(500) DEFAULT NULL,
            tiktok_url      VARCHAR(500) DEFAULT NULL,
            custom_url      VARCHAR(500) DEFAULT NULL,
            brreg_data      LONGTEXT     DEFAULT NULL,
            notes           LONGTEXT     DEFAULT NULL,
            created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) $c;");

        dbDelta("CREATE TABLE {$wpdb->prefix}edifice_contact_emails (
            id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            contact_id  BIGINT UNSIGNED NOT NULL,
            email       VARCHAR(255) NOT NULL,
            label       VARCHAR(50)  DEFAULT NULL,
            sort_order  INT UNSIGNED NOT NULL DEFAULT 0,
            created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_contact_id (contact_id)
        ) $c;");

        dbDelta("CREATE TABLE {$wpdb->prefix}edifice_contact_companies (
            id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            person_id   BIGINT UNSIGNED NOT NULL,
            company_id  BIGINT UNSIGNED NOT NULL,
            role        VARCHAR(100) DEFAULT NULL,
            is_primary  TINYINT(1)   NOT NULL DEFAULT 0,
            sort_order  INT UNSIGNED NOT NULL DEFAULT 0,
            created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_person_id  (person_id),
            INDEX idx_company_id (company_id),
            UNIQUE KEY unique_person_company (person_id, company_id)
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
            id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            contact_id    BIGINT UNSIGNED DEFAULT NULL,
            project_id    BIGINT UNSIGNED DEFAULT NULL,
            type          ENUM('invoice','payment','recurring') NOT NULL DEFAULT 'invoice',
            description   VARCHAR(500) DEFAULT NULL,
            amount        DECIMAL(12,2) NOT NULL DEFAULT 0,
            amount_ex_vat DECIMAL(12,2) DEFAULT NULL,
            vat_amount    DECIMAL(12,2) DEFAULT NULL,
            currency      VARCHAR(3)   NOT NULL DEFAULT 'NOK',
            date          DATE         NOT NULL,
            due_date      DATE         DEFAULT NULL,
            status        ENUM('draft','sent','paid','overdue') NOT NULL DEFAULT 'draft',
            invoice_nr    VARCHAR(50)  DEFAULT NULL,
            external_id   VARCHAR(100) DEFAULT NULL,
            unimicro_raw  LONGTEXT     DEFAULT NULL,
            created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_external_id (external_id)
        ) $c;");

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

        dbDelta("CREATE TABLE {$wpdb->prefix}edifice_contact_interactions (
            id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            contact_id  BIGINT UNSIGNED NOT NULL,
            project_id  BIGINT UNSIGNED DEFAULT NULL,
            dato        DATE         NOT NULL,
            tid         TIME         DEFAULT NULL,
            kanal       VARCHAR(20)  NOT NULL,
            retning     VARCHAR(10)  NOT NULL DEFAULT 'toveis',
            sammendrag  VARCHAR(500) NOT NULL,
            notat       TEXT         DEFAULT NULL,
            kilde       VARCHAR(20)  NOT NULL DEFAULT 'manuell',
            ekstern_ref VARCHAR(255) DEFAULT NULL,
            created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_contact_dato (contact_id, dato),
            INDEX idx_ekstern_ref (ekstern_ref)
        ) $c;");

        dbDelta("CREATE TABLE {$wpdb->prefix}edifice_prospects (
            id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            org_nr            VARCHAR(20)  NOT NULL,
            name              VARCHAR(255) NOT NULL,
            nace_code         VARCHAR(10)  DEFAULT NULL,
            nace_description  VARCHAR(255) DEFAULT NULL,
            employees         INT UNSIGNED DEFAULT NULL,
            kommune_nr        VARCHAR(10)  DEFAULT NULL,
            kommune_navn      VARCHAR(100) DEFAULT NULL,
            registration_date DATE         DEFAULT NULL,
            website           VARCHAR(500) DEFAULT NULL,
            email             VARCHAR(255) DEFAULT NULL,
            phone             VARCHAR(50)  DEFAULT NULL,
            address           VARCHAR(500) DEFAULT NULL,
            postal_address    VARCHAR(500) DEFAULT NULL,
            has_wordpress     TINYINT(1)   DEFAULT NULL,
            wp_version        VARCHAR(50)  DEFAULT NULL,
            server_header     VARCHAR(255) DEFAULT NULL,
            revenue_latest    DECIMAL(15,2) DEFAULT NULL,
            revenue_year      INT UNSIGNED  DEFAULT NULL,
            hosting_score     INT UNSIGNED  NOT NULL DEFAULT 0,
            advisory_score    INT UNSIGNED  NOT NULL DEFAULT 0,
            status            VARCHAR(30)  NOT NULL DEFAULT 'new',
            skip_reason       VARCHAR(255) DEFAULT NULL,
            crm_contact_id    BIGINT UNSIGNED DEFAULT NULL,
            brreg_data        LONGTEXT     DEFAULT NULL,
            last_synced_at    DATETIME     DEFAULT NULL,
            last_scraped_at   DATETIME     DEFAULT NULL,
            created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_org_nr (org_nr),
            INDEX idx_status (status),
            INDEX idx_hosting_score (hosting_score),
            INDEX idx_kommune (kommune_nr),
            INDEX idx_nace (nace_code)
        ) $c;");

        dbDelta("CREATE TABLE {$wpdb->prefix}edifice_sites (
            id                     BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name                   VARCHAR(255) NOT NULL,
            url                    VARCHAR(500) NOT NULL,
            domain                 VARCHAR(255) DEFAULT NULL,
            coolify_service_uuid   VARCHAR(255) DEFAULT NULL,
            coolify_container      VARCHAR(255) DEFAULT NULL,
            customer_name          VARCHAR(255) DEFAULT NULL,
            monthly_cost_nok       DECIMAL(10,2) NOT NULL DEFAULT 0,
            kuma_monitor_id        INT UNSIGNED DEFAULT NULL,
            uptimerobot_monitor_id VARCHAR(50)  DEFAULT NULL,
            notes                  TEXT         DEFAULT NULL,
            active                 TINYINT(1)   NOT NULL DEFAULT 1,
            created_at             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_active (active)
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

        $contact_table = $wpdb->prefix . 'edifice_contacts';
        $cols = $wpdb->get_results($wpdb->prepare(
            "SELECT COLUMN_NAME, COLUMN_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s",
            DB_NAME, $contact_table
        ), OBJECT_K);

        if (! empty($cols)) {
            // ── Migration 2: type ENUM → VARCHAR(50) ────────────────────────────
            if (isset($cols['type']) && stripos($cols['type']->COLUMN_TYPE, 'enum') !== false) {
                $wpdb->query("ALTER TABLE `$contact_table` MODIFY `type` VARCHAR(50) NOT NULL DEFAULT 'company'");
            }

            // ── Migration 3: category VARCHAR → TEXT ────────────────────────────
            if (isset($cols['category']) && stripos($cols['category']->COLUMN_TYPE, 'varchar') !== false) {
                $wpdb->query("ALTER TABLE `$contact_table` MODIFY `category` TEXT DEFAULT NULL");
            }

            // ── Migration 5: add company_id for person→company linkage ──────────
            if (! isset($cols['company_id'])) {
                $wpdb->query(
                    "ALTER TABLE `$contact_table`
                     ADD COLUMN `company_id` BIGINT UNSIGNED DEFAULT NULL AFTER `type`,
                     ADD INDEX  `idx_company_id` (`company_id`)"
                );
            }

            // ── Migration 6: social URL columns (LinkedIn/Instagram/etc.) ──────
            $social_cols = [
                'linkedin_url'  => 'AFTER `status`',
                'instagram_url' => 'AFTER `linkedin_url`',
                'facebook_url'  => 'AFTER `instagram_url`',
                'x_url'         => 'AFTER `facebook_url`',
                'tiktok_url'    => 'AFTER `x_url`',
                'custom_url'    => 'AFTER `tiktok_url`',
            ];
            foreach ($social_cols as $col => $position) {
                if (! isset($cols[$col])) {
                    $wpdb->query("ALTER TABLE `$contact_table` ADD COLUMN `$col` VARCHAR(500) DEFAULT NULL $position");
                }
            }

            // ── Migration 7: normalize phone numbers to include country code ───
            // Idempotent via flag-option. Run only once.
            if (! get_option('edifice_phone_normalized', false)) {
                // Step 1: 00-prefix → +-prefix (international format conversion)
                $wpdb->query(
                    "UPDATE `$contact_table`
                     SET phone = CONCAT('+', SUBSTRING(phone, 3))
                     WHERE phone LIKE '00%' AND phone NOT LIKE '+%'"
                );
                // Step 2: prepend +47 to remaining naked numbers (assume Norwegian)
                $wpdb->query(
                    "UPDATE `$contact_table`
                     SET phone = CONCAT('+47 ', phone)
                     WHERE phone IS NOT NULL AND phone <> '' AND phone NOT LIKE '+%'"
                );
                update_option('edifice_phone_normalized', true);
            }

            // ── Migration 8: compact phone storage — strip all whitespace ──────
            // Resultat: "+47 91 23 45 67" → "+4791234567" (pure E.164).
            // Visning skjer via Edifice_CRM::format_phone().
            if (! get_option('edifice_phone_compact', false)) {
                $rows = $wpdb->get_results("SELECT id, phone FROM `$contact_table` WHERE phone IS NOT NULL AND phone <> ''");
                foreach ($rows as $r) {
                    $clean = preg_replace('/\s+/', '', $r->phone);
                    if ($clean !== $r->phone) {
                        $wpdb->update($contact_table, ['phone' => $clean], ['id' => $r->id]);
                    }
                }
                update_option('edifice_phone_compact', true);
            }

            // ── Migration 9: postal_address kolonne (besøksadr. + postadr.) ────
            if (! isset($cols['postal_address'])) {
                $wpdb->query("ALTER TABLE `$contact_table`
                              ADD COLUMN `postal_address` VARCHAR(500) DEFAULT NULL AFTER `address`");
            }

            // ── Migration 12: mobile-kolonne (egen for mobil) ──────────────────
            if (! isset($cols['mobile'])) {
                $wpdb->query("ALTER TABLE `$contact_table`
                              ADD COLUMN `mobile` VARCHAR(50) DEFAULT NULL AFTER `phone`");
            }

            // ── Migration 14: nettverksoppfølging (tier-system) ───────────────
            // Tier 1 = månedlig pleie, Tier 2 = kvartalsvis, Tier 3 = halvårlig,
            // Tier 4 = passiv. NULL = ikke kategorisert som nettverkskontakt.
            $tier_cols = [
                'tier'                => "TINYINT UNSIGNED DEFAULT NULL AFTER `status`",
                'tier_frequency'      => "VARCHAR(20)  DEFAULT NULL AFTER `tier`",
                'tier_last_contact'   => "DATE         DEFAULT NULL AFTER `tier_frequency`",
                'tier_next_action'    => "DATE         DEFAULT NULL AFTER `tier_last_contact`",
                'tier_next_action_note' => "VARCHAR(500) DEFAULT NULL AFTER `tier_next_action`",
                'tier_relation_note'  => "TEXT         DEFAULT NULL AFTER `tier_next_action_note`",
            ];
            foreach ($tier_cols as $col => $definition) {
                if (! isset($cols[$col])) {
                    $wpdb->query("ALTER TABLE `$contact_table` ADD COLUMN `$col` $definition");
                }
            }
            // Add index on tier for fast filtering in Nettverk-fanen
            $idx = $wpdb->get_var(
                "SHOW INDEX FROM `$contact_table` WHERE Key_name = 'idx_tier'"
            );
            if (! $idx) {
                $wpdb->query("ALTER TABLE `$contact_table` ADD INDEX `idx_tier` (`tier`)");
            }
        }

        // ── Migration 10: opprett relaterte tabeller hvis de mangler ───────────
        $emails_table    = $wpdb->prefix . 'edifice_contact_emails';
        $companies_table = $wpdb->prefix . 'edifice_contact_companies';

        if ($wpdb->get_var("SHOW TABLES LIKE '$emails_table'") !== $emails_table) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            $c = $wpdb->get_charset_collate();
            dbDelta("CREATE TABLE $emails_table (
                id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                contact_id  BIGINT UNSIGNED NOT NULL,
                email       VARCHAR(255) NOT NULL,
                label       VARCHAR(50)  DEFAULT NULL,
                sort_order  INT UNSIGNED NOT NULL DEFAULT 0,
                created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_contact_id (contact_id)
            ) $c;");
        }

        if ($wpdb->get_var("SHOW TABLES LIKE '$companies_table'") !== $companies_table) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            $c = $wpdb->get_charset_collate();
            dbDelta("CREATE TABLE $companies_table (
                id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                person_id   BIGINT UNSIGNED NOT NULL,
                company_id  BIGINT UNSIGNED NOT NULL,
                role        VARCHAR(100) DEFAULT NULL,
                is_primary  TINYINT(1)   NOT NULL DEFAULT 0,
                sort_order  INT UNSIGNED NOT NULL DEFAULT 0,
                created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_person_id  (person_id),
                INDEX idx_company_id (company_id),
                UNIQUE KEY unique_person_company (person_id, company_id)
            ) $c;");
        }

        // ── Migration 11: kopier eksisterende company_id → junction-tabell ─────
        if (! get_option('edifice_company_links_migrated', false)) {
            $wpdb->query(
                "INSERT IGNORE INTO `$companies_table` (person_id, company_id, is_primary, sort_order)
                 SELECT id, company_id, 1, 0
                 FROM   `$contact_table`
                 WHERE  type = 'person' AND company_id IS NOT NULL"
            );
            update_option('edifice_company_links_migrated', true);
        }

        // ── Migration 4: Create product tables if missing (Produkter module) ────
        $products_table = $wpdb->prefix . 'edifice_products';
        if ($wpdb->get_var("SHOW TABLES LIKE '$products_table'") !== $products_table) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            $c = $wpdb->get_charset_collate();

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
        }

        // ── Migration 15: edifice_contact_interactions (strukturert logg) ──────
        // Erstatter fritekst-feltet tier_relation_note som primær logg.
        // tier_relation_note beholdes som "statisk bakgrunn" om kontakten.
        $interactions_table = $wpdb->prefix . 'edifice_contact_interactions';
        if ($wpdb->get_var("SHOW TABLES LIKE '$interactions_table'") !== $interactions_table) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            $c = $wpdb->get_charset_collate();
            dbDelta("CREATE TABLE $interactions_table (
                id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                contact_id  BIGINT UNSIGNED NOT NULL,
                project_id  BIGINT UNSIGNED DEFAULT NULL,
                dato        DATE         NOT NULL,
                tid         TIME         DEFAULT NULL,
                kanal       VARCHAR(20)  NOT NULL,
                retning     VARCHAR(10)  NOT NULL DEFAULT 'toveis',
                sammendrag  VARCHAR(500) NOT NULL,
                notat       TEXT         DEFAULT NULL,
                kilde       VARCHAR(20)  NOT NULL DEFAULT 'manuell',
                ekstern_ref VARCHAR(255) DEFAULT NULL,
                created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_contact_dato (contact_id, dato),
                INDEX idx_ekstern_ref (ekstern_ref)
            ) $c;");
        }

        // ── Migration 16: reduser tier-system fra 4 til 3 nivåer ───────────────
        // Eksisterende rader med tier=4 (passive) settes til NULL.
        // Idempotent via flag-option.
        if (! get_option('edifice_tier_4_purged', false)) {
            $wpdb->query("UPDATE `$contact_table` SET tier = NULL WHERE tier = 4");
            update_option('edifice_tier_4_purged', true);
        }

        // ── Migration 13: opprett edifice_prospects (prospekt-pipeline) ────────
        $prospects_table = $wpdb->prefix . 'edifice_prospects';
        if ($wpdb->get_var("SHOW TABLES LIKE '$prospects_table'") !== $prospects_table) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            $c = $wpdb->get_charset_collate();
            dbDelta("CREATE TABLE $prospects_table (
                id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                org_nr            VARCHAR(20)  NOT NULL,
                name              VARCHAR(255) NOT NULL,
                nace_code         VARCHAR(10)  DEFAULT NULL,
                nace_description  VARCHAR(255) DEFAULT NULL,
                employees         INT UNSIGNED DEFAULT NULL,
                kommune_nr        VARCHAR(10)  DEFAULT NULL,
                kommune_navn      VARCHAR(100) DEFAULT NULL,
                registration_date DATE         DEFAULT NULL,
                website           VARCHAR(500) DEFAULT NULL,
                email             VARCHAR(255) DEFAULT NULL,
                phone             VARCHAR(50)  DEFAULT NULL,
                address           VARCHAR(500) DEFAULT NULL,
                postal_address    VARCHAR(500) DEFAULT NULL,
                has_wordpress     TINYINT(1)   DEFAULT NULL,
                wp_version        VARCHAR(50)  DEFAULT NULL,
                server_header     VARCHAR(255) DEFAULT NULL,
                revenue_latest    DECIMAL(15,2) DEFAULT NULL,
                revenue_year      INT UNSIGNED  DEFAULT NULL,
                hosting_score     INT UNSIGNED  NOT NULL DEFAULT 0,
                advisory_score    INT UNSIGNED  NOT NULL DEFAULT 0,
                status            VARCHAR(30)  NOT NULL DEFAULT 'new',
                skip_reason       VARCHAR(255) DEFAULT NULL,
                crm_contact_id    BIGINT UNSIGNED DEFAULT NULL,
                brreg_data        LONGTEXT     DEFAULT NULL,
                last_synced_at    DATETIME     DEFAULT NULL,
                last_scraped_at   DATETIME     DEFAULT NULL,
                created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_org_nr (org_nr),
                INDEX idx_status (status),
                INDEX idx_hosting_score (hosting_score),
                INDEX idx_kommune (kommune_nr),
                INDEX idx_nace (nace_code)
            ) $c;");
        }

        // ── Migration 17: edifice_sites (hosting-modul, drift+kostnad) ─────────
        $sites_table = $wpdb->prefix . 'edifice_sites';
        if ($wpdb->get_var("SHOW TABLES LIKE '$sites_table'") !== $sites_table) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            $c = $wpdb->get_charset_collate();
            dbDelta("CREATE TABLE $sites_table (
                id                     BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name                   VARCHAR(255) NOT NULL,
                url                    VARCHAR(500) NOT NULL,
                domain                 VARCHAR(255) DEFAULT NULL,
                coolify_service_uuid   VARCHAR(255) DEFAULT NULL,
                coolify_container      VARCHAR(255) DEFAULT NULL,
                customer_name          VARCHAR(255) DEFAULT NULL,
                monthly_cost_nok       DECIMAL(10,2) NOT NULL DEFAULT 0,
                kuma_monitor_id        INT UNSIGNED DEFAULT NULL,
                uptimerobot_monitor_id VARCHAR(50)  DEFAULT NULL,
                notes                  TEXT         DEFAULT NULL,
                active                 TINYINT(1)   NOT NULL DEFAULT 1,
                created_at             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_active (active)
            ) $c;");
        }

        // ── Migration 18: UniMicro webhook-felter på edifice_revenue ───────────
        $revenue_table = $wpdb->prefix . 'edifice_revenue';
        $rev_cols = $wpdb->get_results($wpdb->prepare(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s",
            DB_NAME, $revenue_table
        ), OBJECT_K);
        if (! empty($rev_cols)) {
            if (! isset($rev_cols['external_id'])) {
                $wpdb->query("ALTER TABLE `$revenue_table`
                              ADD COLUMN `external_id` VARCHAR(100) DEFAULT NULL AFTER `invoice_nr`");
            }
            if (! isset($rev_cols['unimicro_raw'])) {
                $wpdb->query("ALTER TABLE `$revenue_table`
                              ADD COLUMN `unimicro_raw` LONGTEXT DEFAULT NULL AFTER `external_id`");
            }
            $idx = $wpdb->get_var("SHOW INDEX FROM `$revenue_table` WHERE Key_name = 'idx_external_id'");
            if (! $idx) {
                $wpdb->query("ALTER TABLE `$revenue_table` ADD INDEX `idx_external_id` (`external_id`)");
            }

            // ── Migration 19: amount_ex_vat + vat_amount på edifice_revenue ────
            // Tilrettelegger for visning av netto + MVA + brutto i Inntekt-modulen.
            // UniMicro-webhooken populerer disse automatisk; manuelle entries kan
            // sette dem via Edifice-skjema.
            if (! isset($rev_cols['amount_ex_vat'])) {
                $wpdb->query("ALTER TABLE `$revenue_table`
                              ADD COLUMN `amount_ex_vat` DECIMAL(12,2) DEFAULT NULL AFTER `amount`");
            }
            if (! isset($rev_cols['vat_amount'])) {
                $wpdb->query("ALTER TABLE `$revenue_table`
                              ADD COLUMN `vat_amount` DECIMAL(12,2) DEFAULT NULL AFTER `amount_ex_vat`");
            }
        }

        // Seed initielle siter ved første gangs aktivering. Idempotent via flagg.
        if (! get_option('edifice_sites_seeded', false)) {
            $existing = (int) $wpdb->get_var("SELECT COUNT(*) FROM `$sites_table`");
            if ($existing === 0) {
                $initial_sites = [
                    [
                        'name'              => 'arnsteinlarsen.no',
                        'url'               => 'https://arnsteinlarsen.no',
                        'domain'            => 'arnsteinlarsen.no',
                        'coolify_container' => 'wordpress-y1cxnuencffdyofuhup1swo7',
                        'customer_name'     => 'LAKI AS',
                        'monthly_cost_nok'  => 0,
                    ],
                    [
                        'name'              => 'borntoplay.no',
                        'url'               => 'https://borntoplay.no',
                        'domain'            => 'borntoplay.no',
                        'coolify_container' => 'wordpress-k103zs4t7n43272c2kvds93g',
                        'customer_name'     => 'LAKI AS',
                        'monthly_cost_nok'  => 0,
                    ],
                    [
                        'name'              => 'boligdama.no',
                        'url'               => 'https://boligdama.no',
                        'domain'            => 'boligdama.no',
                        'coolify_container' => 'wordpress-wagm29yh67zhwyngo6g615qu',
                        'customer_name'     => '',
                        'monthly_cost_nok'  => 0,
                    ],
                    [
                        'name'              => 'KBA — Kvinnebevegelsens arkiv',
                        'url'               => 'https://kba.arnsteinlarsen.no',
                        'domain'            => 'kba.arnsteinlarsen.no',
                        'coolify_container' => 'wordpress-t6ocutdm9bhc1d8d4k4z6qed',
                        'customer_name'     => 'Kvinnebevegelsens arkiv',
                        'monthly_cost_nok'  => 0,
                    ],
                    [
                        'name'              => 'Edifice',
                        'url'               => 'https://edifice.arnsteinlarsen.no',
                        'domain'            => 'edifice.arnsteinlarsen.no',
                        'coolify_container' => 'wordpress-l78r6g3o96gmke1f64raie3e',
                        'customer_name'     => 'LAKI AS',
                        'monthly_cost_nok'  => 0,
                    ],
                ];
                foreach ($initial_sites as $site) {
                    $wpdb->insert($sites_table, $site);
                }
            }
            update_option('edifice_sites_seeded', true);
        }
    }
}
