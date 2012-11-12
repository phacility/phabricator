<?php

final class AphrontFormPolicyControl extends AphrontFormControl {

  private $user;
  private $object;
  private $capability;
  private $policies;

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  public function getUser() {
    return $this->user;
  }

  public function setPolicyObject(PhabricatorPolicyInterface $object) {
    $this->object = $object;
    return $this;
  }

  public function setPolicies(array $policies) {
    assert_instances_of($policies, 'PhabricatorPolicy');
    $this->policies = $policies;
    return $this;
  }

  public function setCapability($capability) {
    $this->capability = $capability;

    $labels = array(
      PhabricatorPolicyCapability::CAN_VIEW => 'Visible To',
      PhabricatorPolicyCapability::CAN_EDIT => 'Editable By',
      PhabricatorPolicyCapability::CAN_JOIN => 'Joinable By',
    );

    $this->setLabel(idx($labels, $this->capability, 'Unknown Policy'));

    return $this;
  }

  protected function getCustomControlClass() {
    return 'aphront-form-control-policy';
  }

  protected function getOptions() {
    $options = array();
    foreach ($this->policies as $policy) {
      if (($policy->getPHID() == PhabricatorPolicies::POLICY_PUBLIC) &&
          ($this->capability != PhabricatorPolicyCapability::CAN_VIEW)) {
        // Never expose "Public" for anything except "Can View".
        continue;
      }

      $type_name = PhabricatorPolicyType::getPolicyTypeName($policy->getType());
      $options[$type_name][$policy->getPHID()] = $policy->getFullName();
    }
    return $options;
  }

  protected function renderInput() {
    if (!$this->object) {
      throw new Exception("Call setPolicyObject() before rendering!");
    }
    if (!$this->capability) {
      throw new Exception("Call setCapability() before rendering!");
    }

    $policy = $this->object->getPolicy($this->capability);
    if (!$policy) {
      // TODO: Make this configurable.
      $policy = PhabricatorPolicies::POLICY_USER;
    }
    $this->setValue($policy);

    return AphrontFormSelectControl::renderSelectTag(
      $this->getValue(),
      $this->getOptions(),
      array(
        'name'      => $this->getName(),
        'disabled'  => $this->getDisabled() ? 'disabled' : null,
        'id'        => $this->getID(),
      ));
  }


}
