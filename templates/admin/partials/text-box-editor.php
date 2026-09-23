<?php
/**
 * Shared text-area overlay + numeric fields.
 *
 * @package Auto_Plate_Designer
 *
 * @var array{x: float, y: float, width: float, height: float, align: string, valign: string} $apd_text_box
 * @var string $apd_text_box_name Input name prefix, e.g. apd_format[text_box].
 * @var string $apd_text_box_ratio CSS aspect-ratio.
 * @var string $apd_text_box_image Full image URL or empty.
 * @var string $apd_text_box_help Description under the heading.
 * @var bool   $apd_text_box_show_stage Whether the overlay is visible.
 * @var bool   $apd_text_box_band Whether to paint an EU band overlay.
 * @var bool   $apd_text_box_studio White padded stage larger than the plate.
 * @var string $apd_text_box_sample Sample letters drawn in the text box.
 * @var array{x: float, y: float, width: float, height: float} $apd_band_box
 * @var string $apd_band_image Country-band preview URL.
 * @var bool   $apd_show_frame Whether the millimetre frame overlay is active.
 * @var int    $apd_border_width Frame thickness in millimetres.
 * @var string $apd_border_color Frame hex color.
 * @var int    $apd_plate_width Plate width in millimetres.
 * @var int    $apd_plate_height Plate height in millimetres.
 */

defined( 'ABSPATH' ) || exit;

$apd_text_box_type        = isset( $apd_text_box_type ) ? (string) $apd_text_box_type : 'eu';
$apd_text_box             = APD_Formats::sanitize_text_box( isset( $apd_text_box ) ? $apd_text_box : array(), $apd_text_box_type );
$apd_text_box_name        = isset( $apd_text_box_name ) ? (string) $apd_text_box_name : 'apd_format[text_box]';
$apd_text_box_ratio       = isset( $apd_text_box_ratio ) ? (string) $apd_text_box_ratio : '520 / 110';
$apd_text_box_image       = isset( $apd_text_box_image ) ? (string) $apd_text_box_image : '';
$apd_text_box_help        = isset( $apd_text_box_help ) ? (string) $apd_text_box_help : '';
$apd_text_box_show_stage  = ! empty( $apd_text_box_show_stage );
$apd_text_box_band        = ! empty( $apd_text_box_band );
$apd_text_box_studio      = ! empty( $apd_text_box_studio );
$apd_text_box_sample      = isset( $apd_text_box_sample ) && '' !== (string) $apd_text_box_sample ? (string) $apd_text_box_sample : __( 'TEXT', 'auto-plate-designer' );
$apd_show_frame           = ! empty( $apd_show_frame );
$apd_border_width         = isset( $apd_border_width ) ? (int) $apd_border_width : 8;
$apd_border_color         = isset( $apd_border_color ) ? (string) $apd_border_color : '#000000';
$apd_plate_width          = isset( $apd_plate_width ) ? max( 1, (int) $apd_plate_width ) : 520;
$apd_plate_height         = isset( $apd_plate_height ) ? max( 1, (int) $apd_plate_height ) : 110;
$apd_band_image           = isset( $apd_band_image ) ? (string) $apd_band_image : '';
$apd_band_box             = APD_Formats::sanitize_band_box(
	isset( $apd_band_box ) ? $apd_band_box : array(),
	$apd_plate_width,
	$apd_plate_height,
	'left'
);
$frame_inset_x            = round( ( $apd_border_width / $apd_plate_width ) * 100, 2 );
$frame_inset_y            = round( ( $apd_border_width / $apd_plate_height ) * 100, 2 );
$apd_band_fields_disabled = ! empty( $apd_band_fields_disabled );
$canvas_max               = (int) APD_Formats::CANVAS_DISPLAY_MAX_PX;
$apd_show_metric_labels   = ! empty( $apd_text_box_studio );
$apd_two_rows             = ! empty( $apd_two_rows );
$apd_letter_align         = isset( $apd_text_box['letter_align'] ) ? (string) $apd_text_box['letter_align'] : 'justify';
$apd_number_align         = isset( $apd_text_box['number_align'] ) ? (string) $apd_text_box['number_align'] : 'justify';
$apd_align_choices        = array(
	'left'    => __( 'Left', 'auto-plate-designer' ),
	'center'  => __( 'Center', 'auto-plate-designer' ),
	'right'   => __( 'Right', 'auto-plate-designer' ),
	'justify' => __( 'Space between', 'auto-plate-designer' ),
);
$apd_sample_parts         = preg_split( '/\s+/', trim( $apd_text_box_sample ), 2 );
$apd_sample_letters       = isset( $apd_sample_parts[0] ) ? (string) $apd_sample_parts[0] : '';
$apd_sample_numbers       = isset( $apd_sample_parts[1] ) ? preg_replace( '/\s+/', '', (string) $apd_sample_parts[1] ) : '';
if ( $apd_two_rows && '' !== $apd_sample_letters ) {
	$letter_chars = preg_split( '//u', $apd_sample_letters, -1, PREG_SPLIT_NO_EMPTY );
	$letter_pairs = array();
	if ( is_array( $letter_chars ) ) {
		$count = count( $letter_chars );
		for ( $i = 0; $i < $count; $i += 2 ) {
			$letter_pairs[] = implode( '', array_slice( $letter_chars, $i, 2 ) );
		}
	}
	$apd_sample_letters = implode( ' ', $letter_pairs );
}
$apd_align_css            = static function ( $align ) {
	if ( 'left' === $align ) {
		return 'flex-start';
	}
	if ( 'right' === $align ) {
		return 'flex-end';
	}
	if ( 'justify' === $align ) {
		return 'space-between';
	}
	return 'center';
};
$apd_glyphs               = static function ( $text ) {
	$chars = preg_split( '//u', (string) $text, -1, PREG_SPLIT_NO_EMPTY );
	$html  = '';

	if ( ! is_array( $chars ) ) {
		return '';
	}

	foreach ( $chars as $char ) {
		if ( ' ' === $char ) {
			$html .= '<span>&nbsp;</span>';
			continue;
		}
		$html .= '<span>' . esc_html( $char ) . '</span>';
	}

	return $html;
};
$apd_studio_attrs         = '';
if ( $apd_text_box_studio ) {
	$apd_studio_attrs = 'data-apd-plate-studio';
	if ( ! $apd_text_box_show_stage ) {
		$apd_studio_attrs .= ' hidden';
	}
}
?>
<?php if ( '' !== $apd_text_box_help ) : ?>
	<p class="description"><?php echo esc_html( $apd_text_box_help ); ?></p>
<?php endif; ?>
<div class="<?php echo $apd_text_box_studio ? 'apd-plate-studio' : ''; ?>" <?php echo $apd_studio_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> style="--apd-canvas-max: <?php echo esc_attr( (string) $canvas_max ); ?>px;">
	<div class="apd-text-box-wrap<?php echo $apd_text_box_studio ? ' apd-text-box-wrap--studio' : ''; ?>" data-apd-text-box-wrap <?php echo $apd_text_box_show_stage ? '' : 'hidden'; ?>>
		<div class="apd-text-box-stage" data-apd-text-box-stage style="aspect-ratio: <?php echo esc_attr( $apd_text_box_ratio ); ?>;">
			<div class="apd-text-box-schematic" data-apd-text-box-schematic <?php echo $apd_text_box_image ? 'hidden' : ''; ?>>
				<span class="apd-frame-fill" data-apd-frame-fill <?php echo $apd_show_frame ? '' : 'hidden'; ?>></span>
			</div>
			<img src="<?php echo esc_url( $apd_text_box_image ); ?>" alt="" data-apd-text-box-image <?php echo $apd_text_box_image ? '' : 'hidden'; ?>>
			<div
				class="apd-frame-box"
				data-apd-frame-box
				<?php echo $apd_show_frame ? '' : 'hidden'; ?>
				style="left: <?php echo esc_attr( (string) $frame_inset_x ); ?>%; top: <?php echo esc_attr( (string) $frame_inset_y ); ?>%; width: <?php echo esc_attr( (string) round( 100 - ( $frame_inset_x * 2 ), 2 ) ); ?>%; height: <?php echo esc_attr( (string) round( 100 - ( $frame_inset_y * 2 ), 2 ) ); ?>%; border-color: <?php echo esc_attr( $apd_border_color ); ?>;"
			>
				<span class="apd-frame-handle" data-apd-frame-handle="nw"></span>
				<span class="apd-frame-handle" data-apd-frame-handle="ne"></span>
				<span class="apd-frame-handle" data-apd-frame-handle="sw"></span>
				<span class="apd-frame-handle" data-apd-frame-handle="se"></span>
			</div>
			<div
				class="apd-band-box"
				data-apd-band-box
				<?php echo ( $apd_text_box_band && ! $apd_band_fields_disabled ) ? '' : 'hidden'; ?>
				style="left: <?php echo esc_attr( (string) $apd_band_box['x'] ); ?>%; top: <?php echo esc_attr( (string) $apd_band_box['y'] ); ?>%; width: <?php echo esc_attr( (string) $apd_band_box['width'] ); ?>%; height: <?php echo esc_attr( (string) $apd_band_box['height'] ); ?>%;"
			>
				<?php if ( $apd_band_image ) : ?>
					<img src="<?php echo esc_url( $apd_band_image ); ?>" alt="" data-apd-band-preview>
				<?php else : ?>
					<span class="apd-band-box-fill" data-apd-band-preview></span>
				<?php endif; ?>
				<span class="apd-text-box-handle" data-apd-band-handle="nw"></span>
				<span class="apd-text-box-handle" data-apd-band-handle="ne"></span>
				<span class="apd-text-box-handle" data-apd-band-handle="sw"></span>
				<span class="apd-text-box-handle" data-apd-band-handle="se"></span>
			</div>
			<div
				class="apd-text-box"
				data-apd-text-box
				style="left: <?php echo esc_attr( (string) $apd_text_box['x'] ); ?>%; top: <?php echo esc_attr( (string) $apd_text_box['y'] ); ?>%; width: <?php echo esc_attr( (string) $apd_text_box['width'] ); ?>%; height: <?php echo esc_attr( (string) $apd_text_box['height'] ); ?>%;"
			>
				<span class="apd-text-box-sample<?php echo $apd_two_rows ? ' apd-text-box-sample--rows' : ''; ?>" data-apd-text-box-sample data-fallback="<?php echo esc_attr( $apd_text_box_sample ); ?>">
					<?php if ( $apd_two_rows ) : ?>
						<span class="apd-text-row" data-apd-text-row="letters" style="justify-content: <?php echo esc_attr( $apd_align_css( $apd_letter_align ) ); ?>;"><?php echo $apd_glyphs( $apd_sample_letters ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<span class="apd-text-row" data-apd-text-row="numbers" style="justify-content: <?php echo esc_attr( $apd_align_css( $apd_number_align ) ); ?>;"><?php echo $apd_glyphs( $apd_sample_numbers ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<?php else : ?>
						<?php echo esc_html( $apd_text_box_sample ); ?>
					<?php endif; ?>
				</span>
				<span class="apd-text-box-handle" data-apd-handle="nw"></span>
				<span class="apd-text-box-handle" data-apd-handle="ne"></span>
				<span class="apd-text-box-handle" data-apd-handle="sw"></span>
				<span class="apd-text-box-handle" data-apd-handle="se"></span>
			</div>
		</div>
	</div>
	<p class="apd-text-box-actions">
		<button type="button" class="button" data-apd-center-text-box><?php esc_html_e( 'Center text area', 'auto-plate-designer' ); ?></button>
	</p>
</div>
<?php if ( $apd_text_box_band ) : ?>
	<div class="apd-metric-section apd-band-box-fields"<?php echo $apd_band_fields_disabled ? ' hidden' : ''; ?>>
	<?php if ( $apd_show_metric_labels ) : ?>
		<p class="apd-metric-heading"><?php esc_html_e( 'Country preset', 'auto-plate-designer' ); ?></p>
	<?php endif; ?>
	<div class="apd-metric-grid apd-text-box-fields">
		<label class="apd-metric"><?php echo esc_html__( 'Band X', 'auto-plate-designer' ); ?>
			<span class="apd-metric__control">
				<input type="number" name="apd_format[band_box][x]" value="<?php echo esc_attr( (string) $apd_band_box['x'] ); ?>" min="0" max="96" step="0.1" data-apd-band-box-input="x" <?php disabled( $apd_band_fields_disabled ); ?>>
				<span class="apd-metric__unit">%</span>
			</span>
		</label>
		<label class="apd-metric"><?php echo esc_html__( 'Band Y', 'auto-plate-designer' ); ?>
			<span class="apd-metric__control">
				<input type="number" name="apd_format[band_box][y]" value="<?php echo esc_attr( (string) $apd_band_box['y'] ); ?>" min="0" max="60" step="0.1" data-apd-band-box-input="y" <?php disabled( $apd_band_fields_disabled ); ?>>
				<span class="apd-metric__unit">%</span>
			</span>
		</label>
		<label class="apd-metric"><?php echo esc_html__( 'Band width', 'auto-plate-designer' ); ?>
			<span class="apd-metric__control">
				<input type="number" name="apd_format[band_box][width]" value="<?php echo esc_attr( (string) $apd_band_box['width'] ); ?>" min="4" max="25" step="0.1" data-apd-band-box-input="width" <?php disabled( $apd_band_fields_disabled ); ?>>
				<span class="apd-metric__unit">%</span>
			</span>
		</label>
		<label class="apd-metric"><?php echo esc_html__( 'Band height', 'auto-plate-designer' ); ?>
			<span class="apd-metric__control">
				<input type="number" name="apd_format[band_box][height]" value="<?php echo esc_attr( (string) $apd_band_box['height'] ); ?>" min="40" max="100" step="0.1" data-apd-band-box-input="height" <?php disabled( $apd_band_fields_disabled ); ?>>
				<span class="apd-metric__unit">%</span>
			</span>
		</label>
	</div>
	</div>
<?php endif; ?>
<?php if ( $apd_show_metric_labels ) : ?>
	<p class="apd-metric-heading"><?php esc_html_e( 'Text area', 'auto-plate-designer' ); ?></p>
<?php endif; ?>
<div class="apd-metric-grid apd-text-box-fields">
	<label class="apd-metric"><?php echo esc_html__( 'X', 'auto-plate-designer' ); ?>
		<span class="apd-metric__control">
			<input type="number" name="<?php echo esc_attr( $apd_text_box_name ); ?>[x]" value="<?php echo esc_attr( (string) $apd_text_box['x'] ); ?>" min="0" max="95" step="0.1" data-apd-text-box-input="x">
			<span class="apd-metric__unit">%</span>
		</span>
	</label>
	<label class="apd-metric"><?php echo esc_html__( 'Y', 'auto-plate-designer' ); ?>
		<span class="apd-metric__control">
			<input type="number" name="<?php echo esc_attr( $apd_text_box_name ); ?>[y]" value="<?php echo esc_attr( (string) $apd_text_box['y'] ); ?>" min="0" max="95" step="0.1" data-apd-text-box-input="y">
			<span class="apd-metric__unit">%</span>
		</span>
	</label>
	<label class="apd-metric"><?php echo esc_html__( 'Width', 'auto-plate-designer' ); ?>
		<span class="apd-metric__control">
			<input type="number" name="<?php echo esc_attr( $apd_text_box_name ); ?>[width]" value="<?php echo esc_attr( (string) $apd_text_box['width'] ); ?>" min="5" max="100" step="0.1" data-apd-text-box-input="width">
			<span class="apd-metric__unit">%</span>
		</span>
	</label>
	<label class="apd-metric"><?php echo esc_html__( 'Height', 'auto-plate-designer' ); ?>
		<span class="apd-metric__control">
			<input type="number" name="<?php echo esc_attr( $apd_text_box_name ); ?>[height]" value="<?php echo esc_attr( (string) $apd_text_box['height'] ); ?>" min="5" max="100" step="0.1" data-apd-text-box-input="height">
			<span class="apd-metric__unit">%</span>
		</span>
	</label>
	<label class="apd-metric" data-apd-single-align<?php echo $apd_two_rows ? ' hidden' : ''; ?>><?php echo esc_html__( 'Align', 'auto-plate-designer' ); ?>
		<span class="apd-metric__control">
			<select name="<?php echo esc_attr( $apd_text_box_name ); ?>[align]" data-apd-text-box-input="align">
				<option value="left" <?php selected( $apd_text_box['align'], 'left' ); ?>><?php esc_html_e( 'Left', 'auto-plate-designer' ); ?></option>
				<option value="center" <?php selected( $apd_text_box['align'], 'center' ); ?>><?php esc_html_e( 'Center', 'auto-plate-designer' ); ?></option>
				<option value="right" <?php selected( $apd_text_box['align'], 'right' ); ?>><?php esc_html_e( 'Right', 'auto-plate-designer' ); ?></option>
			</select>
		</span>
	</label>
	<label class="apd-metric"><?php echo esc_html__( 'Vertical', 'auto-plate-designer' ); ?>
		<span class="apd-metric__control">
			<select name="<?php echo esc_attr( $apd_text_box_name ); ?>[valign]" data-apd-text-box-input="valign">
				<option value="top" <?php selected( $apd_text_box['valign'], 'top' ); ?>><?php esc_html_e( 'Top', 'auto-plate-designer' ); ?></option>
				<option value="middle" <?php selected( $apd_text_box['valign'], 'middle' ); ?>><?php esc_html_e( 'Middle', 'auto-plate-designer' ); ?></option>
				<option value="bottom" <?php selected( $apd_text_box['valign'], 'bottom' ); ?>><?php esc_html_e( 'Bottom', 'auto-plate-designer' ); ?></option>
			</select>
		</span>
	</label>
</div>
<div class="apd-row-styles" data-apd-row-styles<?php echo $apd_two_rows ? '' : ' hidden'; ?>>
	<div class="apd-row-styles__group">
		<p class="apd-row-styles__label"><?php esc_html_e( 'Letters', 'auto-plate-designer' ); ?></p>
		<div class="apd-row-styles__btns" role="group" aria-label="<?php esc_attr_e( 'Letters', 'auto-plate-designer' ); ?>">
			<?php foreach ( $apd_align_choices as $apd_align_value => $apd_align_label ) : ?>
				<button type="button" class="button<?php echo $apd_letter_align === $apd_align_value ? ' is-active' : ''; ?>" data-apd-row-align="letters" data-apd-align="<?php echo esc_attr( $apd_align_value ); ?>" aria-pressed="<?php echo $apd_letter_align === $apd_align_value ? 'true' : 'false'; ?>"><?php echo esc_html( $apd_align_label ); ?></button>
			<?php endforeach; ?>
		</div>
		<input type="hidden" name="<?php echo esc_attr( $apd_text_box_name ); ?>[letter_align]" value="<?php echo esc_attr( $apd_letter_align ); ?>" data-apd-text-box-input="letter_align">
	</div>
	<div class="apd-row-styles__group">
		<p class="apd-row-styles__label"><?php esc_html_e( 'Numbers', 'auto-plate-designer' ); ?></p>
		<div class="apd-row-styles__btns" role="group" aria-label="<?php esc_attr_e( 'Numbers', 'auto-plate-designer' ); ?>">
			<?php foreach ( $apd_align_choices as $apd_align_value => $apd_align_label ) : ?>
				<button type="button" class="button<?php echo $apd_number_align === $apd_align_value ? ' is-active' : ''; ?>" data-apd-row-align="numbers" data-apd-align="<?php echo esc_attr( $apd_align_value ); ?>" aria-pressed="<?php echo $apd_number_align === $apd_align_value ? 'true' : 'false'; ?>"><?php echo esc_html( $apd_align_label ); ?></button>
			<?php endforeach; ?>
		</div>
		<input type="hidden" name="<?php echo esc_attr( $apd_text_box_name ); ?>[number_align]" value="<?php echo esc_attr( $apd_number_align ); ?>" data-apd-text-box-input="number_align">
	</div>
</div>
