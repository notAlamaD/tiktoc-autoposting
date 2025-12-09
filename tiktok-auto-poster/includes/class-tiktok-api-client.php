<?php
/**
 * TikTok API client wrapper.
 *
 * @package TikTokAutoPoster
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TikTok_Api_Client {
    const API_BASE = 'https://open.tiktokapis.com/v2/';

    /**
     * Retrieve information about the connected TikTok user.
     *
     * @return array|WP_Error
     */
    public function get_user_info() {
        $endpoint = add_query_arg(
            array(
                'fields' => 'open_id,display_name,avatar_url,bio_description',
            ),
            self::API_BASE . 'user/info/'
        );

        $response = wp_remote_get(
            $endpoint,
            array(
                'headers' => $this->auth_headers(),
                'timeout' => 20,
            )
        );

        return $this->parse_response(
            $response,
            array(
                'endpoint' => $endpoint,
                'method'   => 'GET',
            )
        );
    }

    /**
     * Retrieve stored token data.
     *
     * @return array
     */
    protected function get_token() {
        $encrypted = tiktok_auto_poster_get_option( 'token' );
        $token     = $encrypted ? tiktok_auto_poster_decrypt( $encrypted ) : '';

        return $token ? json_decode( $token, true ) : array();
    }

    /**
     * Persist token data.
     *
     * @param array $token Token array.
     */
    protected function set_token( $token ) {
        tiktok_auto_poster_set_option( 'token', tiktok_auto_poster_encrypt( wp_json_encode( $token ) ) );
    }

    /**
     * Determine if token is expired.
     *
     * @return bool
     */
    public function is_token_expired() {
        $token = $this->get_token();
        $now   = time();

        return empty( $token['access_token'] ) || empty( $token['expires_at'] ) || $token['expires_at'] <= $now;
    }

    /**
     * Refresh access token using stored refresh token.
     *
     * @return array|WP_Error
     */
    public function refresh_token() {
        $token = $this->get_token();

        if ( empty( $token['refresh_token'] ) ) {
            return new WP_Error( 'missing_refresh_token', __( 'Refresh token missing.', 'tiktok-auto-poster' ) );
        }

        $client_key    = tiktok_auto_poster_get_option( 'client_key' );
        $client_secret = tiktok_auto_poster_get_option( 'client_secret' );

        $payload = array(
            'client_key'    => $client_key,
            'client_secret' => $client_secret,
            'grant_type'    => 'refresh_token',
            'refresh_token' => $token['refresh_token'],
        );

        $response = wp_remote_post(
            'https://open.tiktokapis.com/v2/oauth/token/',
            array(
                'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
                'body'    => $payload,
                'timeout' => 30,
            )
        );

        $data = $this->parse_response(
            $response,
            array(
                'endpoint' => 'https://open.tiktokapis.com/v2/oauth/token/',
                'method'   => 'POST',
                'request'  => $payload,
            )
        );

        if ( is_wp_error( $data ) ) {
            return $data;
        }

        $token['access_token']  = $data['access_token'];
        $token['refresh_token'] = isset( $data['refresh_token'] ) ? $data['refresh_token'] : $token['refresh_token'];
        $token['expires_at']    = time() + absint( $data['expires_in'] );

        $this->set_token( $token );

        return $token;
    }

    /**
     * Publish content using TikTok Content Posting API via pull-from-URL.
     *
     * @param WP_Post $post Post being published.
     * @param string  $file_path File path or URL to media.
     * @param string  $description Caption/description template result.
     * @param string  $post_mode   TikTok post mode (DIRECT_POST or MEDIA_UPLOAD).
     * @param string  $privacy_level TikTok privacy level for DIRECT_POST mode.
     * @return array|WP_Error
     */
    public function publish_content( $post, $file_path, $description, $post_mode = 'DIRECT_POST', $privacy_level = 'PUBLIC_TO_EVERYONE' ) {
        $file_url = tiktok_auto_poster_get_media_url( $file_path );

        if ( ! $file_url ) {
            return new WP_Error( 'media_url_missing', __( 'Could not resolve a public media URL for TikTok.', 'tiktok-auto-poster' ) );
        }

        $media_type = $this->detect_media_type( $file_path );

        $secure_file_url = set_url_scheme( $file_url, 'https' );

        if ( ! in_array( $post_mode, array( 'DIRECT_POST', 'MEDIA_UPLOAD' ), true ) ) {
            $post_mode = 'DIRECT_POST';
        }

        if ( 'PHOTO' === $media_type ) {
            $source_info = array(
                'source'             => 'PULL_FROM_URL',
                'photo_images'       => array( $secure_file_url ),
                'photo_cover_index'  => 0,
            );
            $endpoint    = self::API_BASE . 'post/publish/content/init/';
        } else {
            $source_info = array(
                'source'    => 'PULL_FROM_URL',
                'video_url' => $secure_file_url,
            );
            $media_type  = 'VIDEO';
            $endpoint    = self::API_BASE . 'post/publish/video/init/';
        }

        $privacy = strtoupper( preg_replace( '/[^A-Z_]/', '', $privacy_level ) );

        $payload = array(
            'post_info'  => array(
                'title'         => wp_html_excerpt( get_the_title( $post ), 80 ),
                'description'   => $description,
            ),
            'source_info' => $source_info,
            'post_mode'   => $post_mode,
            'media_type'  => $media_type,
        );

        if ( 'DIRECT_POST' === $post_mode && $privacy ) {
            $payload['post_info']['privacy_level'] = $privacy;
        }

        $result = wp_remote_post(
            $endpoint,
            array(
                'headers' => $this->auth_headers(),
                'timeout' => 45,
                'body'    => wp_json_encode( $payload ),
            )
        );

        return $this->parse_response(
            $result,
            array(
                'endpoint' => $endpoint,
                'method'   => 'POST',
                'request'  => array(
                    'post_info'  => $payload['post_info'],
                    'source_info' => $source_info,
                    'post_mode'  => $post_mode,
                    'media_type' => $media_type,
                ),
            )
        );
    }

    /**
     * Parse HTTP response.
     *
     * @param WP_Error|array $response Response data.
     * @return array|WP_Error
     */
    protected function parse_response( $response, $context = array() ) {
        $this->maybe_log_request( $response, $context );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( 401 === $code && ! empty( $body['message'] ) && false !== strpos( $body['message'], 'expired' ) ) {
            $refreshed = $this->refresh_token();
            if ( ! is_wp_error( $refreshed ) ) {
                return new WP_Error( 'retry', __( 'Token refreshed. Please retry request.', 'tiktok-auto-poster' ) );
            }
        }

        if ( $code < 200 || $code >= 300 ) {
            return new WP_Error( 'tiktok_api_error', $body['message'] ?? __( 'Unknown API error', 'tiktok-auto-poster' ), $body );
        }

        return $body;
    }

    /**
     * Build headers for authorized calls.
     *
     * @return array
     */
    protected function auth_headers() {
        $token = $this->get_token();

        return array(
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . ( $token['access_token'] ?? '' ),
        );
    }

    /**
     * Optionally log requests.
     *
     * @param array $response Response array.
     */
    protected function maybe_log_request( $response, $context ) {
        tiktok_auto_poster_log_request( $context, $response );
    }

    /**
     * Determine media type for request payload.
     *
     * @param string $file_path Path or URL.
     * @return string PHOTO|VIDEO
     */
    protected function detect_media_type( $file_path ) {
        $extension = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
        $image_ext = array( 'jpg', 'jpeg', 'png', 'webp', 'gif' );

        if ( in_array( $extension, $image_ext, true ) ) {
            return 'PHOTO';
        }

        return 'VIDEO';
    }
}
