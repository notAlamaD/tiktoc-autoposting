<?php
/**
 * Helper functions for TikTok Auto Poster.
 *
 * @package TikTokAutoPoster
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Encrypt data for storage.
 *
 * @param string $data Raw data.
 * @return string
 */
function tiktok_auto_poster_encrypt( $data ) {
    $key  = TIKTOK_AUTO_POSTER_SECRET;
    $iv   = substr( hash( 'sha256', $key ), 0, 16 );
    $data = is_string( $data ) ? $data : wp_json_encode( $data );

    return base64_encode( openssl_encrypt( $data, 'AES-256-CBC', $key, 0, $iv ) );
}

/**
 * Decrypt stored data.
 *
 * @param string $data Cipher text.
 * @return string|null
 */
function tiktok_auto_poster_decrypt( $data ) {
    $key = TIKTOK_AUTO_POSTER_SECRET;
    $iv  = substr( hash( 'sha256', $key ), 0, 16 );

    $decoded = base64_decode( (string) $data );

    if ( false === $decoded ) {
        return null;
    }

    $plain = openssl_decrypt( $decoded, 'AES-256-CBC', $key, 0, $iv );

    return $plain ?: null;
}

/**
 * Retrieve plugin option with default.
 *
 * @param string $key Option key.
 * @param mixed  $default Default value.
 *
 * @return mixed
 */
function tiktok_auto_poster_get_option( $key, $default = null ) {
    $options = get_option( 'tiktok_auto_poster_settings', array() );

    return isset( $options[ $key ] ) ? $options[ $key ] : $default;
}

/**
 * Save plugin option.
 *
 * @param string $key Option key.
 * @param mixed  $value Option value.
 */
function tiktok_auto_poster_set_option( $key, $value ) {
    $options         = get_option( 'tiktok_auto_poster_settings', array() );
    $options[ $key ] = $value;
    update_option( 'tiktok_auto_poster_settings', $options );
}

/**
 * Record a TikTok API log entry when logging is enabled.
 *
 * @param array            $context  Request context (endpoint, method, request payload).
 * @param WP_Error|array   $response Response object or WP_Error.
 */
function tiktok_auto_poster_log_request( $context, $response ) {
    if ( ! tiktok_auto_poster_get_option( 'enable_logging' ) ) {
        return;
    }

    $logs = get_option( 'tiktok_auto_poster_logs', array() );

    $logs[] = array(
        'date'           => current_time( 'mysql' ),
        'endpoint'       => $context['endpoint'] ?? '',
        'method'         => $context['method'] ?? '',
        'request'        => isset( $context['request'] ) ? tiktok_auto_poster_truncate_log( $context['request'] ) : null,
        'response_code'  => is_wp_error( $response ) ? null : wp_remote_retrieve_response_code( $response ),
        'response_body'  => is_wp_error( $response ) ? $response->get_error_message() : tiktok_auto_poster_truncate_log( wp_remote_retrieve_body( $response ) ),
    );

    if ( count( $logs ) > 50 ) {
        $logs = array_slice( $logs, -50 );
    }

    update_option( 'tiktok_auto_poster_logs', $logs );
}

/**
 * Retrieve stored TikTok API logs.
 *
 * @return array
 */
function tiktok_auto_poster_get_logs() {
    return get_option( 'tiktok_auto_poster_logs', array() );
}

/**
 * Safely trim log data for storage/display.
 *
 * @param mixed $data Arbitrary data.
 * @return string
 */
function tiktok_auto_poster_truncate_log( $data ) {
    $encoded = is_string( $data ) ? $data : wp_json_encode( $data );

    if ( strlen( $encoded ) > 1000 ) {
        return substr( $encoded, 0, 1000 ) . '…';
    }

    return $encoded;
}

/**
 * Map post object into TikTok description based on template.
 *
 * @param WP_Post $post Post object.
 * @param string  $template Template string.
 * @return string
 */
function tiktok_auto_poster_format_description( $post, $template ) {
    $replacements = array(
        '{post_title}' => get_the_title( $post ),
        '{excerpt}'    => wp_trim_words( $post->post_content, 30 ),
        '{post_url}'   => get_permalink( $post ),
        '{date}'       => get_the_date( '', $post ),
        '{category}'   => tiktok_auto_poster_get_primary_category( $post ),
    );

    $description = str_replace( array_keys( $replacements ), array_values( $replacements ), $template );

    /**
     * Filter description before sending to TikTok.
     *
     * @param string $description Description text.
     * @param WP_Post $post Post object.
     */
    return apply_filters( 'tiktok_poster_description', $description, $post );
}

/**
 * Retrieve primary category name.
 *
 * @param WP_Post $post Post object.
 * @return string
 */
function tiktok_auto_poster_get_primary_category( $post ) {
    $categories = get_the_category( $post );

    if ( empty( $categories ) || is_wp_error( $categories ) ) {
        return '';
    }

    return $categories[0]->name;
}

/**
 * Retrieve media file path or URL to send to TikTok.
 *
 * @param WP_Post $post Post object.
 * @param string  $source Source setting.
 * @return string|null
 */
function tiktok_auto_poster_get_media_file( $post, $source ) {
    $file_path = null;

    if ( 'featured' === $source ) {
        $thumbnail_id = get_post_thumbnail_id( $post );
        if ( $thumbnail_id ) {
            $file_path = get_attached_file( $thumbnail_id );
        }
    } elseif ( 'custom_field' === $source ) {
        $field = get_post_meta( $post->ID, 'tiktok_video_url', true );
        if ( $field ) {
            $file_path = $field;
        }
    } elseif ( 'attachment' === $source ) {
        $attachments = get_attached_media( '', $post );
        if ( ! empty( $attachments ) ) {
            $attachment = reset( $attachments );
            $file_path  = get_attached_file( $attachment->ID );
        }
    }

    /**
     * Filter media file path before sending to TikTok.
     *
     * @param string|null $file_path Media path or URL.
     * @param WP_Post $post Post object.
     */
    return apply_filters( 'tiktok_poster_media_file', $file_path, $post );
}

/**
 * Resolve a media URL from a file path or URL for TikTok Content Posting API.
 *
 * @param string $file_path Media path or URL.
 * @return string
 */
function tiktok_auto_poster_get_media_url( $file_path ) {
    if ( empty( $file_path ) ) {
        return '';
    }

    if ( filter_var( $file_path, FILTER_VALIDATE_URL ) ) {
        return $file_path;
    }

    $uploads   = wp_get_upload_dir();
    $normalized_path = wp_normalize_path( $file_path );
    $base_dir  = wp_normalize_path( trailingslashit( $uploads['basedir'] ) );

    if ( 0 === strpos( $normalized_path, $base_dir ) ) {
        $relative = ltrim( substr( $normalized_path, strlen( $base_dir ) ), '/' );

        return trailingslashit( $uploads['baseurl'] ) . $relative;
    }

    return '';
}
