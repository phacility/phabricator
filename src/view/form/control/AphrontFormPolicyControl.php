<?php

final class AphrontFormPolicyControl extends AphrontFormControl {

  private $object;
  private $capability;
  private $policies;

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
      PhabricatorPolicyCapability::CAN_VIEW => pht('Visible To'),
      PhabricatorPolicyCapability::CAN_EDIT => pht('Editable By'),
      PhabricatorPolicyCapability::CAN_JOIN => pht('Joinable By'),
    );

    $this->setLabel(idx($labels, $this->capability, pht('Unknown Policy')));

    return $this;
  }

  protected function getCustomControlClass() {
    return 'aphront-form-control-policy';
  }

  protected function getOptions() {
    $capability = $this->capability;

    $options = array();
    foreach ($this->policies as $policy) {
      if ($policy->getPHID() == PhabricatorPolicies::POLICY_PUBLIC) {
        // Never expose "Public" for capabilities which don't support it.
        $capobj = PhabricatorPolicyCapability::getCapabilityByKey($capability);
        if (!$capobj || !$capobj->shouldAllowPublicPolicySetting()) {
          continue;
        }
      }

      $type_name = PhabricatorPolicyType::getPolicyTypeName($policy->getType());
      $options[$type_name][$policy->getPHID()] = $policy->getFullName();
    }
    return $options;
  }

  protected function renderInput() {
    if (!$this->object) {
      throw new Exception(pht("Call setPolicyObject() before rendering!"));
    }
    if (!$this->capability) {
      throw new Exception(pht("Call setCapability() before rendering!"));
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
