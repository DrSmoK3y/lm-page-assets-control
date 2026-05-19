<?php
/**
 * Plugin Name:       LM Page Assets Control
 * Plugin URI:        https://lmwebdesigners.com/plugin/lm-page-assets-control/
 * Description:       Block specific CSS and JS files from loading on pages where they are not needed. Improves page speed by preventing unnecessary assets from loading. Use with caution - incorrect rules may break your site's appearance or functionality.
 * Version:           1.0.0
 * Requires at least: 5.0
 * Requires PHP:      7.4
 * Author:            LM Designers
 * Author URI:        https://lmwebdesigners.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       lm-page-assets-control
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LPAC_VERSION', '1.0.0' );
define( 'LPAC_OPT',     'lpac_rules' );

global $LPAC_ACTIVE_BLOCKS;
$LPAC_ACTIVE_BLOCKS = array();

// ── Step 1: Check page conditions on template_redirect ──────────
// is_page(), is_front_page() etc. only work reliably here.
add_action( 'template_redirect', 'lpac_init_blocker', 1 );

function lpac_init_blocker() {
	global $LPAC_ACTIVE_BLOCKS;
	$rules = get_option( LPAC_OPT, array() );
	foreach ( (array) $rules as $rule ) {
		if ( empty( $rule['enabled'] ) || empty( $rule['asset_handle'] ) ) {
			continue;
		}
		if ( lpac_page_matches( $rule ) ) {
			$LPAC_ACTIVE_BLOCKS[] = array(
				'handle'    => trim( $rule['asset_handle'] ),
				'url_match' => trim( isset( $rule['asset_url'] ) ? $rule['asset_url'] : '' ),
				'type'      => isset( $rule['asset_type'] ) ? $rule['asset_type'] : 'both',
			);
		}
	}

	// Set flag so frontend can enqueue debug script if needed.
	if ( current_user_can( 'manage_options' ) && get_option( 'lpac_debug_mode', 0 ) ) {
		$GLOBALS['lpac_show_debug'] = true;
	}
}

// ── Step 2: Multiple hooks — some plugins enqueue late ─
add_action( 'wp_enqueue_scripts', 'lpac_block_assets', PHP_INT_MAX );
add_action( 'wp_print_styles',    'lpac_block_assets', PHP_INT_MAX );
add_action( 'wp_print_scripts',   'lpac_block_assets', PHP_INT_MAX );

function lpac_block_assets() {
	global $LPAC_ACTIVE_BLOCKS, $wp_scripts, $wp_styles;
	if ( empty( $LPAC_ACTIVE_BLOCKS ) ) {
		return;
	}

	foreach ( $LPAC_ACTIVE_BLOCKS as $block ) {
		$handle   = strtolower( $block['handle'] );
		$urlmatch = strtolower( $block['url_match'] );
		$type     = $block['type'];

		if ( in_array( $type, array( 'css', 'both' ), true ) && isset( $wp_styles ) && is_object( $wp_styles ) ) {
			foreach ( array_keys( (array) $wp_styles->registered ) as $h ) {
				$hl = strtolower( $h );
				if ( ! empty( $handle ) && strpos( $hl, $handle ) !== false ) {
					wp_dequeue_style( $h );
					wp_deregister_style( $h );
					continue;
				}
				if ( ! empty( $urlmatch ) && isset( $wp_styles->registered[ $h ]->src ) ) {
					$src = strtolower( lpac_norm( $wp_styles->registered[ $h ]->src ) );
					if ( strpos( $src, $urlmatch ) !== false ) {
						wp_dequeue_style( $h );
						wp_deregister_style( $h );
					}
				}
			}
		}

		if ( in_array( $type, array( 'js', 'both' ), true ) && isset( $wp_scripts ) && is_object( $wp_scripts ) ) {
			foreach ( array_keys( (array) $wp_scripts->registered ) as $h ) {
				$hl = strtolower( $h );
				if ( ! empty( $handle ) && strpos( $hl, $handle ) !== false ) {
					wp_dequeue_script( $h );
					wp_deregister_script( $h );
					continue;
				}
				if ( ! empty( $urlmatch ) && isset( $wp_scripts->registered[ $h ]->src ) ) {
					$src = strtolower( lpac_norm( $wp_scripts->registered[ $h ]->src ) );
					if ( strpos( $src, $urlmatch ) !== false ) {
						wp_dequeue_script( $h );
						wp_deregister_script( $h );
					}
				}
			}
		}
	}
}

function lpac_norm( $src ) {
	if ( substr( $src, 0, 2 ) === '//' ) {
		return 'https:' . $src;
	}
	if ( substr( $src, 0, 1 ) === '/' ) {
		return home_url( $src );
	}
	return $src;
}

function lpac_page_matches( $rule ) {
	$cond = isset( $rule['condition'] ) ? $rule['condition'] : 'everywhere';
	$val  = trim( isset( $rule['page_value'] ) ? $rule['page_value'] : '' );
	$uri  = sanitize_text_field( wp_unslash( isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '' ) );

	switch ( $cond ) {
		case 'everywhere':
			return true;
		case 'is_front_page':
			return is_front_page();
		case 'not_front_page':
			return ! is_front_page();
		case 'is_home':
			return is_home();
		case 'is_single':
			return is_single();
		case 'is_archive':
			return is_archive();
		case 'is_category':
			return is_category();
		case 'is_tag':
			return is_tag();
		case 'is_search':
			return is_search();
		case 'is_404':
			return is_404();
		case 'is_shop':
			return function_exists( 'is_shop' ) && is_shop();
		case 'not_shop':
			return ! ( function_exists( 'is_shop' ) && is_shop() );
		case 'is_product':
			return function_exists( 'is_product' ) && is_product();
		case 'is_product_category':
			return function_exists( 'is_product_category' ) && is_product_category();
		case 'is_cart':
			return function_exists( 'is_cart' ) && is_cart();
		case 'is_checkout':
			return function_exists( 'is_checkout' ) && is_checkout();
		case 'is_account_page':
			return function_exists( 'is_account_page' ) && is_account_page();
		case 'url_contains':
			return ! empty( $val ) && strpos( $uri, $val ) !== false;
		case 'url_not_contains':
			return ! empty( $val ) && strpos( $uri, $val ) === false;
		case 'is_page':
			if ( empty( $val ) ) {
				return is_page();
			}
			$pages = array_map(
				function ( $p ) {
					$p = trim( $p );
					return is_numeric( $p ) ? absint( $p ) : $p;
				},
				explode( ',', $val )
			);
			return is_page( $pages );
		default:
			return false;
	}
}

// ═══════════════════════════════════════════════════════════════════
// DEBUG MODE — Show all handles in footer (admins only)
// WordPress core = yellow | Plugins/Theme = green
// ═══════════════════════════════════════════════════════════════════

add_action( 'wp_footer', 'lpac_debug_output', PHP_INT_MAX );

function lpac_debug_output() {
	if ( ! current_user_can( 'manage_options' ) || ! get_option( 'lpac_debug_mode', 0 ) ) {
		return;
	}

	global $wp_styles, $wp_scripts;
	$styles  = array();
	$scripts = array();

	if ( isset( $wp_styles ) && is_object( $wp_styles ) ) {
		foreach ( $wp_styles->queue as $h ) {
			if ( ! isset( $wp_styles->registered[ $h ] ) ) {
				continue;
			}
			$styles[] = array( 'handle' => $h, 'src' => $wp_styles->registered[ $h ]->src );
		}
	}

	if ( isset( $wp_scripts ) && is_object( $wp_scripts ) ) {
		foreach ( $wp_scripts->queue as $h ) {
			if ( ! isset( $wp_scripts->registered[ $h ] ) ) {
				continue;
			}
			$scripts[] = array( 'handle' => $h, 'src' => $wp_scripts->registered[ $h ]->src );
		}
	}

	usort( $styles,  function ( $a, $b ) { return strcmp( $a['handle'], $b['handle'] ); } );
	usort( $scripts, function ( $a, $b ) { return strcmp( $a['handle'], $b['handle'] ); } );

	// Determine if asset belongs to plugin or theme (green) vs WP core (yellow).
	$site_url    = site_url();
	$plugins_url = plugins_url();
	$theme_url   = get_theme_root_uri();

	echo '<div id="lpac-debug" style="position:fixed;bottom:0;left:0;right:0;height:400px;background:#0f172a;color:#e2e8f0;font-family:monospace;font-size:12px;z-index:999999;border-top:3px solid #38bdf8;display:flex;flex-direction:column">';

	echo '<div style="display:flex;align-items:center;justify-content:space-between;padding:8px 14px;background:#1e293b;border-bottom:1px solid #334155;flex-shrink:0">';
	echo '<span style="color:#38bdf8;font-weight:700">LM Page Assets Control Debug &mdash; ';
	echo '<span style="color:#86efac">&#9646; Green = Plugin/Theme</span> &nbsp; ';
	echo '<span style="color:#fde68a">&#9646; Yellow = WordPress Core</span>';
	echo '</span>';
	echo '<button id="lpac-debug-close" type="button" style="background:#ef4444;color:#fff;border:none;border-radius:4px;padding:4px 12px;cursor:pointer;font-weight:700">&#x2715; Close</button>';
	echo '</div>';

	echo '<div style="display:flex;flex:1;overflow:hidden">';

	$panels = array(
		'css' => array( $styles, '&#127912;', 'CSS' ),
		'js'  => array( $scripts, '&#9881;&#65039;', 'JS' ),
	);

	foreach ( $panels as $ptype => $data ) {
		list( $items, $icon, $label ) = $data;

		$border = ( 'css' === $ptype ) ? 'border-right:2px solid #334155;' : '';
		echo '<div style="flex:1;overflow-y:auto;' . esc_attr( $border ) . '">';

		echo '<div style="padding:5px 12px;background:#1e293b;font-weight:700;font-size:10px;text-transform:uppercase;position:sticky;top:0">';
		echo wp_kses_post( $icon . ' ' . $label . ' (' . count( $items ) . ') &mdash; click handle to copy' );
		echo '</div>';

		foreach ( $items as $idx => $asset ) {
			$src_str = isset( $asset['src'] ) ? (string) $asset['src'] : '';
			$handle  = $asset['handle'];

			// Green = plugin or theme file | Yellow = WordPress core / unknown
			$is_plugin_or_theme = (
				! empty( $src_str ) && (
					strpos( $src_str, $plugins_url ) !== false ||
					strpos( $src_str, $theme_url ) !== false ||
					strpos( $src_str, '/plugins/' ) !== false ||
					strpos( $src_str, '/themes/' ) !== false
				)
			);

			$row_bg     = ( 0 !== $idx % 2 ) ? 'background:#ffffff08;' : '';
			$handle_col = $is_plugin_or_theme ? '#86efac' : '#fde68a';

			echo '<div style="display:flex;' . esc_attr( $row_bg ) . 'padding:3px 0">';

			// Handle cell — clickable to copy (handled via delegated enqueued script)
			echo '<div style="color:' . esc_attr( $handle_col ) . ';';
			echo 'padding:3px 10px;font-weight:700;cursor:pointer;min-width:220px;white-space:nowrap"';
			echo ' title="' . esc_attr__( 'Click to copy', 'lm-page-assets-control' ) . '"';
			echo ' data-lpac-copy="' . esc_attr( $handle ) . '">';
			echo esc_html( $handle );
			echo '</div>';

			// Source URL cell
			echo '<div style="color:#94a3b8;padding:3px 8px;font-size:11px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">';
			echo esc_html( $src_str );
			echo '</div>';

			echo '</div>';
		}

		echo '</div>';
	}

	echo '</div></div>';
}

// Enqueue debug-panel JS on frontend when debug mode is active.
add_action( 'wp_enqueue_scripts', function () {
	if ( empty( $GLOBALS['lpac_show_debug'] ) ) {
		return;
	}
	wp_register_script( 'lpac-debug', false, array(), LPAC_VERSION, true );
	wp_enqueue_script( 'lpac-debug' );
	wp_add_inline_script(
		'lpac-debug',
		'document.addEventListener("click",function(e){' .
			'if(e.target&&e.target.id==="lpac-debug-close"){' .
				'e.preventDefault();' .
				'var d=document.getElementById("lpac-debug");' .
				'if(d)d.remove();' .
			'}' .
			'var c=e.target.closest?e.target.closest("[data-lpac-copy]"):null;' .
			'if(c){' .
				'navigator.clipboard.writeText(c.getAttribute("data-lpac-copy"));' .
				'c.style.background="#16a34a";' .
				'setTimeout(function(){c.style.background="";},1000);' .
			'}' .
		'});'
	);
} );

// ═══════════════════════════════════════════════════════════════════
// ADMIN MENU
// ═══════════════════════════════════════════════════════════════════

add_action( 'admin_menu', function () {
	add_plugins_page(
		__( 'LM Page Assets Control', 'lm-page-assets-control' ),
		__( 'LM Page Assets Control', 'lm-page-assets-control' ),
		'manage_options',
		'lm-page-assets-control',
		'lpac_admin_page'
	);
} );

// ── Helper: condition definitions ───────────────────────────────
function lpac_get_conditions() {
	return array(
		'everywhere'          => array( 'label' => __( 'Everywhere (all pages)', 'lm-page-assets-control' ),            'icon' => '🌐' ),
		'is_front_page'       => array( 'label' => __( 'Home / Front page', 'lm-page-assets-control' ),                 'icon' => '🏠' ),
		'not_front_page'      => array( 'label' => __( 'Everywhere except home', 'lm-page-assets-control' ),             'icon' => '🚫' ),
		'is_home'             => array( 'label' => __( 'Blog index page', 'lm-page-assets-control' ),                   'icon' => '📰' ),
		'is_single'           => array( 'label' => __( 'Single posts', 'lm-page-assets-control' ),                       'icon' => '📄' ),
		'is_page'             => array( 'label' => __( 'Specific page (slug or ID)', 'lm-page-assets-control' ),        'icon' => '📌' ),
		'is_archive'          => array( 'label' => __( 'Archive pages', 'lm-page-assets-control' ),                     'icon' => '📂' ),
		'is_category'         => array( 'label' => __( 'Category pages', 'lm-page-assets-control' ),                    'icon' => '🗂️' ),
		'is_tag'              => array( 'label' => __( 'Tag pages', 'lm-page-assets-control' ),                         'icon' => '🏷️' ),
		'is_search'           => array( 'label' => __( 'Search results', 'lm-page-assets-control' ),                    'icon' => '🔎' ),
		'is_404'              => array( 'label' => __( '404 page', 'lm-page-assets-control' ),                          'icon' => '❌' ),
		'is_shop'             => array( 'label' => __( 'WooCommerce — Shop', 'lm-page-assets-control' ),           'icon' => '🛍️' ),
		'not_shop'            => array( 'label' => __( 'WooCommerce — Not shop', 'lm-page-assets-control' ),      'icon' => '🚫' ),
		'is_product'          => array( 'label' => __( 'WooCommerce — Single product', 'lm-page-assets-control' ), 'icon' => '📦' ),
		'is_product_category' => array( 'label' => __( 'WooCommerce — Product category', 'lm-page-assets-control' ),'icon' => '🗃️' ),
		'is_cart'             => array( 'label' => __( 'WooCommerce — Cart', 'lm-page-assets-control' ),           'icon' => '🛒' ),
		'is_checkout'         => array( 'label' => __( 'WooCommerce — Checkout', 'lm-page-assets-control' ),       'icon' => '💳' ),
		'is_account_page'     => array( 'label' => __( 'WooCommerce — My Account', 'lm-page-assets-control' ),    'icon' => '👤' ),
		'url_contains'        => array( 'label' => __( 'URL contains…', 'lm-page-assets-control' ),               'icon' => '🔍' ),
		'url_not_contains'    => array( 'label' => __( 'URL does NOT contain…', 'lm-page-assets-control' ),        'icon' => '🔍' ),
	);
}

function lpac_get_needs_val_conditions() {
	return array( 'is_page', 'url_contains', 'url_not_contains' );
}

// ── Admin assets (CSS + JS) ────────────────────────────────────────
add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( 'plugins_page_lm-page-assets-control' !== $hook ) {
		return;
	}

	// Dummy style handle for inline admin CSS.
	wp_register_style( 'lpac-admin-style', false, array(), LPAC_VERSION );
	wp_enqueue_style( 'lpac-admin-style' );
	wp_add_inline_style( 'lpac-admin-style', lpac_inline_css() );

	// Dummy script handle for inline admin JS.
	wp_register_script( 'lpac-admin', false, array(), LPAC_VERSION, false );
	wp_enqueue_script( 'lpac-admin' );

	$rules      = get_option( LPAC_OPT, array() );
	$conditions = lpac_get_conditions();
	$needs_val  = lpac_get_needs_val_conditions();

	wp_add_inline_script( 'lpac-admin', lpac_get_admin_js( count( $rules ), $conditions, $needs_val ) );
} );

// ── Save handler ──────────────────────────────────────────────────
add_action( 'admin_post_lpac_save', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Unauthorized', 'lm-page-assets-control' ) );
	}
	if ( ! isset( $_POST['lpac_nonce'] ) ||
		 ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lpac_nonce'] ) ), 'lpac_save' ) ) {
		wp_die( esc_html__( 'Security check failed', 'lm-page-assets-control' ) );
	}

	update_option( 'lpac_debug_mode', isset( $_POST['lpac_debug'] ) ? 1 : 0 );

	$rlpac_rules = isset( $_POST['lpac_rules'] ) ? map_deep( wp_unslash( $_POST['lpac_rules'] ), 'sanitize_text_field' ) : array();
	$rules     = array();

	foreach ( (array) $rlpac_rules as $r ) {
		if ( empty( $r['asset_handle'] ) ) {
			continue;
		}
		$rules[] = array(
			'enabled'      => ! empty( $r['enabled'] ),
			'label'        => sanitize_text_field( isset( $r['label'] )        ? $r['label']        : '' ),
			'asset_handle' => sanitize_text_field( isset( $r['asset_handle'] ) ? $r['asset_handle'] : '' ),
			'asset_url'    => sanitize_text_field( isset( $r['asset_url'] )    ? $r['asset_url']    : '' ),
			'asset_type'   => sanitize_key( isset( $r['asset_type'] )          ? $r['asset_type']   : 'both' ),
			'condition'    => sanitize_key( isset( $r['condition'] )           ? $r['condition']    : 'everywhere' ),
			'page_value'   => sanitize_text_field( isset( $r['page_value'] )   ? $r['page_value']   : '' ),
		);
	}

	update_option( LPAC_OPT, $rules );
	wp_safe_redirect(
		admin_url( 'plugins.php?page=lm-page-assets-control&saved=1&lpac_nonce=' . wp_create_nonce( 'lpac_save' ) )
	);
	exit;
} );

// ═══════════════════════════════════════════════════════════════════
// ADMIN PAGE
// ═══════════════════════════════════════════════════════════════════

function lpac_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$rules    = get_option( LPAC_OPT, array() );
	$debug_on = get_option( 'lpac_debug_mode', 0 );
	$saved    = false;

	if ( isset( $_GET['saved'], $_GET['lpac_nonce'] ) &&
		 wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['lpac_nonce'] ) ), 'lpac_save' ) ) {
		$saved = true;
	}

	$conditions = lpac_get_conditions();
	$needs_val  = lpac_get_needs_val_conditions();
	?>
	<div id="lpac-wrap">

		<div class="lpac-hdr">
			<div>
				<h1 class="lpac-title"><?php esc_html_e( 'LM Page Assets Control', 'lm-page-assets-control' ); ?></h1>
			</div>
		</div>
		<p class="lpac-sub"><?php esc_html_e( 'Block unwanted CSS/JS from selected pages. Use Debug Mode to find exact handle names.', 'lm-page-assets-control' ); ?></p>

		<?php if ( $saved ) : ?>
		<div class="lpac-ok">&#10003; <?php esc_html_e( 'Rules saved!', 'lm-page-assets-control' ); ?></div>
		<?php endif; ?>

		<!-- DEBUG MODE CARD -->
		<div class="lpac-debug-card <?php echo $debug_on ? 'active' : ''; ?>">
			<div class="lpac-dc-left">
				<div class="lpac-dc-title">
					<?php echo $debug_on
						? esc_html__( 'Debug Mode is ON', 'lm-page-assets-control' )
						: esc_html__( 'Debug Mode (OFF)', 'lm-page-assets-control' ); ?>
				</div>
				<div class="lpac-dc-desc">
					<strong><?php esc_html_e( 'Workflow:', 'lm-page-assets-control' ); ?></strong>
					<<ol style="margin:5px 0 0 14px;padding:0;font-size:12px;line-height:1.9">
						<li><?php esc_html_e( 'Enable Debug Mode → Save', 'lm-page-assets-control' ); ?></li>
						<li><?php esc_html_e( 'Visit any page on your site (as logged-in admin)', 'lm-page-assets-control' ); ?></li>
						<li><?php esc_html_e( 'Bottom panel shows all CSS/JS assets on that page', 'lm-page-assets-control' ); ?>
							&mdash; <span style="color:#166534;font-weight:700"><?php esc_html_e( 'Green = Plugin/Theme', 'lm-page-assets-control' ); ?></span>,
							<span style="color:#92400e;font-weight:700"><?php esc_html_e( 'Yellow = WordPress Core', 'lm-page-assets-control' ); ?></span>
						</li>
						<li><?php esc_html_e( 'Click any handle name to copy it', 'lm-page-assets-control' ); ?></li>
						<li><?php esc_html_e( 'Add a rule below, paste handle → select where to block → Save', 'lm-page-assets-control' ); ?></li>
						<li><strong><?php esc_html_e( 'Disable Debug Mode when done!', 'lm-page-assets-control' ); ?></strong></li>
					</ol>
				</div>
			</div>

			<form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="flex-shrink:0;margin-top:6px">
				<?php wp_nonce_field( 'lpac_save', 'lpac_nonce' ); ?>
				<input type="hidden" name="action" value="lpac_save">
				<?php
				foreach ( $rules as $i => $r ) {
					foreach ( array( 'enabled', 'label', 'asset_handle', 'asset_url', 'asset_type', 'condition', 'page_value' ) as $f ) {
						$fv = 'enabled' === $f ? ( ! empty( $r[ $f ] ) ? '1' : '' ) : ( isset( $r[ $f ] ) ? $r[ $f ] : '' );
						echo '<input type="hidden" name="lpac_rules[' . absint( $i ) . '][' . esc_attr( $f ) . ']" value="' . esc_attr( $fv ) . '">';
					}
				}
				?>
				<?php if ( $debug_on ) : ?>
					<button type="submit" class="btn-debug off"><?php esc_html_e( 'Disable Debug Mode', 'lm-page-assets-control' ); ?></button>
				<?php else : ?>
					<button type="submit" name="lpac_debug" value="1" class="btn-debug on"><?php esc_html_e( 'Enable Debug Mode', 'lm-page-assets-control' ); ?></button>
				<?php endif; ?>
			</form>
		</div>

		<div class="lpac-warn">
			<span>&#9888;&#65039;</span>
			<span><?php esc_html_e( 'Wrong handles can break site design. Always use Debug Mode first. Test on staging before live.', 'lm-page-assets-control' ); ?></span>
		</div>

		<form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'lpac_save', 'lpac_nonce' ); ?>
			<input type="hidden" name="action" value="lpac_save">
			<?php if ( $debug_on ) : ?>
				<input type="hidden" name="lpac_debug" value="1">
			<?php endif; ?>

			<div id="lpac-rules-wrap">
				<?php if ( empty( $rules ) ) : ?>
				<div class="lpac-empty" id="lpac-es">
					<p style="font-size:36px;margin:0">&#128141;</p>
					<p style="font-weight:600"><?php esc_html_e( 'No rules yet', 'lm-page-assets-control' ); ?></p>
					<p style="font-size:12px;color:#aaa"><?php esc_html_e( 'Enable Debug Mode first to find handle names, then add rules.', 'lm-page-assets-control' ); ?></p>
				</div>
				<?php else :
					foreach ( $rules as $i => $r ) {
						lpac_render_rule( $i, $r, $conditions, $needs_val );
					}
				endif; ?>
			</div>

			<button type="button" class="btn-add" onclick="lpacAdd()">
				+ <?php esc_html_e( 'Add New Rule', 'lm-page-assets-control' ); ?>
			</button>

			<div class="lpac-bar">
				<span style="font-size:12px;color:#888"><?php esc_html_e( 'Page reloads after saving.', 'lm-page-assets-control' ); ?></span>
				<button type="submit" class="btn-save">&#128190; <?php esc_html_e( 'Save All Rules', 'lm-page-assets-control' ); ?></button>
			</div>
		</form>
	</div>
	<?php
}

// ── Render a single saved rule ────────────────────────────────────
function lpac_render_rule( $i, $rule, $conditions, $needs_val ) {
	$i       = absint( $i );
	$enabled = ! empty( $rule['enabled'] );
	$label   = isset( $rule['label'] )        ? $rule['label']        : '';
	$handle  = isset( $rule['asset_handle'] ) ? $rule['asset_handle'] : '';
	$url     = isset( $rule['asset_url'] )    ? $rule['asset_url']    : '';
	$atype   = isset( $rule['asset_type'] )   ? $rule['asset_type']   : 'both';
	$cond    = isset( $rule['condition'] )    ? $rule['condition']    : 'everywhere';
	$pgval   = isset( $rule['page_value'] )   ? $rule['page_value']   : '';
	$npv     = in_array( $cond, $needs_val, true );
	?>
	<div class="lpac-rule open <?php echo $enabled ? '' : 'disabled'; ?>" id="lpac-rule-<?php echo esc_attr( $i ); ?>">

		<div class="lpac-rh" onclick="lpacTog(<?php echo esc_js( $i ); ?>)">
			<div class="lpac-num"><?php echo esc_html( $i + 1 ); ?></div>
			<input class="lpac-name" type="text"
				   name="lpac_rules[<?php echo esc_attr( $i ); ?>][label]"
				   value="<?php echo esc_attr( $label ); ?>"
				   placeholder="<?php esc_attr_e( 'Rule name', 'lm-page-assets-control' ); ?>"
				   onclick="event.stopPropagation()">
			<div class="lpac-sw-wrap">
				<span class="lpac-sw-lbl"><?php esc_html_e( 'Active', 'lm-page-assets-control' ); ?></span>
				<label class="lpac-sw">
					<input type="checkbox"
						   name="lpac_rules[<?php echo esc_attr( $i ); ?>][enabled]"
						   value="1"
						   <?php checked( $enabled ); ?>
						   onchange="lpacDim(this,<?php echo esc_js( $i ); ?>)">
					<span class="lpac-track"></span>
				</label>
			</div>
			<span class="lpac-chev">&#9660;</span>
		</div>

		<div class="lpac-rb">

			<div class="lpac-f lpac-primary">
				<span class="lpac-lbl">
					&#127919; <?php esc_html_e( 'Asset Handle', 'lm-page-assets-control' ); ?>
					<span class="lpac-badge"><?php esc_html_e( 'Get from Debug Mode', 'lm-page-assets-control' ); ?></span>
				</span>
				<input type="text"
					   name="lpac_rules[<?php echo esc_attr( $i ); ?>][asset_handle]"
					   value="<?php echo esc_attr( $handle ); ?>"
					   placeholder="<?php esc_attr_e( 'e.g. blocksy-woocommerce', 'lm-page-assets-control' ); ?>">
				<p class="lpac-hint"><?php esc_html_e( 'Handle from debug panel. Partial match works.', 'lm-page-assets-control' ); ?></p>
			</div>

			<div class="lpac-f">
				<span class="lpac-lbl">
					&#128279; <?php esc_html_e( 'URL Fallback', 'lm-page-assets-control' ); ?>
					<span class="lpac-badge lpac-badge-gray"><?php esc_html_e( 'Optional', 'lm-page-assets-control' ); ?></span>
				</span>
				<input type="text"
					   name="lpac_rules[<?php echo esc_attr( $i ); ?>][asset_url]"
					   value="<?php echo esc_attr( $url ); ?>"
					   placeholder="<?php esc_attr_e( 'e.g. woocommerce.min.css', 'lm-page-assets-control' ); ?>">
				<p class="lpac-hint"><?php esc_html_e( 'Used only if handle match fails.', 'lm-page-assets-control' ); ?></p>
			</div>

			<div class="lpac-f">
				<span class="lpac-lbl">&#128193; <?php esc_html_e( 'Type', 'lm-page-assets-control' ); ?></span>
				<div style="display:flex;gap:8px;flex-wrap:wrap">
					<?php foreach ( array( 'both' => __( 'Both CSS+JS', 'lm-page-assets-control' ), 'css' => __( 'CSS Only', 'lm-page-assets-control' ), 'js' => __( 'JS Only', 'lm-page-assets-control' ) ) as $v => $lt ) : ?>
					<label class="lpac-pill <?php echo esc_attr( $atype === $v ? 'sel' : '' ); ?>" onclick="lpacT(this)">
						<input type="radio"
							   name="lpac_rules[<?php echo esc_attr( $i ); ?>][asset_type]"
							   value="<?php echo esc_attr( $v ); ?>"
							   <?php checked( $atype, $v ); ?>>
						<?php echo esc_html( $lt ); ?>
					</label>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="lpac-f">
				<span class="lpac-lbl">&#128205; <?php esc_html_e( 'Where to block', 'lm-page-assets-control' ); ?></span>
				<div class="lpac-cg" id="lpac-cg-<?php echo esc_attr( $i ); ?>">
					<?php foreach ( $conditions as $val => $info ) : ?>
					<label class="lpac-pill <?php echo esc_attr( $cond === $val ? 'sel' : '' ); ?>"
						   onclick="lpacSel(this,<?php echo esc_js( $i ); ?>)">
						<input type="radio"
							   name="lpac_rules[<?php echo esc_attr( $i ); ?>][condition]"
							   value="<?php echo esc_attr( $val ); ?>"
							   <?php checked( $cond, $val ); ?>>
						<span><?php echo esc_html( $info['icon'] ); ?></span>
						<?php echo esc_html( $info['label'] ); ?>
					</label>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="lpac-pv <?php echo $npv ? 'show' : ''; ?>" id="lpac-pv-<?php echo esc_attr( $i ); ?>">
				<div class="lpac-f">
					<span class="lpac-lbl"><?php esc_html_e( 'Page slug / ID / URL', 'lm-page-assets-control' ); ?></span>
					<input type="text"
						   name="lpac_rules[<?php echo esc_attr( $i ); ?>][page_value]"
						   value="<?php echo esc_attr( $pgval ); ?>"
						   placeholder="<?php esc_attr_e( 'e.g. about or /contact', 'lm-page-assets-control' ); ?>">
					<p class="lpac-hint"><?php esc_html_e( 'Multiple pages: about, services, contact', 'lm-page-assets-control' ); ?></p>
				</div>
			</div>
		</div>

		<div class="lpac-rf">
			<button type="button" class="btn-rm" onclick="lpacRm(<?php echo esc_js( $i ); ?>)">
				&times; <?php esc_html_e( 'Remove Rule', 'lm-page-assets-control' ); ?>
			</button>
		</div>
	</div>
	<?php
}

// ── Inline admin JS (delivered via wp_add_inline_script) ──────────
function lpac_get_admin_js( $rule_count, $conditions, $needs_val ) {
	ob_start();
	?>
	var lpacI = <?php echo absint( $rule_count ); ?>;
	var lpacC = <?php echo wp_json_encode( $conditions ); ?>;
	var lpacNV = <?php echo wp_json_encode( $needs_val ); ?>;

	function lpacAdd() {
		var es = document.getElementById( 'lpac-es' );
		if ( es ) es.style.display = 'none';
		var i = lpacI++, d = document.createElement( 'div' );
		d.innerHTML = lpacHTML( i );
		document.getElementById( 'lpac-rules-wrap' ).appendChild( d.firstElementChild );
	}

	function lpacHTML( i ) {
		var p = '', f = true;
		for ( var k in lpacC ) {
			p += '<label class="lpac-pill' + ( f ? ' sel' : '' ) + '" onclick="lpacSel(this,' + i + ')">'
			   + '<input type="radio" name="lpac_rules[' + i + '][condition]" value="' + k + '"' + ( f ? ' checked' : '' ) + '>'
			   + '<span>' + lpacC[ k ].icon + '</span>' + lpacC[ k ].label + '</label>';
			f = false;
		}
		return '<div class="lpac-rule open" id="lpac-rule-' + i + '">'
			+ '<div class="lpac-rh" onclick="lpacTog(' + i + ')">'
			+ '<div class="lpac-num">' + ( i + 1 ) + '</div>'
			+ '<input class="lpac-name" type="text" name="lpac_rules[' + i + '][label]" placeholder="<?php echo esc_js( __( 'Rule name', 'lm-page-assets-control' ) ); ?>" onclick="event.stopPropagation()">'
			+ '<div class="lpac-sw-wrap"><span class="lpac-sw-lbl"><?php echo esc_js( __( 'Active', 'lm-page-assets-control' ) ); ?></span>'
			+ '<label class="lpac-sw"><input type="checkbox" name="lpac_rules[' + i + '][enabled]" value="1" checked onchange="lpacDim(this,' + i + ')"><span class="lpac-track"></span></label></div>'
			+ '<span class="lpac-chev">&#9660;</span></div>'
			+ '<div class="lpac-rb">'
			+ '<div class="lpac-f lpac-primary"><span class="lpac-lbl">&#127919; <?php echo esc_js( __( 'Asset Handle', 'lm-page-assets-control' ) ); ?> <span class="lpac-badge"><?php echo esc_js( __( 'Get from Debug Mode', 'lm-page-assets-control' ) ); ?></span></span>'
			+ '<input type="text" name="lpac_rules[' + i + '][asset_handle]" placeholder="<?php echo esc_js( __( 'e.g. blocksy-woocommerce', 'lm-page-assets-control' ) ); ?>">'
			+ '<p class="lpac-hint"><?php echo esc_js( __( 'Enable Debug → visit page → click green handle → paste here. Partial match works.', 'lm-page-assets-control' ) ); ?></p></div>'
			+ '<div class="lpac-f"><span class="lpac-lbl">&#128279; <?php echo esc_js( __( 'URL Fallback', 'lm-page-assets-control' ) ); ?> <span class="lpac-badge lpac-badge-gray"><?php echo esc_js( __( 'Optional', 'lm-page-assets-control' ) ); ?></span></span>'
			+ '<input type="text" name="lpac_rules[' + i + '][asset_url]" placeholder="<?php echo esc_js( __( 'e.g. woocommerce.min.css', 'lm-page-assets-control' ) ); ?>">'
			+ '<p class="lpac-hint"><?php echo esc_js( __( 'Used only if handle match finds nothing.', 'lm-page-assets-control' ) ); ?></p></div>'
			+ '<div class="lpac-f"><span class="lpac-lbl">&#128193; <?php echo esc_js( __( 'Type', 'lm-page-assets-control' ) ); ?></span>'
			+ '<div style="display:flex;gap:8px;flex-wrap:wrap">'
			+ '<label class="lpac-pill sel" onclick="lpacT(this)"><input type="radio" name="lpac_rules[' + i + '][asset_type]" value="both" checked><?php echo esc_js( __( 'Both CSS+JS', 'lm-page-assets-control' ) ); ?></label>'
			+ '<label class="lpac-pill" onclick="lpacT(this)"><input type="radio" name="lpac_rules[' + i + '][asset_type]" value="css"><?php echo esc_js( __( 'CSS Only', 'lm-page-assets-control' ) ); ?></label>'
			+ '<label class="lpac-pill" onclick="lpacT(this)"><input type="radio" name="lpac_rules[' + i + '][asset_type]" value="js"><?php echo esc_js( __( 'JS Only', 'lm-page-assets-control' ) ); ?></label>'
			+ '</div></div>'
			+ '<div class="lpac-f"><span class="lpac-lbl">&#128205; <?php echo esc_js( __( 'Where to block', 'lm-page-assets-control' ) ); ?></span>'
			+ '<div class="lpac-cg" id="lpac-cg-' + i + '">' + p + '</div></div>'
			+ '<div class="lpac-pv" id="lpac-pv-' + i + '"><div class="lpac-f"><span class="lpac-lbl"><?php echo esc_js( __( 'Page slug / ID / URL', 'lm-page-assets-control' ) ); ?></span>'
			+ '<input type="text" name="lpac_rules[' + i + '][page_value]" placeholder="<?php echo esc_js( __( 'e.g. about or /contact', 'lm-page-assets-control' ) ); ?>">'
			+ '<p class="lpac-hint"><?php echo esc_js( __( 'Multiple pages: about, services, contact', 'lm-page-assets-control' ) ); ?></p></div></div>'
			+ '</div>'
			+ '<div class="lpac-rf"><button type="button" class="btn-rm" onclick="lpacRm(' + i + ')"><?php echo esc_js( __( '&times; Remove', 'lm-page-assets-control' ) ); ?></button></div>'
			+ '</div>';
	}

	function lpacTog( i ) { var el = document.getElementById( 'lpac-rule-' + i ); if ( el ) el.classList.toggle( 'open' ); }
	function lpacDim( cb, i ) { var el = document.getElementById( 'lpac-rule-' + i ); if ( el ) el.classList.toggle( 'disabled', ! cb.checked ); }
	function lpacRm( i )  { var el = document.getElementById( 'lpac-rule-' + i ); if ( el ) el.remove(); }
	function lpacT( l )   { l.closest( 'div' ).querySelectorAll( '.lpac-pill' ).forEach( function( x ) { x.classList.remove( 'sel' ); } ); l.classList.add( 'sel' ); }

	function lpacSel( l, i ) {
		var cg = document.getElementById( 'lpac-cg-' + i );
		if ( cg ) cg.querySelectorAll( '.lpac-pill' ).forEach( function( x ) { x.classList.remove( 'sel' ); } );
		l.classList.add( 'sel' );
		var v  = l.querySelector( 'input' ).value;
		var pv = document.getElementById( 'lpac-pv-' + i );
		if ( pv ) pv.classList.toggle( 'show', lpacNV.indexOf( v ) !== -1 );
	}

	document.addEventListener( 'DOMContentLoaded', function() {
		document.querySelectorAll( '.lpac-pill' ).forEach( function( l ) {
			var inp = l.querySelector( 'input:checked' );
			if ( ! inp ) return;
			l.classList.add( 'sel' );
			var card = l.closest( '[id^="lpac-rule-"]' );
			if ( ! card ) return;
			var i  = card.id.replace( 'lpac-rule-', '' );
			var v  = inp.value;
			var pv = document.getElementById( 'lpac-pv-' + i );
			if ( pv ) pv.classList.toggle( 'show', lpacNV.indexOf( v ) !== -1 );
		} );
	} );
	<?php
	return ob_get_clean();
}

// ── Inline CSS ────────────────────────────────────────────────────
function lpac_inline_css() {
	return '
	#lpac-wrap{max-width:940px;margin:30px auto;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;}
	.lpac-hdr{display:flex;align-items:center;gap:14px;margin-bottom:4px;}
	.lpac-title{margin:0;font-size:24px;font-weight:700;color:#1d2327;}
	.lpac-sub{color:#646970;margin:4px 0 18px;}
	.lpac-ok{background:#dcfce7;border:1px solid #16a34a;color:#166534;border-radius:8px;padding:12px 16px;margin-bottom:16px;font-weight:600;}
	.lpac-warn{display:flex;gap:10px;background:#fff8e1;border:1px solid #f59e0b;border-radius:8px;padding:12px 16px;margin-bottom:20px;font-size:13px;color:#78350f;}
	.lpac-debug-card{display:flex;align-items:flex-start;justify-content:space-between;gap:20px;background:#f0fdf4;border:2px solid #86efac;border-radius:10px;padding:16px 20px;margin-bottom:18px;}
	.lpac-debug-card.active{background:#fefce8;border-color:#fbbf24;}
	.lpac-dc-left{flex:1;}
	.lpac-dc-title{font-weight:700;font-size:15px;color:#166534;margin-bottom:6px;}
	.lpac-debug-card.active .lpac-dc-title{color:#92400e;}
	.lpac-dc-desc{font-size:12px;color:#374151;line-height:1.6;}
	.btn-debug{padding:9px 18px;border-radius:8px;border:none;font-weight:700;font-size:13px;cursor:pointer;white-space:nowrap;}
	.btn-debug.on{background:#16a34a;color:#fff;}
	.btn-debug.on:hover{background:#15803d;}
	.btn-debug.off{background:#dc2626;color:#fff;}
	.btn-debug.off:hover{background:#b91c1c;}
	.lpac-rule{background:#fff;border:1px solid #ddd;border-radius:10px;margin-bottom:14px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.06);}
	.lpac-rule.disabled{opacity:.5;}
	.lpac-rh{display:flex;align-items:center;gap:10px;padding:12px 16px;background:#f9f9f9;cursor:pointer;border-bottom:1px solid #eee;user-select:none;}
	.lpac-rule:not(.open) .lpac-rb,.lpac-rule:not(.open) .lpac-rf{display:none;}
	.lpac-num{background:#0073aa;color:#fff;border-radius:50%;width:26px;height:26px;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0;}
	.lpac-name{flex:1;border:none;background:transparent;font-size:14px;font-weight:600;color:#1d2327;outline:none;}
	.lpac-chev{margin-left:auto;font-size:11px;color:#888;transition:.2s;}
	.lpac-rule.open .lpac-chev{transform:rotate(180deg);}
	.lpac-sw-wrap{display:flex;align-items:center;gap:6px;flex-shrink:0;}
	.lpac-sw-lbl{font-size:12px;color:#555;}
	.lpac-sw{position:relative;display:inline-block;width:38px;height:22px;}
	.lpac-sw input{opacity:0;width:0;height:0;}
	.lpac-track{position:absolute;inset:0;background:#ccc;border-radius:20px;transition:.2s;cursor:pointer;}
	.lpac-sw input:checked+.lpac-track{background:#0073aa;}
	.lpac-track:before{content:"";position:absolute;width:16px;height:16px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.2s;}
	.lpac-sw input:checked+.lpac-track:before{transform:translateX(16px);}
	.lpac-rb{padding:16px 20px;}
	.lpac-f{margin-bottom:16px;}
	.lpac-primary{background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:12px 14px;}
	.lpac-lbl{display:flex;align-items:center;gap:6px;font-size:12px;font-weight:700;color:#1d2327;text-transform:uppercase;letter-spacing:.04em;margin-bottom:6px;}
	.lpac-badge{background:#0073aa;color:#fff;font-size:9px;padding:1px 7px;border-radius:10px;text-transform:none;letter-spacing:0;font-weight:700;}
	.lpac-badge-gray{background:#6b7280;}
	.lpac-f input[type=text]{width:100%;box-sizing:border-box;border:1px solid #c3c4c7;border-radius:6px;padding:8px 11px;font-size:13px;}
	.lpac-f input[type=text]:focus{border-color:#0073aa;outline:none;box-shadow:0 0 0 2px rgba(0,115,170,.15);}
	.lpac-hint{font-size:12px;color:#777;margin:4px 0 0;line-height:1.5;}
	.lpac-cg{display:flex;flex-wrap:wrap;gap:6px;}
	.lpac-pill{display:flex;align-items:center;gap:5px;padding:5px 11px;border:1.5px solid #ddd;border-radius:20px;font-size:12px;cursor:pointer;user-select:none;background:#fafafa;transition:all .15s;}
	.lpac-pill input{display:none;}
	.lpac-pill.sel{border-color:#0073aa;background:#e8f4fb;color:#0073aa;font-weight:700;}
	.lpac-pv{display:none;margin-top:10px;}
	.lpac-pv.show{display:block;}
	.lpac-rf{padding:10px 16px;border-top:1px solid #eee;background:#fafafa;}
	.btn-add{display:flex;align-items:center;gap:8px;background:#f0f6fb;border:2px dashed #0073aa;color:#0073aa;border-radius:8px;padding:12px 18px;font-size:14px;font-weight:700;cursor:pointer;width:100%;justify-content:center;margin-bottom:20px;transition:background .15s;}
	.btn-add:hover{background:#e0eef8;}
	.btn-rm{background:none;border:1px solid #dc2626;color:#dc2626;border-radius:6px;padding:5px 12px;font-size:12px;cursor:pointer;}
	.btn-rm:hover{background:#fef2f2;}
	.lpac-bar{display:flex;align-items:center;justify-content:space-between;padding:14px 0;border-top:2px solid #eee;}
	.btn-save{display:flex;align-items:center;gap:7px;background:#0073aa;color:#fff;border:none;border-radius:8px;padding:11px 22px;font-size:14px;font-weight:700;cursor:pointer;}
	.btn-save:hover{background:#006291;}
	.lpac-empty{text-align:center;padding:40px 20px;color:#888;}
	';
}
