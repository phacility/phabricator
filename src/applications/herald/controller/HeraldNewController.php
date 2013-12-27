<?php

final class HeraldNewController extends HeraldController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $content_type_map = HeraldAdapter::getEnabledAdapterMap($user);
    $rule_type_map = HeraldRuleTypeConfig::getRuleTypeMap();

    $errors = array();

    $e_type = null;
    $e_rule = null;

    $step = 0;
    if ($request->isFormPost()) {
      $step = $request->getInt('step');
      $content_type = $request->getStr('content_type');
      if (empty($content_type_map[$content_type])) {
        $errors[] = pht('You must choose a content type for this rule.');
        $e_type = pht('Required');
        $step = 0;
      }

      if (!$errors && $step > 1) {
        $rule_type = $request->getStr('rule_type');
        if (empty($rule_type_map[$rule_type])) {
          $errors[] = pht('You must choose a rule type for this rule.');
          $e_rule = pht('Required');
          $step = 1;
        }
      }

      if (!$errors && $step == 2) {
        $uri = id(new PhutilURI('edit/'))
          ->setQueryParams(
            array(
              'content_type' => $content_type,
              'rule_type' => $rule_type,
            ));
        $uri = $this->getApplicationURI($uri);
        return id(new AphrontRedirectResponse())->setURI($uri);
      }
    }

    if ($errors) {
      $errors = id(new AphrontErrorView())->setErrors($errors);
    }

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->setAction($this->getApplicationURI('new/'));

    $content_types = $this->renderContentTypeControl(
      $content_type_map,
      $e_type);

    $rule_types = $this->renderRuleTypeControl(
      $rule_type_map,
      $e_rule);

    switch ($step) {
      case 0:
      default:
        $form
          ->addHiddenInput('step', 1)
          ->appendChild($content_types);

        $cancel_text = null;
        $cancel_uri = $this->getApplicationURI();
        break;
      case 1:
        $form
          ->addHiddenInput('content_type', $request->getStr('content_type'))
          ->addHiddenInput('step', 2)
          ->appendChild(
            id(new AphrontFormStaticControl())
              ->setLabel(pht('Rule for'))
              ->setValue(
                phutil_tag(
                  'strong',
                  array(),
                  idx($content_type_map, $content_type))))
          ->appendChild($rule_types);

        $cancel_text = pht('Back');
        $cancel_uri = id(new PhutilURI('new/'))
          ->setQueryParams(
            array(
              'content_type' => $request->getStr('content_type'),
              'step' => 1,
            ));
        $cancel_uri = $this->getApplicationURI($cancel_uri);
        break;
    }


    $form
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Continue'))
          ->addCancelButton($cancel_uri, $cancel_text));

    $form_box = id(new PHUIObjectBoxView())
      ->setFormError($errors)
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

  private function renderContentTypeControl(array $content_type_map, $e_type) {
    $request = $this->getRequest();

    $radio = id(new AphrontFormRadioButtonControl())
      ->setLabel(pht('New Rule for'))
      ->setName('content_type')
      ->setValue($request->getStr('content_type'))
      ->setError($e_type);

    foreach ($content_type_map as $value => $name) {
      $adapter = HeraldAdapter::getAdapterForContentType($value);
      $radio->addButton(
        $value,
        $name,
        phutil_escape_html_newlines($adapter->getAdapterContentDescription()));
    }

    return $radio;
  }


  private function renderRuleTypeControl(array $rule_type_map, $e_rule) {
    $request = $this->getRequest();

    // Reorder array to put less powerful rules first.
    $rule_type_map = array_select_keys(
      $rule_type_map,
      array(
        HeraldRuleTypeConfig::RULE_TYPE_PERSONAL,
        HeraldRuleTypeConfig::RULE_TYPE_OBJECT,
        HeraldRuleTypeConfig::RULE_TYPE_GLOBAL,
      )) + $rule_type_map;

    // TODO: Enable this.
    unset($rule_type_map[HeraldRuleTypeConfig::RULE_TYPE_OBJECT]);

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
      ->setValue($request->getStr('rule_type'))
      ->setError($e_rule);

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

    return $radio;
  }
}
