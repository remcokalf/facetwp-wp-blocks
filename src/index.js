import './attributes/sidebarSelect';

/**
 * Remove 'offset' query parameter from GenerateBlocks Query Loop block options in sidebar.
 */

import { addFilter } from '@wordpress/hooks';

addFilter(
    'generateblocks.editor.query-loop.query-parameters',
    'facetwp-wp-blocks/generateblocks/query-loop/query-parameters/offset',
    removeOffsetParameter
);

function removeOffsetParameter( queryParameters ) {
  const params = queryParameters.filter((param) => param.id !== 'offset');
  return params;
}