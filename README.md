# FacetWP WP Query Loop Blocks
Integrates FacetWP with WordPress "Query Loop" blocks.

## Supported blocks
Supports the following Gutenberg blocks based on the "core/query" block:

- "Query Loop" block
- "Posts List" block
- "Products (Beta)" block (included in WooCommerce)

## Usage
Install and activate the plugin, then:

- Add one of the above blocks. It will add the main parent block and at the least one child block: the "Post Template" block.
- Select the main (parent) block.
- Enable FacetWP with the "Enable FacetWP" toggle in the sidebar
- Add some facets to the page. The easiest way is to use a Shortcode block for each facet. For pagination use a Pager facet.
- Save the page.


### Optional: add a custom 'No results' block

- Add a "No results" block as a child block of the main "Query Loop" block, below the "Post Template" block.
- Add a custom "No results" message, and optional header or other content. 

**Notes:**
- The "No results" block can only be added when the child "Post Template" block is selected.
- without a "No results" block, a default "Nothing found" message is shown when there are no results.

## Pagination and sorting

This plugin works with Sort facets and Pager facets, including the Load More Pager facet type.

This plugin does **not work** with a "Pagination" block (which can be added when the "Post Template" block is selected. Also, some Query Loop / Posts List block layout patterns automatically add a "Pagination" block. If that happens, this block needs to be removed. 
Use a Pager facet for pagination instead.

## Unsupported layout patterns

When adding a Query Loop block, you can choose from several layout pattern. There is at least one layout pattern that adds **two** Query Loop blocks. Enabling FacetWP on multiple Query Loop blocks **is not supported**. Enable only one Query Loop block for FacetWP to interact with.
