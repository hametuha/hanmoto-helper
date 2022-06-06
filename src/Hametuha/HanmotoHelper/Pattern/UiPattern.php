<?php

namespace Hametuha\HanmotoHelper\Pattern;


/**
 * UI pattern.
 *
 * @package hanmoto
 */
abstract class UiPattern extends \WP_List_Table {

	protected static $slug = '';

	protected static $parent_slug = '';

	protected static $icon = '';

	protected static $priority = 99;

	protected static $capability = 'edit_others_posts';

	protected static $can_add = true;

	protected $per_page = 20;

	/**
	 * If script is registered, enqueue them.
	 *
	 * @return string[]
	 */
	static protected function scripts() {
		return [];
	}

	/**
	 * Regsiter UI
	 *
	 * @return void
	 */
	public static function add_ui() {
		if ( static::$parent_slug ) {
			// Subpage.

		} else {
			// Main page.
			add_menu_page( static::title(), static::label(), static::$capability, static::$slug, get_called_class() . '::render', static::$icon, static::$priority );
		}
	}

	/**
	 * Return label.
	 *
	 * @return string
	 */
	static protected function label() {
		return '';
	}

	/**
	 * Page title.
	 *
	 * @return string
	 */
	static protected function title() {
		return static::label();
	}

	/**
	 * Description
	 *
	 * @return string
	 */
	static protected function description() {
		return '';
	}

	/**
	 * Render page.
	 *
	 * @return void
	 */
	static public function render() {
		wp_enqueue_style( 'hanmoto-stock-manager' );
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php echo esc_html( static::title() ); ?></h1>
			<?php if ( static::$can_add ) : ?>
				<a href="#hanmoot-add-new" class="page-title-action">
					<?php esc_html_e( 'Add New', 'hanmoto' ); ?>
				</a>
			<?php endif; ?>
			<hr class="wp-header-end">
		</div>
		<?php
			$table = new static();
			$table->prepare_items();

			$table->display();
		?>
		<?php if ( static::$can_add ) : ?>
		<hr style="margin: 2em 0;" />
		<div id="hanmoto-add-new">
			<?php static::editor(); ?>
		</div>
			<?php
		endif;
	}

	/**
	 * Render edit screen.
	 *
	 * @return void
	 */
	static protected function editor() {
		// Render editor.
	}
}
