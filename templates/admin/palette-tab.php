<?php
/**
 * Admin settings tab: color library and named palettes.
 *
 * @package Auto_Plate_Designer
 *
 * @var APD_Admin_Settings $apd_admin Settings controller.
 */

defined( 'ABSPATH' ) || exit;

$library       = APD_Color_Palettes::library();
$palettes      = APD_Color_Palettes::all();
$edit_id       = isset( $_GET['edit'] ) ? sanitize_text_field( wp_unslash( $_GET['edit'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$edit_color_id = isset( $_GET['edit_color'] ) ? sanitize_text_field( wp_unslash( $_GET['edit_color'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$add_palette   = isset( $_GET['add'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$add_color     = isset( $_GET['add_color'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$editing       = '' !== $edit_id ? APD_Color_Palettes::get( $edit_id ) : null;
$editing_color = '' !== $edit_color_id ? APD_Color_Palettes::get_color( $edit_color_id ) : null;
$show_palette  = is_array( $editing ) || $add_palette;
$show_color    = is_array( $editing_color ) || $add_color;

if ( ! is_array( $editing ) ) {
	$editing = array(
		'id'        => '',
		'name'      => '',
		'purpose'   => 'text',
		'color_ids' => array(),
		'active'    => true,
	);
}

if ( ! is_array( $editing_color ) ) {
	$editing_color = array(
		'id'    => '',
		'hex'   => '',
		'label' => '',
	);
}

$editing_colors = isset( $editing['color_ids'] ) && is_array( $editing['color_ids'] ) ? $editing['color_ids'] : array();
$swatch         = APD_Color_Palettes::swatch_display();
$swatch_radius  = APD_Color_Palettes::swatch_radius_css( $swatch );
$purposes       = array(
	'text'       => __( 'Text', 'auto-plate-designer' ),
	'border'     => __( 'Border', 'auto-plate-designer' ),
	'background' => __( 'Plate fill', 'auto-plate-designer' ),
);
$palette_url    = $apd_admin->tab_url( 'palette' );
?>
<div class="apd-tab apd-tab--palette">
	<h2><?php esc_html_e( 'Color palette', 'auto-plate-designer' ); ?></h2>
	<p class="apd-lede"><?php esc_html_e( 'This tab has three jobs: how color buttons look in the shop, the master list of colors, and the named palettes products can offer (letters, frame, or plate fill). Work top to bottom. New products start with no palette selected.', 'auto-plate-designer' ); ?></p>

	<section class="apd-card" aria-labelledby="apd-swatch-heading">
		<h3 id="apd-swatch-heading" class="apd-card__title"><?php esc_html_e( '1. Shop buttons', 'auto-plate-designer' ); ?></h3>
		<p class="apd-card__lede"><?php esc_html_e( 'These settings only change the round or square color buttons on the product page. They do not add or remove colors.', 'auto-plate-designer' ); ?></p>

		<form method="post" action="<?php echo esc_url( $palette_url ); ?>" class="apd-form apd-form--card" id="apd-swatch-display-form">
			<?php wp_nonce_field( 'apd_save_settings', 'apd_settings_nonce' ); ?>
			<input type="hidden" name="apd_settings_action" value="save_swatch_display">

			<div class="apd-field-grid">
				<div class="apd-field">
					<label for="apd_swatch_size"><?php esc_html_e( 'Button size', 'auto-plate-designer' ); ?></label>
					<p class="apd-field__hint"><?php esc_html_e( 'Width and height of one color, 10–100 pixels.', 'auto-plate-designer' ); ?></p>
					<span class="apd-field__control">
						<input type="number" id="apd_swatch_size" name="apd_swatch[size]" min="10" max="100" step="1" value="<?php echo esc_attr( (string) $swatch['size'] ); ?>" data-apd-swatch-size>
						<span class="apd-metric__unit"><?php esc_html_e( 'px', 'auto-plate-designer' ); ?></span>
					</span>
				</div>
				<div class="apd-field apd-field--span">
					<span class="apd-field__label"><?php esc_html_e( 'Shape', 'auto-plate-designer' ); ?></span>
					<fieldset class="apd-choice-list apd-choice-list--inline">
						<label class="apd-choice">
							<input type="radio" name="apd_swatch[shape]" value="circle" data-apd-swatch-shape <?php checked( $swatch['shape'], 'circle' ); ?>>
							<?php esc_html_e( 'Circles', 'auto-plate-designer' ); ?>
						</label>
						<label class="apd-choice">
							<input type="radio" name="apd_swatch[shape]" value="square" data-apd-swatch-shape <?php checked( $swatch['shape'], 'square' ); ?>>
							<?php esc_html_e( 'Squares (no rounding)', 'auto-plate-designer' ); ?>
						</label>
						<label class="apd-choice">
							<input type="radio" name="apd_swatch[shape]" value="rounded" data-apd-swatch-shape <?php checked( $swatch['shape'], 'rounded' ); ?>>
							<?php esc_html_e( 'Squares with border radius', 'auto-plate-designer' ); ?>
						</label>
					</fieldset>
				</div>
				<div class="apd-field">
					<label for="apd_swatch_radius"><?php esc_html_e( 'Corner rounding', 'auto-plate-designer' ); ?></label>
					<p class="apd-field__hint"><?php esc_html_e( 'Used only when shape is squares with border radius.', 'auto-plate-designer' ); ?></p>
					<span class="apd-field__control">
						<input type="number" id="apd_swatch_radius" name="apd_swatch[radius]" min="0" max="50" step="1" value="<?php echo esc_attr( (string) $swatch['radius'] ); ?>" data-apd-swatch-radius <?php disabled( 'rounded' !== $swatch['shape'] ); ?>>
						<span class="apd-metric__unit"><?php esc_html_e( 'px', 'auto-plate-designer' ); ?></span>
					</span>
				</div>
				<div class="apd-field">
					<label for="apd_swatch_border"><?php esc_html_e( 'Outline color', 'auto-plate-designer' ); ?></label>
					<p class="apd-field__hint"><?php esc_html_e( 'Thin border around each shop button.', 'auto-plate-designer' ); ?></p>
					<input type="color" id="apd_swatch_border" name="apd_swatch[border_color]" value="<?php echo esc_attr( APD_Color_Palettes::swatch_border_css( $swatch ) ); ?>" data-apd-swatch-border>
				</div>
				<div class="apd-field apd-field--span">
					<span class="apd-field__label"><?php esc_html_e( 'Live preview', 'auto-plate-designer' ); ?></span>
					<div
						class="apd-swatch-shop-preview"
						data-apd-swatch-preview
						style="--apd-swatch-size: <?php echo esc_attr( (string) (int) $swatch['size'] ); ?>px; --apd-swatch-radius: <?php echo esc_attr( $swatch_radius ); ?>; --apd-swatch-border: <?php echo esc_attr( APD_Color_Palettes::swatch_border_css( $swatch ) ); ?>;"
					>
						<span class="apd-swatch" style="background:#000000"></span>
						<span class="apd-swatch" style="background:#C41E3A"></span>
						<span class="apd-swatch" style="background:#1B365D"></span>
						<span class="apd-swatch" style="background:#FFFFFF"></span>
					</div>
				</div>
			</div>

			<?php submit_button( __( 'Save shop swatches', 'auto-plate-designer' ) ); ?>
		</form>
	</section>

	<section class="apd-card" aria-labelledby="apd-library-heading">
		<h3 id="apd-library-heading" class="apd-card__title"><?php esc_html_e( '2. Color library', 'auto-plate-designer' ); ?></h3>
		<p class="apd-card__lede"><?php esc_html_e( 'Master colors used by palettes. One color can appear on a text palette and a fill palette at the same time.', 'auto-plate-designer' ); ?></p>

		<?php if ( empty( $library ) ) : ?>
			<p class="apd-empty"><?php esc_html_e( 'No colors in the library yet.', 'auto-plate-designer' ); ?></p>
		<?php else : ?>
			<ul class="apd-color-list apd-color-list--cards">
				<?php foreach ( $library as $color ) : ?>
					<li>
						<span class="apd-swatch" style="background: <?php echo esc_attr( $color['hex'] ); ?>"></span>
						<span class="apd-color-list__meta">
							<strong><?php echo esc_html( $color['label'] ); ?></strong>
							<code><?php echo esc_html( $color['hex'] ); ?></code>
						</span>
						<span class="apd-color-list__actions">
							<a href="<?php echo esc_url( add_query_arg( 'edit_color', $color['id'], $palette_url ) ); ?>"><?php esc_html_e( 'Edit', 'auto-plate-designer' ); ?></a>
							|
							<a class="apd-js-confirm" href="<?php echo esc_url( $apd_admin->delete_url( 'colors', $color['id'] ) ); ?>"><?php esc_html_e( 'Remove', 'auto-plate-designer' ); ?></a>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if ( ! $show_color ) : ?>
			<p class="apd-toolbar">
				<a class="button button-primary" href="<?php echo esc_url( add_query_arg( 'add_color', '1', $palette_url ) ); ?>"><?php esc_html_e( 'Add color', 'auto-plate-designer' ); ?></a>
			</p>
		<?php else : ?>
			<p class="apd-toolbar">
				<a class="button" href="<?php echo esc_url( $palette_url ); ?>"><?php esc_html_e( 'Cancel', 'auto-plate-designer' ); ?></a>
			</p>
			<h4 class="apd-card__subtitle"><?php echo '' !== $editing_color['id'] ? esc_html__( 'Edit color', 'auto-plate-designer' ) : esc_html__( 'Add a color', 'auto-plate-designer' ); ?></h4>
			<form method="post" action="<?php echo esc_url( $palette_url ); ?>" class="apd-form apd-form--card" id="apd-color-form">
				<?php wp_nonce_field( 'apd_save_settings', 'apd_settings_nonce' ); ?>
				<input type="hidden" name="apd_settings_action" value="save_color">
				<input type="hidden" name="apd_color[id]" value="<?php echo esc_attr( $editing_color['id'] ); ?>">

				<div class="apd-metric-grid">
					<label class="apd-metric"><?php echo esc_html__( 'Hex', 'auto-plate-designer' ); ?>
						<span class="apd-metric__control">
							<input type="text" id="apd_color_hex" name="apd_color[hex]" value="<?php echo esc_attr( $editing_color['hex'] ); ?>" placeholder="#1A2B3C" pattern="^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$" required>
						</span>
					</label>
					<label class="apd-metric"><?php echo esc_html__( 'Label', 'auto-plate-designer' ); ?>
						<span class="apd-metric__control">
							<input type="text" name="apd_color[label]" value="<?php echo esc_attr( $editing_color['label'] ); ?>" placeholder="<?php esc_attr_e( 'Label', 'auto-plate-designer' ); ?>" required>
						</span>
					</label>
				</div>
				<?php if ( '' !== $editing_color['id'] ) : ?>
					<p class="description"><?php esc_html_e( 'Edit keeps the color on every palette that already uses it.', 'auto-plate-designer' ); ?></p>
				<?php else : ?>
					<p class="description"><?php esc_html_e( 'Tick the palettes that should offer this color in the configurator.', 'auto-plate-designer' ); ?></p>
					<div class="apd-choice-list">
					<?php foreach ( $palettes as $palette ) : ?>
						<label class="apd-choice">
							<input type="checkbox" name="apd_color[palette_ids][]" value="<?php echo esc_attr( $palette['id'] ); ?>" checked>
							<?php echo esc_html( $palette['name'] . ' (' . APD_Color_Palettes::purpose_label( $palette['purpose'] ) . ')' ); ?>
						</label>
					<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<?php submit_button( '' !== $editing_color['id'] ? __( 'Update color', 'auto-plate-designer' ) : __( 'Add color', 'auto-plate-designer' ) ); ?>
			</form>
		<?php endif; ?>
	</section>

	<section class="apd-card" aria-labelledby="apd-palettes-heading">
		<h3 id="apd-palettes-heading" class="apd-card__title"><?php esc_html_e( '3. Named palettes', 'auto-plate-designer' ); ?></h3>
		<p class="apd-card__lede"><?php esc_html_e( 'A palette is a named set of colors for one part of the plate — letters, frame, or fill. Attach palettes on the WooCommerce product, not on a country preset. Example: a Romanian fill palette with only white and yellow.', 'auto-plate-designer' ); ?></p>

		<div class="apd-palette-groups">
			<?php foreach ( $purposes as $purpose => $purpose_label ) : ?>
				<?php
				$rows = array();

				foreach ( $palettes as $palette ) {
					if ( isset( $palette['purpose'] ) && $palette['purpose'] === $purpose ) {
						$rows[] = $palette;
					}
				}
				?>
				<section class="apd-palette-group">
					<h4 class="apd-palette-group__title"><?php echo esc_html( $purpose_label ); ?></h4>
					<?php if ( empty( $rows ) ) : ?>
						<p class="apd-empty"><?php esc_html_e( 'No palettes for this part yet.', 'auto-plate-designer' ); ?></p>
					<?php else : ?>
						<div class="apd-table-scroll">
						<table class="widefat striped">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Name', 'auto-plate-designer' ); ?></th>
									<th><?php esc_html_e( 'Colors', 'auto-plate-designer' ); ?></th>
									<th><?php esc_html_e( 'Active', 'auto-plate-designer' ); ?></th>
									<th><?php esc_html_e( 'Actions', 'auto-plate-designer' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $rows as $palette ) : ?>
									<?php $palette_colors = APD_Color_Palettes::palette_colors( $palette ); ?>
									<tr>
										<td><?php echo esc_html( $palette['name'] ); ?></td>
										<td>
											<?php if ( empty( $palette_colors ) ) : ?>
												&mdash;
											<?php else : ?>
												<ul class="apd-color-list apd-color-list--compact">
													<?php foreach ( $palette_colors as $color ) : ?>
														<li>
															<span class="apd-swatch" style="background: <?php echo esc_attr( $color['hex'] ); ?>"></span>
															<?php echo esc_html( $color['label'] ); ?>
														</li>
													<?php endforeach; ?>
												</ul>
											<?php endif; ?>
										</td>
										<td><?php echo empty( $palette['active'] ) ? esc_html__( 'No', 'auto-plate-designer' ) : esc_html__( 'Yes', 'auto-plate-designer' ); ?></td>
										<td>
											<a href="<?php echo esc_url( add_query_arg( 'edit', $palette['id'], $palette_url ) ); ?>"><?php esc_html_e( 'Edit', 'auto-plate-designer' ); ?></a>
											|
											<a class="apd-js-confirm" href="<?php echo esc_url( $apd_admin->delete_url( 'color_palettes', $palette['id'] ) ); ?>"><?php esc_html_e( 'Delete', 'auto-plate-designer' ); ?></a>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
						</div>
					<?php endif; ?>
				</section>
			<?php endforeach; ?>
		</div>

		<?php if ( ! $show_palette ) : ?>
			<p class="apd-toolbar">
				<a class="button button-primary" href="<?php echo esc_url( add_query_arg( 'add', '1', $palette_url ) ); ?>"><?php esc_html_e( 'Add palette', 'auto-plate-designer' ); ?></a>
			</p>
		<?php else : ?>
			<p class="apd-toolbar">
				<a class="button" href="<?php echo esc_url( $palette_url ); ?>"><?php esc_html_e( 'Cancel', 'auto-plate-designer' ); ?></a>
			</p>
			<h4 class="apd-card__subtitle"><?php echo '' !== $editing['id'] ? esc_html__( 'Edit palette', 'auto-plate-designer' ) : esc_html__( 'Add a named palette', 'auto-plate-designer' ); ?></h4>
			<form method="post" action="<?php echo esc_url( $palette_url ); ?>" class="apd-form apd-form--card">
				<?php wp_nonce_field( 'apd_save_settings', 'apd_settings_nonce' ); ?>
				<input type="hidden" name="apd_settings_action" value="save_palette">
				<input type="hidden" name="apd_palette[id]" value="<?php echo esc_attr( $editing['id'] ); ?>">

				<div class="apd-field-grid">
					<div class="apd-field apd-field--span">
						<label for="apd_palette_name"><?php esc_html_e( 'Palette name', 'auto-plate-designer' ); ?></label>
						<input type="text" class="regular-text" id="apd_palette_name" name="apd_palette[name]" value="<?php echo esc_attr( $editing['name'] ); ?>" required>
					</div>
					<div class="apd-field apd-field--span">
						<span class="apd-field__label"><?php esc_html_e( 'Use for', 'auto-plate-designer' ); ?></span>
						<p class="apd-field__hint"><?php esc_html_e( 'Which part of the plate this palette paints.', 'auto-plate-designer' ); ?></p>
						<div class="apd-choice-list apd-choice-list--inline">
						<?php foreach ( $purposes as $purpose => $purpose_label ) : ?>
							<label class="apd-choice">
								<input type="radio" name="apd_palette[purpose]" value="<?php echo esc_attr( $purpose ); ?>" <?php checked( $editing['purpose'], $purpose ); ?>>
								<?php echo esc_html( $purpose_label ); ?>
							</label>
						<?php endforeach; ?>
						</div>
					</div>
					<div class="apd-field apd-field--span">
						<label class="apd-choice">
							<input type="hidden" name="apd_palette[active]" value="0">
							<input type="checkbox" name="apd_palette[active]" value="1" <?php checked( ! empty( $editing['active'] ) ); ?>>
							<?php esc_html_e( 'Active — uncheck to hide this palette from products without deleting it.', 'auto-plate-designer' ); ?>
						</label>
					</div>
					<div class="apd-field apd-field--span">
						<span class="apd-field__label"><?php esc_html_e( 'Colors in this palette', 'auto-plate-designer' ); ?></span>
						<?php if ( empty( $library ) ) : ?>
							<p class="description"><?php esc_html_e( 'Add colors to the library first.', 'auto-plate-designer' ); ?></p>
						<?php else : ?>
							<div class="apd-palette-color-picks">
								<?php foreach ( $library as $color ) : ?>
									<label class="apd-choice">
										<input type="checkbox" name="apd_palette[color_ids][]" value="<?php echo esc_attr( $color['id'] ); ?>" <?php checked( in_array( $color['id'], $editing_colors, true ) ); ?>>
										<span class="apd-swatch" style="background: <?php echo esc_attr( $color['hex'] ); ?>"></span>
										<?php echo esc_html( $color['label'] ); ?>
										<code><?php echo esc_html( $color['hex'] ); ?></code>
									</label>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</div>
					<div class="apd-field apd-field--span">
						<span class="apd-field__label"><?php esc_html_e( 'Optional: add a new color while saving', 'auto-plate-designer' ); ?></span>
						<p class="apd-field__hint"><?php esc_html_e( 'Leave blank unless you want to create a color and attach it to this palette in one step.', 'auto-plate-designer' ); ?></p>
						<div class="apd-metric-grid">
							<label class="apd-metric"><?php echo esc_html__( 'Hex', 'auto-plate-designer' ); ?>
								<span class="apd-metric__control">
									<input type="text" id="apd_palette_hex" name="apd_palette[hex]" placeholder="#1A2B3C" pattern="^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$">
								</span>
							</label>
							<label class="apd-metric"><?php echo esc_html__( 'Label', 'auto-plate-designer' ); ?>
								<span class="apd-metric__control">
									<input type="text" name="apd_palette[label]" placeholder="<?php esc_attr_e( 'Label', 'auto-plate-designer' ); ?>">
								</span>
							</label>
						</div>
					</div>
				</div>

				<?php submit_button( '' !== $editing['id'] ? __( 'Update palette', 'auto-plate-designer' ) : __( 'Save palette', 'auto-plate-designer' ) ); ?>
			</form>
		<?php endif; ?>
	</section>
</div>
