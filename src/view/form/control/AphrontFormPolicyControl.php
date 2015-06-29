<?php

final class AphrontFormPolicyControl extends AphrontFormControl {

  private $object;
  private $capability;
  private $policies;
  private $spacePHID;
  private $templatePHIDType;
  private $templateObject;

  public function setPolicyObject(PhabricatorPolicyInterface $object) {
    $this->object = $object;
    return $this;
  }

  public function setPolicies(array $policies) {
    assert_instances_of($policies, 'PhabricatorPolicy');
    $this->policies = $policies;
    return $this;
  }

  public function setSpacePHID($space_phid) {
    $this->spacePHID = $space_phid;
    return $this;
  }

  public function getSpacePHID() {
    return $this->spacePHID;
  }

  public function setTemplatePHIDType($type) {
    $this->templatePHIDType = $type;
    return $this;
  }

  public function setTemplateObject($object) {
    $this->templateObject = $object;
    return $this;
  }

  public function getSerializedValue() {
    return json_encode(array(
      $this->getValue(),
      $this->getSpacePHID(),
    ));
  }

  public function readSerializedValue($value) {
    $decoded = phutil_json_decode($value);
    $policy_value = $decoded[0];
    $space_phid = $decoded[1];
    $this->setValue($policy_value);
    $this->setSpacePHID($space_phid);
    return $this;
  }

  public function readValueFromDictionary(array $dictionary) {
    // TODO: This is a little hacky but will only get us into trouble if we
    // have multiple view policy controls in multiple paged form views on the
    // same page, which seems unlikely.
    $this->setSpacePHID(idx($dictionary, 'spacePHID'));

    return parent::readValueFromDictionary($dictionary);
  }

  public function readValueFromRequest(AphrontRequest $request) {
    // See note in readValueFromDictionary().
    $this->setSpacePHID($request->getStr('spacePHID'));

    return parent::readValueFromRequest($request);
  }

  public function setCapability($capability) {
    $this->capability = $capability;

    $labels = array(
      PhabricatorPolicyCapability::CAN_VIEW => pht('Visible To'),
      PhabricatorPolicyCapability::CAN_EDIT => pht('Editable By'),
      PhabricatorPolicyCapability::CAN_JOIN => pht('Joinable By'),
    );

    if (isset($labels[$capability])) {
      $label = $labels[$capability];
    } else {
      $capobj = PhabricatorPolicyCapability::getCapabilityByKey($capability);
      if ($capobj) {
        $label = $capobj->getCapabilityName();
      } else {
        $label = pht('Capability "%s"', $capability);
      }
    }

    $this->setLabel($label);

    return $this;
  }

  protected function getCustomControlClass() {
    return 'aphront-form-control-policy';
  }

  protected function getOptions() {
    $capability = $this->capability;
    $policies = $this->policies;

    // Exclude object policies which don't make sense here. This primarily
    // filters object policies associated from template capabilities (like
    // "Default Task View Policy" being set to "Task Author") so they aren't
    // made available on non-template capabilities (like "Can Bulk Edit").
    foreach ($policies as $key => $policy) {
      if ($policy->getType() != PhabricatorPolicyType::TYPE_OBJECT) {
        continue;
      }

      $rule = PhabricatorPolicyQuery::getObjectPolicyRule($policy->getPHID());
      if (!$rule) {
        continue;
      }

      $target = nonempty($this->templateObject, $this->object);
      if (!$rule->canApplyToObject($target)) {
        unset($policies[$key]);
        continue;
      }
    }

    $options = array();
    foreach ($policies as $policy) {
      if ($policy->getPHID() == PhabricatorPolicies::POLICY_PUBLIC) {
        // Never expose "Public" for capabilities which don't support it.
        $capobj = PhabricatorPolicyCapability::getCapabilityByKey($capability);
        if (!$capobj || !$capobj->shouldAllowPublicPolicySetting()) {
          continue;
        }
      }

      $policy_short_name = id(new PhutilUTF8StringTruncator())
        ->setMaximumGlyphs(28)
        ->truncateString($policy->getName());

      $options[$policy->getType()][$policy->getPHID()] = array(
        'name' => $policy_short_name,
        'full' => $policy->getName(),
        'icon' => $policy->getIcon(),
      );
    }

    // If we were passed several custom policy options, throw away the ones
    // which aren't the value for this capability. For example, an object might
    // have a custom view pollicy and a custom edit policy. When we render
    // the selector for "Can View", we don't want to show the "Can Edit"
    // custom policy -- if we did, the menu would look like this:
    //
    //   Custom
    //     Custom Policy
    //     Custom Policy
    //
    // ...where one is the "view" custom policy, and one is the "edit" custom
    // policy.

    $type_custom = PhabricatorPolicyType::TYPE_CUSTOM;
    if (!empty($options[$type_custom])) {
      $options[$type_custom] = array_select_keys(
        $options[$type_custom],
        array($this->getValue()));
    }

    // If there aren't any custom policies, add a placeholder policy so we
    // render a menu item. This allows the user to switch to a custom policy.

    if (empty($options[$type_custom])) {
      $placeholder = new PhabricatorPolicy();
      $placeholder->setName(pht('Custom Policy...'));
      $options[$type_custom][$this->getCustomPolicyPlaceholder()] = array(
        'name' => $placeholder->getName(),
        'full' => $placeholder->getName(),
        'icon' => $placeholder->getIcon(),
      );
    }

    $options = array_select_keys(
      $options,
      array(
        PhabricatorPolicyType::TYPE_GLOBAL,
        PhabricatorPolicyType::TYPE_OBJECT,
        PhabricatorPolicyType::TYPE_USER,
        PhabricatorPolicyType::TYPE_CUSTOM,
        PhabricatorPolicyType::TYPE_PROJECT,
      ));

    return $options;
  }

  protected function renderInput() {
    if (!$this->object) {
      throw new PhutilInvalidStateException('setPolicyObject');
    }
    if (!$this->capability) {
      throw new PhutilInvalidStateException('setCapability');
    }

    $policy = $this->object->getPolicy($this->capability);
    if (!$policy) {
      // TODO: Make this configurable.
      $policy = PhabricatorPolicies::POLICY_USER;
    }

    if (!$this->getValue()) {
      $this->setValue($policy);
    }

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
        ->setIconFont($icon);
    }


    if ($this->templatePHIDType) {
      $context_path = 'template/'.$this->templatePHIDType.'/';
    } else {
      $object_phid = $this->object->getPHID();
      if ($object_phid) {
        $context_path = 'object/'.$object_phid.'/';
      } else {
        $object_type = phid_get_type($this->object->generatePHID());
        $context_path = 'type/'.$object_type.'/';
      }
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
        'capability' => $this->capability,
        'editURI' => '/policy/edit/'.$context_path,
        'customPlaceholder' => $this->getCustomPolicyPlaceholder(),
      ));

    $selected = idx($flat_options, $this->getValue(), array());
    $selected_icon = idx($selected, 'icon');
    $selected_name = idx($selected, 'name');

    $spaces_control = $this->buildSpacesControl();

    return phutil_tag(
      'div',
      array(
      ),
      array(
        $spaces_control,
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
                idx($icons, $selected_icon),
                $selected_name,
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

  private function getCustomPolicyPlaceholder() {
    return 'custom:placeholder';
  }

  private function buildSpacesControl() {
    if ($this->capability != PhabricatorPolicyCapability::CAN_VIEW) {
      return null;
    }

    if (!($this->object instanceof PhabricatorSpacesInterface)) {
      return null;
    }

    $viewer = $this->getUser();
    if (!PhabricatorSpacesNamespaceQuery::getViewerSpacesExist($viewer)) {
      return null;
    }

    $space_phid = $this->getSpacePHID();
    if ($space_phid === null) {
      $space_phid = $viewer->getDefaultSpacePHID();
    }

    $select = AphrontFormSelectControl::renderSelectTag(
      $space_phid,
      PhabricatorSpacesNamespaceQuery::getSpaceOptionsForViewer(
        $viewer,
        $space_phid),
      array(
        'name' => 'spacePHID',
      ));

    return $select;
  }

}
