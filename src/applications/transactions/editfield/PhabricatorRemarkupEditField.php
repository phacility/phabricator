<?php

final class PhabricatorRemarkupEditField
  extends PhabricatorEditField {

  protected function newControl() {
    return new PhabricatorRemarkupControl();
  }

  protected function newHTTPParameterType() {
    return new AphrontRemarkupHTTPParameterType();
  }

  protected function newConduitParameterType() {
    return new ConduitStringParameterType();
  }

  protected function newBulkParameterType() {
    return new BulkRemarkupParameterType();
  }

  public function getValueForTransaction() {
    $value = $this->getValue();

    if ($value instanceof RemarkupValue) {
      $value = $value->getCorpus();
    }

    return $value;
  }

  public function getValueForDefaults() {
    $value = parent::getValueForDefaults();

    if ($value instanceof RemarkupValue) {
      $value = $value->getCorpus();
    }

    return $value;
  }

  protected function getDefaultValueFromConfiguration($value) {

    // See T13685. After changes to file attachment handling, the database
    // was briefly poisoned with "array()" values as defaults.

    try {
      $value = phutil_string_cast($value);
    } catch (Exception $ex) {
      $value = '';
    } catch (Throwable $ex) {
      $value = '';
    }

    return $value;
  }

  public function getMetadata() {
    $defaults = array();

    $value = $this->getValue();
    if ($value instanceof RemarkupValue) {
      $defaults['remarkup.control'] = $value->getMetadata();
    }

    $metadata = parent::getMetadata();
    $metadata = $metadata + $defaults;

    return $metadata;
  }


}
