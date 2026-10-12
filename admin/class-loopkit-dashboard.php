<?php
/**
 * Loopkit Dashboard.
 *
 * One screen gathering everything the plugin offers, with every number read
 * from the database at render time. A count that would always be zero on a
 * fresh install is shown as an empty state that says what to do instead of a
 * zero in a card that looks like it is telling you something.
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

class Loopkit_Dashboard {

/** Template types given their own card. */
const CARD_TYPES = array( 'header', 'footer', 'single', 'archive' );

/**
 * Menu slug.
 *
 * The dashboard IS the top-level Loopkit screen, so its slug is 'loopkit' -- the
 * same slug the builder's own "back to dashboard" link already used. Registering it
 * under a separate slug would leave every link pointing at a page with no menu
 * entry behind it.
 */
const SLUG = 'loopkit';

/** Templates per type, read from the database. */
public static function template_counts() {
		$out = array();
		foreach ( self::all_template_types() as $type => $info ) {
			$out[ $type ] = $info['count'];
		}
		$ids          = get_posts( array(
			'post_type'      => 'loopkit_template',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'post_status'    => 'any',
		) );
		$out['total'] = count( $ids );
		return $out;
	}

	/**
	 * Every template type actually present, with its count and a readable label.
	 *
	 * Derived from the database rather than a fixed list, so a type the site has --
	 * loop-item, popup, section, global, or one a future release adds -- is always
	 * shown and the per-type rows always add up to the total.
	 *
	 * @return array type => array( label, count )
	 */
	public static function all_template_types() {
		$ids = get_posts( array(
			'post_type'      => 'loopkit_template',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'post_status'    => 'any',
		) );

		$counts = array();
		foreach ( $ids as $id ) {
			$type = get_post_meta( $id, '_loopkit_template_type', true );
			$type = ( is_string( $type ) && '' !== $type ) ? $type : 'untitled';
			$counts[ $type ] = isset( $counts[ $type ] ) ? $counts[ $type ] + 1 : 1;
		}

		// Theme-builder locations always appear, even at zero, so a location not yet
		// made is visible rather than inferred from a missing row.
		foreach ( self::CARD_TYPES as $type ) {
			if ( ! isset( $counts[ $type ] ) ) {
				$counts[ $type ] = 0;
			}
		}

		$labels = array(
			'header'    => __( 'Header', 'loopkit' ),
			'footer'    => __( 'Footer', 'loopkit' ),
			'single'    => __( 'Single', 'loopkit' ),
			'cms-item'  => __( 'Collection Item', 'loopkit' ),
			'archive'   => __( 'Archive', 'loopkit' ),
			'loop-item' => __( 'Loop item', 'loopkit' ),
			'popup'     => __( 'Popup', 'loopkit' ),
			'section'   => __( 'Section', 'loopkit' ),
			'global'    => __( 'Global', 'loopkit' ),
			'column'    => __( 'Column', 'loopkit' ),
			'untitled'  => __( 'Unfinished', 'loopkit' ),
		);

		$out = array();
		foreach ( $counts as $type => $count ) {
			$out[ $type ] = array(
				'label' => isset( $labels[ $type ] ) ? $labels[ $type ] : ucfirst( str_replace( '-', ' ', $type ) ),
				'count' => $count,
			);
		}

		// uasort() hands the callback only the two values, never the keys, so this
		// sorts a list that carries its own key and rank.
		$order = array_flip( self::CARD_TYPES );
		$rows  = array();
		foreach ( $out as $type => $info ) {
			$rows[] = array(
				'type' => $type,
				'info' => $info,
				'rank' => isset( $order[ $type ] ) ? $order[ $type ] : 99,
			);
		}
		usort( $rows, function ( $x, $y ) {
			if ( $x['rank'] === $y['rank'] ) {
				return strcmp( $x['type'], $y['type'] );
			}
			return ( $x['rank'] < $y['rank'] ) ? -1 : 1;
		} );

		$sorted = array();
		foreach ( $rows as $row ) {
			$sorted[ $row['type'] ] = $row['info'];
		}

		return $sorted;
	}

	public static function pages_built() {
$ids = get_posts( array(
'post_type'      => array( 'page', 'post' ),
'posts_per_page' => -1,
'fields'         => 'ids',
'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
'meta_query'     => array( array( 'key' => '_loopkit_edit_mode', 'value' => 'builder' ) ),
) );
return count( $ids );
}

/** Recently edited pages built with Loopkit. */
public static function recent_pages() {
return get_posts( array(
'post_type'      => array( 'page', 'post' ),
'posts_per_page' => 6,
'meta_key'       => '_loopkit_edit_mode',
'meta_value'     => 'builder',
'orderby'        => 'modified',
'order'          => 'DESC',
'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
) );
}

/** Total form submissions. */
public static function submission_count() {
if ( ! class_exists( 'Loopkit_Submissions' ) ) {
return 0;
}
return (int) Loopkit_Submissions::count_submissions();
}

/** How many automation rules are configured. */
public static function automation_count() {
if ( ! class_exists( 'Loopkit_Automations' ) ) {
return 0;
}
$rules = Loopkit_Automations::get_rules();
return is_array( $rules ) ? count( $rules ) : 0;
}

/** Post count for a Loopkit shop post type. */
public static function shop_count( $post_type ) {
$ids = get_posts( array(
'post_type'      => $post_type,
'posts_per_page' => -1,
'fields'         => 'ids',
'post_status'    => 'any',
) );
return count( $ids );
}

/** Every module with its on/off state. */
public static function module_cards() {
$states = Loopkit_Modules::get_states();
$out    = array();
foreach ( Loopkit_Modules::get_modules() as $id => $mod ) {
$out[ $id ] = array(
'name'        => $mod['name'],
'short'       => $mod['short'],
'description' => $mod['description'],
'icon'        => $mod['icon'],
'on'          => ! empty( $states[ $id ] ),
);
}
return $out;
}

/** Widget count from the registry. */
public static function widget_count() {
return count( Loopkit_Widgets_Registry::instance()->get_registered_widgets() );
}

/** Admin URL for a Loopkit screen. */
private static function url( $slug ) {
return admin_url( 'admin.php?page=' . $slug );
}

/** Render the dashboard. */
public static function render() {
$tpl     = self::template_counts();
$built   = self::pages_built();
$subs    = self::submission_count();
$autos   = self::automation_count();
$modules = self::module_cards();
$shop_on = ! empty( $modules['woocommerce']['on'] );
$products = $shop_on ? self::shop_count( 'lk_product' ) : 0;
$orders   = $shop_on ? self::shop_count( 'lk_order' ) : 0;
$widgets  = self::widget_count();
?>
<div class="wrap lk-dash-wrap">

<header class="lk-dash-hero">
<div class="lk-dash-hero-inner">
<span class="dashicons dashicons-dashboard lk-dash-hero-icon"></span>
<div class="lk-dash-hero-text">
<h1><?php esc_html_e( 'Dashboard', 'loopkit' ); ?></h1>
<p class="lk-dash-hero-sub"><?php esc_html_e( 'Everything Loopkit is doing on this site, in one place.', 'loopkit' ); ?></p>
</div>
<div class="lk-dash-hero-actions">
<a class="lk-btn lk-btn-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=page' ) ); ?>">
<span class="dashicons dashicons-plus-alt2"></span><?php esc_html_e( 'New Page', 'loopkit' ); ?>
</a>
<a class="lk-btn lk-btn-ghost" href="<?php echo esc_url( self::url( 'loopkit-templates' ) ); ?>"><span class="dashicons dashicons-plus-alt2"></span><?php esc_html_e( 'Add Template', 'loopkit' ); ?></a>
</div>
</div>
</header>

<div class="lk-dash-stats">
<?php
$stats = array(
array( 'label' => __( 'Pages built with Loopkit', 'loopkit' ), 'value' => $built,      'icon' => 'dashicons-layout' ),
array( 'label' => __( 'Templates', 'loopkit' ),              'value' => $tpl['total'], 'icon' => 'dashicons-layout' ),
array( 'label' => __( 'Submissions', 'loopkit' ),           'value' => $subs,        'icon' => 'dashicons-email-alt' ),
array( 'label' => __( 'Automations', 'loopkit' ),            'value' => $autos,       'icon' => 'dashicons-admin-links' ),
array( 'label' => __( 'Widgets available', 'loopkit' ),     'value' => $widgets,     'icon' => 'dashicons-screenoptions' ),
);
foreach ( $stats as $i => $s ) :
?>
<div class="lk-dash-stat" style="--i:<?php echo esc_attr( $i ); ?>">
<span class="dashicons <?php echo esc_attr( $s['icon'] ); ?> lk-dash-stat-icon"></span>
<div class="lk-dash-stat-body">
<span class="lk-dash-stat-value" data-count="<?php echo esc_attr( $s['value'] ); ?>"><?php echo esc_html( $s['value'] ); ?></span>
<span class="lk-dash-stat-label"><?php echo esc_html( $s['label'] ); ?></span>
</div>
</div>
<?php endforeach; ?>
</div>

<div class="lk-dash-grid">

<section class="lk-dash-card" style="--i:0">
<h2 class="lk-dash-card-title"><?php esc_html_e( 'Theme templates', 'loopkit' ); ?></h2>
<ul class="lk-dash-list">
<?php foreach ( self::all_template_types() as $tkey => $tinfo ) :
  $count = $tinfo['count'];
  ?>
  <li>
    <span class="lk-dash-list-label"><?php echo esc_html( $tinfo['label'] ); ?></span>
    <?php if ( $count > 0 ) : ?>
      <span class="lk-dash-badge" data-count="<?php echo esc_attr( $count ); ?>"><?php echo esc_html( $count ); ?></span>
    <?php else : ?>
      <span class="lk-dash-empty"><?php esc_html_e( 'None yet', 'loopkit' ); ?></span>
    <?php endif; ?>
  </li>
<?php endforeach; ?>
</ul>
<a class="lk-dash-card-link" href="<?php echo esc_url( self::url( 'loopkit-templates' ) ); ?>">
<?php esc_html_e( 'Manage templates', 'loopkit' ); ?><span class="dashicons dashicons-arrow-right-alt"></span>
</a>
</section>

<section class="lk-dash-card" style="--i:1">
<h2 class="lk-dash-card-title"><?php esc_html_e( 'Modules', 'loopkit' ); ?></h2>
<ul class="lk-dash-list">
<?php foreach ( $modules as $id => $mod ) : ?>
<li>
<span class="lk-dash-list-label"><span class="dashicons <?php echo esc_attr( $mod['icon'] ); ?>"></span><?php echo esc_html( $mod['name'] ); ?></span>
<?php if ( $mod['on'] ) : ?>
<span class="lk-dash-badge lk-dash-badge-on"><?php esc_html_e( 'On', 'loopkit' ); ?></span>
<?php else : ?>
<span class="lk-dash-empty"><?php esc_html_e( 'Off', 'loopkit' ); ?></span>
<?php endif; ?>
</li>
<?php endforeach; ?>
</ul>
<a class="lk-dash-card-link" href="<?php echo esc_url( self::url( 'loopkit-tools' ) ); ?>">
<?php esc_html_e( 'Turn modules on or off', 'loopkit' ); ?><span class="dashicons dashicons-arrow-right-alt"></span>
</a>
</section>

<section class="lk-dash-card lk-dash-card-wide" style="--i:2">
<h2 class="lk-dash-card-title"><?php esc_html_e( 'Recently built', 'loopkit' ); ?></h2>
<?php $recent = self::recent_pages(); ?>
<?php if ( $recent ) : ?>
<table class="lk-dash-table">
  <thead><tr>
    <th><?php esc_html_e( 'Page', 'loopkit' ); ?></th>
    <th><?php esc_html_e( 'Type', 'loopkit' ); ?></th>
    <th><?php esc_html_e( 'Status', 'loopkit' ); ?></th>
    <th><?php esc_html_e( 'Last modified', 'loopkit' ); ?></th>
    <th></th>
  </tr></thead>
  <tbody>
<?php foreach ( $recent as $r ) : ?>
  <tr data-post-id="<?php echo esc_attr( $r->ID ); ?>">
    <td><?php echo esc_html( get_the_title( $r ) ? get_the_title( $r ) : __( '(no title)', 'loopkit' ) ); ?></td>
    <td><span class="lk-dash-tag"><?php echo esc_html( $r->post_type ); ?></span></td>
    <td><span class="lk-dash-tag lk-dash-tag-<?php echo esc_attr( $r->post_status ); ?>"><?php echo esc_html( $r->post_status ); ?></span></td>
    <td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $r->post_modified ) ); ?></td>
    <td><a class="lk-dash-link" href="<?php echo esc_url( get_edit_post_link( $r->ID ) ); ?>"><?php esc_html_e( 'Edit', 'loopkit' ); ?></a></td>
  </tr>
<?php endforeach; ?>
  </tbody>
</table>
</ul>
<?php else : ?>
<p class="lk-dash-empty-state"><?php esc_html_e( 'No pages use Loopkit yet. Open any page and choose Loopkit in the Page tab to build it visually.', 'loopkit' ); ?></p>
<?php endif; ?>
</section>

<section class="lk-dash-card" style="--i:3">
<h2 class="lk-dash-card-title"><?php esc_html_e( 'Submissions', 'loopkit' ); ?></h2>
<?php if ( $subs > 0 ) : ?>
<span class="lk-dash-stat-value lk-dash-big" data-count="<?php echo esc_attr( $subs ); ?>"><?php echo esc_html( $subs ); ?></span>
<p class="lk-dash-muted"><?php esc_html_e( 'form submissions collected', 'loopkit' ); ?></p>
<?php else : ?>
<p class="lk-dash-empty-state"><?php esc_html_e( 'No submissions yet. Add a Form widget to any page and submissions will appear here.', 'loopkit' ); ?></p>
<?php endif; ?>
</section>

<section class="lk-dash-card" style="--i:4">
<h2 class="lk-dash-card-title"><?php esc_html_e( 'Automations', 'loopkit' ); ?></h2>
<?php if ( $autos > 0 ) : ?>
<span class="lk-dash-stat-value lk-dash-big" data-count="<?php echo esc_attr( $autos ); ?>"><?php echo esc_html( $autos ); ?></span>
<p class="lk-dash-muted"><?php esc_html_e( 'rules running on new content', 'loopkit' ); ?></p>
<?php else : ?>
<p class="lk-dash-empty-state"><?php esc_html_e( 'No automations set up. Automations send email or fire actions when a submission or order arrives.', 'loopkit' ); ?></p>
<?php endif; ?>
</section>

<section class="lk-dash-card" style="--i:5">
<h2 class="lk-dash-card-title"><?php esc_html_e( 'Shop', 'loopkit' ); ?></h2>
<?php if ( ! $shop_on ) : ?>
<p class="lk-dash-empty-state"><?php esc_html_e( 'The Shop module is off. Turn it on under Tools to add products, cart and checkout.', 'loopkit' ); ?></p>
<?php else : ?>
<ul class="lk-dash-list">
<li><span class="lk-dash-list-label"><?php esc_html_e( 'Products', 'loopkit' ); ?></span>
<?php if ( $products > 0 ) : ?>
<span class="lk-dash-badge" data-count="<?php echo esc_attr( $products ); ?>"><?php echo esc_html( $products ); ?></span>
<?php else : ?><span class="lk-dash-empty"><?php esc_html_e( 'None yet', 'loopkit' ); ?></span><?php endif; ?>
</li>
<li><span class="lk-dash-list-label"><?php esc_html_e( 'Orders', 'loopkit' ); ?></span>
<?php if ( $orders > 0 ) : ?>
<span class="lk-dash-badge" data-count="<?php echo esc_attr( $orders ); ?>"><?php echo esc_html( $orders ); ?></span>
<?php else : ?><span class="lk-dash-empty"><?php esc_html_e( 'None yet', 'loopkit' ); ?></span><?php endif; ?>
</li>
</ul>
<a class="lk-dash-card-link" href="<?php echo esc_url( self::url( 'loopkit-shop' ) ); ?>"><?php esc_html_e( 'Open shop', 'loopkit' ); ?><span class="dashicons dashicons-arrow-right-alt"></span></a>
<?php endif; ?>
</section>

</div>

<section class="lk-dash-card lk-dash-card-wide">
<h2 class="lk-dash-card-title"><?php esc_html_e( 'Go to', 'loopkit' ); ?></h2>
<div class="lk-dash-links">
<?php
$links = array(
array( 'url' => self::url( Loopkit_Dashboard::SLUG ),       'label' => __( 'Dashboard', 'loopkit' ),     'icon' => 'dashicons-dashboard' ),
array( 'url' => self::url( 'loopkit-templates' ),      'label' => __( 'Templates', 'loopkit' ),     'icon' => 'dashicons-layout' ),
array( 'url' => self::url( 'loopkit-settings' ),       'label' => __( 'Settings', 'loopkit' ),      'icon' => 'dashicons-admin-generic' ),
array( 'url' => self::url( 'loopkit-site-settings' ), 'label' => __( 'Site Settings', 'loopkit' ), 'icon' => 'dashicons-admin-site' ),
array( 'url' => self::url( 'loopkit-tools' ),          'label' => __( 'Tools', 'loopkit' ),         'icon' => 'dashicons-admin-tools' ),
);
if ( $shop_on ) {
$links[] = array( 'url' => self::url( 'loopkit-shop' ), 'label' => __( 'Shop', 'loopkit' ), 'icon' => 'dashicons-cart' );
}
foreach ( $links as $l ) :
?>
<a class="lk-dash-link-tile" href="<?php echo esc_url( $l['url'] ); ?>">
<span class="dashicons <?php echo esc_attr( $l['icon'] ); ?>"></span><?php echo esc_html( $l['label'] ); ?>
</a>
<?php endforeach; ?>
</div>
</section>

<footer class="lk-dash-foot">
<span class="lk-dash-version" data-version="<?php echo esc_attr( LOOPKIT_VERSION ); ?>">
<?php
/* translators: %s: plugin version */
printf( esc_html__( 'Loopkit %s', 'loopkit' ), esc_html( LOOPKIT_VERSION ) );
?>
</span>
<a href="<?php echo esc_url( self::url( 'loopkit-system-info' ) ); ?>"><?php esc_html_e( 'System Info', 'loopkit' ); ?></a>
</footer>

</div>
<?php
}
}
