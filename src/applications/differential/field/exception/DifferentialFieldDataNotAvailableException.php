<?php

final class DifferentialFieldDataNotAvailableException extends Exception {

  public function __construct(DifferentialFieldSpecification $spec) {
    $key = $spec->getStorageKey();
    $class = get_class($spec);

    parent::__construct(
      "Differential field specification for '{$key}' (of class '{$class}') is ".
      "attempting to access data which is not available in this context.");
  }

}
