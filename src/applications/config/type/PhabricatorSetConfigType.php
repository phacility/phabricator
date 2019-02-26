<?php

final class PhabricatorSetConfigType
  extends PhabricatorTextConfigType {

  const TYPEKEY = 'set';

  protected function newControl(PhabricatorConfigOption $option) {
    return id(new AphrontFormTextAreaControl())
      ->setCaption(pht('Separate values with newlines or commas.'));
  }

  protected function newCanonicalValue(
    PhabricatorConfigOption $option,
    $value) {

    $value = preg_split('/[\n,]+/', $value);
    foreach ($value as $k => $v) {
      if (!strlen($v)) {
        unset($value[$k]);
      }
      $value[$k] = trim($v);
    }

    return array_fill_keys($value, true);
  }

  public function newValueFromCommandLineValue(
    PhabricatorConfigOption $option,
    $value) {

    try {
      $value = phutil_json_decode($value);
    } catch (Exception $ex) {
      throw $this->newException(
        pht(
          'Option "%s" is of type "%s", but the value you provided is not a '.
          'valid JSON list: when providing a set from the command line, '.
          'specify it as a list of values in JSON. You may need to quote the '.
          'value for your shell (for example: \'["a", "b", ...]\').',
          $option->getKey(),
          $this->getTypeKey()));
    }

    if ($value) {
      if (!phutil_is_natural_list($value)) {
        throw $this->newException(
          pht(
            'Option "%s" is of type "%s", and should be specified on the '.
            'command line as a JSON list of values. You may need to quote '.
            'the value for your shell (for example: \'["a", "b", ...]\').',
            $option->getKey(),
            $this->getTypeKey()));
      }
    }

    return array_fill_keys($value, true);
  }

  public function newDisplayValue(
    PhabricatorConfigOption $option,
    $value) {
    return implode("\n", array_keys($value));
  }

  public function validateStoredValue(
    PhabricatorConfigOption $option,
    $value) {

    if (!is_array($value)) {
      throw $this->newException(
        pht(
          'Option "%s" is of type "%s", but the configured value is not '.
          'a list.',
          $option->getKey(),
          $this->getTypeKey()));
    }

    foreach ($value as $k => $v) {
      if ($v !== true) {
        throw $this->newException(
          pht(
            'Option "%s" is of type "%s", but the value at index "%s" of the '.
            'list is not "true".',
            $option->getKey(),
            $this->getTypeKey(),
            $k));
      }
    }
  }

}
