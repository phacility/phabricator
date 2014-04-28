<?php

final class PhabricatorAuthHighSecurityRequiredException extends Exception {

  private $cancelURI;

  public function setCancelURI($cancel_uri) {
    $this->cancelURI = $cancel_uri;
    return $this;
  }

  public function getCancelURI() {
    return $this->cancelURI;
  }

}
