<?php
/**
 * Frontend product configurator markup.
 *
 * @package Auto_Plate_Designer
 *
 * @var array<string, mixed> $apd_payload Localized configurator data.
 */

defined( 'ABSPATH' ) || exit;

$format         = $apd_payload['format'];
$frame_w        = APD_Formats::preview_frame_width( isset( $format['type'] ) ? (string) $format['type'] : '', (int) $format['width'] );
$palettes       = $apd_payload['palettes'];
$i18n           = $apd_payload['i18n'];
$type           = $format['type'];
$two_row_fields = isset( $format['capabilities']['two_row'] )
	? ! empty( $format['capabilities']['two_row'] )
	: APD_Formats::uses_two_rows( $type );
$fonts          = $format['fonts'];
$presets        = $format['presets'];
$designs        = isset( $format['designs'] ) && is_array( $format['designs'] ) ? $format['designs'] : array();
$max            = (int) $format['max_chars'];
$multiline      = ! empty( $format['multiline'] );
$show_eu        = APD_Formats::uses_painted_plate( $type );
$show_country   = APD_Formats::uses_country_band( $type ) && empty( $format['capabilities']['country_locked'] );
$country_picker = $show_country && count( $presets ) > 1;
$show_designs   = APD_Formats::uses_plate_designs( $type ) && ! empty( $designs );
$show_strip     = APD_Formats::is_holder( $type );
$color_fields   = isset( $apd_payload['color_fields'] ) && is_array( $apd_payload['color_fields'] ) ? $apd_payload['color_fields'] : array();
$swatch_ui      = APD_Color_Palettes::swatch_display();
$default_text   = isset( $apd_payload['default_text'] ) && '' !== (string) $apd_payload['default_text']
	? (string) $apd_payload['default_text']
	: ( APD_Formats::is_atypical_300( $type ) && $two_row_fields ? "CA\n1234" : APD_Formats::sample_plate_text( $type ) );
$restore        = isset( $apd_payload['cart_restore'] ) && is_array( $apd_payload['cart_restore'] ) ? $apd_payload['cart_restore'] : array();
if ( isset( $restore['text'] ) && '' !== (string) $restore['text'] ) {
	$default_text = (string) $restore['text'];
}
$scripts = isset( $apd_payload['scripts'] ) && is_array( $apd_payload['scripts'] ) ? array_values( $apd_payload['scripts'] ) : array();
$cyr_default = isset( $apd_payload['default_text_cyrillic'] ) ? (string) $apd_payload['default_text_cyrillic'] : '';
$combined_default = isset( $apd_payload['default_text_combined'] ) ? (string) $apd_payload['default_text_combined'] : '';
$script_value = isset( $restore['script'] ) && in_array( (string) $restore['script'], $scripts, true ) ? (string) $restore['script'] : ( isset( $scripts[0] ) ? (string) $scripts[0] : '' );
if ( ! isset( $restore['text'] ) && 'cyrillic' === $script_value ) {
	$default_text = $cyr_default;
} elseif ( ! isset( $restore['text'] ) && 'combined' === $script_value ) {
	$default_text = $combined_default;
}
$holder_product = APD_Formats::is_holder( $type );
$comment_value = isset( $restore['comment'] ) ? (string) $restore['comment'] : '';
$default_font   = APD_Formats::default_font_id( $fonts, $type );
if ( ! empty( $restore['font_id'] ) ) {
	$default_font = (string) $restore['font_id'];
}
$default_slogan_font = $default_font;
if ( ! empty( $restore['slogan_font_id'] ) ) {
	$default_slogan_font = (string) $restore['slogan_font_id'];
}
$default_preset = APD_Formats::opening_preset_id(
	$presets,
	isset( $apd_payload['default_preset_id'] ) && '' !== (string) $apd_payload['default_preset_id']
		? (string) $apd_payload['default_preset_id']
		: ( isset( $format['default_preset_id'] ) ? (string) $format['default_preset_id'] : '' )
);
if ( ! empty( $restore['preset_id'] ) ) {
	$default_preset = (string) $restore['preset_id'];
}
$default_design = ! empty( $designs ) ? $designs[0]['id'] : '';
if ( ! empty( $restore['design_id'] ) ) {
	$default_design = (string) $restore['design_id'];
}
$us_side_limits = APD_Formats::active_us_side_limits( $format, $default_design );
$show_frame     = APD_Formats::offers_frame_choice( $type ) && ! APD_Formats::offers_uncolored_frame( $type );
$plain_frame    = APD_Formats::offers_uncolored_frame( $type ) && empty( $format['no_frame'] ) && ( isset( $format['border_width'] ) ? (int) $format['border_width'] : 0 ) > 0;
$hide_plain_frame = ! empty( $apd_payload['hide_plain_frame'] );
$plain_on       = $plain_frame && ! $hide_plain_frame && ! empty( $restore['frame_plain'] );
$legacy_frame_off = ! $hide_plain_frame && $plain_frame && array_key_exists( 'no_frame', $restore ) && ! array_key_exists( 'frame_plain', $restore ) && ! empty( $restore['no_frame'] );
$frame_on       = $show_frame && $show_eu && empty( $format['no_frame'] );
if ( $show_frame && array_key_exists( 'no_frame', $restore ) ) {
	$frame_on = empty( $restore['no_frame'] );
}
$text_hex       = ! empty( $restore['text_color'] ) ? (string) $restore['text_color'] : APD_Plugin::palette_preferred_hex( 'text', array( '#000000' ) );
$holder_hex     = ! empty( $restore['holder_color'] ) ? (string) $restore['holder_color'] : APD_Plugin::palette_preferred_hex( 'holder', array( '#000000' ) );
$opening_fill   = isset( $apd_payload['default_fill'] ) ? (string) $apd_payload['default_fill'] : '';
$plate_hex      = ! empty( $restore['background_color'] ) ? (string) $restore['background_color'] : ( '' !== $opening_fill ? $opening_fill : APD_Plugin::palette_preferred_hex( 'background', array( '#FFFFFF' ) ) );
if ( APD_Formats::is_photo_holder( $type ) ) {
	if ( empty( $restore['text_color'] ) ) {
		$text_hex = '#000000';
	}
	if ( empty( $restore['background_color'] ) ) {
		$plate_hex = '#FFFFFF';
	}
}
$border_hex     = ! empty( $restore['border_color'] ) ? (string) $restore['border_color'] : APD_Plugin::palette_preferred_hex( 'border', array( '#000000', isset( $format['border_color'] ) ? (string) $format['border_color'] : '#000000' ) );
$lock_colors    = ! empty( $apd_payload['lock_colors'] );
if ( $lock_colors ) {
	$locked_hexes = APD_Admin_Settings::locked_color_hexes( $type, $opening_fill );
	$text_hex     = $locked_hexes['text'];
	$plate_hex    = $locked_hexes['background'];
	$border_hex   = $locked_hexes['border'];
}
$slogan_hex     = ! empty( $restore['slogan_color'] ) ? (string) $restore['slogan_color'] : '#000000';
$apd_round      = APD_Formats::uses_painted_plate( $type );
$apd_english    = APD_Formats::is_english( $type );
$apd_radius_mm  = $apd_round ? APD_Formats::plate_corner_radius_mm( $format['height'] ) : 0;
$apd_plate_rx   = $apd_round ? round( $apd_radius_mm / max( 1, (int) $format['width'] ) * 100, 3 ) : 0;
$apd_plate_ry   = $apd_round ? round( $apd_radius_mm / max( 1, (int) $format['height'] ) * 100, 3 ) : 0;
$canvas_label   = $country_picker && ! empty( $presets )
	? ( isset( $i18n['bandHint'] ) ? $i18n['bandHint'] : __( 'Click the country band to change country', 'auto-plate-designer' ) )
	: __( 'Plate preview', 'auto-plate-designer' );

/**
 * Render a color swatch group.
 *
 * @param string                            $label   Field label.
 * @param string                            $name    Input name.
 * @param array<int, array<string, string>> $colors  Palette.
 * @param string                            $current Selected hex.
 */
$apd_swatches = static function ( $label, $name, $colors, $current ) {
	if ( empty( $colors ) ) {
		return;
	}

	echo '<fieldset class="apd-field apd-swatches">';
	echo '<legend>' . esc_html( $label ) . '</legend>';
	echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $current ) . '" data-apd-color="' . esc_attr( $name ) . '">';
	echo '<div class="apd-swatch-list">';

	foreach ( $colors as $color ) {
		$hex     = $color['hex'];
		$pressed = 0 === strcasecmp( $hex, $current ) ? 'true' : 'false';
		printf(
			'<button type="button" class="apd-swatch" data-apd-swatch="%1$s" data-apd-hex="%2$s" aria-pressed="%3$s" title="%4$s" style="background:%2$s"></button>',
			esc_attr( $name ),
			esc_attr( $hex ),
			esc_attr( $pressed ),
			esc_attr( $color['label'] )
		);
	}

	echo '</div></fieldset>';
};

/**
 * Render a color choice. Locked text, frame, and plate colors stay as hidden values.
 *
 * @param string                            $label   Field label.
 * @param string                            $name    Input name.
 * @param array<int, array<string, string>> $colors  Palette.
 * @param string                            $current Selected hex.
 */
$apd_color_choice = static function ( $label, $name, $colors, $current ) use ( $apd_swatches, $lock_colors ) {
	$locked_names = array( 'apd_text_color', 'apd_background_color', 'apd_border_color' );

	if ( $lock_colors && in_array( $name, $locked_names, true ) ) {
		if ( empty( $colors ) ) {
			return;
		}

		echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $current ) . '" data-apd-color="' . esc_attr( $name ) . '">';
		return;
	}

	$apd_swatches( $label, $name, $colors, $current );
};
?>
<div class="apd-configurator" data-apd-root style="--apd-swatch-size: <?php echo esc_attr( (string) (int) $swatch_ui['size'] ); ?>px; --apd-swatch-radius: <?php echo esc_attr( APD_Color_Palettes::swatch_radius_css( $swatch_ui ) ); ?>; --apd-swatch-border: <?php echo esc_attr( APD_Color_Palettes::swatch_border_css( $swatch_ui ) ); ?>;">
	<?php wp_nonce_field( 'apd_configure', 'apd_nonce' ); ?>
	<?php if ( ! empty( $apd_payload['cart_item_key'] ) ) : ?>
		<input type="hidden" name="apd_cart_item" value="<?php echo esc_attr( (string) $apd_payload['cart_item_key'] ); ?>">
	<?php endif; ?>
	<?php if ( ! empty( $legacy_frame_off ) ) : ?>
		<input type="hidden" name="apd_legacy_no_frame" value="1" data-apd-legacy-no-frame>
	<?php endif; ?>

	<div class="apd-preview" style="--apd-ratio: <?php echo esc_attr( (string) ( (int) $format['width'] ) ); ?> / <?php echo esc_attr( (string) ( (int) $format['height'] ) ); ?>; --apd-canvas-max: <?php echo esc_attr( (string) (int) APD_Formats::CANVAS_DISPLAY_MAX_PX ); ?>px; --apd-frame-w: <?php echo esc_attr( (string) (int) $frame_w ); ?>px;">
		<div class="apd-canvas-frame<?php echo $apd_round ? ' apd-round-plate' : ''; ?><?php echo $apd_english ? ' apd-english-plate' : ''; ?>" style="aspect-ratio: <?php echo esc_attr( (string) (int) $format['width'] ); ?> / <?php echo esc_attr( (string) (int) $format['height'] ); ?>;<?php echo $apd_round ? ' --apd-plate-rx: ' . esc_attr( (string) $apd_plate_rx ) . '%; --apd-plate-ry: ' . esc_attr( (string) $apd_plate_ry ) . '%;' : ''; ?>">
			<canvas
				class="apd-canvas"
				width="<?php echo esc_attr( (string) $format['width'] ); ?>"
				height="<?php echo esc_attr( (string) $format['height'] ); ?>"
				aria-label="<?php echo esc_attr( $canvas_label ); ?>"
			></canvas>
		</div>
	</div>

	<div class="apd-fields">
		<?php if ( $show_strip ) : ?>
			<p class="form-row form-row-wide apd-field">
				<label class="apd-choice">
					<input type="checkbox" name="apd_plain" value="1" data-apd-plain <?php checked( ! empty( $restore['unstyled'] ) ); ?>>
					<?php esc_html_e( 'I do not want personalization', 'auto-plate-designer' ); ?>
				</label>
			</p>
		<?php endif; ?>
		<div data-apd-personal>
		<?php if ( count( $scripts ) > 1 ) : ?>
			<?php
			$script_choices = array(
				'latin'    => __( 'Latin', 'auto-plate-designer' ),
				'cyrillic' => __( 'Cyrillic letters', 'auto-plate-designer' ),
				'combined' => __( 'Combined font, Cyrillic and Latin', 'auto-plate-designer' ),
			);
			?>
			<div class="form-row form-row-wide apd-field">
				<label><?php esc_html_e( 'Inscription language', 'auto-plate-designer' ); ?></label>
				<?php if ( empty( $apd_payload['digital_only'] ) && ! $holder_product ) : ?>
					<span class="apd-font-hint"><?php esc_html_e( 'For Cyrillic text, or Latin text longer than 10 characters, the letters are made digitally while the plate frame stays classic and embossed.', 'auto-plate-designer' ); ?></span>
				<?php endif; ?>
				<fieldset class="apd-script">
					<legend class="screen-reader-text"><?php esc_html_e( 'Inscription language', 'auto-plate-designer' ); ?></legend>
					<?php foreach ( $script_choices as $script_key => $script_label ) : ?>
						<?php if ( ! in_array( $script_key, $scripts, true ) ) { continue; } ?>
						<label class="apd-choice">
							<input type="radio" name="apd_script" value="<?php echo esc_attr( $script_key ); ?>" data-apd-script <?php checked( $script_key, $script_value ); ?>>
							<?php echo esc_html( $script_label ); ?>
						</label>
					<?php endforeach; ?>
				</fieldset>
			</div>
		<?php elseif ( 1 === count( $scripts ) ) : ?>
			<input type="hidden" name="apd_script" value="<?php echo esc_attr( $script_value ); ?>" data-apd-script>
			<?php if ( ! $holder_product ) : ?>
				<p class="apd-font-hint"><?php echo 'cyrillic' === $script_value ? esc_html__( 'This plate is written in Cyrillic letters.', 'auto-plate-designer' ) : esc_html__( 'This plate is written in Latin letters.', 'auto-plate-designer' ); ?></p>
			<?php endif; ?>
		<?php endif; ?>
		<p class="apd-font-hint apd-field-error" data-apd-script-error role="alert" hidden></p>
		<?php if ( $two_row_fields ) : ?>
			<?php
			$suv_rows = APD_Formats::suv_plate_rows( $default_text );
			$row_max  = isset( $format['row_max_chars'] ) && is_array( $format['row_max_chars'] ) ? $format['row_max_chars'] : array( $max, $max );
			$row1_max = isset( $row_max[0] ) ? (int) $row_max[0] : $max;
			$row2_max = isset( $row_max[1] ) ? (int) $row_max[1] : $max;
			if ( $row1_max < 1 ) {
				$row1_max = APD_Formats::SUV_ROW_1_MAX;
			}
			if ( $row2_max < 1 ) {
				$row2_max = APD_Formats::SUV_ROW_2_MAX;
			}
			?>
			<div class="apd-row-fields">
				<p class="form-row form-row-wide apd-field">
					<label for="apd_text_row_1"><?php esc_html_e( 'First row', 'auto-plate-designer' ); ?></label>
					<span class="apd-font-hint"><?php esc_html_e( 'Allowed characters are digits, letters, "-", ".", "@", "!" and "?".', 'auto-plate-designer' ); ?></span>
					<input type="text" class="input-text" id="apd_text_row_1" name="apd_text_row_1" data-apd-plate-row value="<?php echo esc_attr( $suv_rows[0] ); ?>" maxlength="<?php echo esc_attr( (string) $row1_max ); ?>" autocomplete="off">
					<span class="apd-count" data-apd-count data-apd-row-index="0"></span>
				</p>
				<p class="form-row form-row-wide apd-field">
					<label for="apd_text_row_2"><?php esc_html_e( 'Second row', 'auto-plate-designer' ); ?></label>
					<input type="text" class="input-text" id="apd_text_row_2" name="apd_text_row_2" data-apd-plate-row value="<?php echo esc_attr( $suv_rows[1] ); ?>" maxlength="<?php echo esc_attr( (string) $row2_max ); ?>" autocomplete="off">
					<span class="apd-count" data-apd-count data-apd-row-index="1"></span>
				</p>
			</div>
			<input type="hidden" id="apd_text" name="apd_text" value="<?php echo esc_attr( $suv_rows[0] . "\n" . $suv_rows[1] ); ?>">
		<?php elseif ( is_array( $us_side_limits ) ) : ?>
			<?php
			$side_left  = '';
			$side_right = '';
			if ( false !== strpos( $default_text, "\n" ) ) {
				$side_parts = explode( "\n", $default_text, 2 );
				$side_left  = $side_parts[0];
				$side_right = isset( $side_parts[1] ) ? $side_parts[1] : '';
			} elseif ( '' === $default_text || 'TEXT' === $default_text ) {
				$side_left  = 'CA';
				$side_right = '1234';
			} else {
				$side_left = $default_text;
			}
			$side_left_max  = (int) $us_side_limits[0];
			$side_right_max = (int) $us_side_limits[1];
			?>
			<div class="apd-side-fields">
				<p class="form-row form-row-wide apd-field">
					<label for="apd_text_left"><?php esc_html_e( 'Left text', 'auto-plate-designer' ); ?></label>
					<span class="apd-font-hint"><?php esc_html_e( 'Allowed characters are digits, letters, "-", ".", "@", "!" and "?".', 'auto-plate-designer' ); ?></span>
					<input type="text" class="input-text" id="apd_text_left" name="apd_text_left" data-apd-plate-side value="<?php echo esc_attr( $side_left ); ?>" maxlength="<?php echo esc_attr( (string) $side_left_max ); ?>" autocomplete="off">
					<span class="apd-count" data-apd-count></span>
				</p>
				<p class="form-row form-row-wide apd-field">
					<label for="apd_text_right"><?php esc_html_e( 'Right text', 'auto-plate-designer' ); ?></label>
					<input type="text" class="input-text" id="apd_text_right" name="apd_text_right" data-apd-plate-side value="<?php echo esc_attr( $side_right ); ?>" maxlength="<?php echo esc_attr( (string) $side_right_max ); ?>" autocomplete="off">
					<span class="apd-count" data-apd-count></span>
				</p>
			</div>
			<input type="hidden" id="apd_text" name="apd_text" value="<?php echo esc_attr( $side_left . "\n" . $side_right ); ?>">
		<?php elseif ( APD_Formats::is_french( $type ) ) : ?>
			<?php
			$fr_limits = isset( $format['fr_groups'] ) && is_array( $format['fr_groups'] ) ? $format['fr_groups'] : array( APD_Formats::FR_GROUP_1_MAX, APD_Formats::FR_GROUP_2_MAX, APD_Formats::FR_GROUP_3_MAX );
			$fr_values = APD_Formats::french_field_values( $type, $default_text );
			$fr_temp   = APD_Formats::is_french_temporary( $type );
			$fr_month  = '05';
			$fr_year   = '26';
			if ( isset( $restore['fr_month'] ) ) {
				$fr_month = (string) $restore['fr_month'];
			}
			if ( isset( $restore['fr_year'] ) ) {
				$fr_year = (string) $restore['fr_year'];
			}
			$fr_letter_max = isset( $fr_limits[0] ) ? (int) $fr_limits[0] : APD_Formats::FR_GROUP_1_MAX;
			$fr_number_max = isset( $fr_limits[1] ) ? (int) $fr_limits[1] : APD_Formats::FR_GROUP_2_MAX;
			$fr_end_max    = isset( $fr_limits[2] ) ? (int) $fr_limits[2] : APD_Formats::FR_GROUP_3_MAX;
			$fr_text       = $fr_temp
				? APD_Formats::FR_WW_MARK . '-' . $fr_values[0] . '-' . $fr_values[1]
				: $fr_values[0] . '-' . $fr_values[1] . '-' . $fr_values[2];
			?>
			<p class="apd-font-hint"><?php esc_html_e( 'The dashes are printed on the plate.', 'auto-plate-designer' ); ?></p>
			<?php if ( $fr_temp ) : ?>
				<p class="apd-font-hint"><?php esc_html_e( 'WW stays on the plate.', 'auto-plate-designer' ); ?></p>
			<?php else : ?>
				<p class="form-row form-row-wide apd-field">
					<label for="apd_fr_a"><?php esc_html_e( 'Letters', 'auto-plate-designer' ); ?></label>
					<input type="text" class="input-text" id="apd_fr_a" name="apd_fr_a" data-apd-fr="letters" data-apd-fr-group value="<?php echo esc_attr( $fr_values[0] ); ?>" maxlength="<?php echo esc_attr( (string) $fr_letter_max ); ?>" autocomplete="off" inputmode="text">
					<span class="apd-count" data-apd-count></span>
				</p>
			<?php endif; ?>
			<p class="form-row form-row-wide apd-field">
				<label for="apd_fr_b"><?php esc_html_e( 'Numbers', 'auto-plate-designer' ); ?></label>
				<input type="text" class="input-text" id="apd_fr_b" name="apd_fr_b" data-apd-fr="digits" data-apd-fr-group value="<?php echo esc_attr( $fr_temp ? $fr_values[0] : $fr_values[1] ); ?>" maxlength="<?php echo esc_attr( (string) $fr_number_max ); ?>" autocomplete="off" inputmode="numeric">
				<span class="apd-count" data-apd-count></span>
			</p>
			<p class="form-row form-row-wide apd-field">
				<label for="apd_fr_c"><?php esc_html_e( 'Letters', 'auto-plate-designer' ); ?></label>
				<input type="text" class="input-text" id="apd_fr_c" name="apd_fr_c" data-apd-fr="letters" data-apd-fr-group value="<?php echo esc_attr( $fr_temp ? $fr_values[1] : $fr_values[2] ); ?>" maxlength="<?php echo esc_attr( (string) $fr_end_max ); ?>" autocomplete="off" inputmode="text">
				<span class="apd-count" data-apd-count></span>
			</p>
			<?php if ( APD_Formats::is_french_region( $type ) ) : ?>
				<?php
				$fr_region = isset( $apd_payload['region_text'] ) ? APD_Formats::french_region_code( (string) $apd_payload['region_text'] ) : '';
				if ( '' === $fr_region ) {
					$fr_region = APD_Formats::FR_REGION_SAMPLE;
				}
				if ( isset( $restore['fr_region'] ) ) {
					$fr_region = APD_Formats::french_region_code( (string) $restore['fr_region'] );
				}
				?>
				<p class="form-row form-row-wide apd-field">
					<label for="apd_fr_region"><?php esc_html_e( 'Region', 'auto-plate-designer' ); ?></label>
					<input type="text" class="input-text" id="apd_fr_region" name="apd_fr_region" data-apd-fr="digits" value="<?php echo esc_attr( $fr_region ); ?>" maxlength="<?php echo esc_attr( (string) APD_Formats::FR_REGION_MAX ); ?>" autocomplete="off" inputmode="numeric">
					<span class="apd-count" data-apd-count></span>
				</p>
			<?php endif; ?>
			<?php if ( $fr_temp ) : ?>
				<div class="apd-fr-date">
					<p class="form-row form-row-wide apd-field">
						<label for="apd_fr_month"><?php esc_html_e( 'Month', 'auto-plate-designer' ); ?></label>
						<input type="text" class="input-text" id="apd_fr_month" name="apd_fr_month" data-apd-fr="digits" value="<?php echo esc_attr( $fr_month ); ?>" maxlength="<?php echo esc_attr( (string) APD_Formats::FR_DATE_MAX ); ?>" autocomplete="off" inputmode="numeric">
						<span class="apd-count" data-apd-count></span>
					</p>
					<p class="form-row form-row-wide apd-field">
						<label for="apd_fr_year"><?php esc_html_e( 'Year', 'auto-plate-designer' ); ?></label>
						<input type="text" class="input-text" id="apd_fr_year" name="apd_fr_year" data-apd-fr="digits" value="<?php echo esc_attr( $fr_year ); ?>" maxlength="<?php echo esc_attr( (string) APD_Formats::FR_DATE_MAX ); ?>" autocomplete="off" inputmode="numeric">
						<span class="apd-count" data-apd-count></span>
					</p>
				</div>
			<?php endif; ?>
			<input type="hidden" id="apd_text" name="apd_text" value="<?php echo esc_attr( $fr_text ); ?>">
		<?php elseif ( APD_Formats::is_austria( $type ) ) : ?>
			<?php
			$at_limits = isset( $format['at_groups'] ) && is_array( $format['at_groups'] ) ? $format['at_groups'] : APD_Formats::austria_limits( array() );
			$at_values = APD_Formats::austria_field_values( $default_text );
			$at_left_max  = isset( $at_limits[0] ) ? (int) $at_limits[0] : APD_Formats::AT_LEFT_MAX;
			$at_right_max = isset( $at_limits[1] ) ? (int) $at_limits[1] : APD_Formats::AT_RIGHT_MAX;
			?>
			<p class="apd-font-hint"><?php esc_html_e( 'The gray rectangle will be replaced with the region image.', 'auto-plate-designer' ); ?></p>
			<p class="form-row form-row-wide apd-field">
				<label for="apd_at_left"><?php esc_html_e( 'Letters', 'auto-plate-designer' ); ?></label>
				<input type="text" class="input-text" id="apd_at_left" name="apd_at_left" data-apd-at="letters" value="<?php echo esc_attr( $at_values[0] ); ?>" maxlength="<?php echo esc_attr( (string) $at_left_max ); ?>" autocomplete="off" inputmode="text">
				<span class="apd-count" data-apd-count></span>
			</p>
			<p class="form-row form-row-wide apd-field">
				<label for="apd_at_right"><?php esc_html_e( 'Registration', 'auto-plate-designer' ); ?></label>
				<input type="text" class="input-text" id="apd_at_right" name="apd_at_right" data-apd-at="mixed" value="<?php echo esc_attr( $at_values[1] ); ?>" maxlength="<?php echo esc_attr( (string) $at_right_max ); ?>" autocomplete="off" inputmode="text">
				<span class="apd-count" data-apd-count></span>
			</p>
			<input type="hidden" id="apd_text" name="apd_text" value="<?php echo esc_attr( $at_values[0] . "\n" . $at_values[1] ); ?>">
		<?php elseif ( APD_Formats::is_english( $type ) ) : ?>
			<fieldset class="apd-field apd-group" data-apd-plate-group>
				<legend><?php esc_html_e( 'Plate', 'auto-plate-designer' ); ?></legend>
				<p class="form-row form-row-wide apd-field">
					<label for="apd_text"><?php echo esc_html( $i18n['textLabel'] ); ?></label>
					<span class="apd-font-hint"><?php esc_html_e( 'Allowed characters are digits, letters, "-", ".", "@", "!" and "?".', 'auto-plate-designer' ); ?></span>
					<input type="text" class="input-text" id="apd_text" name="apd_text" value="<?php echo esc_attr( $default_text ); ?>" maxlength="<?php echo esc_attr( (string) $max ); ?>" autocomplete="off">
					<span class="apd-count" data-apd-count></span>
				</p>
				<?php if ( count( $fonts ) > 1 ) : ?>
					<p class="form-row form-row-wide apd-field">
						<label for="apd_font_id"><?php echo esc_html( $i18n['fontLabel'] ); ?></label>
						<select id="apd_font_id" name="apd_font_id">
							<?php foreach ( $fonts as $font ) : ?>
								<?php $font_for = isset( $font['scripts'] ) && is_array( $font['scripts'] ) ? implode( ' ', $font['scripts'] ) : ''; ?>
								<option value="<?php echo esc_attr( $font['id'] ); ?>" <?php echo '' !== $font_for ? 'data-apd-font-for="' . esc_attr( $font_for ) . '"' : ''; ?> <?php selected( $font['id'], $default_font ); ?>><?php echo esc_html( APD_Formats::font_shop_label( $font ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>
				<?php elseif ( '' !== $default_font ) : ?>
					<input type="hidden" name="apd_font_id" value="<?php echo esc_attr( $default_font ); ?>">
				<?php endif; ?>
				<?php
				$apd_text_colors = APD_Formats::shop_swatch_colors( $type, 'text', $color_fields, $palettes );
				if ( ! empty( $apd_text_colors ) ) {
					echo '<div data-apd-text-colors>';
					$apd_color_choice( $i18n['textColor'], 'apd_text_color', $apd_text_colors, $text_hex );
					echo '</div>';
				}
				?>
			</fieldset>
		<?php else : ?>
			<p class="form-row form-row-wide apd-field">
				<label for="apd_text"><?php echo esc_html( $i18n['textLabel'] ); ?></label>
				<span class="apd-font-hint"><?php esc_html_e( 'Allowed characters are digits, letters, "-", ".", "@", "!" and "?".', 'auto-plate-designer' ); ?></span>
				<?php if ( $multiline ) : ?>
					<textarea class="input-text" id="apd_text" name="apd_text" rows="3" maxlength="<?php echo esc_attr( (string) $max ); ?>"><?php echo esc_textarea( $default_text ); ?></textarea>
				<?php else : ?>
					<input type="text" class="input-text" id="apd_text" name="apd_text" value="<?php echo esc_attr( $default_text ); ?>" maxlength="<?php echo esc_attr( (string) $max ); ?>" autocomplete="off">
				<?php endif; ?>
				<span class="apd-count" data-apd-count></span>
			</p>
		<?php endif; ?>
		<p class="apd-font-hint apd-field-error" data-apd-production hidden></p>

		<?php if ( APD_Formats::is_english( $type ) ) : ?>
			<?php
			$slogan_max   = isset( $format['slogan_max'] ) ? (int) $format['slogan_max'] : APD_Formats::UK_SLOGAN_MAX;
			$slogan_value = isset( $restore['slogan'] ) ? (string) $restore['slogan'] : ( isset( $apd_payload['default_slogan'] ) ? (string) $apd_payload['default_slogan'] : '' );
			if ( $slogan_max < 1 ) {
				$slogan_max = APD_Formats::UK_SLOGAN_MAX;
			}
			?>
			<fieldset class="apd-field apd-group" data-apd-slogan-group>
				<legend><?php echo esc_html( isset( $i18n['sloganLabel'] ) ? $i18n['sloganLabel'] : __( 'Slogan', 'auto-plate-designer' ) ); ?></legend>
				<p class="form-row form-row-wide apd-field">
					<label for="apd_slogan"><?php echo esc_html( isset( $i18n['sloganLabel'] ) ? $i18n['sloganLabel'] : __( 'Slogan', 'auto-plate-designer' ) ); ?></label>
					<input type="text" class="input-text" id="apd_slogan" name="apd_slogan" value="<?php echo esc_attr( $slogan_value ); ?>" maxlength="<?php echo esc_attr( (string) $slogan_max ); ?>" autocomplete="off">
					<span class="apd-count" data-apd-slogan-count></span>
				</p>
				<?php if ( count( $fonts ) > 1 ) : ?>
					<p class="form-row form-row-wide apd-field">
						<label for="apd_slogan_font_id"><?php esc_html_e( 'Slogan font', 'auto-plate-designer' ); ?></label>
						<select id="apd_slogan_font_id" name="apd_slogan_font_id">
							<?php foreach ( $fonts as $font ) : ?>
								<option value="<?php echo esc_attr( $font['id'] ); ?>" <?php selected( $font['id'], $default_slogan_font ); ?>><?php echo esc_html( APD_Formats::font_shop_label( $font ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>
				<?php elseif ( '' !== $default_slogan_font ) : ?>
					<input type="hidden" name="apd_slogan_font_id" value="<?php echo esc_attr( $default_slogan_font ); ?>">
				<?php endif; ?>
				<?php
				$apd_slogan_colors = APD_Formats::shop_swatch_colors( $type, 'slogan', $color_fields, $palettes );
				if ( ! empty( $apd_slogan_colors ) ) {
					$slogan_color_label = isset( $i18n['sloganColor'] ) ? $i18n['sloganColor'] : __( 'Slogan color', 'auto-plate-designer' );
					$apd_swatches( $slogan_color_label, 'apd_slogan_color', $apd_slogan_colors, $slogan_hex );
				}
				?>
			</fieldset>
		<?php endif; ?>

		<?php
		$combined_fonts = array();
		foreach ( $fonts as $font ) {
			$font_scripts = isset( $font['scripts'] ) && is_array( $font['scripts'] ) ? $font['scripts'] : array();
			if ( in_array( 'cyrillic', $font_scripts, true ) ) {
				$combined_fonts[] = $font;
			}
		}
		$combined_font_open = $holder_product && in_array( 'combined', $scripts, true ) && ! empty( $combined_fonts );
		$combined_selected  = $combined_font_open && 'combined' === $script_value;
		$default_font_cyr   = $default_font;
		if ( ! empty( $restore['font_id_cyrillic'] ) ) {
			$default_font_cyr = (string) $restore['font_id_cyrillic'];
		}
		?>
		<?php if ( ! $apd_english && count( $fonts ) > 1 ) : ?>
			<?php $apd_font_style_hint = ! $holder_product && ! APD_Formats::is_french( $type ) && ! APD_Formats::is_austria( $type ) && 'us' !== (string) $type; ?>
			<p class="form-row form-row-wide apd-field">
				<label for="apd_font_id">
					<span data-apd-font-single<?php echo $combined_selected ? ' hidden' : ''; ?>><?php echo esc_html( $i18n['fontLabel'] ); ?></span>
					<span data-apd-font-latin<?php echo $combined_selected ? '' : ' hidden'; ?>><?php esc_html_e( 'Font for Latin', 'auto-plate-designer' ); ?></span>
				</label>
				<?php if ( $apd_font_style_hint ) : ?>
					<span class="apd-font-hint"><?php esc_html_e( 'For a perfect look, choose a font that matches the plate style and the language of your text (Latin or Cyrillic).', 'auto-plate-designer' ); ?></span>
				<?php endif; ?>
				<select id="apd_font_id" name="apd_font_id">
					<?php foreach ( $fonts as $font ) : ?>
						<?php $font_for = isset( $font['scripts'] ) && is_array( $font['scripts'] ) ? implode( ' ', $font['scripts'] ) : ''; ?>
						<option value="<?php echo esc_attr( $font['id'] ); ?>" <?php echo '' !== $font_for ? 'data-apd-font-for="' . esc_attr( $font_for ) . '"' : ''; ?> <?php selected( $font['id'], $default_font ); ?>><?php echo esc_html( APD_Formats::font_shop_label( $font ) ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
		<?php elseif ( ! $apd_english && '' !== $default_font ) : ?>
			<input type="hidden" name="apd_font_id" value="<?php echo esc_attr( $default_font ); ?>">
		<?php endif; ?>
		<?php if ( $combined_font_open ) : ?>
			<p class="form-row form-row-wide apd-field" data-apd-font-cyr<?php echo $combined_selected ? '' : ' hidden'; ?>>
				<label for="apd_font_id_cyr"><?php esc_html_e( 'Font for Cyrillic', 'auto-plate-designer' ); ?></label>
				<select id="apd_font_id_cyr" name="apd_font_id_cyr"<?php echo $combined_selected ? '' : ' disabled'; ?>>
					<?php foreach ( $combined_fonts as $font ) : ?>
						<option value="<?php echo esc_attr( $font['id'] ); ?>" data-apd-font-for="cyrillic" <?php selected( $font['id'], $default_font_cyr ); ?>><?php echo esc_html( APD_Formats::font_shop_label( $font ) ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
		<?php endif; ?>

		<?php if ( $show_country && ! empty( $presets ) ) : ?>
			<?php
			$current_preset = null;
			foreach ( $presets as $preset ) {
				if ( $preset['id'] === $default_preset ) {
					$current_preset = $preset;
					break;
				}
			}
			if ( ! is_array( $current_preset ) ) {
				$current_preset = $presets[0];
			}
			$current_code = $current_preset['country_code'] ? $current_preset['country_code'] : $current_preset['name'];
			?>
			<input type="hidden" name="apd_preset_id" value="<?php echo esc_attr( $default_preset ); ?>" data-apd-preset>
			<?php if ( $country_picker ) : ?>
			<fieldset class="apd-field apd-country">
				<legend><?php echo esc_html( $i18n['countryLabel'] ); ?></legend>
				<button
					type="button"
					class="apd-country-current"
					<?php if ( $country_picker ) : ?>
						data-apd-country-open
						aria-haspopup="dialog"
						aria-expanded="false"
						aria-controls="apd-country-dialog"
					<?php endif; ?>
				>
					<?php if ( ! empty( $current_preset['image_url'] ) ) : ?>
						<img src="<?php echo esc_url( $current_preset['image_url'] ); ?>" alt="" data-apd-country-thumb hidden>
					<?php else : ?>
						<img alt="" data-apd-country-thumb hidden>
					<?php endif; ?>
					<span data-apd-country-code><?php echo esc_html( $current_code ); ?></span>
					<?php if ( $country_picker ) : ?>
						<span class="apd-country-change"><?php echo esc_html( isset( $i18n['changeCountry'] ) ? $i18n['changeCountry'] : __( 'Change country', 'auto-plate-designer' ) ); ?></span>
					<?php endif; ?>
				</button>
			</fieldset>

			<?php if ( $country_picker ) : ?>
			<div class="apd-dialog" id="apd-country-dialog" hidden data-apd-country-dialog role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="apd-country-dialog-title">
				<div class="apd-dialog-backdrop" data-apd-country-dismiss></div>
				<div class="apd-dialog-panel" role="document">
					<div class="apd-dialog-head">
						<h2 id="apd-country-dialog-title"><?php echo esc_html( isset( $i18n['chooseCountry'] ) ? $i18n['chooseCountry'] : __( 'Choose country', 'auto-plate-designer' ) ); ?></h2>
						<button type="button" class="apd-dialog-close" data-apd-country-dismiss aria-label="<?php echo esc_attr( isset( $i18n['closeDialog'] ) ? $i18n['closeDialog'] : __( 'Close', 'auto-plate-designer' ) ); ?>">×</button>
					</div>
					<div class="apd-country-grid">
						<?php foreach ( $presets as $preset ) : ?>
							<button
								type="button"
								class="apd-preset apd-country-option"
								data-apd-preset-id="<?php echo esc_attr( $preset['id'] ); ?>"
								data-apd-country-code="<?php echo esc_attr( $preset['country_code'] ? $preset['country_code'] : $preset['name'] ); ?>"
								data-apd-country-image="<?php echo esc_url( ! empty( $preset['image_url'] ) ? $preset['image_url'] : '' ); ?>"
								aria-pressed="<?php echo $preset['id'] === $default_preset ? 'true' : 'false'; ?>"
							>
								<?php if ( ! empty( $preset['image_url'] ) ) : ?>
									<img src="<?php echo esc_url( $preset['image_url'] ); ?>" alt="">
								<?php endif; ?>
								<span><?php echo esc_html( $preset['country_code'] ? $preset['country_code'] : $preset['name'] ); ?></span>
							</button>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
			<?php endif; ?>
			<?php endif; ?>
		<?php endif; ?>

		<?php if ( $show_designs ) : ?>
			<input type="hidden" name="apd_design_id" value="<?php echo esc_attr( $default_design ); ?>" data-apd-design>
		<?php endif; ?>

		<?php if ( $show_frame ) : ?>
			<p class="form-row form-row-wide apd-field">
				<label class="apd-choice">
					<input type="checkbox" name="apd_frame" value="1" data-apd-frame <?php checked( $frame_on ); ?>>
					<?php echo esc_html( isset( $i18n['frameLabel'] ) ? $i18n['frameLabel'] : __( 'Add frame', 'auto-plate-designer' ) ); ?>
				</label>
			</p>
		<?php elseif ( $plain_frame && ! $legacy_frame_off && ! $hide_plain_frame ) : ?>
			<p class="form-row form-row-wide apd-field" data-apd-plain-frame>
				<label class="apd-choice">
					<input type="checkbox" name="apd_frame_plain" value="1" data-apd-frame-plain <?php checked( $plain_on ); ?>>
					<?php esc_html_e( 'Without frame color', 'auto-plate-designer' ); ?>
				</label>
			</p>
		<?php endif; ?>

		<?php
		$apd_text_colors  = APD_Formats::shop_swatch_colors( $type, 'text', $color_fields, $palettes );
		$apd_plate_colors = APD_Formats::shop_swatch_colors( $type, 'background', $color_fields, $palettes );
		$apd_frame_colors = APD_Formats::shop_swatch_colors( $type, 'border', $color_fields, $palettes );

		if ( in_array( 'holder', $color_fields, true ) ) {
			$holder_colors = isset( $palettes['holder'] ) && is_array( $palettes['holder'] ) ? $palettes['holder'] : array();
			$holder_label  = isset( $i18n['holderColor'] ) ? $i18n['holderColor'] : __( 'Holder color', 'auto-plate-designer' );
			$apd_swatches( $holder_label, 'apd_holder_color', $holder_colors, $holder_hex );
		}

		if ( ! empty( $apd_text_colors ) && ! $show_strip && ! $apd_english ) {
			echo '<div data-apd-text-colors>';
			$apd_color_choice( $i18n['textColor'], 'apd_text_color', $apd_text_colors, $text_hex );
			echo '</div>';
		}

		$digital_colors = isset( $format['digital_colors'] ) && is_array( $format['digital_colors'] ) ? $format['digital_colors'] : array();
		if ( ! empty( $digital_colors ) && APD_Formats::offers_digital_palette( $type ) ) {
			echo '<div data-apd-digital-colors hidden>';
			$apd_swatches( __( 'Digital color', 'auto-plate-designer' ), 'apd_digital_color', $digital_colors, isset( $digital_colors[0]['hex'] ) ? (string) $digital_colors[0]['hex'] : '' );
			echo '</div>';
		}

		if ( ! empty( $apd_plate_colors ) && $show_eu ) {
			$apd_color_choice( $i18n['plateColor'], 'apd_background_color', $apd_plate_colors, $plate_hex );
		}

		$apd_frame_family = $show_frame || APD_Formats::offers_uncolored_frame( $type );
		$apd_frame_hidden = ( $show_frame && ! $frame_on ) || ( ! $show_frame && ! $plain_frame ) || $legacy_frame_off;
		if ( ! empty( $apd_frame_colors ) && $apd_frame_family ) {
			echo '<div data-apd-frame-colors' . ( $apd_frame_hidden ? ' hidden' : '' ) . '>';
			$apd_color_choice( $i18n['borderColor'], 'apd_border_color', $apd_frame_colors, $border_hex );
			echo '</div>';
		} elseif ( ! empty( $apd_frame_colors ) && APD_Formats::is_english( $type ) ) {
			$apd_color_choice( $i18n['borderColor'], 'apd_border_color', $apd_frame_colors, $border_hex );
		}

		if ( ! isset( $apd_slogan_colors ) ) {
			$apd_slogan_colors = APD_Formats::shop_swatch_colors( $type, 'slogan', $color_fields, $palettes );
		}
		if ( ! empty( $apd_slogan_colors ) && ! $apd_english ) {
			$slogan_color_label = isset( $i18n['sloganColor'] ) ? $i18n['sloganColor'] : __( 'Slogan color', 'auto-plate-designer' );
			$apd_swatches( $slogan_color_label, 'apd_slogan_color', $apd_slogan_colors, $slogan_hex );
		}

		if ( $show_strip ) {
			$strip_label = isset( $i18n['stripColor'] ) ? $i18n['stripColor'] : __( 'Strip color', 'auto-plate-designer' );
			$text_label  = isset( $i18n['holderTextColor'] ) ? $i18n['holderTextColor'] : __( 'Holder inscription', 'auto-plate-designer' );

			if ( ! empty( $apd_plate_colors ) ) {
				$apd_color_choice( $strip_label, 'apd_background_color', $apd_plate_colors, $plate_hex );
			}

			if ( ! empty( $apd_text_colors ) ) {
				$apd_color_choice( $text_label, 'apd_text_color', $apd_text_colors, $text_hex );
			}
		}
		?>
		</div>
		<p class="form-row form-row-wide apd-field">
			<label for="apd_comment"><?php esc_html_e( 'Comment', 'auto-plate-designer' ); ?></label>
			<textarea class="input-text" id="apd_comment" name="apd_comment" rows="3" maxlength="<?php echo esc_attr( (string) APD_Formats::COMMENT_MAX ); ?>"><?php echo esc_textarea( $comment_value ); ?></textarea>
		</p>
	</div>
</div>
<script>
(function () {
	var root = document.querySelector('[data-apd-root]');
	var preview = root ? root.querySelector('.apd-preview') : null;
	if (!preview || document.querySelector('.apd-product-layout')) {
		return;
	}
	var layout = document.createElement('div');
	var left = document.createElement('div');
	var right = document.createElement('div');
	layout.className = 'apd-product-layout alignwide';
	left.className = 'apd-product-layout__preview';
	right.className = 'apd-product-layout__details';
	left.appendChild(preview);
	layout.appendChild(left);
	layout.appendChild(right);
	var columns = document.querySelector('.wp-block-columns');
	if (columns && columns.parentNode) {
		columns.parentNode.insertBefore(layout, columns);
		var title = document.querySelector('.wp-block-post-title, h1.product_title, .product_title.entry-title');
		if (title && !columns.contains(title) && !right.contains(title)) {
			right.appendChild(title);
		}
		right.appendChild(columns);
		return;
	}
	var summary = document.querySelector('div.product div.summary, div.product .entry-summary');
	if (summary && summary.parentNode) {
		summary.parentNode.insertBefore(layout, summary);
		right.appendChild(summary);
		return;
	}
	var heading = document.querySelector('.wp-block-post-title, h1.product_title, .product_title.entry-title');
	if (heading && heading.parentNode) {
		heading.parentNode.insertBefore(layout, heading);
		right.appendChild(heading);
	}
})();
</script>
