<?php

/**
 * TODO: Remove this entirely? We have no callsites.
 */
final class AphrontRedirectException extends AphrontException {

  private $uri;

  public function __construct($uri) {
    $this->uri = $uri;
  }

  public function getURI() {
    return $this->uri;
  }

}
