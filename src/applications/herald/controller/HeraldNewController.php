<?php

final class HeraldNewController extends HeraldController {

  private $contentType;
  private $ruleType;

  public function willProcessRequest(array $data) {
    $this->contentType = idx($data, 'type');
    $this->ruleType = idx($data, 'rule_type');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $content_type_map = HeraldAdapter::getEnabledAdapterMap($user);
    if (empty($content_type_map[$this->contentType])) {
      $this->contentType = head_key($content_type_map);
    }

    $rule_type_map = HeraldRuleTypeConfig::getRuleTypeMap();
    if (empty($rule_type_map[$this->ruleType])) {
      $this->ruleType = HeraldRuleTypeConfig::RULE_TYPE_PERSONAL;
    }

    // Reorder array to put "personal" first.
    $rule_type_map = array_select_keys(
      $rule_type_map,
      array(
        HeraldRuleTypeConfig::RULE_TYPE_PERSONAL,
      )) + $rule_type_map;

    list($can_global, $global_link) = $this->explainApplicationCapability(
      HeraldCapabilityManageGlobalRules::CAPABILITY,
      pht('You have permission to create and manage global rules.'),
      pht('You do not have permission to create or manage global rules.'));

    $captions = array(
      HeraldRuleTypeConfig::RULE_TYPE_PERSONAL =>
        pht(
          'Personal rules notify you about events. You own them, but they can '.
          'only affect you. Personal rules only trigger for objects you have '.
          'permission to see.'),
      HeraldRuleTypeConfig::RULE_TYPE_GLOBAL =>
        array(
          pht(
            'Global rules notify anyone about events. Global rules can '.
            'bypass access control policies and act on any object.'),
          $global_link,
        ),
    );

    $radio = id(new AphrontFormRadioButtonControl())
      ->setLabel(pht('Type'))
      ->setName('rule_type')
      ->setValue($this->ruleType);

    foreach ($rule_type_map as $value => $name) {
      $disabled = ($value == HeraldRuleTypeConfig::RULE_TYPE_GLOBAL) &&
                  (!$can_global);

      $radio->addButton(
        $value,
        $name,
        idx($captions, $value),
        $disabled ? 'disabled' : null,
        $disabled);
    }

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->setAction('/herald/edit/')
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('New Rule for'))
          ->setName('content_type')
          ->setValue($this->contentType)
          ->setOptions($content_type_map))
      ->appendChild($radio)
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Create Rule'))
          ->addCancelButton($this->getApplicationURI()));

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Create Herald Rule'))
      ->setForm($form);

    $crumbs = $this
      ->buildApplicationCrumbs()
      ->addTextCrumb(pht('Create Rule'));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $form_box,
      ),
      array(
        'title' => pht('Create Herald Rule'),
        'device' => true,
      ));
  }

}
