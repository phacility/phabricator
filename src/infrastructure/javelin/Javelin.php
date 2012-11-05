<?php

final class Javelin {
  public static function initBehavior($behavior, array $config = array()) {
    $response = CelerityAPI::getStaticResourceResponse();
    $response->initBehavior($behavior, $config);
  }
}
