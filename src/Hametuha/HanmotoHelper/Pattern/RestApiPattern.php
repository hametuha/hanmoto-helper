<?php

namespace Hametuha\HanmotoHelper\Pattern;


/**
 * REST API pattern.
 *
 * @package hanmoto
 */
abstract class RestApiPattern extends Singleton {

	/**
	 * @var string Namespace.
	 */
	protected $namespace = 'hanmoto/v1';

	/**
	 * Return route.
	 *
	 * @return string
	 */
	abstract protected function route();

	/**
	 * Avaiable methods.
	 *
	 * @return string[]
	 */
	protected function methods() {
		return [ 'GET' ];
	}

	/**
	 * @inheritDoc
	 */
	protected function init() {
		add_action( 'rest_api_init', [ $this, 'register_api' ] );
	}

	/**
	 * Register route.
	 */
	public function register_api() {
		$api = [];
		foreach ( $this->methods() as $method ) {
			$api[] = [
				'methods'             => $method,
				'args'                => $this->get_arguments( $method ),
				'permission_callback' => [ $this, 'permission_callback' ],
				'callback'            => [ $this, 'callback' ],
			];
		}
		if ( ! empty( $api ) ) {
			register_rest_route( $this->namespace, $this->route(), $api );
		}
	}

	/**
	 * Arguments for request.
	 *
	 * @param string $method Request method.
	 * @return array
	 */
	public function get_arguments( $method ) {
		return [];
	}

	/**
	 * Callback function.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	abstract public function callback( $request );

	/**
	 * Permission callback.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return bool|\WP_Error
	 */
	public function permission_callback( $request ) {
		return true;
	}
}
