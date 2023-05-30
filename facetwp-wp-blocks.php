<?php
/*
Plugin Name: FacetWP - WP Blocks
Description: Integrates FacetWP with WordPress' 'Query Loop', 'Posts List' and 'Latest Posts' core blocks, WooCommerce 'Products (Beta)' block, GenerateBlocks Query Loop block, and Kadence Blocks Posts block.
Version: 0.2
Author: FacetWP, LLC
Author URI: https://facetwp.com/
GitHub URI: facetwp/facetwp-wp-blocks
*/

defined( 'ABSPATH' ) or exit;


class FacetWP_WP_Blocks_Integration {

  function __construct() {
    define( 'FACETWP_WPBLOCKS_VERSION', '0.2' );
    define( 'FACETWP_WPBLOCKS_URL', plugins_url( '', __FILE__ ) );

    add_action( 'enqueue_block_editor_assets', [ $this, 'add_facetwp_block_setting' ] );
    add_filter( 'facetwp_assets', array( $this, 'add_front_assets' ) );

    add_filter( 'query_loop_block_query_vars', [ $this, 'query_loop_set_facetwp_query_args' ], 100, 3 ); // priority > 10 needed for WooCommerce core/query products block. 11 works, but 100 to be safe if WooCommerce changes its 'query_loop_block_query_vars' filter.
    add_filter( 'block_type_metadata_settings', [ $this, 'latest_posts_set_facetwp_query_args' ], 10, 2 );

    add_filter( 'render_block_data', [ $this, 'prepare_block_data' ], 10, 3 );
    
    add_filter( 'render_block_core/post-template', [ $this, 'query_loop_render_no_results' ], 10, 3 );
    add_filter( 'render_block_core/latest-posts', [ $this, 'latest_posts_render_no_results' ], 10, 3 );
    add_filter( 'render_block_generateblocks/grid', [ $this, 'generateblocks_render_no_results' ], 10, 3 );
    add_filter( 'render_block_kadence/posts', [ $this, 'kadence_posts_render' ], 10, 3 );
  }


  /**
   * Adds custom FacetWP toggle setting to block sidebar
   */

  function add_facetwp_block_setting() {
    wp_register_script( 'fwp_block_assets', FACETWP_WPBLOCKS_URL . '/build/index.js', [ 'wp-blocks', 'wp-dom', 'wp-dom-ready', 'wp-edit-post' ], FACETWP_WPBLOCKS_VERSION );
    wp_enqueue_script( 'fwp_block_assets' );
  }


  /**
   * Adds front CSSassets to overwrite default WP block CSS
   * Adds 100% width to block flex columns when only one 'facetwp-no-results' list item.
   */

  function add_front_assets( $assets ) {
    $assets['facetwp-wp-blocks.css'] = [ FACETWP_WPBLOCKS_URL . '/assets/css/front.css', FACETWP_WPBLOCKS_VERSION ];
    return $assets;
  }


  /**
   * Returns query arguments needed for FacetWP
   * Adds 'facetwp = true' argument to Query Loop block for query detection
   * Adds pagination arguments to AJAX refreshes
   */

  function add_facetwp_query_args($query) {

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

    return $query;
  }


  /**
   * Adds 'facetwp = true' argument to Query Loop block for query detection
   * Adds pagination to AJAX refreshes
   * Runs only on 'core/query' block but - strange enough - once for each contained child block, so we limit it to running once on 'core/post-template', otherwise it runs twice when you have a 'core/no-results' block.
   */

  function query_loop_set_facetwp_query_args( $query, $block, $page ) {

    if ( ( $classname = $block->parsed_block['attrs']['className'] ?? null ) === 'facetwp-template' ) {
      return $this->add_facetwp_query_args($query);
    }

    return $query;
  }


  /**
   * Adds 'facetwp = true' argument to 'core/latest-post' block for query detection
   * There is no hook for the query in 'core/latest-post block'
   * The argument is added to each 'core/latest-post' block, there is no way to know which one has FacetWP enabled, but without the 'facetwp-template' class it can do no harm.
   * See: https://wordpress.stackexchange.com/a/405596
   */

  /**
   * Part 1: Change render callback
   */

  function latest_posts_set_facetwp_query_args( $settings, $metadata ) {

    if ( $metadata['name'] !== 'core/latest-posts' ) {
      return $settings;
    }
    if ( $settings['render_callback'] !== 'render_block_core_latest_posts' ) {
      return $settings;
    }

    $settings['render_callback'] = array( $this, 'latest_posts_render_block_core' );

    return $settings;
  }

  /**
   * Part 2: Custom render callback with pre_get_posts() to insert 'facetwp = true' argument
   */

  function latest_posts_render_block_core( $attributes, $content, $block ) {

    $callback = fn( $query ) => $query->set( 'facetwp', true );

    add_action( 'pre_get_posts', $callback, 10 );
    $output = render_block_core_latest_posts( $attributes, $content, $block );
    remove_action( 'pre_get_posts', $callback, 10 );

    return $output;

  }


  /**
   * Prepares blocks for rendering:
   * Adds 'facetwp-template' class to supported blocks if 'enableFacetWP' isset with custom block setting.
   * For Query Loop blocks: retains layout classes, and renames and injects 'core/query-no-results' block to be retrieved when no results are found.
   */

  function prepare_block_data( $block, $source_block, $parent_block ) {

    // Runs for 'core/post-template' block when parent 'core/query' block has attribute 'enableFacetWP' set with custom block setting.
    // Sets 'facetwp-template' class to Query Loop block child 'core/post-template' block.
    // Renames and injects 'core/query-no-results' block to be retrieved when no results are found.
    if ( $block['blockName'] === 'core/post-template' ) {

      // Checks for our custom attribute set by a custom toggle in 'core/query' block
      if ( ( $enablefacetwp = $parent_block->parsed_block['attrs']['enableFacetWP'] ?? null ) === true ) {

        // 1. Set 'facetwp-template' classname
        // Class is set on the <ul>. We use it also as the identifier of the loop in render_no_results().
        $block['attrs']['className'] = 'facetwp-template';

        // 2. Copy 'displayLayout' array from 'core/query' to 'core/post-template', to be used for classes later in render_no_results().
        if ( isset( $parent_block->parsed_block['attrs']['displayLayout'] ) ) {
          $block['attrs']['displayLayout'] = $parent_block->parsed_block['attrs']['displayLayout'];
        }

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


    // Runs for 'core/latest-posts' block when this block has attribute 'enableFacetWP' set with custom block setting.
    // Sets 'facetwp-template' class to the block.
    if ( $block['blockName'] === 'core/latest-posts' ) {

      // Checks for our custom attribute set by a custom toggle in 'core/latest-posts' block
      if ( ( $enablefacetwp = $block['attrs']['enableFacetWP'] ?? null ) === true ) {

        // Class is set on the <ul>. We use it also as the identifier of the loop in render_no_results().
        $block['attrs']['className'] = 'facetwp-template';

      }

    }


    // Runs for 'generateblocks/grid' block when parent 'generateblocks/query-loop' block has attribute 'enableFacetWP' set with custom block setting.
    if ( $block['blockName'] === 'generateblocks/grid' && ( ($enablefacetwp = $parent_block->parsed_block['attrs']['enableFacetWP'] ?? null ) === true ) ) {
        $block['attrs']['className'] = 'facetwp-template';
        add_filter( 'generateblocks_query_loop_args', [ $this, 'add_facetwp_query_args'], 10, 2 );
    }


    // Runs for 'kadence/posts' block when this block has attribute 'enableFacetWP' set with custom block setting.
    // Template class is added to a new container. Class on block itself does not work with no results because of ob_start() used for this block
    if ( $block['blockName'] === 'kadence/posts' && ( ( $enablefacetwp = $block['attrs']['enableFacetWP'] ?? null ) === true ) ) {
      add_filter( 'kadence_blocks_posts_query_args', [ $this, 'add_facetwp_query_args'], 10, 2 );
    }

    return $block;
  }


  /**
   * Runs only for 'core/post-template' block because of hook name.
   * Renders 'core/query-no-results' block (or fall-back message) to Query Loop child block 'core/post-template' when no results.
   * This is needed because the 'core/query-no-results' block does not work in FacetWP context due to its internal logic.
   */

  function query_loop_render_no_results( $block_content, $block, $instance ) {

    // Was set in prepare_block_data().
    if ( ( $classname = $block['attrs']['className'] ?? null ) === 'facetwp-template' ) {

      // Preserves classnames for <ul> of parent block 'core/query'.
      // These classnames are normally not available on core/post-template, but we added it to core/post-template in prepare_post_template_block_data();
      // These are needed for the <ul> and layout to keep working when dynamically switching its content with AJAX and when there are no results and switching back to results.
      $classnames = 'wp-block-post-template facetwp-template';
      if ( isset( $block['attrs']['displayLayout'] ) ) {
        if ( ( $type = $block['attrs']['displayLayout']['type'] ?? null ) === 'flex' ) {
          $classnames = 'is-flex-container columns-' . $block['attrs']['displayLayout']['columns'] . ' ' . $classnames;
        }
      }

      // Equivalent to no results.
      if ( $block_content == '' ) {

        // Fall-back if no 'No results' block present.
        $no_results_html =  apply_filters( 'facetwp_wpblocks_query_loop_no_results', facetwp_i18n( __( 'Nothing found.', 'fwp-front' ) ) );
        $block_content = '<ul class="' . $classnames . '"><li class="facetwp-no-results">' . $no_results_html . '</li></ul>';

        // Get the the renamed 'core/query-no-results' block that was injected in 'core/post-template' block from 'core/query' block.
        $innerblocks = $block['innerBlocks'];

        foreach ( $innerblocks as $innerblock ) {

          if ( $innerblock['blockName'] === 'fwp-custom-no-results' ) {

            $html_output = render_block( $innerblock );

            // Render the 'fwp-custom-no-results' block content.
            // The 'no-results' class on <li> is add to set flex column to 100% with block_css() if no results.
            $block_content = '<ul class="' . $classnames . '"><li class="facetwp-no-results">' . $html_output . '</li></ul>';

          }
        }
      }
    }

    return $block_content;

  }

  /**
   * Runs only for 'core/latest-posts' block because of hook name.
   * Adds no results message when Search facet returns no results.
   */

  function latest_posts_render_no_results( $block_content, $block, $instance ) {

    // Was set in prepare_block_data().
    if ( ( $classname = $block['attrs']['className'] ?? null ) === 'facetwp-template' ) {

      // To detect the empty posts ul seems to be the only way to detect 0 results.
      // Tried other approaches like 'loop_no_results' hook etc.
      // Alternative is same with DOMdocument() instead of regex, but would load whole results block each time.

      if ( strpos( $block_content, '<ul' ) !== false ) { // Check if $html contains <ul>

        $no_results_html =  apply_filters( 'facetwp_wpblocks_latest_posts_no_results', facetwp_i18n( __( 'Nothing found.', 'fwp-front' ) ) );

        $li_exists = strpos( $block_content, '<li' ) !== false; // Check if any <li> is present in $block_content
        $li = $li_exists ? '' : '<li class="facetwp-no-results">' . $no_results_html . '</li>'; // If no <li>, create new <li>
        preg_match( '/<ul.*?>/', $block_content, $matches ); // Match the <ul> element and its attributes
        $insert_before = $li_exists ? $matches[0] : '</ul>'; // Determine where to insert the new <li> element
        $block_content = str_replace( $insert_before, $li . $insert_before, $block_content ); // Insert the new <li> element

      }
    }

    return $block_content;
  }


  /**
   * Runs only for 'generateblocks/grid' block because of hook name.
   * Adds no results message when Search facet returns no results.
   */

  function generateblocks_render_no_results( $block_content, $block, $instance ) {

    // Was set in prepare_block_data().
    if ( ( $classname = $block['attrs']['className'] ?? null ) === 'facetwp-template' ) {

      // Preserves classnames for block 'generateblocks/grid' CSS.
      $classnames = 'gb-grid-wrapper facetwp-template gb-query-loop-wrapper facetwp-no-results';
      if ( isset( $block['attrs']['uniqueId'] ) ) {
          $classnames = 'gb-grid-wrapper-' . $block['attrs']['uniqueId'] . ' ' . $classnames;
      }

      // Equivalent to no results.
      if ( strpos($block_content, 'gb-query-loop-item') === false ) {

        $no_results_html =  apply_filters( 'facetwp_wpblocks_gb_query_loop_no_results', '<p>' . facetwp_i18n( __( 'Nothing found.', 'fwp-front' ) ) . '</p>' ) ;
        $block_content = '<div class="' . $classnames . '"><div class="gb-grid-column gb-query-loop-item">' . $no_results_html . '</div></div>';

      }
    }

    return $block_content;
  }


  /**
   * Runs only for 'kadence/posts' block because of hook name.
   * Adds container with class 'facetwp-template'
   * Class on block itself does not work with Kadence Posts blocks own no results query
   */

  function kadence_posts_render( $block_content, $block, $instance ) {
    $block_content = '<div class="facetwp-template">' . $block_content . '</div>';

    return $block_content;
  }


}

$fwpblocks = new FacetWP_WP_Blocks_Integration();
