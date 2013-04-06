<?php

/**
 * Implodes a list of handles, rendering their links
 *
 * @group  handle
 * @return PhutilSafeHTML
 */
function implode_handle_links($glue, array $handles) {

  $items = array();
  foreach ($handles as $handle) {
    $items[] = $handle->renderLink();
  }

  return phutil_implode_html($glue, $items);
}

/**
 * Like @{function:implode_handle_links}Implodes selected handles from a pool of
 * handles. Useful if you load handles for various phids, but only render a few
 * of them at a time
 *
 * @group  handle
 * @return PhutilSafeHTML
 */
function implode_selected_handle_links($glue, array $handles, array $phids) {

  $items = array();
  foreach ($phids as $phid) {
    $items[] = $handles[$phid]->renderLink();
  }

  return phutil_implode_html($glue, $items);
}
