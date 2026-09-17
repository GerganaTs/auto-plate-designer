<?php
/**
 * Admin settings tab: color library and named palettes.
 *
 * @package Auto_Plate_Designer
 *
 * @var APD_Admin_Settings $apd_admin Settings controller.
 */

defined( 'ABSPATH' ) || exit;

$library     = APD_Color_Palettes::library();
$palettes    = APD_Color_Palettes::all();
$edit_id     = isset( $_GET['edit'] ) ? sanitize_text_field( wp_unslash( $_GET['edit'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$edit_color_id = isset( $_GET['edit_color'] ) ? sanitize_text_field( wp_unslash( $_GET['edit_color'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$editing     = '' !== $edit_id ? APD_Color_Palettes::get( $edit_id ) : null;
$editing_color = '' !== $edit_color_id ? APD_Color_Palettes::get_color( $edit_color_id ) : null;

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
$swatch        = APD_Color_Palettes::swatch_display();
$swatch_radius = APD_Color_Palettes::swatch_radius_css( $swatch );
$purposes       = array(
	'text'       => __( 'Text', 'auto-plate-designer' ),
	'border'     => __( 'Border', 'auto-plate-designer' ),
	'background' => __( 'Plate fill', 'auto-plate-designer' ),
);
?>
<div class="apd-tab">
	<h2><?php esc_html_e( 'Color palette', 'auto-plate-designer' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Build named palettes here. On each WooCommerce product you choose which palettes appear in the configurator — for letters, the frame, or the plate fill. New products start with none selected.', 'auto-plate-designer' ); ?></p>

	<h3><?php esc_html_e( 'Shop swatches', 'auto-plate-designer' ); ?></h3>
	<p class="description"><?php esc_html_e( 'Size, shape, and border of the color buttons on the product page. This does not change the admin lists above the library.', 'auto-plate-designer' ); ?></p>

	<form method="post" action="<?php echo esc_url( $apd_admin->tab_url( 'palette' ) ); ?>" class="apd-form" id="apd-swatch-display-form">
		<?php wp_nonce_field( 'apd_save_settings', 'apd_settings_nonce' ); ?>
		<input type="hidden" name="apd_settings_action" value="save_swatch_display">

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="apd_swatch_size"><?php esc_html_e( 'Swatch size', 'auto-plate-designer' ); ?></label></th>
				<td>
					<input type="number" id="apd_swatch_size" name="apd_swatch[size]" min="10" max="100" step="1" value="<?php echo esc_attr( (string) $swatch['size'] ); ?>" data-apd-swatch-size>
					<span><?php esc_html_e( 'px', 'auto-plate-designer' ); ?></span>
					<p class="description"><?php esc_html_e( 'Width and height of one color on the site, from 10 to 100 pixels.', 'auto-plate-designer' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Swatch shape', 'auto-plate-designer' ); ?></th>
				<td>
					<fieldset class="apd-choice-list">
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
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="apd_swatch_radius"><?php esc_html_e( 'Border radius', 'auto-plate-designer' ); ?></label></th>
				<td>
					<input type="number" id="apd_swatch_radius" name="apd_swatch[radius]" min="0" max="50" step="1" value="<?php echo esc_attr( (string) $swatch['radius'] ); ?>" data-apd-swatch-radius <?php disabled( 'rounded' !== $swatch['shape'] ); ?>>
					<span><?php esc_html_e( 'px', 'auto-plate-designer' ); ?></span>
					<p class="description"><?php esc_html_e( 'Used only for squares with border radius.', 'auto-plate-designer' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="apd_swatch_border"><?php esc_html_e( 'Swatch border color', 'auto-plate-designer' ); ?></label></th>
				<td>
					<input type="color" id="apd_swatch_border" name="apd_swatch[border_color]" value="<?php echo esc_attr( APD_Color_Palettes::swatch_border_css( $swatch ) ); ?>" data-apd-swatch-border>
					<p class="description"><?php esc_html_e( 'Outline around each color on the site.', 'auto-plate-designer' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Preview', 'auto-plate-designer' ); ?></th>
				<td>
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
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Save shop swatches', 'auto-plate-designer' ) ); ?>
	</form>

	<h3><?php esc_html_e( 'Color library', 'auto-plate-designer' ); ?></h3>
	<p class="description"><?php esc_html_e( 'Master colors. Palettes pick from this list. The same color can sit on a text palette and a fill palette.', 'auto-plate-designer' ); ?></p>

	<?php if ( empty( $library ) ) : ?>
		<p><?php esc_html_e( 'No colors in the library yet.', 'auto-plate-designer' ); ?></p>
	<?php else : ?>
		<ul class="apd-color-list">
			<?php foreach ( $library as $color ) : ?>
				<li>
					<span class="apd-swatch" style="background: <?php echo esc_attr( $color['hex'] ); ?>"></span>
					<code><?php echo esc_html( $color['hex'] ); ?></code>
					<?php echo esc_html( $color['label'] ); ?>
					<a href="<?php echo esc_url( add_query_arg( 'edit_color', $color['id'], $apd_admin->tab_url( 'palette' ) ) . '#apd-color-form' ); ?>"><?php esc_html_e( 'Edit', 'auto-plate-designer' ); ?></a>
					|
					<a class="apd-js-confirm" href="<?php echo esc_url( $apd_admin->delete_url( 'colors', $color['id'] ) ); ?>"><?php esc_html_e( 'Remove', 'auto-plate-designer' ); ?></a>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( $apd_admin->tab_url( 'palette' ) ); ?>" class="apd-form" id="apd-color-form">
		<?php wp_nonce_field( 'apd_save_settings', 'apd_settings_nonce' ); ?>
		<input type="hidden" name="apd_settings_action" value="save_color">
		<input type="hidden" name="apd_color[id]" value="<?php echo esc_attr( $editing_color['id'] ); ?>">

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="apd_color_hex"><?php echo '' !== $editing_color['id'] ? esc_html__( 'Edit color', 'auto-plate-designer' ) : esc_html__( 'Add a color', 'auto-plate-designer' ); ?></label></th>
				<td>
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
				</td>
			</tr>
		</table>

		<?php
		submit_button( '' !== $editing_color['id'] ? __( 'Update color', 'auto-plate-designer' ) : __( 'Add color', 'auto-plate-designer' ) );

		if ( '' !== $editing_color['id'] ) {
			echo '<p><a href="' . esc_url( $apd_admin->tab_url( 'palette' ) ) . '">' . esc_html__( 'Cancel', 'auto-plate-designer' ) . '</a></p>';
		}
		?>
	</form>

	<h3><?php echo '' !== $editing['id'] ? esc_html__( 'Edit palette', 'auto-plate-designer' ) : esc_html__( 'Add a named palette', 'auto-plate-designer' ); ?></h3>
	<p class="description"><?php esc_html_e( 'Example: a Romanian fill palette with only white and yellow. Attach that palette on the product — not on a country preset.', 'auto-plate-designer' ); ?></p>

	<form method="post" action="<?php echo esc_url( $apd_admin->tab_url( 'palette' ) ); ?>" class="apd-form">
		<?php wp_nonce_field( 'apd_save_settings', 'apd_settings_nonce' ); ?>
		<input type="hidden" name="apd_settings_action" value="save_palette">
		<input type="hidden" name="apd_palette[id]" value="<?php echo esc_attr( $editing['id'] ); ?>">

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="apd_palette_name"><?php esc_html_e( 'Palette name', 'auto-plate-designer' ); ?></label></th>
				<td><input type="text" class="regular-text" id="apd_palette_name" name="apd_palette[name]" value="<?php echo esc_attr( $editing['name'] ); ?>" required></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Use for', 'auto-plate-designer' ); ?></th>
				<td>
					<div class="apd-choice-list">
					<?php foreach ( $purposes as $purpose => $purpose_label ) : ?>
						<label class="apd-choice">
							<input type="radio" name="apd_palette[purpose]" value="<?php echo esc_attr( $purpose ); ?>" <?php checked( $editing['purpose'], $purpose ); ?>>
							<?php echo esc_html( $purpose_label ); ?>
						</label>
					<?php endforeach; ?>
					</div>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Active', 'auto-plate-designer' ); ?></th>
				<td>
					<label class="apd-choice">
						<input type="hidden" name="apd_palette[active]" value="0">
						<input type="checkbox" name="apd_palette[active]" value="1" <?php checked( ! empty( $editing['active'] ) ); ?>>
						<?php esc_html_e( 'Inactive palettes stay in this list but are hidden from products.', 'auto-plate-designer' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Colors', 'auto-plate-designer' ); ?></th>
				<td>
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
					<p class="description" style="margin-top:8px;"><?php esc_html_e( 'New color (optional)', 'auto-plate-designer' ); ?></p>
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
					<p class="description"><?php esc_html_e( 'Leave blank unless you want to add a color while saving this palette.', 'auto-plate-designer' ); ?></p>
				</td>
			</tr>
		</table>

		<?php submit_button( '' !== $editing['id'] ? __( 'Update palette', 'auto-plate-designer' ) : __( 'Save palette', 'auto-plate-designer' ) ); ?>
	</form>

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
			<h3><?php echo esc_html( $purpose_label ); ?></h3>
			<?php if ( empty( $rows ) ) : ?>
				<p><?php esc_html_e( 'No palettes for this part yet.', 'auto-plate-designer' ); ?></p>
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
									<a href="<?php echo esc_url( add_query_arg( 'edit', $palette['id'], $apd_admin->tab_url( 'palette' ) ) ); ?>"><?php esc_html_e( 'Edit', 'auto-plate-designer' ); ?></a>
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
