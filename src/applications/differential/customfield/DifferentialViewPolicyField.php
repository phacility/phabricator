<?php

final class DifferentialViewPolicyField
  extends DifferentialCoreCustomField {

  public function getFieldKey() {
    return 'differential:view-policy';
  }

  public function getFieldName() {
    return pht('View Policy');
  }

  public function getFieldDescription() {
    return pht('Controls visibility.');
  }

  protected function readValueFromRevision(
    DifferentialRevision $revision) {
    return $revision->getViewPolicy();
  }

  public function readValueFromRequest(AphrontRequest $request) {
    $this->setValue($request->getStr($this->getFieldKey()));
  }

  public function renderEditControl(array $handles) {
    $viewer = $this->getViewer();
    $revision = $this->getObject();

    $policies = id(new PhabricatorPolicyQuery())
      ->setViewer($viewer)
      ->setObject($revision)
      ->execute();

    return id(new AphrontFormPolicyControl())
      ->setUser($viewer)
      ->setCapability(PhabricatorPolicyCapability::CAN_VIEW)
      ->setPolicyObject($revision)
      ->setPolicies($policies)
      ->setName($this->getFieldKey())
      ->setValue($this->getValue())
      ->setError($this->getFieldError());
  }

  public function getApplicationTransactionType() {
    return PhabricatorTransactions::TYPE_VIEW_POLICY;
  }

}
