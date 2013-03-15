<?php

final class ReleephFieldParseException extends Exception {

  public function __construct(ReleephFieldSpecification $field,
                              $message) {

    $name = $field->getName();
    parent::__construct("{$name}: {$message}");
  }

}
