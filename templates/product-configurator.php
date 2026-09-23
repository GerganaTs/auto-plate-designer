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
$frame_w        = APD_Formats::canvas_display_width( (int) $format['width'], (int) $format['height'] );
$palettes       = $apd_payload['palettes'];
$i18n           = $apd_payload['i18n'];
$type           = $format['type'];
$fonts          = $format['fonts'];
$presets        = $format['presets'];
$designs        = isset( $format['designs'] ) && is_array( $format['designs'] ) ? $format['designs'] : array();
$max            = (int) $format['max_chars'];
$multiline      = ! empty( $format['multiline'] );
$show_eu        = APD_Formats::uses_painted_plate( $type );
$show_country   = APD_Formats::uses_country_band( $type );
$show_designs   = APD_Formats::uses_plate_designs( $type ) && ! empty( $designs );
$show_strip     = 'holder' === $type;
$color_fields   = isset( $apd_payload['color_fields'] ) && is_array( $apd_payload['color_fields'] ) ? $apd_payload['color_fields'] : array();
$swatch_ui      = APD_Color_Palettes::swatch_display();
$default_text   = isset( $apd_payload['default_text'] ) && '' !== (string) $apd_payload['default_text']
	? (string) $apd_payload['default_text']
	: APD_Formats::sample_plate_text( $type );
$restore        = isset( $apd_payload['cart_restore'] ) && is_array( $apd_payload['cart_restore'] ) ? $apd_payload['cart_restore'] : array();
if ( isset( $restore['text'] ) && '' !== (string) $restore['text'] ) {
	$default_text = (string) $restore['text'];
}
$default_font   = ! empty( $fonts ) ? $fonts[0]['id'] : '';
if ( ! empty( $restore['font_id'] ) ) {
	$default_font = (string) $restore['font_id'];
}
$default_preset = ! empty( $presets ) ? $presets[0]['id'] : '';
if ( ! empty( $restore['preset_id'] ) ) {
	$default_preset = (string) $restore['preset_id'];
}
$default_design = ! empty( $designs ) ? $designs[0]['id'] : '';
if ( ! empty( $restore['design_id'] ) ) {
	$default_design = (string) $restore['design_id'];
}
$show_frame     = APD_Formats::offers_frame_choice( $type );
$frame_on       = $show_frame && $show_eu && empty( $format['no_frame'] );
if ( $show_frame && array_key_exists( 'no_frame', $restore ) ) {
	$frame_on = empty( $restore['no_frame'] );
}
$text_hex       = ! empty( $restore['text_color'] ) ? (string) $restore['text_color'] : APD_Plugin::palette_preferred_hex( 'text', array( '#000000' ) );
$plate_hex      = ! empty( $restore['background_color'] ) ? (string) $restore['background_color'] : APD_Plugin::palette_preferred_hex( 'background', array( '#FFFFFF' ) );
$border_hex     = ! empty( $restore['border_color'] ) ? (string) $restore['border_color'] : APD_Plugin::palette_preferred_hex( 'border', array( '#000000', isset( $format['border_color'] ) ? (string) $format['border_color'] : '#000000' ) );
$canvas_label   = $show_country && ! empty( $presets )
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
?>
<div class="apd-configurator" data-apd-root style="--apd-swatch-size: <?php echo esc_attr( (string) (int) $swatch_ui['size'] ); ?>px; --apd-swatch-radius: <?php echo esc_attr( APD_Color_Palettes::swatch_radius_css( $swatch_ui ) ); ?>; --apd-swatch-border: <?php echo esc_attr( APD_Color_Palettes::swatch_border_css( $swatch_ui ) ); ?>;">
	<?php wp_nonce_field( 'apd_configure', 'apd_nonce' ); ?>

	<div class="apd-preview" style="--apd-ratio: <?php echo esc_attr( (string) ( (int) $format['width'] ) ); ?> / <?php echo esc_attr( (string) ( (int) $format['height'] ) ); ?>; --apd-canvas-max: <?php echo esc_attr( (string) (int) APD_Formats::CANVAS_DISPLAY_MAX_PX ); ?>px; --apd-frame-w: <?php echo esc_attr( (string) (int) $frame_w ); ?>px;">
		<div class="apd-canvas-frame">
			<canvas
				class="apd-canvas"
				width="<?php echo esc_attr( (string) $format['width'] ); ?>"
				height="<?php echo esc_attr( (string) $format['height'] ); ?>"
				aria-label="<?php echo esc_attr( $canvas_label ); ?>"
			></canvas>
		</div>
	</div>

	<div class="apd-fields">
		<?php if ( APD_Formats::is_suv_kind( $type ) ) : ?>
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
		<?php else : ?>
			<p class="form-row form-row-wide apd-field">
				<label for="apd_text"><?php echo esc_html( $i18n['textLabel'] ); ?></label>
				<?php if ( $multiline ) : ?>
					<textarea class="input-text" id="apd_text" name="apd_text" rows="3" maxlength="<?php echo esc_attr( (string) $max ); ?>"><?php echo esc_textarea( $default_text ); ?></textarea>
				<?php else : ?>
					<input type="text" class="input-text" id="apd_text" name="apd_text" value="<?php echo esc_attr( $default_text ); ?>" maxlength="<?php echo esc_attr( (string) $max ); ?>" autocomplete="off">
				<?php endif; ?>
				<span class="apd-count" data-apd-count></span>
			</p>
		<?php endif; ?>

		<?php if ( count( $fonts ) > 1 ) : ?>
			<p class="form-row form-row-wide apd-field">
				<label for="apd_font_id"><?php echo esc_html( $i18n['fontLabel'] ); ?></label>
				<select id="apd_font_id" name="apd_font_id">
					<?php foreach ( $fonts as $font ) : ?>
						<option value="<?php echo esc_attr( $font['id'] ); ?>" <?php selected( $font['id'], $default_font ); ?>><?php echo esc_html( $font['family'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
		<?php elseif ( '' !== $default_font ) : ?>
			<input type="hidden" name="apd_font_id" value="<?php echo esc_attr( $default_font ); ?>">
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
			<fieldset class="apd-field apd-country">
				<legend><?php echo esc_html( $i18n['countryLabel'] ); ?></legend>
				<input type="hidden" name="apd_preset_id" value="<?php echo esc_attr( $default_preset ); ?>" data-apd-preset>
				<button
					type="button"
					class="apd-country-current"
					data-apd-country-open
					aria-haspopup="dialog"
					aria-expanded="false"
					aria-controls="apd-country-dialog"
				>
					<?php if ( ! empty( $current_preset['image_url'] ) ) : ?>
						<img src="<?php echo esc_url( $current_preset['image_url'] ); ?>" alt="" data-apd-country-thumb hidden>
					<?php else : ?>
						<img alt="" data-apd-country-thumb hidden>
					<?php endif; ?>
					<span data-apd-country-code><?php echo esc_html( $current_code ); ?></span>
					<span class="apd-country-change"><?php echo esc_html( isset( $i18n['changeCountry'] ) ? $i18n['changeCountry'] : __( 'Change country', 'auto-plate-designer' ) ); ?></span>
				</button>
			</fieldset>

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

		<?php if ( $show_designs ) : ?>
			<fieldset class="apd-field apd-presets apd-designs">
				<legend><?php echo esc_html( $i18n['designLabel'] ); ?></legend>
				<input type="hidden" name="apd_design_id" value="<?php echo esc_attr( $default_design ); ?>" data-apd-design>
				<div class="apd-preset-list">
					<?php foreach ( $designs as $design ) : ?>
						<button
							type="button"
							class="apd-preset apd-design"
							data-apd-design-id="<?php echo esc_attr( $design['id'] ); ?>"
							aria-pressed="<?php echo $design['id'] === $default_design ? 'true' : 'false'; ?>"
						>
							<?php if ( ! empty( $design['image_url'] ) ) : ?>
								<img src="<?php echo esc_url( $design['image_url'] ); ?>" alt="">
							<?php endif; ?>
							<span><?php echo esc_html( ! empty( $design['code'] ) ? $design['code'] : $design['name'] ); ?></span>
						</button>
					<?php endforeach; ?>
				</div>
			</fieldset>
		<?php endif; ?>

		<?php if ( $show_frame ) : ?>
			<p class="form-row form-row-wide apd-field">
				<label class="apd-choice">
					<input type="checkbox" name="apd_frame" value="1" data-apd-frame <?php checked( $frame_on ); ?>>
					<?php echo esc_html( isset( $i18n['frameLabel'] ) ? $i18n['frameLabel'] : __( 'Add frame', 'auto-plate-designer' ) ); ?>
				</label>
			</p>
		<?php endif; ?>

		<?php
		if ( in_array( 'text', $color_fields, true ) ) {
			$apd_swatches( $i18n['textColor'], 'apd_text_color', $palettes['text'], $text_hex );
		}

		if ( in_array( 'background', $color_fields, true ) && $show_eu ) {
			$apd_swatches( $i18n['plateColor'], 'apd_background_color', $palettes['background'], $plate_hex );
		}

		if ( in_array( 'border', $color_fields, true ) && $show_frame ) {
			echo '<div data-apd-frame-colors' . ( $frame_on ? '' : ' hidden' ) . '>';
			$apd_swatches( $i18n['borderColor'], 'apd_border_color', $palettes['border'], $border_hex );
			echo '</div>';
		}

		if ( in_array( 'background', $color_fields, true ) && $show_strip ) {
			$apd_swatches( $i18n['stripColor'], 'apd_background_color', $palettes['background'], $plate_hex );
		}
		?>
	</div>
</div>
