<?php

final class PhabricatorRegexListConfigType
  extends PhabricatorTextListConfigType {

  const TYPEKEY = 'list<regex>';

  protected function validateStoredItem(
    PhabricatorConfigOption $option,
    $value) {

    $ok = @preg_match($value, '');
    if ($ok === false) {
      throw $this->newException(
        pht(
          'Option "%s" is of type "%s" and must be set to a list of valid '.
          'regular expressions, but "%s" is not a valid regular expression.',
          $option->getKey(),
          $this->getTypeKey(),
          $value));
    }
  }

}
