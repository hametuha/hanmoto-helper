<?php

namespace Hametuha\HanmotoHelper\Controller;


use Hametuha\HanmotoHelper\Pattern\Singleton;
use Hametuha\HanmotoHelper\Utility\Renderer;

/**
 * Block related functions.
 *
 * @package hanmoto
 */
class Blocks extends Singleton {

	use Renderer;

	/**
	 * @inheritDoc
	 */
	protected function init() {
		add_action( 'init', [ $this, 'register_blocks' ], 100 );
	}

	/**
	 * Register blocks.
	 */
	public function register_blocks() {
		register_block_type( 'hanmoto/isbn', [
			'editor_script'   => 'hanmoto-isbn-block',
			'style'           => 'hanmoto-card',
			'attributes'      => $this->block_attributes(),
			'render_callback' => [ $this, 'render_callback' ],
		] );
		wp_localize_script( 'hanmoto-isbn-block', 'HanmotoIsbnBlockVars', [
			'name'  => $this->block_name(),
			'label' => $this->block_label(),
			'attributes' => $this->block_attributes(),
		] );
	}

	/**
	 * Block name.
	 *
	 * @return string
	 */
	protected function block_name() {
		return 'hanmoto/isbn';
	}

	/**
	 *
	 *
	 * @return string
	 */
	protected function block_label() {
		return 'ISBN';
	}

	/**
	 * Block attributes.
	 *
	 * @return array
	 */
	protected function block_attributes() {
		return [
			'isbn' => [
				'type'    => 'string',
				'default' => '',
			],
			'style' => [
				'type'    => 'string',
				'default' => 'card',
			],
		];
	}

	/**
	 * Render block.
	 *
	 * @param array  $attributes Attributes.
	 * @param string $content    Contents.
	 * @return string
	 */
	public function render_callback( $attributes, $content ) {
		$attributes = wp_parse_args( $attributes, $this->block_attributes() );
		$style = $attributes['style'];
		$isbn  = array_map( 'trim', array_filter( preg_split( '/(\r\n|\r|\n)/u', $attributes['isbn'] ), function( $var ) {
			return preg_match( '/\d{13}/u', $var );
		} ) );
		return $this->render_isbn( $isbn, $style );
	}
}
