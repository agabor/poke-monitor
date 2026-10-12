<?php

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap poke-monitor-wrap">
	<h1 class="poke-monitor-title"><?php esc_html_e( 'Poke Monitor', 'poke-monitor' ); ?></h1>

	<?php \Poke_Monitor\Admin\View::render( 'section-nav', array( 'sections' => $sections ) ); ?>

	<div class="poke-monitor-card poke-monitor-cf-card">
		<p class="poke-monitor-cf-note poke-monitor-cf-note--<?php echo esc_attr( $cloudflare['live_note']['status'] ); ?>">
			<span class="poke-monitor-cf-note__icon" aria-hidden="true"><?php echo esc_html( $cloudflare['live_note_icon'] ); ?></span>
			<span class="poke-monitor-cf-note__text"><?php echo esc_html( $cloudflare['live_note']['message'] ); ?></span>
		</p>

		<?php if ( ! empty( $cloudflare['hint'] ) ) : ?>
			<p class="poke-monitor-cf-hint"><?php echo esc_html( $cloudflare['hint'] ); ?></p>
		<?php endif; ?>

		<div class="poke-monitor-cf-switch">
			<span class="poke-monitor-cf-switch__label" id="poke-monitor-cf-switch-label"><?php esc_html_e( 'Cloudflare bypass detection', 'poke-monitor' ); ?></span>
			<button
				type="button"
				class="poke-monitor-switch<?php echo ! empty( $cloudflare['enabled'] ) ? ' is-on' : ''; ?>"
				id="poke-monitor-cf-switch"
				role="switch"
				aria-checked="<?php echo ! empty( $cloudflare['enabled'] ) ? 'true' : 'false'; ?>"
				aria-labelledby="poke-monitor-cf-switch-label"
				data-enabled="<?php echo ! empty( $cloudflare['enabled'] ) ? '1' : '0'; ?>"
			>
				<span class="poke-monitor-switch__knob"></span>
				<span class="poke-monitor-switch__state"><?php echo ! empty( $cloudflare['enabled'] ) ? esc_html__( 'On', 'poke-monitor' ) : esc_html__( 'Off', 'poke-monitor' ); ?></span>
			</button>
		</div>

		<p class="poke-monitor-cf-footer" id="poke-monitor-cf-set-by"><?php echo esc_html( $cloudflare['description'] ); ?></p>
	</div>

	<nav class="poke-monitor-tabs" aria-label="<?php esc_attr_e( 'Error types', 'poke-monitor' ); ?>">
		<?php foreach ($tabs as $poke_monitor_tab ) : ?>
			<a
				class="<?php echo esc_attr( $poke_monitor_tab['css_classes'] ); ?>"
				href="<?php echo esc_url( $poke_monitor_tab['url'] ); ?>"
				<?php echo ! empty( $poke_monitor_tab['is_active'] ) ? 'aria-current="page"' : ''; ?>
			>
				<span class="poke-monitor-tab__label"><?php echo esc_html( $poke_monitor_tab['label'] ); ?></span>
				<span class="poke-monitor-tab__count"<?php echo '' !== $poke_monitor_tab['tooltip'] ? ' title="' . esc_attr( $poke_monitor_tab['tooltip'] ) . '"' : ''; ?>><?php echo esc_html( number_format_i18n( (int) $poke_monitor_tab['count'] ) ); ?></span>
				<?php if ( ! empty( $poke_monitor_tab['is_detection_off'] ) ) : ?>
					<span class="poke-monitor-tab__note"><?php esc_html_e( 'detection off', 'poke-monitor' ); ?></span>
				<?php endif; ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<div class="poke-monitor-card poke-monitor-log-card">
		<?php if ( empty( $rows ) ) : ?>
			<p class="poke-monitor-empty"><?php echo esc_html( $current_tab['empty_message'] ); ?></p>
		<?php else : ?>
			<div class="poke-monitor-table-scroll">
				<table class="poke-monitor-table">
					<thead>
						<tr>
							<th scope="col">
								<a class="poke-monitor-sort" href="<?php echo esc_url( $sort['time_url'] ); ?>">
									<?php esc_html_e( 'Time', 'poke-monitor' ); ?>
									<span class="poke-monitor-sort__indicator" aria-hidden="true"><?php echo esc_html( $sort['time_indicator'] ); ?></span>
								</a>
							</th>
							<th scope="col"><?php esc_html_e( 'Method', 'poke-monitor' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Path', 'poke-monitor' ); ?></th>
							<th scope="col">
								<a class="poke-monitor-sort" href="<?php echo esc_url( $sort['code_url'] ); ?>">
									<?php esc_html_e( 'Code', 'poke-monitor' ); ?>
									<span class="poke-monitor-sort__indicator" aria-hidden="true"><?php echo esc_html( $sort['code_indicator'] ); ?></span>
								</a>
							</th>
							<th scope="col"><?php esc_html_e( 'IP', 'poke-monitor' ); ?></th>
							<th scope="col"><?php echo esc_html( $badge_column_label ); ?></th>
							<th scope="col"><?php esc_html_e( 'Allowlist', 'poke-monitor' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rows as $poke_monitor_row ) : ?>
							<tr>
								<td class="poke-monitor-cell poke-monitor-cell--time"><?php echo esc_html( $poke_monitor_row['time'] ); ?></td>
								<td class="poke-monitor-cell poke-monitor-cell--method"><?php echo esc_html( $poke_monitor_row['method'] ); ?></td>
								<td class="poke-monitor-cell poke-monitor-cell--path">
									<span class="poke-monitor-mono poke-monitor-ellipsis" title="<?php echo esc_attr( $poke_monitor_row['path'] ); ?>"><?php echo esc_html( $poke_monitor_row['path'] ); ?></span>
									<span class="poke-monitor-second-line poke-monitor-mono poke-monitor-ellipsis" title="<?php echo esc_attr( $poke_monitor_row['second_line'] ); ?>"><?php echo esc_html( $poke_monitor_row['second_line'] ); ?></span>
								</td>
								<td class="poke-monitor-cell poke-monitor-cell--code"><span class="poke-monitor-code <?php echo esc_attr( $poke_monitor_row['status_class'] ); ?>"><?php echo esc_html( (string) $poke_monitor_row['status_code'] ); ?></span></td>
								<td class="poke-monitor-cell poke-monitor-cell--ip">
									<span class="poke-monitor-mono"><?php echo '' !== $poke_monitor_row['client_ip'] ? esc_html( $poke_monitor_row['client_ip'] ) : '—'; ?></span>
									<?php if ( ! empty( $poke_monitor_row['addresses_differ'] ) ) : ?>
										<span class="poke-monitor-remote-addr poke-monitor-mono is-different" title="<?php esc_attr_e( 'Actual connecting address differs from the client IP', 'poke-monitor' ); ?>"><?php echo esc_html( $poke_monitor_row['remote_addr'] ); ?></span>
									<?php endif; ?>
								</td>
								<td class="poke-monitor-cell poke-monitor-cell--types">
									<?php foreach ( $poke_monitor_row['badges'] as $poke_monitor_badge ) : ?>
										<a class="poke-monitor-badge <?php echo esc_attr( $poke_monitor_badge['badge_class'] ); ?>" href="<?php echo esc_url( $poke_monitor_badge['url'] ); ?>"><?php echo esc_html( $poke_monitor_badge['label'] ); ?></a>
									<?php endforeach; ?>
								</td>
								<td class="poke-monitor-cell poke-monitor-cell--allowlist">
									<?php foreach ( $poke_monitor_row['allowlist'] as $poke_monitor_button_kind => $poke_monitor_allowlist_button ) : ?>
										<button
											type="button"
											class="button button-small poke-monitor-al-row-button<?php echo ! empty( $poke_monitor_allowlist_button['exists'] ) ? ' is-existing' : ''; ?>"
											data-kind="<?php echo esc_attr( $poke_monitor_button_kind ); ?>"
											data-prefill="<?php echo esc_attr( $poke_monitor_allowlist_button['prefill_json'] ); ?>"
											data-existing="<?php echo esc_attr( $poke_monitor_allowlist_button['existing_json'] ); ?>"
											title="<?php echo esc_attr( $poke_monitor_allowlist_button['title'] ); ?>"
											<?php disabled( ! empty( $poke_monitor_allowlist_button['disabled'] ) ); ?>
										><?php echo esc_html( ( ! empty( $poke_monitor_allowlist_button['exists'] ) ? '✓ ' : '+ ' ) . $poke_monitor_allowlist_button['label'] ); ?></button>
									<?php endforeach; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>

		<?php if ( $total_pages > 1 ) : ?>
			<div class="poke-monitor-pagination">
				<?php
				echo wp_kses_post(
					paginate_links(
						array(
							'base'      => $pagination_base,
							'format'    => '',
							'current'   => $page,
							'total'     => $total_pages,
							'prev_text' => '‹',
							'next_text' => '›',
							'type'      => 'plain',
						)
					)
				);
				?>
			</div>
		<?php endif; ?>

		<div class="poke-monitor-footer">
			<p class="poke-monitor-footer__count">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: number of logged requests. */
						_n( '%s request', '%s requests', $total_items, 'poke-monitor' ),
						number_format_i18n( $total_items )
					)
				);
				?>
			</p>

			<div class="poke-monitor-footer__actions">
				<?php if ( ! $is_all_tab ) : ?>
					<button
						type="button"
						class="button poke-monitor-clear-type"
						data-type-id="<?php echo esc_attr( (string) (int) $current_tab['error_type_id'] ); ?>"
						data-label="<?php echo esc_attr( $current_tab['label'] ); ?>"
					>
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: error type name. */
								__( 'Clear %s', 'poke-monitor' ),
								$current_tab['label']
							)
						);
						?>
					</button>
				<?php endif; ?>
				<button type="button" class="button button-link-delete poke-monitor-clear-all"><?php esc_html_e( 'Clear all', 'poke-monitor' ); ?></button>
			</div>

			<p class="poke-monitor-footer__autoclean">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: number of days. */
						_n(
							'Auto-clean: entries older than %s day are removed daily.',
							'Auto-clean: entries older than %s days are removed daily.',
							$keep_days,
							'poke-monitor'
						),
						number_format_i18n( $keep_days )
					)
				);
				?>
			</p>
		</div>
	</div>

	<?php
	\Poke_Monitor\Admin\View::render(
		'allowlist-form',
		array(
			'category_options' => $category_options,
			'ua_hint'          => $ua_hint,
			'is_modal'         => true,
		)
	);
	?>
</div>