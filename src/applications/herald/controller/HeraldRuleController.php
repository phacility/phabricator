<?php

final class HeraldRuleController extends HeraldController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $content_type_map = HeraldAdapter::getEnabledAdapterMap($viewer);
    $rule_type_map = HeraldRuleTypeConfig::getRuleTypeMap();

    if ($id) {
      $rule = id(new HeraldRuleQuery())
        ->setViewer($viewer)
        ->withIDs(array($id))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->executeOne();
      if (!$rule) {
        return new Aphront404Response();
      }
      $cancel_uri = '/'.$rule->getMonogram();
    } else {
      $new_uri = $this->getApplicationURI('new/');

      $rule = new HeraldRule();
      $rule->setAuthorPHID($viewer->getPHID());
      $rule->setMustMatchAll(1);

      $content_type = $request->getStr('content_type');
      $rule->setContentType($content_type);

      $rule_type = $request->getStr('rule_type');
      if (!isset($rule_type_map[$rule_type])) {
        return $this->newDialog()
          ->setTitle(pht('Invalid Rule Type'))
          ->appendParagraph(
            pht(
              'The selected rule type ("%s") is not recognized by Herald.',
              $rule_type))
          ->addCancelButton($new_uri);
      }
      $rule->setRuleType($rule_type);

      try {
        $adapter = HeraldAdapter::getAdapterForContentType(
          $rule->getContentType());
      } catch (Exception $ex) {
        return $this->newDialog()
          ->setTitle(pht('Invalid Content Type'))
          ->appendParagraph(
            pht(
              'The selected content type ("%s") is not recognized by '.
              'Herald.',
              $rule->getContentType()))
          ->addCancelButton($new_uri);
      }

      if (!$adapter->supportsRuleType($rule->getRuleType())) {
        return $this->newDialog()
          ->setTitle(pht('Rule/Content Mismatch'))
          ->appendParagraph(
            pht(
              'The selected rule type ("%s") is not supported by the selected '.
              'content type ("%s").',
              $rule->getRuleType(),
              $rule->getContentType()))
          ->addCancelButton($new_uri);
      }

      if ($rule->isObjectRule()) {
        $rule->setTriggerObjectPHID($request->getStr('targetPHID'));
        $object = id(new PhabricatorObjectQuery())
          ->setViewer($viewer)
          ->withPHIDs(array($rule->getTriggerObjectPHID()))
          ->requireCapabilities(
            array(
              PhabricatorPolicyCapability::CAN_VIEW,
              PhabricatorPolicyCapability::CAN_EDIT,
            ))
          ->executeOne();
        if (!$object) {
          throw new Exception(
            pht('No valid object provided for object rule!'));
        }

        if (!$adapter->canTriggerOnObject($object)) {
          throw new Exception(
            pht('Object is of wrong type for adapter!'));
        }
      }

      $cancel_uri = $this->getApplicationURI();
    }

    if ($rule->isGlobalRule()) {
      $this->requireApplicationCapability(
        HeraldManageGlobalRulesCapability::CAPABILITY);
    }

    $adapter = HeraldAdapter::getAdapterForContentType($rule->getContentType());

    $local_version = id(new HeraldRule())->getConfigVersion();
    if ($rule->getConfigVersion() > $local_version) {
      throw new Exception(
        pht(
          'This rule was created with a newer version of Herald. You can not '.
          'view or edit it in this older version. Upgrade your Phabricator '.
          'deployment.'));
    }

    // Upgrade rule version to our version, since we might add newly-defined
    // conditions, etc.
    $rule->setConfigVersion($local_version);

    $rule_conditions = $rule->loadConditions();
    $rule_actions = $rule->loadActions();

    $rule->attachConditions($rule_conditions);
    $rule->attachActions($rule_actions);

    $e_name = true;
    $errors = array();
    if ($request->isFormPost() && $request->getStr('save')) {
      list($e_name, $errors) = $this->saveRule($adapter, $rule, $request);
      if (!$errors) {
        $id = $rule->getID();
        $uri = '/'.$rule->getMonogram();
        return id(new AphrontRedirectResponse())->setURI($uri);
      }
    }

    $must_match_selector = $this->renderMustMatchSelector($rule);
    $repetition_selector = $this->renderRepetitionSelector($rule, $adapter);

    $handles = $this->loadHandlesForRule($rule);

    require_celerity_resource('herald-css');

    $content_type_name = $content_type_map[$rule->getContentType()];
    $rule_type_name = $rule_type_map[$rule->getRuleType()];

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->setID('herald-rule-edit-form')
      ->addHiddenInput('content_type', $rule->getContentType())
      ->addHiddenInput('rule_type', $rule->getRuleType())
      ->addHiddenInput('save', 1)
      ->appendChild(
        // Build this explicitly (instead of using addHiddenInput())
        // so we can add a sigil to it.
        javelin_tag(
          'input',
          array(
            'type'  => 'hidden',
            'name'  => 'rule',
            'sigil' => 'rule',
          )))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Rule Name'))
          ->setName('name')
          ->setError($e_name)
          ->setValue($rule->getName()));

    $trigger_object_control = false;
    if ($rule->isObjectRule()) {
      $trigger_object_control = id(new AphrontFormStaticControl())
        ->setValue(
          pht(
            'This rule triggers for %s.',
            $handles[$rule->getTriggerObjectPHID()]->renderLink()));
    }


    $form
      ->appendChild(
        id(new AphrontFormMarkupControl())
          ->setValue(pht(
            'This %s rule triggers for %s.',
            phutil_tag('strong', array(), $rule_type_name),
            phutil_tag('strong', array(), $content_type_name))))
      ->appendChild($trigger_object_control)
      ->appendChild(
        id(new PHUIFormInsetView())
          ->setTitle(pht('Conditions'))
          ->setRightButton(javelin_tag(
            'a',
            array(
              'href' => '#',
              'class' => 'button button-green',
              'sigil' => 'create-condition',
              'mustcapture' => true,
            ),
            pht('New Condition')))
          ->setDescription(
            pht('When %s these conditions are met:', $must_match_selector))
          ->setContent(javelin_tag(
            'table',
            array(
              'sigil' => 'rule-conditions',
              'class' => 'herald-condition-table',
            ),
            '')))
      ->appendChild(
        id(new PHUIFormInsetView())
          ->setTitle(pht('Action'))
          ->setRightButton(javelin_tag(
            'a',
            array(
              'href' => '#',
              'class' => 'button button-green',
              'sigil' => 'create-action',
              'mustcapture' => true,
            ),
            pht('New Action')))
          ->setDescription(pht(
            'Take these actions %s this rule matches:',
            $repetition_selector))
          ->setContent(javelin_tag(
              'table',
              array(
                'sigil' => 'rule-actions',
                'class' => 'herald-action-table',
              ),
              '')))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Save Rule'))
          ->addCancelButton($cancel_uri));

    $this->setupEditorBehavior($rule, $handles, $adapter);

    $title = $rule->getID()
        ? pht('Edit Herald Rule: %s', $rule->getName())
        : pht('Create Herald Rule: %s', idx($content_type_map, $content_type));

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->setBackground(PHUIObjectBoxView::WHITE_CONFIG)
      ->setFormErrors($errors)
      ->setForm($form);

    $crumbs = $this
      ->buildApplicationCrumbs()
      ->addTextCrumb($title)
      ->setBorder(true);

    $view = id(new PHUITwoColumnView())
      ->setFooter($form_box);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild(
        array(
          $view,
      ));
  }

  private function saveRule(HeraldAdapter $adapter, $rule, $request) {
    $new_name = $request->getStr('name');
    $match_all = ($request->getStr('must_match') == 'all');

    $repetition_policy_param = $request->getStr('repetition_policy');

    $e_name = true;
    $errors = array();

    if (!strlen($new_name)) {
      $e_name = pht('Required');
      $errors[] = pht('Rule must have a name.');
    }

    $data = null;
    try {
      $data = phutil_json_decode($request->getStr('rule'));
    } catch (PhutilJSONParserException $ex) {
      throw new PhutilProxyException(
        pht('Failed to decode rule data.'),
        $ex);
    }

    if (!is_array($data) ||
        !$data['conditions'] ||
        !$data['actions']) {
      throw new Exception(pht('Failed to decode rule data.'));
    }

    $conditions = array();
    foreach ($data['conditions'] as $condition) {
      if ($condition === null) {
        // We manage this as a sparse array on the client, so may receive
        // NULL if conditions have been removed.
        continue;
      }

      $obj = new HeraldCondition();
      $obj->setFieldName($condition[0]);
      $obj->setFieldCondition($condition[1]);

      if (is_array($condition[2])) {
        $obj->setValue(array_keys($condition[2]));
      } else {
        $obj->setValue($condition[2]);
      }

      try {
        $adapter->willSaveCondition($obj);
      } catch (HeraldInvalidConditionException $ex) {
        $errors[] = $ex->getMessage();
      }

      $conditions[] = $obj;
    }

    $actions = array();
    foreach ($data['actions'] as $action) {
      if ($action === null) {
        // Sparse on the client; removals can give us NULLs.
        continue;
      }

      if (!isset($action[1])) {
        // Legitimate for any action which doesn't need a target, like
        // "Do nothing".
        $action[1] = null;
      }

      $obj = new HeraldActionRecord();
      $obj->setAction($action[0]);
      $obj->setTarget($action[1]);

      try {
        $adapter->willSaveAction($rule, $obj);
      } catch (HeraldInvalidActionException $ex) {
        $errors[] = $ex->getMessage();
      }

      $actions[] = $obj;
    }

    if (!$errors) {
      $new_state = id(new HeraldRuleSerializer())->serializeRuleComponents(
        $match_all,
        $conditions,
        $actions,
        $repetition_policy_param);

      $xactions = array();
      $xactions[] = id(new HeraldRuleTransaction())
        ->setTransactionType(HeraldRuleTransaction::TYPE_EDIT)
        ->setNewValue($new_state);
      $xactions[] = id(new HeraldRuleTransaction())
        ->setTransactionType(HeraldRuleTransaction::TYPE_NAME)
        ->setNewValue($new_name);

      try {
        id(new HeraldRuleEditor())
          ->setActor($this->getViewer())
          ->setContinueOnNoEffect(true)
          ->setContentSourceFromRequest($request)
          ->applyTransactions($rule, $xactions);
        return array(null, null);
      } catch (Exception $ex) {
        $errors[] = $ex->getMessage();
      }
    }

    // mutate current rule, so it would be sent to the client in the right state
    $rule->setMustMatchAll((int)$match_all);
    $rule->setName($new_name);
    $rule->setRepetitionPolicy(
      HeraldRepetitionPolicyConfig::toInt($repetition_policy_param));
    $rule->attachConditions($conditions);
    $rule->attachActions($actions);

    return array($e_name, $errors);
  }

  private function setupEditorBehavior(
    HeraldRule $rule,
    array $handles,
    HeraldAdapter $adapter) {

    $all_rules = $this->loadRulesThisRuleMayDependUpon($rule);
    $all_rules = mpull($all_rules, 'getName', 'getPHID');
    asort($all_rules);

    $all_fields = $adapter->getFieldNameMap();
    $all_conditions = $adapter->getConditionNameMap();
    $all_actions = $adapter->getActionNameMap($rule->getRuleType());

    $fields = $adapter->getFields();
    $field_map = array_select_keys($all_fields, $fields);

    // Populate any fields which exist in the rule but which we don't know the
    // names of, so that saving a rule without touching anything doesn't change
    // it.
    foreach ($rule->getConditions() as $condition) {
      $field_name = $condition->getFieldName();

      if (empty($field_map[$field_name])) {
        $field_map[$field_name] = pht('<Unknown Field "%s">', $field_name);
      }
    }

    $actions = $adapter->getActions($rule->getRuleType());
    $action_map = array_select_keys($all_actions, $actions);

    // Populate any actions which exist in the rule but which we don't know the
    // names of, so that saving a rule without touching anything doesn't change
    // it.
    foreach ($rule->getActions() as $action) {
      $action_name = $action->getAction();

      if (empty($action_map[$action_name])) {
        $action_map[$action_name] = pht('<Unknown Action "%s">', $action_name);
      }
    }

    $config_info = array();
    $config_info['fields'] = $this->getFieldGroups($adapter, $field_map);
    $config_info['conditions'] = $all_conditions;
    $config_info['actions'] = $this->getActionGroups($adapter, $action_map);
    $config_info['valueMap'] = array();

    foreach ($field_map as $field => $name) {
      try {
        $field_conditions = $adapter->getConditionsForField($field);
      } catch (Exception $ex) {
        $field_conditions = array(HeraldAdapter::CONDITION_UNCONDITIONALLY);
      }
      $config_info['conditionMap'][$field] = $field_conditions;
    }

    foreach ($field_map as $field => $fname) {
      foreach ($config_info['conditionMap'][$field] as $condition) {
        $value_key = $adapter->getValueTypeForFieldAndCondition(
          $field,
          $condition);

        if ($value_key instanceof HeraldFieldValue) {
          $value_key->setViewer($this->getViewer());

          $spec = $value_key->getControlSpecificationDictionary();
          $value_key = $value_key->getFieldValueKey();
          $config_info['valueMap'][$value_key] = $spec;
        }

        $config_info['values'][$field][$condition] = $value_key;
      }
    }

    $config_info['rule_type'] = $rule->getRuleType();

    foreach ($action_map as $action => $name) {
      try {
        $value_key = $adapter->getValueTypeForAction(
          $action,
         $rule->getRuleType());
      } catch (Exception $ex) {
        $value_key = new HeraldEmptyFieldValue();
      }

      if ($value_key instanceof HeraldFieldValue) {
        $value_key->setViewer($this->getViewer());

        $spec = $value_key->getControlSpecificationDictionary();
        $value_key = $value_key->getFieldValueKey();
        $config_info['valueMap'][$value_key] = $spec;
      }

      $config_info['targets'][$action] = $value_key;
    }

    $default_group = head($config_info['fields']);
    $default_field = head_key($default_group['options']);
    $default_condition = head($config_info['conditionMap'][$default_field]);
    $default_actions = head($config_info['actions']);
    $default_action = head_key($default_actions['options']);

    if ($rule->getConditions()) {
      $serial_conditions = array();
      foreach ($rule->getConditions() as $condition) {
        $value = $adapter->getEditorValueForCondition(
          $this->getViewer(),
          $condition);

        $serial_conditions[] = array(
          $condition->getFieldName(),
          $condition->getFieldCondition(),
          $value,
        );
      }
    } else {
      $serial_conditions = array(
        array($default_field, $default_condition, null),
      );
    }

    if ($rule->getActions()) {
      $serial_actions = array();
      foreach ($rule->getActions() as $action) {
        $value = $adapter->getEditorValueForAction(
          $this->getViewer(),
          $action);

        $serial_actions[] = array(
          $action->getAction(),
          $value,
        );
      }
    } else {
      $serial_actions = array(
        array($default_action, null),
      );
    }

    Javelin::initBehavior(
      'herald-rule-editor',
      array(
        'root' => 'herald-rule-edit-form',
        'default' => array(
          'field' => $default_field,
          'condition' => $default_condition,
          'action' => $default_action,
        ),
        'conditions' => (object)$serial_conditions,
        'actions' => (object)$serial_actions,
        'template' => $this->buildTokenizerTemplates() + array(
          'rules' => $all_rules,
        ),
        'info' => $config_info,
      ));
  }

  private function loadHandlesForRule($rule) {
    $phids = array();

    foreach ($rule->getActions() as $action) {
      if (!is_array($action->getTarget())) {
        continue;
      }
      foreach ($action->getTarget() as $target) {
        $target = (array)$target;
        foreach ($target as $phid) {
          $phids[] = $phid;
        }
      }
    }

    foreach ($rule->getConditions() as $condition) {
      $value = $condition->getValue();
      if (is_array($value)) {
        foreach ($value as $phid) {
          $phids[] = $phid;
        }
      }
    }

    $phids[] = $rule->getAuthorPHID();

    if ($rule->isObjectRule()) {
      $phids[] = $rule->getTriggerObjectPHID();
    }

    return $this->loadViewerHandles($phids);
  }


  /**
   * Render the selector for the "When (all of | any of) these conditions are
   * met:" element.
   */
  private function renderMustMatchSelector($rule) {
    return AphrontFormSelectControl::renderSelectTag(
      $rule->getMustMatchAll() ? 'all' : 'any',
      array(
        'all' => pht('all of'),
        'any' => pht('any of'),
      ),
      array(
        'name' => 'must_match',
      ));
  }


  /**
   * Render the selector for "Take these actions (every time | only the first
   * time) this rule matches..." element.
   */
  private function renderRepetitionSelector($rule, HeraldAdapter $adapter) {
    $repetition_policy = HeraldRepetitionPolicyConfig::toString(
      $rule->getRepetitionPolicy());

    $repetition_options = $adapter->getRepetitionOptions();
    $repetition_names = HeraldRepetitionPolicyConfig::getMap();
    $repetition_map = array_select_keys($repetition_names, $repetition_options);

    if (count($repetition_map) < 2) {
      return head($repetition_names);
    } else {
      return AphrontFormSelectControl::renderSelectTag(
        $repetition_policy,
        $repetition_map,
        array(
          'name' => 'repetition_policy',
        ));
    }
  }


  protected function buildTokenizerTemplates() {
    $template = new AphrontTokenizerTemplateView();
    $template = $template->render();
    return array(
      'markup' => $template,
    );
  }


  /**
   * Load rules for the "Another Herald rule..." condition dropdown, which
   * allows one rule to depend upon the success or failure of another rule.
   */
  private function loadRulesThisRuleMayDependUpon(HeraldRule $rule) {
    $viewer = $this->getRequest()->getUser();

    // Any rule can depend on a global rule.
    $all_rules = id(new HeraldRuleQuery())
      ->setViewer($viewer)
      ->withRuleTypes(array(HeraldRuleTypeConfig::RULE_TYPE_GLOBAL))
      ->withContentTypes(array($rule->getContentType()))
      ->execute();

    if ($rule->isObjectRule()) {
      // Object rules may depend on other rules for the same object.
      $all_rules += id(new HeraldRuleQuery())
        ->setViewer($viewer)
        ->withRuleTypes(array(HeraldRuleTypeConfig::RULE_TYPE_OBJECT))
        ->withContentTypes(array($rule->getContentType()))
        ->withTriggerObjectPHIDs(array($rule->getTriggerObjectPHID()))
        ->execute();
    }

    if ($rule->isPersonalRule()) {
      // Personal rules may depend upon your other personal rules.
      $all_rules += id(new HeraldRuleQuery())
        ->setViewer($viewer)
        ->withRuleTypes(array(HeraldRuleTypeConfig::RULE_TYPE_PERSONAL))
        ->withContentTypes(array($rule->getContentType()))
        ->withAuthorPHIDs(array($rule->getAuthorPHID()))
        ->execute();
    }

    // mark disabled rules as disabled since they are not useful as such;
    // don't filter though to keep edit cases sane / expected
    foreach ($all_rules as $current_rule) {
      if ($current_rule->getIsDisabled()) {
        $current_rule->makeEphemeral();
        $current_rule->setName($rule->getName().' '.pht('(Disabled)'));
      }
    }

    // A rule can not depend upon itself.
    unset($all_rules[$rule->getID()]);

    return $all_rules;
  }

  private function getFieldGroups(HeraldAdapter $adapter, array $field_map) {
    $group_map = array();
    foreach ($field_map as $field_key => $field_name) {
      $group_key = $adapter->getFieldGroupKey($field_key);
      $group_map[$group_key][$field_key] = $field_name;
    }

    return $this->getGroups(
      $group_map,
      HeraldFieldGroup::getAllFieldGroups());
  }

  private function getActionGroups(HeraldAdapter $adapter, array $action_map) {
    $group_map = array();
    foreach ($action_map as $action_key => $action_name) {
      $group_key = $adapter->getActionGroupKey($action_key);
      $group_map[$group_key][$action_key] = $action_name;
    }

    return $this->getGroups(
      $group_map,
      HeraldActionGroup::getAllActionGroups());
  }

  private function getGroups(array $item_map, array $group_list) {
    assert_instances_of($group_list, 'HeraldGroup');

    $groups = array();
    foreach ($item_map as $group_key => $options) {
      asort($options);

      $group_object = idx($group_list, $group_key);
      if ($group_object) {
        $group_label = $group_object->getGroupLabel();
        $group_order = $group_object->getSortKey();
      } else {
        $group_label = nonempty($group_key, pht('Other'));
        $group_order = 'Z';
      }

      $groups[] = array(
        'label' => $group_label,
        'options' => $options,
        'order' => $group_order,
      );
    }

    return array_values(isort($groups, 'order'));
  }


}
