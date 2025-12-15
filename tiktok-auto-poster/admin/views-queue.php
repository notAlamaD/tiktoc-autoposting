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
$queue_status   = isset( $_GET['queue_status'] ) ? sanitize_text_field( wp_unslash( $_GET['queue_status'] ) ) : '';
$queue_message  = isset( $_GET['queue_message'] ) ? sanitize_text_field( wp_unslash( rawurldecode( $_GET['queue_message'] ) ) ) : '';
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

    <?php if ( $queue_status ) : ?>
        <?php
        $classes = 'notice is-dismissible ' . ( 'deleted' === $queue_status ? 'notice-success' : 'notice-error' );
        $message = $queue_message ? $queue_message : ( 'deleted' === $queue_status ? __( 'Queue item deleted.', 'tiktok-auto-poster' ) : __( 'Unable to update queue.', 'tiktok-auto-poster' ) );
        ?>
        <div class="<?php echo esc_attr( $classes ); ?>">
            <p><?php echo esc_html( $message ); ?></p>
        </div>
    <?php endif; ?>

    <?php if ( $creator_error ) : ?>
        <div class="notice notice-warning"><p><?php echo esc_html( $creator_error->get_error_message() ); ?></p></div>
    <?php endif; ?>

    <div class="card tiktok-manual-card">
        <h2><?php esc_html_e( 'Posting account', 'tiktok-auto-poster' ); ?></h2>
        <?php if ( empty( $creator_detail ) ) : ?>
            <p class="description"><?php esc_html_e( 'Connect your TikTok account to see creator availability and upload limits.', 'tiktok-auto-poster' ); ?></p>
        <?php else : ?>
            <ul>
                <li><strong><?php esc_html_e( 'Nickname:', 'tiktok-auto-poster' ); ?></strong> <?php echo esc_html( $creator_detail['nickname'] ?? $creator_detail['display_name'] ?? __( 'Unknown', 'tiktok-auto-poster' ) ); ?></li>
                <?php if ( isset( $creator_detail['can_post'] ) || isset( $creator_detail['can_post_more'] ) ) : ?>
                    <li><strong><?php esc_html_e( 'Posting availability:', 'tiktok-auto-poster' ); ?></strong> <?php echo ( isset( $creator_detail['can_post'] ) ? ( $creator_detail['can_post'] ? esc_html__( 'Ready to publish', 'tiktok-auto-poster' ) : esc_html__( 'Cannot publish now', 'tiktok-auto-poster' ) ) : ( $creator_detail['can_post_more'] ? esc_html__( 'Ready to publish', 'tiktok-auto-poster' ) : esc_html__( 'Cannot publish now', 'tiktok-auto-poster' ) ) ); ?></li>
                <?php endif; ?>
                <?php if ( ! empty( $creator_detail['max_video_post_duration_sec'] ) ) : ?>
                    <li><strong><?php esc_html_e( 'Max video duration:', 'tiktok-auto-poster' ); ?></strong> <?php echo esc_html( $creator_detail['max_video_post_duration_sec'] ); ?> <?php esc_html_e( 'seconds', 'tiktok-auto-poster' ); ?></li>
                <?php endif; ?>
            </ul>
            <p class="description"><?php esc_html_e( 'Creator info is pulled from TikTok each time you open this page so the publish form reflects the latest limits.', 'tiktok-auto-poster' ); ?></p>
        <?php endif; ?>
    </div>

    <div class="card tiktok-manual-card">
        <h2><?php esc_html_e( 'Manual posting', 'tiktok-auto-poster' ); ?></h2>
        <p class="description"><?php esc_html_e( 'Pick a recent post or provide an ID to push it into the TikTok queue. Use the immediate send option to process right away.', 'tiktok-auto-poster' ); ?></p>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'tiktok_manual_queue' ); ?>
            <input type="hidden" name="action" value="tiktok_manual_queue" />

            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row"><label for="queued_post"><?php esc_html_e( 'Choose a recent post', 'tiktok-auto-poster' ); ?></label></th>
                        <td>
                            <select id="queued_post" name="queued_post" class="regular-text">
                                <option value=""><?php esc_html_e( 'Select a post…', 'tiktok-auto-poster' ); ?></option>
                                <?php foreach ( $recent as $post_item ) : ?>
                                    <option value="<?php echo esc_attr( $post_item->ID ); ?>"><?php echo esc_html( $post_item->post_title . ' (ID: ' . $post_item->ID . ')' ); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e( 'Recently published posts appear here for quick selection.', 'tiktok-auto-poster' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="manual_post_id"><?php esc_html_e( '…or enter a Post ID', 'tiktok-auto-poster' ); ?></label></th>
                        <td>
                            <input type="number" id="manual_post_id" name="manual_post_id" class="regular-text" placeholder="123" />
                            <p class="description"><?php esc_html_e( 'Useful if the post is older or not listed above.', 'tiktok-auto-poster' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Send timing', 'tiktok-auto-poster' ); ?></th>
                        <td>
                            <label><input type="checkbox" name="send_now" value="1" /> <?php esc_html_e( 'Send immediately (process now instead of waiting for cron)', 'tiktok-auto-poster' ); ?></label>
                        </td>
                    </tr>
                </tbody>
            </table>

            <?php submit_button( __( 'Add to TikTok queue', 'tiktok-auto-poster' ) ); ?>
        </form>
    </div>

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
                <th><?php esc_html_e( 'Actions', 'tiktok-auto-poster' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $entries ) ) : ?>
                <tr><td colspan="9"><?php esc_html_e( 'Queue is empty.', 'tiktok-auto-poster' ); ?></td></tr>
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
                        <td>
                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Remove this item from the queue?', 'tiktok-auto-poster' ) ); ?>');">
                                <?php wp_nonce_field( 'tiktok_delete_queue_' . $entry['id'] ); ?>
                                <input type="hidden" name="action" value="tiktok_delete_queue" />
                                <input type="hidden" name="queue_id" value="<?php echo esc_attr( $entry['id'] ); ?>" />
                                <?php submit_button( __( 'Delete', 'tiktok-auto-poster' ), 'delete small', 'submit', false ); ?>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
