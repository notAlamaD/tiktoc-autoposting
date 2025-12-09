<?php
/**
 * Plugin Name: TikTok Auto Poster
 * Description: Automatically publish WordPress posts to TikTok via official API.
 * Version: 0.1.0
 * Author: Auto Generated
 * Text Domain: tiktok-auto-poster
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'TIKTOK_AUTO_POSTER_VERSION' ) ) {
    define( 'TIKTOK_AUTO_POSTER_VERSION', '0.1.0' );
}

if ( ! defined( 'TIKTOK_AUTO_POSTER_DIR' ) ) {
    define( 'TIKTOK_AUTO_POSTER_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'TIKTOK_AUTO_POSTER_URL' ) ) {
    define( 'TIKTOK_AUTO_POSTER_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'TIKTOK_AUTO_POSTER_SECRET' ) ) {
    $tiktok_auto_poster_secret = '';

    if ( defined( 'AUTH_KEY' ) && AUTH_KEY ) {
        $tiktok_auto_poster_secret = AUTH_KEY;
    } elseif ( defined( 'SECURE_AUTH_KEY' ) && SECURE_AUTH_KEY ) {
        $tiktok_auto_poster_secret = SECURE_AUTH_KEY;
    } elseif ( function_exists( 'wp_salt' ) ) {
        $tiktok_auto_poster_secret = wp_salt();
    } else {
        $tiktok_auto_poster_secret = 'tiktok-auto-poster-secret';
    }

    define( 'TIKTOK_AUTO_POSTER_SECRET', $tiktok_auto_poster_secret );
}

require_once TIKTOK_AUTO_POSTER_DIR . 'includes/helpers.php';
require_once TIKTOK_AUTO_POSTER_DIR . 'includes/class-tiktok-posts.php';
require_once TIKTOK_AUTO_POSTER_DIR . 'includes/class-tiktok-api-client.php';
require_once TIKTOK_AUTO_POSTER_DIR . 'includes/class-tiktok-settings.php';
require_once TIKTOK_AUTO_POSTER_DIR . 'includes/class-tiktok-queue.php';
require_once TIKTOK_AUTO_POSTER_DIR . 'includes/class-tiktok-cron.php';
require_once TIKTOK_AUTO_POSTER_DIR . 'includes/class-tiktok-hooks.php';

class TikTok_Auto_Poster {
    public function __construct() {
        tiktok_auto_poster_migrate_token_option();
        add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
        register_activation_hook( __FILE__, array( 'TikTok_Queue', 'activate' ) );
        register_activation_hook( __FILE__, array( 'TikTok_Posts', 'activate' ) );
        register_activation_hook( __FILE__, array( 'TikTok_Settings', 'activate' ) );
        register_deactivation_hook( __FILE__, array( 'TikTok_Queue', 'deactivate' ) );
        register_deactivation_hook( __FILE__, array( 'TikTok_Settings', 'deactivate' ) );
        register_deactivation_hook( __FILE__, array( 'TikTok_Cron', 'clear_schedule' ) );

        new TikTok_Settings();
        new TikTok_Hooks();
        new TikTok_Cron();
    }

    public function load_textdomain() {
        load_plugin_textdomain( 'tiktok-auto-poster', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }
}

new TikTok_Auto_Poster();
