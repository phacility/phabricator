<?php

abstract class PhabricatorJSONConfigType
  extends PhabricatorTextConfigType {

  protected function newCanonicalValue(
    PhabricatorConfigOption $option,
    $value) {

    try {
      $value = phutil_json_decode($value);
    } catch (Exception $ex) {
      throw $this->newException(
        pht(
          'Value for option "%s" (of type "%s") must be specified in JSON, '.
          'but input could not be decoded: %s',
          $option->getKey(),
          $this->getTypeKey(),
          $ex->getMessage()));
    }

    return $value;
  }

  protected function newControl(PhabricatorConfigOption $option) {
    return id(new AphrontFormTextAreaControl())
      ->setHeight(AphrontFormTextAreaControl::HEIGHT_VERY_TALL)
      ->setCustomClass('PhabricatorMonospaced')
      ->setCaption(pht('Enter value in JSON.'));
  }

  public function newDisplayValue(
    PhabricatorConfigOption $option,
    $value) {
    return PhabricatorConfigJSON::prettyPrintJSON($value);
  }

}
