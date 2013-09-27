<?php

final class DifferentialViewPolicyFieldSpecification
  extends DifferentialFieldSpecification {

  private $value;

  public function shouldAppearOnEdit() {
    return true;
  }

  protected function didSetRevision() {
    $this->value = $this->getRevision()->getViewPolicy();
  }

  public function setValueFromRequest(AphrontRequest $request) {
    $this->value = $request->getStr('viewPolicy');
    return $this;
  }

  public function renderEditControl() {
    $viewer = $this->getUser();
    $revision = $this->getRevision();

    $policies = id(new PhabricatorPolicyQuery())
      ->setViewer($viewer)
      ->setObject($revision)
      ->execute();

    return id(new AphrontFormPolicyControl())
      ->setUser($viewer)
      ->setCapability(PhabricatorPolicyCapability::CAN_VIEW)
      ->setPolicyObject($revision)
      ->setPolicies($policies)
      ->setName('viewPolicy');
  }

  public function willWriteRevision(DifferentialRevisionEditor $editor) {
    $this->getRevision()->setViewPolicy($this->value);
  }

}
