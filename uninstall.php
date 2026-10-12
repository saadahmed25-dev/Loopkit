<?php
/**
 * What happens when someone deletes Loopkit.
 *
 * WordPress runs this only on Delete, never on Deactivate, so a site can be
 * switched off and back on without losing anything.
 *
 * Deleting is not the default: a site is only wiped if "Remove all data on
 * uninstall" was switched on in Loopkit → Settings → Advanced beforehand.
 * Anything else would mean a mis-click destroys every page someone built,
 * with no undo and no warning.
 *
 * @package Loopkit
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Everything Loopkit creates, in one place.
 *
 * Kept as a plain function so the Tools screen can call the same code rather
 * than keeping its own list that drifts out of step with this one.
 */
function loopkit_uninstall_remove_all() {
	global $wpdb;

	/* ---- Posts: templates, products, orders, field groups ---------- */

	$post_types = array(
		'loopkit_template',
		'loopkit_product',
		'loopkit_order',
		'loopkit_field_group',
	);
	foreach ( $post_types as $type ) {
		$ids = $wpdb->get_col(
			$wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s", $type )
		);
		foreach ( $ids as $id ) {
			wp_delete_post( (int) $id, true );
		}
	}

	/* ---- Meta left on ordinary posts and pages --------------------- */

	// Every page built with Loopkit carries these. The post itself stays —
	// it is the person's content, not ours — but our data comes off it.
	$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '\_loopkit\_%'" );
	$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '\_lk\_%'" );

	/* ---- User meta -------------------------------------------------- */

	$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE '\_loopkit\_%'" );

	/* ---- Our own tables --------------------------------------------- */

	$tables = array(
		$wpdb->prefix . 'lk_cms_items',
		$wpdb->prefix . 'lk_cms_fields',
		$wpdb->prefix . 'lk_cms_collections',
		$wpdb->prefix . 'loopkit_submissions',
	);
	foreach ( $tables as $table ) {
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/* ---- Options ----------------------------------------------------- */

	$options = array(
		'loopkit_general_settings',
		'loopkit_advanced_settings',
		'loopkit_site_settings',
		'loopkit_maintenance_settings',
		'loopkit_global_css',
		'loopkit_global_css_time',
		'loopkit_field_options',
		'loopkit_flush_rewrite',
		'loopkit_templates_migrated',
		'loopkit_theme_builder_cache',
		'loopkit_shop_coupons',
		'loopkit_submissions_db',
		'loopkit_cms_db',
		'loopkit_cms_item_templates',
		'loopkit_cms_flush_rewrites',
		'loopkit_mascot',
		'loopkit_mascot_on_at',
	);
	foreach ( $options as $option ) {
		delete_option( $option );
		delete_site_option( $option );
	}

	// Anything added later that follows the naming, so a new option does not
	// quietly survive an uninstall because nobody updated the list above.
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'loopkit\_%'" );

	/* ---- Transients --------------------------------------------------- */

	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_loopkit\_%'" );
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_timeout\_loopkit\_%'" );

	/* ---- Generated files ---------------------------------------------- */

	$uploads = wp_upload_dir();
	if ( empty( $uploads['error'] ) && ! empty( $uploads['basedir'] ) ) {
		foreach ( array( 'loopkit', 'loopkit-import' ) as $folder ) {
			loopkit_uninstall_rmdir( trailingslashit( $uploads['basedir'] ) . $folder );
		}
	}

	/* ---- The rules that pointed at collection rows ---------------------- */

	delete_option( 'rewrite_rules' );
}

/**
 * Delete a folder and what is in it.
 *
 * Guarded against following a symlink out of the uploads folder: this runs
 * as a delete with no confirmation, and a symlinked cache directory is not
 * a rare thing on a managed host.
 *
 * @param string $dir Absolute path.
 */
function loopkit_uninstall_rmdir( $dir ) {
	if ( ! is_dir( $dir ) || is_link( $dir ) ) {
		return;
	}
	$items = scandir( $dir );
	if ( false === $items ) {
		return;
	}
	foreach ( $items as $item ) {
		if ( '.' === $item || '..' === $item ) {
			continue;
		}
		$path = $dir . '/' . $item;
		if ( is_link( $path ) ) {
			unlink( $path );
		} elseif ( is_dir( $path ) ) {
			loopkit_uninstall_rmdir( $path );
		} else {
			unlink( $path );
		}
	}
	rmdir( $dir );
}

/* ---------------------------------------------------------------------- */

$loopkit_advanced = get_option( 'loopkit_advanced_settings', array() );
$loopkit_wipe     = is_array( $loopkit_advanced ) && ! empty( $loopkit_advanced['remove_data_on_uninstall'] );

if ( $loopkit_wipe ) {
	if ( is_multisite() ) {
		// Each site keeps its own pages, templates and collections.
		$sites = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );
		foreach ( $sites as $site_id ) {
			switch_to_blog( (int) $site_id );
			loopkit_uninstall_remove_all();
			restore_current_blog();
		}
	} else {
		loopkit_uninstall_remove_all();
	}
}
