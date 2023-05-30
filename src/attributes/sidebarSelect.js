/* Add custom FacetWP toggle to specified blocks, in sidebar */
/* Adapted from https://github.com/MarieComet/core-block-custom-attributes */

const { __ } = wp.i18n;

// Enable custom attributes on core Query Loop and Latest Posts blocks, and the GenerateBlocks Query Loop block.
const enableSidebarToggleOnBlocks = [
    'core/query',
    'core/latest-posts',
    'generateblocks/query-loop',
    'kadence/posts'
];

const { createHigherOrderComponent } = wp.compose;
const { Fragment } = wp.element;
const { InspectorControls } = wp.blockEditor;
const { PanelBody, ToggleControl } = wp.components;


/**
 * Declare Custom Toggle
 */

const setSidebarToggle = ( settings, name ) => {

    // Do nothing if it's another block than our defined ones.
    if ( ! enableSidebarToggleOnBlocks.includes( name ) ) {
        return settings;
    }

    return Object.assign( {}, settings, {
        attributes: Object.assign( {}, settings.attributes, {
            enableFacetWP: { type: 'boolean' }
        } ),
    } );
};

wp.hooks.addFilter(
    'blocks.registerBlockType',
    'facetwp/set-sidebar-toggle',
    setSidebarToggle
);

/**
 * Add custom toggle to the block sidebar
 */

const withSidebarToggle = createHigherOrderComponent( ( BlockEdit ) => {
    return ( props ) => {

        // If current block is not allowed
    	if ( ! enableSidebarToggleOnBlocks.includes( props.name ) ) {
            return (
                <BlockEdit { ...props } />
            );
        }

        const { attributes, setAttributes } = props;
        const { enableFacetWP } = attributes;

        return (
            <Fragment>
                <BlockEdit { ...props } />
                <InspectorControls>
                	<PanelBody
    	                title={ __( 'FacetWP' ) }
    	            >
						<ToggleControl
							label="Enable FacetWP"
							help={
								enableFacetWP
									? 'FacetWP is enabled for this block.'
									: 'FacetWP is disabled for this block.'
							}
							checked={ enableFacetWP }
							onChange={ ( value ) => {
								setAttributes( {
									enableFacetWP: value,
								} );
							} }
						/>
	                </PanelBody>
                </InspectorControls>
            </Fragment>
        );
    };
}, 'withSidebarToggle' );

wp.hooks.addFilter(
    'editor.BlockEdit',
    'facetwp/with-sidebar-toggle',
    withSidebarToggle
);