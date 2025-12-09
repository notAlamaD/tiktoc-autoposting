<?php
/**
 * TikTok posts tracking table.
 *
 * @package TikTokAutoPoster
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TikTok_Posts {
    const TABLE = 'tiktok_posts';

    /**
     * Create the posts table.
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
            UNIQUE KEY post_id (post_id),
            KEY status (status)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Ensure a record exists for the given post.
     *
     * @param int $post_id Post ID.
     * @return array Row data.
     */
    public function ensure( $post_id ) {
        $row = $this->get_by_post( $post_id );

        if ( $row ) {
            return $row;
        }

        global $wpdb;

        $wpdb->insert(
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

        return $this->get_by_post( $post_id );
    }

    /**
     * Get a record by post id.
     *
     * @param int $post_id Post ID.
     * @return array|null
     */
    public function get_by_post( $post_id ) {
        global $wpdb;

        $table = $wpdb->prefix . self::TABLE;

        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE post_id = %d", $post_id ), ARRAY_A );

        return $row ?: null;
    }

    /**
     * Get a record by table id.
     *
     * @param int $id Row id.
     * @return array|null
     */
    public function get( $id ) {
        global $wpdb;

        $table = $wpdb->prefix . self::TABLE;
        $row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );

        return $row ?: null;
    }

    /**
     * Mark a post as pending.
     *
     * @param int $post_id Post ID.
     */
    public function mark_pending( $post_id ) {
        $this->ensure( $post_id );
        $this->update_status( $post_id, 'pending', array( 'last_error' => null ) );
    }

    /**
     * Mark a post as processing with a given attempt number.
     *
     * @param int $post_id Post ID.
     * @param int $attempt Attempt number.
     */
    public function mark_processing( $post_id, $attempt ) {
        $this->ensure( $post_id );
        $this->update_status(
            $post_id,
            'processing',
            array(
                'attempts' => $attempt,
            )
        );
    }

    /**
     * Mark a post as published.
     *
     * @param int    $post_id Post ID.
     * @param string $tiktok_post_id TikTok post id.
     * @param int    $attempt Attempt number.
     */
    public function mark_published( $post_id, $tiktok_post_id, $attempt ) {
        $this->ensure( $post_id );
        $this->update_status(
            $post_id,
            'published',
            array(
                'tiktok_post_id' => $tiktok_post_id,
                'attempts'       => $attempt,
                'last_error'     => null,
            )
        );
    }

    /**
     * Mark a post as errored.
     *
     * @param int    $post_id Post ID.
     * @param string $message Error message.
     * @param int    $attempt Attempt number.
     */
    public function mark_error( $post_id, $message, $attempt ) {
        $this->ensure( $post_id );
        $this->update_status(
            $post_id,
            'error',
            array(
                'attempts'   => $attempt,
                'last_error' => $message,
            )
        );
    }

    /**
     * Get recent TikTok post records.
     *
     * @param int $limit Limit.
     * @return array
     */
    public function get_recent( $limit = 50 ) {
        global $wpdb;

        $table = $wpdb->prefix . self::TABLE;

        $query = $wpdb->prepare( "SELECT * FROM {$table} ORDER BY updated_at DESC LIMIT %d", $limit );

        return $wpdb->get_results( $query, ARRAY_A );
    }

    /**
     * Get pending records for cron processing.
     *
     * @param int $limit Limit.
     * @return array
     */
    public function get_pending( $limit = 5 ) {
        global $wpdb;

        $table = $wpdb->prefix . self::TABLE;
        $query = $wpdb->prepare( "SELECT * FROM {$table} WHERE status = %s ORDER BY updated_at ASC LIMIT %d", 'pending', $limit );

        return $wpdb->get_results( $query, ARRAY_A );
    }

    /**
     * Update status + meta for a post record.
     *
     * @param int    $post_id Post ID.
     * @param string $status Status.
     * @param array  $data Extra data.
     */
    protected function update_status( $post_id, $status, $data = array() ) {
        global $wpdb;

        $data['status']     = $status;
        $data['updated_at'] = current_time( 'mysql' );

        $wpdb->update( $wpdb->prefix . self::TABLE, $data, array( 'post_id' => $post_id ) );
    }
}
