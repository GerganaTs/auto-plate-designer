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
$apd_text_box_design      = ! empty( $apd_text_box_design );
$apd_text_box_sample      = isset( $apd_text_box_sample ) && '' !== (string) $apd_text_box_sample ? (string) $apd_text_box_sample : __( 'TEXT', 'auto-plate-designer' );
$apd_show_frame           = ! empty( $apd_show_frame );
$apd_fixed_frame_mm       = isset( $apd_fixed_frame_mm ) ? max( 0, (int) $apd_fixed_frame_mm ) : 0;
$apd_paint_frame          = $apd_show_frame || $apd_fixed_frame_mm > 0;
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
$canvas_max               = isset( $apd_canvas_max ) ? max( 80, (int) $apd_canvas_max ) : (int) APD_Formats::CANVAS_DISPLAY_MAX_PX;
$apd_show_metric_labels   = ! empty( $apd_text_box_studio );
$apd_two_rows             = ! empty( $apd_two_rows );
$apd_split_ui             = ! empty( $apd_split_ui );
$apd_split_on             = $apd_split_ui && ! empty( $apd_split_on );
$apd_split_name           = isset( $apd_split_name ) ? (string) $apd_split_name : '';
$apd_right_box_name       = isset( $apd_right_box_name ) ? (string) $apd_right_box_name : '';
$apd_side_left_name       = isset( $apd_side_left_name ) ? (string) $apd_side_left_name : '';
$apd_side_right_name      = isset( $apd_side_right_name ) ? (string) $apd_side_right_name : '';
$apd_split_class          = isset( $apd_split_class ) ? (string) $apd_split_class : '';
$apd_split_row_hidden     = ! empty( $apd_split_row_hidden );
$apd_side_left_max        = isset( $apd_side_left_max ) ? (int) $apd_side_left_max : APD_Formats::US_SIDE_LEFT_MAX;
$apd_side_right_max       = isset( $apd_side_right_max ) ? (int) $apd_side_right_max : APD_Formats::US_SIDE_RIGHT_MAX;
if ( $apd_side_left_max < 1 ) {
	$apd_side_left_max = APD_Formats::US_SIDE_LEFT_MAX;
}
if ( $apd_side_right_max < 1 ) {
	$apd_side_right_max = APD_Formats::US_SIDE_RIGHT_MAX;
}
$apd_text_box_right = isset( $apd_text_box_right ) && is_array( $apd_text_box_right ) ? $apd_text_box_right : array();
if ( $apd_split_ui ) {
	if ( ! isset( $apd_text_box_right['x'] ) && ! isset( $apd_text_box_right['width'] ) ) {
		$apd_seed           = APD_Formats::seed_side_boxes( $apd_text_box );
		$apd_text_box_right = $apd_seed[1];
	} else {
		$apd_linked         = APD_Formats::link_side_boxes( $apd_text_box, $apd_text_box_right );
		$apd_text_box_right = $apd_linked[1];
		if ( $apd_split_on ) {
			$apd_text_box = $apd_linked[0];
		}
	}
}
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
$apd_row_has_space        = static function ( $align, $text ) {
	return 'justify' === $align && false !== mb_strpos( (string) $text, ' ' );
};
$apd_glyphs               = static function ( $text, $grow_spaces ) {
	$chars = preg_split( '//u', (string) $text, -1, PREG_SPLIT_NO_EMPTY );
	$html  = '';

	if ( ! is_array( $chars ) ) {
		return '';
	}

	foreach ( $chars as $char ) {
		if ( ' ' === $char ) {
			$html .= $grow_spaces ? '<span class="apd-text-gap">&nbsp;</span>' : '<span>&nbsp;</span>';
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
	<?php if ( $apd_split_ui ) : ?>
		<p class="apd-split-toggle <?php echo esc_attr( $apd_split_class ); ?>"<?php echo $apd_split_row_hidden ? ' hidden' : ''; ?>>
			<label class="apd-choice">
				<input type="checkbox" name="<?php echo esc_attr( $apd_split_name ); ?>" value="1" data-apd-split-text <?php checked( $apd_split_on ); ?>>
				<?php esc_html_e( 'Two text fields side by side', 'auto-plate-designer' ); ?>
			</label>
			<span class="description"><?php esc_html_e( 'The two boxes share a height and move together. Each width and the gap between them are set separately.', 'auto-plate-designer' ); ?></span>
		</p>
	<?php endif; ?>
	<div class="apd-text-box-wrap<?php echo $apd_text_box_studio ? ' apd-text-box-wrap--studio' : ''; ?>" data-apd-text-box-wrap <?php echo $apd_text_box_show_stage ? '' : 'hidden'; ?>>
		<?php
		$apd_group_presets = isset( $apd_group_presets ) && is_array( $apd_group_presets ) ? $apd_group_presets : array();
		$apd_region_preset = isset( $apd_region_preset ) && is_array( $apd_region_preset ) ? $apd_region_preset : array();
		$apd_group_box_2   = isset( $apd_group_box_2 ) && is_array( $apd_group_box_2 ) ? $apd_group_box_2 : array( 'x' => 40, 'y' => 8, 'width' => 18, 'height' => 84, 'align' => 'center', 'valign' => 'middle' );
		$apd_group_box_3   = isset( $apd_group_box_3 ) && is_array( $apd_group_box_3 ) ? $apd_group_box_3 : array( 'x' => 62, 'y' => 8, 'width' => 16, 'height' => 84, 'align' => 'center', 'valign' => 'middle' );
		$apd_region_box    = isset( $apd_region_box ) && is_array( $apd_region_box ) ? $apd_region_box : array( 'x' => 33.5, 'y' => 8.7, 'width' => 10.9, 'height' => 82.1 );
		$apd_date_month    = isset( $apd_date_month ) && is_array( $apd_date_month ) ? $apd_date_month : array( 'x' => 93, 'y' => 14, 'width' => 5.4, 'height' => 15.8, 'align' => 'right', 'valign' => 'top' );
		$apd_date_year     = isset( $apd_date_year ) && is_array( $apd_date_year ) ? $apd_date_year : array( 'x' => 93, 'y' => 70.2, 'width' => 5.4, 'height' => 15.8, 'align' => 'right', 'valign' => 'bottom' );
		$apd_show_groups   = empty( $apd_text_box_design );
		?>
		<div class="apd-text-box-stage" data-apd-text-box-stage<?php echo $apd_text_box_design ? ' data-apd-design-stage' : ''; ?> style="aspect-ratio: <?php echo esc_attr( $apd_text_box_ratio ); ?>;"<?php echo $apd_show_groups ? ' data-apd-group-presets="' . esc_attr( wp_json_encode( $apd_group_presets ) ) . '" data-apd-region-preset="' . esc_attr( wp_json_encode( $apd_region_preset ) ) . '"' : ''; ?>>
			<div class="apd-text-box-schematic" data-apd-text-box-schematic <?php echo $apd_text_box_image ? 'hidden' : ''; ?>>
				<span class="apd-frame-fill" data-apd-frame-fill <?php echo $apd_paint_frame ? '' : 'hidden'; ?>></span>
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
				class="apd-band-box apd-band-box--mirror"
				data-apd-band-mirror
				hidden
				style="left: <?php echo esc_attr( (string) ( isset( $apd_fr_region_box['x'] ) ? $apd_fr_region_box['x'] : 92.3 ) ); ?>%; top: <?php echo esc_attr( (string) $apd_band_box['y'] ); ?>%; width: <?php echo esc_attr( (string) $apd_band_box['width'] ); ?>%; height: <?php echo esc_attr( (string) $apd_band_box['height'] ); ?>%;"
			>
				<span class="apd-band-box-fill"></span>
			</div>
			<div
				class="apd-text-box"
				data-apd-text-box
				style="left: <?php echo esc_attr( (string) $apd_text_box['x'] ); ?>%; top: <?php echo esc_attr( (string) $apd_text_box['y'] ); ?>%; width: <?php echo esc_attr( (string) $apd_text_box['width'] ); ?>%; height: <?php echo esc_attr( (string) $apd_text_box['height'] ); ?>%;"
			>
				<span class="apd-text-box-sample<?php echo $apd_two_rows ? ' apd-text-box-sample--rows' : ''; ?>" data-apd-text-box-sample data-fallback="<?php echo esc_attr( $apd_text_box_sample ); ?>">
					<?php if ( $apd_two_rows ) : ?>
						<span class="apd-text-row" data-apd-text-row="letters" data-apd-gap="<?php echo $apd_row_has_space( $apd_letter_align, $apd_sample_letters ) ? 'spaces' : ''; ?>" style="justify-content: <?php echo esc_attr( $apd_row_has_space( $apd_letter_align, $apd_sample_letters ) ? 'flex-start' : $apd_align_css( $apd_letter_align ) ); ?>;"><?php echo $apd_glyphs( $apd_sample_letters, $apd_row_has_space( $apd_letter_align, $apd_sample_letters ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<span class="apd-text-row" data-apd-text-row="numbers" data-apd-gap="<?php echo $apd_row_has_space( $apd_number_align, $apd_sample_numbers ) ? 'spaces' : ''; ?>" style="justify-content: <?php echo esc_attr( $apd_row_has_space( $apd_number_align, $apd_sample_numbers ) ? 'flex-start' : $apd_align_css( $apd_number_align ) ); ?>;"><?php echo $apd_glyphs( $apd_sample_numbers, $apd_row_has_space( $apd_number_align, $apd_sample_numbers ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<?php else : ?>
						<?php echo esc_html( $apd_split_on ? 'CA' : $apd_text_box_sample ); ?>
					<?php endif; ?>
				</span>
				<span class="apd-text-box-handle" data-apd-handle="nw"></span>
				<span class="apd-text-box-handle" data-apd-handle="ne"></span>
				<span class="apd-text-box-handle" data-apd-handle="sw"></span>
				<span class="apd-text-box-handle" data-apd-handle="se"></span>
			</div>
			<?php
			$apd_slogan_box  = isset( $apd_slogan_box ) && is_array( $apd_slogan_box ) ? $apd_slogan_box : APD_Formats::default_slogan_box(
				array(
					'type'     => $apd_text_box_type,
					'width'    => $apd_plate_width,
					'height'   => $apd_plate_height,
					'band_box' => $apd_band_box,
				)
			);
			$apd_show_slogan = APD_Formats::is_english( $apd_text_box_type );
			?>
			<div
				class="apd-text-box apd-slogan-box"
				data-apd-extra-box="slogan"
				<?php echo $apd_show_slogan ? '' : 'hidden'; ?>
				style="left: <?php echo esc_attr( (string) $apd_slogan_box['x'] ); ?>%; top: <?php echo esc_attr( (string) $apd_slogan_box['y'] ); ?>%; width: <?php echo esc_attr( (string) $apd_slogan_box['width'] ); ?>%; height: <?php echo esc_attr( (string) $apd_slogan_box['height'] ); ?>%;"
			>
				<span class="apd-text-box-sample" data-apd-extra-sample>SLOGAN</span>
				<span class="apd-text-box-handle" data-apd-extra-handle="nw"></span>
				<span class="apd-text-box-handle" data-apd-extra-handle="ne"></span>
				<span class="apd-text-box-handle" data-apd-extra-handle="sw"></span>
				<span class="apd-text-box-handle" data-apd-extra-handle="se"></span>
			</div>
			<?php if ( $apd_show_groups ) : ?>
			<div class="apd-region-box" data-apd-extra-box="at_region" data-apd-extra-kind="region" hidden style="left: <?php echo esc_attr( (string) $apd_region_box['x'] ); ?>%; top: <?php echo esc_attr( (string) $apd_region_box['y'] ); ?>%; width: <?php echo esc_attr( (string) $apd_region_box['width'] ); ?>%; height: <?php echo esc_attr( (string) $apd_region_box['height'] ); ?>%;">
				<span class="apd-text-box-handle" data-apd-extra-handle="nw"></span>
				<span class="apd-text-box-handle" data-apd-extra-handle="ne"></span>
				<span class="apd-text-box-handle" data-apd-extra-handle="sw"></span>
				<span class="apd-text-box-handle" data-apd-extra-handle="se"></span>
			</div>
			<div class="apd-text-box" data-apd-extra-box="text_box_2" data-apd-extra-kind="text" data-apd-extra-index="1" hidden style="left: <?php echo esc_attr( (string) $apd_group_box_2['x'] ); ?>%; top: <?php echo esc_attr( (string) $apd_group_box_2['y'] ); ?>%; width: <?php echo esc_attr( (string) $apd_group_box_2['width'] ); ?>%; height: <?php echo esc_attr( (string) $apd_group_box_2['height'] ); ?>%;">
				<span class="apd-text-box-sample" data-apd-extra-sample>000</span>
				<span class="apd-text-box-handle" data-apd-extra-handle="nw"></span>
				<span class="apd-text-box-handle" data-apd-extra-handle="ne"></span>
				<span class="apd-text-box-handle" data-apd-extra-handle="sw"></span>
				<span class="apd-text-box-handle" data-apd-extra-handle="se"></span>
			</div>
			<div class="apd-text-box" data-apd-extra-box="text_box_3" data-apd-extra-kind="text" data-apd-extra-index="2" hidden style="left: <?php echo esc_attr( (string) $apd_group_box_3['x'] ); ?>%; top: <?php echo esc_attr( (string) $apd_group_box_3['y'] ); ?>%; width: <?php echo esc_attr( (string) $apd_group_box_3['width'] ); ?>%; height: <?php echo esc_attr( (string) $apd_group_box_3['height'] ); ?>%;">
				<span class="apd-text-box-sample" data-apd-extra-sample>AA</span>
				<span class="apd-text-box-handle" data-apd-extra-handle="nw"></span>
				<span class="apd-text-box-handle" data-apd-extra-handle="ne"></span>
				<span class="apd-text-box-handle" data-apd-extra-handle="sw"></span>
				<span class="apd-text-box-handle" data-apd-extra-handle="se"></span>
			</div>
			<div class="apd-text-box apd-date-box" data-apd-extra-box="fr_month" data-apd-extra-kind="date" hidden style="left: <?php echo esc_attr( (string) $apd_date_month['x'] ); ?>%; top: <?php echo esc_attr( (string) $apd_date_month['y'] ); ?>%; width: <?php echo esc_attr( (string) $apd_date_month['width'] ); ?>%; height: <?php echo esc_attr( (string) $apd_date_month['height'] ); ?>%;">
				<span class="apd-text-box-sample" data-apd-extra-sample>05</span>
				<span class="apd-text-box-handle" data-apd-extra-handle="nw"></span>
				<span class="apd-text-box-handle" data-apd-extra-handle="ne"></span>
				<span class="apd-text-box-handle" data-apd-extra-handle="sw"></span>
				<span class="apd-text-box-handle" data-apd-extra-handle="se"></span>
			</div>
			<div class="apd-region-code-box" data-apd-extra-box="fr_region_code" hidden style="left: <?php echo esc_attr( (string) ( isset( $apd_fr_region_box['x'] ) ? $apd_fr_region_box['x'] : 92.3 ) ); ?>%; top: <?php echo esc_attr( (string) ( isset( $apd_fr_region_box['y'] ) ? $apd_fr_region_box['y'] : 60.4 ) ); ?>%; width: <?php echo esc_attr( (string) ( isset( $apd_fr_region_box['width'] ) ? $apd_fr_region_box['width'] : 7.7 ) ); ?>%; height: <?php echo esc_attr( (string) ( isset( $apd_fr_region_box['height'] ) ? $apd_fr_region_box['height'] : 21.9 ) ); ?>%;">
				<span class="apd-text-box-sample"><?php echo esc_html( APD_Formats::FR_REGION_SAMPLE ); ?></span>
			</div>
			<div class="apd-text-box apd-date-box" data-apd-extra-box="fr_year" data-apd-extra-kind="date" hidden style="left: <?php echo esc_attr( (string) $apd_date_year['x'] ); ?>%; top: <?php echo esc_attr( (string) $apd_date_year['y'] ); ?>%; width: <?php echo esc_attr( (string) $apd_date_year['width'] ); ?>%; height: <?php echo esc_attr( (string) $apd_date_year['height'] ); ?>%;">
				<span class="apd-text-box-sample" data-apd-extra-sample>26</span>
				<span class="apd-text-box-handle" data-apd-extra-handle="nw"></span>
				<span class="apd-text-box-handle" data-apd-extra-handle="ne"></span>
				<span class="apd-text-box-handle" data-apd-extra-handle="sw"></span>
				<span class="apd-text-box-handle" data-apd-extra-handle="se"></span>
			</div>
			<?php endif; ?>
			<?php if ( $apd_split_ui ) : ?>
			<div
				class="apd-text-box"
				data-apd-text-box-right
				<?php echo $apd_split_on ? '' : 'hidden'; ?>
				style="left: <?php echo esc_attr( (string) $apd_text_box_right['x'] ); ?>%; top: <?php echo esc_attr( (string) $apd_text_box_right['y'] ); ?>%; width: <?php echo esc_attr( (string) $apd_text_box_right['width'] ); ?>%; height: <?php echo esc_attr( (string) $apd_text_box_right['height'] ); ?>%;"
			>
				<span class="apd-text-box-sample" data-apd-text-box-sample-right>1234</span>
				<span class="apd-text-box-handle" data-apd-handle="nw"></span>
				<span class="apd-text-box-handle" data-apd-handle="ne"></span>
				<span class="apd-text-box-handle" data-apd-handle="sw"></span>
				<span class="apd-text-box-handle" data-apd-handle="se"></span>
			</div>
			<?php endif; ?>
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
<?php if ( empty( $apd_text_box_design ) ) : ?>
	<input type="hidden" name="apd_format[text_box_metric]" value="" data-apd-text-box-metric>
	<p class="description apd-holder-only"<?php echo APD_Formats::is_holder( $apd_text_box_type ) ? '' : ' hidden'; ?>><?php esc_html_e( 'X, Y, width, and height are percentages of the white strip.', 'auto-plate-designer' ); ?></p>
<?php endif; ?>
<div class="apd-metric-grid apd-text-box-fields">
	<label class="apd-metric"><span data-apd-split-off<?php echo $apd_split_on ? ' hidden' : ''; ?>><?php echo esc_html__( 'X', 'auto-plate-designer' ); ?></span><span data-apd-split-on<?php echo ( $apd_split_ui && $apd_split_on ) ? '' : ' hidden'; ?>><?php echo esc_html__( 'Left X', 'auto-plate-designer' ); ?></span>
		<span class="apd-metric__control">
			<input type="number" name="<?php echo esc_attr( $apd_text_box_name ); ?>[x]" value="<?php echo esc_attr( (string) $apd_text_box['x'] ); ?>" min="0" max="95" step="0.01" data-apd-text-box-input="x">
			<span class="apd-metric__unit">%</span>
		</span>
	</label>
	<label class="apd-metric"><?php echo esc_html__( 'Y', 'auto-plate-designer' ); ?>
		<span class="apd-metric__control">
			<input type="number" name="<?php echo esc_attr( $apd_text_box_name ); ?>[y]" value="<?php echo esc_attr( (string) $apd_text_box['y'] ); ?>" min="0" max="95" step="0.01" data-apd-text-box-input="y">
			<span class="apd-metric__unit">%</span>
		</span>
	</label>
	<label class="apd-metric"><span data-apd-split-off<?php echo $apd_split_on ? ' hidden' : ''; ?>><?php echo esc_html__( 'Width', 'auto-plate-designer' ); ?></span><span data-apd-split-on<?php echo ( $apd_split_ui && $apd_split_on ) ? '' : ' hidden'; ?>><?php echo esc_html__( 'Left width', 'auto-plate-designer' ); ?></span>
		<span class="apd-metric__control">
			<input type="number" name="<?php echo esc_attr( $apd_text_box_name ); ?>[width]" value="<?php echo esc_attr( (string) $apd_text_box['width'] ); ?>" min="5" max="100" step="0.01" data-apd-text-box-input="width">
			<span class="apd-metric__unit">%</span>
		</span>
	</label>
	<label class="apd-metric"><?php echo esc_html__( 'Height', 'auto-plate-designer' ); ?>
		<span class="apd-metric__control">
			<input type="number" name="<?php echo esc_attr( $apd_text_box_name ); ?>[height]" value="<?php echo esc_attr( (string) $apd_text_box['height'] ); ?>" min="5" max="100" step="0.01" data-apd-text-box-input="height">
			<span class="apd-metric__unit">%</span>
		</span>
	</label>
	<label class="apd-metric" data-apd-single-align<?php echo $apd_two_rows ? ' hidden' : ''; ?>><span data-apd-split-off<?php echo $apd_split_on ? ' hidden' : ''; ?>><?php echo esc_html__( 'Align', 'auto-plate-designer' ); ?></span><span data-apd-split-on<?php echo ( $apd_split_ui && $apd_split_on ) ? '' : ' hidden'; ?>><?php echo esc_html__( 'Left align', 'auto-plate-designer' ); ?></span>
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
<?php if ( $apd_show_groups ) : ?>
<p class="apd-metric-heading" data-apd-group-fields="text_box_2" hidden><?php esc_html_e( 'Second text area', 'auto-plate-designer' ); ?></p>
<div class="apd-metric-grid apd-text-box-fields" data-apd-group-fields="text_box_2" hidden>
	<?php foreach ( array( 'x' => __( 'X', 'auto-plate-designer' ), 'y' => __( 'Y', 'auto-plate-designer' ), 'width' => __( 'Width', 'auto-plate-designer' ), 'height' => __( 'Height', 'auto-plate-designer' ) ) as $apd_field => $apd_field_label ) : ?>
		<label class="apd-metric"><?php echo esc_html( $apd_field_label ); ?>
			<span class="apd-metric__control">
				<input type="number" name="apd_format[text_box_2][<?php echo esc_attr( $apd_field ); ?>]" value="<?php echo esc_attr( (string) $apd_group_box_2[ $apd_field ] ); ?>" min="0" max="100" step="0.01" data-apd-extra-input="text_box_2" data-apd-extra-field="<?php echo esc_attr( $apd_field ); ?>" disabled>
				<span class="apd-metric__unit">%</span>
			</span>
		</label>
	<?php endforeach; ?>
	<input type="hidden" name="apd_format[text_box_2][align]" value="<?php echo esc_attr( (string) $apd_group_box_2['align'] ); ?>" data-apd-extra-input="text_box_2" data-apd-extra-field="align" disabled>
	<input type="hidden" name="apd_format[text_box_2][valign]" value="<?php echo esc_attr( (string) $apd_group_box_2['valign'] ); ?>" data-apd-extra-input="text_box_2" data-apd-extra-field="valign" disabled>
</div>
<p class="apd-metric-heading" data-apd-group-fields="text_box_3" hidden><?php esc_html_e( 'Third text area', 'auto-plate-designer' ); ?></p>
<div class="apd-metric-grid apd-text-box-fields" data-apd-group-fields="text_box_3" hidden>
	<?php foreach ( array( 'x' => __( 'X', 'auto-plate-designer' ), 'y' => __( 'Y', 'auto-plate-designer' ), 'width' => __( 'Width', 'auto-plate-designer' ), 'height' => __( 'Height', 'auto-plate-designer' ) ) as $apd_field => $apd_field_label ) : ?>
		<label class="apd-metric"><?php echo esc_html( $apd_field_label ); ?>
			<span class="apd-metric__control">
				<input type="number" name="apd_format[text_box_3][<?php echo esc_attr( $apd_field ); ?>]" value="<?php echo esc_attr( (string) $apd_group_box_3[ $apd_field ] ); ?>" min="0" max="100" step="0.01" data-apd-extra-input="text_box_3" data-apd-extra-field="<?php echo esc_attr( $apd_field ); ?>" disabled>
				<span class="apd-metric__unit">%</span>
			</span>
		</label>
	<?php endforeach; ?>
	<input type="hidden" name="apd_format[text_box_3][align]" value="<?php echo esc_attr( (string) $apd_group_box_3['align'] ); ?>" data-apd-extra-input="text_box_3" data-apd-extra-field="align" disabled>
	<input type="hidden" name="apd_format[text_box_3][valign]" value="<?php echo esc_attr( (string) $apd_group_box_3['valign'] ); ?>" data-apd-extra-input="text_box_3" data-apd-extra-field="valign" disabled>
</div>
<p class="apd-metric-heading" data-apd-group-fields="at_region" hidden><?php esc_html_e( 'Region box', 'auto-plate-designer' ); ?></p>
<div class="apd-metric-grid apd-text-box-fields" data-apd-group-fields="at_region" hidden>
	<?php foreach ( array( 'x' => __( 'X', 'auto-plate-designer' ), 'y' => __( 'Y', 'auto-plate-designer' ), 'width' => __( 'Width', 'auto-plate-designer' ), 'height' => __( 'Height', 'auto-plate-designer' ) ) as $apd_field => $apd_field_label ) : ?>
		<label class="apd-metric"><?php echo esc_html( $apd_field_label ); ?>
			<span class="apd-metric__control">
				<input type="number" name="apd_format[at_region][<?php echo esc_attr( $apd_field ); ?>]" value="<?php echo esc_attr( (string) $apd_region_box[ $apd_field ] ); ?>" min="0" max="100" step="0.01" data-apd-extra-input="at_region" data-apd-extra-field="<?php echo esc_attr( $apd_field ); ?>" disabled>
				<span class="apd-metric__unit">%</span>
			</span>
		</label>
	<?php endforeach; ?>
</div>
<p class="description" data-apd-group-fields="fr_month" hidden><?php esc_html_e( 'The month lines up with the top of the letters. The year lines up with the bottom. Drag a box to move it.', 'auto-plate-designer' ); ?></p>
<?php foreach ( array( 'fr_month' => array( __( 'Month', 'auto-plate-designer' ), $apd_date_month ), 'fr_year' => array( __( 'Year', 'auto-plate-designer' ), $apd_date_year ) ) as $apd_date_key => $apd_date_pair ) : ?>
<p class="apd-metric-heading" data-apd-group-fields="<?php echo esc_attr( $apd_date_key ); ?>" hidden><?php echo esc_html( $apd_date_pair[0] ); ?></p>
<div class="apd-metric-grid apd-text-box-fields" data-apd-group-fields="<?php echo esc_attr( $apd_date_key ); ?>" hidden>
	<?php foreach ( array( 'x' => __( 'X', 'auto-plate-designer' ), 'y' => __( 'Y', 'auto-plate-designer' ), 'width' => __( 'Width', 'auto-plate-designer' ), 'height' => __( 'Height', 'auto-plate-designer' ) ) as $apd_field => $apd_field_label ) : ?>
		<label class="apd-metric"><?php echo esc_html( $apd_field_label ); ?>
			<span class="apd-metric__control">
				<input type="number" name="apd_format[<?php echo esc_attr( $apd_date_key ); ?>][<?php echo esc_attr( $apd_field ); ?>]" value="<?php echo esc_attr( (string) $apd_date_pair[1][ $apd_field ] ); ?>" min="0" max="100" step="0.01" data-apd-extra-input="<?php echo esc_attr( $apd_date_key ); ?>" data-apd-extra-field="<?php echo esc_attr( $apd_field ); ?>" disabled>
				<span class="apd-metric__unit">%</span>
			</span>
		</label>
	<?php endforeach; ?>
	<input type="hidden" name="apd_format[<?php echo esc_attr( $apd_date_key ); ?>][align]" value="<?php echo esc_attr( (string) $apd_date_pair[1]['align'] ); ?>" data-apd-extra-input="<?php echo esc_attr( $apd_date_key ); ?>" data-apd-extra-field="align" disabled>
	<input type="hidden" name="apd_format[<?php echo esc_attr( $apd_date_key ); ?>][valign]" value="<?php echo esc_attr( (string) $apd_date_pair[1]['valign'] ); ?>" data-apd-extra-input="<?php echo esc_attr( $apd_date_key ); ?>" data-apd-extra-field="valign" disabled>
</div>
<?php endforeach; ?>
<p class="apd-metric-heading" data-apd-group-fields="slogan" <?php echo $apd_show_slogan ? '' : 'hidden'; ?>><?php esc_html_e( 'Slogan', 'auto-plate-designer' ); ?></p>
<p class="description" data-apd-group-fields="slogan" <?php echo $apd_show_slogan ? '' : 'hidden'; ?>><?php esc_html_e( 'Drag the slogan box to place it on the frame.', 'auto-plate-designer' ); ?></p>
<div class="apd-metric-grid apd-text-box-fields" data-apd-group-fields="slogan" <?php echo $apd_show_slogan ? '' : 'hidden'; ?>>
	<?php foreach ( array( 'x' => __( 'X', 'auto-plate-designer' ), 'y' => __( 'Y', 'auto-plate-designer' ), 'width' => __( 'Width', 'auto-plate-designer' ), 'height' => __( 'Height', 'auto-plate-designer' ) ) as $apd_field => $apd_field_label ) : ?>
		<label class="apd-metric"><?php echo esc_html( $apd_field_label ); ?>
			<span class="apd-metric__control">
				<input type="number" name="apd_format[slogan_box][<?php echo esc_attr( $apd_field ); ?>]" value="<?php echo esc_attr( (string) $apd_slogan_box[ $apd_field ] ); ?>" min="0" max="100" step="0.01" data-apd-extra-input="slogan" data-apd-extra-field="<?php echo esc_attr( $apd_field ); ?>" <?php disabled( ! $apd_show_slogan ); ?>>
				<span class="apd-metric__unit">%</span>
			</span>
		</label>
	<?php endforeach; ?>
	<input type="hidden" name="apd_format[slogan_box][align]" value="<?php echo esc_attr( (string) $apd_slogan_box['align'] ); ?>" data-apd-extra-input="slogan" data-apd-extra-field="align" <?php disabled( ! $apd_show_slogan ); ?>>
	<input type="hidden" name="apd_format[slogan_box][valign]" value="<?php echo esc_attr( (string) $apd_slogan_box['valign'] ); ?>" data-apd-extra-input="slogan" data-apd-extra-field="valign" <?php disabled( ! $apd_show_slogan ); ?>>
</div>
<?php endif; ?>
<?php if ( $apd_split_ui ) : ?>
<div class="apd-metric-grid apd-text-box-fields" data-apd-split-panel<?php echo $apd_split_on ? '' : ' hidden'; ?>>
	<label class="apd-metric"><?php echo esc_html__( 'Right X', 'auto-plate-designer' ); ?>
		<span class="apd-metric__control">
			<input type="number" name="<?php echo esc_attr( $apd_right_box_name ); ?>[x]" value="<?php echo esc_attr( (string) $apd_text_box_right['x'] ); ?>" min="0" max="95" step="0.01" data-apd-text-box-right-input="x">
			<span class="apd-metric__unit">%</span>
		</span>
	</label>
	<label class="apd-metric"><?php echo esc_html__( 'Right width', 'auto-plate-designer' ); ?>
		<span class="apd-metric__control">
			<input type="number" name="<?php echo esc_attr( $apd_right_box_name ); ?>[width]" value="<?php echo esc_attr( (string) $apd_text_box_right['width'] ); ?>" min="5" max="100" step="0.01" data-apd-text-box-right-input="width">
			<span class="apd-metric__unit">%</span>
		</span>
	</label>
	<label class="apd-metric"><?php echo esc_html__( 'Right align', 'auto-plate-designer' ); ?>
		<span class="apd-metric__control">
			<select name="<?php echo esc_attr( $apd_right_box_name ); ?>[align]" data-apd-text-box-right-input="align">
				<option value="left" <?php selected( $apd_text_box_right['align'], 'left' ); ?>><?php esc_html_e( 'Left', 'auto-plate-designer' ); ?></option>
				<option value="center" <?php selected( $apd_text_box_right['align'], 'center' ); ?>><?php esc_html_e( 'Center', 'auto-plate-designer' ); ?></option>
				<option value="right" <?php selected( $apd_text_box_right['align'], 'right' ); ?>><?php esc_html_e( 'Right', 'auto-plate-designer' ); ?></option>
			</select>
		</span>
	</label>
	<label class="apd-metric"><?php echo esc_html__( 'Left maximum', 'auto-plate-designer' ); ?>
		<input type="number" name="<?php echo esc_attr( $apd_side_left_name ); ?>" min="1" max="<?php echo esc_attr( (string) APD_Security::ABSOLUTE_MAX_CHARS ); ?>" value="<?php echo esc_attr( (string) $apd_side_left_max ); ?>">
	</label>
	<label class="apd-metric"><?php echo esc_html__( 'Right maximum', 'auto-plate-designer' ); ?>
		<input type="number" name="<?php echo esc_attr( $apd_side_right_name ); ?>" min="1" max="<?php echo esc_attr( (string) APD_Security::ABSOLUTE_MAX_CHARS ); ?>" value="<?php echo esc_attr( (string) $apd_side_right_max ); ?>">
	</label>
	<input type="hidden" name="<?php echo esc_attr( $apd_right_box_name ); ?>[y]" value="<?php echo esc_attr( (string) $apd_text_box_right['y'] ); ?>" data-apd-text-box-right-input="y">
	<input type="hidden" name="<?php echo esc_attr( $apd_right_box_name ); ?>[height]" value="<?php echo esc_attr( (string) $apd_text_box_right['height'] ); ?>" data-apd-text-box-right-input="height">
	<input type="hidden" name="<?php echo esc_attr( $apd_right_box_name ); ?>[valign]" value="<?php echo esc_attr( (string) $apd_text_box_right['valign'] ); ?>" data-apd-text-box-right-input="valign">
</div>
<?php endif; ?>
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
