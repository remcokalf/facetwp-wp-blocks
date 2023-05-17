<?php
/*
Plugin Name: FacetWP - WP Query Loop Blocks
Description: Integrates FacetWP with WP 'Query Loop' blocks, WP 'Posts List' blocks, and WooCommerce 'Products (Beta)' blocks.
Version: 0.1
Author: FacetWP, LLC
Author URI: https://facetwp.com/
GitHub URI: facetwp/facetwp-wp-query-loop-block
*/

defined( 'ABSPATH' ) or exit;


class FacetWP_WPQLB_Integration {
	function __construct() {
		define( 'FACETWP_WPQLB_VERSION', '0.1' );
		define( 'FACETWP_WPQLB_URL', plugins_url( '', __FILE__ ) );

		add_action( 'enqueue_block_editor_assets', [ $this, 'add_query_loop_setting' ] );
		add_filter( 'query_loop_block_query_vars', [ $this, 'set_facetwp_query_args' ], 99, 3 ); // priority > 10 needed for WooCommerce core/query products block. 11 works, but 99 to be safe if WooCommerce changes its 'query_loop_block_query_vars' filter.
		add_filter( 'render_block_data', [ $this, 'prepare_post_template_block_data' ], 10, 3 );
		add_filter( 'render_block_core/post-template', [ $this, 'render_no_results' ], 10, 3 );
		add_action( 'wp_head', [ $this, 'add_block_css' ], 100 );
	}


	/**
	 * Adds custom FacetWP toggle setting to Query Loop block sidebar
	 */

	function add_query_loop_setting() {
		wp_register_script(
			'fwp_query_loop_assets', FACETWP_WPQLB_URL . '/build/index.js', [ 'wp-blocks', 'wp-dom', 'wp-dom-ready', 'wp-edit-post' ],  FACETWP_WPQLB_VERSION );
		wp_enqueue_script( 'fwp_query_loop_assets' );
	}


	/**
	 * Adds 'facetwp = true' argument to Query Loop block for query detection
	 * Runs only on 'core/query' block but - strange enough - once for each contained child block, so we limit it to running once on 'core/post-template', otherwise it runs twice when you have a 'core/no-results' block.
	 */

	function set_facetwp_query_args( $query, $block, $page ) {

		if (($classname = $block->parsed_block['attrs']['className'] ?? null) === 'facetwp-template') {

			$query['facetwp'] = true;

			// Sets paged and offset
			$prefix = FWP()->helper->get_setting( 'prefix' );
			$paged  = isset( $_GET[ $prefix . 'paged' ] ) ? (int) $_GET[ $prefix . 'paged' ] : 1;

			// For AJAX refreshes, grabs the page number from the response
			if ( ! FWP()->request->is_preload ) {
				$post_data = FWP()->request->process_post_data();
				$paged     = (int) $post_data['paged'];
			}

			$per_page = isset( $query['posts_per_page'] ) ? (int) $query['posts_per_page'] : 10;
			$offset   = ( 1 < $paged ) ? ( ( $paged - 1 ) * $per_page ) : 0;

			$GLOBALS['wp_the_query']->set( 'page', $paged );
			$GLOBALS['wp_the_query']->set( 'paged', $paged );
			$query['paged'] = $paged;
			$query['offset'] = $offset;
		}

		return $query;
	}


	/**
	 * Runs for 'core/post-template' block when parent 'core/query' block has attribute 'enableFacetWP', set with custom block setting.
	 * Sets 'facetwp-template' class to Query Loop block child 'core/post-template' block.
	 * Renames and injects 'core/query-no-results' block to be retrieved when no results are found.
	 */

	function prepare_post_template_block_data( $block, $source_block, $parent_block ) {

		if ( $block['blockName'] === 'core/post-template' ) {

			// Checks for our custom attribute set by a custom toggle in 'core/query' block
			if (($enablefacetwp = $parent_block->parsed_block['attrs']['enableFacetWP'] ?? null) === true) {

				// 1. Set 'facetwp-template' classname
				// Class is set on the <ul>. We use it also as the identifier of the loop in render_no_results().
				$block['attrs']['className'] = 'facetwp-template';

				// 2. Copy 'displayLayout' array from 'core/query' to 'core/post-template', to be used for classes later in render_no_results().
				$block['attrs']['displayLayout'] = $parent_block->parsed_block['attrs']['displayLayout'];

				// 3. Inject 'core/query-no-results' block from 'core/query' to 'core/post-template' innerBlocks, to be later used in render_no_results().
				$innerblocks = $parent_block->parsed_block['innerBlocks'];

				foreach ( $innerblocks as $innerblock ) {
					if ( $innerblock['blockName'] === 'core/query-no-results' ) {

						// Circumvent render_block_core_query_no_results() query logic:
						// render_block does not work with 'core/query-no-results' because it checks for wp_query->have_posts : https://d.pr/i/Wp698Y So it renders nothing.
						// To prevent this, we change its blockName to something custom.
						$innerblock['blockName'] = 'fwp-custom-no-results';

						// Add to 'core/post-template' innerBlocks
						array_push( $block['innerBlocks'], $innerblock );

					}
				}
			}
		}

		return $block;
	}


	/**
	 * Runs only for 'core/post-template' block because of hook name
	 * Renders 'core/query-no-results' block (or fall-back message) to Query Loop child block 'core/post-template' when no results.
	 * This is needed because the 'core/query-no-results' block does not work in FacetWP context due to its internal logic.
	 */

	function render_no_results( $block_content, $block, $instance ) {

		// Was set in prepare_post_template_block_data().
		if (($classname = $block['attrs']['className'] ?? null) === 'facetwp-template') {

			// Gets layout classnames for <ul> of parent block 'core/query'.
			// These classnames are normally not available on core/post-template, but we added it to core/post-template in prepare_post_template_block_data();
			// These are needed for the <ul> and layout to keep working when dynamically switching its content with AJAX and when there are no results and switching back to results.
			$classnames = 'wp-block-post-template facetwp-template';
			if ( isset( $block['attrs']['displayLayout'] ) ) {
				if (($type = $block['attrs']['displayLayout']['type'] ?? null) === 'flex') {
					$classnames = 'is-flex-container columns-' . $block['attrs']['displayLayout']['columns'] . ' ' . $classnames;
				}
			}

			// Equivalent to no results.
			if ( $block_content == '' ) {

				// Fall-back if no 'No results' block present.
				$block_content = '<ul class="' . $classnames . '"><li class="no-results">Nothing found.</li></ul>';

				// Get the the renamed 'core/query-no-results' block that was injected in 'core/post-template' block from 'core/query' block.
				$innerblocks = $block['innerBlocks'];

				foreach ( $innerblocks as $innerblock ) {

					if ( $innerblock['blockName'] === 'fwp-custom-no-results' ) {

						$html_output = render_block( $innerblock );

						// Render the 'fwp-custom-no-results' block content.
						// The 'no-results' class on <li> is add to set flex column to 100% with block_css() if no results.
						$block_content = '<ul class="' . $classnames . '"><li class="no-results">' . $html_output . '</li></ul>';

					}
				}
			}
		}

		return $block_content;

	}


	/**
	 * Adds CSS to overwrite default WP block CSS
	 * Add 100% width to flex columns when only one 'no-results' list item.
	 */

	function add_block_css() {
		?>
        <style>
            .wp-block-query .wp-block-post-template.facetwp-template > li.no-results {
                width: 100%;
            }
        </style>
		<?php
	}


}


new FacetWP_WPQLB_Integration();
