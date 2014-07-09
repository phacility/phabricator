<?php

/**
 * Include a CSS or JS static resource by name. This function records a
 * dependency for the current page, so when a response is generated it can be
 * included. You can call this method from any context, and it is recommended
 * you invoke it as close to the actual dependency as possible so that page
 * dependencies are minimized.
 *
 * For more information, see @{article:Adding New CSS and JS}.
 *
 * @param string Name of the celerity module to include. This is whatever you
 *               annotated as "@provides" in the file.
 * @return void
 */
function require_celerity_resource($symbol, $source_name = 'phabricator') {
  $response = CelerityAPI::getStaticResourceResponse();
  $response->requireResource($symbol, $source_name);
}


/**
 * Generate a node ID which is guaranteed to be unique for the current page,
 * even across Ajax requests. You should use this method to generate IDs for
 * nodes which require a uniqueness guarantee.
 *
 * @return string A string appropriate for use as an 'id' attribute on a DOM
 *                node. It is guaranteed to be unique for the current page, even
 *                if the current request is a subsequent Ajax request.
 */
function celerity_generate_unique_node_id() {
  static $uniq = 0;
  $response = CelerityAPI::getStaticResourceResponse();
  $block = $response->getMetadataBlock();

  return 'UQ'.$block.'_'.($uniq++);
}


/**
 * Get the versioned URI for a raw resource, like an image.
 *
 * @param   string  Path to the raw image.
 * @return  string  Versioned path to the image, if one is available.
 */
function celerity_get_resource_uri($resource, $source = 'phabricator') {
  $resource = ltrim($resource, '/');

  $map = CelerityResourceMap::getNamedInstance($source);
  $response = CelerityAPI::getStaticResourceResponse();
  return $response->getURI($map, $resource);
}
