<?php

final class DifferentialEditPolicyFieldSpecification
  extends DifferentialFieldSpecification {

  private $value;

  public function shouldAppearOnEdit() {
    return true;
  }

  protected function didSetRevision() {
    $this->value = $this->getRevision()->getEditPolicy();
  }

  public function setValueFromRequest(AphrontRequest $request) {
    $this->value = $request->getStr('editPolicy');
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
      ->setCapability(PhabricatorPolicyCapability::CAN_EDIT)
      ->setPolicyObject($revision)
      ->setPolicies($policies)
      ->setName('editPolicy')
      ->setCaption(
        pht(
          'NOTE: These policy features are not yet fully supported.'));
  }

  public function willWriteRevision(DifferentialRevisionEditor $editor) {
    $this->getRevision()->setEditPolicy($this->value);
  }

}
