<?php
/**
 * Gerenciador da Tabela de Índice
 */

class Vana_Index {

    private static string $table_name = '';

    public static function init(): void {
        global $wpdb;
        self::$table_name = $wpdb->prefix . 'vana_origin_index';
    }

    public static function create_table(): void {
        global $wpdb;

        if (empty(self::$table_name)) {
            self::init();
        }

        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS " . self::$table_name . " (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            origin_key varchar(255) NOT NULL,
            post_id bigint(20) UNSIGNED NOT NULL,
            post_type varchar(20) NOT NULL,
            content_hash varchar(64) NOT NULL,
            source varchar(50) DEFAULT 'youtube',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY origin_key (origin_key),
            KEY post_id (post_id),
            KEY post_type (post_type),
            KEY source (source)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public static function find_by_origin_key(string $origin_key): ?object {
        global $wpdb;

        if (empty(self::$table_name)) {
            self::init();
        }

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::$table_name . " WHERE origin_key = %s",
            $origin_key
        ));
    }

    /**
     * Patch C: upsert atômico
     */
    public static function upsert(
        string $origin_key,
        int $post_id,
        string $post_type,
        string $hash,
        string $source = 'youtube'
    ): bool {
        global $wpdb;

        if (empty(self::$table_name)) {
            self::init();
        }

        $sql = "INSERT INTO " . self::$table_name . "
            (origin_key, post_id, post_type, content_hash, source, created_at, updated_at)
            VALUES (%s, %d, %s, %s, %s, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                post_id = VALUES(post_id),
                post_type = VALUES(post_type),
                content_hash = VALUES(content_hash),
                source = VALUES(source),
                updated_at = NOW()";

        $result = $wpdb->query($wpdb->prepare(
            $sql,
            $origin_key,
            $post_id,
            $post_type,
            $hash,
            $source
        ));

        return $result !== false;
    }
}
