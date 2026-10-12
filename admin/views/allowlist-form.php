<?php

defined( 'ABSPATH' ) || exit;

$poke_monitor_is_modal = ! empty( $is_modal );
?>
<?php if ( $poke_monitor_is_modal ) : ?>
<div class="poke-monitor-al-modal" id="poke-monitor-al-modal" role="dialog" aria-modal="true" aria-labelledby="poke-monitor-al-title" hidden>
	<div class="poke-monitor-al-modal__backdrop"></div>
<?php endif; ?>
	<form class="poke-monitor-card poke-monitor-al-form" id="poke-monitor-al-form"<?php echo $poke_monitor_is_modal ? '' : ' hidden'; ?>>
		<h2 class="poke-monitor-al-form-title" id="poke-monitor-al-title"><?php esc_html_e( 'Add allowlist entry', 'poke-monitor' ); ?></h2>

		<input type="hidden" id="poke-monitor-al-id" value="">

		<div class="poke-monitor-al-field">
			<label class="poke-monitor-al-field__label" for="poke-monitor-al-type"><?php esc_html_e( 'Type', 'poke-monitor' ); ?></label>
			<select id="poke-monitor-al-type">
				<option value="ip"><?php esc_html_e( 'IP', 'poke-monitor' ); ?></option>
				<option value="ua"><?php esc_html_e( 'User-Agent', 'poke-monitor' ); ?></option>
			</select>
		</div>

		<div class="poke-monitor-al-field">
			<label class="poke-monitor-al-field__label" for="poke-monitor-al-value"><?php esc_html_e( 'Value (exact match)', 'poke-monitor' ); ?></label>
			<input type="text" class="regular-text poke-monitor-mono" id="poke-monitor-al-value" maxlength="512" autocomplete="off">
			<p class="poke-monitor-al-field__hint"><?php echo esc_html( $ua_hint ); ?></p>
		</div>

		<fieldset class="poke-monitor-al-field poke-monitor-al-categories">
			<legend class="poke-monitor-al-field__label"><?php esc_html_e( 'Applies to', 'poke-monitor' ); ?></legend>
			<label class="poke-monitor-al-check">
				<input type="checkbox" class="poke-monitor-al-category" data-category="all" checked>
				<?php esc_html_e( 'All categories', 'poke-monitor' ); ?>
			</label>
			<?php foreach ( $category_options as $poke_monitor_category_option ) : ?>
				<label class="poke-monitor-al-check">
					<input type="checkbox" class="poke-monitor-al-category" data-category="<?php echo esc_attr( (string) $poke_monitor_category_option['id'] ); ?>">
					<?php echo esc_html( $poke_monitor_category_option['label'] ); ?>
				</label>
			<?php endforeach; ?>
		</fieldset>

		<div class="poke-monitor-al-field">
			<label class="poke-monitor-al-field__label" for="poke-monitor-al-note"><?php esc_html_e( 'Note', 'poke-monitor' ); ?></label>
			<input type="text" class="regular-text" id="poke-monitor-al-note" maxlength="255">
		</div>

		<div class="poke-monitor-al-field">
			<label class="poke-monitor-al-check">
				<input type="checkbox" id="poke-monitor-al-delete-existing">
				<?php esc_html_e( 'Also delete existing matching rows', 'poke-monitor' ); ?>
			</label>
		</div>

		<p class="poke-monitor-al-error" id="poke-monitor-al-error" role="alert" hidden></p>

		<div class="poke-monitor-al-form-actions">
			<button type="submit" class="button button-primary" id="poke-monitor-al-save"><?php esc_html_e( 'Save', 'poke-monitor' ); ?></button>
			<button type="button" class="button" id="poke-monitor-al-cancel"><?php esc_html_e( 'Cancel', 'poke-monitor' ); ?></button>
		</div>
	</form>
<?php if ( $poke_monitor_is_modal ) : ?>
</div>
<?php endif; ?>