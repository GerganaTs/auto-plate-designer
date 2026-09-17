<?php
/**
 * Admin settings tab: which catalog categories are published.
 *
 * @package Auto_Plate_Designer
 *
 * @var APD_Admin_Settings $apd_admin Settings controller.
 */

defined( 'ABSPATH' ) || exit;

$definitions = APD_Catalog::definitions();
$published   = APD_Catalog::published_slugs();
?>
<div class="apd-tab">
	<h2><?php esc_html_e( 'Catalog', 'auto-plate-designer' ); ?></h2>
	<p class="description"><?php esc_html_e( 'These WooCommerce product categories are created by the plugin. Uncheck a category to hide it from the shop. Formats stay in Auto Plate Designer until you create a product for each model.', 'auto-plate-designer' ); ?></p>

	<form method="post" action="<?php echo esc_url( $apd_admin->tab_url( 'catalog' ) ); ?>" class="apd-form">
		<?php wp_nonce_field( 'apd_save_settings', 'apd_settings_nonce' ); ?>
		<input type="hidden" name="apd_settings_action" value="save_catalog">

		<table class="form-table" role="presentation">
			<?php foreach ( $definitions as $slug => $def ) : ?>
				<tr>
					<th><?php echo esc_html( isset( $def['name'] ) ? $def['name'] : $slug ); ?></th>
					<td>
						<label class="apd-choice">
							<input type="checkbox" name="apd_catalog[published][]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $published, true ) ); ?>>
							<?php
							printf(
								/* translators: 1: category slug, 2: format kind */
								esc_html__( 'Publish in the shop (slug: %1$s, type: %2$s)', 'auto-plate-designer' ),
								esc_html( $slug ),
								esc_html( isset( $def['kind'] ) ? $def['kind'] : '' )
							);
							?>
						</label>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>

		<?php submit_button( __( 'Save catalog', 'auto-plate-designer' ) ); ?>
	</form>
</div>
