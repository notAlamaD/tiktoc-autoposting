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

        $posts    = new TikTok_Posts();
        $attempts = (int) $item['attempts'] + 1;

        $posts->mark_processing( $item['post_id'], $attempts );

        $result = $this->publish_post_to_tiktok( $item['post_id'], $attempts );

        if ( is_wp_error( $result ) ) {
            $this->handle_error( $queue, $item, $result->get_error_message(), $attempts );
            $posts->mark_error( $item['post_id'], $result->get_error_message(), $attempts );
            return;
        }

        $queue->update(
            $item['id'],
            array(
                'status'         => 'success',
                'tiktok_post_id' => $result['data']['post_id'] ?? '',
                'attempts'       => $attempts,
            )
        );

        $posts->mark_published( $item['post_id'], $result['data']['post_id'] ?? '', $attempts );
    }

    /**
     * Handle error retry.
     *
     * @param TikTok_Queue $queue Queue instance.
     * @param array        $item Queue row.
     * @param string       $message Error message.
     */
    protected function handle_error( $queue, $item, $message, $attempts ) {
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

    /**
     * Publish a single post immediately, updating TikTok posts table.
     *
     * @param int $post_id Post ID.
     * @return array{status:string,message?:string,post_id?:string}
     */
    public function publish_single_post( $post_id ) {
        $posts    = new TikTok_Posts();
        $record   = $posts->ensure( $post_id );
        $attempts = (int) ( $record['attempts'] ?? 0 ) + 1;

        $posts->mark_processing( $post_id, $attempts );

        $result = $this->publish_post_to_tiktok( $post_id, $attempts );

        if ( is_wp_error( $result ) ) {
            $posts->mark_error( $post_id, $result->get_error_message(), $attempts );

            return array(
                'status'  => 'error',
                'message' => $result->get_error_message(),
            );
        }

        $posts->mark_published( $post_id, $result['data']['post_id'] ?? '', $attempts );

        return array(
            'status'   => 'success',
            'post_id'  => $result['data']['post_id'] ?? '',
        );
    }

    /**
     * Build and dispatch TikTok publish request.
     *
     * @param int $post_id Post ID.
     * @param int $attempts Attempt number.
     * @return array|WP_Error
     */
    protected function publish_post_to_tiktok( $post_id, $attempts ) {
        $post = get_post( $post_id );

        if ( ! $post ) {
            return new WP_Error( 'missing_post', __( 'Post not found', 'tiktok-auto-poster' ) );
        }

        $media_source = tiktok_auto_poster_get_option( 'media_source', 'featured' );
        $file_path    = tiktok_auto_poster_get_media_file( $post, $media_source );

        if ( ! $file_path ) {
            return new WP_Error( 'media_missing', __( 'Media not found', 'tiktok-auto-poster' ) );
        }

        $client  = new TikTok_Api_Client();
        $creator = tiktok_auto_poster_get_creator_info_cached();

        if ( is_wp_error( $creator ) ) {
            return new WP_Error( 'creator_info_missing', sprintf( __( 'Unable to load TikTok creator info: %s', 'tiktok-auto-poster' ), $creator->get_error_message() ) );
        }

        $creator_info = $creator['data']['creator_info'] ?? $creator['creator_info'] ?? array();
        $can_post     = null;

        if ( isset( $creator_info['can_post'] ) ) {
            $can_post = (bool) $creator_info['can_post'];
        } elseif ( isset( $creator_info['can_post_more'] ) ) {
            $can_post = (bool) $creator_info['can_post_more'];
        }

        if ( false === $can_post ) {
            return new WP_Error( 'creator_blocked', __( 'TikTok reports this account cannot publish right now. Please try again later.', 'tiktok-auto-poster' ) );
        }

        $media_type = tiktok_auto_poster_detect_media_type( $file_path );

        if ( 'VIDEO' === $media_type && ! empty( $creator_info['max_video_post_duration_sec'] ) ) {
            $max_duration = absint( $creator_info['max_video_post_duration_sec'] );
            $duration     = tiktok_auto_poster_get_video_duration_seconds( $file_path );

            if ( $duration && $duration > $max_duration ) {
                return new WP_Error(
                    'video_too_long',
                    sprintf(
                        /* translators: 1: video length, 2: maximum allowed */
                        __( 'Video duration is %1$s seconds, but the connected TikTok account allows up to %2$s seconds.', 'tiktok-auto-poster' ),
                        $duration,
                        $max_duration
                    )
                );
            }
        }

        $description = tiktok_auto_poster_format_description( $post, tiktok_auto_poster_get_option( 'description', '{post_title}' ) );
        $post_mode   = tiktok_auto_poster_get_option( 'post_mode', 'DIRECT_POST' );
        $privacy     = tiktok_auto_poster_get_option( 'privacy_level', 'PUBLIC_TO_EVERYONE' );

        return $client->publish_content( $post, $file_path, $description, $post_mode, $privacy );
    }
}
