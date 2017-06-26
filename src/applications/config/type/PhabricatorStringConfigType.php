<?php

final class PhabricatorStringConfigType
  extends PhabricatorTextConfigType {

  const TYPEKEY = 'string';

  public function validateStoredValue(
    PhabricatorConfigOption $option,
    $value) {

    if (!is_string($value)) {
      throw $this->newException(
        pht(
          'Option "%s" is of type "%s", but the configured value is not '.
          'a string.',
          $option->getKey(),
          $this->getTypeKey()));
    }
  }

}
