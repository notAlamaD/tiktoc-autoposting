<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$queue   = new TikTok_Queue();
$entries = $queue->get_recent( 50 );
$recent  = get_posts(
    array(
        'numberposts' => 20,
        'post_status' => 'publish',
        'post_type'   => array_keys( get_post_types( array( 'public' => true ) ) ),
    )
);

$manual_status  = isset( $_GET['manual_status'] ) ? sanitize_text_field( wp_unslash( $_GET['manual_status'] ) ) : '';
$manual_message = isset( $_GET['manual_message'] ) ? sanitize_text_field( wp_unslash( rawurldecode( $_GET['manual_message'] ) ) ) : '';
?>
<div class="wrap">
    <h1><?php esc_html_e( 'TikTok Queue', 'tiktok-auto-poster' ); ?></h1>

    <?php if ( $manual_status ) : ?>
        <?php
        $classes = 'notice is-dismissible ' . ( 'success' === $manual_status ? 'notice-success' : ( 'queued' === $manual_status || 'processing' === $manual_status ? 'notice-info' : 'notice-error' ) );
        $message = $manual_message ? $manual_message : ( 'success' === $manual_status ? __( 'Post sent to TikTok.', 'tiktok-auto-poster' ) : ( 'queued' === $manual_status ? __( 'Post added to queue.', 'tiktok-auto-poster' ) : __( 'Manual send completed with status: ', 'tiktok-auto-poster' ) . $manual_status ) );
        ?>
        <div class="<?php echo esc_attr( $classes ); ?>">
            <p><?php echo esc_html( $message ); ?></p>
        </div>
    <?php endif; ?>

    <h2><?php esc_html_e( 'Manual posting', 'tiktok-auto-poster' ); ?></h2>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( 'tiktok_manual_queue' ); ?>
        <input type="hidden" name="action" value="tiktok_manual_queue" />

        <p>
            <label for="queued_post"><strong><?php esc_html_e( 'Choose a recent post', 'tiktok-auto-poster' ); ?></strong></label><br />
            <select id="queued_post" name="queued_post">
                <option value=""><?php esc_html_e( 'Select a post…', 'tiktok-auto-poster' ); ?></option>
                <?php foreach ( $recent as $post_item ) : ?>
                    <option value="<?php echo esc_attr( $post_item->ID ); ?>"><?php echo esc_html( $post_item->post_title . ' (ID: ' . $post_item->ID . ')' ); ?></option>
                <?php endforeach; ?>
            </select>
        </p>

        <p>
            <label for="manual_post_id"><strong><?php esc_html_e( '…or enter a Post ID', 'tiktok-auto-poster' ); ?></strong></label><br />
            <input type="number" id="manual_post_id" name="manual_post_id" class="regular-text" />
        </p>

        <p>
            <label><input type="checkbox" name="send_now" value="1" /> <?php esc_html_e( 'Send immediately (process now instead of waiting for cron)', 'tiktok-auto-poster' ); ?></label>
        </p>

        <?php submit_button( __( 'Add to TikTok queue', 'tiktok-auto-poster' ), 'primary', 'submit', false ); ?>
    </form>

    <table class="widefat">
        <thead>
            <tr>
                <th><?php esc_html_e( 'ID', 'tiktok-auto-poster' ); ?></th>
                <th><?php esc_html_e( 'Post', 'tiktok-auto-poster' ); ?></th>
                <th><?php esc_html_e( 'Status', 'tiktok-auto-poster' ); ?></th>
                <th><?php esc_html_e( 'Attempts', 'tiktok-auto-poster' ); ?></th>
                <th><?php esc_html_e( 'TikTok Post ID', 'tiktok-auto-poster' ); ?></th>
                <th><?php esc_html_e( 'Last error', 'tiktok-auto-poster' ); ?></th>
                <th><?php esc_html_e( 'Created', 'tiktok-auto-poster' ); ?></th>
                <th><?php esc_html_e( 'Updated', 'tiktok-auto-poster' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $entries ) ) : ?>
                <tr><td colspan="8"><?php esc_html_e( 'Queue is empty.', 'tiktok-auto-poster' ); ?></td></tr>
            <?php else : ?>
                <?php foreach ( $entries as $entry ) : $post = get_post( $entry['post_id'] ); ?>
                    <tr>
                        <td><?php echo esc_html( $entry['id'] ); ?></td>
                        <td><?php echo $post ? '<a href="' . esc_url( get_edit_post_link( $post ) ) . '">' . esc_html( get_the_title( $post ) ) . '</a>' : esc_html__( 'Unknown', 'tiktok-auto-poster' ); ?></td>
                        <td><?php echo esc_html( $entry['status'] ); ?></td>
                        <td><?php echo esc_html( $entry['attempts'] ); ?></td>
                        <td><?php echo esc_html( $entry['tiktok_post_id'] ); ?></td>
                        <td title="<?php echo esc_attr( $entry['last_error'] ); ?>"><?php echo esc_html( wp_html_excerpt( $entry['last_error'], 100 ) ); ?></td>
                        <td><?php echo esc_html( $entry['created_at'] ); ?></td>
                        <td><?php echo esc_html( $entry['updated_at'] ); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
