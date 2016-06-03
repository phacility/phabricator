<?php

abstract class PhabricatorStringSetting
  extends PhabricatorSetting {

  final protected function newCustomEditField($object) {
    return $this->newEditField($object, new PhabricatorTextEditField());
  }

  public function getTransactionNewValue($value) {
    if (!strlen($value)) {
      return null;
    }

    return (string)$value;
  }

}
