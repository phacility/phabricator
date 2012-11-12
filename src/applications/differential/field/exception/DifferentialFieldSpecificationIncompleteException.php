<?php

final class DifferentialFieldSpecificationIncompleteException
  extends Exception {

  public function __construct(DifferentialFieldSpecification $spec) {
    $key = $spec->getStorageKey();
    $class = get_class($spec);

    parent::__construct(
      "Differential field specification for '{$key}' (of class '{$class}') is ".
      "incompletely implemented: it claims it should appear in a context but ".
      "does not implement all the required methods for that context.");
  }

}
