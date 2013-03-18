<?php

final class ReleephFieldSpecificationIncompleteException extends Exception {

  public function __construct(ReleephFieldSpecification $field) {
    $class = get_class($field);
    parent::__construct(
      "Releeph field class {$class} is incompletely implemented.");
  }

}
