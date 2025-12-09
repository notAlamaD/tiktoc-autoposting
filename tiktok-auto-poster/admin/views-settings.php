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

    <p>
        <?php
        printf(
            '<a href="%1$s">%2$s</a>',
            esc_url( admin_url( 'admin.php?page=tiktok-auto-poster-accounts' ) ),
            esc_html__( 'Manage your TikTok connection and account details from the Connected Accounts page.', 'tiktok-auto-poster' )
        );
        ?>
    </p>

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
                <th scope="row"><?php esc_html_e( 'TikTok post mode', 'tiktok-auto-poster' ); ?></th>
                <td>
                    <select name="tiktok_auto_poster_settings[post_mode]">
                        <option value="DIRECT_POST" <?php selected( $settings['post_mode'] ?? 'DIRECT_POST', 'DIRECT_POST' ); ?>><?php esc_html_e( 'Direct post (auto publish)', 'tiktok-auto-poster' ); ?></option>
                        <option value="MEDIA_UPLOAD" <?php selected( $settings['post_mode'] ?? 'DIRECT_POST', 'MEDIA_UPLOAD' ); ?>><?php esc_html_e( 'Inbox/Draft (confirm in TikTok app)', 'tiktok-auto-poster' ); ?></option>
                    </select>
                    <p class="description"><?php esc_html_e( 'DIRECT_POST publishes automatically after TikTok processing. MEDIA_UPLOAD sends to inbox/drafts for manual confirmation.', 'tiktok-auto-poster' ); ?></p>
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
