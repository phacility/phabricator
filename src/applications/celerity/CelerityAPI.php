<?php

/**
 * Indirection layer which provisions for a terrifying future where we need to
 * build multiple resource responses per page.
 */
final class CelerityAPI extends Phobject {

  private static $response;

  public static function getStaticResourceResponse() {
    if (empty(self::$response)) {
      self::$response = new CelerityStaticResourceResponse();
    }
    return self::$response;
  }

}
