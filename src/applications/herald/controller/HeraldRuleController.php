<?php

final class HeraldRuleController extends HeraldController {

  private $id;
  private $filter;

  public function willProcessRequest(array $data) {
    $this->id = (int)idx($data, 'id');
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $content_type_map = HeraldAdapter::getEnabledAdapterMap();
    $rule_type_map = HeraldRuleTypeConfig::getRuleTypeMap();

    if ($this->id) {
      $id = $this->id;
      $rule = id(new HeraldRuleQuery())
        ->setViewer($user)
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
      $cancel_uri = $this->getApplicationURI("rule/{$id}/");
    } else {
      $rule = new HeraldRule();
      $rule->setAuthorPHID($user->getPHID());
      $rule->setMustMatchAll(1);

      $content_type = $request->getStr('content_type');
      $rule->setContentType($content_type);

      $rule_type = $request->getStr('rule_type');
      if (!isset($rule_type_map[$rule_type])) {
        $rule_type = HeraldRuleTypeConfig::RULE_TYPE_GLOBAL;
      }
      $rule->setRuleType($rule_type);

      $cancel_uri = $this->getApplicationURI();
    }

    $adapter = HeraldAdapter::getAdapterForContentType($rule->getContentType());

    $local_version = id(new HeraldRule())->getConfigVersion();
    if ($rule->getConfigVersion() > $local_version) {
      throw new Exception(
        "This rule was created with a newer version of Herald. You can not ".
        "view or edit it in this older version. Upgrade your Phabricator ".
        "deployment.");
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
        $uri = $this->getApplicationURI("rule/{$id}/");
        return id(new AphrontRedirectResponse())->setURI($uri);
      }
    }

    if ($errors) {
      $error_view = new AphrontErrorView();
      $error_view->setTitle(pht('Form Errors'));
      $error_view->setErrors($errors);
    } else {
      $error_view = null;
    }

    $must_match_selector = $this->renderMustMatchSelector($rule);
    $repetition_selector = $this->renderRepetitionSelector($rule, $adapter);

    $handles = $this->loadHandlesForRule($rule);

    require_celerity_resource('herald-css');

    $content_type_name = $content_type_map[$rule->getContentType()];
    $rule_type_name = $rule_type_map[$rule->getRuleType()];

    $form = id(new AphrontFormView())
      ->setUser($user)
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

    $form
      ->appendChild(
        id(new AphrontFormMarkupControl())
          ->setValue(pht(
            "This %s rule triggers for %s.",
            phutil_tag('strong', array(), $rule_type_name),
            phutil_tag('strong', array(), $content_type_name))))
      ->appendChild(
        id(new AphrontFormInsetView())
          ->setTitle(pht('Conditions'))
          ->setRightButton(javelin_tag(
            'a',
            array(
              'href' => '#',
              'class' => 'button green',
              'sigil' => 'create-condition',
              'mustcapture' => true
            ),
            pht('New Condition')))
          ->setDescription(
            pht('When %s these conditions are met:', $must_match_selector))
          ->setContent(javelin_tag(
            'table',
            array(
              'sigil' => 'rule-conditions',
              'class' => 'herald-condition-table'
            ),
            '')))
      ->appendChild(
        id(new AphrontFormInsetView())
          ->setTitle(pht('Action'))
          ->setRightButton(javelin_tag(
            'a',
            array(
              'href' => '#',
              'class' => 'button green',
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
        ? pht('Edit Herald Rule')
        : pht('Create Herald Rule');

    $form_box = id(new PHUIFormBoxView())
      ->setHeaderText($title)
      ->setFormError($error_view)
      ->setForm($form);

    $crumbs = $this
      ->buildApplicationCrumbs()
      ->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName($title));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $form_box,
      ),
      array(
        'title' => pht('Edit Rule'),
        'device' => true,
      ));
  }

  private function saveRule(HeraldAdapter $adapter, $rule, $request) {
    $rule->setName($request->getStr('name'));
    $match_all = ($request->getStr('must_match') == 'all');
    $rule->setMustMatchAll((int)$match_all);

    $repetition_policy_param = $request->getStr('repetition_policy');
    $rule->setRepetitionPolicy(
      HeraldRepetitionPolicyConfig::toInt($repetition_policy_param));

    $e_name = true;
    $errors = array();

    if (!strlen($rule->getName())) {
      $e_name = pht("Required");
      $errors[] = pht("Rule must have a name.");
    }

    $data = json_decode($request->getStr('rule'), true);
    if (!is_array($data) ||
        !$data['conditions'] ||
        !$data['actions']) {
      throw new Exception("Failed to decode rule data.");
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

      $obj = new HeraldAction();
      $obj->setAction($action[0]);
      $obj->setTarget($action[1]);

      try {
        $adapter->willSaveAction($rule, $obj);
      } catch (HeraldInvalidActionException $ex) {
        $errors[] = $ex;
      }

      $actions[] = $obj;
    }

    $rule->attachConditions($conditions);
    $rule->attachActions($actions);

    if (!$errors) {
      try {

        $edit_action = $rule->getID() ? 'edit' : 'create';

        $rule->openTransaction();
          $rule->save();
          $rule->saveConditions($conditions);
          $rule->saveActions($actions);
          $rule->logEdit($request->getUser()->getPHID(), $edit_action);
        $rule->saveTransaction();

      } catch (AphrontQueryDuplicateKeyException $ex) {
        $e_name = pht("Not Unique");
        $errors[] = pht("Rule name is not unique. Choose a unique name.");
      }
    }

    return array($e_name, $errors);
  }

  private function setupEditorBehavior(
    HeraldRule $rule,
    array $handles,
    HeraldAdapter $adapter) {

    $serial_conditions = array(
      array('default', 'default', ''),
    );

    if ($rule->getConditions()) {
      $serial_conditions = array();
      foreach ($rule->getConditions() as $condition) {

        $value = $condition->getValue();
        if (is_array($value)) {
          $value_map = array();
          foreach ($value as $k => $fbid) {
            $value_map[$fbid] = $handles[$fbid]->getName();
          }
          $value = $value_map;
        }

        $serial_conditions[] = array(
          $condition->getFieldName(),
          $condition->getFieldCondition(),
          $value,
        );
      }
    }

    $serial_actions = array(
      array('default', ''),
    );
    if ($rule->getActions()) {
      $serial_actions = array();
      foreach ($rule->getActions() as $action) {

        switch ($action->getAction()) {
          case HeraldAdapter::ACTION_FLAG:
            $current_value = $action->getTarget();
            break;
          default:
            $target_map = array();
            foreach ((array)$action->getTarget() as $fbid) {
              $target_map[$fbid] = $handles[$fbid]->getName();
            }
            $current_value = $target_map;
            break;
        }

        $serial_actions[] = array(
          $action->getAction(),
          $current_value,
        );
      }
    }

    $all_rules = $this->loadRulesThisRuleMayDependUpon($rule);
    $all_rules = mpull($all_rules, 'getName', 'getID');
    asort($all_rules);

    $all_fields = $adapter->getFieldNameMap();
    $all_conditions = $adapter->getConditionNameMap();
    $all_actions = $adapter->getActionNameMap($rule->getRuleType());

    $fields = $adapter->getFields();
    $field_map = array_select_keys($all_fields, $fields);

    $actions = $adapter->getActions($rule->getRuleType());
    $action_map = array_select_keys($all_actions, $actions);

    $config_info = array();
    $config_info['fields'] = $field_map;
    $config_info['conditions'] = $all_conditions;
    $config_info['actions'] = $action_map;

    foreach ($config_info['fields'] as $field => $name) {
      $field_conditions = $adapter->getConditionsForField($field);
      $config_info['conditionMap'][$field] = $field_conditions;
    }

    foreach ($config_info['fields'] as $field => $fname) {
      foreach ($config_info['conditionMap'][$field] as $condition) {
        $value_type = $adapter->getValueTypeForFieldAndCondition(
          $field,
          $condition);
        $config_info['values'][$field][$condition] = $value_type;
      }
    }

    $config_info['rule_type'] = $rule->getRuleType();

    foreach ($config_info['actions'] as $action => $name) {
      $config_info['targets'][$action] = $adapter->getValueTypeForAction(
        $action,
       $rule->getRuleType());
    }

    Javelin::initBehavior(
      'herald-rule-editor',
      array(
        'root' => 'herald-rule-edit-form',
        'conditions' => (object)$serial_conditions,
        'actions' => (object)$serial_actions,
        'template' => $this->buildTokenizerTemplates() + array(
          'rules' => $all_rules,
          'colors' => PhabricatorFlagColor::getColorNameMap(),
          'defaultColor' => PhabricatorFlagColor::COLOR_BLUE,
        ),
        'author' => array($rule->getAuthorPHID() =>
                          $handles[$rule->getAuthorPHID()]->getName()),
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
      'source' => array(
        'email'       => '/typeahead/common/mailable/',
        'user'        => '/typeahead/common/users/',
        'repository'  => '/typeahead/common/repositories/',
        'package'     => '/typeahead/common/packages/',
        'project'     => '/typeahead/common/projects/',
      ),
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

    if ($rule->getRuleType() == HeraldRuleTypeConfig::RULE_TYPE_PERSONAL) {
      // Personal rules may depend upon your other personal rules.
      $all_rules += id(new HeraldRuleQuery())
        ->setViewer($viewer)
        ->withRuleTypes(array(HeraldRuleTypeConfig::RULE_TYPE_PERSONAL))
        ->withContentTypes(array($rule->getContentType()))
        ->withAuthorPHIDs(array($rule->getAuthorPHID()))
        ->execute();
    }

    // A rule can not depend upon itself.
    unset($all_rules[$rule->getID()]);

    return $all_rules;
  }


}
