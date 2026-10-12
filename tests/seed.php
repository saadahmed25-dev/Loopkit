<?php
/**
 * Build the fixtures the browser suites need, and print their ids.
 *
 *   php tests/seed.php /path/to/wp-load.php
 *
 * The browser suites need real things to click: a page open in the builder,
 * a collection with every media type, a collection with a few rows. Each
 * used to seed its own, which is why several of them only passed on a clean
 * database and in a particular order.
 *
 * Prints KEY=value lines for the runner to read.
 *
 * @package Loopkit
 */

// Command line only. These load WordPress and some of them delete things —
// one of them wipes the site to prove the wipe works. Reachable over the web,
// that is a URL that destroys a site.
if ( 'cli' !== PHP_SAPI ) {
	http_response_code( 403 );
	exit( "Loopkit tests run from the command line only.\n" );
}

// Not $wp: WordPress has a global of that name, and wp-load.php runs in
// this scope — naming it $wp replaces the WP object with a string, and the
// failure surfaces much later as "add_query_var() on string".
$lk_wp_load = isset( $argv[1] ) ? $argv[1] : dirname( __DIR__, 4 ) . '/wp-load.php';
if ( ! file_exists( $lk_wp_load ) ) {
	fwrite( STDERR, "wp-load.php not found at {$lk_wp_load}\n" );
	exit( 2 );
}
require $lk_wp_load;

if ( ! class_exists( 'Loopkit_Module_CMS' ) ) {
	// Every module is off until the option says otherwise, and a wiped site
	// has no option at all.
	$modules = get_option( 'loopkit_modules', array() );
	$modules = is_array( $modules ) ? $modules : array();
	foreach ( array( 'cms', 'woocommerce', 'acf' ) as $id ) {
		$modules[ $id ] = 1;
	}
	update_option( 'loopkit_modules', $modules );
	fwrite( STDERR, "CMS module was off; switched on. Run this again.\n" );
	exit( 3 );
}

Loopkit_Module_CMS::install();

foreach ( Loopkit_Module_CMS::get_collections() as $c ) {
	Loopkit_Module_CMS::delete_collection( (int) $c->id );
}

/* A collection of plain rows, for the item editor and import suites. */
$rows_id = Loopkit_Module_CMS::create_collection( 'My Collection', 'my-collection', 'dashicons-database' );
foreach ( array(
	array( 'Title', 'title', 'text' ),
	array( 'Title 2', 'title_2', 'long_text' ),
	array( 'Order', 'ordr', 'number' ),
	array( 'Link', 'link', 'url' ),
) as $f ) {
	Loopkit_Module_CMS::create_field( $rows_id, $f[0], $f[1], $f[2], array(), 0 );
}
foreach ( array( 'one', 'two', 'three' ) as $v ) {
	Loopkit_Module_CMS::create_item( $rows_id, array( 'title' => 'Row ' . $v ) );
}

/* One of every media type, for the media suite. */
$media_id = Loopkit_Module_CMS::create_collection( 'Media Test', 'media-test', 'dashicons-database' );
foreach ( array(
	array( 'Title', 'title', 'text' ),
	array( 'Pic', 'pic', 'image' ),
	array( 'Logo', 'logo', 'svg' ),
	array( 'Clip', 'clip', 'video' ),
	array( 'Track', 'track', 'audio' ),
) as $f ) {
	Loopkit_Module_CMS::create_field( $media_id, $f[0], $f[1], $f[2], array(), 0 );
}
Loopkit_Module_CMS::create_item(
	$media_id,
	array(
		'title' => 'Row A',
		'clip'  => 'http://example.com/uploads/promo-video.mp4',
		'track' => 'http://example.com/uploads/theme-song.mp3',
		'logo'  => 'http://example.com/uploads/mark.svg',
	)
);

/* A page with a section, a container and a heading — for the handle and
   event suites, which need something real to hover and delete. */
$page = get_page_by_path( 'lk-test-page' );
$page_id = $page ? $page->ID : wp_insert_post( array(
	'post_type'   => 'page',
	'post_title'  => 'Loopkit Test Page',
	'post_name'   => 'lk-test-page',
	'post_status' => 'publish',
) );
$data = array(
	array(
		'id'       => 'sec1',
		'elType'   => 'section',
		'settings' => array(),
		'elements' => array(
			array(
				'id'       => 'con1',
				'elType'   => 'container',
				'settings' => array(),
				'elements' => array(
					array(
						'id'         => 'wid1',
						'elType'     => 'widget',
						'widgetType' => 'heading',
						'settings'   => array( 'title' => 'Add Your Heading Text Here' ),
						'elements'   => array(),
					),
				),
			),
		),
	),
);
update_post_meta( $page_id, '_loopkit_data', wp_slash( wp_json_encode( $data ) ) );
update_post_meta( $page_id, '_loopkit_edit_mode', 'builder' );

/* A collection whose fields match the import CSV below. The import suite
   used to build its own; without one, its columns matched nothing and the
   mapping step had nothing to show. */
$team_id = Loopkit_Module_CMS::create_collection( 'Team', 'team', 'dashicons-groups' );
foreach ( array(
	array( 'Name', 'name', 'text' ),
	array( 'Role', 'role', 'text' ),
	array( 'Site', 'site', 'url' ),
) as $f ) {
	Loopkit_Module_CMS::create_field( $team_id, $f[0], $f[1], $f[2], array(), 0 );
}
Loopkit_Module_CMS::create_item( $team_id, array( 'name' => 'Ayesha Khan', 'role' => 'Designer' ) );
Loopkit_Module_CMS::create_item( $team_id, array( 'name' => 'Bilal, Jr.', 'role' => 'Engineer "Lead"' ) );

/* A CSV for the import suite. */
$csv = sys_get_temp_dir() . '/lk-import.csv';
file_put_contents(
	$csv,
	"Name,Role,Site\nSana Malik,Writer,https://sana.example\n"
	. "Hina Raza,Manager,https://hina.example\nOmar Farooq,Developer,https://omar.example\n"
);

/* The shop module too — the quick-view suite needs it, and the uninstall
   suite switches every module off. */
$lk_mods = get_option( 'loopkit_modules', array() );
$lk_mods = is_array( $lk_mods ) ? $lk_mods : array();
foreach ( array( 'cms', 'woocommerce', 'acf' ) as $lk_id ) {
	$lk_mods[ $lk_id ] = 1;
}
update_option( 'loopkit_modules', $lk_mods );

/* The mascot and its switch both default on. */
delete_option( 'loopkit_mascot' );
delete_option( 'loopkit_mascot_on_at' );

/* The uninstall suite turns "remove all data" on and leaves it there, so a
   later run found it already on and the switch suite failed on "off by
   default". Normalise it here rather than asking each suite to tidy up. */
$lk_adv = get_option( 'loopkit_advanced_settings', array() );
if ( is_array( $lk_adv ) ) {
	$lk_adv['remove_data_on_uninstall'] = '';
	update_option( 'loopkit_advanced_settings', $lk_adv );
}

echo "ROWS_COLLECTION={$rows_id}\n";
echo "TEAM_COLLECTION={$team_id}\n";
echo "MEDIA_COLLECTION={$media_id}\n";
echo "PAGE_ID={$page_id}\n";
echo "IMPORT_CSV={$csv}\n";
