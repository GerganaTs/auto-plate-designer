<?php
/**
 * Admin settings tab: layout limits and character whitelist.
 *
 * @package Auto_Plate_Designer
 *
 * @var APD_Admin_Settings $apd_admin Settings controller.
 * @var array<string, mixed> $settings Plugin settings.
 */

defined( 'ABSPATH' ) || exit;

$limits     = isset( $settings['limits'] ) && is_array( $settings['limits'] ) ? $settings['limits'] : array();
$layouts    = isset( $limits['layouts'] ) && is_array( $limits['layouts'] ) ? $limits['layouts'] : array();
$whitelist  = isset( $settings['char_whitelist'] ) ? (string) $settings['char_whitelist'] : APD_Security::DEFAULT_ADMIN_CHAR_CLASS;
$layout_labels = array(
	'text_only'       => __( 'Text only', 'auto-plate-designer' ),
	'text_image_text' => __( 'Text / image / text', 'auto-plate-designer' ),
	'image_text'      => __( 'Image / text', 'auto-plate-designer' ),
	'multiline_text'  => __( 'Multiline text', 'auto-plate-designer' ),
);
?>
<div class="apd-tab">
	<h2><?php esc_html_e( 'Limits and character rules', 'auto-plate-designer' ); ?></h2>

	<form method="post" action="<?php echo esc_url( $apd_admin->tab_url( 'limits' ) ); ?>" class="apd-form">
		<?php wp_nonce_field( 'apd_save_settings', 'apd_settings_nonce' ); ?>
		<input type="hidden" name="apd_settings_action" value="save_limits">

		<table class="form-table" role="presentation">
			<tr>
				<th><label for="apd_min_font"><?php esc_html_e( 'Minimum font size (px)', 'auto-plate-designer' ); ?></label></th>
				<td>
					<input type="number" id="apd_min_font" name="apd_limits[min_font_size]" min="6" max="48" value="<?php echo esc_attr( (string) ( $limits['min_font_size'] ?? 12 ) ); ?>">
					<p class="description"><?php esc_html_e( 'Auto-fit stops shrinking at this size. If the text still does not fit, Add to cart is blocked.', 'auto-plate-designer' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="apd_max_lines"><?php esc_html_e( 'Global max lines', 'auto-plate-designer' ); ?></label></th>
				<td>
					<input type="number" id="apd_max_lines" name="apd_limits[max_lines]" min="1" max="5" value="<?php echo esc_attr( (string) ( $limits['max_lines'] ?? 5 ) ); ?>">
					<p class="description"><?php esc_html_e( 'Hard cap is 5 lines.', 'auto-plate-designer' ); ?></p>
				</td>
			</tr>
		</table>

		<h3><?php esc_html_e( 'Per-layout limits', 'auto-plate-designer' ); ?></h3>
		<div class="apd-table-scroll">
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Layout', 'auto-plate-designer' ); ?></th>
					<th><?php esc_html_e( 'Max characters', 'auto-plate-designer' ); ?></th>
					<th><?php esc_html_e( 'Max lines', 'auto-plate-designer' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $layout_labels as $key => $label ) : ?>
					<?php
					$row   = isset( $layouts[ $key ] ) && is_array( $layouts[ $key ] ) ? $layouts[ $key ] : array();
					$chars = isset( $row['max_chars'] ) ? (int) $row['max_chars'] : 12;
					$lines = isset( $row['max_lines'] ) ? (int) $row['max_lines'] : 1;
					?>
					<tr>
						<td><?php echo esc_html( $label ); ?></td>
						<td><input type="number" min="1" max="<?php echo esc_attr( (string) APD_Security::ABSOLUTE_MAX_CHARS ); ?>" name="apd_limits[layouts][<?php echo esc_attr( $key ); ?>][max_chars]" value="<?php echo esc_attr( (string) $chars ); ?>"></td>
						<td>
							<?php if ( 'multiline_text' === $key ) : ?>
								<input type="number" min="1" max="5" name="apd_limits[layouts][<?php echo esc_attr( $key ); ?>][max_lines]" value="<?php echo esc_attr( (string) $lines ); ?>">
							<?php else : ?>
								<input type="hidden" name="apd_limits[layouts][<?php echo esc_attr( $key ); ?>][max_lines]" value="1">
								1
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		</div>

		<h3><?php esc_html_e( 'Character whitelist', 'auto-plate-designer' ); ?></h3>
		<p class="description"><?php esc_html_e( 'This extra character class can only make rules stricter. Hardcoded protection cannot be disabled, even if this field is cleared or invalid.', 'auto-plate-designer' ); ?></p>
		<p>
			<label><?php esc_html_e( 'Hardcoded minimum (read only)', 'auto-plate-designer' ); ?></label><br>
			<code><?php echo esc_html( APD_Security::DEFAULT_ADMIN_CHAR_CLASS ); ?></code>
		</p>
		<p>
			<label for="apd_char_whitelist"><?php esc_html_e( 'Admin character class', 'auto-plate-designer' ); ?></label><br>
			<textarea class="large-text code" rows="3" id="apd_char_whitelist" name="apd_limits[char_whitelist]"><?php echo esc_textarea( $whitelist ); ?></textarea>
		</p>

		<?php submit_button( __( 'Save limits', 'auto-plate-designer' ) ); ?>
	</form>
</div>
