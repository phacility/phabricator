<?php

final class PhabricatorWildConfigType
  extends PhabricatorJSONConfigType {

  const TYPEKEY = 'wild';

  protected function newCanonicalValue(
    PhabricatorConfigOption $option,
    $value) {

    $raw_value = $value;

    // NOTE: We're significantly more liberal about canonicalizing "wild"
    // values than "JSON" values because they're used to deal with some
    // unusual edge cases, including situations where old config has been left
    // in the database and we aren't sure what type it's supposed to be.
    // Accept anything we can decode.

    $value = json_decode($raw_value, true);
    if ($value === null && $raw_value != 'null') {
      throw $this->newException(
        pht(
          'Value for option "%s" (of type "%s") must be specified in JSON, '.
          'but input could not be decoded. (Did you forget to quote a string?)',
          $option->getKey(),
          $this->getTypeKey()));
    }

    return $value;
  }

  public function validateStoredValue(
    PhabricatorConfigOption $option,
    $value) {
    return;
  }

}
