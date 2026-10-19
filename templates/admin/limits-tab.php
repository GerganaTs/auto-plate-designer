<?php
/**
 * Admin settings tab: font floor, street-plate wrap lines, and character class.
 *
 * @package Auto_Plate_Designer
 *
 * @var APD_Admin_Settings $apd_admin Settings controller.
 * @var array<string, mixed> $settings Plugin settings.
 */

defined( 'ABSPATH' ) || exit;

$limits    = isset( $settings['limits'] ) && is_array( $settings['limits'] ) ? $settings['limits'] : array();
$whitelist = isset( $settings['char_whitelist'] ) ? (string) $settings['char_whitelist'] : APD_Security::DEFAULT_ADMIN_CHAR_CLASS;
$wrap      = isset( $limits['wrap_lines'] ) ? (int) $limits['wrap_lines'] : 5;

if ( $wrap < 1 ) {
	$wrap = 5;
}
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
				<th><label for="apd_wrap_lines"><?php esc_html_e( 'Maximum wrapped lines', 'auto-plate-designer' ); ?></label></th>
				<td>
					<input type="number" id="apd_wrap_lines" name="apd_limits[wrap_lines]" min="1" max="5" value="<?php echo esc_attr( (string) $wrap ); ?>">
					<p class="description"><?php esc_html_e( 'Street plates with text wrap stop after this many lines. The limit is 5.', 'auto-plate-designer' ); ?></p>
				</td>
			</tr>
		</table>

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
