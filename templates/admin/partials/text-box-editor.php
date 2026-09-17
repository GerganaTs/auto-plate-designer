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

$apd_text_box             = APD_Formats::sanitize_text_box( isset( $apd_text_box ) ? $apd_text_box : array() );
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
?>
<?php if ( '' !== $apd_text_box_help ) : ?>
	<p class="description"><?php echo esc_html( $apd_text_box_help ); ?></p>
<?php endif; ?>
<div class="<?php echo $apd_text_box_studio ? 'apd-plate-studio' : ''; ?>" <?php echo $apd_text_box_studio ? 'data-apd-plate-studio' : ''; ?> style="--apd-canvas-max: <?php echo esc_attr( (string) $canvas_max ); ?>px;">
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
				<span class="apd-text-box-sample" data-apd-text-box-sample><?php echo esc_html( $apd_text_box_sample ); ?></span>
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
	<div class="apd-metric-grid apd-text-box-fields apd-band-box-fields"<?php echo $apd_band_fields_disabled ? ' hidden' : ''; ?>>
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
	<label class="apd-metric"><?php echo esc_html__( 'Align', 'auto-plate-designer' ); ?>
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
