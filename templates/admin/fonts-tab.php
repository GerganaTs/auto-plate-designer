<?php
/**
 * Admin settings tab: allowed font families and local .woff2 uploads.
 *
 * @package Auto_Plate_Designer
 *
 * @var APD_Admin_Settings $apd_admin Settings controller.
 * @var array<string, mixed> $settings Plugin settings.
 */

defined( 'ABSPATH' ) || exit;

$fonts   = isset( $settings['fonts'] ) && is_array( $settings['fonts'] ) ? $settings['fonts'] : array();
$edit_id = isset( $_GET['edit'] ) ? sanitize_text_field( wp_unslash( $_GET['edit'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$is_add  = isset( $_GET['add'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$editing = null;

if ( '' !== $edit_id ) {
	foreach ( $fonts as $font ) {
		if ( isset( $font['id'] ) && $font['id'] === $edit_id ) {
			$editing = $font;
			break;
		}
	}
}

$is_edit   = is_array( $editing );
$show_form = $is_edit || $is_add;

if ( ! $is_edit ) {
	$editing = array(
		'id'            => '',
		'family'        => '',
		'note'          => '',
		'weight'        => 400,
		'style'         => 'normal',
		'attachment_id' => 0,
	);
}

$attachment_id = isset( $editing['attachment_id'] ) ? absint( $editing['attachment_id'] ) : 0;
$file_name     = '';

if ( $attachment_id ) {
	$file_path = get_attached_file( $attachment_id );
	$file_name = is_string( $file_path ) && '' !== $file_path ? basename( $file_path ) : '';
}

$font_style = isset( $editing['style'] ) && 'italic' === $editing['style'] ? 'italic' : 'normal';
?>
<div class="apd-tab">
	<h2><?php esc_html_e( 'Fonts', 'auto-plate-designer' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Upload a self-hosted .woff2 file for each family. The storefront never loads fonts from Google or any other CDN.', 'auto-plate-designer' ); ?></p>

	<div class="apd-table-scroll">
	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Family', 'auto-plate-designer' ); ?></th>
				<th><?php esc_html_e( 'Shop label', 'auto-plate-designer' ); ?></th>
				<th><?php esc_html_e( 'Style', 'auto-plate-designer' ); ?></th>
				<th><?php esc_html_e( 'File', 'auto-plate-designer' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'auto-plate-designer' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $fonts ) ) : ?>
				<tr><td colspan="5"><?php esc_html_e( 'No fonts yet.', 'auto-plate-designer' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $fonts as $font ) : ?>
					<?php
					$att_id  = absint( $font['attachment_id'] );
					$att_url = $att_id ? wp_get_attachment_url( $att_id ) : '';
					?>
					<tr>
						<td><?php echo esc_html( $font['family'] ); ?></td>
						<td><?php echo isset( $font['note'] ) && '' !== (string) $font['note'] ? esc_html( (string) $font['note'] ) : '&mdash;'; ?></td>
						<td><?php echo esc_html( $font['style'] ); ?></td>
						<td>
							<?php if ( $att_url ) : ?>
								<a href="<?php echo esc_url( $att_url ); ?>"><?php esc_html_e( 'WOFF2', 'auto-plate-designer' ); ?></a>
							<?php else : ?>
								&mdash;
							<?php endif; ?>
						</td>
						<td>
							<a href="<?php echo esc_url( add_query_arg( 'edit', $font['id'], $apd_admin->tab_url( 'fonts' ) ) ); ?>"><?php esc_html_e( 'Edit', 'auto-plate-designer' ); ?></a>
							|
							<a class="apd-js-confirm" href="<?php echo esc_url( $apd_admin->delete_url( 'fonts', $font['id'] ) ); ?>"><?php esc_html_e( 'Delete', 'auto-plate-designer' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
	</div>

	<?php if ( ! $show_form ) : ?>
		<p class="apd-toolbar">
			<a class="button button-primary" href="<?php echo esc_url( add_query_arg( 'add', '1', $apd_admin->tab_url( 'fonts' ) ) ); ?>"><?php esc_html_e( 'Add font', 'auto-plate-designer' ); ?></a>
		</p>
	<?php else : ?>
		<p class="apd-toolbar">
			<a class="button" href="<?php echo esc_url( $apd_admin->tab_url( 'fonts' ) ); ?>"><?php esc_html_e( 'Cancel', 'auto-plate-designer' ); ?></a>
		</p>

	<h3><?php echo $is_edit ? esc_html__( 'Edit font', 'auto-plate-designer' ) : esc_html__( 'Add font', 'auto-plate-designer' ); ?></h3>

	<form method="post" action="<?php echo esc_url( $apd_admin->tab_url( 'fonts' ) ); ?>" class="apd-form">
		<?php wp_nonce_field( 'apd_save_settings', 'apd_settings_nonce' ); ?>
		<input type="hidden" name="apd_settings_action" value="save_font">
		<?php if ( $is_edit ) : ?>
			<input type="hidden" name="apd_font[id]" value="<?php echo esc_attr( (string) $editing['id'] ); ?>">
		<?php endif; ?>

		<table class="form-table" role="presentation">
			<tr>
				<th><label for="apd_font_family"><?php esc_html_e( 'Family name', 'auto-plate-designer' ); ?></label></th>
				<td><input type="text" class="regular-text" id="apd_font_family" name="apd_font[family]" value="<?php echo esc_attr( (string) $editing['family'] ); ?>" required placeholder="Oswald"></td>
			</tr>
			<tr>
				<th><label for="apd_font_note"><?php esc_html_e( 'Shop label', 'auto-plate-designer' ); ?></label></th>
				<td>
					<input type="text" class="regular-text" id="apd_font_note" name="apd_font[note]" value="<?php echo esc_attr( isset( $editing['note'] ) ? (string) $editing['note'] : '' ); ?>" maxlength="40" placeholder="<?php esc_attr_e( 'Cyrillic', 'auto-plate-designer' ); ?>">
					<p class="description"><?php esc_html_e( 'Customers see this after the font name. Leave it empty to show only the name.', 'auto-plate-designer' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="apd_font_style"><?php esc_html_e( 'Style', 'auto-plate-designer' ); ?></label></th>
				<td>
					<select id="apd_font_style" name="apd_font[style]">
						<option value="normal" <?php selected( $font_style, 'normal' ); ?>><?php esc_html_e( 'Normal', 'auto-plate-designer' ); ?></option>
						<option value="italic" <?php selected( $font_style, 'italic' ); ?>><?php esc_html_e( 'Italic', 'auto-plate-designer' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'WOFF2 file', 'auto-plate-designer' ); ?></th>
				<td>
					<div class="apd-media-field">
						<input type="hidden" name="apd_font[attachment_id]" value="<?php echo esc_attr( $attachment_id ? (string) $attachment_id : '' ); ?>" data-apd-media-input>
						<button type="button" class="button" data-apd-media="font"><?php esc_html_e( 'Select WOFF2', 'auto-plate-designer' ); ?></button>
						<button type="button" class="button-link" data-apd-media-clear><?php esc_html_e( 'Clear', 'auto-plate-designer' ); ?></button>
						<div class="apd-media-preview" data-apd-media-name><?php echo esc_html( $file_name ); ?></div>
					</div>
					<?php if ( $is_edit ) : ?>
						<p class="description"><?php esc_html_e( 'Leave this empty to keep the current file.', 'auto-plate-designer' ); ?></p>
					<?php endif; ?>
					<p class="description"><?php esc_html_e( 'Download a .woff2 subset (including from Google Fonts) and upload it to this site. Do not point the storefront at fonts.googleapis.com.', 'auto-plate-designer' ); ?></p>
				</td>
			</tr>
		</table>

		<?php submit_button( $is_edit ? __( 'Update font', 'auto-plate-designer' ) : __( 'Add font', 'auto-plate-designer' ) ); ?>
	</form>
	<?php endif; ?>
</div>
