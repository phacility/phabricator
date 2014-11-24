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
    return pht('Short blurb about the user.');
  }

  public function shouldAppearInApplicationTransactions() {
    return true;
  }

  public function shouldAppearInEditView() {
    return true;
  }

  public function shouldAppearInPropertyView() {
    return true;
  }

  public function readValueFromObject(PhabricatorCustomFieldInterface $object) {
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

  public function readValueFromRequest(AphrontRequest $request) {
    $this->value = $request->getStr($this->getFieldKey());
  }

  public function renderEditControl(array $handles) {
    return id(new PhabricatorRemarkupControl())
      ->setUser($this->getViewer())
      ->setName($this->getFieldKey())
      ->setValue($this->value)
      ->setLabel($this->getFieldName());
  }

  public function renderPropertyViewLabel() {
    return null;
  }

  public function renderPropertyViewValue(array $handles) {
    $blurb = $this->getObject()->loadUserProfile()->getBlurb();
    if (!strlen($blurb)) {
      return null;
    }
    return PhabricatorMarkupEngine::renderOneObject(
      id(new PhabricatorMarkupOneOff())->setContent($blurb),
      'default',
      $this->getViewer());
  }

  public function getStyleForPropertyView() {
    return 'block';
  }

}
