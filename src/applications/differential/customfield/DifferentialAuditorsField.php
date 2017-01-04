<?php

final class DifferentialAuditorsField
  extends DifferentialStoredCustomField {

  public function getFieldKey() {
    return 'phabricator:auditors';
  }

  public function getFieldName() {
    return pht('Auditors');
  }

  public function getFieldDescription() {
    return pht('Allows commits to trigger audits explicitly.');
  }

  public function getValueForStorage() {
    return phutil_json_encode($this->getValue());
  }

  public function setValueFromStorage($value) {
    try {
      $this->setValue(phutil_json_decode($value));
    } catch (PhutilJSONParserException $ex) {
      $this->setValue(array());
    }
    return $this;
  }

  public function canDisableField() {
    return false;
  }

  public function shouldAppearInEditEngine() {
    return true;
  }

  public function shouldAppearInCommitMessage() {
    return true;
  }

  public function shouldAppearInConduitTransactions() {
    return true;
  }

  protected function newConduitEditParameterType() {
    return new ConduitPHIDListParameterType();
  }

  public function shouldAppearInApplicationTransactions() {
    return true;
  }

}
