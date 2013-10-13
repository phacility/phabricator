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

      $options[$policy->getType()][$policy->getPHID()] = array(
        'name' => phutil_utf8_shorten($policy->getName(), 28),
        'full' => $policy->getName(),
        'icon' => $policy->getIcon(),
      );
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


    $control_id = celerity_generate_unique_node_id();
    $input_id = celerity_generate_unique_node_id();

    $caret = phutil_tag(
      'span',
      array(
        'class' => 'caret',
      ));

    $input = phutil_tag(
      'input',
      array(
        'type' => 'hidden',
        'id' => $input_id,
        'name' => $this->getName(),
        'value' => $this->getValue(),
      ));

    $options = $this->getOptions();

    $order = array();
    $labels = array();
    foreach ($options as $key => $values) {
      $order[$key] = array_keys($values);
      $labels[$key] = PhabricatorPolicyType::getPolicyTypeName($key);
    }

    $flat_options = array_mergev($options);

    $icons = array();
    foreach (igroup($flat_options, 'icon') as $icon => $ignored) {
      $icons[$icon] = id(new PHUIIconView())
        ->setSpriteSheet(PHUIIconView::SPRITE_STATUS)
        ->setSpriteIcon($icon);
    }


    Javelin::initBehavior(
      'policy-control',
      array(
        'controlID' => $control_id,
        'inputID' => $input_id,
        'options' => $flat_options,
        'groups' => array_keys($options),
        'order' => $order,
        'icons' => $icons,
        'labels' => $labels,
        'value' => $this->getValue(),
      ));

    $selected = $flat_options[$this->getValue()];

    return phutil_tag(
      'div',
      array(
      ),
      array(
        javelin_tag(
          'a',
          array(
            'class' => 'grey button dropdown has-icon policy-control',
            'href' => '#',
            'mustcapture' => true,
            'sigil' => 'policy-control',
            'id' => $control_id,
          ),
          array(
            $caret,
            javelin_tag(
              'span',
              array(
                'sigil' => 'policy-label',
                'class' => 'phui-button-text',
              ),
              array(
                $icons[$selected['icon']],
                $selected['name'],
              )),
          )),
        $input,
      ));

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
