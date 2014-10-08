<?php

/**
 * Implodes selected handles from a pool of handles. Useful if you load handles
 * for various phids, but only render a few of them at a time.
 *
 * @return PhutilSafeHTML
 */
function implode_selected_handle_links($glue, array $handles, array $phids) {

  $items = array();
  foreach ($phids as $phid) {
    $items[] = $handles[$phid]->renderLink();
  }

  return phutil_implode_html($glue, $items);
}
