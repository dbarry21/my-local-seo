<?php
/**
 * Admin Tabs Loader (safe + ordered)
 * ------------------------------------------------------------
 * - ONLY loads admin/tabs/*.php (tab files call myls_register_admin_tab()).
 * - DOES NOT define/reset the registry; inc/core.php owns it.
 * - Exposes both:
 *     * myls_get_admin_tabs()            -> numeric array (sorted)
 *     * myls_get_admin_tabs_ordered()    -> associative array keyed by id (sorted)
 * - Sorts by 'order' asc, then 'title' asc; filters by capability.
 * - Hardens against filters that drop fields (restores missing keys from raw registry).
 */

if ( ! defined('ABSPATH') ) exit;

if ( ! defined('MYLS_TABS_LOADER_READY') ) {
	define('MYLS_TABS_LOADER_READY', true);

	/** Get a single tab by id (or null). */
	if ( ! function_exists('myls_get_admin_tab') ) {
		function myls_get_admin_tab( string $id ) : ?array {
			return $GLOBALS['myls_admin_tabs'][ $id ] ?? null;
		}
	}

	/**
	 * Return tabs as a NUMERIC array, sorted & capability filtered.
	 * (Useful internally; some code prefers a list.)
	 */
	if ( ! function_exists('myls_get_admin_tabs') ) {
		function myls_get_admin_tabs() : array {
			// RAW registry (do NOT reset it here)
			$raw_tabs = $GLOBALS['myls_admin_tabs'] ?? [];

			// Allow external adjustment before sorting.
			$filtered = apply_filters( 'myls_admin_tabs', $raw_tabs );

			// ---- HARDENING: restore any missing keys from the raw registry
			// If a filter returns a "slim" config (id, title, cb...) we merge the raw tab
			// underneath so fields like 'icon', 'cap', 'order' survive.
			if ( is_array( $filtered ) ) {
				foreach ( $filtered as $id => $t ) {
					if ( isset( $raw_tabs[ $id ] ) && is_array( $raw_tabs[ $id ] ) ) {
						// Filtered values win; raw fills missing keys.
						$filtered[ $id ] = array_merge( $raw_tabs[ $id ], $t );
					}
				}
			}

			// Normalize to numeric list for sorting.
			$list = array_values( is_array( $filtered ) ? $filtered : [] );

			// Sort by order asc, then title asc (case-insensitive).
			usort( $list, function( $a, $b ) {
				$oa = isset($a['order']) ? (int)$a['order'] : 10;
				$ob = isset($b['order']) ? (int)$b['order'] : 10;
				if ( $oa === $ob ) {
					return strcasecmp( (string)($a['title'] ?? ''), (string)($b['title'] ?? '') );
				}
				return $oa <=> $ob;
			});

			// Capability filter.
			$list = array_values( array_filter( $list, function( $t ) {
				$cap = $t['cap'] ?? 'manage_options';
				return current_user_can( $cap );
			}));

			return apply_filters( 'myls_admin_tabs_sorted_list', $list );
		}
	}

	/**
	 * COMPAT: Return tabs as an ASSOCIATIVE array keyed by id, in sorted order.
	 * This is what inc/admin.php expects (foreach preserves insertion order).
	 */
	if ( ! function_exists('myls_get_admin_tabs_ordered') ) {
		function myls_get_admin_tabs_ordered() : array {
			$sorted_list = myls_get_admin_tabs(); // numeric, sorted
			$assoc = [];
			foreach ( $sorted_list as $t ) {
				if ( ! empty( $t['id'] ) ) {
					$assoc[ $t['id'] ] = $t;
				}
			}
			return apply_filters( 'myls_admin_tabs_ordered_assoc', $assoc );
		}
	}

	/** Determine current tab id (query > first), filterable default. */
	if ( ! function_exists('myls_get_current_tab_id') ) {
		function myls_get_current_tab_id() : string {
			$req = isset( $_GET['tab'] ) ? sanitize_key( (string) $_GET['tab'] ) : '';
			if ( $req !== '' ) {
				$tab = myls_get_admin_tab( $req );
				$cap = $tab['cap'] ?? 'manage_options';
				if ( $tab && current_user_can( $cap ) ) {
					return $req;
				}
			}
			$ordered = myls_get_admin_tabs_ordered();
			$default = $ordered ? array_key_first($ordered) : '';
			return (string) apply_filters( 'myls_default_tab_id', $default, $ordered );
		}
	}

	/** Render WP-style nav tabs (header). */
	if ( ! function_exists('myls_render_admin_tabs_nav') ) {
		function myls_render_admin_tabs_nav( string $page_slug ) : void {
			// Ensure tabs are discovered before rendering (idempotent).
			if ( function_exists('myls_load_all_admin_tabs') ) {
				myls_load_all_admin_tabs();
			}

			$tabs       = myls_get_admin_tabs_ordered(); // assoc, sorted
			$current_id = myls_get_current_tab_id();
			if ( empty( $tabs ) ) return;

			$wrapper_class = apply_filters( 'myls_admin_tabs_nav_classes', 'nav-tab-wrapper', $tabs, $current_id );
			printf( '<h2 class="%s" style="margin-bottom:1em;">', esc_attr( $wrapper_class ) );

			foreach ( $tabs as $id => $t ) {
				$url    = add_query_arg( ['page' => $page_slug, 'tab' => $id], admin_url( 'admin.php' ) );
				$active = ( $t['id'] === $current_id ) ? ' nav-tab-active' : '';

				$icon   = ! empty( $t['icon'] )
					? '<span class="dashicons ' . esc_attr( $t['icon'] ) . '" style="vertical-align:middle;margin-right:6px;"></span>'
					: '';

				$item = sprintf(
					'<a href="%s" class="nav-tab%s">%s%s</a>',
					esc_url( $url ),
					esc_attr( $active ),
					$icon,
					esc_html( $t['title'] )
				);

				echo apply_filters( 'myls_admin_tabs_nav_item_html', $item, $t, $current_id, $page_slug );
			}
			echo '</h2>';
		}
	}

	/** Render current tab content. */
	if ( ! function_exists('myls_render_current_admin_tab') ) {
		function myls_render_current_admin_tab() : void {
			$current_id = myls_get_current_tab_id();
			if ( $current_id === '' ) {
				echo '<div class="notice notice-warning"><p>No tabs registered.</p></div>';
				return;
			}
			$tab = myls_get_admin_tab( $current_id );
			if ( ! $tab || ! is_callable( $tab['cb'] ?? null ) ) {
				echo '<div class="notice notice-error"><p>Invalid tab callback.</p></div>';
				return;
			}
			call_user_func( $tab['cb'] );
		}
	}

	/** Convenience: render both nav + current tab. */
	if ( ! function_exists('myls_render_admin_tabs_page') ) {
		function myls_render_admin_tabs_page( string $page_slug ) : void {
			myls_render_admin_tabs_nav( $page_slug );
			echo '<div class="myls-admin-tab-content">';
			myls_render_current_admin_tab();
			echo '</div>';
		}
	}

	/** Safe recursive includer (fallback). */
	if ( ! function_exists('myls_include_dir') ) {
		function myls_include_dir( string $dir_path ) : void {
			$dir_path = wp_normalize_path( $dir_path );
			if ( ! is_dir( $dir_path ) ) return;
			$it = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator(
					$dir_path,
					FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO
				),
				RecursiveIteratorIterator::SELF_FIRST
			);
			foreach ( $it as $file ) {
				if ( $file instanceof SplFileInfo && $file->isFile() && strtolower( $file->getExtension() ) === 'php' ) {
					include_once $file->getRealPath();
				}
			}
		}
	}

	/**
	 * Loader: include all tab files from admin/tabs (admin only).
	 * Guarded so it runs only once per request. Does not reset registry.
	 */
	if ( ! function_exists('myls_load_all_admin_tabs') ) {
		function myls_load_all_admin_tabs() : void {
			static $loaded = false;
			if ( $loaded ) return;
			$loaded = true;

			if ( ! is_admin() ) return;

			$base = defined('MYLS_PATH') ? MYLS_PATH : plugin_dir_path( __FILE__ );
			$dir  = trailingslashit( $base ) . 'admin/tabs';

			if ( is_dir( $dir ) ) {
				myls_include_dir( $dir ); // each tab file calls myls_register_admin_tab()
			}
		}
	}

	/**
	 * Hook the loader early in admin.
	 * (The main plugin file may also add this hook; double-run is prevented.)
	 */
	add_action( 'admin_init', 'myls_load_all_admin_tabs', 1 );
}
