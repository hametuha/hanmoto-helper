<?php
/**
 * Template for stats.
 *
 * @package
 */

wp_localize_script( 'hanmoto-inventory-stats', 'HanmotoStats', [
	'password' => filter_input( INPUT_GET, 'pw' ),
	'post'     => get_query_var( 'p' ),
	'title'    => single_post_title( '', false ),
] );
wp_enqueue_script( 'hanmoto-inventory-stats' );
wp_enqueue_style( 'hanmoto-stats' );
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="UTF-8" />
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>><?php wp_body_open(); ?>
<div class="hanmoto-stats-template">
	<header class="hanmoto-stats-header">
		<h1 class="hanmoto-stats-title"><?php single_post_title(); ?> / <?php esc_html_e( '在庫変動', 'hanmoto' ); ?></h1>
	</header>
	<main class="hanmoto-stats-main loading">
		<span class="dashicons dashicons-update"></span>
		<canvas id="hanmoto-stats-chart"></canvas>
	</main>
	<footer class="hanmoto-stats-footer">
		<p>&copy; <?php echo esc_html( date( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?></p>
	</footer>
</div><!-- //.hanmoto-stats-template -->
<?php wp_footer(); ?>
</body>
</html>
