<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap">
    <h1><?php esc_html_e( 'TikTok Posts Status', 'tiktok-auto-poster' ); ?></h1>

    <?php if ( $publish_status ) : ?>
        <?php
        $classes = 'notice is-dismissible ' . ( 'success' === $publish_status ? 'notice-success' : 'notice-error' );
        $message = $publish_message ? $publish_message : ( 'success' === $publish_status ? __( 'Published to TikTok.', 'tiktok-auto-poster' ) : __( 'Unable to publish.', 'tiktok-auto-poster' ) );
        ?>
        <div class="<?php echo esc_attr( $classes ); ?>">
            <p><?php echo esc_html( $message ); ?></p>
        </div>
    <?php endif; ?>

    <p class="description"><?php esc_html_e( 'All WordPress posts tracked for TikTok with their publish status and latest TikTok post IDs.', 'tiktok-auto-poster' ); ?></p>

    <table class="widefat">
        <thead>
            <tr>
                <th><?php esc_html_e( 'ID', 'tiktok-auto-poster' ); ?></th>
                <th><?php esc_html_e( 'Post', 'tiktok-auto-poster' ); ?></th>
                <th><?php esc_html_e( 'Status', 'tiktok-auto-poster' ); ?></th>
                <th><?php esc_html_e( 'Attempts', 'tiktok-auto-poster' ); ?></th>
                <th><?php esc_html_e( 'TikTok Post ID', 'tiktok-auto-poster' ); ?></th>
                <th><?php esc_html_e( 'Last error', 'tiktok-auto-poster' ); ?></th>
                <th><?php esc_html_e( 'Updated', 'tiktok-auto-poster' ); ?></th>
                <th><?php esc_html_e( 'Actions', 'tiktok-auto-poster' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $posts_rows ) ) : ?>
                <tr><td colspan="8"><?php esc_html_e( 'No TikTok posts tracked yet.', 'tiktok-auto-poster' ); ?></td></tr>
            <?php else : ?>
                <?php foreach ( $posts_rows as $row ) : $post = get_post( $row['post_id'] ); ?>
                    <tr>
                        <td><?php echo esc_html( $row['id'] ); ?></td>
                        <td><?php echo $post ? '<a href="' . esc_url( get_edit_post_link( $post ) ) . '">' . esc_html( get_the_title( $post ) ) . '</a>' : esc_html__( 'Unknown', 'tiktok-auto-poster' ); ?></td>
                        <td><?php echo esc_html( $row['status'] ); ?></td>
                        <td><?php echo esc_html( $row['attempts'] ); ?></td>
                        <td><?php echo esc_html( $row['tiktok_post_id'] ); ?></td>
                        <td title="<?php echo esc_attr( $row['last_error'] ); ?>"><?php echo esc_html( wp_html_excerpt( $row['last_error'], 100 ) ); ?></td>
                        <td><?php echo esc_html( $row['updated_at'] ); ?></td>
                        <td>
                            <?php if ( in_array( $row['status'], array( 'pending', 'error' ), true ) ) : ?>
                                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block">
                                    <?php wp_nonce_field( 'tiktok_publish_post_' . $row['id'] ); ?>
                                    <input type="hidden" name="action" value="tiktok_publish_post" />
                                    <input type="hidden" name="record_id" value="<?php echo esc_attr( $row['id'] ); ?>" />
                                    <?php submit_button( __( 'Publish now', 'tiktok-auto-poster' ), 'primary small', 'submit', false ); ?>
                                </form>
                            <?php else : ?>
                                <span class="dashicons dashicons-yes"></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
