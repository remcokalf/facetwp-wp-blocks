/* Add custom FacetWP toggle to 'core/query' block, in sidebar */
/* Adapted from https://github.com/MarieComet/core-block-custom-attributes */

const { __ } = wp.i18n;

// Enable custom attributes on Query Loop block
const enableSidebarToggleOnBlocks = [
    'core/query'
];

const { createHigherOrderComponent } = wp.compose;
const { Fragment } = wp.element;
const { InspectorControls } = wp.blockEditor;
const { PanelBody, ToggleControl } = wp.components;


/**
 * Declare our custom attribute
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
 * Add Custom Toggle to Query Loop block Sidebar
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
									? 'FacetWP is enabled for this Query Loop block.'
									: 'FacetWP is disabled for this Query Loop block.'
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
