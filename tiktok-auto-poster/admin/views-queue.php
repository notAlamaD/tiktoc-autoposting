<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$queue   = new TikTok_Queue();
$entries = $queue->get_recent( 50 );
?>
<div class="wrap">
    <h1><?php esc_html_e( 'TikTok Queue', 'tiktok-auto-poster' ); ?></h1>
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
