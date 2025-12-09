<?php
/**
 * CRON management for TikTok Auto Poster.
 *
 * @package TikTokAutoPoster
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TikTok_Cron {
    /**
     * Constructor.
     */
    public function __construct() {
        add_filter( 'cron_schedules', array( $this, 'add_intervals' ) );
        add_action( 'init', array( $this, 'maybe_schedule' ) );
        add_action( 'tiktok_auto_poster_cron', array( $this, 'process_queue' ) );
    }

    /**
     * Add custom intervals.
     *
     * @param array $schedules Registered schedules.
     * @return array
     */
    public function add_intervals( $schedules ) {
        $intervals = array( 5, 15, 30, 60 );

        foreach ( $intervals as $minutes ) {
            $schedules[ 'tiktok_' . $minutes ] = array(
                'interval' => MINUTE_IN_SECONDS * $minutes,
                'display'  => sprintf( __( 'Every %d minutes', 'tiktok-auto-poster' ), $minutes ),
            );
        }

        return $schedules;
    }

    /**
     * Clear cron schedule on deactivation.
     */
    public static function clear_schedule() {
        wp_clear_scheduled_hook( 'tiktok_auto_poster_cron' );
    }

    /**
     * Schedule cron based on settings.
     */
    public function maybe_schedule() {
        $schedule = $this->get_schedule_name();

        $next = wp_next_scheduled( 'tiktok_auto_poster_cron' );
        if ( $next ) {
            return;
        }

        wp_schedule_event( time(), $schedule, 'tiktok_auto_poster_cron' );
    }

    /**
     * Reschedule cron when settings change.
     */
    public static function reschedule() {
        self::clear_schedule();
        $self = new self();
        $self->maybe_schedule();
    }

    /**
     * Get selected schedule name.
     *
     * @return string
     */
    protected function get_schedule_name() {
        $interval = (int) tiktok_auto_poster_get_option( 'cron_interval', 15 );
        $allowed  = array( 5, 15, 30, 60 );

        if ( ! in_array( $interval, $allowed, true ) ) {
            $interval = 15;
        }

        return 'tiktok_' . $interval;
    }

    /**
     * Process queue items.
     */
    public function process_queue() {
        $queue = new TikTok_Queue();
        $items = $queue->get_pending( 5 );

        foreach ( $items as $item ) {
            $this->process_item( $queue, $item );
        }
    }

    /**
     * Process a single queue item.
     *
     * @param TikTok_Queue $queue Queue instance.
     * @param array        $item Queue row.
     */
    public function process_item( $queue, $item ) {
        $queue->update( $item['id'], array( 'status' => 'processing' ) );

        $post = get_post( $item['post_id'] );
        if ( ! $post ) {
            $queue->update( $item['id'], array( 'status' => 'error', 'last_error' => 'Post not found' ) );
            return;
        }

        $media_source = tiktok_auto_poster_get_option( 'media_source', 'featured' );
        $file_path    = tiktok_auto_poster_get_media_file( $post, $media_source );

        if ( ! $file_path ) {
            $queue->update( $item['id'], array( 'status' => 'error', 'last_error' => __( 'Media not found', 'tiktok-auto-poster' ) ) );
            return;
        }

        $client      = new TikTok_Api_Client();
        $description = tiktok_auto_poster_format_description( $post, tiktok_auto_poster_get_option( 'description', '{post_title}' ) );

        $upload = $client->upload_media( $file_path );

        if ( is_wp_error( $upload ) ) {
            $this->handle_error( $queue, $item, $upload->get_error_message() );
            return;
        }

        $media_id = $upload['data']['media_id'] ?? '';

        $post_resp = $client->create_post( $media_id, $description );

        if ( is_wp_error( $post_resp ) ) {
            $this->handle_error( $queue, $item, $post_resp->get_error_message() );
            return;
        }

        $queue->update(
            $item['id'],
            array(
                'status'         => 'success',
                'tiktok_post_id' => $post_resp['data']['post_id'] ?? '',
                'attempts'       => $item['attempts'] + 1,
            )
        );
    }

    /**
     * Handle error retry.
     *
     * @param TikTok_Queue $queue Queue instance.
     * @param array        $item Queue row.
     * @param string       $message Error message.
     */
    protected function handle_error( $queue, $item, $message ) {
        $attempts = (int) $item['attempts'] + 1;
        $status   = $attempts >= 3 ? 'error' : 'pending';

        $queue->update(
            $item['id'],
            array(
                'status'     => $status,
                'attempts'   => $attempts,
                'last_error' => $message,
            )
        );
    }
}
