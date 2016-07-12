<?php

final class Javelin extends Phobject {

  public static function initBehavior(
    $behavior,
    array $config = array(),
    $source_name = 'phabricator') {

    $response = CelerityAPI::getStaticResourceResponse();

    $response->initBehavior($behavior, $config, $source_name);
  }

}
