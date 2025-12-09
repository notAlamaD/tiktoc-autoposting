<?php
/**
 * Admin view: API Logs list.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap">
    <h1><?php esc_html_e( 'TikTok API Logs', 'tiktok-auto-poster' ); ?></h1>
    <p><?php esc_html_e( 'Recent API responses captured when logging is enabled. Use these entries to debug errors returned by TikTok.', 'tiktok-auto-poster' ); ?></p>

    <?php if ( ! tiktok_auto_poster_get_option( 'enable_logging' ) ) : ?>
        <div class="notice notice-warning"><p><?php esc_html_e( 'API logging is currently disabled. Enable it in the settings page to start recording responses.', 'tiktok-auto-poster' ); ?></p></div>
    <?php endif; ?>

    <?php if ( empty( $logs ) ) : ?>
        <p><?php esc_html_e( 'No log entries found yet.', 'tiktok-auto-poster' ); ?></p>
    <?php else : ?>
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Date', 'tiktok-auto-poster' ); ?></th>
                    <th><?php esc_html_e( 'Endpoint', 'tiktok-auto-poster' ); ?></th>
                    <th><?php esc_html_e( 'Method', 'tiktok-auto-poster' ); ?></th>
                    <th><?php esc_html_e( 'Status', 'tiktok-auto-poster' ); ?></th>
                    <th><?php esc_html_e( 'Request', 'tiktok-auto-poster' ); ?></th>
                    <th><?php esc_html_e( 'Response / Error', 'tiktok-auto-poster' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $logs as $log ) : ?>
                    <tr>
                        <td><?php echo esc_html( $log['date'] ?? '' ); ?></td>
                        <td><code><?php echo esc_html( $log['endpoint'] ?? '' ); ?></code></td>
                        <td><?php echo esc_html( $log['method'] ?? '' ); ?></td>
                        <td><?php echo isset( $log['response_code'] ) ? esc_html( $log['response_code'] ) : '&mdash;'; ?></td>
                        <td><small><?php echo esc_html( $log['request'] ?? '' ); ?></small></td>
                        <td><small><?php echo esc_html( $log['response_body'] ?? '' ); ?></small></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
