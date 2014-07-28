<?php

/**
 * @concrete-extensible
 */
class ConduitMethodNotFoundException extends ConduitException {

  public function __construct($method) {
    parent::__construct(pht("Conduit method '%s' does not exist.", $method));
  }

}
