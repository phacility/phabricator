<?php

abstract class PhabricatorTextListConfigType
  extends PhabricatorTextConfigType {

  protected function newControl(PhabricatorConfigOption $option) {
    return id(new AphrontFormTextAreaControl())
      ->setCaption(pht('Separate values with newlines.'));
  }

  protected function newCanonicalValue(
    PhabricatorConfigOption $option,
    $value) {

    $value = phutil_split_lines($value, $retain_endings = false);
    foreach ($value as $k => $v) {
      if (!strlen($v)) {
        unset($value[$k]);
      }
    }

    return array_values($value);
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
          'valid JSON list. When setting a list option from the command '.
          'line, specify the value in JSON. You may need to quote the '.
          'value for your shell (for example: \'["a", "b", ...]\').',
          $option->getKey(),
          $this->getTypeKey()));
    }

    return $value;
  }

  public function newDisplayValue(
    PhabricatorConfigOption $option,
    $value) {
    return implode("\n", $value);
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

    $expect_key = 0;
    foreach ($value as $k => $v) {
      if (!is_string($v)) {
        throw $this->newException(
          pht(
            'Option "%s" is of type "%s", but the item at index "%s" of the '.
            'list is not a string.',
            $option->getKey(),
            $this->getTypeKey(),
            $k));
      }

      // Make sure this is a list with keys "0, 1, 2, ...", not a map with
      // arbitrary keys.
      if ($k != $expect_key) {
        throw $this->newException(
          pht(
            'Option "%s" is of type "%s", but the value is not a list: it '.
            'is a map with unnatural or sparse keys.',
            $option->getKey(),
            $this->getTypeKey()));
      }
      $expect_key++;

      $this->validateStoredItem($option, $v);
    }
  }

  protected function validateStoredItem(
    PhabricatorConfigOption $option,
    $value) {
    return;
  }

}
