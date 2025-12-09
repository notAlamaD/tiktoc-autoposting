<?php
/**
 * Queue management.
 *
 * @package TikTokAutoPoster
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TikTok_Queue {
    const TABLE = 'tiktok_post_queue';

    /**
     * Create table and schedule cron.
     */
    public static function activate() {
        global $wpdb;

        $table_name      = $wpdb->prefix . self::TABLE;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id BIGINT(20) unsigned NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            attempts INT(10) NOT NULL DEFAULT 0,
            last_error TEXT NULL,
            tiktok_post_id VARCHAR(255) NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY status (status)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
        TikTok_Cron::reschedule();
    }

    /**
     * Cleanup schedules.
     */
    public static function deactivate() {
        wp_clear_scheduled_hook( 'tiktok_auto_poster_cron' );
    }

    /**
     * Add post to queue.
     *
     * @param int $post_id Post ID.
     */
    public function enqueue( $post_id ) {
        global $wpdb;

        $result = $wpdb->insert(
            $wpdb->prefix . self::TABLE,
            array(
                'post_id'    => $post_id,
                'status'     => 'pending',
                'attempts'   => 0,
                'last_error' => null,
                'created_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%d', '%s', '%s', '%s' )
        );

        return $result ? (int) $wpdb->insert_id : 0;
    }

    /**
     * Get queue item by id.
     *
     * @param int $id Row id.
     * @return array|null
     */
    public function get( $id ) {
        global $wpdb;

        $table = $wpdb->prefix . self::TABLE;

        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );

        return $row ?: null;
    }

    /**
     * Fetch pending records.
     *
     * @param int $limit Max rows.
     * @return array
     */
    public function get_pending( $limit = 5 ) {
        global $wpdb;

        $table = $wpdb->prefix . self::TABLE;
        $query = $wpdb->prepare( "SELECT * FROM {$table} WHERE status = %s ORDER BY created_at ASC LIMIT %d", 'pending', $limit );

        return $wpdb->get_results( $query, ARRAY_A );
    }

    /**
     * Get latest queue entries.
     *
     * @param int $limit Number of rows.
     * @return array
     */
    public function get_recent( $limit = 50 ) {
        global $wpdb;

        $table = $wpdb->prefix . self::TABLE;
        $query = $wpdb->prepare( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d", $limit );

        return $wpdb->get_results( $query, ARRAY_A );
    }

    /**
     * Update queue row.
     *
     * @param int   $id Row id.
     * @param array $data Data to update.
     */
    public function update( $id, $data ) {
        global $wpdb;

        $data['updated_at'] = current_time( 'mysql' );
        $wpdb->update( $wpdb->prefix . self::TABLE, $data, array( 'id' => $id ) );
    }

    /**
     * Delete queue row.
     *
     * @param int $id Row id.
     */
    public function delete( $id ) {
        global $wpdb;

        $wpdb->delete( $wpdb->prefix . self::TABLE, array( 'id' => $id ), array( '%d' ) );
    }
}
