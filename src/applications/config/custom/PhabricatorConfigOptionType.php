<?php

abstract class PhabricatorConfigOptionType extends Phobject {

  public function validateOption(PhabricatorConfigOption $option, $value) {
    return;
  }

  public function readRequest(
    PhabricatorConfigOption $option,
    AphrontRequest $request) {

    $e_value = null;
    $errors = array();
    $storage_value = $request->getStr('value');
    $display_value = $request->getStr('value');

    return array($e_value, $errors, $storage_value, $display_value);
  }

  public function getDisplayValue(
    PhabricatorConfigOption $option,
    PhabricatorConfigEntry $entry,
    $value) {

    if (is_array($value)) {
      $json = new PhutilJSON();
      return $json->encodeFormatted($value);
    } else {
      return $value;
    }

  }

  public function renderControl(
    PhabricatorConfigOption $option,
    $display_value,
    $e_value) {

    return id(new AphrontFormTextControl())
      ->setName('value')
      ->setLabel(pht('Value'))
      ->setValue($display_value)
      ->setError($e_value);
  }

}
