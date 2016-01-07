<?php

final class PhabricatorPolicyEditField
  extends PhabricatorEditField {

  private $policies;
  private $capability;
  private $spaceField;

  public function setPolicies(array $policies) {
    $this->policies = $policies;
    return $this;
  }

  public function getPolicies() {
    if ($this->policies === null) {
      throw new PhutilInvalidStateException('setPolicies');
    }
    return $this->policies;
  }

  public function setCapability($capability) {
    $this->capability = $capability;
    return $this;
  }

  public function getCapability() {
    return $this->capability;
  }

  public function setSpaceField(PhabricatorSpaceEditField $space_field) {
    $this->spaceField = $space_field;
    return $this;
  }

  public function getSpaceField() {
    return $this->spaceField;
  }

  protected function newControl() {
    $control = id(new AphrontFormPolicyControl())
      ->setCapability($this->getCapability())
      ->setPolicyObject($this->getObject())
      ->setPolicies($this->getPolicies());

    $space_field = $this->getSpaceField();
    if ($space_field) {
      $control->setSpacePHID($space_field->getValueForControl());
    }

    return $control;
  }

  protected function newHTTPParameterType() {
    return new AphrontPHIDHTTPParameterType();
  }

  protected function newConduitParameterType() {
    return new ConduitStringParameterType();
  }


}
