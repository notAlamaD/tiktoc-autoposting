<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$post_types = get_post_types( array( 'public' => true ), 'objects' );
$statuses   = array(
    'publish' => __( 'Publish', 'tiktok-auto-poster' ),
    'draft'   => __( 'Draft', 'tiktok-auto-poster' ),
);
?>
<div class="wrap">
    <h1><?php esc_html_e( 'TikTok Auto Poster Settings', 'tiktok-auto-poster' ); ?></h1>

    <?php settings_errors( 'tiktok_auto_poster' ); ?>

    <?php if ( isset( $_GET['connected'] ) ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e( 'TikTok account connected successfully.', 'tiktok-auto-poster' ); ?></p>
        </div>
    <?php endif; ?>

    <?php
    $token_plain   = isset( $settings['token'] ) ? tiktok_auto_poster_decrypt( $settings['token'] ) : '';
    $token_data    = $token_plain ? json_decode( $token_plain, true ) : array();
    $client_key    = $settings['client_key'] ?? '';
    $client_secret = $settings['client_secret'] ?? '';
    $state         = wp_create_nonce( 'tiktok_oauth_state' );
    $redirect      = trailingslashit( home_url( 'tiktok-oauth-callback' ) );
    $auth_url      = add_query_arg(
        array(
            'client_key'    => $client_key,
            'scope'         => 'video.upload,video.publish,user.info.basic',
            'response_type' => 'code',
            'redirect_uri'  => $redirect,
            'state'         => $state,
        ),
        'https://www.tiktok.com/v2/auth/authorize/'
    );
    ?>

    <h2><?php esc_html_e( 'TikTok account', 'tiktok-auto-poster' ); ?></h2>
    <table class="form-table" role="presentation">
        <tr>
            <th scope="row"><?php esc_html_e( 'Connection status', 'tiktok-auto-poster' ); ?></th>
            <td>
                <?php if ( ! empty( $token_data['access_token'] ) ) : ?>
                    <p><?php esc_html_e( 'Connected to TikTok.', 'tiktok-auto-poster' ); ?></p>
                    <?php if ( ! empty( $token_data['open_id'] ) ) : ?>
                        <p><strong><?php esc_html_e( 'User ID:', 'tiktok-auto-poster' ); ?></strong> <?php echo esc_html( $token_data['open_id'] ); ?></p>
                    <?php endif; ?>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <?php wp_nonce_field( 'tiktok_disconnect' ); ?>
                        <input type="hidden" name="action" value="tiktok_disconnect" />
                        <?php submit_button( __( 'Disconnect', 'tiktok-auto-poster' ), 'delete', 'submit', false ); ?>
                    </form>
                <?php else : ?>
                    <p><?php esc_html_e( 'Not connected yet.', 'tiktok-auto-poster' ); ?></p>
                    <?php if ( $client_key && $client_secret ) : ?>
                        <a class="button button-primary" href="<?php echo esc_url( $auth_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Connect TikTok account', 'tiktok-auto-poster' ); ?></a>
                        <p class="description"><?php esc_html_e( 'Click Connect to open the TikTok authorization window in a new tab.', 'tiktok-auto-poster' ); ?></p>
                    <?php else : ?>
                        <button class="button button-primary" type="button" disabled="disabled"><?php esc_html_e( 'Connect TikTok account', 'tiktok-auto-poster' ); ?></button>
                        <p class="description"><?php esc_html_e( 'Save your Client Key and Client Secret first, then use the Connect button to launch TikTok authorization.', 'tiktok-auto-poster' ); ?></p>
                    <?php endif; ?>
                <?php endif; ?>
            </td>
        </tr>
    </table>

    <form method="post" action="options.php">
        <?php settings_fields( 'tiktok-auto-poster' ); ?>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><?php esc_html_e( 'Client Key', 'tiktok-auto-poster' ); ?></th>
                <td><input type="text" name="tiktok_auto_poster_settings[client_key]" value="<?php echo esc_attr( $settings['client_key'] ?? '' ); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Client Secret', 'tiktok-auto-poster' ); ?></th>
                <td><input type="password" name="tiktok_auto_poster_settings[client_secret]" value="<?php echo esc_attr( $settings['client_secret'] ?? '' ); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Redirect URI', 'tiktok-auto-poster' ); ?></th>
                <td><code><?php echo esc_html( trailingslashit( home_url( 'tiktok-oauth-callback' ) ) ); ?></code></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Auto posting enabled', 'tiktok-auto-poster' ); ?></th>
                <td><input type="checkbox" name="tiktok_auto_poster_settings[auto_post_enabled]" value="1" <?php checked( $settings['auto_post_enabled'] ?? 0, 1 ); ?> /></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Post types', 'tiktok-auto-poster' ); ?></th>
                <td>
                    <?php foreach ( $post_types as $type ) : ?>
                        <label><input type="checkbox" name="tiktok_auto_poster_settings[post_types][]" value="<?php echo esc_attr( $type->name ); ?>" <?php checked( in_array( $type->name, $settings['post_types'] ?? array(), true ) ); ?> /> <?php echo esc_html( $type->labels->singular_name ); ?></label><br/>
                    <?php endforeach; ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Trigger statuses', 'tiktok-auto-poster' ); ?></th>
                <td>
                    <?php foreach ( $statuses as $key => $label ) : ?>
                        <label><input type="checkbox" name="tiktok_auto_poster_settings[statuses][]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $settings['statuses'] ?? array(), true ) ); ?> /> <?php echo esc_html( $label ); ?></label><br/>
                    <?php endforeach; ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Media source', 'tiktok-auto-poster' ); ?></th>
                <td>
                    <select name="tiktok_auto_poster_settings[media_source]">
                        <option value="featured" <?php selected( $settings['media_source'] ?? '', 'featured' ); ?>><?php esc_html_e( 'Featured image', 'tiktok-auto-poster' ); ?></option>
                        <option value="custom_field" <?php selected( $settings['media_source'] ?? '', 'custom_field' ); ?>><?php esc_html_e( 'Custom field', 'tiktok-auto-poster' ); ?></option>
                        <option value="attachment" <?php selected( $settings['media_source'] ?? '', 'attachment' ); ?>><?php esc_html_e( 'Attachment', 'tiktok-auto-poster' ); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Description template', 'tiktok-auto-poster' ); ?></th>
                <td>
                    <textarea name="tiktok_auto_poster_settings[description]" rows="5" class="large-text"><?php echo esc_textarea( $settings['description'] ?? '' ); ?></textarea>
                    <p class="description"><?php esc_html_e( 'Available tags: {post_title}, {excerpt}, {post_url}, {date}, {category}', 'tiktok-auto-poster' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Use queue instead of immediate posting', 'tiktok-auto-poster' ); ?></th>
                <td><input type="checkbox" name="tiktok_auto_poster_settings[queue_enabled]" value="1" <?php checked( $settings['queue_enabled'] ?? 0, 1 ); ?> /></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'CRON interval (minutes)', 'tiktok-auto-poster' ); ?></th>
                <td>
                    <select name="tiktok_auto_poster_settings[cron_interval]">
                        <?php foreach ( array( 5, 15, 30, 60 ) as $interval ) : ?>
                            <option value="<?php echo esc_attr( $interval ); ?>" <?php selected( (int) ( $settings['cron_interval'] ?? 15 ), $interval ); ?>><?php echo esc_html( $interval ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Enable API logging', 'tiktok-auto-poster' ); ?></th>
                <td><input type="checkbox" name="tiktok_auto_poster_settings[enable_logging]" value="1" <?php checked( $settings['enable_logging'] ?? 0, 1 ); ?> /></td>
            </tr>
        </table>

        <?php submit_button(); ?>
    </form>
</div>
