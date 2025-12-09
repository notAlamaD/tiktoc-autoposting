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
     * Upload media file.
     *
     * @param string $file_path Path or URL.
     * @param string $type video|image
     * @return array|WP_Error
     */
    public function upload_media( $file_path, $type = 'video' ) {
        $endpoint = 'video/upload/';

        if ( 'image' === $type ) {
            $endpoint = 'image/upload/';
        }

        $body = array();

        if ( file_exists( $file_path ) ) {
            $body['file'] = file_get_contents( $file_path );
            $filename     = basename( $file_path );
        } else {
            $body['file'] = wp_remote_retrieve_body( wp_remote_get( $file_path ) );
            $filename     = basename( wp_parse_url( $file_path, PHP_URL_PATH ) );
        }

        if ( empty( $body['file'] ) ) {
            return new WP_Error( 'missing_media', __( 'Media file is empty.', 'tiktok-auto-poster' ) );
        }

        $request = array(
            'headers' => $this->auth_headers(),
            'timeout' => 60,
            'body'    => array(
                'video' => array(
                    'file'     => $body['file'],
                    'filename' => $filename,
                ),
            ),
        );

        $result = wp_remote_post( self::API_BASE . $endpoint, $request );

        return $this->parse_response(
            $result,
            array(
                'endpoint' => self::API_BASE . $endpoint,
                'method'   => 'POST',
                'request'  => array(
                    'filename' => $filename ?? basename( $file_path ),
                    'type'     => $type,
                ),
            )
        );
    }

    /**
     * Create TikTok post with uploaded media.
     *
     * @param string $media_id Media id returned by upload.
     * @param string $description Caption text.
     * @return array|WP_Error
     */
    public function create_post( $media_id, $description ) {
        $request = array(
            'headers' => $this->auth_headers(),
            'timeout' => 30,
            'body'    => wp_json_encode(
                array(
                    'post_info' => array(
                        'caption' => $description,
                    ),
                    'media_id'  => $media_id,
                )
            ),
        );

        $result = wp_remote_post( self::API_BASE . 'post/publish/', $request );

        return $this->parse_response(
            $result,
            array(
                'endpoint' => self::API_BASE . 'post/publish/',
                'method'   => 'POST',
                'request'  => array(
                    'caption' => $description,
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
}
