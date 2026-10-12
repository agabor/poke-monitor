<?php
/**
 * Section navigation partial.
 *
 * Expected input: $sections, a list of arrays with the keys label, url and is_active.
 */

defined( 'ABSPATH' ) || exit;
?>
<nav class="poke-monitor-sections" aria-label="<?php esc_attr_e( 'Poke Monitor sections', 'poke-monitor' ); ?>">
	<?php foreach ( $sections as $poke_monitor_section ) : ?>
		<a
			class="poke-monitor-section-link<?php echo ! empty( $poke_monitor_section['is_active'] ) ? ' is-active' : ''; ?>"
			href="<?php echo esc_url( $poke_monitor_section['url'] ); ?>"
			<?php echo ! empty( $poke_monitor_section['is_active'] ) ? 'aria-current="page"' : ''; ?>
		><?php echo esc_html( $poke_monitor_section['label'] ); ?></a>
	<?php endforeach; ?>
</nav>