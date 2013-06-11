<?php

final class PhabricatorUserBlurbField
  extends PhabricatorUserCustomField {

  private $value;

  public function getFieldKey() {
    return 'user:blurb';
  }

  public function getFieldName() {
    return pht('Blurb');
  }

  public function getFieldDescription() {
    return pht('Short user summary.');
  }

  protected function didSetObject(PhabricatorCustomFieldInterface $object) {
    $this->value = $object->loadUserProfile()->getBlurb();
  }

  public function getOldValueForApplicationTransactions() {
    return $this->getObject()->loadUserProfile()->getBlurb();
  }

  public function getNewValueForApplicationTransactions() {
    return $this->value;
  }

  public function applyApplicationTransactionInternalEffects(
    PhabricatorApplicationTransaction $xaction) {
    $this->getObject()->loadUserProfile()->setBlurb($xaction->getNewValue());
  }

  public function setValueFromRequest(AphrontRequest $request) {
    $this->value = $request->getStr($this->getFieldKey());
  }

  public function renderEditControl() {
    return id(new PhabricatorRemarkupControl())
      ->setName($this->getFieldKey())
      ->setValue($this->value)
      ->setLabel($this->getFieldName());
  }

}
