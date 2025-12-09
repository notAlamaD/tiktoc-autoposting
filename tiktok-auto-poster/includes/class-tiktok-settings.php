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
        add_action( 'init', array( $this, 'register_rewrite_rules' ) );
        add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
        add_action( 'template_redirect', array( $this, 'maybe_handle_oauth_callback' ) );
        add_action( 'admin_post_tiktok_disconnect', array( $this, 'disconnect' ) );
        add_action( 'admin_post_tiktok_start_oauth', array( $this, 'start_oauth' ) );
        add_action( 'admin_post_tiktok_manual_queue', array( $this, 'manual_enqueue' ) );
        add_action( 'admin_post_tiktok_delete_queue', array( $this, 'delete_queue_item' ) );
        add_action( 'admin_post_tiktok_publish_post', array( $this, 'publish_tiktok_post' ) );
        add_action( 'update_option_tiktok_auto_poster_settings', array( $this, 'after_settings_saved' ), 10, 3 );
    }

    /**
     * Run on plugin activation.
     */
    public static function activate() {
        $self = new self();
        $self->register_rewrite_rules();
        flush_rewrite_rules();
    }

    /**
     * Run on plugin deactivation.
     */
    public static function deactivate() {
        flush_rewrite_rules();
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

        add_submenu_page(
            'tiktok-auto-poster-queue',
            __( 'TikTok Posts Status', 'tiktok-auto-poster' ),
            __( 'TikTok Posts Status', 'tiktok-auto-poster' ),
            'manage_options',
            'tiktok-auto-poster-posts',
            array( $this, 'render_posts_page' )
        );

        add_submenu_page(
            'tiktok-auto-poster-queue',
            __( 'Connected Accounts', 'tiktok-auto-poster' ),
            __( 'Connected Accounts', 'tiktok-auto-poster' ),
            'manage_options',
            'tiktok-auto-poster-accounts',
            array( $this, 'render_accounts_page' )
        );

        add_submenu_page(
            'tiktok-auto-poster-queue',
            __( 'API Logs', 'tiktok-auto-poster' ),
            __( 'API Logs', 'tiktok-auto-poster' ),
            'manage_options',
            'tiktok-auto-poster-logs',
            array( $this, 'render_logs_page' )
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
        $sanitized['redirect_uri']     = esc_url_raw( $this->get_redirect_uri() );
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
     * Start OAuth flow by redirecting to TikTok.
     */
    public function start_oauth() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Unauthorized', 'tiktok-auto-poster' ) );
        }

        check_admin_referer( 'tiktok_connect' );

        $client_key    = tiktok_auto_poster_get_option( 'client_key' );
        $client_secret = tiktok_auto_poster_get_option( 'client_secret' );
        $redirect      = $this->get_redirect_uri();

        if ( empty( $client_key ) || empty( $client_secret ) ) {
            add_settings_error( 'tiktok_auto_poster', 'missing_client', __( 'Enter Client Key and Client Secret before connecting.', 'tiktok-auto-poster' ) );
            set_transient( 'settings_errors', get_settings_errors(), 30 );
            wp_safe_redirect( admin_url( 'options-general.php?page=tiktok-auto-poster' ) );
            exit;
        }

        $state = wp_create_nonce( 'tiktok_oauth_state' );

        $auth_url = add_query_arg(
            array(
                'client_key'    => $client_key,
                'scope'         => 'video.upload,video.publish,user.info.basic',
                'response_type' => 'code',
                'redirect_uri'  => $redirect,
                'state'         => $state,
            ),
            'https://www.tiktok.com/v2/auth/authorize/'
        );

        wp_safe_redirect( $auth_url );
        exit;
    }

    /**
     * Handle OAuth callback and exchange code for tokens.
     */
    public function handle_oauth_callback() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Unauthorized', 'tiktok-auto-poster' ) );
        }

        $state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';

        if ( ! wp_verify_nonce( $state, 'tiktok_oauth_state' ) ) {
            wp_die( esc_html__( 'Invalid OAuth state.', 'tiktok-auto-poster' ) );
        }

        $code = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';

        if ( empty( $code ) ) {
            wp_die( esc_html__( 'Missing OAuth code.', 'tiktok-auto-poster' ) );
        }

        $client_key    = tiktok_auto_poster_get_option( 'client_key' );
        $client_secret = tiktok_auto_poster_get_option( 'client_secret' );
        $redirect_uri  = $this->get_redirect_uri();

        $response = wp_remote_post(
            'https://open.tiktokapis.com/v2/oauth/token/',
            array(
                'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
                'body'    => array(
                    'client_key'    => $client_key,
                    'client_secret' => $client_secret,
                    'code'          => $code,
                    'grant_type'    => 'authorization_code',
                    'redirect_uri'  => $redirect_uri,
                ),
                'timeout' => 30,
            )
        );

        tiktok_auto_poster_log_request(
            array(
                'endpoint' => 'https://open.tiktokapis.com/v2/oauth/token/',
                'method'   => 'POST',
                'request'  => array(
                    'client_key'   => $client_key,
                    'redirect_uri' => $redirect_uri,
                    'grant_type'   => 'authorization_code',
                ),
            ),
            $response
        );

        if ( is_wp_error( $response ) ) {
            wp_die( esc_html( $response->get_error_message() ) );
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $data['access_token'] ) || empty( $data['refresh_token'] ) ) {
            wp_die( esc_html__( 'Failed to retrieve access token from TikTok.', 'tiktok-auto-poster' ) );
        }

        $token = array(
            'access_token'  => $data['access_token'],
            'refresh_token' => $data['refresh_token'],
            'expires_at'    => time() + absint( $data['expires_in'] ?? 0 ),
            'open_id'       => $data['open_id'] ?? '',
            'scope'         => $data['scope'] ?? '',
        );

        tiktok_auto_poster_set_option( 'token', tiktok_auto_poster_encrypt( wp_json_encode( $token ) ) );

        wp_safe_redirect( admin_url( 'options-general.php?page=tiktok-auto-poster&connected=1' ) );
        exit;
    }

    /**
     * Register rewrite rule for OAuth callback.
     */
    public function register_rewrite_rules() {
        add_rewrite_rule( '^tiktok-oauth-callback/?$', 'index.php?tiktok_oauth_callback=1', 'top' );
    }

    /**
     * Allow query var for OAuth callback detection.
     *
     * @param array $vars Query vars.
     * @return array
     */
    public function add_query_vars( $vars ) {
        $vars[] = 'tiktok_oauth_callback';

        return $vars;
    }

    /**
     * Maybe handle OAuth callback routed through rewrite.
     */
    public function maybe_handle_oauth_callback() {
        if ( ! $this->is_callback_request() ) {
            return;
        }

        $this->handle_oauth_callback();
    }

    /**
     * Determine whether the current request is targeting the OAuth callback.
     *
     * @return bool
     */
    private function is_callback_request() {
        // Primary detection via rewrite/query var.
        if ( intval( get_query_var( 'tiktok_oauth_callback' ) ) === 1 ) {
            return true;
        }

        $slug = 'tiktok-oauth-callback';

        // Fallback for environments where rewrite rules have not flushed yet.
        if ( isset( $GLOBALS['wp'] ) && isset( $GLOBALS['wp']->request ) ) {
            if ( trim( $GLOBALS['wp']->request, '/' ) === $slug ) {
                return true;
            }
        }

        // Final safeguard based on the actual request URI path.
        $path = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH ) : '';
        $path = is_string( $path ) ? trim( $path, '/' ) : '';
        $callback_path = trim( wp_parse_url( home_url( $slug ), PHP_URL_PATH ), '/' );

        return $path === $callback_path;
    }

    /**
     * Get redirect URI for TikTok OAuth.
     *
     * @return string
     */
    private function get_redirect_uri() {
        return trailingslashit( home_url( 'tiktok-oauth-callback' ) );
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

    /**
     * Render TikTok posts status page.
     */
    public function render_posts_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $posts_repo = new TikTok_Posts();
        $posts_rows = $posts_repo->get_recent( 50 );

        $publish_status  = isset( $_GET['publish_status'] ) ? sanitize_text_field( wp_unslash( $_GET['publish_status'] ) ) : '';
        $publish_message = isset( $_GET['publish_message'] ) ? sanitize_text_field( wp_unslash( rawurldecode( $_GET['publish_message'] ) ) ) : '';

        include TIKTOK_AUTO_POSTER_DIR . 'admin/views-posts.php';
    }

    /**
     * Render API logs page.
     */
    public function render_logs_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $logs = array_reverse( tiktok_auto_poster_get_logs() );

        include TIKTOK_AUTO_POSTER_DIR . 'admin/views-logs.php';
    }

    /**
     * Handle manual queue submission.
     */
    public function manual_enqueue() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Unauthorized', 'tiktok-auto-poster' ) );
        }

        check_admin_referer( 'tiktok_manual_queue' );

        $post_id       = absint( $_POST['manual_post_id'] ?? 0 );
        $selected_post = absint( $_POST['queued_post'] ?? 0 );
        $send_now      = ! empty( $_POST['send_now'] );

        if ( ! $post_id && $selected_post ) {
            $post_id = $selected_post;
        }

        if ( ! $post_id ) {
            wp_safe_redirect( add_query_arg( array( 'page' => 'tiktok-auto-poster-queue', 'manual_status' => 'error', 'manual_message' => rawurlencode( __( 'Select a post to queue.', 'tiktok-auto-poster' ) ) ), admin_url( 'admin.php' ) ) );
            exit;
        }

        $post = get_post( $post_id );

        if ( ! $post ) {
            wp_safe_redirect( add_query_arg( array( 'page' => 'tiktok-auto-poster-queue', 'manual_status' => 'error', 'manual_message' => rawurlencode( __( 'Post not found.', 'tiktok-auto-poster' ) ) ), admin_url( 'admin.php' ) ) );
            exit;
        }

        $queue    = new TikTok_Queue();
        $queue_id = $queue->enqueue( $post_id );

        if ( ! $queue_id ) {
            wp_safe_redirect( add_query_arg( array( 'page' => 'tiktok-auto-poster-queue', 'manual_status' => 'error', 'manual_message' => rawurlencode( __( 'Unable to add to queue.', 'tiktok-auto-poster' ) ) ), admin_url( 'admin.php' ) ) );
            exit;
        }

        $status = 'queued';

        if ( $send_now ) {
            $cron = new TikTok_Cron();
            $item = $queue->get( $queue_id );

            if ( $item ) {
                $cron->process_item( $queue, $item );
                $processed = $queue->get( $queue_id );
                if ( $processed && ! empty( $processed['status'] ) ) {
                    $status = $processed['status'];
                }
            }
        }

        wp_safe_redirect( add_query_arg( array( 'page' => 'tiktok-auto-poster-queue', 'manual_status' => $status ), admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Handle queue item deletion.
     */
    public function delete_queue_item() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Unauthorized', 'tiktok-auto-poster' ) );
        }

        $queue_id = absint( $_POST['queue_id'] ?? 0 );

        if ( ! $queue_id ) {
            wp_safe_redirect( add_query_arg( array( 'page' => 'tiktok-auto-poster-queue', 'queue_status' => 'error', 'queue_message' => rawurlencode( __( 'Queue item not found.', 'tiktok-auto-poster' ) ) ), admin_url( 'admin.php' ) ) );
            exit;
        }

        check_admin_referer( 'tiktok_delete_queue_' . $queue_id );

        $queue = new TikTok_Queue();
        $queue->delete( $queue_id );

        wp_safe_redirect( add_query_arg( array( 'page' => 'tiktok-auto-poster-queue', 'queue_status' => 'deleted' ), admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Render connected accounts page.
     */
    public function render_accounts_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $token          = tiktok_auto_poster_get_option( 'token' );
        $token_details  = $token ? json_decode( tiktok_auto_poster_decrypt( $token ), true ) : array();
        $client         = new TikTok_Api_Client();
        $account_result = null;

        if ( ! empty( $token_details['access_token'] ) ) {
            $account_result = $client->get_user_info();
        }

        include TIKTOK_AUTO_POSTER_DIR . 'admin/views-accounts.php';
    }

    /**
     * Publish a pending TikTok post from the status table.
     */
    public function publish_tiktok_post() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Unauthorized', 'tiktok-auto-poster' ) );
        }

        $record_id = absint( $_POST['record_id'] ?? 0 );

        if ( ! $record_id ) {
            wp_safe_redirect( add_query_arg( array( 'page' => 'tiktok-auto-poster-posts', 'publish_status' => 'error', 'publish_message' => rawurlencode( __( 'Record not found.', 'tiktok-auto-poster' ) ) ), admin_url( 'admin.php' ) ) );
            exit;
        }

        check_admin_referer( 'tiktok_publish_post_' . $record_id );

        $posts_repo = new TikTok_Posts();
        $record     = $posts_repo->get( $record_id );

        if ( ! $record ) {
            wp_safe_redirect( add_query_arg( array( 'page' => 'tiktok-auto-poster-posts', 'publish_status' => 'error', 'publish_message' => rawurlencode( __( 'Record not found.', 'tiktok-auto-poster' ) ) ), admin_url( 'admin.php' ) ) );
            exit;
        }

        $cron   = new TikTok_Cron();
        $result = $cron->publish_single_post( $record['post_id'] );

        $status  = $result['status'] ?? 'error';
        $message = $result['message'] ?? __( 'Published to TikTok.', 'tiktok-auto-poster' );

        wp_safe_redirect( add_query_arg( array( 'page' => 'tiktok-auto-poster-posts', 'publish_status' => $status, 'publish_message' => rawurlencode( $message ) ), admin_url( 'admin.php' ) ) );
        exit;
    }
}
