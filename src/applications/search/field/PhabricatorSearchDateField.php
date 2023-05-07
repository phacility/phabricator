<?php

final class PhabricatorSearchDateField
  extends PhabricatorSearchField {

  protected function newControl() {
    return id(new AphrontFormTextControl())
      ->setPlaceholder(pht('"2022-12-25" or "7 days ago"...'));
  }

  protected function getValueFromRequest(AphrontRequest $request, $key) {
    return $request->getStr($key);
  }

  public function getValueForQuery($value) {
    return $this->parseDateTime($value);
  }

  protected function validateControlValue($value) {
    if ($value === null || !strlen($value)) {
      return;
    }

    $epoch = $this->parseDateTime($value);
    if ($epoch) {
      return;
    }

    $this->addError(
      pht('Invalid'),
      pht('Date value for "%s" can not be parsed.', $this->getLabel()));
  }

  protected function parseDateTime($value) {
    if ($value === null || !strlen($value)) {
      return null;
    }

    // If this appears to be an epoch timestamp, just return it unmodified.
    // This assumes values like "2016" or "20160101" are "Ymd".
    if (is_int($value) || ctype_digit($value)) {
      if ((int)$value > 30000000) {
        return (int)$value;
      }
    }

    return PhabricatorTime::parseLocalTime($value, $this->getViewer());
  }

  protected function newConduitParameterType() {
    return new ConduitEpochParameterType();
  }

}
