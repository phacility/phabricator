<?php

final class HeraldContentTypeConfig {

  const CONTENT_TYPE_DIFFERENTIAL = 'differential';
  const CONTENT_TYPE_COMMIT       = 'commit';

  public static function getContentTypeMap() {
    $map = array();

    $adapters = HeraldAdapter::getAllEnabledAdapters();
    foreach ($adapters as $adapter) {
      $type = $adapter->getAdapterContentType();
      $name = $adapter->getAdapterContentName();
      $map[$type] = $name;
    }

    asort($map);
    return $map;
  }
}
