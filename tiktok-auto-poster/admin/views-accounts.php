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
    if ( isset( $account_result['data']['user'] ) ) {
        $profile_data = $account_result['data']['user'];
    } elseif ( isset( $account_result['data'] ) ) {
        $profile_data = $account_result['data'];
    } else {
        $profile_data = $account_result;
    }
}

?>
<div class="wrap">
    <h1><?php esc_html_e( 'Connected TikTok Account', 'tiktok-auto-poster' ); ?></h1>
    <p><?php esc_html_e( 'Manage the single TikTok profile linked to this site and review its details.', 'tiktok-auto-poster' ); ?></p>

    <style>
        .tiktok-account-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 16px;
            margin-top: 16px;
        }
        .tiktok-account-card {
            background: #fff;
            border: 1px solid #c3c4c7;
            border-radius: 6px;
            padding: 16px 18px;
            box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
        }
        .tiktok-account-card h2 {
            margin-top: 0;
        }
        .tiktok-account-row {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .tiktok-account-avatar {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            object-fit: cover;
            background: #f6f7f7;
            border: 1px solid #dcdcde;
        }
        .tiktok-account-meta dt {
            font-weight: 600;
        }
        .tiktok-account-meta dd {
            margin: 0 0 10px;
        }
    </style>

    <?php if ( isset( $_GET['connected'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'TikTok account connected successfully.', 'tiktok-auto-poster' ); ?></p></div>
    <?php endif; ?>

    <?php if ( $connection_error ) : ?>
        <div class="notice notice-warning"><p><?php echo esc_html( $connection_error->get_error_message() ); ?></p></div>
    <?php endif; ?>

    <div class="tiktok-account-grid">
        <div class="tiktok-account-card">
            <h2><?php esc_html_e( 'Connection', 'tiktok-auto-poster' ); ?></h2>
            <?php if ( empty( $token_details['access_token'] ) ) : ?>
                <p><?php esc_html_e( 'No TikTok account is connected yet. Authorize one account to enable posting.', 'tiktok-auto-poster' ); ?></p>
                <?php if ( $client_key && $client_secret ) : ?>
                    <a class="button button-primary" href="<?php echo esc_url( $auth_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Connect TikTok account', 'tiktok-auto-poster' ); ?></a>
                    <p class="description"><?php esc_html_e( 'The authorization opens in a new tab. Return here after approving access.', 'tiktok-auto-poster' ); ?></p>
                <?php else : ?>
                    <button class="button button-primary" type="button" disabled="disabled"><?php esc_html_e( 'Connect TikTok account', 'tiktok-auto-poster' ); ?></button>
                    <p class="description"><?php esc_html_e( 'Save your Client Key and Client Secret in Settings before connecting.', 'tiktok-auto-poster' ); ?></p>
                <?php endif; ?>
            <?php else : ?>
                <p><strong class="dashicons dashicons-yes-alt" aria-hidden="true"></strong> <?php esc_html_e( 'Connected to TikTok.', 'tiktok-auto-poster' ); ?></p>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:12px;">
                    <?php wp_nonce_field( 'tiktok_disconnect' ); ?>
                    <input type="hidden" name="action" value="tiktok_disconnect" />
                    <?php submit_button( __( 'Disconnect account', 'tiktok-auto-poster' ), 'delete', 'submit', false ); ?>
                </form>
                <?php if ( ! empty( $token_details['expires_at'] ) ) : ?>
                    <p class="description">
                        <?php
                        printf(
                            /* translators: %s: expiration date */
                            esc_html__( 'Access token expires on %s.', 'tiktok-auto-poster' ),
                            esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), intval( $token_details['expires_at'] ) ) )
                        );
                        ?>
                    </p>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="tiktok-account-card">
            <h2><?php esc_html_e( 'Account details', 'tiktok-auto-poster' ); ?></h2>
            <?php if ( empty( $token_details['access_token'] ) ) : ?>
                <p class="description"><?php esc_html_e( 'Details will appear here after you connect your TikTok account.', 'tiktok-auto-poster' ); ?></p>
            <?php else : ?>
                <div class="tiktok-account-row">
                    <?php if ( ! empty( $profile_data['avatar_url'] ) ) : ?>
                        <img class="tiktok-account-avatar" src="<?php echo esc_url( $profile_data['avatar_url'] ); ?>" alt="" />
                    <?php else : ?>
                        <div class="tiktok-account-avatar" aria-hidden="true"></div>
                    <?php endif; ?>
                    <div>
                        <strong><?php echo esc_html( $profile_data['display_name'] ?? __( 'Unknown user', 'tiktok-auto-poster' ) ); ?></strong><br />
                        <span class="description"><?php echo esc_html( $profile_data['open_id'] ?? ( $token_details['open_id'] ?? '' ) ); ?></span>
                    </div>
                </div>
                <dl class="tiktok-account-meta" style="margin-top:16px;">
                    <dt><?php esc_html_e( 'Scopes', 'tiktok-auto-poster' ); ?></dt>
                    <dd><?php echo esc_html( $token_details['scope'] ?? '' ); ?></dd>

                    <dt><?php esc_html_e( 'Connection status', 'tiktok-auto-poster' ); ?></dt>
                    <dd><?php echo esc_html( $connection_error ? __( 'Not connected', 'tiktok-auto-poster' ) : __( 'Connected', 'tiktok-auto-poster' ) ); ?></dd>
                </dl>
            <?php endif; ?>
        </div>
    </div>
</div>
