<?php
/**
 * Settings page for plugin.
 *
 * @package TikTokAutoPoster
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TikTok_Settings {
    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_post_tiktok_disconnect', array( $this, 'disconnect' ) );
        add_action( 'update_option_tiktok_auto_poster_settings', array( $this, 'after_settings_saved' ), 10, 3 );
    }

    /**
     * Register menu items.
     */
    public function register_menu() {
        add_options_page(
            __( 'TikTok Auto Poster', 'tiktok-auto-poster' ),
            __( 'TikTok Auto Poster', 'tiktok-auto-poster' ),
            'manage_options',
            'tiktok-auto-poster',
            array( $this, 'render_settings_page' )
        );

        add_menu_page(
            __( 'TikTok Posts', 'tiktok-auto-poster' ),
            __( 'TikTok Posts', 'tiktok-auto-poster' ),
            'manage_options',
            'tiktok-auto-poster-queue',
            array( $this, 'render_queue_page' ),
            'dashicons-format-video',
            81
        );
    }

    /**
     * Register settings fields.
     */
    public function register_settings() {
        register_setting( 'tiktok-auto-poster', 'tiktok_auto_poster_settings', array( $this, 'sanitize_settings' ) );
    }

    /**
     * Sanitize settings before saving.
     *
     * @param array $input Raw input.
     * @return array
     */
    public function sanitize_settings( $input ) {
        $sanitized = array();
        $sanitized['client_key']       = sanitize_text_field( $input['client_key'] ?? '' );
        $sanitized['client_secret']    = sanitize_text_field( $input['client_secret'] ?? '' );
        $sanitized['redirect_uri']     = esc_url_raw( admin_url( 'admin-post.php?action=tiktok_oauth_callback' ) );
        $sanitized['auto_post_enabled'] = ! empty( $input['auto_post_enabled'] ) ? 1 : 0;
        $sanitized['post_types']       = array_map( 'sanitize_text_field', $input['post_types'] ?? array() );
        $sanitized['statuses']         = array_map( 'sanitize_text_field', $input['statuses'] ?? array( 'publish' ) );
        $sanitized['media_source']     = sanitize_text_field( $input['media_source'] ?? 'featured' );
        $sanitized['description']      = sanitize_textarea_field( $input['description'] ?? '{post_title}\n\n{excerpt}\n\n{post_url}' );
        $sanitized['queue_enabled']    = ! empty( $input['queue_enabled'] ) ? 1 : 0;
        $sanitized['cron_interval']    = sanitize_text_field( $input['cron_interval'] ?? '15' );
        $sanitized['enable_logging']   = ! empty( $input['enable_logging'] );

        return $sanitized;
    }

    /**
     * React to settings being saved.
     *
     * @param array $old_value Previous value.
     * @param array $value New value.
     * @param string $option Option name.
     */
    public function after_settings_saved( $old_value, $value, $option ) {
        if ( isset( $old_value['cron_interval'] ) && $old_value['cron_interval'] !== $value['cron_interval'] ) {
            TikTok_Cron::reschedule();
        }
    }

    /**
     * Disconnect TikTok account.
     */
    public function disconnect() {
        check_admin_referer( 'tiktok_disconnect' );

        tiktok_auto_poster_set_option( 'token', '' );
        wp_safe_redirect( admin_url( 'options-general.php?page=tiktok-auto-poster' ) );
        exit;
    }

    /**
     * Render settings page.
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $settings = get_option( 'tiktok_auto_poster_settings', array() );
        include TIKTOK_AUTO_POSTER_DIR . 'admin/views-settings.php';
    }

    /**
     * Render queue page wrapper.
     */
    public function render_queue_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        include TIKTOK_AUTO_POSTER_DIR . 'admin/views-queue.php';
    }
}
