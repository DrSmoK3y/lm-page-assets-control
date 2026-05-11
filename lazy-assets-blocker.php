<?php
/**
 * Plugin Name:       Lazy Assets Blocker
 * Plugin URI:        https://lmwebdesigners.com/plugin/lazy-assets-blocker/
 * Description:       Block specific CSS and JS files from loading on pages where they are not needed. Improves page speed by preventing unnecessary assets from loading. Use with caution - incorrect rules may break your site's appearance or functionality.
 * Version:           1.0.0
 * Requires at least: 5.0
 * Requires PHP:      7.4
 * Author:            DrSmoK3y
 * Author URI:        https://lmwebdesigners.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       lazy-assets-blocker
 * Domain Path:       /languages
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'LAB_VERSION', '1.0.0' );
define( 'LAB_OPT',     'lab_rules' );

global $LAB_ACTIVE_BLOCKS;
$LAB_ACTIVE_BLOCKS = array();

// Step 1: template_redirect pe page conditions check (is_page etc work here)
add_action( 'template_redirect', 'lab_init_blocker', 1 );
function lab_init_blocker() {
	global $LAB_ACTIVE_BLOCKS;
	$rules = get_option( LAB_OPT, array() );
	foreach ( (array) $rules as $rule ) {
		if ( empty( $rule['enabled'] ) || empty( $rule['asset_handle'] ) ) continue;
		if ( lab_page_matches( $rule ) ) {
			$LAB_ACTIVE_BLOCKS[] = array(
				'handle'    => trim( $rule['asset_handle'] ),
				'url_match' => trim( $rule['asset_url'] ?? '' ),
				'type'      => $rule['asset_type'] ?? 'both',
			);
		}
	}
}

// Step 2: Multiple hooks - kuch plugins late enqueue karte hain
add_action( 'wp_enqueue_scripts', 'lab_block_assets', PHP_INT_MAX );
add_action( 'wp_print_styles',    'lab_block_assets', PHP_INT_MAX );
add_action( 'wp_print_scripts',   'lab_block_assets', PHP_INT_MAX );

function lab_block_assets() {
	global $LAB_ACTIVE_BLOCKS, $wp_scripts, $wp_styles;
	if ( empty( $LAB_ACTIVE_BLOCKS ) ) return;

	foreach ( $LAB_ACTIVE_BLOCKS as $block ) {
		$handle = strtolower( $block['handle'] );
		$urlmatch = strtolower( $block['url_match'] );
		$type = $block['type'];

		if ( in_array( $type, array('css','both'), true ) && isset($wp_styles) && is_object($wp_styles) ) {
			foreach ( array_keys( (array) $wp_styles->registered ) as $h ) {
				$hl = strtolower($h);
				if ( !empty($handle) && strpos($hl, $handle) !== false ) {
					wp_dequeue_style($h); wp_deregister_style($h); continue;
				}
				if ( !empty($urlmatch) && isset($wp_styles->registered[$h]->src) ) {
					$src = strtolower( lab_norm( $wp_styles->registered[$h]->src ) );
					if ( strpos($src, $urlmatch) !== false ) { wp_dequeue_style($h); wp_deregister_style($h); }
				}
			}
		}

		if ( in_array( $type, array('js','both'), true ) && isset($wp_scripts) && is_object($wp_scripts) ) {
			foreach ( array_keys( (array) $wp_scripts->registered ) as $h ) {
				$hl = strtolower($h);
				if ( !empty($handle) && strpos($hl, $handle) !== false ) {
					wp_dequeue_script($h); wp_deregister_script($h); continue;
				}
				if ( !empty($urlmatch) && isset($wp_scripts->registered[$h]->src) ) {
					$src = strtolower( lab_norm( $wp_scripts->registered[$h]->src ) );
					if ( strpos($src, $urlmatch) !== false ) { wp_dequeue_script($h); wp_deregister_script($h); }
				}
			}
		}
	}
}

function lab_norm($src) {
	if ( substr($src,0,2) === '//' ) return 'https:'.$src;
	if ( substr($src,0,1) === '/'  ) return home_url($src);
	return $src;
}

function lab_page_matches($rule) {
	$cond = $rule['condition'] ?? 'everywhere';
	$val  = trim( $rule['page_value'] ?? '' );
	$uri  = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) );
	switch($cond) {
		case 'everywhere':      return true;
		case 'is_front_page':   return is_front_page();
		case 'not_front_page':  return !is_front_page();
		case 'is_home':         return is_home();
		case 'is_single':       return is_single();
		case 'is_archive':      return is_archive();
		case 'is_category':     return is_category();
		case 'is_tag':          return is_tag();
		case 'is_search':       return is_search();
		case 'is_404':          return is_404();
		case 'is_shop':         return function_exists('is_shop') && is_shop();
		case 'not_shop':        return !(function_exists('is_shop') && is_shop());
		case 'is_product':      return function_exists('is_product') && is_product();
		case 'is_product_category': return function_exists('is_product_category') && is_product_category();
		case 'is_cart':         return function_exists('is_cart') && is_cart();
		case 'is_checkout':     return function_exists('is_checkout') && is_checkout();
		case 'is_account_page': return function_exists('is_account_page') && is_account_page();
		case 'url_contains':    return !empty($val) && strpos($uri,$val)!==false;
		case 'url_not_contains':return !empty($val) && strpos($uri,$val)===false;
		case 'is_page':
			if (empty($val)) return is_page();
			$pages = array_map(function($p){ $p=trim($p); return is_numeric($p)?absint($p):$p; }, explode(',',$val));
			return is_page($pages);
		default: return false;
	}
}

// DEBUG MODE — footer pe all handles show karo (admins only)
add_action( 'wp_footer', 'lab_debug_output', PHP_INT_MAX );
function lab_debug_output() {
	if ( !current_user_can('manage_options') || !get_option('lab_debug_mode',0) ) return;
	global $wp_styles, $wp_scripts;
	$styles = $scripts = array();
	if ( isset($wp_styles) && is_object($wp_styles) ) {
		foreach ($wp_styles->queue as $h) {
			if (!isset($wp_styles->registered[$h])) continue;
			$styles[] = array('handle'=>$h, 'src'=>$wp_styles->registered[$h]->src);
		}
	}
	if ( isset($wp_scripts) && is_object($wp_scripts) ) {
		foreach ($wp_scripts->queue as $h) {
			if (!isset($wp_scripts->registered[$h])) continue;
			$scripts[] = array('handle'=>$h, 'src'=>$wp_scripts->registered[$h]->src);
		}
	}
	usort($styles,  function($a,$b){return strcmp($a['handle'],$b['handle']);});
	usort($scripts, function($a,$b){return strcmp($a['handle'],$b['handle']);});

	echo '<div id="lab-debug" style="position:fixed;bottom:0;left:0;right:0;height:360px;background:#0f172a;color:#e2e8f0;font-family:monospace;font-size:12px;z-index:999999;border-top:3px solid #38bdf8;display:flex;flex-direction:column">';
	echo '<div style="display:flex;align-items:center;justify-content:space-between;padding:8px 14px;background:#1e293b;border-bottom:1px solid #334155;flex-shrink:0">';
	echo '<span style="color:#38bdf8;font-weight:700">⚡ LAB Debug — Click any handle (yellow=plugin) to copy it, then paste in plugin rule</span>';
	echo '<button onclick="document.getElementById(\'lab-debug\').remove()" style="background:#ef4444;color:#fff;border:none;border-radius:4px;padding:4px 12px;cursor:pointer;font-weight:700">✕ Close</button>';
	echo '</div><div style="display:flex;flex:1;overflow:hidden">';

	foreach ( array('css'=>array($styles,'🎨','#86efac'), 'js'=>array($scripts,'⚙️','#fbbf24')) as $type=>$data ) {
		list($items, $icon, $color) = $data;
		echo '<div style="flex:1;overflow-y:auto' . ($type==='css'?';border-right:2px solid #334155':'') . '">';
		echo '<div style="padding:5px 12px;background:#1e293b;color:'.$color.';font-weight:700;font-size:10px;text-transform:uppercase;position:sticky;top:0">'.$icon.' '.strtoupper($type).' ('.count($items).') — use handle in plugin</div>';
		foreach ($items as $idx=>$s) {
			$bg  = $idx%2 ? 'background:#ffffff08;' : '';
			$isp = strpos($s['src'],'/plugins/')!==false || strpos($s['src'],'woocommerce')!==false || strpos($s['src'],'blocksy')!==false;
			$hl  = $isp ? 'color:#fde68a;' : 'color:'.$color.';';
			echo '<div style="display:flex;'.$bg.'padding:3px 0">';
			echo '<div style="'.$hl.'padding:3px 10px;font-weight:700;cursor:pointer;min-width:200px;white-space:nowrap" title="Click to copy" onclick="navigator.clipboard.writeText(\''.esc_js($s['handle']).'\');this.style.background=\'#16a34a\';setTimeout(()=>this.style.background=\'\',1000)">'.esc_html($s['handle']).'</div>';
			echo '<div style="color:#64748b;padding:3px 8px;font-size:11px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">'.esc_html($s['src']).'</div>';
			echo '</div>';
		}
		echo '</div>';
	}
	echo '</div></div>';
}

// ═══ ADMIN ═══════════════════════════════════════════════════════
add_action('admin_menu', function() {
	add_plugins_page('Lazy Assets Blocker','Asset Blocker','manage_options','lazy-assets-blocker','lab_admin_page');
});
add_action('admin_enqueue_scripts', function($hook) {
	if ('plugins_page_lazy-assets-blocker'!==$hook) return;
	wp_add_inline_style('wp-admin', lab_inline_css());
});

add_action('admin_post_lab_save', function() {
	if (!current_user_can('manage_options')) wp_die('Unauthorized');
	if (!isset($_POST['lab_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['lab_nonce'])),'lab_save')) wp_die('Security failed');
	update_option('lab_debug_mode', isset($_POST['lab_debug']) ? 1 : 0);
	$rules = array();
	foreach ((array)wp_unslash($_POST['lab_rules']??array()) as $r) {
		if (empty($r['asset_handle'])) continue;
		$rules[] = array(
			'enabled'      => !empty($r['enabled']),
			'label'        => sanitize_text_field($r['label']??''),
			'asset_handle' => sanitize_text_field($r['asset_handle']??''),
			'asset_url'    => sanitize_text_field($r['asset_url']??''),
			'asset_type'   => sanitize_text_field($r['asset_type']??'both'),
			'condition'    => sanitize_text_field($r['condition']??'everywhere'),
			'page_value'   => sanitize_text_field($r['page_value']??''),
		);
	}
	update_option(LAB_OPT, $rules);
	wp_safe_redirect(admin_url('plugins.php?page=lazy-assets-blocker&saved=1&lab_nonce='.wp_create_nonce('lab_save')));
	exit;
});

function lab_admin_page() {
	if (!current_user_can('manage_options')) return;
	$rules    = get_option(LAB_OPT, array());
	$debug_on = get_option('lab_debug_mode', 0);
	$saved    = false;
	if (isset($_GET['saved'],$_GET['lab_nonce'])&&wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['lab_nonce'])),'lab_save')) $saved=true;

	$conditions = array(
		'everywhere'=>array('label'=>'Everywhere (all pages)','icon'=>'🌐'),
		'is_front_page'=>array('label'=>'Home / Front page','icon'=>'🏠'),
		'not_front_page'=>array('label'=>'Everywhere except home','icon'=>'🚫'),
		'is_home'=>array('label'=>'Blog index page','icon'=>'📰'),
		'is_single'=>array('label'=>'Single posts','icon'=>'📄'),
		'is_page'=>array('label'=>'Specific page (slug or ID)','icon'=>'📌'),
		'is_archive'=>array('label'=>'Archive pages','icon'=>'📂'),
		'is_category'=>array('label'=>'Category pages','icon'=>'🗂️'),
		'is_tag'=>array('label'=>'Tag pages','icon'=>'🏷️'),
		'is_search'=>array('label'=>'Search results','icon'=>'🔎'),
		'is_404'=>array('label'=>'404 page','icon'=>'❌'),
		'is_shop'=>array('label'=>'WooCommerce — Shop','icon'=>'🛍️'),
		'not_shop'=>array('label'=>'WooCommerce — Not shop','icon'=>'🚫'),
		'is_product'=>array('label'=>'WooCommerce — Single product','icon'=>'📦'),
		'is_product_category'=>array('label'=>'WooCommerce — Product category','icon'=>'🗃️'),
		'is_cart'=>array('label'=>'WooCommerce — Cart','icon'=>'🛒'),
		'is_checkout'=>array('label'=>'WooCommerce — Checkout','icon'=>'💳'),
		'is_account_page'=>array('label'=>'WooCommerce — My Account','icon'=>'👤'),
		'url_contains'=>array('label'=>'URL contains…','icon'=>'🔍'),
		'url_not_contains'=>array('label'=>'URL does NOT contain…','icon'=>'🔍'),
	);
	$needs_val = array('is_page','url_contains','url_not_contains');
	?>
<div id="lab-wrap">
<div class="lb-hdr"><div><h1 class="lb-title">Lazy Assets Blocker</h1></div></div>
<p class="lb-sub">Block unwanted CSS/JS from selected pages. Use Debug Mode to find exact handle names.</p>
<?php if ($saved): ?><div class="lb-ok">✅ Rules saved!</div><?php endif; ?>

<div class="lb-debug-card <?php echo $debug_on?'active':''; ?>">
<div class="ldc-left">
<div class="ldc-title"><?php echo $debug_on?'🟢 Debug Mode is ON':'🔍 Debug Mode (OFF)'; ?></div>
<div class="ldc-desc">
<strong>Workflow:</strong>
<ol style="margin:5px 0 0 14px;padding:0;font-size:12px;line-height:1.9">
<li>Enable Debug Mode → Save</li>
<li>Visit any page on your site (as logged-in admin)</li>
<li>Bottom panel shows all CSS/JS — <strong style="color:#b45309">yellow = plugin/WooCommerce/Blocksy assets</strong></li>
<li>Click any handle name to copy it</li>
<li>Add a rule below, paste handle → select where to block → Save</li>
<li><strong>Disable Debug Mode when done!</strong></li>
</ol>
</div></div>
<form method="POST" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="flex-shrink:0;margin-top:6px">
<?php wp_nonce_field('lab_save','lab_nonce'); ?>
<input type="hidden" name="action" value="lab_save">
<?php foreach ($rules as $i=>$r): foreach (array('enabled','label','asset_handle','asset_url','asset_type','condition','page_value') as $f):
$fv = $f==='enabled' ? (!empty($r[$f])?'1':'') : ($r[$f]??''); ?>
<input type="hidden" name="lab_rules[<?php echo esc_attr($i); ?>][<?php echo esc_attr($f); ?>]" value="<?php echo esc_attr($fv); ?>">
<?php endforeach; endforeach; ?>
<?php if ($debug_on): ?><button type="submit" class="btn-debug off">🔴 Disable Debug Mode</button>
<?php else: ?><button type="submit" name="lab_debug" value="1" class="btn-debug on">🟢 Enable Debug Mode</button><?php endif; ?>
</form></div>

<div class="lb-warn"><span>⚠️</span><span>Wrong handles can break site design. Always use Debug Mode first. Test on staging before live.</span></div>

<form method="POST" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
<?php wp_nonce_field('lab_save','lab_nonce'); ?>
<input type="hidden" name="action" value="lab_save">
<?php if ($debug_on): ?><input type="hidden" name="lab_debug" value="1"><?php endif; ?>
<div id="lb-rules-wrap">
<?php if (empty($rules)): ?>
<div class="lb-empty" id="lb-es"><p style="font-size:36px;margin:0">📭</p><p style="font-weight:600">No rules yet</p><p style="font-size:12px;color:#aaa">Enable Debug Mode first to find handle names, then add rules.</p></div>
<?php else: foreach ($rules as $i=>$r) lab_render_rule($i,$r,$conditions,$needs_val); endif; ?>
</div>
<button type="button" class="btn-add" onclick="labAdd()">+ Add New Rule</button>
<div class="lb-bar"><span style="font-size:12px;color:#888">Page reloads after saving.</span><button type="submit" class="btn-save">💾 Save All Rules</button></div>
</form></div>

<script>
var LI=<?php echo absint(count($rules)); ?>,LC=<?php echo wp_json_encode($conditions); ?>,LNV=<?php echo wp_json_encode($needs_val); ?>;
function labAdd(){var es=document.getElementById('lb-es');if(es)es.style.display='none';var i=LI++,d=document.createElement('div');d.innerHTML=labHTML(i);document.getElementById('lb-rules-wrap').appendChild(d.firstElementChild);}
function labHTML(i){var p='',f=true;for(var k in LC){p+='<label class="lb-pill'+(f?' sel':'')+'" onclick="labSel(this,'+i+')"><input type="radio" name="lab_rules['+i+'][condition]" value="'+k+'"'+(f?' checked':'')+'><span>'+LC[k].icon+'</span>'+LC[k].label+'</label>';f=false;}
return '<div class="lb-rule open" id="lr-'+i+'"><div class="lb-rh" onclick="labTog('+i+')"><div class="lb-num">'+(i+1)+'</div><input class="lb-name" type="text" name="lab_rules['+i+'][label]" placeholder="Rule name" onclick="event.stopPropagation()"><div class="lb-sw-wrap"><span class="lb-sw-lbl">Active</span><label class="lb-sw"><input type="checkbox" name="lab_rules['+i+'][enabled]" value="1" checked onchange="labDim(this,'+i+')"><span class="lb-track"></span></label></div><span class="lb-chev">▼</span></div><div class="lb-rb"><div class="lb-f lb-primary"><span class="lb-lbl">🎯 Asset Handle <span class="lb-badge">Get from Debug Mode</span></span><input type="text" name="lab_rules['+i+'][asset_handle]" placeholder="e.g.  blocksy-woocommerce  (copy from debug panel)"><p class="lb-hint">Enable Debug → visit page → click yellow handle → paste here. Partial match works.</p></div><div class="lb-f"><span class="lb-lbl">🔗 URL Fallback <span class="lb-badge lb-badge-gray">Optional</span></span><input type="text" name="lab_rules['+i+'][asset_url]" placeholder="e.g.  woocommerce.min.css"><p class="lb-hint">Used only if handle match finds nothing.</p></div><div class="lb-f"><span class="lb-lbl">📁 Type</span><div style="display:flex;gap:8px;flex-wrap:wrap"><label class="lb-pill sel" onclick="labT(this)"><input type="radio" name="lab_rules['+i+'][asset_type]" value="both" checked>Both CSS+JS</label><label class="lb-pill" onclick="labT(this)"><input type="radio" name="lab_rules['+i+'][asset_type]" value="css">CSS Only</label><label class="lb-pill" onclick="labT(this)"><input type="radio" name="lab_rules['+i+'][asset_type]" value="js">JS Only</label></div></div><div class="lb-f"><span class="lb-lbl">📍 Where to block</span><div class="lb-cg" id="cg-'+i+'">'+p+'</div></div><div class="lb-pv" id="pv-'+i+'"><div class="lb-f"><span class="lb-lbl">Page slug/ID/URL</span><input type="text" name="lab_rules['+i+'][page_value]" placeholder="e.g. about  or  /contact"><p class="lb-hint">Multiple: <code>about, services</code></p></div></div></div><div class="lb-rf"><button type="button" class="btn-rm" onclick="labRm('+i+')">✕ Remove</button></div></div>';}
function labTog(i){document.getElementById('lr-'+i)?.classList.toggle('open');}
function labDim(cb,i){document.getElementById('lr-'+i)?.classList.toggle('disabled',!cb.checked);}
function labRm(i){document.getElementById('lr-'+i)?.remove();}
function labT(l){l.closest('div').querySelectorAll('.lb-pill').forEach(x=>x.classList.remove('sel'));l.classList.add('sel');}
function labSel(l,i){document.getElementById('cg-'+i)?.querySelectorAll('.lb-pill').forEach(x=>x.classList.remove('sel'));l.classList.add('sel');var v=l.querySelector('input').value,pv=document.getElementById('pv-'+i);if(pv)pv.classList.toggle('show',LNV.includes(v));}
document.querySelectorAll('.lb-pill').forEach(function(l){if(l.querySelector('input:checked')){l.classList.add('sel');var c=l.closest('[id^="lr-"]');if(!c)return;var i=c.id.replace('lr-',''),v=l.querySelector('input').value,pv=document.getElementById('pv-'+i);if(pv)pv.classList.toggle('show',LNV.includes(v));}});
</script>
<?php
}

function lab_render_rule($i,$rule,$conditions,$needs_val){
	$i=absint($i);$enabled=!empty($rule['enabled']);
	$label=$rule['label']??'';$handle=$rule['asset_handle']??'';$url=$rule['asset_url']??'';
	$atype=$rule['asset_type']??'both';$cond=$rule['condition']??'everywhere';$pgval=$rule['page_value']??'';
	$npv=in_array($cond,$needs_val,true);
	?>
<div class="lb-rule open <?php echo $enabled?'':'disabled'; ?>" id="lr-<?php echo esc_attr($i); ?>">
<div class="lb-rh" onclick="labTog(<?php echo esc_js($i); ?>)">
<div class="lb-num"><?php echo esc_html($i+1); ?></div>
<input class="lb-name" type="text" name="lab_rules[<?php echo esc_attr($i); ?>][label]" value="<?php echo esc_attr($label); ?>" placeholder="Rule name" onclick="event.stopPropagation()">
<div class="lb-sw-wrap"><span class="lb-sw-lbl">Active</span>
<label class="lb-sw"><input type="checkbox" name="lab_rules[<?php echo esc_attr($i); ?>][enabled]" value="1" <?php checked($enabled); ?> onchange="labDim(this,<?php echo esc_js($i); ?>)"><span class="lb-track"></span></label></div>
<span class="lb-chev">▼</span></div>
<div class="lb-rb">
<div class="lb-f lb-primary">
<span class="lb-lbl">🎯 Asset Handle <span class="lb-badge">Get from Debug Mode</span></span>
<input type="text" name="lab_rules[<?php echo esc_attr($i); ?>][asset_handle]" value="<?php echo esc_attr($handle); ?>" placeholder="e.g.  blocksy-woocommerce">
<p class="lb-hint">Handle from debug panel. Partial match works.</p></div>
<div class="lb-f">
<span class="lb-lbl">🔗 URL Fallback <span class="lb-badge lb-badge-gray">Optional</span></span>
<input type="text" name="lab_rules[<?php echo esc_attr($i); ?>][asset_url]" value="<?php echo esc_attr($url); ?>" placeholder="e.g.  woocommerce.min.css">
<p class="lb-hint">Used only if handle match fails.</p></div>
<div class="lb-f"><span class="lb-lbl">📁 Type</span>
<div style="display:flex;gap:8px;flex-wrap:wrap">
<?php foreach(array('both'=>'Both CSS+JS','css'=>'CSS Only','js'=>'JS Only') as $v=>$lt): ?>
<label class="lb-pill <?php echo $atype===$v?'sel':''; ?>" onclick="labT(this)"><input type="radio" name="lab_rules[<?php echo esc_attr($i); ?>][asset_type]" value="<?php echo esc_attr($v); ?>" <?php checked($atype,$v); ?>><?php echo esc_html($lt); ?></label>
<?php endforeach; ?></div></div>
<div class="lb-f"><span class="lb-lbl">📍 Where to block</span>
<div class="lb-cg" id="cg-<?php echo esc_attr($i); ?>">
<?php foreach($conditions as $val=>$info): ?>
<label class="lb-pill <?php echo $cond===$val?'sel':''; ?>" onclick="labSel(this,<?php echo esc_js($i); ?>)">
<input type="radio" name="lab_rules[<?php echo esc_attr($i); ?>][condition]" value="<?php echo esc_attr($val); ?>" <?php checked($cond,$val); ?>>
<span><?php echo esc_html($info['icon']); ?></span><?php echo esc_html($info['label']); ?></label>
<?php endforeach; ?></div></div>
<div class="lb-pv <?php echo $npv?'show':''; ?>" id="pv-<?php echo esc_attr($i); ?>">
<div class="lb-f"><span class="lb-lbl">Page slug/ID/URL</span>
<input type="text" name="lab_rules[<?php echo esc_attr($i); ?>][page_value]" value="<?php echo esc_attr($pgval); ?>" placeholder="e.g.  about  or  /contact">
<p class="lb-hint">Multiple: <code>about, services</code></p></div></div></div>
<div class="lb-rf"><button type="button" class="btn-rm" onclick="labRm(<?php echo esc_js($i); ?>)">✕ Remove Rule</button></div></div>
<?php }

function lab_inline_css(){return '
#lab-wrap{max-width:940px;margin:30px auto;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;}
.lb-hdr{display:flex;align-items:center;gap:14px;margin-bottom:4px;}.lb-icon{font-size:36px;}
.lb-title{margin:0;font-size:24px;font-weight:700;color:#1d2327;}.lb-ver{font-size:11px;background:#e0f0ff;color:#0073aa;padding:2px 8px;border-radius:20px;font-weight:700;}
.lb-sub{color:#646970;margin:4px 0 18px;}.lb-ok{background:#dcfce7;border:1px solid #16a34a;color:#166534;border-radius:8px;padding:12px 16px;margin-bottom:16px;font-weight:600;}
.lb-warn{display:flex;gap:10px;background:#fff8e1;border:1px solid #f59e0b;border-radius:8px;padding:12px 16px;margin-bottom:20px;font-size:13px;color:#78350f;}
.lb-debug-card{display:flex;align-items:flex-start;justify-content:space-between;gap:20px;background:#f0fdf4;border:2px solid #86efac;border-radius:10px;padding:16px 20px;margin-bottom:18px;}
.lb-debug-card.active{background:#fefce8;border-color:#fbbf24;}.ldc-left{flex:1;}
.ldc-title{font-weight:700;font-size:15px;color:#166534;margin-bottom:6px;}.lb-debug-card.active .ldc-title{color:#92400e;}
.ldc-desc{font-size:12px;color:#374151;line-height:1.6;}
.btn-debug{padding:9px 18px;border-radius:8px;border:none;font-weight:700;font-size:13px;cursor:pointer;white-space:nowrap;}
.btn-debug.on{background:#16a34a;color:#fff;}.btn-debug.on:hover{background:#15803d;}
.btn-debug.off{background:#dc2626;color:#fff;}.btn-debug.off:hover{background:#b91c1c;}
.lb-rule{background:#fff;border:1px solid #ddd;border-radius:10px;margin-bottom:14px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.06);}
.lb-rule.disabled{opacity:.5;}.lb-rh{display:flex;align-items:center;gap:10px;padding:12px 16px;background:#f9f9f9;cursor:pointer;border-bottom:1px solid #eee;user-select:none;}
.lb-rule:not(.open) .lb-rb,.lb-rule:not(.open) .lb-rf{display:none;}
.lb-num{background:#0073aa;color:#fff;border-radius:50%;width:26px;height:26px;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0;}
.lb-name{flex:1;border:none;background:transparent;font-size:14px;font-weight:600;color:#1d2327;outline:none;}
.lb-chev{margin-left:auto;font-size:11px;color:#888;transition:.2s;}.lb-rule.open .lb-chev{transform:rotate(180deg);}
.lb-sw-wrap{display:flex;align-items:center;gap:6px;flex-shrink:0;}.lb-sw-lbl{font-size:12px;color:#555;}
.lb-sw{position:relative;display:inline-block;width:38px;height:22px;}.lb-sw input{opacity:0;width:0;height:0;}
.lb-track{position:absolute;inset:0;background:#ccc;border-radius:20px;transition:.2s;cursor:pointer;}
.lb-sw input:checked+.lb-track{background:#0073aa;}
.lb-track:before{content:"";position:absolute;width:16px;height:16px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.2s;}
.lb-sw input:checked+.lb-track:before{transform:translateX(16px);}
.lb-rb{padding:16px 20px;}.lb-f{margin-bottom:16px;}
.lb-primary{background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:12px 14px;}
.lb-lbl{display:flex;align-items:center;gap:6px;font-size:12px;font-weight:700;color:#1d2327;text-transform:uppercase;letter-spacing:.04em;margin-bottom:6px;}
.lb-badge{background:#0073aa;color:#fff;font-size:9px;padding:1px 7px;border-radius:10px;text-transform:none;letter-spacing:0;font-weight:700;}
.lb-badge-gray{background:#6b7280;}.lb-f input[type=text]{width:100%;box-sizing:border-box;border:1px solid #c3c4c7;border-radius:6px;padding:8px 11px;font-size:13px;}
.lb-f input[type=text]:focus{border-color:#0073aa;outline:none;box-shadow:0 0 0 2px rgba(0,115,170,.15);}
.lb-hint{font-size:12px;color:#777;margin:4px 0 0;line-height:1.5;}
.lb-cg{display:flex;flex-wrap:wrap;gap:6px;}.lb-pill{display:flex;align-items:center;gap:5px;padding:5px 11px;border:1.5px solid #ddd;border-radius:20px;font-size:12px;cursor:pointer;user-select:none;background:#fafafa;transition:all .15s;}
.lb-pill input{display:none;}.lb-pill.sel{border-color:#0073aa;background:#e8f4fb;color:#0073aa;font-weight:700;}
.lb-pv{display:none;margin-top:10px;}.lb-pv.show{display:block;}.lb-rf{padding:10px 16px;border-top:1px solid #eee;background:#fafafa;}
.btn-add{display:flex;align-items:center;gap:8px;background:#f0f6fb;border:2px dashed #0073aa;color:#0073aa;border-radius:8px;padding:12px 18px;font-size:14px;font-weight:700;cursor:pointer;width:100%;justify-content:center;margin-bottom:20px;transition:background .15s;}
.btn-add:hover{background:#e0eef8;}.btn-rm{background:none;border:1px solid #dc2626;color:#dc2626;border-radius:6px;padding:5px 12px;font-size:12px;cursor:pointer;}
.btn-rm:hover{background:#fef2f2;}.lb-bar{display:flex;align-items:center;justify-content:space-between;padding:14px 0;border-top:2px solid #eee;}
.btn-save{display:flex;align-items:center;gap:7px;background:#0073aa;color:#fff;border:none;border-radius:8px;padding:11px 22px;font-size:14px;font-weight:700;cursor:pointer;}
.btn-save:hover{background:#006291;}.lb-empty{text-align:center;padding:40px 20px;color:#888;}
';}
