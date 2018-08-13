<?php

final class PhabricatorUserTitleField
  extends PhabricatorUserCustomField {

  private $value;

  public function getFieldKey() {
    return 'user:title';
  }

  public function getModernFieldKey() {
    return 'title';
  }

  public function getFieldKeyForConduit() {
    return $this->getModernFieldKey();
  }

  public function getFieldName() {
    return pht('Title');
  }

  public function getFieldDescription() {
    return pht('User title, like "CEO" or "Assistant to the Manager".');
  }

  public function canDisableField() {
    return false;
  }

  public function shouldAppearInApplicationTransactions() {
    return true;
  }

  public function shouldAppearInEditView() {
    return true;
  }

  public function readValueFromObject(PhabricatorCustomFieldInterface $object) {
    $this->value = $object->loadUserProfile()->getTitle();
  }

  public function getOldValueForApplicationTransactions() {
    return $this->getObject()->loadUserProfile()->getTitle();
  }

  public function getNewValueForApplicationTransactions() {
    return $this->value;
  }

  public function applyApplicationTransactionInternalEffects(
    PhabricatorApplicationTransaction $xaction) {
    $this->getObject()->loadUserProfile()->setTitle($xaction->getNewValue());
  }

  public function readValueFromRequest(AphrontRequest $request) {
    $this->value = $request->getStr($this->getFieldKey());
  }

  public function setValueFromStorage($value) {
    $this->value = $value;
    return $this;
  }

  public function renderEditControl(array $handles) {
    return id(new AphrontFormTextControl())
      ->setName($this->getFieldKey())
      ->setValue($this->value)
      ->setLabel($this->getFieldName());
  }

  public function shouldAppearInConduitTransactions() {
    return true;
  }

  protected function newConduitEditParameterType() {
    return new ConduitStringParameterType();
  }

}
