<?php
/**
 * Admin settings tab: plate formats (EU / US / custom / holder).
 *
 * @package Auto_Plate_Designer
 *
 * @var APD_Admin_Settings $apd_admin Settings controller.
 */

defined( 'ABSPATH' ) || exit;

$formats = APD_Formats::all();
$edit_id = isset( $_GET['edit'] ) ? sanitize_text_field( wp_unslash( $_GET['edit'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$editing = '' !== $edit_id ? APD_Formats::get( $edit_id ) : null;
$fonts   = isset( $settings['fonts'] ) && is_array( $settings['fonts'] ) ? $settings['fonts'] : array();

if ( ! is_array( $editing ) ) {
	$editing = array(
		'id'               => '',
		'name'             => '',
		'type'             => 'eu',
		'width'            => 520,
		'height'           => 110,
		'border_width'     => 8,
		'border_color'     => '#000000',
		'band_ratio'       => APD_Formats::eu_band_ratio( 520, 110 ),
		'band_side'        => 'left',
		'base_image_id'    => 0,
		'color_zones'      => array(),
		'max_chars'        => 12,
		'font_ids'         => array(),
		'price_adjustment' => 0,
		'text_box'         => array(),
		'no_frame'         => false,
		'band_box'         => array(),
	);
}

if ( ! isset( $editing['max_chars'] ) ) {
	$editing['max_chars'] = APD_Formats::default_max_chars( $editing['type'] );
}

if ( ! isset( $editing['font_ids'] ) || ! is_array( $editing['font_ids'] ) ) {
	$editing['font_ids'] = array();
}

$base_id    = absint( $editing['base_image_id'] );
$base_thumb = $base_id ? wp_get_attachment_image_url( $base_id, 'medium' ) : '';
$base_full  = $base_id ? wp_get_attachment_image_url( $base_id, 'full' ) : '';
$text_box   = APD_Formats::sanitize_text_box( isset( $editing['text_box'] ) ? $editing['text_box'] : array(), isset( $editing['type'] ) ? (string) $editing['type'] : 'eu', $editing );
$stage_ratio = ( ! empty( $editing['width'] ) && ! empty( $editing['height'] ) )
	? (string) (int) $editing['width'] . ' / ' . (string) (int) $editing['height']
	: '520 / 110';
if ( empty( $editing['band_box'] ) || ! is_array( $editing['band_box'] ) ) {
	$editing['band_box'] = APD_Formats::default_band_box(
		(int) $editing['width'],
		(int) $editing['height'],
		isset( $editing['band_side'] ) ? (string) $editing['band_side'] : 'left'
	);
}
$band_box = APD_Formats::sanitize_band_box(
	$editing['band_box'],
	(int) $editing['width'],
	(int) $editing['height'],
	isset( $editing['band_side'] ) ? (string) $editing['band_side'] : 'left'
);
$sample_band = '';
if ( class_exists( 'APD_Presets' ) ) {
	foreach ( APD_Presets::active_for_format( 'eu' ) as $preset ) {
		if ( empty( $preset['image_id'] ) ) {
			continue;
		}
		$url = wp_get_attachment_image_url( absint( $preset['image_id'] ), 'full' );
		if ( $url ) {
			$sample_band = $url;
			break;
		}
	}
}
$new_product_url = admin_url( 'post-new.php?post_type=product' );
?>
<div class="apd-tab">
	<h2><?php esc_html_e( 'Formats', 'auto-plate-designer' ); ?></h2>
	<p class="description"><?php esc_html_e( 'A format is a template (how the plate is drawn). It does not appear in the shop until you create a WooCommerce product and enable the configurator on that product.', 'auto-plate-designer' ); ?></p>

	<div class="apd-table-scroll">
	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Name', 'auto-plate-designer' ); ?></th>
				<th><?php esc_html_e( 'Type', 'auto-plate-designer' ); ?></th>
				<th><?php esc_html_e( 'Size (W×H)', 'auto-plate-designer' ); ?></th>
				<th><?php esc_html_e( 'Max characters', 'auto-plate-designer' ); ?></th>
				<th><?php esc_html_e( 'Extra price adjustment', 'auto-plate-designer' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'auto-plate-designer' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $formats ) ) : ?>
				<tr><td colspan="6"><?php esc_html_e( 'No formats yet. Add one below.', 'auto-plate-designer' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $formats as $format ) : ?>
					<tr>
						<td><?php echo esc_html( $format['name'] ); ?></td>
						<td><?php echo esc_html( APD_Formats::type_label( $format['type'] ) ); ?></td>
						<td><?php echo esc_html( (int) $format['width'] . ' x ' . (int) $format['height'] ); ?></td>
						<td><?php echo esc_html( (string) ( isset( $format['max_chars'] ) ? (int) $format['max_chars'] : APD_Formats::default_max_chars( $format['type'] ) ) ); ?></td>
						<td><?php echo esc_html( (string) $format['price_adjustment'] ); ?></td>
						<td>
							<a href="<?php echo esc_url( add_query_arg( 'edit', $format['id'], $apd_admin->tab_url( 'formats' ) ) ); ?>"><?php esc_html_e( 'Edit', 'auto-plate-designer' ); ?></a>
							|
							<a href="<?php echo esc_url( add_query_arg( 'apd_format', $format['id'], $new_product_url ) ); ?>"><?php esc_html_e( 'Create product', 'auto-plate-designer' ); ?></a>
							|
							<a class="apd-js-confirm" href="<?php echo esc_url( $apd_admin->delete_url( 'formats', $format['id'] ) ); ?>"><?php esc_html_e( 'Delete', 'auto-plate-designer' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
	</div>

	<h3><?php echo $editing['id'] ? esc_html__( 'Edit format', 'auto-plate-designer' ) : esc_html__( 'Add format', 'auto-plate-designer' ); ?></h3>

	<form method="post" action="<?php echo esc_url( $apd_admin->tab_url( 'formats' ) ); ?>" class="apd-form">
		<?php wp_nonce_field( 'apd_save_settings', 'apd_settings_nonce' ); ?>
		<input type="hidden" name="apd_settings_action" value="save_format">
		<input type="hidden" name="apd_format[id]" value="<?php echo esc_attr( $editing['id'] ); ?>">

		<table class="form-table" role="presentation">
			<tr>
				<th><label for="apd_format_name"><?php esc_html_e( 'Name', 'auto-plate-designer' ); ?></label></th>
				<td><input type="text" class="regular-text" id="apd_format_name" name="apd_format[name]" value="<?php echo esc_attr( $editing['name'] ); ?>" required></td>
			</tr>
			<tr>
				<th><label for="apd_format_type"><?php esc_html_e( 'Type', 'auto-plate-designer' ); ?></label></th>
				<td>
					<select id="apd_format_type" name="apd_format[type]" data-apd-format-type>
						<?php foreach ( APD_Security::allowed_format_types() as $type_slug ) : ?>
							<option value="<?php echo esc_attr( $type_slug ); ?>" <?php selected( $editing['type'], $type_slug ); ?>><?php echo esc_html( APD_Formats::type_label( $type_slug ) ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'EU and motorcycle plates paint a euroband. USA plates use state graphics. SUV / crossover and holders use a full uploaded photo. Color plates start at EU size without a country band.', 'auto-plate-designer' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Plate canvas', 'auto-plate-designer' ); ?></th>
				<td>
					<div class="apd-metric-grid">
						<label class="apd-metric"><?php echo esc_html__( 'Width', 'auto-plate-designer' ); ?>
							<span class="apd-metric__control">
								<input type="number" name="apd_format[width]" value="<?php echo esc_attr( (string) $editing['width'] ); ?>" min="100" max="2000">
								<span class="apd-metric__unit">mm</span>
							</span>
						</label>
						<label class="apd-metric"><?php echo esc_html__( 'Height', 'auto-plate-designer' ); ?>
							<span class="apd-metric__control">
								<input type="number" name="apd_format[height]" value="<?php echo esc_attr( (string) $editing['height'] ); ?>" min="40" max="1200">
								<span class="apd-metric__unit">mm</span>
							</span>
						</label>
					</div>
					<p class="description"><?php esc_html_e( 'These numbers set the canvas proportion for every product on this format. Use real millimetres: EU 520×110, USA 305×152, motorcycle 240×130, SUV type C 340×200, holder 520×110.', 'auto-plate-designer' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="apd_format_max_chars"><?php esc_html_e( 'Maximum characters', 'auto-plate-designer' ); ?></label></th>
				<td>
					<input type="number" id="apd_format_max_chars" name="apd_format[max_chars]" min="1" max="<?php echo esc_attr( (string) APD_Security::ABSOLUTE_MAX_CHARS ); ?>" value="<?php echo esc_attr( (string) $editing['max_chars'] ); ?>">
					<p class="description"><?php esc_html_e( 'Shoppers cannot type more than this on the product page.', 'auto-plate-designer' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Fonts', 'auto-plate-designer' ); ?></th>
				<td>
					<?php if ( empty( $fonts ) ) : ?>
						<p class="description"><?php esc_html_e( 'Upload fonts under the Fonts tab first. Until then the preview uses a system sans-serif.', 'auto-plate-designer' ); ?></p>
					<?php else : ?>
						<div class="apd-choice-list">
						<?php foreach ( $fonts as $font ) : ?>
							<label class="apd-choice">
								<input type="checkbox" name="apd_format[font_ids][]" value="<?php echo esc_attr( $font['id'] ); ?>" <?php checked( in_array( $font['id'], $editing['font_ids'], true ) ); ?>>
								<?php echo esc_html( $font['family'] . ' (' . $font['weight'] . ')' ); ?>
							</label>
						<?php endforeach; ?>
						</div>
						<p class="description"><?php esc_html_e( 'Leave all unchecked to offer every uploaded font. If only one is checked, shoppers will not see a font dropdown.', 'auto-plate-designer' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<tr class="apd-border-fields"<?php echo APD_Formats::uses_painted_plate( $editing['type'] ) ? '' : ' hidden'; ?>>
				<th><?php esc_html_e( 'Border', 'auto-plate-designer' ); ?></th>
				<td>
					<label class="apd-choice">
						<input type="checkbox" name="apd_format[no_frame]" value="1" data-apd-no-frame <?php checked( ! empty( $editing['no_frame'] ) ); ?> <?php disabled( ! APD_Formats::uses_painted_plate( $editing['type'] ) ); ?>>
						<?php esc_html_e( 'Without frame', 'auto-plate-designer' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'When this is checked, the shop plate is drawn without a border. Width and default color are unused.', 'auto-plate-designer' ); ?></p>
					<div class="apd-metric-grid" data-apd-frame-controls>
						<label class="apd-metric"><?php echo esc_html__( 'Width', 'auto-plate-designer' ); ?>
							<span class="apd-metric__control">
								<input type="number" name="apd_format[border_width]" value="<?php echo esc_attr( (string) $editing['border_width'] ); ?>" min="0" max="40" data-apd-border-width <?php disabled( ! APD_Formats::uses_painted_plate( $editing['type'] ) ); ?>>
								<span class="apd-metric__unit">mm</span>
							</span>
						</label>
						<label class="apd-metric"><?php echo esc_html__( 'Default color', 'auto-plate-designer' ); ?>
							<span class="apd-metric__control">
								<input type="text" name="apd_format[border_color]" value="<?php echo esc_attr( $editing['border_color'] ); ?>" pattern="^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$" data-apd-border-color <?php disabled( ! APD_Formats::uses_painted_plate( $editing['type'] ) ); ?>>
							</span>
						</label>
					</div>
					<p class="description"><?php esc_html_e( 'Drag the inner corners on the preview to resize the frame. Width is millimetres, matching the plate canvas.', 'auto-plate-designer' ); ?></p>
				</td>
			</tr>
			<tr class="apd-band-fields"<?php echo APD_Formats::uses_country_band( $editing['type'] ) ? '' : ' hidden'; ?>>
				<th><?php esc_html_e( 'Country band', 'auto-plate-designer' ); ?></th>
				<td>
					<div class="apd-metric-grid">
						<label class="apd-metric"><?php echo esc_html__( 'Side', 'auto-plate-designer' ); ?>
							<span class="apd-metric__control">
								<select name="apd_format[band_side]" data-apd-band-side <?php disabled( ! APD_Formats::uses_country_band( $editing['type'] ) ); ?>>
									<option value="left" <?php selected( $editing['band_side'], 'left' ); ?>><?php esc_html_e( 'Left', 'auto-plate-designer' ); ?></option>
									<option value="right" <?php selected( $editing['band_side'], 'right' ); ?>><?php esc_html_e( 'Right', 'auto-plate-designer' ); ?></option>
								</select>
							</span>
						</label>
					</div>
					<input type="hidden" name="apd_format[band_ratio]" value="<?php echo esc_attr( (string) $editing['band_ratio'] ); ?>" data-apd-band-ratio <?php disabled( ! APD_Formats::uses_country_band( $editing['type'] ) ); ?>>
					<p class="description"><?php esc_html_e( 'Drag the country band on the preview to move or resize it. The shop uses the same box. Default is a real 40 mm euroband on a 520×110 mm plate.', 'auto-plate-designer' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="apd_format_price"><?php esc_html_e( 'Extra price adjustment', 'auto-plate-designer' ); ?></label></th>
				<td>
					<input type="number" step="0.01" id="apd_format_price" name="apd_format[price_adjustment]" value="<?php echo esc_attr( (string) $editing['price_adjustment'] ); ?>">
					<p class="description"><?php esc_html_e( 'Added to the product price in the cart. Use 0 for no change.', 'auto-plate-designer' ); ?></p>
				</td>
			</tr>
			<tr class="apd-image-fields"<?php echo APD_Formats::uses_base_image( $editing['type'] ) ? '' : ' hidden'; ?>>
				<th><span data-apd-image-heading><?php echo 'suv' === $editing['type'] ? esc_html__( 'Plate graphic', 'auto-plate-designer' ) : esc_html__( 'Holder photo', 'auto-plate-designer' ); ?></span></th>
				<td>
					<div class="apd-media-field">
						<input type="hidden" name="apd_format[base_image_id]" value="<?php echo esc_attr( (string) $base_id ); ?>" data-apd-media-input>
						<button type="button" class="button" data-apd-media="image"><?php esc_html_e( 'Select image', 'auto-plate-designer' ); ?></button>
						<button type="button" class="button-link" data-apd-media-clear><?php esc_html_e( 'Clear', 'auto-plate-designer' ); ?></button>
						<div class="apd-media-preview">
							<?php if ( $base_thumb ) : ?>
								<img src="<?php echo esc_url( $base_thumb ); ?>" alt="">
							<?php endif; ?>
						</div>
					</div>
					<p class="description" data-apd-image-help><?php echo 'suv' === $editing['type'] ? esc_html__( 'Upload the full SUV / crossover plate image. Shoppers only change the text in the number area.', 'auto-plate-designer' ) : esc_html__( 'Photo of the holder. Shopper text is drawn on the bottom strip unless you move the text area.', 'auto-plate-designer' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Text area', 'auto-plate-designer' ); ?></th>
				<td>
					<p class="description"><?php echo esc_html( __( 'Drag the box onto the number area. Shoppers cannot move the text. On USA products a state graphic can override this with its own box. On EU and motorcycle plates, drag the country band and the inner frame corners too. Use Center text area after a resize.', 'auto-plate-designer' ) ); ?></p>
				</td>
			</tr>
			<tr class="apd-studio-row">
				<td colspan="2">
					<?php
					$apd_text_box            = $text_box;
					$apd_text_box_name       = 'apd_format[text_box]';
					$apd_text_box_ratio      = $stage_ratio;
					$apd_text_box_image      = ( APD_Formats::uses_base_image( $editing['type'] ) && $base_full ) ? $base_full : '';
					$apd_text_box_help       = '';
					$apd_text_box_show_stage = true;
					$apd_text_box_studio     = true;
					$apd_text_box_sample     = APD_Formats::uses_painted_plate( $editing['type'] ) ? 'CA 0909 BX' : __( 'TEXT', 'auto-plate-designer' );
					$apd_text_box_band       = true;
					$apd_band_box            = $band_box;
					$apd_band_image          = $sample_band;
					$apd_show_frame          = APD_Formats::uses_frame( $editing );
					$apd_border_width        = (int) $editing['border_width'];
					$apd_border_color        = (string) $editing['border_color'];
					$apd_plate_width         = (int) $editing['width'];
					$apd_plate_height        = (int) $editing['height'];
					$apd_band_fields_disabled = ! APD_Formats::uses_country_band( $editing['type'] );
					include APD_PLUGIN_DIR . 'templates/admin/partials/text-box-editor.php';
					?>
				</td>
			</tr>
		</table>

		<?php submit_button( $editing['id'] ? __( 'Update format', 'auto-plate-designer' ) : __( 'Add format', 'auto-plate-designer' ) ); ?>
	</form>
</div>
