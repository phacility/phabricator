<?php

final class ConduitMethodDoesNotExistException
  extends ConduitMethodNotFoundException {

  public function __construct($method_name) {
    parent::__construct(
      pht(
        'Conduit API method "%s" does not exist.',
        $method_name));
  }

}
