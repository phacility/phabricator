<?php

final class PhabricatorUserBlurbField
  extends PhabricatorUserCustomField {

  private $value;

  public function getFieldKey() {
    return 'user:blurb';
  }

  public function getModernFieldKey() {
    return 'blurb';
  }

  public function getFieldKeyForConduit() {
    return $this->getModernFieldKey();
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

  public function setValueFromStorage($value) {
    $this->value = $value;
    return $this;
  }

  public function renderEditControl(array $handles) {
    return id(new PhabricatorRemarkupControl())
      ->setUser($this->getViewer())
      ->setName($this->getFieldKey())
      ->setValue($this->value)
      ->setLabel($this->getFieldName());
  }

  public function getApplicationTransactionRemarkupBlocks(
    PhabricatorApplicationTransaction $xaction) {
    return array(
      $xaction->getNewValue(),
    );
  }

  public function renderPropertyViewLabel() {
    return null;
  }

  public function renderPropertyViewValue(array $handles) {
    $blurb = $this->getObject()->loadUserProfile()->getBlurb();
    if (!strlen($blurb)) {
      return null;
    }

    $viewer = $this->getViewer();
    $view = new PHUIRemarkupView($viewer, $blurb);

    return $view;
  }

  public function getStyleForPropertyView() {
    return 'block';
  }

  public function shouldAppearInConduitTransactions() {
    return true;
  }

  protected function newConduitEditParameterType() {
    return new ConduitStringParameterType();
  }

}
