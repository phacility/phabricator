<?php

abstract class DifferentialCustomField
  extends PhabricatorCustomField {

  protected function renderHandleList(array $handles) {
    if (!$handles) {
      return null;
    }

    $out = array();
    foreach ($handles as $handle) {
      $out[] = $handle->renderLink();
    }

    return phutil_implode_html(phutil_tag('br'), $out);
  }

}
