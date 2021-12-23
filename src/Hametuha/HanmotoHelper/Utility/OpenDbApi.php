<?php

namespace Hametuha\HanmotoHelper\Utility;


/**
 * Open DB API
 *
 * @package hanmoto
 */
trait OpenDbApi {

	/**
	 * Get endpoint path.
	 *
	 * @param string $endpoint Get endpoint.
	 *
	 * @return string
	 */
	protected function openbd_url( $endpoint ) {
		return untrailingslashit( 'https://api.openbd.jp/v1/' . ltrim( $endpoint, '/' ) );
	}

	/**
	 * Get detail from Open DB.
	 *
	 * @param string|string[] $isbn ISBN or array of ISBN.
	 * @return \WP_Error|array
	 */
	public function openbd_get( $isbn ) {
		$isbn   = (array) $isbn;
		$url    = add_query_arg( [
			'isbn' => implode( ',', array_map( 'trim', $isbn ) ),
		], $this->openbd_url( 'get' ) );
		$result = wp_remote_get( $url );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return json_decode( $result['body'], true ) ?: new \WP_Error( 'invalid_result', __( 'Invalid response.', 'hanmoto' ) );
	}

	/**
	 * Get openBD list.
	 *
	 * @return string[]|\WP_Error
	 */
	public function openbd_list() {
		$endpoint = $this->openbd_url( 'coverage' );
		$response = wp_remote_get( $endpoint );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$result = json_decode( $response['body'], true );
		return is_array( $result ) ? $result : new \WP_Error( 'invalid_result', __( 'Invalid response.', 'hanmoto' ) );
	}

	/**
	 * Get list ob isbns.
	 *
	 * @param string|string[] $id           Publisher id. Allow multiple ids.
	 * @param int             $country_code Default 4 Japan.
	 * @param bool            $allow_979    If true, search with 979.
	 * @return string[]
	 */
	public function openbd_filter( $id, $country_code = 4, $allow_979 = false ) {
		$list  = $this->openbd_list();
		$codes = [ 978 ];
		$ids   = (array) $id;
		if ( $allow_979 ) {
			$code[] = 979;
		}
		$prefix = [];
		foreach ( $codes as $code ) {
			foreach ( $ids as $id ) {
				$prefix[] = $code . $country_code . $id;
			}
		}
		return array_values( array_filter( $list, function( $isbn ) use ( $prefix ) {
			foreach ( $prefix as $p ) {
				if ( 0 === strpos( $isbn, $p ) ) {
					return true;
				}
			}
			return false;
		} ) );
	}

	/**
	 * Make request to OpenBD
	 *
	 * @param string $endpoint Endpoint name.
	 * @param string $method GET or POST.
	 * @param array $params Query parameter.
	 *
	 * @return object|array|\WP_Error
	 */
	protected function openbd_request( $endpoint, $method = 'GET', $params = [] ) {
		$method = strtoupper( $method );
		$params = (array) $params;
		switch ( $method ) {
			case 'GET':
				$result = wp_remote_get( add_query_arg( $params, $endpoint ) );
				break;
			case 'POST':
				$result                   = wp_remote_post( $endpoint );
				$curl_opt[ CURLOPT_POST ] = true;
				$params_escaped           = [];
				foreach ( $params as $key => $value ) {
					$params_escaped[ rawurlencode( $key ) ] = rawurlencode( $value );
				}
				$curl_opt[ CURLOPT_POSTFIELDS ] = $params_escaped;
				break;
			default:
				// translators: %s is method.
				return new \WP_Error( 400, sprintf( __( 'Method %s is not allowed.', 'isbn-beautify' ), $method ) );
				break;
		}
		$curl_opt[ CURLOPT_URL ] = $endpoint;
		$ch                      = curl_init();
		curl_setopt_array( $ch, $curl_opt );
		$result = curl_exec( $ch );
		if ( ! $result ) {
			$err = curl_error( $ch );
			$no  = curl_errno( $ch );
			curl_close( $ch );

			return new WP_Error( 500, sprintf( 'OpenBD API returns error: %s %s', $no, $err ) );
		}
		curl_close( $ch );
		$response = json_decode( $result );
		if ( ! $response ) {
			return new \WP_Error( 500, __( 'Failed to parse response. something might be wrong.', 'isbn-beautify' ) );
		}

		return $response;
	}

}
