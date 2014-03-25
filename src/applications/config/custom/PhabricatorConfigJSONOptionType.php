<?php

abstract class PhabricatorConfigJSONOptionType
  extends PhabricatorConfigOptionType {

  public function readRequest(
    PhabricatorConfigOption $option,
    AphrontRequest $request) {

    $e_value = null;
    $errors = array();
    $storage_value = $request->getStr('value');
    $display_value = $request->getStr('value');

    if (strlen($display_value)) {
      $storage_value = phutil_json_decode($display_value);
      if ($storage_value === null) {
        $e_value = pht('Invalid');
        $errors[] = pht(
          'Configuration value should be specified in JSON. The provided '.
          'value is not valid JSON.');
      } else {
        try {
          $this->validateOption($option, $storage_value);
        } catch (Exception $ex) {
          $e_value = pht('Invalid');
          $errors[] = $ex->getMessage();
        }
      }
    } else {
      $storage_value = null;
    }

    return array($e_value, $errors, $storage_value, $display_value);
  }

  public function getDisplayValue(
    PhabricatorConfigOption $option,
    PhabricatorConfigEntry $entry) {
    $value = $entry->getValue();
    if (!$value) {
      return '';
    }

    $json = new PhutilJSON();
    return $json->encodeFormatted($value);
  }

  public function renderControl(
    PhabricatorConfigOption $option,
    $display_value,
    $e_value) {

    return id(new AphrontFormTextAreaControl())
      ->setHeight(AphrontFormTextAreaControl::HEIGHT_VERY_TALL)
      ->setName('value')
      ->setLabel(pht('Value'))
      ->setValue($display_value)
      ->setError($e_value);
  }

}
