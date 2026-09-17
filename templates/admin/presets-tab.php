<?php
/**
 * Admin settings tab: country-band presets.
 *
 * @package Auto_Plate_Designer
 *
 * @var APD_Admin_Settings $apd_admin Settings controller.
 */

defined( 'ABSPATH' ) || exit;

$presets = APD_Presets::all();
$edit_id = isset( $_GET['edit'] ) ? sanitize_text_field( wp_unslash( $_GET['edit'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$editing = '' !== $edit_id ? APD_Presets::get( $edit_id ) : null;

if ( ! is_array( $editing ) ) {
	$editing = array(
		'id'                   => '',
		'name'                 => '',
		'country_code'         => '',
		'image_id'             => 0,
		'band_ratio'           => APD_Formats::eu_band_ratio( 520, 110 ),
		'side'                 => 'left',
		'allowed_format_types' => array( 'eu' ),
		'active'               => true,
	);
}

$image_id    = absint( $editing['image_id'] );
$image_thumb = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : '';
$allowed     = isset( $editing['allowed_format_types'] ) && is_array( $editing['allowed_format_types'] ) ? $editing['allowed_format_types'] : array( 'eu' );
?>
<div class="apd-tab">
	<h2><?php esc_html_e( 'Country presets', 'auto-plate-designer' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Country bands are shown on EU and motorcycle plates. USA, SUV, street, color, and holder formats never display these presets.', 'auto-plate-designer' ); ?></p>

	<div class="apd-table-scroll">
	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Name', 'auto-plate-designer' ); ?></th>
				<th><?php esc_html_e( 'Code', 'auto-plate-designer' ); ?></th>
				<th><?php esc_html_e( 'Side', 'auto-plate-designer' ); ?></th>
				<th><?php esc_html_e( 'Active', 'auto-plate-designer' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'auto-plate-designer' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $presets ) ) : ?>
				<tr><td colspan="5"><?php esc_html_e( 'No presets yet. Add Bulgaria, generic EU, Moldova, Germany, and Ukraine here.', 'auto-plate-designer' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $presets as $preset ) : ?>
					<tr>
						<td><?php echo esc_html( $preset['name'] ); ?></td>
						<td><?php echo esc_html( $preset['country_code'] ); ?></td>
						<td><?php echo esc_html( $preset['side'] ); ?></td>
						<td><?php echo ! empty( $preset['active'] ) ? esc_html__( 'Yes', 'auto-plate-designer' ) : esc_html__( 'No', 'auto-plate-designer' ); ?></td>
						<td>
							<a href="<?php echo esc_url( add_query_arg( 'edit', $preset['id'], $apd_admin->tab_url( 'presets' ) ) ); ?>"><?php esc_html_e( 'Edit', 'auto-plate-designer' ); ?></a>
							|
							<a class="apd-js-confirm" href="<?php echo esc_url( $apd_admin->delete_url( 'presets', $preset['id'] ) ); ?>"><?php esc_html_e( 'Delete', 'auto-plate-designer' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
	</div>

	<h3><?php echo $editing['id'] ? esc_html__( 'Edit preset', 'auto-plate-designer' ) : esc_html__( 'Add preset', 'auto-plate-designer' ); ?></h3>

	<form method="post" action="<?php echo esc_url( $apd_admin->tab_url( 'presets' ) ); ?>" class="apd-form">
		<?php wp_nonce_field( 'apd_save_settings', 'apd_settings_nonce' ); ?>
		<input type="hidden" name="apd_settings_action" value="save_preset">
		<input type="hidden" name="apd_preset[id]" value="<?php echo esc_attr( $editing['id'] ); ?>">

		<table class="form-table" role="presentation">
			<tr>
				<th><label for="apd_preset_name"><?php esc_html_e( 'Name', 'auto-plate-designer' ); ?></label></th>
				<td><input type="text" class="regular-text" id="apd_preset_name" name="apd_preset[name]" value="<?php echo esc_attr( $editing['name'] ); ?>" required></td>
			</tr>
			<tr>
				<th><label for="apd_preset_code"><?php esc_html_e( 'Country code', 'auto-plate-designer' ); ?></label></th>
				<td>
					<input type="text" id="apd_preset_code" name="apd_preset[country_code]" value="<?php echo esc_attr( $editing['country_code'] ); ?>" maxlength="3" minlength="1" pattern="[A-Za-z]{1,3}" class="small-text" required>
					<p class="description"><?php esc_html_e( '1 to 3 letters on the blue band, for example A, BG, or SLO. Empty is not allowed.', 'auto-plate-designer' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Band image', 'auto-plate-designer' ); ?></th>
				<td>
					<div class="apd-media-field">
						<input type="hidden" name="apd_preset[image_id]" value="<?php echo esc_attr( (string) $image_id ); ?>" data-apd-media-input>
						<button type="button" class="button" data-apd-media="image"><?php esc_html_e( 'Select image', 'auto-plate-designer' ); ?></button>
						<button type="button" class="button-link" data-apd-media-clear><?php esc_html_e( 'Clear', 'auto-plate-designer' ); ?></button>
						<div class="apd-media-preview">
							<?php if ( $image_thumb ) : ?>
								<img src="<?php echo esc_url( $image_thumb ); ?>" alt="">
							<?php endif; ?>
						</div>
					</div>
					<p class="description"><?php esc_html_e( 'PNG, JPEG, WebP, or SVG. SVG is sanitized on save in a later stage; prefer PNG/WebP.', 'auto-plate-designer' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Band layout', 'auto-plate-designer' ); ?></th>
				<td>
					<div class="apd-metric-grid">
						<label class="apd-metric"><?php echo esc_html__( 'Ratio', 'auto-plate-designer' ); ?>
							<span class="apd-metric__control">
								<input type="number" step="0.01" min="0.05" max="0.4" name="apd_preset[band_ratio]" value="<?php echo esc_attr( (string) $editing['band_ratio'] ); ?>">
							</span>
						</label>
						<label class="apd-metric"><?php echo esc_html__( 'Side', 'auto-plate-designer' ); ?>
							<span class="apd-metric__control">
								<select name="apd_preset[side]">
									<option value="left" <?php selected( $editing['side'], 'left' ); ?>><?php esc_html_e( 'Left', 'auto-plate-designer' ); ?></option>
									<option value="right" <?php selected( $editing['side'], 'right' ); ?>><?php esc_html_e( 'Right', 'auto-plate-designer' ); ?></option>
								</select>
							</span>
						</label>
					</div>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Allowed on', 'auto-plate-designer' ); ?></th>
				<td>
					<div class="apd-choice-list">
						<label class="apd-choice"><input type="checkbox" name="apd_preset[allowed_format_types][]" value="eu" <?php checked( in_array( 'eu', $allowed, true ) ); ?>> <?php esc_html_e( 'EU plates', 'auto-plate-designer' ); ?></label>
						<label class="apd-choice"><input type="checkbox" name="apd_preset[allowed_format_types][]" value="moto" <?php checked( in_array( 'moto', $allowed, true ) || ( in_array( 'eu', $allowed, true ) && ! in_array( 'moto', $allowed, true ) && array( 'eu' ) === array_values( $allowed ) ) ); ?>> <?php esc_html_e( 'Motorcycle plates', 'auto-plate-designer' ); ?></label>
					</div>
					<p class="description"><?php esc_html_e( 'American and SUV plates do not use a country band.', 'auto-plate-designer' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Status', 'auto-plate-designer' ); ?></th>
				<td><label class="apd-choice"><input type="checkbox" name="apd_preset[active]" value="1" <?php checked( ! empty( $editing['active'] ) ); ?>> <?php esc_html_e( 'Active', 'auto-plate-designer' ); ?></label></td>
			</tr>
		</table>

		<?php submit_button( $editing['id'] ? __( 'Update preset', 'auto-plate-designer' ) : __( 'Add preset', 'auto-plate-designer' ) ); ?>
	</form>
</div>
