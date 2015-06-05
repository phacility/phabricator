<?php

final class PhabricatorSearchDateField
  extends PhabricatorSearchField {

  protected function newControl() {
    return new AphrontFormTextControl();
  }

  protected function getValueFromRequest(AphrontRequest $request, $key) {
    return $request->getStr($key);
  }

  public function getValueForQuery($value) {
    return $this->parseDateTime($value);
  }

  protected function validateControlValue($value) {
    if (!strlen($value)) {
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
    if (!strlen($value)) {
      return null;
    }

    return PhabricatorTime::parseLocalTime($value, $this->getViewer());
  }

}
