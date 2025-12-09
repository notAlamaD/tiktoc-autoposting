<?php
/**
 * Connected accounts admin page.
 *
 * @package TikTokAutoPoster
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$profile_data      = array();
$connection_error  = null;

if ( $account_result instanceof WP_Error ) {
    $connection_error = $account_result;
} elseif ( is_array( $account_result ) ) {
    $profile_data = isset( $account_result['data'] ) ? $account_result['data'] : $account_result;
}

?>
<div class="wrap">
    <h1><?php esc_html_e( 'Connected TikTok Accounts', 'tiktok-auto-poster' ); ?></h1>
    <p><?php esc_html_e( 'Review the TikTok accounts currently authorized for automatic posting.', 'tiktok-auto-poster' ); ?></p>

    <?php if ( isset( $_GET['connected'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'TikTok account connected successfully.', 'tiktok-auto-poster' ); ?></p></div>
    <?php endif; ?>

    <?php if ( $connection_error ) : ?>
        <div class="notice notice-warning"><p><?php echo esc_html( $connection_error->get_error_message() ); ?></p></div>
    <?php endif; ?>

    <div class="card" style="max-width: 960px; margin-top: 15px;">
        <h2><?php esc_html_e( 'Connection status', 'tiktok-auto-poster' ); ?></h2>
        <?php if ( empty( $token_details['access_token'] ) ) : ?>
            <p><?php esc_html_e( 'Not connected yet. Use the button below to authorize one TikTok account for posting.', 'tiktok-auto-poster' ); ?></p>
            <?php if ( $client_key && $client_secret ) : ?>
                <a class="button button-primary" href="<?php echo esc_url( $auth_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Connect TikTok account', 'tiktok-auto-poster' ); ?></a>
                <p class="description"><?php esc_html_e( 'Connect opens TikTok authorization in a new tab. Return here after approving access.', 'tiktok-auto-poster' ); ?></p>
            <?php else : ?>
                <button class="button button-primary" type="button" disabled="disabled"><?php esc_html_e( 'Connect TikTok account', 'tiktok-auto-poster' ); ?></button>
                <p class="description"><?php esc_html_e( 'Save your Client Key and Client Secret in Settings before connecting.', 'tiktok-auto-poster' ); ?></p>
            <?php endif; ?>
        <?php else : ?>
            <p><?php esc_html_e( 'Connected to TikTok.', 'tiktok-auto-poster' ); ?></p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'tiktok_disconnect' ); ?>
                <input type="hidden" name="action" value="tiktok_disconnect" />
                <?php submit_button( __( 'Disconnect', 'tiktok-auto-poster' ), 'delete', 'submit', false ); ?>
            </form>
        <?php endif; ?>
    </div>

    <table class="widefat striped">
        <thead>
            <tr>
                <th><?php esc_html_e( 'TikTok User ID', 'tiktok-auto-poster' ); ?></th>
                <th><?php esc_html_e( 'Display Name', 'tiktok-auto-poster' ); ?></th>
                <th><?php esc_html_e( 'Scopes', 'tiktok-auto-poster' ); ?></th>
                <th><?php esc_html_e( 'Token Expires', 'tiktok-auto-poster' ); ?></th>
                <th><?php esc_html_e( 'Status', 'tiktok-auto-poster' ); ?></th>
                <th><?php esc_html_e( 'Avatar', 'tiktok-auto-poster' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $token_details ) ) : ?>
                <tr>
                    <td colspan="6"><?php esc_html_e( 'No TikTok accounts are connected yet.', 'tiktok-auto-poster' ); ?></td>
                </tr>
            <?php else : ?>
                <tr>
                    <td><?php echo esc_html( $profile_data['open_id'] ?? ( $token_details['open_id'] ?? '' ) ); ?></td>
                    <td><?php echo esc_html( $profile_data['display_name'] ?? __( 'Unknown', 'tiktok-auto-poster' ) ); ?></td>
                    <td><?php echo esc_html( $token_details['scope'] ?? '' ); ?></td>
                    <td>
                        <?php
                        if ( ! empty( $token_details['expires_at'] ) ) {
                            echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), intval( $token_details['expires_at'] ) ) );
                        } else {
                            echo esc_html_x( '—', 'placeholder for missing value', 'tiktok-auto-poster' );
                        }
                        ?>
                    </td>
                    <td>
                        <?php
                        if ( $connection_error || empty( $token_details ) ) {
                            echo esc_html( __( 'Not connected', 'tiktok-auto-poster' ) );
                        } else {
                            echo esc_html( __( 'Connected', 'tiktok-auto-poster' ) );
                        }
                        ?>
                    </td>
                    <td>
                        <?php if ( ! empty( $profile_data['avatar_url'] ) ) : ?>
                            <img src="<?php echo esc_url( $profile_data['avatar_url'] ); ?>" alt="" style="width:48px;height:48px;border-radius:24px;" />
                        <?php else : ?>
                            &mdash;
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
