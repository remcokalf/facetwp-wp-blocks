# FacetWP WP Blocks
Integrates FacetWP with specific WordPress, GenerateBlocks and Kadence Blocks blocks.

## Supported blocks
Supports the following blocks based on the "core/query" block  (available under the "Theme" heading):

- "Query Loop"
- "Posts List"
- "Products (Beta)" (comes with WooCommerce)

And the following block (available under the "Widgets" heading):

- "Latest Posts"

Also supported are:

- GeneratePress "Query Loop" block
- Kadence Blocks "Posts" block

## Usage
Install and activate the plugin, then:

- Go to or open a new block page, or a block template page.
- Add one of the supported blocks. 
- Select the (main parent) block in the block list.
- Enable FacetWP with the "Enable FacetWP" toggle in the sidebar.
- Save the page.

In general:
- Don't use any 'offset' setting (it will just not work as it is overwritten). For GenerateBlocks it is disabled, for WP and Kadence blocks not (seems to be impossible).


## Pagination and sorting

This plugin works with Sort facets and Pager facets, including the Load More Pager facet.

This plugin does **not work** with a "Pagination" block (which can be added when the "Post Template" block is selected. 

Some Query Loop / Posts List block layout patterns automatically add a "Pagination" block. If that happens, this block needs to be removed. Use a Pager facet for pagination instead.

GenerateBlocks Query Loop block has the option to add Pagination, which should not be used: it does not work.


## Unsupported layout patterns

When adding a Query Loop block, you can choose a layout pattern. There is at least one layout pattern that adds **two** Query Loop blocks. Enabling FacetWP on multiple Query Loop blocks **is not supported**. Enable only one Query Loop block for FacetWP to interact with.


## Optional: add a custom 'No results' block

Optional for WP "core/query" blocks:
- Add a "No results" block as a child block of the main "Query Loop" block, below the "Post Template" block.
- Add a custom "No results" message to it, and optional header or other HTML/content/blocks.

**Notes:**
- The "No results" block can only be added when the child "Post Template" block is selected.
- Without a "No results" block, a default (translatable) 'Nothing found.' message is shown when there are no results.

### Change the default 'No results' message 

Every block has a default 'No results' mesasage. It can be changed with the following hooks:

**WP Query Loop block:**

```php
add_filter( 'facetwp_wpblocks_query_loop_no_results', function() {
  return '<p>' . facetwp_i18n(__( 'Nothing found.', 'fwp-front' )) .'</p>';
},10);
```

**WP Latest Posts block:**

```php
add_filter( 'facetwp_wpblocks_latest_posts_no_results', function() {
  return '<p>' . facetwp_i18n(__( 'Nothing found.', 'fwp-front' )) .'</p>';
},10);
```

**GenerateBlocks Query Loop block:**

```php
add_filter( 'facetwp_wpblocks_gb_query_loop_no_results', function() {
  return '<p>' . facetwp_i18n(__( 'Nothing found.', 'fwp-front' )) .'</p>';
},10);
```


**Kadence Blocks Posts block:**

```php
add_filter( 'kadence_blocks_posts_empty_query', function() {
  return '<p>' . facetwp_i18n(__( 'Nothing found.', 'fwp-front' )) .'</p>';
},10);
```
