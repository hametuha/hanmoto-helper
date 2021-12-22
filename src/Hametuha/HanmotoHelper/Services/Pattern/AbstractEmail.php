<?php

namespace Hametuha\HanmotoHelper\Services\Pattern;


/**
 * Email pattern.
 */
abstract class AbstractEmail extends \WC_Email {

	/**
	 * @inheritDoc
	 */
	public function __construct() {
		$this->template_base  = hanmoto_root_dir() . '/template-parts/hanmoto/';
		$this->template_plain = sprintf( 'emails/plain/%s.php', $this->template_id() );
		$this->template_html  = sprintf( 'emails/%s.php', $this->template_id() );
		parent::__construct();
	}

	/**
	 * Template id.
	 *
	 * @return string
	 */
	protected function template_id() {
		return 'shipped';
	}

	/**
	 * @inheritDoc
	 */
	public function get_content_html() {
		return wc_get_template_html( $this->template_html, $this->mail_content_props( false ), '', $this->template_base );
	}

	/**
	 * @inheritDoc
	 */
	public function get_content_plain() {
		return wc_get_template_html( $this->template_html, $this->mail_content_props( true ), '', $this->template_base );
	}

	/**
	 * Get mail props.
	 *
	 * @param bool $plain
	 * @return array
	 */
	protected function mail_content_props( $plain = false ) {
		return [
			'order'              => $this->object,
			'email_heading'      => $this->get_heading(),
			'additional_content' => $this->get_additional_content(),
			'sent_to_admin'      => true,
			'plain_text'         => $plain,
			'email'              => $this,
		];
	}

	/**
	 * @inheritDoc
	 */
	public function get_email_type_options() {
		$options = parent::get_email_type_options();
		if ( isset( $options['multipart'] ) ) {
			unset( $options['multipart'] );
		}
		return $options;
	}
}
