<?php

final class PhabricatorBoolConfigType
  extends PhabricatorTextConfigType {

  const TYPEKEY = 'bool';

  protected function newCanonicalValue(
    PhabricatorConfigOption $option,
    $value) {

    if (!preg_match('/^(true|false)\z/', $value)) {
      throw $this->newException(
        pht(
          'Value for option "%s" of type "%s" must be either '.
          '"true" or "false".',
          $option->getKey(),
          $this->getTypeKey()));
    }

    return ($value === 'true');
  }

  public function newDisplayValue(
    PhabricatorConfigOption $option,
    $value) {

    if ($value) {
      return 'true';
    } else {
      return 'false';
    }
  }

  public function validateStoredValue(
    PhabricatorConfigOption $option,
    $value) {

    if (!is_bool($value)) {
      throw $this->newException(
        pht(
          'Option "%s" is of type "%s", but the configured value is not '.
          'a boolean.',
          $option->getKey(),
          $this->getTypeKey()));
    }
  }

  protected function newControl(PhabricatorConfigOption $option) {
    $bool_map = $option->getBoolOptions();

    $map = array(
      '' => pht('(Use Default)'),
    ) + array(
      'true'  => idx($bool_map, 0),
      'false' => idx($bool_map, 1),
    );

    return id(new AphrontFormSelectControl())
      ->setOptions($map);
  }
}
