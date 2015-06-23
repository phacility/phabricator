<?php

final class PhabricatorSearchDateControlField
  extends PhabricatorSearchField {

  protected function getValueExistsInRequest(AphrontRequest $request, $key) {
    // The control doesn't actually submit a value with the same name as the
    // key, so look for the "_d" value instead, which has the date part of the
    // control value.
    return $request->getExists($key.'_d');
  }

  protected function getValueFromRequest(AphrontRequest $request, $key) {
    $value = AphrontFormDateControlValue::newFromRequest($request, $key);
    $value->setOptional(true);
    return $value->getDictionary();
  }

  protected function newControl() {
    return id(new AphrontFormDateControl())
      ->setAllowNull(true);
  }

  protected function didReadValueFromSavedQuery($value) {
    if (!$value) {
      return null;
    }

    if ($value instanceof AphrontFormDateControlValue && $value->getEpoch()) {
      return $value->setOptional(true);
    }

    $value = AphrontFormDateControlValue::newFromWild(
      $this->getViewer(),
      $value);
    return $value->setOptional(true);
  }

}
