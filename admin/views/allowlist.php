<?php

use Poke_Monitor\Admin\View;

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap poke-monitor-wrap">
	<h1 class="poke-monitor-title"><?php esc_html_e( 'Poke Monitor', 'poke-monitor' ); ?></h1>

	<?php View::render( 'section-nav', array( 'sections' => $sections ) ); ?>

	<div class="poke-monitor-al-toolbar">
		<button type="button" class="button button-primary" id="poke-monitor-al-add"><?php esc_html_e( 'Add entry', 'poke-monitor' ); ?></button>
		<button
			type="button"
			class="button"
			id="poke-monitor-al-add-current-ip"
			data-current-ip="<?php echo esc_attr( $current_ip ); ?>"
			<?php disabled( '' === $current_ip ); ?>
		><?php esc_html_e( 'Add my current IP', 'poke-monitor' ); ?></button>
	</div>

	<?php
	View::render(
		'allowlist-form',
		array(
			'category_options' => $category_options,
			'ua_hint'          => $ua_hint,
			'is_modal'         => false,
		)
	);
	?>

	<div class="poke-monitor-card poke-monitor-al-card">
		<?php if ( empty( $entries ) ) : ?>
			<p class="poke-monitor-empty"><?php esc_html_e( 'No allowlist entries yet. Allowlisted IPs and User-Agents are not logged.', 'poke-monitor' ); ?></p>
		<?php else : ?>
			<div class="poke-monitor-table-scroll">
				<table class="poke-monitor-table poke-monitor-al-table">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Type', 'poke-monitor' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Value (exact)', 'poke-monitor' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Applies to', 'poke-monitor' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Note', 'poke-monitor' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Actions', 'poke-monitor' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $entries as $poke_monitor_entry ) : ?>
							<tr>
								<td class="poke-monitor-cell">
									<span class="poke-monitor-al-type poke-monitor-al-type--<?php echo esc_attr( $poke_monitor_entry['type'] ); ?>"><?php echo esc_html( $poke_monitor_entry['type_label'] ); ?></span>
								</td>
								<td class="poke-monitor-cell poke-monitor-cell--value">
									<span class="poke-monitor-mono poke-monitor-ellipsis" title="<?php echo esc_attr( $poke_monitor_entry['value'] ); ?>"><?php echo esc_html( $poke_monitor_entry['value'] ); ?></span>
								</td>
								<td class="poke-monitor-cell"><?php echo esc_html( $poke_monitor_entry['applies_to'] ); ?></td>
								<td class="poke-monitor-cell">
									<span><?php echo esc_html( $poke_monitor_entry['note'] ); ?></span>
									<?php if ( '' !== $poke_monitor_entry['created_at'] ) : ?>
										<span class="poke-monitor-second-line poke-monitor-al-meta">
											<?php
											echo esc_html(
												'' !== $poke_monitor_entry['created_by']
													? sprintf(
														/* translators: 1: user display name, 2: date and time. */
														__( 'Added by %1$s, %2$s', 'poke-monitor' ),
														$poke_monitor_entry['created_by'],
														$poke_monitor_entry['created_at']
													)
													: $poke_monitor_entry['created_at']
											);
											?>
										</span>
									<?php endif; ?>
								</td>
								<td class="poke-monitor-cell poke-monitor-al-row-actions">
									<button type="button" class="button poke-monitor-al-edit" data-entry="<?php echo esc_attr( $poke_monitor_entry['entry_json'] ); ?>"><?php esc_html_e( 'Edit', 'poke-monitor' ); ?></button>
									<button type="button" class="button button-link-delete poke-monitor-al-delete" data-entry="<?php echo esc_attr( $poke_monitor_entry['entry_json'] ); ?>"><?php esc_html_e( 'Delete', 'poke-monitor' ); ?></button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</div>
</div>