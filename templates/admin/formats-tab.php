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
$is_add  = isset( $_GET['add'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$editing = '' !== $edit_id ? APD_Formats::get( $edit_id ) : null;
$is_edit = is_array( $editing );
$show_form = $is_edit || $is_add;
$fonts     = isset( $settings['fonts'] ) && is_array( $settings['fonts'] ) ? $settings['fonts'] : array();

if ( ! $is_edit ) {
	$editing = array(
		'id'               => '',
		'name'             => '',
		'type'             => '',
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

$format_type = isset( $editing['type'] ) ? (string) $editing['type'] : '';
$has_type    = '' !== $format_type;

if ( ! isset( $editing['max_chars'] ) ) {
	$editing['max_chars'] = $has_type ? APD_Formats::default_max_chars( $format_type ) : 12;
}

if ( ! isset( $editing['font_ids'] ) || ! is_array( $editing['font_ids'] ) ) {
	$editing['font_ids'] = array();
}

if ( $is_add && ! $is_edit ) {
	$editing['font_ids'] = array();

	foreach ( $fonts as $font ) {
		if ( is_array( $font ) && isset( $font['id'] ) && '' !== (string) $font['id'] ) {
			$editing['font_ids'][] = (string) $font['id'];
		}
	}
}

if ( APD_Formats::is_holder( $format_type ) ) {
	$holder_size       = APD_Formats::default_size( $format_type );
	$editing['width']  = $holder_size['width'];
	$editing['height'] = $holder_size['height'];
}

$base_id    = absint( $editing['base_image_id'] );
$base_thumb = $base_id ? wp_get_attachment_image_url( $base_id, 'medium' ) : '';
$base_full  = $base_id ? wp_get_attachment_image_url( $base_id, 'full' ) : '';

if ( APD_Formats::is_holder( $format_type ) && ! $base_full ) {
	$base_full  = APD_Formats::bundled_holder_image_url( $format_type );
	$base_thumb = $base_full;
}
$text_box   = APD_Formats::sanitize_text_box( isset( $editing['text_box'] ) ? $editing['text_box'] : array(), $has_type ? $format_type : 'eu', $editing );
$stage_ratio = ( ! empty( $editing['width'] ) && ! empty( $editing['height'] ) )
	? (string) (int) $editing['width'] . ' / ' . (string) (int) $editing['height']
	: '520 / 110';
if ( empty( $editing['band_box'] ) || ! is_array( $editing['band_box'] ) ) {
	$editing['band_box'] = APD_Formats::default_band_box(
		(int) $editing['width'],
		(int) $editing['height'],
		isset( $editing['band_side'] ) ? (string) $editing['band_side'] : 'left',
		$has_type ? $format_type : ''
	);
}
$band_box = APD_Formats::sanitize_band_box(
	$editing['band_box'],
	(int) $editing['width'],
	(int) $editing['height'],
	isset( $editing['band_side'] ) ? (string) $editing['band_side'] : 'left',
	$has_type ? $format_type : ''
);
$sample_band = '';
if ( class_exists( 'APD_Presets' ) ) {
	foreach ( APD_Presets::active_for_format( $has_type ? $format_type : 'eu' ) as $preset ) {
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
$details_hidden  = $has_type ? '' : ' hidden';
?>
<div class="apd-tab">
	<h2><?php esc_html_e( 'Formats', 'auto-plate-designer' ); ?></h2>

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
				<tr><td colspan="6"><?php esc_html_e( 'No formats yet.', 'auto-plate-designer' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $formats as $format ) : ?>
					<tr>
						<td><?php echo esc_html( $format['name'] ); ?></td>
						<td><?php echo esc_html( APD_Formats::type_label( $format['type'] ) ); ?></td>
						<td><?php echo esc_html( (int) $format['width'] . ' x ' . (int) $format['height'] ); ?></td>
						<td><?php echo esc_html( APD_Formats::max_chars_label( $format ) ); ?></td>
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

	<?php if ( ! $show_form ) : ?>
		<p>
			<a class="button button-primary" href="<?php echo esc_url( add_query_arg( 'add', '1', $apd_admin->tab_url( 'formats' ) ) ); ?>"><?php esc_html_e( 'Add Format', 'auto-plate-designer' ); ?></a>
		</p>
	<?php else : ?>
		<p>
			<a class="button" href="<?php echo esc_url( $apd_admin->tab_url( 'formats' ) ); ?>"><?php esc_html_e( 'Cancel', 'auto-plate-designer' ); ?></a>
		</p>

	<h3><?php echo $editing['id'] ? esc_html__( 'Edit format', 'auto-plate-designer' ) : esc_html__( 'Add format', 'auto-plate-designer' ); ?></h3>

	<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( $apd_admin->tab_url( 'formats' ) ); ?>" class="apd-form" data-apd-format-form>
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
						<option value="" <?php selected( $format_type, '' ); ?>><?php esc_html_e( 'Select type', 'auto-plate-designer' ); ?></option>
						<?php foreach ( APD_Security::allowed_format_types() as $type_slug ) : ?>
							<option value="<?php echo esc_attr( $type_slug ); ?>" <?php selected( $format_type, $type_slug ); ?>><?php echo esc_html( APD_Formats::type_label( $type_slug ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr class="apd-canvas-fields" data-apd-format-details<?php echo ( $has_type && ! APD_Formats::is_holder( $format_type ) ) ? '' : ' hidden'; ?>>
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
					<p class="description" data-apd-custom-size<?php echo 'custom' === $format_type ? '' : ' hidden'; ?>><?php esc_html_e( 'Street plates keep the millimetres you type. A 34×20 cm plate is 340 × 200.', 'auto-plate-designer' ); ?></p>
				</td>
			</tr>
			<?php
			$show_suv_rows   = $has_type && APD_Formats::uses_two_rows( $format_type );
			$suv_limits = APD_Formats::suv_row_limits(
				array(
					'type'            => $show_suv_rows ? $format_type : 'suv',
					'max_chars_row_1' => isset( $editing['max_chars_row_1'] ) ? $editing['max_chars_row_1'] : 0,
					'max_chars_row_2' => isset( $editing['max_chars_row_2'] ) ? $editing['max_chars_row_2'] : 0,
				)
			);
			$show_us_split   = $has_type && 'us' === $format_type && ! empty( $editing['split_text'] );
			$show_single_max = $has_type && ! $show_suv_rows && ! APD_Formats::is_holder( $format_type ) && ! $show_us_split;
			?>
			<tr data-apd-format-details data-apd-max-single<?php echo $show_single_max ? '' : ' hidden'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<th><label for="apd_format_max_chars"><?php esc_html_e( 'Maximum characters', 'auto-plate-designer' ); ?></label></th>
				<td>
					<input type="number" id="apd_format_max_chars" name="apd_format[max_chars]" min="1" max="<?php echo esc_attr( (string) APD_Security::ABSOLUTE_MAX_CHARS ); ?>" value="<?php echo esc_attr( (string) $editing['max_chars'] ); ?>"<?php echo $show_suv_rows ? ' disabled' : ''; ?>>
				</td>
			</tr>
			<tr data-apd-format-details data-apd-wrap<?php echo ( $has_type && APD_Formats::is_street_plate( $format_type ) ) ? '' : ' hidden'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<th><?php esc_html_e( 'Text wrap', 'auto-plate-designer' ); ?></th>
				<td>
					<label class="apd-choice">
						<input type="checkbox" name="apd_format[wrap_text]" value="1" <?php checked( ! empty( $editing['wrap_text'] ) ); ?> <?php disabled( ! APD_Formats::is_street_plate( $format_type ) ); ?>>
						<?php esc_html_e( 'Wrap long text onto extra lines.', 'auto-plate-designer' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'The plate size stays the width and height above. Words move to the next line until the multiline limit.', 'auto-plate-designer' ); ?></p>
				</td>
			</tr>
			<tr data-apd-format-details data-apd-max-rows<?php echo $show_suv_rows ? '' : ' hidden'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<th><?php esc_html_e( 'Characters per row', 'auto-plate-designer' ); ?></th>
				<td>
					<div class="apd-metric-grid">
						<label class="apd-metric"><?php esc_html_e( 'First row maximum', 'auto-plate-designer' ); ?>
							<input type="number" id="apd_format_row_1_max" name="apd_format[max_chars_row_1]" min="1" max="<?php echo esc_attr( (string) APD_Security::ABSOLUTE_MAX_CHARS ); ?>" value="<?php echo esc_attr( (string) $suv_limits[0] ); ?>"<?php echo $show_suv_rows ? '' : ' disabled'; ?>>
						</label>
						<label class="apd-metric"><?php esc_html_e( 'Second row maximum', 'auto-plate-designer' ); ?>
							<input type="number" id="apd_format_row_2_max" name="apd_format[max_chars_row_2]" min="1" max="<?php echo esc_attr( (string) APD_Security::ABSOLUTE_MAX_CHARS ); ?>" value="<?php echo esc_attr( (string) $suv_limits[1] ); ?>"<?php echo $show_suv_rows ? '' : ' disabled'; ?>>
						</label>
					</div>
				</td>
			</tr>
			<tr data-apd-format-details<?php echo $details_hidden; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<th><?php esc_html_e( 'Fonts', 'auto-plate-designer' ); ?></th>
				<td>
					<label class="apd-choice">
						<input type="checkbox" name="apd_format[font_ids_all]" value="1" data-apd-fonts-all <?php checked( ! empty( $editing['font_ids_all'] ) ); ?>>
						<?php esc_html_e( 'Select all fonts', 'auto-plate-designer' ); ?>
					</label>
					<?php if ( ! empty( $fonts ) ) : ?>
						<div class="apd-choice-list">
						<?php foreach ( $fonts as $font ) : ?>
							<label class="apd-choice">
								<input type="checkbox" name="apd_format[font_ids][]" value="<?php echo esc_attr( $font['id'] ); ?>" <?php checked( ! empty( $editing['font_ids_all'] ) || in_array( (string) $font['id'], $editing['font_ids'], true ) ); ?>>
								<?php echo esc_html( $font['family'] . ' (' . $font['weight'] . ')' ); ?>
							</label>
						<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</td>
			</tr>
			<tr class="apd-border-fields" data-apd-format-details<?php echo ( $has_type && ! APD_Formats::is_holder( $format_type ) ) ? '' : ' hidden'; ?>>
				<th><?php esc_html_e( 'Border', 'auto-plate-designer' ); ?></th>
				<td>
					<label class="apd-choice">
						<input type="checkbox" name="apd_format[no_frame]" value="1" data-apd-no-frame <?php checked( ! empty( $editing['no_frame'] ) ); ?> <?php disabled( ! $has_type || APD_Formats::is_holder( $format_type ) ); ?>>
						<?php esc_html_e( 'Without frame', 'auto-plate-designer' ); ?>
					</label>
					<div class="apd-metric-grid" data-apd-frame-controls>
						<label class="apd-metric"><?php echo esc_html__( 'Width', 'auto-plate-designer' ); ?>
							<span class="apd-metric__control">
								<input type="number" name="apd_format[border_width]" value="<?php echo esc_attr( (string) $editing['border_width'] ); ?>" min="0" max="40" data-apd-border-width <?php disabled( ! $has_type || APD_Formats::is_holder( $format_type ) ); ?>>
								<span class="apd-metric__unit">mm</span>
							</span>
						</label>
						<label class="apd-metric"><?php echo esc_html__( 'Default color', 'auto-plate-designer' ); ?>
							<span class="apd-metric__control">
								<input type="text" name="apd_format[border_color]" value="<?php echo esc_attr( $editing['border_color'] ); ?>" pattern="^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$" data-apd-border-color <?php disabled( ! $has_type || APD_Formats::is_holder( $format_type ) ); ?>>
							</span>
						</label>
					</div>
				</td>
			</tr>
			<tr class="apd-band-fields" data-apd-format-details<?php echo ( $has_type && APD_Formats::uses_country_band( $format_type ) ) ? '' : ' hidden'; ?>>
				<th><?php esc_html_e( 'Country band', 'auto-plate-designer' ); ?></th>
				<td>
					<div class="apd-metric-grid">
						<label class="apd-metric"><?php echo esc_html__( 'Side', 'auto-plate-designer' ); ?>
							<span class="apd-metric__control">
								<select name="apd_format[band_side]" data-apd-band-side <?php disabled( ! APD_Formats::uses_country_band( $format_type ) ); ?>>
									<option value="left" <?php selected( $editing['band_side'], 'left' ); ?>><?php esc_html_e( 'Left', 'auto-plate-designer' ); ?></option>
									<option value="right" <?php selected( $editing['band_side'], 'right' ); ?>><?php esc_html_e( 'Right', 'auto-plate-designer' ); ?></option>
								</select>
							</span>
						</label>
					</div>
					<input type="hidden" name="apd_format[band_ratio]" value="<?php echo esc_attr( (string) $editing['band_ratio'] ); ?>" data-apd-band-ratio <?php disabled( ! APD_Formats::uses_country_band( $format_type ) ); ?>>
				</td>
			</tr>
			<tr data-apd-format-details<?php echo $details_hidden; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<th><label for="apd_format_price"><?php esc_html_e( 'Extra price adjustment', 'auto-plate-designer' ); ?></label></th>
				<td>
					<input type="number" step="0.01" id="apd_format_price" name="apd_format[price_adjustment]" value="<?php echo esc_attr( (string) $editing['price_adjustment'] ); ?>">
				</td>
			</tr>
			<tr class="apd-image-fields" data-apd-format-details<?php echo ( $has_type && APD_Formats::uses_base_image( $format_type ) ) ? '' : ' hidden'; ?>>
				<th><span data-apd-image-heading><?php echo 'us' === $format_type ? esc_html__( 'Plate graphic', 'auto-plate-designer' ) : esc_html__( 'Holder photo', 'auto-plate-designer' ); ?></span></th>
				<td>
					<div class="apd-media-field">
						<input type="hidden" name="apd_format[base_image_id]" value="<?php echo esc_attr( (string) $base_id ); ?>" data-apd-media-input>
						<label class="apd-upload">
							<?php esc_html_e( 'Upload photo', 'auto-plate-designer' ); ?>
							<input type="file" name="apd_base_image" accept="image/png,image/jpeg,image/webp,.png,.jpg,.jpeg,.webp" data-apd-base-file>
						</label>
						<button type="button" class="button" data-apd-media="image"><?php esc_html_e( 'Select image', 'auto-plate-designer' ); ?></button>
						<button type="button" class="button-link" data-apd-media-clear><?php esc_html_e( 'Clear', 'auto-plate-designer' ); ?></button>
						<div class="apd-media-preview">
							<?php if ( $base_thumb ) : ?>
								<img src="<?php echo esc_url( $base_thumb ); ?>" alt="">
							<?php endif; ?>
						</div>
					</div>
					<p class="description" data-apd-image-help<?php echo 'us' === $format_type ? '' : ' hidden'; ?>><?php esc_html_e( 'Upload the plate graphic, then drag the text area onto the number hole. Set the character limit above.', 'auto-plate-designer' ); ?></p>
				</td>
			</tr>
			<tr class="apd-studio-row" data-apd-format-studio data-apd-format-details<?php echo $details_hidden; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<td colspan="2">
					<?php
					$apd_text_box            = $text_box;
					$apd_text_box_name       = 'apd_format[text_box]';
					$apd_text_box_ratio      = $stage_ratio;
					$apd_text_box_image      = ( APD_Formats::uses_base_image( $format_type ) && $base_full ) ? $base_full : '';
					$apd_text_box_help       = '';
					$apd_text_box_show_stage = $has_type;
					$apd_text_box_studio     = true;
					$apd_text_box_sample     = APD_Formats::admin_sample_plate_text( $has_type ? $format_type : 'custom' );
					$apd_text_box_type       = $has_type ? $format_type : 'eu';
					$apd_text_box_band       = true;
					$apd_band_box            = $band_box;
					$apd_band_image          = $sample_band;
					$apd_show_frame          = $has_type && ! APD_Formats::is_holder( $format_type ) && empty( $editing['no_frame'] ) && (int) $editing['border_width'] > 0;
					$apd_border_width        = (int) $editing['border_width'];
					$apd_border_color        = (string) $editing['border_color'];
					$apd_plate_width          = (int) $editing['width'];
					$apd_plate_height         = (int) $editing['height'];
					$apd_two_rows             = $has_type && APD_Formats::uses_two_rows( $format_type );
					$apd_split_ui             = true;
					$apd_split_on             = $show_us_split;
					$apd_split_name           = 'apd_format[split_text]';
					$apd_right_box_name       = 'apd_format[text_box_right]';
					$apd_side_left_name       = 'apd_format[max_chars_left]';
					$apd_side_right_name      = 'apd_format[max_chars_right]';
					$apd_split_class          = 'apd-us-only';
					$apd_split_row_hidden     = 'us' !== $format_type;
					$apd_text_box_right       = isset( $editing['text_box_right'] ) && is_array( $editing['text_box_right'] ) ? $editing['text_box_right'] : array();
					$apd_side_left_max        = isset( $editing['max_chars_left'] ) ? (int) $editing['max_chars_left'] : APD_Formats::US_SIDE_LEFT_MAX;
					$apd_side_right_max       = isset( $editing['max_chars_right'] ) ? (int) $editing['max_chars_right'] : APD_Formats::US_SIDE_RIGHT_MAX;
					$apd_band_fields_disabled = ! APD_Formats::uses_country_band( $format_type );
					$apd_canvas_max           = APD_Formats::preview_frame_width( $format_type, (int) $editing['width'] );
					include APD_PLUGIN_DIR . 'templates/admin/partials/text-box-editor.php';
					?>
				</td>
			</tr>
		</table>

		<div data-apd-format-submit<?php echo $details_hidden; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<?php
			$purpose_labels = array(
				'text'             => __( 'Text color', 'auto-plate-designer' ),
				'border'           => __( 'Border color', 'auto-plate-designer' ),
				'background'       => __( 'Plate color', 'auto-plate-designer' ),
				'holder'           => __( 'Holder color', 'auto-plate-designer' ),
				'holder_text'      => __( 'Holder inscription', 'auto-plate-designer' ),
				'holder_strip'     => __( 'Holder strip', 'auto-plate-designer' ),
				'color_text'       => __( 'Color plate text', 'auto-plate-designer' ),
				'color_border'     => __( 'Color plate frame', 'auto-plate-designer' ),
				'color_background' => __( 'Color plate fill', 'auto-plate-designer' ),
			);
			$format_palette_ids = isset( $editing['palette_ids'] ) && is_array( $editing['palette_ids'] ) ? $editing['palette_ids'] : array();

			if ( $is_edit && ! array_key_exists( 'palette_ids', $editing ) && ! isset( $editing['palette_slots'] ) && ! empty( $editing['id'] ) ) {
				$format_palette_ids = APD_Admin_Settings::palette_ids_on_products( $editing['id'] );
			}

			$named_palettes = APD_Color_Palettes::all();
			$active_palettes = APD_Color_Palettes::active();
			$use_slots       = $has_type && APD_Formats::uses_palette_slots( $format_type );
			$photo_holder    = $has_type && APD_Formats::is_photo_holder( $format_type );
			$slot_values     = APD_Formats::palette_slots_for_editor( $is_edit ? $editing : array(), $format_palette_ids );
			$slot_labels     = array(
				'text'       => __( 'Text color', 'auto-plate-designer' ),
				'background' => __( 'Plate color', 'auto-plate-designer' ),
				'border'     => __( 'Plate frame color', 'auto-plate-designer' ),
			);
			$apd_palette_chips = static function ( $palette ) {
				$chips = '';

				foreach ( APD_Color_Palettes::palette_colors( $palette ) as $chip ) {
					$chips .= '<span class="apd-palette-chip" style="background:' . esc_attr( $chip['hex'] ) . ';" title="' . esc_attr( $chip['label'] ) . '"></span>';
				}

				return $chips;
			};
			?>
			<div class="apd-format-palettes" data-apd-format-palettes data-apd-format-details<?php echo $details_hidden; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<h3><?php esc_html_e( 'Colors for this format', 'auto-plate-designer' ); ?></h3>
				<p class="description" data-apd-palette-slot-help<?php echo ( $use_slots && 'us' !== $format_type ) ? '' : ' hidden'; ?>><?php esc_html_e( 'Choose one palette for the text, the plate, and the frame. Every active palette is listed with its name and colors.', 'auto-plate-designer' ); ?></p>
				<p class="description" data-apd-palette-us-help<?php echo ( $use_slots && 'us' === $format_type ) ? '' : ' hidden'; ?>><?php esc_html_e( 'Choose a palette for the text. Every active palette is listed with its name and colors.', 'auto-plate-designer' ); ?></p>
				<p class="description" data-apd-palette-check-help<?php echo ( $use_slots || $photo_holder ) ? ' hidden' : ''; ?>><?php esc_html_e( 'Pick the palettes shoppers can use. The name and colors of each palette are shown here. A product only chooses this format.', 'auto-plate-designer' ); ?></p>
				<p class="description" data-apd-palette-photo-help<?php echo $photo_holder ? '' : ' hidden'; ?>><?php esc_html_e( 'The strip and the letters use the same palettes. The strip starts white and the letters start black.', 'auto-plate-designer' ); ?></p>
				<p class="description" data-apd-palette-slot-error hidden><?php esc_html_e( 'Choose a palette for the text, the plate, and the frame.', 'auto-plate-designer' ); ?></p>
				<div data-apd-palette-slots<?php echo $use_slots ? '' : ' hidden'; ?>>
					<?php foreach ( $slot_labels as $slot => $slot_label ) : ?>
						<?php
						$selected_id = isset( $slot_values[ $slot ] ) ? (string) $slot_values[ $slot ] : '';
						$selected    = '' !== $selected_id ? APD_Color_Palettes::get( $selected_id ) : null;
						?>
						<div class="apd-palette-menu" data-apd-palette-menu data-apd-palette-slot="<?php echo esc_attr( $slot ); ?>"<?php echo ( $use_slots && ! in_array( $slot, APD_Formats::palette_slot_keys_for( $format_type ), true ) ) ? ' hidden' : ''; ?>>
							<label class="apd-palette-menu__heading" id="apd-palette-slot-<?php echo esc_attr( $slot ); ?>"><?php echo esc_html( $slot_label ); ?></label>
							<input type="hidden" name="apd_format[palette_slots][<?php echo esc_attr( $slot ); ?>]" value="<?php echo esc_attr( $selected_id ); ?>" data-apd-palette-slot-input <?php disabled( ! $use_slots ); ?>>
							<button type="button" class="apd-palette-menu__button" aria-haspopup="listbox" aria-expanded="false" aria-labelledby="apd-palette-slot-<?php echo esc_attr( $slot ); ?>">
								<span class="apd-palette-menu__current">
									<?php if ( is_array( $selected ) ) : ?>
										<span class="apd-palette-menu__label"><span class="apd-palette-choice__name"><?php echo esc_html( $selected['name'] ); ?></span><span class="apd-palette-choice__colors"><?php echo $apd_palette_chips( $selected ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- hex and label escaped in the helper. ?></span></span>
									<?php else : ?>
										<?php esc_html_e( 'Choose a palette', 'auto-plate-designer' ); ?>
									<?php endif; ?>
								</span>
							</button>
							<div class="apd-palette-menu__list" role="listbox" hidden>
								<?php foreach ( $active_palettes as $palette ) : ?>
									<button type="button" class="apd-palette-menu__option" role="option" data-apd-palette-option value="<?php echo esc_attr( $palette['id'] ); ?>" aria-selected="<?php echo $palette['id'] === $selected_id ? 'true' : 'false'; ?>">
										<span class="apd-palette-menu__label"><span class="apd-palette-choice__name"><?php echo esc_html( $palette['name'] ); ?></span><span class="apd-palette-choice__colors"><?php echo $apd_palette_chips( $palette ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- hex and label escaped in the helper. ?></span></span>
									</button>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
				<div data-apd-palette-checks<?php echo $use_slots ? ' hidden' : ''; ?>>
				<div data-apd-photo-palettes<?php echo $photo_holder ? '' : ' hidden'; ?>>
					<p><strong><?php esc_html_e( 'Strip and letters', 'auto-plate-designer' ); ?></strong></p>
					<?php
					$found_photo_palette = false;
					echo '<div class="apd-choice-list">';
					foreach ( $named_palettes as $palette ) {
						if ( empty( $palette['active'] ) || ! isset( $palette['purpose'] ) || ! in_array( $palette['purpose'], array( 'holder_strip', 'holder_text' ), true ) ) {
							continue;
						}
						$found_photo_palette = true;
						$chips               = '';
						foreach ( APD_Color_Palettes::palette_colors( $palette ) as $chip ) {
							$chips .= '<span class="apd-palette-chip" style="background:' . esc_attr( $chip['hex'] ) . ';" title="' . esc_attr( $chip['label'] ) . '"></span>';
						}
						printf(
							'<label class="apd-choice apd-palette-choice"><input type="checkbox" name="apd_format[palette_ids][]" value="%1$s" %2$s %3$s><span class="apd-palette-choice__name">%4$s</span><span class="apd-palette-choice__colors">%5$s</span></label>',
							esc_attr( $palette['id'] ),
							checked( in_array( $palette['id'], $format_palette_ids, true ), true, false ),
							disabled( ! $photo_holder, true, false ),
							esc_html( $palette['name'] ),
							$chips // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- hex and label escaped above.
						);
					}
					echo '</div>';
					if ( ! $found_photo_palette ) {
						echo '<p class="description">' . esc_html__( 'No palettes for this part yet. Add them under Color palette.', 'auto-plate-designer' ) . '</p>';
					}
					?>
				</div>
				<?php foreach ( $purpose_labels as $purpose => $purpose_label ) : ?>
					<div data-apd-color-cap="<?php echo esc_attr( $purpose ); ?>">
						<p><strong><?php echo esc_html( $purpose_label ); ?></strong></p>
						<?php
						$found_palette = false;
						echo '<div class="apd-choice-list">';
						foreach ( $named_palettes as $palette ) {
							if ( empty( $palette['active'] ) || ! isset( $palette['purpose'] ) || $palette['purpose'] !== $purpose ) {
								continue;
							}
							$found_palette = true;
							$chips         = '';
							foreach ( APD_Color_Palettes::palette_colors( $palette ) as $chip ) {
								$chips .= '<span class="apd-palette-chip" style="background:' . esc_attr( $chip['hex'] ) . ';" title="' . esc_attr( $chip['label'] ) . '"></span>';
							}
							printf(
								'<label class="apd-choice apd-palette-choice"><input type="checkbox" name="apd_format[palette_ids][]" value="%1$s" %2$s><span class="apd-palette-choice__name">%3$s</span><span class="apd-palette-choice__colors">%4$s</span></label>',
								esc_attr( $palette['id'] ),
								checked( in_array( $palette['id'], $format_palette_ids, true ), true, false ),
								esc_html( $palette['name'] ),
								$chips // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- hex and label escaped above.
							);
						}
						echo '</div>';
						if ( ! $found_palette ) {
							echo '<p class="description">' . esc_html__( 'No palettes for this part yet. Add them under Color palette.', 'auto-plate-designer' ) . '</p>';
						}
						?>
					</div>
				<?php endforeach; ?>
				</div>
			</div>
			<?php submit_button( $editing['id'] ? __( 'Update format', 'auto-plate-designer' ) : __( 'Add format', 'auto-plate-designer' ) ); ?>
		</div>
	</form>
	<?php endif; ?>
</div>
