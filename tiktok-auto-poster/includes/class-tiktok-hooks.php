<?php
/**
 * Post hooks for TikTok Auto Poster.
 *
 * @package TikTokAutoPoster
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TikTok_Hooks {
    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'transition_post_status', array( $this, 'maybe_queue_post' ), 10, 3 );
    }

    /**
     * Determine if post should be queued or sent immediately.
     *
     * @param string  $new_status New status.
     * @param string  $old_status Old status.
     * @param WP_Post $post Post.
     */
    public function maybe_queue_post( $new_status, $old_status, $post ) {
        if ( 'publish' !== $new_status || 'publish' === $old_status ) {
            return;
        }

        $settings = get_option( 'tiktok_auto_poster_settings', array() );

        if ( empty( $settings['auto_post_enabled'] ) ) {
            return;
        }

        $allowed_types   = $settings['post_types'] ?? array();
        $allowed_status  = $settings['statuses'] ?? array( 'publish' );
        if ( ! in_array( $post->post_type, $allowed_types, true ) ) {
            return;
        }

        if ( ! in_array( $new_status, $allowed_status, true ) ) {
            return;
        }

        $queue        = new TikTok_Queue();
        $use_queue    = ! empty( $settings['queue_enabled'] );
        $media_source = tiktok_auto_poster_get_option( 'media_source', 'featured' );
        $file_path    = tiktok_auto_poster_get_media_file( $post, $media_source );

        if ( ! $file_path ) {
            return;
        }

        if ( $use_queue ) {
            $queue->enqueue( $post->ID );
            return;
        }

        $client      = new TikTok_Api_Client();
        $description = tiktok_auto_poster_format_description( $post, tiktok_auto_poster_get_option( 'description', '{post_title}' ) );
        $response  = $client->publish_content( $post, $file_path, $description );
        $status    = is_wp_error( $response ) ? 'error' : 'success';
        $error_msg = is_wp_error( $response ) ? $response->get_error_message() : '';

        $queue->enqueue( $post->ID );
        $queue->update(
            $this->get_latest_queue_id( $queue, $post->ID ),
            array(
                'status'         => $status,
                'tiktok_post_id' => is_wp_error( $response ) ? '' : ( $response['data']['post_id'] ?? '' ),
                'last_error'     => $error_msg,
                'attempts'       => 1,
            )
        );
    }

    /**
     * Retrieve latest queue entry id for a post.
     *
     * @param TikTok_Queue $queue Queue instance.
     * @param int          $post_id Post ID.
     * @return int|null
     */
    protected function get_latest_queue_id( $queue, $post_id ) {
        global $wpdb;

        $table = $wpdb->prefix . TikTok_Queue::TABLE;

        return (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE post_id = %d ORDER BY created_at DESC LIMIT 1", $post_id ) );
    }
}
