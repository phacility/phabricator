<?php

final class DiffusionRefNotFoundException extends Exception {

  private $ref;

  public function setRef($ref) {
    $this->ref = $ref;
    return $this;
  }

  public function getRef() {
    return $this->ref;
  }

}
